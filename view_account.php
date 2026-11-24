<?php
/**
 * One account: its linked contact and its transactions, using liberty's generic xref-group
 * display (one tab per group) — the same framework health/view_day.php uses.
 *
 * @package accounts
 */

namespace Bitweaver\Accounts;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser;

$gBitSystem->verifyPackage( 'accounts' );
$gBitSystem->verifyPermission( 'p_accounts_view' );

$gContent = new AccountsAccount( null, (int)( $_REQUEST['content_id'] ?? 0 ) );
$gContent->load();
if( !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No valid account specified.' ) );
}
$gContent->loadXrefInfo();

$gBitSmarty->assign( 'gContent',  $gContent );
$gBitSmarty->assign( 'gXrefInfo', $gContent->mXrefInfo );
$gBitSmarty->assign( 'contactLink', $gContent->getContactLink() );
$gBitSmarty->assign( 'canUpdate', $gBitUser->hasPermission( 'p_accounts_update' ) );

$gBitSystem->display( 'bitpackage:accounts/view_account.tpl', KernelTools::tra( 'Account' ).': '.$gContent->getTitle() );
