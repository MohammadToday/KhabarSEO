<?php
/**
 * Plugin Name:       KhabarSEO
 * Plugin URI:        https://github.com/OWNER/khabar-seo
 * Description:       Lightweight, configurable SEO for news websites, built for Persian and other RTL publishers. No telemetry, no upsells.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Tested up to:      7.1
 * Requires PHP:      8.1
 * Author:            KhabarSEO contributors
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       khabar-seo
 * Domain Path:       /languages
 *
 * @package KhabarSEO
 */

// This file must stay parseable on old PHP versions so the requirements notice can be shown.

defined( 'ABSPATH' ) || exit;

define( 'KHS_VERSION', '0.1.0' );
define( 'KHS_FILE', __FILE__ );
define( 'KHS_DIR', plugin_dir_path( __FILE__ ) );
define( 'KHS_URL', plugin_dir_url( __FILE__ ) );
define( 'KHS_BASENAME', plugin_basename( __FILE__ ) );
define( 'KHS_MIN_PHP', '8.1' );
define( 'KHS_MIN_WP', '6.5' );
define( 'KHS_TESTED_WP', '7.1' );

/**
 * Lists unmet requirements (empty array when everything is fine).
 *
 * @return string[] Human readable problems (untranslated, escaped on output).
 */
function khs_requirements_errors() {
	global $wp_version;
	$errors = array();
	if ( version_compare( PHP_VERSION, KHS_MIN_PHP, '<' ) ) {
		$errors[] = 'PHP ' . KHS_MIN_PHP . '+ is required (this server runs ' . PHP_VERSION . ').';
	}
	if ( version_compare( $wp_version, KHS_MIN_WP, '<' ) ) {
		$errors[] = 'WordPress ' . KHS_MIN_WP . '+ is required (this site runs ' . $wp_version . ').';
	}
	if ( ! extension_loaded( 'mbstring' ) ) {
		$errors[] = 'The PHP mbstring extension is required for correct Persian/RTL text handling.';
	}
	return $errors;
}

/**
 * Shows the requirements notice in the admin.
 *
 * @return void
 */
function khs_requirements_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p><strong>KhabarSEO</strong> ' . esc_html( implode( ' ', khs_requirements_errors() ) ) . '</p></div>';
}

/**
 * Activation callback.
 *
 * @param bool $network_wide Whether this is a network-wide activation.
 * @return void
 */
function khs_activate( $network_wide = false ) {
	$errors = khs_requirements_errors();
	if ( $errors ) {
		deactivate_plugins( KHS_BASENAME );
		wp_die( esc_html( 'KhabarSEO cannot be activated: ' . implode( ' ', $errors ) ), '', array( 'back_link' => true ) );
	}
	require_once KHS_DIR . 'src/Autoloader.php';
	\KhabarSEO\Autoloader::register( KHS_DIR . 'src/' );
	\KhabarSEO\Core\Installer::activate( (bool) $network_wide );
}

/**
 * Deactivation callback.
 *
 * @return void
 */
function khs_deactivate() {
	if ( khs_requirements_errors() ) {
		return;
	}
	require_once KHS_DIR . 'src/Autoloader.php';
	\KhabarSEO\Autoloader::register( KHS_DIR . 'src/' );
	\KhabarSEO\Core\Installer::deactivate();
}

register_activation_hook( KHS_FILE, 'khs_activate' );
register_deactivation_hook( KHS_FILE, 'khs_deactivate' );

if ( khs_requirements_errors() ) {
	add_action( 'admin_notices', 'khs_requirements_notice' );
	return;
}

require_once KHS_DIR . 'src/Autoloader.php';
\KhabarSEO\Autoloader::register( KHS_DIR . 'src/' );

add_action( 'plugins_loaded', array( '\KhabarSEO\Plugin', 'boot' ) );
