<?php
/**
 * Robots meta through the core wp_robots filter (merge-only).
 *
 * WordPress prints exactly one robots meta tag from the wp_robots filter
 * (wp-includes/robots-template.php). Core already adds noindex for search and
 * embeds, noindex/nofollow when search engines are discouraged, and
 * max-image-preview:large on public sites. KhabarSEO merges its rules into
 * that array and never prints a tag of its own. Conflicts resolve to the more
 * restrictive rule, as Google documents for robots rules.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Output;

use KhabarSEO\Core\Meta;
use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Robots rules.
 */
final class Robots {

	/**
	 * Preview sizes, least to most permissive.
	 */
	private const IMAGE_ORDER = array( 'none', 'standard', 'large' );

	/**
	 * Memoized directives per context object.
	 *
	 * @var array<int, array>
	 */
	private static array $memo = array();

	/**
	 * Forgets memoized directives.
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$memo = array();
	}

	/**
	 * Callback of the wp_robots filter.
	 *
	 * @param array $robots Directives collected so far.
	 * @return array
	 */
	public static function filter( $robots ): array {
		return self::merge( is_array( $robots ) ? $robots : array(), self::directives( Context::current() ) );
	}

	/**
	 * Whether the current page ends up noindex (KhabarSEO rules or core rules).
	 *
	 * @return bool
	 */
	public static function current_is_noindex(): bool {
		if ( ! get_option( 'blog_public' ) || is_search() || is_404() || is_embed() ) {
			return true;
		}
		return self::directives( Context::current() )['noindex'];
	}

	/**
	 * KhabarSEO directives for a context.
	 *
	 * @param Context $c Context.
	 * @return array{noindex:bool,nofollow:bool,nosnippet:bool,max-snippet:string,max-image-preview:string,max-video-preview:string}
	 */
	public static function directives( Context $c ): array {
		$id = spl_object_id( $c );
		if ( isset( self::$memo[ $id ] ) ) {
			return self::$memo[ $id ];
		}
		$out = array(
			'noindex'           => false,
			'nofollow'          => false,
			'nosnippet'         => false,
			'max-snippet'       => (string) (int) Settings::get( 'robots.max_snippet' ),
			'max-image-preview' => (string) Settings::get( 'robots.max_image_preview' ),
			'max-video-preview' => (string) (int) Settings::get( 'robots.max_video_preview' ),
		);

		switch ( $c->type ) {
			case Context::SEARCH:
			case Context::NOT_FOUND:
				$out['noindex'] = true;
				break;

			case Context::DATE:
				$out['noindex'] = 'noindex' === Settings::get( 'indexing.date' );
				break;

			case Context::AUTHOR:
				$out['noindex'] = 'noindex' === Settings::get( 'indexing.authors' ) || ! self::author_has_posts();
				break;

			case Context::TERM:
				$out['noindex'] = self::term_noindex( $c->object );
				break;

			case Context::SINGULAR:
			case Context::FRONT_PAGE:
			case Context::POSTS_PAGE:
				if ( 'attachment' === $c->object->post_type ) {
					$out['noindex'] = true;
				}
				$override         = Meta::post_robots( (int) $c->object->ID );
				$out['noindex']   = $out['noindex'] || $override['noindex'];
				$out['nofollow']  = $override['nofollow'];
				$out['nosnippet'] = $override['nosnippet'];
				if ( null !== $override['max_snippet'] ) {
					$out['max-snippet'] = (string) $override['max_snippet'];
				}
				break;
		}

		if ( $c->is_deep_page() && 'noindex' === Settings::get( 'indexing.pagination' ) ) {
			$out['noindex'] = true;
		}

		self::$memo[ $id ] = (array) apply_filters( 'khs_robots_directives', $out, $c );
		return self::$memo[ $id ];
	}

	/**
	 * Whether a term archive is noindex.
	 *
	 * @param \WP_Term $term Term.
	 * @return bool
	 */
	private static function term_noindex( \WP_Term $term ): bool {
		$override = Meta::term_robots( (int) $term->term_id );
		if ( '' !== $override ) {
			return 'noindex' === $override;
		}
		if ( 'post_tag' === $term->taxonomy ) {
			$rule = Settings::get( 'indexing.tags' );
			if ( 'noindex_all' === $rule ) {
				return true;
			}
			if ( 'min_posts' === $rule ) {
				return (int) $term->count < (int) Settings::get( 'indexing.tag_min_posts' );
			}
			return false;
		}
		return 'noindex' === Settings::get( 'indexing.categories' );
	}

	/**
	 * Whether the author archive lists at least one published item (no extra query).
	 *
	 * @return bool
	 */
	private static function author_has_posts(): bool {
		global $wp_query;
		return $wp_query instanceof \WP_Query && (int) $wp_query->found_posts > 0;
	}

	/**
	 * Merges KhabarSEO directives into the wp_robots array.
	 *
	 * Rules already set by WordPress or other code are kept; for conflicting
	 * values the more restrictive one wins. index and follow are only added
	 * when nothing forbids them (Google treats them as defaults).
	 *
	 * @param array $robots Existing directives (key => true or string value).
	 * @param array $ours   Result of directives().
	 * @return array Ordered directives.
	 */
	public static function merge( array $robots, array $ours ): array {
		if ( ! empty( $robots['none'] ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['none'] );
		}
		foreach ( array( 'noindex', 'nofollow', 'nosnippet' ) as $flag ) {
			if ( ! empty( $ours[ $flag ] ) ) {
				$robots[ $flag ] = true;
			}
		}

		$robots['max-snippet']       = self::min_number( $robots['max-snippet'] ?? null, $ours['max-snippet'] ?? null );
		$robots['max-video-preview'] = self::min_number( $robots['max-video-preview'] ?? null, $ours['max-video-preview'] ?? null );
		$robots['max-image-preview'] = self::min_image( $robots['max-image-preview'] ?? null, $ours['max-image-preview'] ?? null );

		$noindex  = ! empty( $robots['noindex'] );
		$nofollow = ! empty( $robots['nofollow'] );

		$ordered = array();
		if ( $noindex ) {
			$ordered['noindex'] = true;
		} else {
			$ordered['index'] = true;
		}
		if ( $nofollow ) {
			$ordered['nofollow'] = true;
		} else {
			$ordered['follow'] = true;
		}
		if ( ! empty( $robots['nosnippet'] ) ) {
			$ordered['nosnippet'] = true;
		}
		foreach ( array( 'max-image-preview', 'max-snippet', 'max-video-preview' ) as $key ) {
			if ( 'max-snippet' === $key && isset( $ordered['nosnippet'] ) ) {
				continue;
			}
			if ( null !== $robots[ $key ] && '' !== $robots[ $key ] ) {
				$ordered[ $key ] = (string) $robots[ $key ];
			}
		}
		foreach ( $robots as $key => $value ) {
			if ( ! array_key_exists( $key, $ordered ) && ! in_array( $key, array( 'index', 'follow', 'noindex', 'nofollow', 'nosnippet', 'max-image-preview', 'max-snippet', 'max-video-preview' ), true ) ) {
				$ordered[ $key ] = $value;
			}
		}
		return $ordered;
	}

	/**
	 * More restrictive of two numeric limits, where -1 means unlimited.
	 *
	 * @param mixed $a First value.
	 * @param mixed $b Second value.
	 * @return string|null
	 */
	private static function min_number( $a, $b ): ?string {
		$values = array();
		foreach ( array( $a, $b ) as $v ) {
			if ( is_numeric( $v ) ) {
				$values[] = (int) $v;
			}
		}
		if ( ! $values ) {
			return null;
		}
		$limited = array_filter(
			$values,
			static function ( int $v ): bool {
				return $v >= 0;
			}
		);
		return (string) ( $limited ? min( $limited ) : -1 );
	}

	/**
	 * More restrictive of two image preview sizes.
	 *
	 * @param mixed $a First value.
	 * @param mixed $b Second value.
	 * @return string|null
	 */
	private static function min_image( $a, $b ): ?string {
		$best = null;
		foreach ( array( $a, $b ) as $v ) {
			$rank = array_search( $v, self::IMAGE_ORDER, true );
			if ( false === $rank ) {
				continue;
			}
			$best = null === $best ? $rank : min( $best, $rank );
		}
		return null === $best ? null : self::IMAGE_ORDER[ $best ];
	}
}
