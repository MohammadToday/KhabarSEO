<?php
/**
 * Document title, meta description and canonical output.
 *
 * Title: themes with title-tag support, and every block theme (core's
 * _block_template_render_title_tag() in wp-includes/block-template.php), print
 * <title> from wp_get_document_title().
 * A non-empty pre_get_document_title value is returned there as-is, without
 * the escaping core applies on the document_title filter, so it is escaped
 * here. Themes without title-tag support print their own title with
 * wp_title(); KhabarSEO only replaces that when the legacy option is on.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Output;

use KhabarSEO\Core\Settings;
use KhabarSEO\Core\Template;
use KhabarSEO\Core\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Head output.
 */
final class Head {

	/**
	 * Memoized plain-text title.
	 *
	 * @var string|null
	 */
	private static ?string $title = null;

	/**
	 * Whether WordPress prints the <title> from wp_get_document_title() for the
	 * active theme (title-tag support or a block theme).
	 *
	 * @return bool
	 */
	public static function theme_uses_document_title(): bool {
		return current_theme_supports( 'title-tag' ) || ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() );
	}

	/**
	 * Plain-text title of the current request ('' = let WordPress decide).
	 *
	 * @return string
	 */
	public static function title(): string {
		if ( null === self::$title ) {
			$c           = Context::current();
			$title       = Template::render( $c->title_template(), $c->resolvers(), (string) Settings::get( 'titles.separator' ) );
			self::$title = (string) apply_filters( 'khs_title', $title, $c );
		}
		return self::$title;
	}

	/**
	 * Plain-text meta description of the current request.
	 *
	 * @return string
	 */
	public static function description(): string {
		$c           = Context::current();
		$description = Template::render( $c->description_template(), $c->resolvers(), (string) Settings::get( 'titles.separator' ) );
		return Text::normalize_space( (string) apply_filters( 'khs_description', $description, $c ) );
	}

	/**
	 * Callback of pre_get_document_title.
	 *
	 * @param string $title Title from earlier callbacks.
	 * @return string
	 */
	public static function filter_document_title( $title ): string {
		$ours = self::title();
		return '' === $ours ? (string) $title : esc_html( $ours );
	}

	/**
	 * Callback of wp_title for themes without title-tag support (opt-in).
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function filter_wp_title( $title ): string {
		$ours = self::title();
		return '' === $ours ? (string) $title : esc_html( $ours );
	}

	/**
	 * Prints the meta description and the canonical link (wp_head).
	 *
	 * @return void
	 */
	public static function print_tags(): void {
		$description = self::description();
		if ( '' !== $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
		$canonical = Canonical::current();
		if ( '' !== $canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
		}
	}
}
