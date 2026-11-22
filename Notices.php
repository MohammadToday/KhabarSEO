<?php
/**
 * Admin notices. Kept deliberately few: setup pending, conflicting SEO plugin,
 * kill switch, end-of-life PHP (plugin screens only).
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Core\Capabilities;
use KhabarSEO\Core\Environment;
use KhabarSEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Notices.
 */
final class Notices {

	/**
	 * Hooks the notices.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_notices', array( $this, 'render' ) );
	}

	/**
	 * Prints the notices for users who can manage the plugin.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			return;
		}
		$screen    = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$screen_id = $screen ? (string) $screen->id : '';
		$ours      = false !== strpos( $screen_id, 'khabar-seo' );
		$broad     = $ours || in_array( $screen_id, array( 'dashboard', 'plugins' ), true );
		$state     = Plugin::output_state();

		if ( ! $broad ) {
			return;
		}

		if ( in_array( 'wizard_pending', $state['reasons'], true ) && ( ! $ours || false === strpos( $screen_id, 'khabar-seo-setup' ) ) ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'KhabarSEO is installed but switched off.', 'khabar-seo' ),
				esc_html__( 'It adds nothing to your pages until you finish the setup wizard.', 'khabar-seo' ),
				esc_url( admin_url( 'admin.php?page=' . Menu::WIZARD_SLUG ) ),
				esc_html__( 'Start the setup wizard', 'khabar-seo' )
			);
		}

		if ( in_array( 'conflict', $state['reasons'], true ) ) {
			$names = implode( ', ', array_column( $state['conflicts'], 'name' ) );
			printf(
				'<div class="notice notice-error"><p><strong>%1$s</strong> %2$s</p></div>',
				esc_html(
					/* translators: %s: names of other SEO plugins. */
					sprintf( __( 'Another SEO plugin is active: %s.', 'khabar-seo' ), $names )
				),
				esc_html__( 'KhabarSEO output stays disabled so your pages do not get duplicate tags. Deactivate the other plugin first, then finish the wizard.', 'khabar-seo' )
			);
		}

		if ( $ours && in_array( 'kill_switch', $state['reasons'], true ) ) {
			printf(
				'<div class="notice notice-warning"><p>%s</p></div>',
				esc_html__( 'The KHS_DISABLE constant is set in wp-config.php, so KhabarSEO output is disabled.', 'khabar-seo' )
			);
		}

		if ( $ours ) {
			$php = Environment::php_status( PHP_VERSION );
			if ( $php['known'] && in_array( $php['level'], array( 'warn', 'fail' ), true ) ) {
				printf(
					'<div class="notice notice-%1$s"><p>%2$s</p></div>',
					'fail' === $php['level'] ? 'error' : 'warning',
					esc_html(
						/* translators: 1: PHP version, 2: end-of-life date. */
						sprintf( __( 'Your server runs PHP %1$s, whose security support ends or ended on %2$s. Ask your host to upgrade PHP.', 'khabar-seo' ), PHP_VERSION, $php['eol'] )
					)
				);
			}
		}
	}
}
