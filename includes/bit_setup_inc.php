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

	// No content type of its own: accounts are Contact businesses and statement lines are xref items on
	// them (see admin/schema_inc.php and MANUAL.md). Service/hook registration, if ever needed,
	// belongs here — see stock/includes/bit_setup_inc.php for the pattern.
}
