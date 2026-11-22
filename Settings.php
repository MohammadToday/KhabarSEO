<?php
/**
 * Settings storage.
 *
 * khs_settings     autoload=yes, hard 8 KB cap, hot-path values only.
 * khs_settings_ext autoload=no, bulky or cold data (scan results, later lists).
 *
 * Call on or after the init action: the schema is cheap but reads site locale.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Settings access.
 */
final class Settings {

	public const OPTION     = 'khs_settings';
	public const OPTION_EXT = 'khs_settings_ext';
	public const MAX_BYTES  = 8192;

	/**
	 * Raw stored settings (nested group => name => value).
	 *
	 * @var array|null
	 */
	private static ?array $stored = null;

	/**
	 * Raw ext option.
	 *
	 * @var array|null
	 */
	private static ?array $ext = null;

	/**
	 * Clears the in-memory caches (used by tests and after resets).
	 *
	 * @return void
	 */
	public static function flush_cache(): void {
		self::$stored = null;
		self::$ext    = null;
	}

	/**
	 * Loads the stored option, running pending migrations once.
	 *
	 * @return array
	 */
	private static function load(): array {
		if ( null !== self::$stored ) {
			return self::$stored;
		}
		$raw = get_option( self::OPTION, array() );
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}
		$version = (int) ( $raw['state']['schema'] ?? 0 );
		if ( $version < Schema::VERSION ) {
			$raw = Migrations::run( $raw, $version );
			self::persist( $raw );
		}
		self::$stored = $raw;
		return $raw;
	}

	/**
	 * Effective value of one field (stored value, else dynamic default, else default).
	 *
	 * @param string $key Field key such as "site.publication_name".
	 * @return mixed Null for unknown keys.
	 */
	public static function get( string $key ) {
		$spec = Schema::field( $key );
		if ( null === $spec ) {
			return null;
		}
		$stored               = self::load();
		list( $group, $name ) = explode( '.', $key, 2 );
		if ( isset( $stored[ $group ] ) && is_array( $stored[ $group ] ) && array_key_exists( $name, $stored[ $group ] ) ) {
			$value = $stored[ $group ][ $name ];
			if ( '' === $value && '' !== $spec['dynamic'] ) {
				return self::dynamic( $spec['dynamic'] );
			}
			return $value;
		}
		if ( '' !== $spec['dynamic'] ) {
			return self::dynamic( $spec['dynamic'] );
		}
		return $spec['default'];
	}

	/**
	 * Convenience boolean getter.
	 *
	 * @param string $key Field key.
	 * @return bool
	 */
	public static function is_on( string $key ): bool {
		return (bool) self::get( $key );
	}

	/**
	 * Values derived from the site, used when a field is empty.
	 *
	 * @param string $name Dynamic source name.
	 * @return string
	 */
	private static function dynamic( string $name ): string {
		switch ( $name ) {
			case 'language':
				return Locale::site_language();
			case 'locale':
				return Locale::site_locale();
		}
		return '';
	}

	/**
	 * Flat map of effective values for all editable (non-internal) fields.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$out = array();
		foreach ( Schema::keys() as $key ) {
			$out[ $key ] = self::get( $key );
		}
		return $out;
	}

	/**
	 * Stores already-sanitized values.
	 *
	 * @param array<string, mixed> $values         Flat key => value map.
	 * @param bool                 $allow_internal Allow internal state keys.
	 * @return array{ok:bool,error:string}
	 */
	public static function update( array $values, bool $allow_internal = false ): array {
		$old = self::load();
		$new = $old;
		foreach ( $values as $key => $value ) {
			$spec = Schema::field( (string) $key );
			if ( null === $spec || ( $spec['internal'] && ! $allow_internal ) ) {
				continue;
			}
			list( $group, $name )   = explode( '.', (string) $key, 2 );
			$new[ $group ][ $name ] = $value;
		}
		return self::save_nested( $new, $old );
	}

	/**
	 * Writes a full nested array if it respects the size cap.
	 *
	 * @param array $updated Nested settings.
	 * @param array $old Previous nested settings (for the action).
	 * @return array{ok:bool,error:string}
	 */
	private static function save_nested( array $updated, array $old ): array {
		if ( strlen( maybe_serialize( $updated ) ) > self::MAX_BYTES ) {
			return array(
				'ok'    => false,
				'error' => 'size',
			);
		}
		if ( ! self::persist( $updated ) ) {
			return array(
				'ok'    => false,
				'error' => 'write',
			);
		}
		self::$stored = $updated;
		do_action( 'khs_settings_updated', $updated, $old );
		return array(
			'ok'    => true,
			'error' => '',
		);
	}

	/**
	 * Writes the option (autoload yes). Unchanged data counts as success.
	 *
	 * @param array $data Nested settings.
	 * @return bool
	 */
	private static function persist( array $data ): bool {
		$current = get_option( self::OPTION, null );
		if ( is_array( $current ) && maybe_serialize( $current ) === maybe_serialize( $data ) ) {
			return true;
		}
		return (bool) update_option( self::OPTION, $data, 'yes' );
	}

	/**
	 * Marks internal state (plugin version, schema version).
	 *
	 * @param array $state Flat internal keys, e.g. array( 'state.wizard_done' => true ).
	 * @return array{ok:bool,error:string}
	 */
	public static function set_state( array $state ): array {
		return self::update( $state, true );
	}

	/**
	 * Replaces everything with defaults (internal state is reset as well).
	 *
	 * @return array{ok:bool,error:string}
	 */
	public static function reset(): array {
		$defaults                    = Schema::defaults_nested();
		$defaults['state']['schema'] = Schema::VERSION;
		$defaults['state']['plugin'] = defined( 'KHS_VERSION' ) ? KHS_VERSION : '';
		$old                         = self::load();
		self::$ext                   = null;
		delete_option( self::OPTION_EXT );
		return self::save_nested( $defaults, $old );
	}

	/**
	 * Builds the export document (KhabarSEO's own JSON, no internal state).
	 *
	 * @return array
	 */
	public static function export(): array {
		$settings = array();
		$stored   = self::load();
		foreach ( Schema::keys() as $key ) {
			list( $group, $name ) = explode( '.', $key, 2 );
			$settings[ $key ]     = $stored[ $group ][ $name ] ?? Schema::field( $key )['default'];
		}
		return array(
			'plugin'   => 'khabar-seo',
			'format'   => 1,
			'schema'   => Schema::VERSION,
			'version'  => defined( 'KHS_VERSION' ) ? KHS_VERSION : '',
			'settings' => $settings,
		);
	}

	/**
	 * Imports an export document. Everything goes through the sanitizer.
	 *
	 * @param mixed $data Decoded JSON.
	 * @return array{ok:bool,error:string,applied:int}
	 */
	public static function import( $data ): array {
		$fail = static function ( string $code ): array {
			return array(
				'ok'      => false,
				'error'   => $code,
				'applied' => 0,
			);
		};
		if ( ! is_array( $data ) || ( $data['plugin'] ?? '' ) !== 'khabar-seo' || ! isset( $data['settings'] ) || ! is_array( $data['settings'] ) ) {
			return $fail( 'format' );
		}
		if ( (int) ( $data['format'] ?? 0 ) < 1 || (int) $data['format'] > 1 ) {
			return $fail( 'format' );
		}
		$settings = Migrations::migrate_flat( $data['settings'], (int) ( $data['schema'] ?? 1 ) );
		$keys     = array_values( array_intersect( Schema::keys(), array_keys( $settings ) ) );
		if ( ! $keys ) {
			return $fail( 'empty' );
		}
		$clean  = Sanitizer::sanitize_many( $settings, $keys );
		$result = self::update( $clean['values'] );
		return array(
			'ok'      => $result['ok'],
			'error'   => $result['error'],
			'applied' => $result['ok'] ? count( $clean['values'] ) : 0,
		);
	}

	/**
	 * Reads one value from the autoload=no ext option.
	 *
	 * @param string $key     Ext key.
	 * @param mixed  $fallback Fallback.
	 * @return mixed
	 */
	public static function get_ext( string $key, $fallback = null ) {
		if ( null === self::$ext ) {
			$raw       = get_option( self::OPTION_EXT, array() );
			self::$ext = is_array( $raw ) ? $raw : array();
		}
		return self::$ext[ $key ] ?? $fallback;
	}

	/**
	 * Writes one value to the ext option (autoload no).
	 *
	 * @param string $key   Ext key.
	 * @param mixed  $value Value (arrays or scalars).
	 * @return void
	 */
	public static function set_ext( string $key, $value ): void {
		self::get_ext( $key );
		self::$ext[ $key ] = $value;
		update_option( self::OPTION_EXT, self::$ext, 'no' );
	}

	/**
	 * Serialized size in bytes of the autoloaded option (for the Status page).
	 *
	 * @return int
	 */
	public static function stored_size(): int {
		return strlen( maybe_serialize( self::load() ) );
	}
}
