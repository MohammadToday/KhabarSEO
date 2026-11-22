<?php
/**
 * Schema-driven sanitization. Every value that reaches storage passes here.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizer.
 */
final class Sanitizer {

	/**
	 * Sanitizes several fields.
	 *
	 * @param array    $input Raw input, already unslashed, keyed by field key.
	 * @param string[] $keys  Keys to process. A listed key missing from the input counts as empty/false.
	 * @return array{values:array<string,mixed>,errors:array<string,string>}
	 */
	public static function sanitize_many( array $input, array $keys ): array {
		$values = array();
		$errors = array();
		foreach ( $keys as $key ) {
			$spec = Schema::field( $key );
			if ( null === $spec || $spec['internal'] ) {
				continue;
			}
			$error          = '';
			$values[ $key ] = self::sanitize_value( $spec, $input[ $key ] ?? null, $error );
			if ( '' !== $error ) {
				$errors[ $key ] = $error;
			}
		}
		return array(
			'values' => $values,
			'errors' => $errors,
		);
	}

	/**
	 * Sanitizes one value by schema type.
	 *
	 * @param array  $spec  Field definition.
	 * @param mixed  $raw   Raw value (null = not submitted).
	 * @param string $error Receives an error code, empty when fine.
	 * @return mixed
	 */
	public static function sanitize_value( array $spec, $raw, string &$error = '' ) {
		$error = '';
		switch ( $spec['type'] ) {
			case 'bool':
				return self::to_bool( $raw );

			case 'int':
				return self::integer( $spec, $raw, $error );

			case 'choice':
				$value = is_scalar( $raw ) ? (string) $raw : '';
				if ( in_array( $value, array_map( 'strval', $spec['options'] ), true ) ) {
					return $value;
				}
				$error = 'choice';
				return $spec['default'];

			case 'post_types':
				return self::post_types( $raw );

			case 'media':
				return self::media_id( $raw );

			case 'handle':
				return self::handle( $raw, $error );

			case 'lang':
				return self::language( $raw, $error );

			case 'locale':
				return self::locale( $raw, $error );

			case 'template':
				return self::template( $raw, (int) ( $spec['max'] ?? 300 ), $error );

			case 'tpl_map':
				return self::template_map( $spec, $raw, $error );

			case 'list':
				return self::token_list( $raw, (int) ( $spec['max'] ?? 40 ) );

			case 'text':
			default:
				return self::text( $raw, (int) ( $spec['max'] ?? 0 ), $error );
		}
	}

	/**
	 * Single-line text.
	 *
	 * @param mixed  $raw   Raw value.
	 * @param int    $max   Maximum characters (0 = unlimited).
	 * @param string $error Error code.
	 * @return string
	 */
	public static function text( $raw, int $max, string &$error = '' ): string {
		$text = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
		$text = Text::normalize_space( $text );
		if ( $max > 0 && mb_strlen( $text, 'UTF-8' ) > $max ) {
			$text  = mb_substr( $text, 0, $max, 'UTF-8' );
			$error = 'length';
		}
		return $text;
	}

	/**
	 * Integer clamped to the schema range (min/max null = unbounded).
	 *
	 * @param array  $spec  Field definition.
	 * @param mixed  $raw   Raw value.
	 * @param string $error Error code.
	 * @return int
	 */
	private static function integer( array $spec, $raw, string &$error ): int {
		$number = ( is_scalar( $raw ) && 1 === preg_match( '/^-?\d+$/', trim( (string) $raw ) ) ) ? (int) $raw : null;
		if ( null === $number ) {
			if ( null !== $raw && '' !== $raw ) {
				$error = 'range';
			}
			return (int) $spec['default'];
		}
		$clamped = $number;
		if ( null !== $spec['min'] ) {
			$clamped = max( (int) $spec['min'], $clamped );
		}
		if ( null !== $spec['max'] ) {
			$clamped = min( (int) $spec['max'], $clamped );
		}
		if ( $clamped !== $number ) {
			$error = 'range';
		}
		return $clamped;
	}

	/**
	 * Title or description template. Unknown variables are kept but reported.
	 *
	 * @param mixed  $raw   Raw value.
	 * @param int    $max   Maximum characters.
	 * @param string $error Error code.
	 * @return string
	 */
	public static function template( $raw, int $max, string &$error = '' ): string {
		$value = self::text( $raw, $max, $error );
		if ( '' === $error && Template::unknown_variables( $value ) ) {
			$error = 'variables';
		}
		return $value;
	}

	/**
	 * Map of object slug => template. Entries equal to the default are dropped
	 * to keep the autoloaded option small.
	 *
	 * @param array  $spec  Field definition.
	 * @param mixed  $raw   Raw map.
	 * @param string $error Error code.
	 * @return array<string, string>
	 */
	private static function template_map( array $spec, $raw, string &$error ): array {
		$allowed = 'taxonomies' === $spec['source'] ? array_keys( self::selectable_taxonomies() ) : array_keys( self::selectable_post_types() );
		$out     = array();
		foreach ( (array) $raw as $slug => $value ) {
			if ( ! is_string( $slug ) || ! in_array( $slug, $allowed, true ) ) {
				continue;
			}
			$item_error = '';
			$template   = self::template( $value, (int) ( $spec['max'] ?? 300 ), $item_error );
			if ( '' !== $item_error && '' === $error ) {
				$error = $item_error;
			}
			if ( '' !== $template && $template !== $spec['item_default'] ) {
				$out[ $slug ] = $template;
			}
		}
		ksort( $out );
		return $out;
	}

	/**
	 * List of query-parameter names (letters, digits, _ - [ ] and a trailing *).
	 *
	 * @param mixed $raw Raw list (array or newline/comma separated string).
	 * @param int   $max Maximum number of entries.
	 * @return string[]
	 */
	public static function token_list( $raw, int $max ): array {
		$items = is_array( $raw ) ? $raw : preg_split( '/[\r\n,]+/', is_scalar( $raw ) ? (string) $raw : '' );
		$out   = array();
		foreach ( (array) $items as $item ) {
			$item = strtolower( trim( is_scalar( $item ) ? (string) $item : '' ) );
			if ( '' === $item || 1 !== preg_match( '/^[a-z0-9_\-\[\]]+\*?$/', $item ) || in_array( $item, $out, true ) ) {
				continue;
			}
			$out[] = $item;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Checkbox-style boolean.
	 *
	 * @param mixed $raw Raw value.
	 * @return bool
	 */
	private static function to_bool( $raw ): bool {
		if ( is_bool( $raw ) ) {
			return $raw;
		}
		return in_array( $raw, array( '1', 1, 'on', 'true', 'yes' ), true );
	}

	/**
	 * Public post types except attachments, restricted to registered ones.
	 *
	 * @param mixed $raw Raw list.
	 * @return string[]
	 */
	private static function post_types( $raw ): array {
		$allowed = array_keys( self::selectable_post_types() );
		$out     = array();
		foreach ( (array) $raw as $slug ) {
			if ( is_string( $slug ) && in_array( $slug, $allowed, true ) && ! in_array( $slug, $out, true ) ) {
				$out[] = $slug;
			}
		}
		return $out;
	}

	/**
	 * Post types that can be configured (public, without attachments).
	 *
	 * @return array<string, object>
	 */
	public static function selectable_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
	}

	/**
	 * Taxonomies that can be configured (public ones with archive pages).
	 *
	 * @return array<string, object>
	 */
	public static function selectable_taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'             => true,
				'publicly_queryable' => true,
			),
			'objects'
		);
		unset( $taxonomies['post_format'] );
		return $taxonomies;
	}

	/**
	 * Attachment ID of an image, or 0.
	 *
	 * @param mixed $raw Raw value.
	 * @return int
	 */
	private static function media_id( $raw ): int {
		$id = is_numeric( $raw ) ? absint( $raw ) : 0;
		if ( $id > 0 && ( 'attachment' !== get_post_type( $id ) || ! wp_attachment_is_image( $id ) ) ) {
			return 0;
		}
		return $id;
	}

	/**
	 * X handle: "@name" (1-15 letters, digits, underscore) or empty.
	 *
	 * @param mixed  $raw   Raw value.
	 * @param string $error Error code.
	 * @return string
	 */
	private static function handle( $raw, string &$error ): string {
		$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		$value = ltrim( $value, '@' );
		if ( '' === $value ) {
			return '';
		}
		if ( 1 !== preg_match( '/^[A-Za-z0-9_]{1,15}$/', $value ) ) {
			$error = 'format';
			return '';
		}
		return '@' . $value;
	}

	/**
	 * ISO 639 language code (2-3 lowercase letters) or empty (= detect).
	 *
	 * @param mixed  $raw   Raw value.
	 * @param string $error Error code.
	 * @return string
	 */
	private static function language( $raw, string &$error ): string {
		$value = is_scalar( $raw ) ? strtolower( trim( (string) $raw ) ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( 1 !== preg_match( '/^[a-z]{2,3}$/', $value ) ) {
			$error = 'format';
			return '';
		}
		return $value;
	}

	/**
	 * Locale such as fa_IR (also accepts fa-IR) or empty (= detect).
	 *
	 * @param mixed  $raw   Raw value.
	 * @param string $error Error code.
	 * @return string
	 */
	private static function locale( $raw, string &$error ): string {
		$value = is_scalar( $raw ) ? trim( str_replace( '-', '_', (string) $raw ) ) : '';
		if ( '' === $value ) {
			return '';
		}
		if ( 1 !== preg_match( '/^([a-z]{2,3})(?:_([A-Za-z]{2,3}))?$/', $value, $m ) ) {
			$error = 'format';
			return '';
		}
		return isset( $m[2] ) ? $m[1] . '_' . strtoupper( $m[2] ) : $m[1];
	}

	/**
	 * Absolute http(s) URL or empty string.
	 *
	 * @param mixed  $raw   Raw value.
	 * @param string $error Error code.
	 * @return string
	 */
	public static function absolute_url( $raw, string &$error = '' ): string {
		$value = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		if ( '' === $value ) {
			return '';
		}
		$url = esc_url_raw( $value, array( 'http', 'https' ) );
		if ( '' === $url || 1 !== preg_match( '#^https?://[^/\s]+#i', $url ) ) {
			$error = 'format';
			return '';
		}
		return $url;
	}
}
