<?php
/**
 * Per-post and per-term override storage (post meta and term meta).
 *
 * Reads go through get_post_meta()/get_term_meta(), which use the meta cache
 * that WP_Query primes for the main query, so singular views add no queries.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Override meta.
 */
final class Meta {

	public const TITLE     = '_khs_title';
	public const DESC      = '_khs_desc';
	public const CANONICAL = '_khs_canonical';
	public const ROBOTS    = '_khs_robots';

	/**
	 * Maximum stored lengths (characters). Hints in the UI are advisory; these
	 * caps only stop abuse of the database.
	 */
	public const MAX_TITLE = 300;
	public const MAX_DESC  = 2000;

	/**
	 * Registers the meta keys (not exposed in REST until the Phase 5 sidebar).
	 *
	 * @return void
	 */
	public static function register(): void {
		$post_auth = static function ( $allowed, $meta_key, $object_id ) {
			return current_user_can( 'edit_post', (int) $object_id );
		};
		$term_auth = static function ( $allowed, $meta_key, $object_id ) {
			return current_user_can( 'edit_term', (int) $object_id );
		};
		foreach ( array( self::TITLE, self::DESC, self::CANONICAL ) as $key ) {
			register_post_meta(
				'',
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => $post_auth,
				)
			);
			register_term_meta(
				'',
				$key,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => false,
					'auth_callback' => $term_auth,
				)
			);
		}
		register_post_meta(
			'',
			self::ROBOTS,
			array(
				'type'          => 'object',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $post_auth,
			)
		);
		register_term_meta(
			'',
			self::ROBOTS,
			array(
				'type'          => 'string',
				'single'        => true,
				'show_in_rest'  => false,
				'auth_callback' => $term_auth,
			)
		);
	}

	/**
	 * Normalized robots override of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array{noindex:bool,nofollow:bool,nosnippet:bool,max_snippet:int|null}
	 */
	public static function post_robots( int $post_id ): array {
		return self::normalize_post_robots( get_post_meta( $post_id, self::ROBOTS, true ) );
	}

	/**
	 * Normalizes a stored or submitted robots override.
	 *
	 * @param mixed $raw Stored value.
	 * @return array{noindex:bool,nofollow:bool,nosnippet:bool,max_snippet:int|null}
	 */
	public static function normalize_post_robots( $raw ): array {
		$raw = is_array( $raw ) ? $raw : array();
		$max = $raw['max_snippet'] ?? null;
		$max = ( is_scalar( $max ) && 1 === preg_match( '/^-?\d+$/', trim( (string) $max ) ) ) ? max( -1, min( 10000, (int) $max ) ) : null;
		return array(
			'noindex'     => ! empty( $raw['noindex'] ),
			'nofollow'    => ! empty( $raw['nofollow'] ),
			'nosnippet'   => ! empty( $raw['nosnippet'] ),
			'max_snippet' => $max,
		);
	}

	/**
	 * Term robots override: "index", "noindex" or "" (use the archive rule).
	 *
	 * @param int $term_id Term ID.
	 * @return string
	 */
	public static function term_robots( int $term_id ): string {
		$value = get_term_meta( $term_id, self::ROBOTS, true );
		return in_array( $value, array( 'index', 'noindex' ), true ) ? $value : '';
	}

	/**
	 * Stores a string override, deleting the row when empty.
	 *
	 * @param string $type     "post" or "term".
	 * @param int    $id       Object ID.
	 * @param string $key      Meta key.
	 * @param string $value    Sanitized value.
	 * @return void
	 */
	public static function save_string( string $type, int $id, string $key, string $value ): void {
		if ( '' === $value ) {
			delete_metadata( $type, $id, $key );
			return;
		}
		update_metadata( $type, $id, $key, $value );
	}

	/**
	 * Stores a post robots override, deleting the row when it is all defaults.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $robots  Normalized override.
	 * @return void
	 */
	public static function save_post_robots( int $post_id, array $robots ): void {
		$robots = self::normalize_post_robots( $robots );
		if ( ! $robots['noindex'] && ! $robots['nofollow'] && ! $robots['nosnippet'] && null === $robots['max_snippet'] ) {
			delete_post_meta( $post_id, self::ROBOTS );
			return;
		}
		update_post_meta( $post_id, self::ROBOTS, $robots );
	}
}
