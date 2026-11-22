<?php
/**
 * Schema-driven sanitization. Every value that reaches storage passes here.
 *
 * @package KhabarSEO
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
				$number = is_numeric( $raw ) ? (int) $raw : (int) $spec['default'];
				if ( $spec['min'] > 0 || $spec['max'] > 0 ) {
					$clamped = max( (int) $spec['min'], $spec['max'] > 0 ? min( $number, (int) $spec['max'] ) : $number );
					if ( $clamped !== $number ) {
						$error = 'range';
					}
					$number = $clamped;
				}
				return $number;

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

			case 'text':
			default:
				$text = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
				$text = Text::normalize_space( $text );
				if ( $spec['max'] > 0 && mb_strlen( $text, 'UTF-8' ) > (int) $spec['max'] ) {
					$text  = mb_substr( $text, 0, (int) $spec['max'], 'UTF-8' );
					$error = 'length';
				}
				return $text;
		}
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
	 * Post types that can be marked as news.
	 *
	 * @return array<string, object>
	 */
	public static function selectable_post_types(): array {
		$types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $types['attachment'] );
		return $types;
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
}
