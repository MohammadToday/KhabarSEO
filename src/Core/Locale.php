<?php
/**
 * Site locale helpers. Nothing here is specific to a language: Persian
 * behavior follows the site locale and the settings.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Locale helpers.
 */
final class Locale {

	/**
	 * Site locale in og:locale form, for example fa_IR or en_US.
	 *
	 * @return string
	 */
	public static function site_locale(): string {
		return self::normalize( (string) get_locale() );
	}

	/**
	 * ISO 639 language of the site, for example fa.
	 *
	 * @return string
	 */
	public static function site_language(): string {
		$parts = explode( '_', self::site_locale() );
		return $parts[0];
	}

	/**
	 * Converts a WordPress or BCP 47 style locale to language_COUNTRY.
	 *
	 * @param string $locale Input such as "fa_IR", "fa-IR" or "en_US_posix".
	 * @return string
	 */
	public static function normalize( string $locale ): string {
		$locale = str_replace( '-', '_', trim( $locale ) );
		$parts  = explode( '_', $locale );
		$lang   = strtolower( $parts[0] );
		if ( '' === $lang ) {
			return 'en_US';
		}
		if ( isset( $parts[1] ) && 1 === preg_match( '/^[A-Za-z]{2,3}$/', $parts[1] ) ) {
			return $lang . '_' . strtoupper( $parts[1] );
		}
		return $lang;
	}

	/**
	 * BCP 47 form (fa-IR) of a locale, used by structured data in later phases.
	 *
	 * @param string $locale Locale such as fa_IR.
	 * @return string
	 */
	public static function bcp47( string $locale ): string {
		return str_replace( '_', '-', self::normalize( $locale ) );
	}
}
