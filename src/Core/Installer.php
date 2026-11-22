<?php
/**
 * Activation, deactivation and per-site setup.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
final class Installer {

	/**
	 * Activation. Handles network-wide activation on multisite.
	 *
	 * @param bool $network_wide Whether activated for the whole network.
	 * @return void
	 */
	public static function activate( bool $network_wide ): void {
		if ( is_multisite() && $network_wide ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::setup_site();
				restore_current_blog();
			}
			return;
		}
		self::setup_site();
		set_transient( 'khs_activation_redirect', 1, MINUTE_IN_SECONDS );
	}

	/**
	 * Deactivation. Nothing to tear down in Part 1A; data stays.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_transient( 'khs_activation_redirect' );
	}

	/**
	 * Creates the option for the current site (never overwrites existing data).
	 *
	 * @return void
	 */
	public static function setup_site(): void {
		if ( false !== get_option( Settings::OPTION, false ) ) {
			return;
		}
		$defaults                    = Schema::defaults_nested();
		$defaults['state']['schema'] = Schema::VERSION;
		$defaults['state']['plugin'] = KHS_VERSION;
		add_option( Settings::OPTION, $defaults, '', 'yes' );
	}

	/**
	 * Sets up sites created after a network-wide activation.
	 *
	 * @param \WP_Site $new_site New site.
	 * @return void
	 */
	public static function on_new_site( $new_site ): void {
		if ( ! is_plugin_active_for_network( KHS_BASENAME ) ) {
			return;
		}
		switch_to_blog( (int) $new_site->blog_id );
		self::setup_site();
		restore_current_blog();
	}

	/**
	 * Records the running plugin version after an update.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		if ( Settings::get( 'state.plugin' ) !== KHS_VERSION ) {
			Settings::set_state( array( 'state.plugin' => KHS_VERSION ) );
		}
	}
}
