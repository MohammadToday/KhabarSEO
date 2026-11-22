<?php
/**
 * Capability checks.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Capabilities.
 */
final class Capabilities {

	/**
	 * Capability required to manage the plugin (default manage_options).
	 *
	 * @return string
	 */
	public static function manage(): string {
		$cap = apply_filters( 'khs_capability', 'manage_options' );
		return is_string( $cap ) && '' !== $cap ? $cap : 'manage_options';
	}

	/**
	 * Whether the current user may manage the plugin.
	 *
	 * @return bool
	 */
	public static function can_manage(): bool {
		return current_user_can( self::manage() );
	}
}
