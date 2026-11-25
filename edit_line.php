<?php
/**
 * One statement line: set who it was paid to or received from. The supplier is a business contact, picked with
 * the same search as an account's own contact; only the line's xref column is written, so the import key, amount
 * and description stay as imported. Any business can be chosen on any account (personal accounts pay business
 * suppliers and the other way round).
 *
 * @package accounts
 */

namespace Bitweaver\Accounts;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser;

$gBitSystem->verifyPackage( 'accounts' );
$gBitSystem->verifyPermission( 'p_accounts_update' );

$gContent = AccountsAccount::loadFor( (int)( $_REQUEST['content_id'] ?? 0 ) );
$gContent->load();
if( !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No valid account specified.' ) );
}
$line = $gContent->getLine( (int)( $_REQUEST['xref_id'] ?? 0 ) );
if( !$line ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No such line on this account.' ) );
}

$back = ACCOUNTS_PKG_URL.'view_account.php?content_id='.$gContent->mContentId.( !empty( $_REQUEST['ty'] ) ? '&ty='.urlencode( (string)$_REQUEST['ty'] ) : '' ).( !empty( $_REQUEST['page'] ) ? '&page='.(int)$_REQUEST['page'] : '' );

if( isset( $_REQUEST['save'] ) || isset( $_REQUEST['clear'] ) ) {
	$contactId = isset( $_REQUEST['clear'] ) ? 0 : (int)( $_REQUEST['supplier_id'] ?? 0 );
	if( $contactId > 0 && !$gContent->mDb->getOne( "SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id` = ? AND `content_type_guid` = 'contactbusiness'", [ $contactId ] ) ) {
		$gBitSmarty->assign( 'errors', [ KernelTools::tra( 'Pick a business from the list.' ) ] );
	} elseif( $gContent->setLineSupplier( $line['xref_id'], $contactId ) ) {
		// Fill forward: remember the text against the supplier and link every other unset line that holds it.
		$filled = 0;
		$text = trim( (string)( $_REQUEST['match_text'] ?? '' ) );
		if( $contactId > 0 && $text !== '' && !empty( $_REQUEST['fill'] ) && AccountsSupplier::addRule( $contactId, $text ) ) {
			$filled = AccountsSupplier::fill();
		}
		header( 'Location: '.$back.( $filled ? '&filled='.$filled : '' ) );
		exit;
	} else {
		$gBitSmarty->assign( 'errors', [ KernelTools::tra( 'The line could not be saved.' ) ] );
	}
}

$tz = $gBitUser->getUserTimezone();
$line['dateText'] = ( new \DateTime( '@'.$line['date'] ) )->setTimezone( $tz )->format( 'D j M Y' );
$line['amountText'] = number_format( (float)$line['amount'], 2 );
$line['suggest'] = AccountsSupplier::suggest( $line['desc'] );

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'line', $line );
$gBitSmarty->assign( 'back', $back );
$gBitSmarty->assign( 'ty', (string)( $_REQUEST['ty'] ?? '' ) );
$gBitSmarty->assign( 'pageNo', (int)( $_REQUEST['page'] ?? 0 ) );

$gBitSystem->display( 'bitpackage:accounts/edit_line.tpl', KernelTools::tra( 'Statement line' ).': '.$gContent->getTitle() );
