<?php

// An account is its own content type (AccountsAccount, a pure liberty_content record — title is
// the account's name, content_id the only identifier, same shape as health's HealthDay). The
// Contact record for the bank or service holding it is linked with a CONTACT xref row and
// supplies the extra detail; every statement line is an xref row on the account, its item the line's type (DD, BP...). No tables of
// its own. See MANUAL.md for the column-by-column layout of a statement line.
//
// Unlike the device-sourced health items (multiple=-1, read-only), statement-line rows are edited by
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
	'AccountsPaypal'  => ACCOUNTS_PKG_CLASS_PATH.'AccountsPaypal.php',
	'AccountsRent'    => ACCOUNTS_PKG_CLASS_PATH.'AccountsRent.php',
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

// The package carries the whole vocabulary a working install needs: the link from an account to its contact
// record, the statement-line types for each account kind, OPENING / PLAN / BALANCE. A site's own additions
// belong in a local scheme (config/local/xref_schemes/), not here. See MANUAL.md for the row layout.
foreach( [ 'accountsaccount', 'accountspaypal', 'accountsrent' ] as $guid ) {
	$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('general','$guid','General',1,3,'','')";

	// CONTACT - the contact record for the bank or service holding the account. xref = that
	// contact's content_id; picked with a search (see AccountsAccount::enrichXrefDisplay()). One per account.
	$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('CONTACT','$guid','general','Contact',0,0,3,'','link',NULL)";
}

// OPENING - the balance before the account's first recorded line, so a statement view can show a running
// balance. xkey = the amount. Bank and rent accounts. One per account.
foreach( [ 'accountsaccount', 'accountsrent' ] as $guid ) {
	$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('OPENING','$guid','general','Opening balance',0,1,3,'','key-json-text','[\"amount\",\"note\"]')";
}

// PLAN - an expected future line on the account (the forecast): start_date = next due date, xkey = signed amount
// (negative = paid out), xkey_ext = what it is, data json = repeat (weekly|2weekly|4weekly|monthly), until
// (Y-m-d), via (paypal), note. A repeating line rolls to its next date when it happens; a one-off is archived.
// Never counted by reports, only shown after the last real line. Bank and rent accounts.
foreach( [ 'accountsaccount', 'accountsrent' ] as $guid ) {
	$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('planned','$guid','Planned',3,3,'','')";
}
foreach( [ 'accountsaccount', 'accountsrent' ] as $guid ) {
	$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('PLAN','$guid','planned','Planned line',1,1,3,'','key-json-text','[\"amount\",\"description\",\"repeat\",\"until\",\"via\",\"note\"]')";
}

// BALANCE - a balance noted by hand, from the bank's app, as at a date (start_date); xkey = the amount. The forecast
// starts from the newer of this and the last statement line, since statements only arrive monthly. Bank and rent accounts.
foreach( [ 'accountsaccount', 'accountsrent' ] as $guid ) {
	$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('BALANCE','$guid','planned','Balance noted',1,2,3,'','key-json-text','[\"amount\",\"note\"]')";
}

// Statement-line types. The item IS the line's type; the direction of money is the sign of xkey, never the type.
// Bank lines use the bank's own Type column (business and personal accounts share it); the rent account (its own
// type) has rent charged, housing benefit and the tenant's payments; PayPal (bank is master) holds only sales
// and where that money went. Row layout (xkey = signed amount, xkey_ext = import key, xref = counterparty
// contact, data json): MANUAL.md. Item codes are C(20).
$hint = '["amount","import_key","code","description","balance","treatment","claim","source","note","gross","fee","net","status"]';
foreach( [ 'accountsaccount', 'accountsrent', 'accountspaypal' ] as $guid ) {
	$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('transactions','$guid','Transactions',2,3,'','')";
}
$lines = [
	[ 'accountsaccount', 'DD',         'Direct debit',            1 ],
	[ 'accountsaccount', 'BP',         'Bill payment',            2 ],
	[ 'accountsaccount', 'CR',         'Credit',                  3 ],
	[ 'accountsaccount', 'VIS',        'Visa debit purchase',     4 ],
	[ 'accountsaccount', 'CTL',        'Contactless purchase',    5 ], // the bank's ')))'
	[ 'accountsaccount', 'DR',         'Debit (bank charges)',    6 ],
	[ 'accountsaccount', 'TFR',        'Transfer',                7 ],
	[ 'accountsaccount', 'ATM',        'Cash withdrawal',         8 ],
	[ 'accountsaccount', 'OBP',        'Open banking payment',    9 ], // e.g. a pay-later provider via the bank app
	[ 'accountsaccount', 'BANK_OTHER', 'Other bank transaction', 10 ],
	[ 'accountsrent',    'RENT',       'Rent charge',             1 ],
	[ 'accountsrent',    'HB',         'Housing benefit',         2 ],
	[ 'accountsrent',    'PAY',        'Payment towards rent',    3 ],
	[ 'accountspaypal',  'PP_SALE',    'Sale received',           1 ],
	[ 'accountspaypal',  'PP_PAYOUT',  'Paid out to bank',        2 ],
	[ 'accountspaypal',  'PP_OTHER',   'Other PayPal transaction', 3 ],
];
foreach( $lines as [ $guid, $item, $title, $sort ] ) {
	$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('$item','$guid','transactions','$title',1,$sort,3,'','key-json-text','$hint')";
}

// STMT - statement wording on a business contact ("Acmehost", "PAYPAL"): xkey = the text, xkey_ext = a note. A statement line
// whose description contains it is linked to that contact (see AccountsSupplier). Accounts adds it to the contact's own type.
$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('STMT','contactbusiness','links','Statement text',1,10,3,'','key-json-text','[\"text\",\"note\"]')";

$gBitInstaller->registerSchemaDefault( ACCOUNTS_PKG_NAME, array_merge( $xrefTypes, $xrefItems ) );
