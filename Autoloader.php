<?php
/**
 * Minimal PSR-4 autoloader (no Composer at runtime).
 *
 * @package KhabarSEO
 */

namespace KhabarSEO;

defined( 'ABSPATH' ) || exit;

/**
 * Maps the KhabarSEO\ namespace to the src/ directory.
 */
final class Autoloader {

	/**
	 * Registers the autoloader.
	 *
	 * @param string $base_dir Absolute path to src/ with trailing slash.
	 * @return void
	 */
	public static function register( string $base_dir ): void {
		spl_autoload_register(
			static function ( string $class_name ) use ( $base_dir ): void {
				$prefix = 'KhabarSEO\\';
				if ( 0 !== strncmp( $class_name, $prefix, strlen( $prefix ) ) ) {
					return;
				}
				$relative = substr( $class_name, strlen( $prefix ) );
				$file     = $base_dir . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require $file;
				}
			}
		);
	}
}
