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
	const REPEATS = [ 'weekly' => '+7 days', '2weekly' => '+14 days', '4weekly' => '+28 days', 'monthly' => '+1 month', '6monthly' => '+6 months', 'annually' => '+1 year' ];

	/** The repeats that count in calendar months, and how many: they keep the day of the month. */
	const MONTH_STEPS = [ 'monthly' => 1, '6monthly' => 6, 'annually' => 12 ];

	/**
	 * The date after $pFrom for a repeat rule, as an epoch, or null for no or an unknown rule. A monthly line keeps
	 * its day of the month where the month has it ($pDay, the day it was first set for, so a 31st falls to the month's last day and comes back to the 31st after).
	 */
	public static function nextDue( int $pFrom, string $pRepeat, \DateTimeZone $pTz, int $pDay = 0 ): ?int {
		if( !isset( self::REPEATS[$pRepeat] ) ) {
			return null;
		}
		$d = ( new \DateTime( '@'.$pFrom ) )->setTimezone( $pTz );
		if( isset( self::MONTH_STEPS[$pRepeat] ) ) {
			$day = $pDay ?: (int)$d->format( 'j' );
			$d->modify( 'first day of this month' )->modify( '+'.self::MONTH_STEPS[$pRepeat].' months' );
			$d->setDate( (int)$d->format( 'Y' ), (int)$d->format( 'n' ), min( $day, (int)$d->format( 't' ) ) );
		} else {
			$d->modify( self::REPEATS[$pRepeat] );
		}
		return $d->getTimestamp();
	}

	/** England and Wales bank holidays for a year as Y-m-d => true. Substitute days follow the usual rule; one-off royal holidays are not known. */
	public static function bankHolidays( int $pYear ): array {
		static $cache = [];
		if( isset( $cache[$pYear] ) ) {
			return $cache[$pYear];
		}
		// Easter Sunday (Meeus/Jones/Butcher)
		$a = $pYear % 19; $b = intdiv( $pYear, 100 ); $c = $pYear % 100;
		$d = intdiv( $b, 4 ); $e = $b % 4; $f = intdiv( $b + 8, 25 ); $g = intdiv( $b - $f + 1, 3 );
		$h = ( 19 * $a + $b - $d - $g + 15 ) % 30; $i = intdiv( $c, 4 ); $k = $c % 4;
		$l = ( 32 + 2 * $e + 2 * $i - $h - $k ) % 7; $m = intdiv( $a + 11 * $h + 22 * $l, 451 );
		$month = intdiv( $h + $l - 7 * $m + 114, 31 ); $day = ( ( $h + $l - 7 * $m + 114 ) % 31 ) + 1;
		$easter = new \DateTime( sprintf( '%04d-%02d-%02d', $pYear, $month, $day ) );

		$mondayOnOrAfter = fn( string $pDate ) => ( new \DateTime( $pDate ) )->modify( 'monday this week' )->format( 'Y-m-d' );
		$days = [];
		$fixed = [ "$pYear-01-01", "$pYear-12-25", "$pYear-12-26" ];
		$weekdayHolidays = [
			( clone $easter )->modify( '-2 days' )->format( 'Y-m-d' ),
			( clone $easter )->modify( '+1 day' )->format( 'Y-m-d' ),
			( new \DateTime( "first monday of may $pYear" ) )->format( 'Y-m-d' ),
			( new \DateTime( "last monday of may $pYear" ) )->format( 'Y-m-d' ),
			( new \DateTime( "last monday of august $pYear" ) )->format( 'Y-m-d' ),
		];
		foreach( $weekdayHolidays as $w ) {
			$days[$w] = true;
		}
		foreach( $fixed as $f ) {
			if( (int)date( 'N', strtotime( $f ) ) < 6 ) {
				$days[$f] = true;
			}
		}
		// a fixed holiday on a weekend moves to the next weekday that is not already a holiday
		foreach( $fixed as $f ) {
			if( (int)date( 'N', strtotime( $f ) ) >= 6 ) {
				$s = new \DateTime( $f );
				do {
					$s->modify( '+1 day' );
				} while( (int)$s->format( 'N' ) >= 6 || isset( $days[$s->format( 'Y-m-d' )] ) );
				$days[$s->format( 'Y-m-d' )] = true;
			}
		}
		return $cache[$pYear] = $days;
	}

	/** Not a weekend and not a bank holiday. */
	public static function isWorkingDay( \DateTime $pDate ): bool {
		return (int)$pDate->format( 'N' ) < 6 && !isset( self::bankHolidays( (int)$pDate->format( 'Y' ) )[$pDate->format( 'Y-m-d' )] );
	}

	/**
	 * The date a line is actually paid: its nominal date, or when it falls on a weekend or bank holiday, the working
	 * day before ('prev') or after ('next'). Any other rule leaves it alone.
	 */
	public static function adjusted( int $pNominal, string $pRule, \DateTimeZone $pTz ): int {
		if( $pRule !== 'prev' && $pRule !== 'next' ) {
			return $pNominal;
		}
		$d = ( new \DateTime( '@'.$pNominal ) )->setTimezone( $pTz );
		$step = $pRule === 'prev' ? '-1 day' : '+1 day';
		for( $i = 0; $i < 10 && !self::isWorkingDay( $d ); $i++ ) {
			$d->modify( $step );
		}
		return $d->getTimestamp();
	}

	/**
	 * Planned occurrences from $pFrom up to but not including $pTo (epochs), oldest first, each with 'when' (the day it is
	 * paid, after any weekend or bank-holiday rule) and 'nominal' (the date the repeat falls on). A line whose first date is
	 * before $pFrom and has not been marked done is overdue and is listed once, flagged.
	 *
	 * @param  array<int,array> $pPlanned  rows from AccountsAccount::getPlanned()
	 * @return array<int,array> the row plus 'when', 'nominal' and 'overdue'
	 */
	public static function occurrences( array $pPlanned, int $pFrom, int $pTo, \DateTimeZone $pTz ): array {
		$out = [];
		foreach( $pPlanned as $p ) {
			$until = null;
			if( $p['until'] !== '' && ( $u = \DateTime::createFromFormat( '!Y-m-d', $p['until'], $pTz ) ) ) {
				$until = $u->getTimestamp();
			}
			$rule = (string)( $p['adjust'] ?? '' );
			$nominal = $p['due'];
			$first = true;
			for( $guard = 0; $nominal !== null && $guard < 400 && ( $until === null || $nominal <= $until ); $guard++ ) {
				$when = self::adjusted( $nominal, $rule, $pTz );
				if( $when >= $pTo ) {
					break;
				}
				if( $when < $pFrom ) {
					if( $first ) {
						$out[] = $p + [ 'when' => $when, 'nominal' => $nominal, 'overdue' => true ];
					}
				} else {
					$out[] = $p + [ 'when' => $when, 'nominal' => $nominal, 'overdue' => false ];
				}
				$first = false;
				$nominal = $p['repeat'] !== '' ? self::nextDue( $nominal, $p['repeat'], $pTz, (int)( $p['day'] ?? 0 ) ) : null;
			}
		}
		usort( $out, fn( $a, $b ) => $a['when'] <=> $b['when'] ?: $a['xref_id'] <=> $b['xref_id'] );
		return $out;
	}
}
