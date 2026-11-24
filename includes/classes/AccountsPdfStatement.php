<?php
/**
 * Reads a personal-account statement PDF into the same "Date,Type,Description,Paid Out,Paid In,Balance"
 * CSV the business account downloads, so one import path serves both (AccountsImport::parseBankCsv()).
 *
 * The PDF has no table, only words at positions, and a transaction takes one to three text rows: the type
 * and first description line start it, and the amount sits on the row that ends it. So the page is read as
 * rows of words placed by `pdftotext -bbox`: a date at the left starts a day, a type code in the type column
 * starts a transaction, description words in the next column are joined, and an amount is paid out, paid in
 * or balance by which of the three right-hand columns it ends in. Everything outside the span between
 * BALANCE BROUGHT FORWARD and BALANCE CARRIED FORWARD on each page is ignored. The opening and closing
 * balances in the account summary are read too, so the caller can check opening + lines = closing.
 *
 * @package accounts
 */
namespace Bitweaver\Accounts;

class AccountsPdfStatement {

	/** Column edges, in PDF points, measured on the bank's layout. */
	const DATE_MAX  = 100;   // a date starts left of here
	const TYPE_MIN  = 105;   // type code column
	const DESC_MIN  = 135;   // description starts here
	const OUT_MAX   = 420;   // an amount ending left of this is paid out
	const IN_MAX    = 500;   // ... left of this, paid in; beyond it, the balance
	const AMOUNT_MIN_X = 340; // an amount word starts right of here (descriptions never do)

	/**
	 * @param  string $pPdfPath  path to the statement PDF
	 * @return array{csv:string,opening:?string,closing:?string,lines:int,warnings:string[]}
	 */
	public static function toCsv( string $pPdfPath ): array {
		$warnings = [];
		$html = self::bbox( $pPdfPath );
		if( $html === null ) {
			return [ 'csv' => '', 'opening' => null, 'closing' => null, 'lines' => 0, 'warnings' => [ 'The PDF could not be read (is pdftotext installed?).' ] ];
		}
		[ $opening, $closing ] = self::summaryBalances( $html );

		$rows = [];          // finished transactions: date, type, desc, out, in, balance
		$date = null;
		$cur = null;         // transaction being built
		$capturing = false;
		foreach( self::pageRows( $html ) as $row ) {
			$text = implode( ' ', array_column( $row, 'w' ) );
			if( strpos( $text, 'BROUGHT FORWARD' ) !== false ) {
				$capturing = true;
				continue;
			}
			if( strpos( $text, 'CARRIED FORWARD' ) !== false ) {
				$capturing = false;
				continue;
			}
			if( $row === 'PAGEBREAK' || !$capturing ) {
				continue;
			}

			$type = null;
			$desc = [];
			$amount = $side = $balance = null;
			$dateWords = [];
			foreach( $row as $w ) {
				if( $w['x0'] < self::DATE_MAX ) {
					$dateWords[] = $w['w'];
				} elseif( $w['x0'] < self::DESC_MIN && $w['x0'] >= self::TYPE_MIN && $type === null ) {
					$type = $w['w'];
				} elseif( $w['x0'] >= self::AMOUNT_MIN_X && preg_match( '/^[\d,]+\.\d\d$/', $w['w'] ) ) {
					$v = str_replace( ',', '', $w['w'] );
					if( $w['x1'] < self::OUT_MAX ) {
						$amount = $v; $side = 'out';
					} elseif( $w['x1'] < self::IN_MAX ) {
						$amount = $v; $side = 'in';
					} else {
						$balance = $v;
					}
				} elseif( $w['x0'] >= self::DESC_MIN ) {
					$desc[] = $w['w'];
				}
			}
			if( $dateWords ) {
				$d = \DateTime::createFromFormat( '!d M y', implode( ' ', $dateWords ) );
				if( $d ) {
					$date = $d->format( 'd M Y' );
				}
			}
			if( $type !== null ) {
				if( $cur !== null ) {
					$warnings[] = "Transaction without an amount near $date: ".$cur['desc'];
				}
				$cur = [ 'date' => $date, 'type' => $type, 'desc' => implode( ' ', $desc ), 'out' => '', 'in' => '', 'balance' => '' ];
			} elseif( $cur !== null ) {
				$cur['desc'] = trim( $cur['desc'].' '.implode( ' ', $desc ) );
			} elseif( $rows && $desc ) {
				// a continuation after an already-finished one-row transaction
				$rows[count( $rows ) - 1]['desc'] = trim( $rows[count( $rows ) - 1]['desc'].' '.implode( ' ', $desc ) );
			}
			if( $amount !== null && $cur !== null ) {
				$cur[$side] = $amount;
				$rows[] = $cur;
				$cur = null;
			}
			if( $balance !== null && $rows ) {
				$rows[count( $rows ) - 1]['balance'] = $balance;
			}
		}
		if( $cur !== null ) {
			$warnings[] = 'The last transaction has no amount: '.$cur['desc'];
		}

		$out = fopen( 'php://memory', 'r+' );
		fputcsv( $out, [ 'Date', 'Type', 'Description', 'Paid Out', 'Paid In', 'Balance' ], ',', '"', '' );
		foreach( $rows as $r ) {
			fputcsv( $out, [ $r['date'], $r['type'], $r['desc'], $r['out'], $r['in'], $r['balance'] ], ',', '"', '' );
		}
		rewind( $out );
		$csv = stream_get_contents( $out );
		fclose( $out );

		// opening + lines = closing
		if( $opening !== null && $closing !== null ) {
			$sum = $opening;
			foreach( $rows as $r ) {
				$sum = bcadd( bcsub( $sum, $r['out'] ?: '0', 2 ), $r['in'] ?: '0', 2 );
			}
			if( bccomp( $sum, $closing, 2 ) !== 0 ) {
				$warnings[] = "Opening $opening plus the lines gives $sum, but the statement closes at $closing.";
			}
		}
		return [ 'csv' => $csv, 'opening' => $opening, 'closing' => $closing, 'lines' => count( $rows ), 'warnings' => $warnings ];
	}

	/** pdftotext -bbox output (all pages), or null when it could not be run. */
	private static function bbox( string $pPath ): ?string {
		$cmd = 'pdftotext -bbox '.escapeshellarg( $pPath ).' - 2>/dev/null';
		$out = shell_exec( $cmd );
		return is_string( $out ) && strpos( $out, '<word' ) !== false ? $out : null;
	}

	/**
	 * All rows of words, page by page, top to bottom, each word left to right; 'PAGEBREAK' marks a new page.
	 *
	 * @return array<int,array|string>
	 */
	private static function pageRows( string $pHtml ): array {
		$ret = [];
		foreach( array_slice( explode( '<page ', $pHtml ), 1 ) as $page ) {
			$words = [];
			if( preg_match_all( '/<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="[\d.]+">([^<]*)<\/word>/', $page, $m, PREG_SET_ORDER ) ) {
				foreach( $m as $w ) {
					$words[] = [ 'x0' => (float)$w[1], 'y' => (float)$w[2], 'x1' => (float)$w[3], 'w' => html_entity_decode( $w[4] ) ];
				}
			}
			usort( $words, fn( $a, $b ) => $a['y'] <=> $b['y'] ?: $a['x0'] <=> $b['x0'] );
			$row = [];
			$rowY = null;
			foreach( $words as $w ) {
				if( $rowY !== null && $w['y'] - $rowY > 2.0 ) {
					usort( $row, fn( $a, $b ) => $a['x0'] <=> $b['x0'] );
					$ret[] = $row;
					$row = [];
				}
				if( !$row ) {
					$rowY = $w['y'];
				}
				$row[] = $w;
			}
			if( $row ) {
				usort( $row, fn( $a, $b ) => $a['x0'] <=> $b['x0'] );
				$ret[] = $row;
			}
			// carried-forward state is kept by the caller across pages; the balance rows reset it there
		}
		return $ret;
	}

	/** Opening and closing balance from the account summary box on page one. */
	private static function summaryBalances( string $pHtml ): array {
		$first = explode( '<page ', $pHtml )[1] ?? '';
		$opening = $closing = null;
		foreach( self::pageRows( '<page '.$first ) as $row ) {
			$text = preg_replace( '/\s+/', '', implode( '', array_column( $row, 'w' ) ) );
			if( preg_match( '/^Opening?Balance£?([\d,]+\.\d\d)/i', $text, $m ) || preg_match( '/^Ope\s*ning/i', implode( ' ', array_column( $row, 'w' ) ) ) && preg_match( '/([\d,]+\.\d\d)$/', $text, $m ) ) {
				$opening = str_replace( ',', '', $m[1] );
			}
			if( preg_match( '/^Closing?Balance£?([\d,]+\.\d\d)/i', $text, $m ) || preg_match( '/^Clos\s*ing/i', implode( ' ', array_column( $row, 'w' ) ) ) && preg_match( '/([\d,]+\.\d\d)$/', $text, $m ) ) {
				$closing = str_replace( ',', '', $m[1] );
			}
		}
		return [ $opening, $closing ];
	}
}
