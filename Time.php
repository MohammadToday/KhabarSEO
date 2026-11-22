<?php
/**
 * Machine-readable dates.
 *
 * These helpers use DateTimeImmutable and DateTimeInterface::format() only.
 * They never call date_i18n(), wp_date() or get_the_date(), so calendar
 * plugins that convert those functions (for example to Jalali) cannot change
 * the output. Output always uses ASCII digits and the Gregorian calendar.
 *
 * @package KhabarSEO
 */

declare(strict_types=1);

namespace KhabarSEO\Core;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Time helpers.
 */
final class Time {

	/**
	 * Whether a MySQL datetime is empty or the zero date.
	 *
	 * @param string $mysql MySQL datetime.
	 * @return bool
	 */
	public static function is_empty( string $mysql ): bool {
		return '' === $mysql || 0 === strpos( $mysql, '0000-00-00' );
	}

	/**
	 * ISO 8601 with offset from a GMT MySQL datetime (post_date_gmt).
	 *
	 * @param string            $mysql_gmt MySQL datetime in UTC.
	 * @param DateTimeZone|null $zone      Target zone, default the site zone.
	 * @return string Empty string for invalid input.
	 */
	public static function iso_from_gmt( string $mysql_gmt, ?DateTimeZone $zone = null ): string {
		if ( self::is_empty( $mysql_gmt ) ) {
			return '';
		}
		try {
			$date = new DateTimeImmutable( $mysql_gmt, new DateTimeZone( 'UTC' ) );
		} catch ( \Exception $e ) {
			return '';
		}
		return $date->setTimezone( $zone ?? wp_timezone() )->format( DateTimeInterface::ATOM );
	}

	/**
	 * ISO 8601 with offset from a local MySQL datetime (post_date).
	 *
	 * @param string            $mysql_local MySQL datetime in the site zone.
	 * @param DateTimeZone|null $zone        Site zone, default wp_timezone().
	 * @return string Empty string for invalid input.
	 */
	public static function iso_from_local( string $mysql_local, ?DateTimeZone $zone = null ): string {
		if ( self::is_empty( $mysql_local ) ) {
			return '';
		}
		try {
			$date = new DateTimeImmutable( $mysql_local, $zone ?? wp_timezone() );
		} catch ( \Exception $e ) {
			return '';
		}
		return $date->format( DateTimeInterface::ATOM );
	}

	/**
	 * Whether a string looks like a valid machine ISO 8601 date with offset,
	 * ASCII digits only and a Gregorian-plausible year.
	 *
	 * @param string $iso Candidate.
	 * @return bool
	 */
	public static function is_machine_iso( string $iso ): bool {
		if ( 1 !== preg_match( '/^(\d{4})-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $iso, $m ) ) {
			return false;
		}
		return (int) $m[1] >= 1970 && (int) $m[1] <= 2100;
	}
}
