<?php
/**
 * Detects active plugins and themes that overlap with KhabarSEO output.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Conflicts;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin and theme detector.
 */
final class PluginDetector {

	/**
	 * Memoized data file.
	 *
	 * @var array|null
	 */
	private static ?array $data = null;

	/**
	 * Conflict data (data/conflicts.php).
	 *
	 * @return array
	 */
	public static function data(): array {
		if ( null === self::$data ) {
			self::$data = (array) require KHS_DIR . 'data/conflicts.php';
		}
		return self::$data;
	}

	/**
	 * Directory slugs of all active plugins (site and network).
	 *
	 * @return string[]
	 */
	public static function active_slugs(): array {
		$plugins = (array) get_option( 'active_plugins', array() );
		if ( is_multisite() ) {
			$plugins = array_merge( $plugins, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		$slugs = array();
		foreach ( $plugins as $file ) {
			$dir = dirname( (string) $file );
			// A plugin that is a single file in the plugins directory has no directory.
			$slugs[] = '.' === $dir ? basename( (string) $file, '.php' ) : $dir;
		}
		return array_values( array_unique( $slugs ) );
	}

	/**
	 * Detects entries of one data group.
	 *
	 * @param string $group Data group: seo_plugins, overlap_plugins or date_plugins.
	 * @return array<string, array> Slug => entry (with 'slug').
	 */
	public static function detect( string $group ): array {
		$entries = self::data()[ $group ] ?? array();
		$active  = self::active_slugs();
		$found   = array();
		foreach ( $entries as $slug => $entry ) {
			$by_constant = ! empty( $entry['constant'] ) && defined( $entry['constant'] );
			if ( in_array( $slug, $active, true ) || $by_constant ) {
				$entry['slug']  = $slug;
				$found[ $slug ] = $entry;
			}
		}
		return $found;
	}

	/**
	 * Active SEO plugins that output meta tags.
	 *
	 * @return array<string, array>
	 */
	public static function seo_plugins(): array {
		return self::detect( 'seo_plugins' );
	}

	/**
	 * Hint entry for the active theme (parent or child), or null.
	 *
	 * @return array|null
	 */
	public static function theme_hint(): ?array {
		$themes = self::data()['themes'] ?? array();
		foreach ( array_unique( array( get_template(), get_stylesheet() ) ) as $slug ) {
			if ( isset( $themes[ $slug ] ) ) {
				$entry         = $themes[ $slug ];
				$entry['slug'] = $slug;
				return $entry;
			}
		}
		return null;
	}
}
