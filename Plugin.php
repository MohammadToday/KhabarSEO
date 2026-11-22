<?php
/**
 * Plugin bootstrap.
 *
 * Front-end output is OFF until the setup wizard has finished, and stays off
 * while another SEO plugin is active or the KHS_DISABLE constant is true.
 * Part 1B/1C modules hook into the khs_output_ready action.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO;

use KhabarSEO\Admin\Menu;
use KhabarSEO\Conflicts\PluginDetector;
use KhabarSEO\Core\Installer;
use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Bootstrap.
 */
final class Plugin {

	/**
	 * Memoized output state.
	 *
	 * @var array|null
	 */
	private static ?array $state = null;

	/**
	 * Entry point (plugins_loaded).
	 *
	 * @return void
	 */
	public static function boot(): void {
		$plugin = new self();
		add_action( 'init', array( $plugin, 'init' ), 0 );
	}

	/**
	 * Initializes translations, admin and (when allowed) output modules.
	 *
	 * @return void
	 */
	public function init(): void {
		load_plugin_textdomain( 'khabar-seo', false, dirname( KHS_BASENAME ) . '/languages' );

		add_action( 'khs_settings_updated', array( self::class, 'flush_state' ) );
		add_action( 'wp_initialize_site', array( Installer::class, 'on_new_site' ) );

		if ( is_admin() ) {
			Installer::maybe_upgrade();
			( new Menu() )->register();
		}

		if ( self::output_state()['active'] ) {
			/**
			 * Fires when KhabarSEO front-end output is allowed. Output modules register here.
			 */
			do_action( 'khs_output_ready' );
		}
	}

	/**
	 * Whether front-end output is active, and why not.
	 *
	 * Reasons: kill_switch, wizard_pending, conflict.
	 *
	 * @return array{active:bool,reasons:string[],conflicts:array}
	 */
	public static function output_state(): array {
		if ( null !== self::$state ) {
			return self::$state;
		}
		$reasons   = array();
		$conflicts = array();
		if ( defined( 'KHS_DISABLE' ) && KHS_DISABLE ) {
			$reasons[] = 'kill_switch';
		}
		if ( ! Settings::is_on( 'state.wizard_done' ) ) {
			$reasons[] = 'wizard_pending';
		}
		if ( ! ( defined( 'KHS_FORCE_OUTPUT' ) && KHS_FORCE_OUTPUT ) ) {
			$conflicts = PluginDetector::seo_plugins();
			if ( $conflicts ) {
				$reasons[] = 'conflict';
			}
		}
		self::$state = array(
			'active'    => empty( $reasons ),
			'reasons'   => $reasons,
			'conflicts' => $conflicts,
		);
		return self::$state;
	}

	/**
	 * Clears the memoized state (after settings change).
	 *
	 * @return void
	 */
	public static function flush_state(): void {
		self::$state = null;
	}
}
