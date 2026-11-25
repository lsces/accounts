<?php
/**
 * Linking statement lines to their supplier. A business or person contact carries one or more STMT rows: pieces of bank
 * statement wording ("Acmehost", "EXAMPLE TELECOM", "PAYPAL"). Any statement line whose description contains the
 * text, and has no supplier yet, is linked to that contact - when a rule is added (fill forward over what is
 * already loaded) and after every import. A line that already has a supplier is never changed, so a hand-set
 * choice outlives any rule. Matching ignores case; the longest text wins when several match.
 *
 * @package accounts
 */

namespace Bitweaver\Accounts;

use Bitweaver\Liberty\LibertyXref;

class AccountsSupplier {

	/**
	 * A first guess at the text to match from a line's description: the words up to the first one that holds a digit
	 * or an asterisk, i.e. before the invoice number, phone number or reference. "Acmehost INV123" gives
	 * "Acmehost"; "PAYPAL * SOME SHOP 0123456789" gives "PAYPAL". A description that starts with a number (a transfer
	 * to an account number) is suggested whole. The person edits it before saving.
	 */
	public static function suggest( string $pDescription ): string {
		$words = [];
		foreach( preg_split( '/\s+/', trim( html_entity_decode( $pDescription, ENT_QUOTES | ENT_HTML5 ) ) ) as $w ) {
			if( $w === '' || preg_match( '/[\d*]/', $w ) ) {
				break;
			}
			$words[] = $w;
		}
		// a dash or comma left hanging before the reference ('B&M - 10596') is not part of the name
		return $words ? rtrim( implode( ' ', $words ), ' -,:;/' ) : trim( html_entity_decode( $pDescription, ENT_QUOTES | ENT_HTML5 ) );
	}

	/** Every STMT rule: [ 'text' => string, 'contact_id' => int ], longest text first. */
	public static function rules(): array {
		global $gBitDb;
		$rs = $gBitDb->query(
			"SELECT x.`xkey`, x.`content_id` FROM `".BIT_DB_PREFIX."liberty_xref` x
				INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.`content_id` = x.`content_id` AND lc.`content_type_guid` IN ('contactbusiness','contactperson')
				WHERE x.`item` = 'STMT' AND x.`end_date` IS NULL AND x.`xkey` IS NOT NULL"
		);
		$ret = [];
		while( $r = $rs->fetchRow() ) {
			$text = trim( (string)$r['xkey'] );
			if( $text !== '' ) {
				$ret[] = [ 'text' => $text, 'contact_id' => (int)$r['content_id'] ];
			}
		}
		usort( $ret, fn( $a, $b ) => strlen( $b['text'] ) <=> strlen( $a['text'] ) );
		return $ret;
	}

	/** Remembers $pText on the contact (once; case-insensitive). */
	public static function addRule( int $pContactId, string $pText ): bool {
		global $gBitDb;
		$pText = trim( $pText );
		if( $pText === '' ) {
			return false;
		}
		foreach( self::rules() as $r ) {
			if( $r['contact_id'] === $pContactId && strcasecmp( $r['text'], $pText ) === 0 ) {
				return true;
			}
		}
		$xref = new LibertyXref();
		$xref->mContentTypeGuid = (string)$gBitDb->getOne( "SELECT `content_type_guid` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id` = ?", [ $pContactId ] );
		$xref->mPackageGuid     = 'contact';
		$hash = [
			'content_id' => $pContactId,
			'item'       => 'STMT',
			'xorder'     => 1 + (int)$gBitDb->getOne( "SELECT MAX(`xorder`) FROM `".BIT_DB_PREFIX."liberty_xref` WHERE `content_id` = ? AND `item` = 'STMT'", [ $pContactId ] ),
			'xkey'       => $pText,
		];
		return $xref->store( $hash );
	}

	/**
	 * Links every unset statement line, on all accounts or on one, whose description holds a rule's text.
	 *
	 * @param int|null   $pAccountId  limit to one account (content_id), or null for every account
	 * @param array|null $pRules      only these rules ([ 'text', 'contact_id' ]) instead of all of them
	 * @param bool       $pReplace    also re-link lines that already have a different supplier (a hand-set one too);
	 *                                lines already on the rule's contact are left alone
	 * @return int  lines linked
	 */
	public static function fill( ?int $pAccountId = null, ?array $pRules = null, bool $pReplace = false ): int {
		global $gBitDb;
		$rules = $pRules ?? self::rules();
		if( !$rules ) {
			return 0;
		}
		$sql = "SELECT x.`xref_id`, x.`content_id`, x.`item`, x.`data`, x.`xref`, lc.`content_type_guid`
			FROM `".BIT_DB_PREFIX."liberty_xref` x
				INNER JOIN `".BIT_DB_PREFIX."liberty_content` lc ON lc.`content_id` = x.`content_id`
			WHERE lc.`content_type_guid` IN ('accountsaccount','accountspaypal','accountsrent')
				AND x.`item` NOT IN ('CONTACT','OPENING','PLAN','BALANCE') AND x.`end_date` IS NULL"
			.( $pReplace ? '' : " AND ( x.`xref` IS NULL OR x.`xref` = 0 )" );
		$bind = [];
		if( $pAccountId !== null ) {
			$sql .= " AND x.`content_id` = ?";
			$bind[] = $pAccountId;
		}
		$rs = $gBitDb->query( $sql, $bind );
		$todo = [];
		while( $r = $rs->fetchRow() ) {
			$data = !empty( $r['data'] ) ? ( json_decode( $r['data'], true ) ?: [] ) : [];
			$desc = html_entity_decode( (string)( $data['description'] ?? '' ), ENT_QUOTES | ENT_HTML5 );
			foreach( $rules as $rule ) {
				if( stripos( $desc, $rule['text'] ) !== false ) {
					if( (int)$r['xref'] !== $rule['contact_id'] ) {
						$todo[] = [ $r, $rule['contact_id'] ];
					}
					break;
				}
			}
		}
		$n = 0;
		foreach( $todo as [ $r, $contactId ] ) {
			$xref = new LibertyXref();
			$xref->mContentTypeGuid = $r['content_type_guid'];
			$xref->mPackageGuid     = 'accounts';
			$xref->load( (int)$r['xref_id'] );
			$hash = [ 'xref_id' => (int)$r['xref_id'], 'content_id' => (int)$r['content_id'], 'item' => $r['item'], 'xref' => $contactId ];
			if( $xref->store( $hash ) ) {
				$n++;
			}
		}
		return $n;
	}
}
