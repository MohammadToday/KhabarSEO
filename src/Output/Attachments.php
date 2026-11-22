<?php
/**
 * Attachment pages.
 *
 * Verified in core (wp-includes/canonical.php, redirect_canonical()): when the
 * wp_attachment_pages_enabled option is off (the default for sites installed
 * on WordPress 6.4 or later), attachment pages redirect to the file URL; when
 * it is on (sites upgraded from older versions), the attachment page is shown.
 * redirect_canonical runs on template_redirect at priority 10; KhabarSEO runs
 * at priority 1 and sends attachments of published, viewable posts to that
 * post. Everything else keeps the WordPress behavior.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Output;

use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Attachment redirects.
 */
final class Attachments {

	/**
	 * Redirects an attachment page to its parent post (template_redirect).
	 *
	 * @return void
	 */
	public static function maybe_redirect(): void {
		if ( ! is_attachment() || 'redirect' !== Settings::get( 'indexing.attachment' ) ) {
			return;
		}
		$target = self::parent_url( get_queried_object() );
		if ( '' === $target ) {
			return;
		}
		if ( wp_safe_redirect( $target, 301, 'KhabarSEO' ) ) {
			exit;
		}
	}

	/**
	 * Permalink of the published, publicly viewable parent of an attachment.
	 *
	 * @param mixed $attachment Queried object.
	 * @return string Empty when there is no suitable parent.
	 */
	public static function parent_url( $attachment ): string {
		if ( ! $attachment instanceof \WP_Post || 'attachment' !== $attachment->post_type || $attachment->post_parent < 1 ) {
			return '';
		}
		$parent = get_post( (int) $attachment->post_parent );
		if ( ! $parent instanceof \WP_Post || 'publish' !== $parent->post_status || '' !== (string) $parent->post_password || ! is_post_type_viewable( $parent->post_type ) ) {
			return '';
		}
		return (string) get_permalink( $parent );
	}
}
