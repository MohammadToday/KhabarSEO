<?php
/**
 * Helpers that mark passages with data-nosnippet: a block and a shortcode.
 *
 * Google supports data-nosnippet on span, div and section elements; the
 * attribute is boolean and the marked HTML must be valid and closed. Both
 * helpers add the attribute in the server-rendered HTML (never with
 * JavaScript, which Google advises against). They are registered whatever the
 * output state, so content never shows raw shortcodes. No front-end assets.
 *
 * Note for editors: this also hides the passage from normal snippets and AI
 * features, not only from one feature.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Content;

defined( 'ABSPATH' ) || exit;

/**
 * data-nosnippet helpers.
 */
final class NoSnippet {

	public const SHORTCODE = 'khs_nosnippet';

	/**
	 * Registers the shortcode and the block (init).
	 *
	 * @return void
	 */
	public static function register(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );

		if ( function_exists( 'register_block_type' ) ) {
			$type = register_block_type( KHS_DIR . 'assets/blocks/nosnippet' );
			if ( $type instanceof \WP_Block_Type && ! empty( $type->editor_script_handles ) ) {
				wp_add_inline_script(
					$type->editor_script_handles[0],
					'window.khsNosnippet = ' . wp_json_encode( array( 'label' => __( 'Hidden from search snippets (data-nosnippet)', 'khabar-seo' ) ) ) . ';',
					'before'
				);
			}
		}
	}

	/**
	 * [khs_nosnippet]...[/khs_nosnippet], or [khs_nosnippet inline="1"] for a span.
	 *
	 * Put the opening and closing tags on their own lines for block content,
	 * so WordPress does not wrap them in paragraphs.
	 *
	 * @param array|string $atts    Attributes.
	 * @param string|null  $content Enclosed content.
	 * @return string
	 */
	public static function shortcode( $atts, $content = null ): string {
		$atts = shortcode_atts( array( 'inline' => '0' ), is_array( $atts ) ? $atts : array(), self::SHORTCODE );
		$tag  = in_array( strtolower( (string) $atts['inline'] ), array( '1', 'true', 'yes' ), true ) ? 'span' : 'div';
		$html = do_shortcode( (string) $content );
		if ( 'div' === $tag ) {
			$html = trim( (string) preg_replace( '#^\s*</p>|<p>\s*$#', '', $html ) );
		}
		return '<' . $tag . ' data-nosnippet>' . $html . '</' . $tag . '>';
	}
}
