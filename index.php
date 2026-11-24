<?php
/**
 * Accounts — the list of accounts, each linking to its transactions.
 *
 * @package accounts
 * @subpackage functions
 */

namespace Bitweaver\Accounts;

use Bitweaver\KernelTools;

require_once '../kernel/includes/setup_inc.php';

global $gBitSystem, $gBitSmarty, $gBitUser, $gBitDb;

$gBitSystem->verifyPackage( 'accounts' );
$gBitSystem->verifyPermission( 'p_accounts_view' );

// Balances noted by hand (the bank's app is the only live source between statements).
if( !empty( $_POST['note_balance'] ) && $gBitUser->hasPermission( 'p_accounts_update' ) ) {
	foreach( (array)( $_POST['balance'] ?? [] ) as $id => $val ) {
		$val = str_replace( [ ',', '£', ' ' ], '', trim( (string)$val ) );
		if( $val !== '' && is_numeric( $val ) ) {
			$noted = AccountsAccount::loadFor( (int)$id );
			$noted->load();
			if( $noted->isValid() && !( $noted instanceof AccountsPaypal ) ) {
				$noted->storeBalanceNote( $val );
			}
		}
	}
	header( 'Location: '.ACCOUNTS_PKG_URL.'index.php' );
	die;
}

$accounts = $gBitDb->getAll(
	"SELECT lc.`content_id`, lc.`title`, lc.`content_type_guid`,
			( SELECT COUNT(*) FROM `".BIT_DB_PREFIX."liberty_xref` x
				WHERE x.`content_id` = lc.`content_id` AND x.`item` NOT IN ('CONTACT','OPENING','PLAN','BALANCE') AND x.`end_date` IS NULL ) AS txn_count,
			( SELECT FIRST 1 c.`title` FROM `".BIT_DB_PREFIX."liberty_xref` l
				JOIN `".BIT_DB_PREFIX."liberty_content` c ON ( c.`content_id` = l.`xref` )
				WHERE l.`content_id` = lc.`content_id` AND l.`item` = 'CONTACT' AND l.`end_date` IS NULL ) AS contact_title
		FROM `".BIT_DB_PREFIX."liberty_content` lc
		WHERE lc.`content_type_guid` IN ( ?, ? )
		ORDER BY lc.`title`",
	[ AccountsAccount::GUID, AccountsPaypal::GUID ]
);

// The next month: every planned line due in the window across the bank accounts, a repeating line once per occurrence,
// with the combined balance carried on from where the statements leave it.
$tz = $gBitUser->getUserTimezone();
$days = max( 7, min( 120, (int)( $_REQUEST['days'] ?? 28 ) ) );
$from = ( new \DateTime( 'today', $tz ) )->getTimestamp();
$to   = ( new \DateTime( 'today', $tz ) )->modify( '+'.$days.' days' )->getTimestamp();   // exclusive: 28 days is four weeks
$forecast = [];
$balances = [];
$startBalance = '0';
$hasPlan = false;
foreach( $accounts as $a ) {
	if( $a['content_type_guid'] !== AccountsAccount::GUID ) {
		continue;
	}
	$acct = AccountsAccount::loadFor( (int)$a['content_id'] );
	$acct->load();
	$cur = $acct->getCurrentBalance( $tz );
	$isCash = $acct->isCash();
	if( $isCash ) {
		$startBalance = bcadd( $startBalance, $cur['amount'], 2 );
	}
	$balances[] = [ 'cash' => $isCash, 'account_id' => (int)$a['content_id'], 'title' => $a['title'], 'amount' => number_format( (float)$cur['amount'], 2 ),
		'as_at' => $cur['as_at'] ? ( new \DateTime( '@'.$cur['as_at'] ) )->setTimezone( $tz )->format( 'j M' ) : '', 'source' => $cur['source'] ];
	foreach( $isCash ? AccountsPlan::occurrences( $acct->getPlanned(), $from, $to, $tz ) : [] as $o ) {
		$hasPlan = true;
		$forecast[] = $o + [ 'account' => $a['title'], 'account_id' => (int)$a['content_id'] ];
	}
}
usort( $forecast, fn( $x, $y ) => $x['when'] <=> $y['when'] ?: $x['xref_id'] <=> $y['xref_id'] );
$running = $startBalance;
foreach( $forecast as &$f ) {
	$running = bcadd( $running, $f['amount'], 2 );
	$f['date']    = ( new \DateTime( '@'.$f['when'] ) )->setTimezone( $tz )->format( 'D j M' );
	$f['out']     = bccomp( $f['amount'], '0', 2 ) < 0 ? number_format( (float)ltrim( $f['amount'], '-' ), 2 ) : '';
	$f['in']      = bccomp( $f['amount'], '0', 2 ) > 0 ? number_format( (float)$f['amount'], 2 ) : '';
	$f['balance'] = number_format( (float)$running, 2 );
}
unset( $f );
$totalOut = $totalIn = '0';
foreach( $forecast as $f ) {
	if( bccomp( $f['amount'], '0', 2 ) < 0 ) {
		$totalOut = bcadd( $totalOut, ltrim( $f['amount'], '-' ), 2 );
	} else {
		$totalIn = bcadd( $totalIn, $f['amount'], 2 );
	}
}
$gBitSmarty->assign( 'totalOut', number_format( (float)$totalOut, 2 ) );
$gBitSmarty->assign( 'totalIn',  number_format( (float)$totalIn, 2 ) );
$gBitSmarty->assign( 'spare',    number_format( (float)bcsub( $totalIn, $totalOut, 2 ), 2 ) );
$gBitSmarty->assign( 'balances', $balances );
$gBitSmarty->assign( 'canNote',  $gBitUser->hasPermission( 'p_accounts_update' ) );

$gBitSmarty->assign( 'forecast',     $forecast );
$gBitSmarty->assign( 'hasPlan',      $hasPlan );
$gBitSmarty->assign( 'days',         $days );
$gBitSmarty->assign( 'startBalance', number_format( (float)$startBalance, 2 ) );
$gBitSmarty->assign( 'accounts', $accounts );
$gBitSmarty->assign( 'canCreate', $gBitUser->hasPermission( 'p_accounts_create' ) );

$gBitSystem->display( 'bitpackage:accounts/list_accounts.tpl', KernelTools::tra( 'Accounts' ) );
