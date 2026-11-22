<?php
/**
 * Registers the front-end output. Only called from the khs_output_ready action,
 * which fires when the wizard is finished, no other SEO plugin is active and
 * the KHS_DISABLE kill switch is not set.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Output;

use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end hooks.
 */
final class Frontend {

	/**
	 * Hooks titles, robots, description, canonical and attachment redirects.
	 *
	 * @return void
	 */
	public static function register(): void {
		if ( is_admin() ) {
			return;
		}
		add_filter( 'pre_get_document_title', array( Head::class, 'filter_document_title' ), 99 );
		// Registered on init, after the theme declared its features.
		self::maybe_legacy_title();

		add_filter( 'wp_robots', array( Robots::class, 'filter' ), 999 );

		// Replace core's singular-only canonical with ours (one tag on every context).
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( Head::class, 'print_tags' ), 1 );

		add_action( 'template_redirect', array( Attachments::class, 'maybe_redirect' ), 1 );
	}

	/**
	 * Hooks wp_title when the theme has no title-tag support and the option is on.
	 *
	 * @return void
	 */
	public static function maybe_legacy_title(): void {
		if ( did_action( 'after_setup_theme' ) && ! Head::theme_uses_document_title() && Settings::is_on( 'advanced.legacy_title' ) && ! has_filter( 'wp_title', array( Head::class, 'filter_wp_title' ) ) ) {
			add_filter( 'wp_title', array( Head::class, 'filter_wp_title' ), 99 );
		}
	}
}
