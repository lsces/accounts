<?php
/**
 * Statement import for a bank account. The bank is the master record, so every statement line becomes
 * one xref row on the account, its item the bank's own Type code (see DEVELOPER.md). Importing is safe to
 * repeat: each line carries an import key (hash of date, amount, type, description and its n-th
 * occurrence in the file), and a key already on the account is skipped, so overlapping statements and a
 * second run of the same file add nothing.
 *
 * @package accounts
 */
namespace Bitweaver\Accounts;

use Bitweaver\Liberty\LibertyXref;

class AccountsImport {

	/** The bank's Type codes that have an item of their own; ')))' is contactless. Anything else goes to BANK_OTHER. */
	const TYPE_ITEMS = [
		'DD' => 'DD', 'BP' => 'BP', 'CR' => 'CR', 'VIS' => 'VIS', ')))' => 'CTL',
		'DR' => 'DR', 'TFR' => 'TFR', 'ATM' => 'ATM', 'OBP' => 'OBP',
	];

	/**
	 * Parse one bank "Date,Type,Description,Paid Out,Paid In,Balance" CSV.
	 *
	 * @param  string        $pCsv     file contents
	 * @param  string        $pSource  file name, kept on each line as its source
	 * @param  \DateTimeZone $pTz      the zone a statement date is midnight in
	 * @return array{lines:array,errors:array}
	 */
	public static function parseBankCsv( string $pCsv, string $pSource, \DateTimeZone $pTz ): array {
		$lines = $errors = [];
		$seen = [];
		$handle = fopen( 'php://memory', 'r+' );
		fwrite( $handle, preg_replace( '/^\xEF\xBB\xBF/', '', $pCsv ) );
		rewind( $handle );
		$rowNo = 0;
		while( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) {
			$rowNo++;
			if( $row === [ null ] || count( $row ) < 5 ) {
				continue;
			}
			if( $rowNo === 1 && strcasecmp( trim( $row[0] ), 'Date' ) === 0 ) {
				continue;
			}
			$date = \DateTime::createFromFormat( '!d M Y', trim( $row[0] ), $pTz );
			$out  = self::money( $row[3] ?? '' );
			$in   = self::money( $row[4] ?? '' );
			if( !$date || ( $out === null && $in === null ) ) {
				$errors[] = "$pSource line $rowNo: not a statement line";
				continue;
			}
			$type   = trim( $row[1] );
			$amount = bcsub( $in ?? '0', $out ?? '0', 2 );
			$desc   = trim( $row[2] );
			$iso    = $date->format( 'Y-m-d' );
			$base   = $iso.'|'.$amount.'|'.$type.'|'.$desc;
			$seen[$base] = ( $seen[$base] ?? 0 ) + 1;
			$balance = self::money( $row[5] ?? '' );
			$lines[] = [
				'date'    => $iso,
				'epoch'   => $date->getTimestamp(),
				'type'    => $type,
				'item'    => self::TYPE_ITEMS[$type] ?? 'BANK_OTHER',
				'desc'    => $desc,
				'amount'  => $amount,
				'balance' => $balance,
				'key'     => 'h'.substr( sha1( $base.'|'.$seen[$base] ), 0, 24 ),
				'source'  => $pSource,
			];
		}
		fclose( $handle );

		return [ 'lines' => $lines, 'errors' => $errors ];
	}

	/** A bank amount as a plain decimal string, or null when blank. */
	private static function money( string $pValue ): ?string {
		$v = str_replace( [ ',', ' ', '£' ], '', trim( $pValue ) );
		return $v === '' || !is_numeric( $v ) ? null : bcadd( $v, '0', 2 );
	}

	/**
	 * Where the printed balances disagree with the lines. The bank prints a balance only on the last line of
	 * a day; between two printed balances the lines in between must add up to the difference.
	 *
	 * @return string[]  one message per disagreement; empty when the file reconciles
	 */
	public static function checkBalances( array $pLines ): array {
		$problems = [];
		$prev = null;
		$sum = '0';
		foreach( $pLines as $l ) {
			$sum = bcadd( $sum, $l['amount'], 2 );
			if( $l['balance'] !== null ) {
				if( $prev !== null && bccomp( bcadd( $prev, $sum, 2 ), $l['balance'], 2 ) !== 0 ) {
					$problems[] = $l['source'].' '.$l['date'].': balance '.$l['balance'].' but the lines give '.bcadd( $prev, $sum, 2 );
				}
				$prev = $l['balance'];
				$sum = '0';
			}
		}
		return $problems;
	}

	/**
	 * The balance before the first line, worked back from the first balance the bank printed: that balance
	 * less everything from the first line up to and including the line carrying it. Only meaningful for the
	 * earliest statement of an account, so the caller uses it only when the account has no lines yet.
	 */
	public static function deriveOpening( array $pLines ): ?string {
		$sum = '0';
		foreach( $pLines as $l ) {
			$sum = bcadd( $sum, $l['amount'], 2 );
			if( $l['balance'] !== null ) {
				return bcsub( $l['balance'], $sum, 2 );
			}
		}
		return null;
	}

	/**
	 * Mark each line as new or already held, by import key, against what the account already has.
	 * Duplicate keys within the batch itself (the same statement given twice) count as already held.
	 */
	public static function classify( AccountsAccount $pAccount, array $pLines ): array {
		$have = [];
		$rs = $pAccount->mDb->query(
			"SELECT `xkey_ext` FROM `".BIT_DB_PREFIX."liberty_xref` WHERE `content_id` = ? AND `item` NOT IN ('CONTACT','OPENING','PLAN','BALANCE') AND `xkey_ext` IS NOT NULL",
			[ (int)$pAccount->mContentId ]
		);
		while( $r = $rs->fetchRow() ) {
			$have[(string)$r['xkey_ext']] = true;
		}
		foreach( $pLines as &$l ) {
			$l['new'] = !isset( $have[$l['key']] );
			$have[$l['key']] = true;
		}
		return $pLines;
	}

	/**
	 * Store the new lines on the account. Each is its own row; one failing is counted and reported, the rest still go in.
	 *
	 * @return array{added:int,failed:int,errors:string[]}
	 */
	public static function store( AccountsAccount $pAccount, array $pLines ): array {
		$added = $failed = 0;
		$errors = [];
		$order = (int)$pAccount->mDb->getOne(
			"SELECT MAX(`xorder`) FROM `".BIT_DB_PREFIX."liberty_xref` WHERE `content_id` = ?",
			[ (int)$pAccount->mContentId ]
		);
		foreach( $pLines as $l ) {
			if( empty( $l['new'] ) ) {
				continue;
			}
			$data = [ 'description' => $l['desc'], 'source' => $l['source'] ];
			if( $l['item'] === 'BANK_OTHER' ) {
				$data['code'] = $l['type'];
			}
			if( $l['balance'] !== null ) {
				$data['balance'] = $l['balance'];
			}
			$xref = new LibertyXref();
			$xref->mContentTypeGuid = $pAccount->mContentTypeGuid;
			$xref->mPackageGuid     = $pAccount->mPackageGuid;
			// store() takes the hash by reference - a named variable, never a literal.
			$hash = [
				'content_id' => (int)$pAccount->mContentId,
				'item'       => $l['item'],
				'xorder'     => ++$order,
				'start_date' => $l['epoch'],
				'xkey'       => $l['amount'],
				'xkey_ext'   => $l['key'],
				'edit'       => json_encode( $data ),
			];
			if( $xref->store( $hash ) ) {
				$added++;
			} else {
				$failed++;
				$errors = array_merge( $errors, array_map( 'strval', $xref->mErrors ) );
			}
		}
		// Link the new lines to suppliers the contacts' statement text already recognises.
		$linked = $added ? AccountsSupplier::fill( (int)$pAccount->mContentId ) : 0;
		return [ 'added' => $added, 'failed' => $failed, 'linked' => $linked, 'errors' => array_values( array_unique( $errors ) ) ];
	}
}
