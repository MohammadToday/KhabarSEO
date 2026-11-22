<?php
/**
 * Admin menu, assets and handler registration.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Core\Capabilities;
use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Menu.
 */
final class Menu {

	public const SLUG        = 'khabar-seo';
	public const WIZARD_SLUG = 'khabar-seo-setup';

	/**
	 * Hook suffixes of our screens (assets load only there).
	 *
	 * @var string[]
	 */
	private array $screens = array();

	/**
	 * Registers menu, assets and the admin-post handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		add_filter( 'plugin_action_links_' . KHS_BASENAME, array( $this, 'action_links' ) );

		( new SettingsPage() )->register();
		( new Wizard() )->register();
		( new ToolsPage() )->register();
		( new StatusPage() )->register();
		( new Notices() )->register();
	}

	/**
	 * Adds the top-level menu and the wizard page.
	 *
	 * @return void
	 */
	public function add_menu(): void {
		$cap      = Capabilities::manage();
		$settings = new SettingsPage();
		$wizard   = new Wizard();

		$this->screens[] = (string) add_menu_page(
			__( 'KhabarSEO', 'khabar-seo' ),
			__( 'KhabarSEO', 'khabar-seo' ),
			$cap,
			self::SLUG,
			array( $settings, 'render' ),
			'dashicons-search',
			81
		);
		$this->screens[] = (string) add_submenu_page(
			self::SLUG,
			__( 'KhabarSEO settings', 'khabar-seo' ),
			__( 'Settings', 'khabar-seo' ),
			$cap,
			self::SLUG,
			array( $settings, 'render' )
		);
		$this->screens[] = (string) add_submenu_page(
			self::SLUG,
			__( 'KhabarSEO setup wizard', 'khabar-seo' ),
			__( 'Setup wizard', 'khabar-seo' ),
			$cap,
			self::WIZARD_SLUG,
			array( $wizard, 'render' )
		);
	}

	/**
	 * Loads admin CSS and JS on KhabarSEO screens and on post and term edit
	 * screens (for the override fields) only.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue( string $hook_suffix ): void {
		$ours   = in_array( $hook_suffix, $this->screens, true );
		$editor = in_array( $hook_suffix, array( 'post.php', 'post-new.php', 'term.php' ), true );
		if ( ! $ours && ! $editor ) {
			return;
		}
		if ( $ours ) {
			wp_enqueue_media();
		}
		wp_enqueue_style( 'khs-admin', KHS_URL . 'assets/admin/admin.css', array(), KHS_VERSION );
		wp_enqueue_script( 'khs-admin', KHS_URL . 'assets/admin/admin.js', array(), KHS_VERSION, true );
	}

	/**
	 * Sends a fresh install to the setup wizard once.
	 *
	 * @return void
	 */
	public function maybe_redirect_after_activation(): void {
		if ( ! get_transient( 'khs_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'khs_activation_redirect' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Core sets this flag on bulk activation, nothing is processed.
		if ( wp_doing_ajax() || is_network_admin() || isset( $_GET['activate-multi'] ) || ! Capabilities::can_manage() ) {
			return;
		}
		if ( Settings::is_on( 'state.wizard_done' ) ) {
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::WIZARD_SLUG ) );
		exit;
	}

	/**
	 * Adds Settings and Setup links on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( array $links ): array {
		$mine = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html__( 'Settings', 'khabar-seo' ) . '</a>',
		);
		return array_merge( $mine, $links );
	}
}
