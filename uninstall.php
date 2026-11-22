<?php
/**
 * Runs when the plugin is deleted from the Plugins screen.
 *
 * @package KhabarSEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/src/Autoloader.php';
\KhabarSEO\Autoloader::register( __DIR__ . '/src/' );

\KhabarSEO\Core\Uninstaller::run();
