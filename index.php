<?php
/**
 * Accounts — the list of accounts, each linking to its transactions.
 *
 * @package accounts
 * @subpackage functions
 */

namespace Bitweaver\Accounts;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser, $gBitDb;

$gBitSystem->verifyPackage( 'accounts' );
$gBitSystem->verifyPermission( 'p_accounts_view' );

$accounts = $gBitDb->getAll(
	"SELECT lc.`content_id`, lc.`title`, lc.`content_type_guid`,
			( SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_xref` x
				WHERE x.`content_id` = lc.`content_id` AND x.`item` <> 'CONTACT' AND x.`end_date` IS NULL ) AS txn_count,
			( SELECT FIRST 1 c.`title` FROM `".BIT_DB_PREFIX."liberty_xref` l
				JOIN `".BIT_DB_PREFIX."liberty_content` c ON ( c.`content_id` = l.`xref` )
				WHERE l.`content_id` = lc.`content_id` AND l.`item` = 'CONTACT' AND l.`end_date` IS NULL ) AS contact_title
		FROM `".BIT_DB_PREFIX."liberty_content` lc
		WHERE lc.`content_type_guid` IN ( ?, ? )
		ORDER BY lc.`title`",
	[ AccountsAccount::GUID, AccountsPaypal::GUID ]
);

$gBitSmarty->assign( 'accounts', $accounts );
$gBitSmarty->assign( 'canCreate', $gBitUser->hasPermission( 'p_accounts_create' ) );

$gBitSystem->display( 'bitpackage:accounts/list_accounts.tpl', KernelTools::tra( 'Accounts' ) );
