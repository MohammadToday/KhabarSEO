<?php
/**
 * Plugin deletion cleanup. Honors advanced.keep_data (default: keep).
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Uninstaller.
 */
final class Uninstaller {

	/**
	 * Runs for every site on multisite, or for the single site.
	 *
	 * @return void
	 */
	public static function run(): void {
		if ( is_multisite() ) {
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);
			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::run_site();
				restore_current_blog();
			}
			return;
		}
		self::run_site();
	}

	/**
	 * Cleans up the current site.
	 *
	 * @return void
	 */
	private static function run_site(): void {
		global $wpdb;

		$settings = get_option( 'khs_settings', array() );
		$keep     = ! is_array( $settings ) || ! isset( $settings['advanced']['keep_data'] ) || ! empty( $settings['advanced']['keep_data'] );
		if ( $keep ) {
			return;
		}

		delete_option( 'khs_settings' );
		delete_option( 'khs_settings_ext' );
		delete_transient( 'khs_activation_redirect' );

		$like = $wpdb->esc_like( '_khs_' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off uninstall cleanup.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $like ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $wpdb->esc_like( '_transient_khs_' ) . '%', $wpdb->esc_like( '_transient_timeout_khs_' ) . '%' ) );
		foreach ( array( 'khs_redirects', 'khs_404_log', 'khs_queue' ) as $table ) {
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $wpdb->prefix . $table ) );
		}
		// phpcs:enable
	}
}
