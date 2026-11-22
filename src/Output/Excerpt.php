<?php
/**
 * Excerpts for descriptions. Sections marked as hidden from snippets
 * (data-nosnippet) are never used.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Output;

use KhabarSEO\Core\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Excerpt builder.
 */
final class Excerpt {

	/**
	 * Description text of a post.
	 *
	 * @param \WP_Post $post   Post.
	 * @param bool     $auto   Use the start of the content when there is no written excerpt.
	 * @param int      $length Maximum characters of an automatic excerpt.
	 * @return string Plain text.
	 */
	public static function for_post( \WP_Post $post, bool $auto, int $length ): string {
		if ( '' !== (string) $post->post_password ) {
			return '';
		}
		$written = Text::plain( (string) $post->post_excerpt );
		if ( '' !== $written ) {
			return $written;
		}
		if ( ! $auto ) {
			return '';
		}
		$content = self::strip_hidden( (string) $post->post_content );
		if ( function_exists( 'excerpt_remove_blocks' ) ) {
			$content = excerpt_remove_blocks( $content );
		}
		$content = strip_shortcodes( $content );
		return Text::truncate( Text::plain( $content ), $length );
	}

	/**
	 * Removes content that must not appear in snippets: the KhabarSEO block and
	 * shortcode, and simple elements carrying data-nosnippet.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function strip_hidden( string $content ): string {
		$content = (string) preg_replace( '#<!--\s+wp:khabar-seo/nosnippet\b.*?<!--\s+/wp:khabar-seo/nosnippet\s+-->#s', ' ', $content );
		$content = (string) preg_replace( '#\[khs_nosnippet\b[^\]]*\].*?\[/khs_nosnippet\]#s', ' ', $content );
		$content = (string) preg_replace( '#<(div|span|section)\b[^>]*\sdata-nosnippet\b[^>]*>.*?</\1>#is', ' ', $content );
		return $content;
	}
}
