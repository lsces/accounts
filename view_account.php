<?php
/**
 * One account. The default display is its statement: every line in date order with money out, money in
 * and a running balance, paginated, optionally for one tax year (the running balance is still the true
 * one). The xref grid, read only, is on the edit page.
 *
 * @package accounts
 */

namespace Bitweaver\Accounts;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser;

$gBitSystem->verifyPackage( 'accounts' );
$gBitSystem->verifyPermission( 'p_accounts_view' );

$gContent = AccountsAccount::loadFor( (int)( $_REQUEST['content_id'] ?? 0 ) );
$gContent->load();
if( !$gContent->isValid() ) {
	$gBitSystem->fatalError( KernelTools::tra( 'No valid account specified.' ) );
}

const STATEMENT_PAGE_SIZE = 20;

$all = $gContent->getStatementLines( $gBitUser->getUserTimezone() );
$taxYears = array_values( array_unique( array_column( $all, 'taxYear' ) ) );
rsort( $taxYears );
$ty = (string)( $_REQUEST['ty'] ?? '' );
if( !in_array( $ty, $taxYears, true ) ) {
	$ty = '';
}
$lines = $ty === '' ? $all : array_values( array_filter( $all, fn( $l ) => $l['taxYear'] === $ty ) );

$total = count( $lines );
$pages = max( 1, (int)ceil( $total / STATEMENT_PAGE_SIZE ) );
$page = min( $pages, max( 1, (int)( $_REQUEST['page'] ?? 1 ) ) );
$rows = array_slice( $lines, ( $page - 1 ) * STATEMENT_PAGE_SIZE, STATEMENT_PAGE_SIZE );

$opening = $gContent->getOpening();
$fmt = fn( $v ) => $v === '' ? '' : number_format( (float)$v, 2 );
foreach( $rows as &$r ) {
	$r['out'] = $fmt( $r['out'] );
	$r['in'] = $fmt( $r['in'] );
	$r['balance'] = $fmt( $r['balance'] );
}
unset( $r );

// This account's own forecast: its planned lines for the next 12 weeks, the balance carried on from where it stands now.
$planRows = [];
if( !( $gContent instanceof AccountsPaypal ) ) {
	$cur = $gContent->getCurrentBalance( $gBitUser->getUserTimezone() );
	$running = $cur['amount'];
	$tz = $gBitUser->getUserTimezone();
	$today = ( new \DateTime( 'today', $tz ) )->getTimestamp();
	foreach( AccountsPlan::occurrences( $gContent->getPlanned(), $today, ( new \DateTime( 'today', $tz ) )->modify( '+84 days' )->getTimestamp(), $tz ) as $o ) {
		$running = bcadd( $running, $o['amount'], 2 );
		$planRows[] = [ 'date' => ( new \DateTime( '@'.$o['when'] ) )->setTimezone( $tz )->format( 'D j M Y' ), 'overdue' => $o['overdue'], 'description' => $o['description'],
			'out' => bccomp( $o['amount'], '0', 2 ) < 0 ? number_format( (float)ltrim( $o['amount'], '-' ), 2 ) : '',
			'in'  => bccomp( $o['amount'], '0', 2 ) > 0 ? number_format( (float)$o['amount'], 2 ) : '',
			'balance' => number_format( (float)$running, 2 ) ];
	}
	$gBitSmarty->assign( 'planNow', number_format( (float)$cur['amount'], 2 ) );
}
$gBitSmarty->assign( 'planRows',  $planRows );
$gBitSmarty->assign( 'gContent',   $gContent );
$gBitSmarty->assign( 'contactLink', $gContent->getContactLink() );
$gBitSmarty->assign( 'canUpdate',  $gBitUser->hasPermission( 'p_accounts_update' ) );
$gBitSmarty->assign( 'canImport',  $gContent::GUID === AccountsAccount::GUID && $gBitUser->hasPermission( 'p_accounts_create' ) );
$gBitSmarty->assign( 'rows',       $rows );
$gBitSmarty->assign( 'total',      $total );
$gBitSmarty->assign( 'page',       $page );
$gBitSmarty->assign( 'pages',      $pages );
$gBitSmarty->assign( 'taxYears',   $taxYears );
$gBitSmarty->assign( 'ty',         $ty );
$gBitSmarty->assign( 'opening',    $opening ? number_format( (float)$opening['amount'], 2 ) : null );
$gBitSmarty->assign( 'closing',    $all ? $fmt( end( $all )['balance'] ) : '' );

$gBitSystem->display( 'bitpackage:accounts/view_account.tpl', KernelTools::tra( 'Account' ).': '.$gContent->getTitle() );
