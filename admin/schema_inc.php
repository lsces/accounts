<?php

// An account is its own content type (AccountsAccount, a pure liberty_content record — title is
// the account's name, content_id the only identifier, same shape as health's HealthDay). The
// Contact record for the bank or service holding it is linked with a CONTACT xref row and
// supplies the extra detail; every statement line is a TXN xref row on the account. No tables of
// its own. See MANUAL.md for the column-by-column layout of a TXN row.
//
// Unlike the device-sourced health items (multiple=-1, read-only), TXN rows are edited by
// hand (treatment, counterparty, notes), so the item is multiple=1: many rows per account,
// xorder auto-incremented, add/edit/delete offered.

global $gBitInstaller;

$gBitInstaller->registerPackageInfo( ACCOUNTS_PKG_NAME, [
	'description' => 'Accounts is a lightweight single-entry ledger for a sole trader: it imports bank and payment-service statements, hangs each line off an account contact, and reports a tax year\'s income and expenses.',
	'license'     => '<a href="http://www.gnu.org/licenses/licenses.html#LGPL">LGPL</a>',
] );

$gBitInstaller->registerPreferences( ACCOUNTS_PKG_NAME, [
	[ ACCOUNTS_PKG_NAME, 'accounts_menu_text', 'Accounts' ],
] );

// ### Register content types
$gBitInstaller->registerContentObjects( ACCOUNTS_PKG_NAME, [
	'AccountsAccount' => ACCOUNTS_PKG_CLASS_PATH.'AccountsAccount.php',
] );

// ### Default User Permissions
// Financial data: nothing is granted below 'admin' by default (health grants view to
// 'registered'; deliberately not copied here).
$gBitInstaller->registerUserPermissions( ACCOUNTS_PKG_NAME, [
	[ 'p_accounts_view',    'Can view accounts and transactions',   'admin', ACCOUNTS_PKG_NAME ],
	[ 'p_accounts_create',  'Can import and add transactions',      'admin', ACCOUNTS_PKG_NAME ],
	[ 'p_accounts_update',  'Can edit transactions',                'admin', ACCOUNTS_PKG_NAME ],
	[ 'p_accounts_expunge', 'Can delete transactions',              'admin', ACCOUNTS_PKG_NAME ],
	[ 'p_accounts_admin',   'Can administer accounts',              'admin', ACCOUNTS_PKG_NAME ],
] );

$gBitInstaller->registerRequirements( ACCOUNTS_PKG_NAME, [
	'kernel'  => [ 'min' => '5.0.0' ],
	'liberty' => [ 'min' => '5.0.2' ],
	'users'   => [ 'min' => '5.0.0' ],
	'contact' => [ 'min' => '5.0.1' ],
] );

// ### Xref seed data
// liberty_xref_group: x_group, content_type_guid, title, sort_order, role_id, type_href, template
// liberty_xref_item:  item, content_type_guid, x_group, cross_ref_title, multiple, sort_order, role_id, cross_ref_href, template, data
$X = BIT_DB_PREFIX;

$xrefTypes = [];
$xrefItems = [];

// Two groups on the account: the link to its contact, and the transactions.
$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('general','accountsaccount','General',1,3,'','')";
$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('transactions','accountsaccount','Transactions',2,3,'','')";

// CONTACT — the contact record for the bank or service holding the account. xref = that
// contact's content_id; picked with a search (see AccountsAccount::enrichXrefDisplay()). One per account.
$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('CONTACT','accountsaccount','general','Contact',0,0,3,'','link',NULL)";

// TXN — one statement line on this account.
//   start_date  the transaction date (tax year is derived from it, year boundary 5 April)
//   xkey        signed amount, negative = paid out
//   xkey_ext    import key: the payment service's transaction id, or a hash of
//               date|amount|type|description. Indexed via (item, xkey_ext), so a re-import
//               finds its own lines with one lookup, across year boundaries too.
//   xref        counterparty — a contact's content_id (BT, Acmehost, ClubX...), null until matched
//   data        json: type, description, balance, treatment (income|expense|transfer|not_claimed),
//               claim (e.g. RQ-3786), source (statement file), note, and for PayPal gross/fee/net/status
// 'key-json-text': data's first two hint entries title xkey / xkey_ext, the rest are json field names.
$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('TXN','accountsaccount','transactions','Transaction',1,1,3,'','key-json-text','[\"amount\",\"import_key\",\"type\",\"description\",\"balance\",\"treatment\",\"claim\",\"source\",\"note\",\"gross\",\"fee\",\"net\",\"status\"]')";

$gBitInstaller->registerSchemaDefault( ACCOUNTS_PKG_NAME, array_merge( $xrefTypes, $xrefItems ) );
