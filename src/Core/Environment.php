<?php
/**
 * Environment checks used by the Status page.
 *
 * @package KhabarSEO
 */

declare(strict_types=1);

namespace KhabarSEO\Core;

use DateTimeImmutable;
use DateTimeZone;

defined( 'ABSPATH' ) || exit;

/**
 * Environment helpers.
 */
final class Environment {

	/**
	 * End-of-life status of a PHP version.
	 *
	 * @param string                 $version PHP version string.
	 * @param DateTimeImmutable|null $now     Current time (injectable for tests).
	 * @return array{level:string,branch:string,eol:string,days:int,known:bool}
	 */
	public static function php_status( string $version, ?DateTimeImmutable $now = null ): array {
		$data   = require KHS_DIR . 'data/php-eol.php';
		$parts  = explode( '.', $version );
		$branch = $parts[0] . '.' . ( $parts[1] ?? '0' );
		$now    = $now ?? new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
		$out    = array(
			'level'  => 'ok',
			'branch' => $branch,
			'eol'    => '',
			'days'   => 0,
			'known'  => false,
		);
		if ( ! isset( $data['branches'][ $branch ] ) ) {
			// Older than the table is end-of-life; newer than the table is simply unknown.
			$out['level'] = version_compare( $branch, '7.4', '<' ) ? 'fail' : 'ok';
			return $out;
		}
		$eol          = new DateTimeImmutable( $data['branches'][ $branch ] . ' 23:59:59', new DateTimeZone( 'UTC' ) );
		$out['known'] = true;
		$out['eol']   = $data['branches'][ $branch ];
		$out['days']  = (int) floor( ( $eol->getTimestamp() - $now->getTimestamp() ) / DAY_IN_SECONDS );
		if ( $out['days'] < 0 ) {
			$out['level'] = 'fail';
		} elseif ( $out['days'] <= 120 ) {
			$out['level'] = 'warn';
		}
		return $out;
	}

	/**
	 * Timezone report with the January and July UTC offsets of the current year.
	 *
	 * @param DateTimeZone|null $zone Zone to check, default the site zone.
	 * @param int|null          $year Year to sample, default the current year.
	 * @return array{name:string,is_offset:bool,jan:string,jul:string,tzdb:string,issue:string}
	 */
	public static function timezone_report( ?DateTimeZone $zone = null, ?int $year = null ): array {
		$zone = $zone ?? wp_timezone();
		$year = $year ?? (int) gmdate( 'Y' );
		$name = $zone->getName();
		$jan  = ( new DateTimeImmutable( $year . '-01-15 12:00:00', $zone ) )->format( 'P' );
		$jul  = ( new DateTimeImmutable( $year . '-07-15 12:00:00', $zone ) )->format( 'P' );
		$out  = array(
			'name'      => $name,
			'is_offset' => 1 === preg_match( '/^[+-]\d{2}:\d{2}$/', $name ),
			'jan'       => $jan,
			'jul'       => $jul,
			'tzdb'      => function_exists( 'timezone_version_get' ) ? timezone_version_get() : '',
			'issue'     => '',
		);
		// Iran abolished daylight saving time in 2022 (fixed +03:30). Old tz data would show a summer shift. [VERIFY with your host's tz data].
		if ( 'Asia/Tehran' === $name && ( '+03:30' !== $jan || '+03:30' !== $jul ) ) {
			$out['issue'] = 'tehran_dst';
		} elseif ( in_array( $name, array( 'UTC', '+00:00' ), true ) ) {
			$out['issue'] = 'utc_default';
		}
		return $out;
	}
}
