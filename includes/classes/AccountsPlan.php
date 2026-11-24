<?php
/**
 * The forecast: planned lines (item PLAN on an account) expanded into the dated occurrences that fall in a window,
 * so a weekly line appears four times in a month. Planned lines are never real transactions; reports ignore them.
 *
 * @package accounts
 */
namespace Bitweaver\Accounts;

class AccountsPlan {

	/** Repeat rules and the step each takes. */
	const REPEATS = [ 'weekly' => '+7 days', '2weekly' => '+14 days', '4weekly' => '+28 days', 'monthly' => '+1 month' ];

	/**
	 * The date after $pFrom for a repeat rule, as an epoch, or null for no or an unknown rule. A monthly line keeps
	 * its day of the month where the month has it ($pDay, the day it was first set for, so a 31st falls to the month's last day and comes back to the 31st after).
	 */
	public static function nextDue( int $pFrom, string $pRepeat, \DateTimeZone $pTz, int $pDay = 0 ): ?int {
		if( !isset( self::REPEATS[$pRepeat] ) ) {
			return null;
		}
		$d = ( new \DateTime( '@'.$pFrom ) )->setTimezone( $pTz );
		if( $pRepeat === 'monthly' ) {
			$day = $pDay ?: (int)$d->format( 'j' );
			$d->modify( 'first day of next month' );
			$d->setDate( (int)$d->format( 'Y' ), (int)$d->format( 'n' ), min( $day, (int)$d->format( 't' ) ) );
		} else {
			$d->modify( self::REPEATS[$pRepeat] );
		}
		return $d->getTimestamp();
	}

	/**
	 * Planned occurrences from $pFrom up to but not including $pTo (epochs) for these planned lines, oldest first. A line due
	 * before $pFrom that has not been marked done is overdue and is listed once, on its own date, flagged.
	 *
	 * @param  array<int,array> $pPlanned  rows from AccountsAccount::getPlanned()
	 * @return array<int,array> the row plus 'when' (epoch) and 'overdue'
	 */
	public static function occurrences( array $pPlanned, int $pFrom, int $pTo, \DateTimeZone $pTz ): array {
		$out = [];
		foreach( $pPlanned as $p ) {
			$until = null;
			if( $p['until'] !== '' && ( $u = \DateTime::createFromFormat( '!Y-m-d', $p['until'], $pTz ) ) ) {
				$until = $u->getTimestamp();
			}
			$when = $p['due'];
			if( $when < $pFrom ) {
				$out[] = $p + [ 'when' => $when, 'overdue' => true ];
				// the overdue one stands for itself; later repeats continue from the first on or after the window start
				while( $p['repeat'] !== '' && $when < $pFrom && ( $when = self::nextDue( $when, $p['repeat'], $pTz, (int)( $p['day'] ?? 0 ) ) ) !== null ) {
				}
			}
			$guard = 0;
			while( $when !== null && $when < $pTo && ( $until === null || $when <= $until ) && $guard++ < 400 ) {
				if( $when >= $pFrom ) {
					$out[] = $p + [ 'when' => $when, 'overdue' => false ];
				}
				$when = $p['repeat'] !== '' ? self::nextDue( $when, $p['repeat'], $pTz, (int)( $p['day'] ?? 0 ) ) : null;
			}
		}
		usort( $out, fn( $a, $b ) => $a['when'] <=> $b['when'] ?: $a['xref_id'] <=> $b['xref_id'] );
		return $out;
	}
}
