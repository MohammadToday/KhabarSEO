<?php
/**
 * Title and description template engine.
 *
 * Pure and side-effect free: variable values come from resolver callables
 * that are only called for variables present in the template, so unused
 * variables never cost a database query.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

declare(strict_types=1);

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Template engine.
 */
final class Template {

	/**
	 * Supported variable names.
	 */
	public const VARIABLES = array( 'title', 'sitename', 'tagline', 'category', 'author', 'date', 'sep', 'page', 'excerpt' );

	/**
	 * Internal marker that stands for the separator while cleaning up.
	 */
	private const SEP_MARK = "\u{E000}";

	/**
	 * Renders a template.
	 *
	 * Unknown %tokens% are kept literally (so "50%" or typos stay visible),
	 * values are plain text, separators next to empty values collapse, and
	 * leading or trailing separators are removed.
	 *
	 * @param string                             $template  Template such as "%title% %sep% %sitename%".
	 * @param array<string, string|callable>     $resolvers Variable name => value or callable returning a string.
	 * @param string                             $separator Separator text for %sep%.
	 * @return string Plain text (not escaped).
	 */
	public static function render( string $template, array $resolvers, string $separator ): string {
		if ( '' === trim( $template ) ) {
			return '';
		}
		$cache = array();
		$out   = (string) preg_replace_callback(
			'/%([a-z_]+)%/',
			static function ( array $m ) use ( $resolvers, &$cache ): string {
				$name = $m[1];
				if ( 'sep' === $name ) {
					return self::SEP_MARK;
				}
				if ( ! in_array( $name, self::VARIABLES, true ) ) {
					return $m[0];
				}
				if ( ! array_key_exists( $name, $cache ) ) {
					$value          = $resolvers[ $name ] ?? '';
					$value          = is_callable( $value ) ? $value() : $value;
					$cache[ $name ] = Text::normalize_space( (string) $value );
				}
				// A value must never introduce a separator of its own.
				return str_replace( self::SEP_MARK, '', $cache[ $name ] );
			},
			$template
		);
		return self::cleanup( $out, $separator );
	}

	/**
	 * Collapses repeated or dangling separators and whitespace.
	 *
	 * @param string $text      Rendered text containing SEP_MARK.
	 * @param string $separator Real separator.
	 * @return string
	 */
	private static function cleanup( string $text, string $separator ): string {
		$mark = self::SEP_MARK;
		// Any run of separators (with spaces between them) becomes one.
		$text = (string) preg_replace( '/(?:[\s\p{Z}]*' . $mark . '[\s\p{Z}]*)+/u', ' ' . $mark . ' ', $text );
		$text = Text::normalize_space( $text );
		// Strip separators at either end.
		$text = (string) preg_replace( '/^(?:' . $mark . '[\s\p{Z}]*)+|(?:[\s\p{Z}]*' . $mark . ')+$/u', '', $text );
		$text = trim( $text );
		$sep  = trim( $separator );
		return '' === $sep ? Text::normalize_space( str_replace( $mark, ' ', $text ) ) : str_replace( $mark, $sep, $text );
	}

	/**
	 * Variables used by a template that the engine does not know.
	 *
	 * @param string $template Template.
	 * @return string[]
	 */
	public static function unknown_variables( string $template ): array {
		preg_match_all( '/%([a-z_]+)%/', $template, $m );
		$unknown = array();
		foreach ( array_unique( $m[1] ) as $name ) {
			if ( 'sep' !== $name && ! in_array( $name, self::VARIABLES, true ) ) {
				$unknown[] = '%' . $name . '%';
			}
		}
		return $unknown;
	}

	/**
	 * Whether a template uses a variable.
	 *
	 * @param string $template Template.
	 * @param string $name     Variable name without percent signs.
	 * @return bool
	 */
	public static function uses( string $template, string $name ): bool {
		return false !== strpos( $template, '%' . $name . '%' );
	}
}
