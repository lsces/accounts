<?php
/**
 * One account (a bank account, a payment-service account, a rent account...) — the base
 * object every statement-line xref row hangs off. Stored as a pure liberty_content record
 * (content_type_guid='accountsaccount'), title = the account's own name; content_id is the only
 * identifier. The Contact record for the bank or service holding the account is linked to it
 * with a CONTACT xref row and supplies the extra detail (sort code, number, addresses) — see
 * this package's MANUAL.md.
 *
 * @package accounts
 */
namespace Bitweaver\Accounts;

use Bitweaver\Liberty\LibertyContent;

defined( 'ACCOUNTSACCOUNT_CONTENT_TYPE_GUID' ) || define( 'ACCOUNTSACCOUNT_CONTENT_TYPE_GUID', 'accountsaccount' );

#[\AllowDynamicProperties]
class AccountsAccount extends LibertyContent {

	/** Content type of this class; a subclass (AccountsPaypal) overrides these three. */
	const GUID        = 'accountsaccount';
	const NAME        = 'Account';
	const NAME_PLURAL = 'Accounts';

	/**
	 * The right class for a stored account, whichever kind it is (bank or PayPal).
	 *
	 * @param int|null $pContentId  Null for a blank account of the base kind.
	 */
	public static function loadFor( $pContentId ): AccountsAccount {
		global $gBitDb;
		$guid = $pContentId ? $gBitDb->getOne( "SELECT `content_type_guid` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id` = ?", [ (int)$pContentId ] ) : null;
		return $guid === AccountsPaypal::GUID ? new AccountsPaypal( null, (int)$pContentId ) : new AccountsAccount( null, (int)$pContentId );
	}

	/**
	 * @param int|null $pDummy      Unused — LibertyBase::getNewObject() always calls
	 *                              `new $class(null, $contentId)`; see HealthDay for the same contract.
	 * @param int|null $pContentId  liberty_content.content_id to load.
	 */
	public function __construct( $pDummy = null, $pContentId = null ) {
		parent::__construct();
		$this->mContentTypeGuid = static::GUID;
		$this->mContentId = (int)( $pContentId ?? $pDummy );

		$this->registerContentType(
			static::GUID, [
				'content_type_guid'   => static::GUID,
				'content_name'        => static::NAME,
				'content_name_plural' => static::NAME_PLURAL,
				'handler_class'       => ( new \ReflectionClass( static::class ) )->getShortName(),
				'handler_package'     => 'accounts',
				'handler_file'        => ( new \ReflectionClass( static::class ) )->getShortName().'.php',
				'maintainer_url'      => 'https://www.bitweaver.org',
		], );

		$this->mViewContentPerm    = 'p_accounts_view';
		$this->mCreateContentPerm  = 'p_accounts_create';
		$this->mUpdateContentPerm  = 'p_accounts_update';
		$this->mAdminContentPerm   = 'p_accounts_admin';
		$this->mExpungeContentPerm = 'p_accounts_expunge';
	}

	/**
	 * @param  array $pLookupHash  Must contain 'content_id'.
	 * @return static|null
	 */
	public static function lookup( $pLookupHash ) {
		$ret = null;
		$lookupContentId = null;
		if( !empty( $pLookupHash['content_id'] ) && is_numeric( $pLookupHash['content_id'] ) ) {
			$lookupContentId = (int)$pLookupHash['content_id'];
		}
		if( static::verifyId( $lookupContentId ) ) {
			$ret = static::getLibertyObject( $lookupContentId, static::GUID );
		}
		return $ret;
	}

	/**
	 * Load the account into $this->mInfo from liberty_content.
	 *
	 * @return int|null  Row count (> 0) on success, or null if mContentId is not set.
	 */
	public function load() {
		if( $this->verifyId( $this->mContentId ) ) {
			$selectSql = $joinSql = $whereSql = '';
			$bindVars = [];

			$whereSql = " WHERE lc.`content_id` = ? AND lc.`content_type_guid` = ?";
			$bindVars[] = $this->mContentId;
			$bindVars[] = static::GUID;

			$this->getServicesSql( 'content_load_sql_function', $selectSql, $joinSql, $whereSql, $bindVars, $this );

			$sql = "SELECT lc.* $selectSql
						, uue.`login` AS `modifier_user`, uue.`real_name` AS `modifier_real_name`
						, uuc.`login` AS `creator_user`, uuc.`real_name` AS `creator_real_name`
					FROM `".BIT_DB_PREFIX."liberty_content` lc
						LEFT JOIN `".BIT_DB_PREFIX."users_users` uue ON (uue.`user_id` = lc.`modifier_user_id`)
						LEFT JOIN `".BIT_DB_PREFIX."users_users` uuc ON (uuc.`user_id` = lc.`user_id`) $joinSql
					$whereSql";
			if( $this->mInfo = $this->mDb->getRow( $sql, $bindVars ) ) {
				$this->mContentId       = $this->mInfo['content_id'];
				$this->mContentTypeGuid = $this->mInfo['content_type_guid'];
				$this->mInfo['creator'] = $this->mInfo['creator_real_name'] ?? $this->mInfo['creator_user'];
				$this->mInfo['editor']  = $this->mInfo['modifier_real_name'] ?? $this->mInfo['modifier_user'];
				LibertyContent::load();
			}
			return count( $this->mInfo );
		}
		return null;
	}

	/**
	 * @param  array $pParamHash
	 * @return bool
	 */
	public function verifyAccountData( array &$pParamHash ): bool {
		$pParamHash['title'] = trim( (string)( $pParamHash['title'] ?? '' ) );
		if( $pParamHash['title'] === '' ) {
			$this->mErrors['title'] = 'The account needs a name.';
		}
		return count( $this->mErrors ) == 0;
	}

	/**
	 * @param  array $pParamHash  Data to persist; modified in place.
	 * @return bool
	 */
	public function store( array &$pParamHash ): bool {
		if( $this->verifyAccountData( $pParamHash ) ) {
			$this->StartTrans();
			if( LibertyContent::store( $pParamHash ) ) {
				$this->mContentId          = $pParamHash['content_id'];
				$this->mInfo['content_id'] = $this->mContentId;
				$this->CompleteTrans();
			} else {
				$this->mDb->RollbackTrans();
			}
		}
		return count( $this->mErrors ) == 0;
	}

	/**
	 * @return bool TRUE when mContentId refers to a real row of this content type.
	 */
	public function isValid() {
		if( !@$this->verifyId( $this->mContentId ) ) {
			return false;
		}
		return (bool)$this->mDb->getOne(
			"SELECT 1 FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id` = ? AND `content_type_guid` = ?",
			[ $this->mContentId, static::GUID ]
		);
	}

	/**
	 * The CONTACT link's xref row, or null — one per account.
	 */
	public function getContactLink(): ?array {
		if( !$this->isValid() ) {
			return null;
		}
		$row = $this->mDb->getRow(
			"SELECT FIRST 1 x.`xref_id`, x.`xref`, c.`title` AS `contact_title`
				FROM `".BIT_DB_PREFIX."liberty_xref` x
					LEFT JOIN `".BIT_DB_PREFIX."liberty_content` c ON ( c.`content_id` = x.`xref` )
				WHERE x.`content_id` = ? AND x.`item` = 'CONTACT' AND x.`end_date` IS NULL
				ORDER BY x.`xref_id` DESC",
			[ (int)$this->mContentId ]
		);
		return $row ?: null;
	}

	/**
	 * The opening balance row (xkey = amount), or null when none is set.
	 */
	public function getOpening(): ?array {
		return $this->mDb->getRow(
			"SELECT FIRST 1 `xref_id`, `xkey` AS `amount` FROM `".BIT_DB_PREFIX."liberty_xref`
				WHERE `content_id` = ? AND `item` = 'OPENING' AND `end_date` IS NULL ORDER BY `xref_id` DESC",
			[ (int)$this->mContentId ]
		) ?: null;
	}

	/**
	 * Set (or change) the opening balance. $pAmount is a plain decimal string.
	 */
	public function storeOpening( string $pAmount ): bool {
		$old = $this->getOpening();
		$hash = [ 'content_id' => (int)$this->mContentId, 'item' => 'OPENING', 'xkey' => bcadd( $pAmount, '0', 2 ) ];
		if( $old ) {
			$hash['xref_id'] = (int)$old['xref_id'];
		}
		$xref = new \Bitweaver\Liberty\LibertyXref();
		$xref->mContentTypeGuid = $this->mContentTypeGuid;
		$xref->mPackageGuid     = $this->mPackageGuid;
		return $xref->store( $hash );
	}

	/**
	 * Every statement line on the account in date order (then the order they were imported), each with its
	 * running balance from the opening balance.
	 *
	 * @return array<int,array>  date (Y-m-d), epoch, item, desc, amount, out, in, balance, taxYear ('2025-26')
	 */
	public function getStatementLines( \DateTimeZone $pTz ): array {
		$opening = $this->getOpening();
		$balance = $opening ? (string)$opening['amount'] : '0';
		$rs = $this->mDb->query(
			"SELECT `xref_id`, `item`, `start_date`, `xkey`, `data` FROM `".BIT_DB_PREFIX."liberty_xref`
				WHERE `content_id` = ? AND `item` NOT IN ('CONTACT','OPENING') AND `end_date` IS NULL
				ORDER BY `start_date`, `xorder`, `xref_id`",
			[ (int)$this->mContentId ]
		);
		$ret = [];
		while( $r = $rs->fetchRow() ) {
			$d = ( new \DateTime( '@'.(int)$r['start_date'] ) )->setTimezone( $pTz );
			$data = !empty( $r['data'] ) ? ( json_decode( $r['data'], true ) ?: [] ) : [];
			$amount = bcadd( (string)$r['xkey'], '0', 2 );
			$balance = bcadd( $balance, $amount, 2 );
			// UK tax year: 6 April to 5 April.
			$y = (int)$d->format( 'Y' );
			$startYear = $d->format( 'md' ) >= '0406' ? $y : $y - 1;
			$ret[] = [
				'xref_id' => (int)$r['xref_id'],
				'date'    => $d->format( 'Y-m-d' ),
				'item'    => $r['item'],
				'desc'    => (string)( $data['description'] ?? '' ),
				'amount'  => $amount,
				'out'     => bccomp( $amount, '0', 2 ) < 0 ? ltrim( $amount, '-' ) : '',
				'in'      => bccomp( $amount, '0', 2 ) > 0 ? $amount : '',
				'balance' => $balance,
				'taxYear' => $startYear.'-'.substr( (string)( $startYear + 1 ), 2 ),
			];
		}
		return $ret;
	}

	/**
	 * Adds the contact search for the CONTACT item's picker (read by liberty's link picker
	 * template); everything else is left to the base implementation.
	 */
	public function enrichXrefDisplay( array &$pXrefInfo ): void {
		parent::enrichXrefDisplay( $pXrefInfo );
		if( ( $pXrefInfo['item'] ?? '' ) === 'CONTACT' && defined( 'CONTACT_PKG_URL' ) ) {
			$pXrefInfo['lookup'] = [
				'url'    => CONTACT_PKG_URL.'includes/lookup_contact.php',
				'params' => [ 'type' => 'contactbusiness' ],
				'label'  => 'Contact',
				'help'   => 'The contact record for the bank or service holding this account.',
			];
		}
	}

	public function getDisplayUrl( $pContentId = null ) {
		return ACCOUNTS_PKG_URL.'view_account.php?content_id='.( $pContentId ?: $this->mContentId );
	}

	public function getEditUrl( $pContentId = null, $pMixed = null ) {
		return ACCOUNTS_PKG_URL.'edit_account.php?content_id='.( $pContentId ?: $this->mContentId );
	}
}
