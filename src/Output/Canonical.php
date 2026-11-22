<?php
/**
 * Canonical URLs.
 *
 * WordPress core prints rel=canonical only on singular views (rel_canonical()
 * in wp-includes/link-template.php, hooked to wp_head). KhabarSEO removes that
 * action and prints exactly one canonical for every context it handles.
 *
 * Rules:
 * - singular: per-post override, else wp_get_canonical_url(), which already
 *   points page N of a multi-page post (<!--nextpage-->) to itself. Google's
 *   Article guidance: for multi-part articles the canonical points at each
 *   individual page or a view-all page, never at page 1.
 * - lists: the object link, plus /page/N/ (or ?paged=N) beyond page 1. Google's
 *   pagination guidance: give each page its own canonical, never page 1.
 * - search results and 404 pages: none. Noindex pages: none unless enabled.
 * - tracking parameters are removed and the URL is normalized, so Persian
 *   (percent-encoded) slugs are encoded identically everywhere.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Output;

use KhabarSEO\Core\Meta;
use KhabarSEO\Core\Settings;
use KhabarSEO\Core\Url;

defined( 'ABSPATH' ) || exit;

/**
 * Canonical builder.
 */
final class Canonical {

	/**
	 * Canonical URL of the current request, or empty string for none.
	 *
	 * @return string
	 */
	public static function current(): string {
		if ( Robots::current_is_noindex() && ! Settings::is_on( 'canonical.on_noindex' ) ) {
			return '';
		}
		$url = self::for_context( Context::current() );
		$url = (string) apply_filters( 'khs_canonical', $url, Context::current() );
		return '' === $url ? '' : self::clean( $url );
	}

	/**
	 * Removes tracking parameters and normalizes.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function clean( string $url ): string {
		$url = Url::strip_query_params( $url, (array) Settings::get( 'canonical.strip_params' ) );
		$url = strtok( $url, '#' );
		return Url::normalize( false === $url ? '' : $url );
	}

	/**
	 * Raw canonical for a context.
	 *
	 * @param Context $c Context.
	 * @return string
	 */
	public static function for_context( Context $c ): string {
		$override = $c->override( Meta::CANONICAL );
		if ( '' !== $override ) {
			return $override;
		}
		switch ( $c->type ) {
			case Context::SINGULAR:
			case Context::FRONT_PAGE:
				$url = wp_get_canonical_url( $c->object );
				return is_string( $url ) ? $url : '';

			case Context::POSTS_PAGE:
				return self::paginate( (string) get_permalink( $c->object ), $c->paged );

			case Context::FRONT:
				return self::paginate( home_url( '/' ), $c->paged );

			case Context::TERM:
				$link = get_term_link( $c->object );
				return is_string( $link ) ? self::paginate( $link, $c->paged ) : '';

			case Context::AUTHOR:
				return self::paginate( get_author_posts_url( (int) $c->object->ID, (string) $c->object->user_nicename ), $c->paged );

			case Context::POST_TYPE_ARCHIVE:
				$link = get_post_type_archive_link( (string) $c->object->name );
				return is_string( $link ) ? self::paginate( $link, $c->paged ) : '';

			case Context::DATE:
				return self::paginate( self::date_link( $c ), $c->paged );
		}
		return '';
	}

	/**
	 * Link of the current date archive.
	 *
	 * @param Context $c Context.
	 * @return string
	 */
	private static function date_link( Context $c ): string {
		$year  = (int) get_query_var( 'year' );
		$month = (int) get_query_var( 'monthnum' );
		$day   = (int) get_query_var( 'day' );
		if ( 'day' === $c->date_level ) {
			return (string) get_day_link( $year, $month, $day );
		}
		if ( 'month' === $c->date_level ) {
			return (string) get_month_link( $year, $month );
		}
		return (string) get_year_link( $year );
	}

	/**
	 * Adds the page number to a list URL, the way WordPress builds its own
	 * pagination links (pretty permalinks: /page/N/, otherwise ?paged=N).
	 *
	 * @param string $base Link of page 1.
	 * @param int    $page Page number.
	 * @return string
	 */
	public static function paginate( string $base, int $page ): string {
		global $wp_rewrite;
		if ( '' === $base || $page < 2 ) {
			return $base;
		}
		$pretty = $wp_rewrite instanceof \WP_Rewrite && $wp_rewrite->using_permalinks() && false === strpos( $base, '?' );
		if ( ! $pretty ) {
			return add_query_arg( 'paged', $page, $base );
		}
		return trailingslashit( $base ) . user_trailingslashit( $wp_rewrite->pagination_base . '/' . $page, 'paged' );
	}
}
