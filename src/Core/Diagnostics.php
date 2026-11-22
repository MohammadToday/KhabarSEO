<?php
/**
 * Status checks and the plain-text diagnostics report.
 *
 * The report contains no URLs, e-mail addresses, keys or user data.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Core;

use KhabarSEO\Conflicts\PluginDetector;
use KhabarSEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Diagnostics.
 */
final class Diagnostics {

	/**
	 * Human label of a level.
	 *
	 * @param string $level ok, info, warn or fail.
	 * @return string
	 */
	public static function level_label( string $level ): string {
		switch ( $level ) {
			case 'ok':
				return __( 'OK', 'khabar-seo' );
			case 'warn':
				return __( 'Warning', 'khabar-seo' );
			case 'fail':
				return __( 'Problem', 'khabar-seo' );
		}
		return __( 'Info', 'khabar-seo' );
	}

	/**
	 * All checks.
	 *
	 * @return array<int, array{id:string,label:string,level:string,detail:string}>
	 */
	public static function checks(): array {
		return array_merge(
			self::check_plugin(),
			self::check_php(),
			self::check_wordpress(),
			self::check_extensions(),
			self::check_permalinks(),
			self::check_timezone(),
			self::check_dates(),
			self::check_date_plugins(),
			self::check_runtime(),
			self::check_visibility(),
			self::check_output(),
			self::check_theme()
		);
	}

	/**
	 * Builds one row.
	 *
	 * @param string $id     Check id.
	 * @param string $label  Label.
	 * @param string $level  Level.
	 * @param string $detail Detail text.
	 * @return array
	 */
	private static function row( string $id, string $label, string $level, string $detail ): array {
		return array(
			'id'     => $id,
			'label'  => $label,
			'level'  => $level,
			'detail' => $detail,
		);
	}

	/**
	 * Plugin version.
	 *
	 * @return array
	 */
	private static function check_plugin(): array {
		/* translators: 1: plugin version, 2: settings schema version. */
		$detail = sprintf( __( 'Version %1$s, settings schema %2$d (beta).', 'khabar-seo' ), KHS_VERSION, Schema::VERSION );
		return array( self::row( 'plugin', __( 'KhabarSEO', 'khabar-seo' ), 'info', $detail ) );
	}

	/**
	 * PHP version and end-of-life status.
	 *
	 * @return array
	 */
	private static function check_php(): array {
		$status = Environment::php_status( PHP_VERSION );
		if ( 'fail' === $status['level'] && $status['known'] ) {
			/* translators: 1: PHP version, 2: end-of-life date. */
			$detail = sprintf( __( 'PHP %1$s reached end of life on %2$s and no longer gets security fixes. Ask your host to upgrade.', 'khabar-seo' ), PHP_VERSION, $status['eol'] );
		} elseif ( 'warn' === $status['level'] ) {
			/* translators: 1: PHP version, 2: end-of-life date. */
			$detail = sprintf( __( 'PHP %1$s reaches end of life on %2$s. Plan an upgrade.', 'khabar-seo' ), PHP_VERSION, $status['eol'] );
		} elseif ( $status['known'] ) {
			/* translators: 1: PHP version, 2: end-of-life date. */
			$detail = sprintf( __( 'PHP %1$s, supported until %2$s.', 'khabar-seo' ), PHP_VERSION, $status['eol'] );
		} else {
			/* translators: %s: PHP version. */
			$detail = sprintf( __( 'PHP %s (not in the end-of-life table).', 'khabar-seo' ), PHP_VERSION );
		}
		return array( self::row( 'php', __( 'PHP version', 'khabar-seo' ), $status['level'], $detail ) );
	}

	/**
	 * WordPress version.
	 *
	 * @return array
	 */
	private static function check_wordpress(): array {
		$version = get_bloginfo( 'version' );
		$level   = 'ok';
		/* translators: %s: WordPress version. */
		$detail = sprintf( __( 'WordPress %s.', 'khabar-seo' ), $version );
		if ( version_compare( $version, KHS_MIN_WP, '<' ) ) {
			$level = 'fail';
		} elseif ( version_compare( $version, KHS_TESTED_WP . '.99', '>' ) ) {
			$level = 'info';
			/* translators: 1: WordPress version, 2: highest tested version. */
			$detail = sprintf( __( 'WordPress %1$s is newer than the tested version (%2$s). Report any problem.', 'khabar-seo' ), $version, KHS_TESTED_WP );
		}
		return array( self::row( 'wordpress', __( 'WordPress version', 'khabar-seo' ), $level, $detail ) );
	}

	/**
	 * PHP extensions.
	 *
	 * @return array
	 */
	private static function check_extensions(): array {
		$rows   = array();
		$rows[] = extension_loaded( 'mbstring' )
			? self::row( 'ext_mbstring', __( 'PHP mbstring', 'khabar-seo' ), 'ok', __( 'Available.', 'khabar-seo' ) )
			: self::row( 'ext_mbstring', __( 'PHP mbstring', 'khabar-seo' ), 'fail', __( 'Missing. Required for correct Persian/RTL text handling.', 'khabar-seo' ) );
		$rows[] = class_exists( '\DOMDocument' )
			? self::row( 'ext_dom', __( 'PHP DOM', 'khabar-seo' ), 'ok', __( 'Available.', 'khabar-seo' ) )
			: self::row( 'ext_dom', __( 'PHP DOM', 'khabar-seo' ), 'warn', __( 'Missing. The rendered head scan cannot run.', 'khabar-seo' ) );
		$rows[] = function_exists( 'idn_to_ascii' )
			? self::row( 'ext_intl', __( 'PHP intl', 'khabar-seo' ), 'ok', __( 'Available.', 'khabar-seo' ) )
			: self::row( 'ext_intl', __( 'PHP intl', 'khabar-seo' ), 'info', __( 'Missing. Internationalized domain names are not converted to ASCII (only matters for non-ASCII domains).', 'khabar-seo' ) );
		return $rows;
	}

	/**
	 * Permalink structure and install type.
	 *
	 * @return array
	 */
	private static function check_permalinks(): array {
		$structure = (string) get_option( 'permalink_structure', '' );
		$parts     = array();
		if ( '' === $structure ) {
			$parts[] = __( 'Plain permalinks (?p=ID).', 'khabar-seo' );
		} else {
			/* translators: %s: permalink structure such as /%postname%/. */
			$parts[] = sprintf( __( 'Structure %s', 'khabar-seo' ), $structure );
			$parts[] = '/' === substr( $structure, -1 ) ? __( 'with trailing slash.', 'khabar-seo' ) : __( 'without trailing slash.', 'khabar-seo' );
		}
		$path = (string) wp_parse_url( home_url(), PHP_URL_PATH );
		if ( '' !== trim( $path, '/' ) ) {
			$parts[] = __( 'Subdirectory install.', 'khabar-seo' );
		}
		if ( is_multisite() ) {
			$parts[] = __( 'Multisite.', 'khabar-seo' );
		}
		return array( self::row( 'permalinks', __( 'Permalinks', 'khabar-seo' ), 'info', implode( ' ', $parts ) ) );
	}

	/**
	 * Timezone, including the January/July offset check.
	 *
	 * @return array
	 */
	private static function check_timezone(): array {
		$tz = Environment::timezone_report();
		/* translators: 1: timezone, 2: January offset, 3: July offset, 4: PHP timezone database version. */
		$detail = sprintf( __( '%1$s. Offset in January %2$s, in July %3$s. PHP timezone database %4$s.', 'khabar-seo' ), $tz['name'], $tz['jan'], $tz['jul'], $tz['tzdb'] );
		$level  = 'ok';
		if ( 'fixed_offset' === $tz['issue'] ) {
			$level = 'fail';
			/* translators: %s: expected UTC offset such as +03:30. */
			$detail .= ' ' . sprintf( __( 'This time zone no longer changes for summer, so both offsets should be %s. Your server time zone data is outdated: ask your host to update PHP or tzdata, otherwise machine-readable dates will be off by an hour.', 'khabar-seo' ), $tz['expected'] );
		} elseif ( 'utc_default' === $tz['issue'] ) {
			$level   = 'warn';
			$detail .= ' ' . __( 'The timezone is UTC. Set your real timezone in Settings, General so published dates are correct.', 'khabar-seo' );
		}
		return array( self::row( 'timezone', __( 'Timezone', 'khabar-seo' ), $level, $detail ) );
	}

	/**
	 * Date conversion filters and the ISO value of the latest post.
	 *
	 * @return array
	 */
	private static function check_dates(): array {
		$rows = array();

		// 1. Is wp_date() altered? Compare with native PHP on a fixed instant (2023-03-21 12:00:00 UTC).
		$timestamp = 1679400000;
		$format    = 'Y-m-d\TH:i:sP';
		$native    = gmdate( $format, $timestamp );
		$filtered  = wp_date( $format, $timestamp, new \DateTimeZone( 'UTC' ) );
		if ( $native === $filtered ) {
			$rows[] = self::row( 'date_filters', __( 'Date functions', 'khabar-seo' ), 'ok', __( 'wp_date() returns standard Gregorian ISO dates.', 'khabar-seo' ) );
		} else {
			/* translators: 1: value from PHP, 2: value from wp_date(). */
			$detail = sprintf( __( 'A plugin or theme converts dates (calendar or digits). PHP gives %1$s but wp_date() gives %2$s. KhabarSEO bypasses these functions for machine-readable dates.', 'khabar-seo' ), $native, (string) $filtered );
			$rows[] = self::row( 'date_filters', __( 'Date functions', 'khabar-seo' ), 'info', $detail );
		}

		// 2. ISO value from the stored timestamp of the newest post.
		$posts = get_posts(
			array(
				'post_type'        => (array) Settings::get( 'site.news_post_types' ) ? (array) Settings::get( 'site.news_post_types' ) : array( 'post' ),
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		if ( ! $posts ) {
			$rows[] = self::row( 'iso_check', __( 'Machine date check', 'khabar-seo' ), 'info', __( 'No published post to check yet.', 'khabar-seo' ) );
			return $rows;
		}
		$post = $posts[0];
		$iso  = Time::iso_from_gmt( (string) $post->post_date_gmt );
		$ok   = false;
		if ( '' !== $iso && Time::is_machine_iso( $iso ) ) {
			try {
				$parsed = ( new \DateTimeImmutable( $iso ) )->getTimestamp();
				$stored = ( new \DateTimeImmutable( (string) $post->post_date_gmt, new \DateTimeZone( 'UTC' ) ) )->getTimestamp();
				$ok     = $parsed === $stored;
			} catch ( \Exception $e ) {
				$ok = false;
			}
		}
		if ( $ok ) {
			$theme_value = (string) get_the_date( 'c', $post );
			/* translators: 1: ISO date emitted by KhabarSEO, 2: value of get_the_date(). */
			$detail = sprintf( __( 'Newest post: KhabarSEO emits %1$s, identical to the stored GMT time. get_the_date() shows %2$s.', 'khabar-seo' ), $iso, $theme_value );
			$rows[] = self::row( 'iso_check', __( 'Machine date check', 'khabar-seo' ), 'ok', $detail );
		} else {
			$rows[] = self::row( 'iso_check', __( 'Machine date check', 'khabar-seo' ), 'fail', __( 'The ISO date built from the newest post does not match its stored GMT time.', 'khabar-seo' ) );
		}
		return $rows;
	}

	/**
	 * Calendar plugins listed in data/conflicts.php (date_plugins).
	 *
	 * @return array
	 */
	private static function check_date_plugins(): array {
		$rows      = array();
		$structure = (string) get_option( 'permalink_structure', '' );
		$has_dates = 1 === preg_match( '/%(year|monthnum|day|hour|minute|second)%/', $structure );
		foreach ( PluginDetector::detect( 'date_plugins' ) as $entry ) {
			$label = $entry['name'];
			if ( empty( $entry['option'] ) ) {
				$rows[] = self::row( 'dateplugin_' . $entry['slug'], $label, 'info', __( 'Detected. Its behavior is not verified: KhabarSEO still bypasses date functions for machine-readable dates.', 'khabar-seo' ) );
				continue;
			}
			$options = get_option( $entry['option'], array() );
			$options = is_array( $options ) ? $options : array();
			$dates   = self::truthy( $options[ $entry['date_flag'] ] ?? '' );
			$links   = self::truthy( $options[ $entry['link_flag'] ] ?? '' );
			$level   = 'info';
			$detail  = $dates
				? __( 'Date conversion is on: KhabarSEO never uses the converted date functions for machine-readable dates.', 'khabar-seo' )
				: __( 'Date conversion is off.', 'khabar-seo' );
			if ( $links ) {
				if ( $has_dates ) {
					$level   = 'warn';
					$detail .= ' ' . __( 'It also converts permalinks and date archives to the Jalali calendar, and your permalink structure contains date parts, so URLs and canonical values may use Jalali numbers. Consider a permalink structure without dates.', 'khabar-seo' );
				} else {
					$detail .= ' ' . __( 'It also converts permalinks and archives, but your permalink structure has no date parts, so post URLs are not affected.', 'khabar-seo' );
				}
			}
			$rows[] = self::row( 'dateplugin_' . $entry['slug'], $label, $level, $detail );
		}
		return $rows;
	}

	/**
	 * Cache, cron and settings size.
	 *
	 * @return array
	 */
	private static function check_runtime(): array {
		$rows   = array();
		$rows[] = self::row(
			'object_cache',
			__( 'Persistent object cache', 'khabar-seo' ),
			'info',
			wp_using_ext_object_cache() ? __( 'Active.', 'khabar-seo' ) : __( 'Not active. This is fine; KhabarSEO does not require one.', 'khabar-seo' )
		);
		$rows[] = self::row(
			'cron',
			__( 'WP-Cron', 'khabar-seo' ),
			'info',
			( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ? __( 'Disabled in wp-config.php (a system cron should call it).', 'khabar-seo' ) : __( 'Traffic-triggered (default).', 'khabar-seo' )
		);
		$size   = Settings::stored_size();
		$level  = $size > Settings::MAX_BYTES * 0.75 ? 'warn' : 'ok';
		/* translators: 1: current size in bytes, 2: maximum size in bytes. */
		$detail = sprintf( __( '%1$d of %2$d bytes used.', 'khabar-seo' ), $size, Settings::MAX_BYTES );
		$rows[] = self::row( 'settings_size', __( 'Autoloaded settings size', 'khabar-seo' ), $level, $detail );
		return $rows;
	}

	/**
	 * Settings, Reading: "Discourage search engines from indexing this site".
	 *
	 * @return array
	 */
	private static function check_visibility(): array {
		if ( get_option( 'blog_public' ) ) {
			return array( self::row( 'blog_public', __( 'Search engine visibility', 'khabar-seo' ), 'ok', __( 'Search engines may index this site.', 'khabar-seo' ) ) );
		}
		return array( self::row( 'blog_public', __( 'Search engine visibility', 'khabar-seo' ), 'fail', __( 'Settings, Reading asks search engines not to index this site, so WordPress sets every page to noindex, nofollow. KhabarSEO never overrides this. Untick that option when the site goes live.', 'khabar-seo' ) ) );
	}

	/**
	 * Output state, kill switch and detected plugins.
	 *
	 * @return array
	 */
	private static function check_output(): array {
		$state = Plugin::output_state();
		$rows  = array();
		if ( $state['active'] ) {
			$rows[] = self::row( 'output', __( 'Front-end output', 'khabar-seo' ), 'ok', __( 'Active.', 'khabar-seo' ) );
		} else {
			$messages = array();
			foreach ( $state['reasons'] as $reason ) {
				if ( 'kill_switch' === $reason ) {
					$messages[] = __( 'The KHS_DISABLE constant is set in wp-config.php.', 'khabar-seo' );
				} elseif ( 'wizard_pending' === $reason ) {
					$messages[] = __( 'The setup wizard has not been finished.', 'khabar-seo' );
				} elseif ( 'conflict' === $reason ) {
					$names = array_column( $state['conflicts'], 'name' );
					/* translators: %s: names of other SEO plugins. */
					$messages[] = sprintf( __( 'Another SEO plugin is active: %s.', 'khabar-seo' ), implode( ', ', $names ) );
				}
			}
			$rows[] = self::row( 'output', __( 'Front-end output', 'khabar-seo' ), 'warn', __( 'Inactive.', 'khabar-seo' ) . ' ' . implode( ' ', $messages ) );
		}
		$overlap = PluginDetector::detect( 'overlap_plugins' );
		foreach ( $overlap as $entry ) {
			$rows[] = self::row( 'overlap_' . $entry['slug'], $entry['name'], 'warn', (string) ( $entry['note'] ?? '' ) );
		}
		return $rows;
	}

	/**
	 * Active theme.
	 *
	 * @return array
	 */
	private static function check_theme(): array {
		$theme  = wp_get_theme();
		$detail = $theme->get( 'Name' ) . ' ' . $theme->get( 'Version' );
		if ( $theme->parent() ) {
			/* translators: %s: parent theme name. */
			$detail .= ' ' . sprintf( __( '(child of %s)', 'khabar-seo' ), $theme->parent()->get( 'Name' ) );
		}
		$level = 'info';
		if ( ! \KhabarSEO\Output\Head::theme_uses_document_title() ) {
			$level   = 'warn';
			$detail .= ' ' . ( Settings::is_on( 'advanced.legacy_title' )
				? __( 'The theme has no title-tag support; KhabarSEO replaces wp_title() because the compatibility option is on. Check the result with Scan now.', 'khabar-seo' )
				: __( 'The theme has no title-tag support and prints its own title, so KhabarSEO titles are not used. See Advanced, Theme compatibility.', 'khabar-seo' ) );
		}
		$hint = PluginDetector::theme_hint();
		if ( $hint ) {
			$detail .= ' ' . __( 'KhabarSEO has a compatibility hint for this theme (shown after a head scan).', 'khabar-seo' );
		}
		return array( self::row( 'theme', __( 'Active theme', 'khabar-seo' ), $level, $detail ) );
	}

	/**
	 * Whether a stored flag is on.
	 *
	 * @param mixed $value Stored value.
	 * @return bool
	 */
	private static function truthy( $value ): bool {
		return ! in_array( $value, array( '', '0', 0, false, null, 'off', 'no' ), true );
	}

	/**
	 * Plain-text report for support requests.
	 *
	 * @return string
	 */
	public static function report(): string {
		$lines   = array();
		$lines[] = '### KhabarSEO diagnostics';
		foreach ( self::checks() as $row ) {
			$lines[] = sprintf( '%s [%s]: %s', wp_strip_all_tags( $row['label'] ), strtoupper( $row['level'] ), wp_strip_all_tags( $row['detail'] ) );
		}
		$lines[] = 'Constants: KHS_DISABLE=' . ( defined( 'KHS_DISABLE' ) && KHS_DISABLE ? 'true' : 'false' ) . ', KHS_FORCE_OUTPUT=' . ( defined( 'KHS_FORCE_OUTPUT' ) && KHS_FORCE_OUTPUT ? 'true' : 'false' ) . ', WP_DEBUG=' . ( defined( 'WP_DEBUG' ) && WP_DEBUG ? 'true' : 'false' );
		$lines[] = 'Locale: ' . get_locale() . ', news post types: ' . implode( ',', (array) Settings::get( 'site.news_post_types' ) );
		$active  = PluginDetector::active_slugs();
		sort( $active );
		$lines[] = 'Active plugins: ' . implode( ', ', $active );
		return implode( "\n", $lines );
	}
}
