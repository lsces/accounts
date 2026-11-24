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

// The package carries only what every install needs: the link from an account to its contact record,
// on both account types (bank and PayPal, which have their own content types so each gets its own tabs).
// The statement-line vocabulary (the bank's Type codes, a payment service's types) is site-specific and
// is a local scheme, config/local/xref_schemes/accounts.php (liberty's Local Scheme page applies it),
// not part of a new install. See MANUAL.md for the row layout those items share.
foreach( [ 'accountsaccount', 'accountspaypal' ] as $guid ) {
	$xrefTypes[] = "INSERT INTO `{$X}liberty_xref_group` (`x_group`,`content_type_guid`,`title`,`sort_order`,`role_id`,`type_href`,`template`) VALUES ('general','$guid','General',1,3,'','')";

	// CONTACT - the contact record for the bank or service holding the account. xref = that
	// contact's content_id; picked with a search (see AccountsAccount::enrichXrefDisplay()). One per account.
	$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('CONTACT','$guid','general','Contact',0,0,3,'','link',NULL)";
}

// OPENING - the balance before the account's first recorded line, so a statement view can show a running
// balance. xkey = the amount. Bank accounts only. One per account.
$xrefItems[] = "INSERT INTO `{$X}liberty_xref_item` (`item`,`content_type_guid`,`x_group`,`cross_ref_title`,`multiple`,`sort_order`,`role_id`,`cross_ref_href`,`template`,`data`) VALUES ('OPENING','accountsaccount','general','Opening balance',0,1,3,'','key-json-text','[\"amount\",\"note\"]')";

$gBitInstaller->registerSchemaDefault( ACCOUNTS_PKG_NAME, array_merge( $xrefTypes, $xrefItems ) );
