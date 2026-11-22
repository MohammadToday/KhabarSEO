<?php
/**
 * Text hygiene for Persian/RTL and other scripts. Uses mb_* and /u regexes;
 * never splits a UTF-8 sequence and never leaves a dangling ZWNJ.
 *
 * @package KhabarSEO
 */

declare(strict_types=1);

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Text helpers.
 */
final class Text {

	/**
	 * Zero width non-joiner (half-space), zero width joiner, and bidi marks.
	 * These are never treated as whitespace and must not end a truncated text.
	 */
	private const INVISIBLE = '\x{200C}\x{200D}\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{061C}';

	/**
	 * Collapses whitespace (including NBSP and other Unicode spaces) to single
	 * spaces, removes control characters, and keeps ZWNJ untouched.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	public static function normalize_space( string $text ): string {
		$text = mb_scrub( $text, 'UTF-8' );
		$text = (string) preg_replace( '/[\p{Cc}\p{Cs}]+/u', ' ', $text );
		$text = (string) preg_replace( '/[\s\p{Z}]+/u', ' ', $text );
		return trim( $text );
	}

	/**
	 * Length in characters.
	 *
	 * @param string $text Input.
	 * @return int
	 */
	public static function length( string $text ): int {
		return mb_strlen( $text, 'UTF-8' );
	}

	/**
	 * Truncates to at most $max characters including the ellipsis, preferring a
	 * word boundary. ZWNJ is part of a word (Persian half-space), so only real
	 * whitespace is a boundary. Trailing ZWNJ/ZWJ/bidi marks and punctuation are
	 * removed before the ellipsis.
	 *
	 * @param string $text     Input.
	 * @param int    $max      Maximum length including the ellipsis.
	 * @param string $ellipsis Appended when the text was cut.
	 * @return string
	 */
	public static function truncate( string $text, int $max, string $ellipsis = '…' ): string {
		$text = self::normalize_space( $text );
		if ( $max < 1 || self::length( $text ) <= $max ) {
			return $text;
		}
		$room = max( 0, $max - self::length( $ellipsis ) );
		$cut  = mb_substr( $text, 0, $room, 'UTF-8' );

		// Cutting exactly before a space is already a clean boundary.
		$next_is_space = ' ' === mb_substr( $text, $room, 1, 'UTF-8' );
		if ( ! $next_is_space && 1 === preg_match( '/^(.*)[\s\p{Z}]/su', $cut, $m ) && self::length( $m[1] ) >= (int) floor( $room * 0.6 ) ) {
			$cut = $m[1];
		}
		return self::strip_dangling( $cut ) . $ellipsis;
	}

	/**
	 * Removes trailing spaces, invisible joiners/marks and separator punctuation
	 * (including the Persian comma and semicolon).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	public static function strip_dangling( string $text ): string {
		return (string) preg_replace( '/[\s\p{Z}' . self::INVISIBLE . ',.;:\x{060C}\x{061B}\-\x{2013}\x{2014}]+$/u', '', $text );
	}
}
