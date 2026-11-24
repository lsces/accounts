<?php
/**
 * An account's planned lines (the forecast): add, change, mark done, remove. A planned line is something expected
 * on the account - a bill, a pension payment, the weekly rent - with its next due date and, if it repeats, how often.
 * Marking one done moves a repeating line to its next date and archives a one-off, so the forecast keeps its history.
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
if( $gContent instanceof AccountsPaypal ) {
	$gBitSystem->fatalError( KernelTools::tra( 'Planned lines are kept on bank and rent accounts.' ) );
}

$tz = $gBitUser->getUserTimezone();
$errors = [];
$planned = $gContent->getPlanned();
$byId = array_column( $planned, null, 'xref_id' );
$self = ACCOUNTS_PKG_URL.'plan.php?content_id='.(int)$gContent->mContentId;

if( !empty( $_POST['done'] ) && isset( $byId[(int)$_POST['done']] ) ) {
	$gContent->completePlanned( (int)$_POST['done'], $tz );
	header( 'Location: '.$self );
	die;
}
if( !empty( $_POST['remove'] ) && isset( $byId[(int)$_POST['remove']] ) ) {
	$gContent->archivePlanned( (int)$_POST['remove'] );
	header( 'Location: '.$self );
	die;
}

$editId = (int)( $_REQUEST['xref_id'] ?? 0 );
$form = [ 'xref_id' => 0, 'due' => date( 'Y-m-d' ), 'description' => '', 'direction' => 'out', 'amount' => '', 'repeat' => '', 'until' => '', 'via' => '', 'note' => '', 'adjust' => '' ];
if( $editId && isset( $byId[$editId] ) ) {
	$p = $byId[$editId];
	$form = [ 'xref_id' => $editId, 'due' => ( new \DateTime( '@'.$p['due'] ) )->setTimezone( $tz )->format( 'Y-m-d' ), 'description' => $p['description'],
		'direction' => bccomp( $p['amount'], '0', 2 ) < 0 ? 'out' : 'in', 'amount' => ltrim( $p['amount'], '-' ), 'repeat' => $p['repeat'], 'until' => $p['until'], 'via' => $p['via'], 'note' => $p['note'], 'adjust' => $p['adjust'] ];
}

if( !empty( $_POST['save'] ) ) {
	foreach( [ 'xref_id', 'due', 'description', 'direction', 'amount', 'repeat', 'until', 'via', 'note', 'adjust' ] as $k ) {
		$form[$k] = trim( (string)( $_POST[$k] ?? '' ) );
	}
	$due = \DateTime::createFromFormat( '!Y-m-d', $form['due'], $tz );
	$amount = str_replace( [ ',', '£' ], '', $form['amount'] );
	if( !$due ) {
		$errors['due'] = KernelTools::tra( 'Enter the date it is due.' );
	}
	if( $form['description'] === '' ) {
		$errors['description'] = KernelTools::tra( 'Say what it is.' );
	}
	if( !is_numeric( $amount ) ) {
		$errors['amount'] = KernelTools::tra( 'Enter the amount.' );
	}
	if( $form['repeat'] !== '' && !isset( AccountsPlan::REPEATS[$form['repeat']] ) ) {
		$errors['repeat'] = KernelTools::tra( 'Unknown repeat.' );
	}
	if( $form['until'] !== '' && !\DateTime::createFromFormat( '!Y-m-d', $form['until'], $tz ) ) {
		$errors['until'] = KernelTools::tra( 'The end date must be a date.' );
	}
	if( !$errors ) {
		$line = [
			'xref_id'     => ( $form['xref_id'] && isset( $byId[(int)$form['xref_id']] ) ) ? (int)$form['xref_id'] : 0,
			'due'         => $due->getTimestamp(),
			'amount'      => $form['direction'] === 'in' ? bcadd( ltrim( $amount, '-' ), '0', 2 ) : '-'.bcadd( ltrim( $amount, '-' ), '0', 2 ),
			'description' => $form['description'], 'repeat' => $form['repeat'], 'until' => $form['until'],
			'via'         => $form['via'] === 'paypal' ? 'paypal' : '', 'note' => $form['note'], 'adjust' => $form['adjust'],
		];
		if( $gContent->storePlanned( $line ) ) {
			header( 'Location: '.$self );
			die;
		}
		$errors[] = KernelTools::tra( 'The line was not saved. Has the accounts scheme been installed with the PLAN item?' );
	}
}

$rows = [];
foreach( $planned as $p ) {
	$rows[] = $p + [ 'date' => ( new \DateTime( '@'.$p['due'] ) )->setTimezone( $tz )->format( 'Y-m-d' ),
		'out' => bccomp( $p['amount'], '0', 2 ) < 0 ? number_format( (float)ltrim( $p['amount'], '-' ), 2 ) : '',
		'in'  => bccomp( $p['amount'], '0', 2 ) > 0 ? number_format( (float)$p['amount'], 2 ) : '' ];
}

$gBitSmarty->assign( 'gContent', $gContent );
$gBitSmarty->assign( 'rows',     $rows );
$gBitSmarty->assign( 'form',     $form );
$gBitSmarty->assign( 'errors',   $errors );
$gBitSmarty->assign( 'repeats',  array_keys( AccountsPlan::REPEATS ) );

$gBitSystem->display( 'bitpackage:accounts/plan.tpl', KernelTools::tra( 'Planned' ).': '.$gContent->getTitle() );
