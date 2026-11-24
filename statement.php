<?php
/**
 * The account's statement: every line in date order with money out, money in and a running balance,
 * paginated, optionally for one tax year (the running balance is still the true one).
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

const STATEMENT_PAGE_SIZE = 50;

$all = $gContent->getStatementLines( $gBitUser->getUserTimezone() );
$taxYears = array_values( array_unique( array_column( $all, 'taxYear' ) ) );
rsort( $taxYears );
$ty = (string)( $_REQUEST['ty'] ?? '' );
$lines = in_array( $ty, $taxYears, true ) ? array_values( array_filter( $all, fn( $l ) => $l['taxYear'] === $ty ) ) : $all;
if( !in_array( $ty, $taxYears, true ) ) {
	$ty = '';
}

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

$gBitSmarty->assign( 'gContent',  $gContent );
$gBitSmarty->assign( 'rows',      $rows );
$gBitSmarty->assign( 'total',     $total );
$gBitSmarty->assign( 'page',      $page );
$gBitSmarty->assign( 'pages',     $pages );
$gBitSmarty->assign( 'taxYears',  $taxYears );
$gBitSmarty->assign( 'ty',        $ty );
$gBitSmarty->assign( 'opening',   $opening ? number_format( (float)$opening['amount'], 2 ) : null );
$gBitSmarty->assign( 'closing',   $all ? $fmt( end( $all )['balance'] ) : ( $opening ? number_format( (float)$opening['amount'], 2 ) : '' ) );

$gBitSystem->display( 'bitpackage:accounts/statement.tpl', KernelTools::tra( 'Statement' ).': '.$gContent->getTitle() );
