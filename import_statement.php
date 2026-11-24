<?php
/**
 * Import bank statement CSVs onto a bank account: upload, preview (what is new, what is already held,
 * whether the printed balances reconcile), then import. Repeating an import adds nothing.
 *
 * @package accounts
 */

namespace Bitweaver\Accounts;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser;

$gBitSystem->verifyPackage( 'accounts' );
$gBitSystem->verifyPermission( 'p_accounts_create' );

$gContent = AccountsAccount::loadFor( (int)( $_REQUEST['content_id'] ?? 0 ) );
$gContent->load();
if( !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No valid account specified.' ) );
}
if( $gContent instanceof AccountsPaypal ) {
	$gBitSystem->fatalError( KernelTools::tra( 'Statement import here is for bank accounts.' ) );
}

$tz = $gBitUser->getUserTimezone();
$files = [];   // name => csv text, from this upload or carried over from the preview
$errors = [];

if( !empty( $_FILES['csv']['name'] ) && is_array( $_FILES['csv']['name'] ) ) {
	foreach( $_FILES['csv']['name'] as $i => $name ) {
		if( $_FILES['csv']['error'][$i] === UPLOAD_ERR_OK && is_uploaded_file( $_FILES['csv']['tmp_name'][$i] ) ) {
			$files[basename( $name )] = (string)file_get_contents( $_FILES['csv']['tmp_name'][$i] );
		} elseif( $_FILES['csv']['error'][$i] !== UPLOAD_ERR_NO_FILE ) {
			$errors[] = basename( $name ).': upload failed';
		}
	}
} elseif( !empty( $_POST['carry_name'] ) && is_array( $_POST['carry_name'] ) ) {
	foreach( $_POST['carry_name'] as $i => $name ) {
		$files[(string)$name] = (string)( $_POST['carry_csv'][$i] ?? '' );
	}
}
ksort( $files );

$lines = [];
$problems = [];
foreach( $files as $name => $text ) {
	$parsed = AccountsImport::parseBankCsv( $text, $name, $tz );
	$errors = array_merge( $errors, $parsed['errors'] );
	$problems = array_merge( $problems, AccountsImport::checkBalances( $parsed['lines'] ) );
	$lines = array_merge( $lines, $parsed['lines'] );
}
$lines = $lines ? AccountsImport::classify( $gContent, $lines ) : [];
$newCount = count( array_filter( $lines, fn( $l ) => $l['new'] ) );

$result = null;
if( !empty( $_POST['do_import'] ) && $lines ) {
	$result = AccountsImport::store( $gContent, $lines );
	$errors = array_merge( $errors, $result['errors'] );
	$lines = $files = [];
}

$otherTypes = array_unique( array_map( fn( $l ) => $l['type'], array_filter( $lines, fn( $l ) => $l['item'] === 'BANK_OTHER' ) ) );

$gBitSmarty->assign( 'gContent',   $gContent );
$gBitSmarty->assign( 'files',      $files );
$gBitSmarty->assign( 'lines',      $lines );
$gBitSmarty->assign( 'newCount',   $newCount );
$gBitSmarty->assign( 'problems',   $problems );
$gBitSmarty->assign( 'otherTypes', $otherTypes );
$gBitSmarty->assign( 'result',     $result );
$gBitSmarty->assign( 'errors',     $errors );

$gBitSystem->display( 'bitpackage:accounts/import_statement.tpl', KernelTools::tra( 'Import statements' ).': '.$gContent->getTitle() );
