<?php
/**
 * One-request flash messages shown after an admin-post redirect.
 * Messages are stored per user in a short-lived transient, never taken from the URL.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Flash messages.
 */
final class Flash {

	/**
	 * Queues a message for the current user.
	 *
	 * @param string $type success, error, warning or info.
	 * @param string $text Plain text (escaped on output).
	 * @return void
	 */
	public static function add( string $type, string $text ): void {
		$key      = self::key();
		$messages = get_transient( $key );
		$messages = is_array( $messages ) ? $messages : array();
		if ( count( $messages ) < 10 ) {
			$messages[] = array(
				'type' => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
				'text' => $text,
			);
		}
		set_transient( $key, $messages, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Prints and clears the queued messages.
	 *
	 * @return void
	 */
	public static function render(): void {
		$key      = self::key();
		$messages = get_transient( $key );
		if ( ! is_array( $messages ) || ! $messages ) {
			return;
		}
		delete_transient( $key );
		foreach ( $messages as $message ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( (string) $message['type'] ),
				esc_html( (string) $message['text'] )
			);
		}
	}

	/**
	 * Transient key of the current user.
	 *
	 * @return string
	 */
	private static function key(): string {
		return 'khs_flash_' . get_current_user_id();
	}
}
