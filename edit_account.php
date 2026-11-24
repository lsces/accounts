<?php
/**
 * Create or rename an account and link it to its contact record. The contact (the bank or
 * service holding the account) supplies sort code, number and addresses; nothing of that is
 * kept on the account itself.
 *
 * @package accounts
 */

namespace Bitweaver\Accounts;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser;

$gBitSystem->verifyPackage( 'accounts' );

$gContent = new AccountsAccount( null, !empty( $_REQUEST['content_id'] ) ? (int)$_REQUEST['content_id'] : null );
if( $gContent->mContentId ) {
	$gContent->load();
	if( !$gContent->isValid() ) {
		$gBitSystem->fatalError( KernelTools::tra( 'No valid account specified.' ) );
	}
	$gBitSystem->verifyPermission( 'p_accounts_update' );
} else {
	$gBitSystem->verifyPermission( 'p_accounts_create' );
}

$errors = [];
$link = $gContent->getContactLink();
$title = trim( $_REQUEST['title'] ?? ( $gContent->mInfo['title'] ?? '' ) );
$contactId = isset( $_REQUEST['contact_id'] ) ? (int)$_REQUEST['contact_id'] : (int)( $link['xref'] ?? 0 );
$contactTitle = trim( $_REQUEST['contact_title'] ?? '' );

if( !empty( $_REQUEST['save'] ) ) {
	// store() takes &$pParamHash by reference — must be a named variable, not a literal.
	$pHash = [ 'title' => $title ];
	if( $gContent->mContentId ) {
		$pHash['content_id'] = $gContent->mContentId;
	}
	if( $gContent->store( $pHash ) ) {
		// Only a contact that really exists (and is a business) is linked.
		$validContact = $contactId && $gContent->mDb->getOne(
			"SELECT 1 FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id` = ? AND `content_type_guid` = 'contactbusiness'",
			[ $contactId ]
		);
		if( $contactId && !$validContact ) {
			$errors['contact'] = KernelTools::tra( 'That contact was not found; the account was saved without a link.' );
		} elseif( $validContact && (int)( $link['xref'] ?? 0 ) !== $contactId ) {
			$xHash = [ 'content_id' => (int)$gContent->mContentId, 'item' => 'CONTACT', 'xref' => $contactId ];
			if( !empty( $link['xref_id'] ) ) {
				$xHash['xref_id'] = (int)$link['xref_id'];
			}
			if( !$gContent->storeXref( $xHash ) ) {
				$errors['contact'] = KernelTools::tra( 'The account was saved but the contact link was not.' );
			}
		}
		if( !$errors ) {
			header( 'Location: '.ACCOUNTS_PKG_URL.'view_account.php?content_id='.$gContent->mContentId );
			die;
		}
	} else {
		$errors = $gContent->mErrors;
	}
}

if( $contactId && $contactTitle === '' ) {
	$contactTitle = (string)$gContent->mDb->getOne( "SELECT `title` FROM `".BIT_DB_PREFIX."liberty_content` WHERE `content_id` = ?", [ $contactId ] );
}

$gBitSmarty->assign( 'gContent',     $gContent );
$gBitSmarty->assign( 'accountTitle', $title );
$gBitSmarty->assign( 'contactId',    $contactId ?: '' );
$gBitSmarty->assign( 'contactTitle', $contactTitle );
$gBitSmarty->assign( 'errors',       $errors );

$gBitSystem->display( 'bitpackage:accounts/edit_account.tpl', $gContent->mContentId ? KernelTools::tra( 'Edit account' ) : KernelTools::tra( 'New account' ), [ 'display_mode' => 'edit' ] );
