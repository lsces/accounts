<?php

// An account is its own content type (AccountsAccount, a pure liberty_content record — title is
// the account's name, content_id the only identifier, same shape as health's HealthDay). The
// Contact record for the bank or service holding it is linked with a CONTACT xref row and
// supplies the extra detail; every statement line is an xref row on the account, its item the line's type (DD, BP, PP_EXPRESS...). No tables of
// its own. See MANUAL.md for the column-by-column layout of a statement line.
//
// Unlike the device-sourced health items (multiple=-1, read-only), these rows are edited by
// hand (treatment, counterparty, notes), so the item is multiple=1: many rows per account,
// xorder auto-incremented, add/edit/delete offered.

global $gBitInstaller;

// This file is included on every installer page view, and the installer's cached $gBitSystem brings
// $mPackages (including each package's seed list) back from the object cache each time. Without a
// reset, registerSchemaDefault() appends another copy of every seed statement per page view, and the
// install run then executes them two or three times over and dies on the first unique key
// (found 2026-11-24: 9 seeds became 27 across three page views). Starting the list afresh makes
// including this file any number of times harmless.
unset( $gBitInstaller->mPackages[ACCOUNTS_PKG_NAME]['defaults'] );

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

// Three groups on the account: the link to its contact, bank transactions, and PayPal transactions.
$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('general','accountsaccount','General',1,3,'','')";
$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('transactions','accountsaccount','Transactions',2,3,'','')";
$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('paypal','accountsaccount','PayPal',3,3,'','')";

// CONTACT — the contact record for the bank or service holding the account. xref = that
// contact's content_id; picked with a search (see AccountsAccount::enrichXrefDisplay()). One per account.
$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('CONTACT','accountsaccount','general','Contact',0,0,3,'','link',NULL)";

// Statement lines. The item IS the line's type: the bank's own Type column for the bank (business and
// personal accounts share one vocabulary), a short code per PayPal transaction type for PayPal.
//   start_date  the transaction date (tax year is derived from it, year boundary 5 April)
//   xkey        signed amount, negative = paid out (direction is never implied by the type: BP and CR go both ways)
//   xkey_ext    import key: the payment service's transaction id, or a hash of
//               date|amount|type|description. Indexed via (item, xkey_ext), so a re-import
//               finds its own lines with one lookup, across year boundaries too.
//   xref        counterparty - a contact's content_id (BT, Acmehost, ClubX...), null until matched
//   data        json: code (the source's original type text, where the item is a normalised code), description,
//               balance, treatment (income|expense|transfer|not_claimed), claim (e.g. RQ-3786), source
//               (statement file), note, and for PayPal gross/fee/net/status
// 'key-json-text': data's first two hint entries title xkey / xkey_ext, the rest are json field names.
// Item codes are C(20); the generic xref list orders by item then date, so the statement views
// (not the generic tab) are what give date order and a running balance.
$hint = '["amount","import_key","code","description","balance","treatment","claim","source","note","gross","fee","net","status"]';
$txnItems = [
	// group, item, title                          sort
	[ 'transactions', 'DD',         'Direct debit',                  1 ],
	[ 'transactions', 'BP',         'Bill payment',                  2 ],
	[ 'transactions', 'CR',         'Credit',                        3 ],
	[ 'transactions', 'VIS',        'Visa debit purchase',           4 ],
	[ 'transactions', 'CTL',        'Contactless purchase',          5 ],
	[ 'transactions', 'DR',         'Debit (bank charges)',          6 ],
	[ 'transactions', 'TFR',        'Transfer',                      7 ],
	[ 'transactions', 'ATM',        'Cash withdrawal',               8 ],
	[ 'transactions', 'BANK_OTHER', 'Other bank transaction',        9 ],
	[ 'paypal', 'PP_EXPRESS',  'Express Checkout payment',           1 ],
	[ 'paypal', 'PP_WEBPAY',   'Website payment',                    2 ],
	[ 'paypal', 'PP_PREAPPR',  'Pre-approved payment',               3 ],
	[ 'paypal', 'PP_INVOICE',  'Invoice received',                   4 ],
	[ 'paypal', 'PP_BILLPAY',  'BillPay transaction',                5 ],
	[ 'paypal', 'PP_CARD_IN',  'Credit card deposit',                6 ],
	[ 'paypal', 'PP_BANK_IN',  'Bank deposit to PayPal',             7 ],
	[ 'paypal', 'PP_CARD_OUT', 'Credit card withdrawal',             8 ],
	[ 'paypal', 'PP_WITHDRAW', 'User initiated withdrawal',          9 ],
	[ 'paypal', 'PP_CONV',     'Currency conversion',               10 ],
	[ 'paypal', 'PP_REFUND',   'Payment refund',                    11 ],
	[ 'paypal', 'PP_AUTH',     'Authorisation hold (shadows a payment, not imported as income/expense)', 12 ],
	[ 'paypal', 'PP_OTHER',    'Other PayPal transaction',          13 ],
];
foreach( $txnItems as [ $grp, $item, $title, $sort ] ) {
	$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('$item','accountsaccount','$grp','".str_replace( "'", "''", $title )."',1,$sort,3,'','key-json-text','".str_replace( "'", "''", $hint )."')";
}

$gBitInstaller->registerSchemaDefault( ACCOUNTS_PKG_NAME, array_merge( $xrefTypes, $xrefItems ) );
