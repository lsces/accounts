<?php
global $gBitSystem;

$pRegisterHash = [
	'package_name' => 'accounts',
	'package_path' => dirname( dirname( __FILE__ ) ).'/',
	'homeable'     => true,
];
// fix to quieten down VS Code which can't see the dynamic creation of these ...
define( 'ACCOUNTS_PKG_NAME', $pRegisterHash['package_name'] );
define( 'ACCOUNTS_PKG_URL', BIT_ROOT_URL . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'ACCOUNTS_PKG_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/' );
define( 'ACCOUNTS_PKG_INCLUDE_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/');
define( 'ACCOUNTS_PKG_CLASS_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/includes/classes/');
define( 'ACCOUNTS_PKG_ADMIN_PATH', BIT_ROOT_PATH . basename( $pRegisterHash['package_path'] ) . '/admin/');

$gBitSystem->registerPackage( $pRegisterHash );

if( $gBitSystem->isPackageActive( 'accounts' ) ) {

	$menuHash = [
		'package_name'  => ACCOUNTS_PKG_NAME,
		'index_url'     => ACCOUNTS_PKG_URL.'index.php',
		'menu_template' => 'bitpackage:accounts/menu_accounts.tpl',
	];
	$gBitSystem->registerAppMenu( $menuHash );

	// content-type registration (the per-account, per-tax-year ledger — see MANUAL.md)
	// and any service/hook registration belongs here once those classes exist —
	// nothing to register yet.
}
