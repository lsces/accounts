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
	// A first import into an account with no opening balance sets one, worked back from the first printed balance.
	$hasLines = (bool)$gContent->mDb->getOne( "SELECT FIRST 1 1 FROM `".BIT_DB_PREFIX."liberty_xref` WHERE `content_id` = ? AND `item` NOT IN ('CONTACT','OPENING')", [ (int)$gContent->mContentId ] );
	$openingSet = null;
	if( !$hasLines && !$gContent->getOpening() && ( $derived = AccountsImport::deriveOpening( $lines ) ) !== null && $gContent->storeOpening( $derived ) ) {
		$openingSet = $derived;
	}
	$result = AccountsImport::store( $gContent, $lines );
	// The raw files are kept, as health keeps its uploads: archive/<account content_id>/<name>.
	$archiveDir = ACCOUNTS_IMPORT_PATH.'archive/'.(int)$gContent->mContentId.'/';
	if( !is_dir( $archiveDir ) ) {
		mkdir( $archiveDir, 0777, true );
	}
	foreach( $files as $name => $text ) {
		$safe = preg_replace( '/[^A-Za-z0-9 ()._-]/', '_', basename( (string)$name ) );
		if( $safe !== '' && $safe[0] !== '.' ) {
			file_put_contents( $archiveDir.$safe, $text );
		}
	}
	$result['opening'] = $openingSet;
	$errors = array_merge( $errors, $result['errors'] );
	$lines = $files = [];
}

$archived = [];
foreach( glob( ACCOUNTS_IMPORT_PATH.'archive/'.(int)$gContent->mContentId.'/*.csv' ) ?: [] as $path ) {
	$archived[] = [ 'name' => basename( $path ), 'size' => filesize( $path ), 'mtime' => filemtime( $path ) ];
}
usort( $archived, fn( $a, $b ) => strcmp( $b['name'], $a['name'] ) );

$otherTypes = array_unique( array_map( fn( $l ) => $l['type'], array_filter( $lines, fn( $l ) => $l['item'] === 'BANK_OTHER' ) ) );

$gBitSmarty->assign( 'gContent',   $gContent );
$gBitSmarty->assign( 'files',      $files );
$gBitSmarty->assign( 'lines',      $lines );
$gBitSmarty->assign( 'newCount',   $newCount );
$gBitSmarty->assign( 'problems',   $problems );
$gBitSmarty->assign( 'otherTypes', $otherTypes );
$gBitSmarty->assign( 'archived',   $archived );
$gBitSmarty->assign( 'result',     $result );
$gBitSmarty->assign( 'errors',     $errors );

$gBitSystem->display( 'bitpackage:accounts/import_statement.tpl', KernelTools::tra( 'Import statements' ).': '.$gContent->getTitle() );
