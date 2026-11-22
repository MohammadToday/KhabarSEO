<?php
/**
 * What the current front-end request shows, plus the template variables for it.
 *
 * Built once per request (after the main query ran). Variable values are
 * resolved lazily, so a variable that no template uses costs nothing.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Output;

use KhabarSEO\Core\Meta;
use KhabarSEO\Core\Settings;
use KhabarSEO\Core\Text;

defined( 'ABSPATH' ) || exit;

/**
 * Request context.
 */
final class Context {

	/**
	 * Context types.
	 */
	public const FRONT             = 'front';
	public const FRONT_PAGE        = 'front_page';
	public const POSTS_PAGE        = 'posts_page';
	public const SINGULAR          = 'singular';
	public const TERM              = 'term';
	public const AUTHOR            = 'author';
	public const POST_TYPE_ARCHIVE = 'post_type_archive';
	public const DATE              = 'date';
	public const SEARCH            = 'search';
	public const NOT_FOUND         = 'not_found';
	public const OTHER             = 'other';

	/**
	 * Memoized instance.
	 *
	 * @var Context|null
	 */
	private static ?Context $current = null;

	/**
	 * Context type.
	 *
	 * @var string
	 */
	public string $type = self::OTHER;

	/**
	 * Queried object.
	 *
	 * @var \WP_Post|\WP_Term|\WP_User|\WP_Post_Type|null
	 */
	public $object = null;

	/**
	 * Archive page number (1 = first page).
	 *
	 * @var int
	 */
	public int $paged = 1;

	/**
	 * Page number inside a multi-page post (1 = first page).
	 *
	 * @var int
	 */
	public int $page = 1;

	/**
	 * Number of archive pages.
	 *
	 * @var int
	 */
	public int $max_pages = 1;

	/**
	 * Date archive level: year, month or day.
	 *
	 * @var string
	 */
	public string $date_level = '';

	/**
	 * Context of the current request.
	 *
	 * @return Context
	 */
	public static function current(): Context {
		if ( null === self::$current ) {
			self::$current = self::detect();
		}
		return self::$current;
	}

	/**
	 * Forgets the memoized context (tests, or after a second main query).
	 *
	 * @return void
	 */
	public static function reset(): void {
		self::$current = null;
	}

	/**
	 * Detects the context from the main query.
	 *
	 * @return Context
	 */
	private static function detect(): Context {
		global $wp_query;

		$c            = new self();
		$c->paged     = max( 1, (int) get_query_var( 'paged' ) );
		$c->max_pages = ( $wp_query instanceof \WP_Query ) ? max( 1, (int) $wp_query->max_num_pages ) : 1;

		if ( is_404() ) {
			$c->type = self::NOT_FOUND;
		} elseif ( is_search() ) {
			$c->type = self::SEARCH;
		} elseif ( is_front_page() && is_home() ) {
			$c->type = self::FRONT;
		} elseif ( is_front_page() ) {
			$c->type   = self::FRONT_PAGE;
			$c->object = get_queried_object();
			$c->page   = max( 1, (int) get_query_var( 'page' ) );
		} elseif ( is_home() ) {
			$c->type   = self::POSTS_PAGE;
			$c->object = get_queried_object();
		} elseif ( is_singular() ) {
			$c->type   = self::SINGULAR;
			$c->object = get_queried_object();
			$c->page   = max( 1, (int) get_query_var( 'page' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$c->type   = self::TERM;
			$c->object = get_queried_object();
		} elseif ( is_author() ) {
			$c->type   = self::AUTHOR;
			$c->object = get_queried_object();
		} elseif ( is_post_type_archive() ) {
			$c->type   = self::POST_TYPE_ARCHIVE;
			$c->object = get_queried_object();
		} elseif ( is_date() ) {
			$c->type       = self::DATE;
			$c->date_level = is_day() ? 'day' : ( is_month() ? 'month' : 'year' );
		}

		$valid = array(
			self::FRONT_PAGE        => '\WP_Post',
			self::POSTS_PAGE        => '\WP_Post',
			self::SINGULAR          => '\WP_Post',
			self::TERM              => '\WP_Term',
			self::AUTHOR            => '\WP_User',
			self::POST_TYPE_ARCHIVE => '\WP_Post_Type',
		);
		if ( isset( $valid[ $c->type ] ) && ! ( $c->object instanceof $valid[ $c->type ] ) ) {
			$c->type   = self::OTHER;
			$c->object = null;
		}
		return $c;
	}

	/**
	 * The post, when the context shows one.
	 *
	 * @return \WP_Post|null
	 */
	public function post(): ?\WP_Post {
		return $this->object instanceof \WP_Post ? $this->object : null;
	}

	/**
	 * Whether this is a list page beyond page 1.
	 *
	 * @return bool
	 */
	public function is_deep_page(): bool {
		return $this->paged > 1 && in_array( $this->type, array( self::FRONT, self::POSTS_PAGE, self::TERM, self::AUTHOR, self::POST_TYPE_ARCHIVE, self::DATE, self::SEARCH ), true );
	}

	/**
	 * Title template for this context, after per-item overrides.
	 *
	 * @return string
	 */
	public function title_template(): string {
		$override = $this->override( Meta::TITLE );
		if ( '' !== $override ) {
			return $override;
		}
		switch ( $this->type ) {
			case self::FRONT:
			case self::FRONT_PAGE:
				return (string) Settings::get( 'titles.home_title' );
			case self::POSTS_PAGE:
				return Settings::map_item( 'titles.singular_title', 'page' );
			case self::SINGULAR:
				return Settings::map_item( 'titles.singular_title', (string) $this->object->post_type );
			case self::TERM:
				return Settings::map_item( 'titles.term_title', (string) $this->object->taxonomy );
			case self::AUTHOR:
				return (string) Settings::get( 'titles.author_title' );
			case self::POST_TYPE_ARCHIVE:
				return (string) Settings::get( 'titles.archive_title' );
			case self::DATE:
				return (string) Settings::get( 'titles.date_title' );
			case self::SEARCH:
				return (string) Settings::get( 'titles.search_title' );
			case self::NOT_FOUND:
				return (string) Settings::get( 'titles.notfound_title' );
		}
		return '';
	}

	/**
	 * Description template for this context, after per-item overrides.
	 *
	 * @return string
	 */
	public function description_template(): string {
		$override = $this->override( Meta::DESC );
		if ( '' !== $override ) {
			return $override;
		}
		switch ( $this->type ) {
			case self::FRONT:
			case self::FRONT_PAGE:
				return (string) Settings::get( 'titles.home_desc' );
			case self::POSTS_PAGE:
				return Settings::map_item( 'titles.singular_desc', 'page' );
			case self::SINGULAR:
				return Settings::map_item( 'titles.singular_desc', (string) $this->object->post_type );
			case self::TERM:
				return Settings::map_item( 'titles.term_desc', (string) $this->object->taxonomy );
			case self::AUTHOR:
				return (string) Settings::get( 'titles.author_desc' );
		}
		return '';
	}

	/**
	 * A string override stored on the current post or term.
	 *
	 * @param string $key Meta key.
	 * @return string
	 */
	public function override( string $key ): string {
		if ( $this->object instanceof \WP_Post && in_array( $this->type, array( self::SINGULAR, self::FRONT_PAGE, self::POSTS_PAGE ), true ) ) {
			return trim( (string) get_post_meta( $this->object->ID, $key, true ) );
		}
		if ( $this->object instanceof \WP_Term && self::TERM === $this->type ) {
			return trim( (string) get_term_meta( $this->object->term_id, $key, true ) );
		}
		return '';
	}

	/**
	 * Lazy resolvers for the template variables.
	 *
	 * %date% is human-facing: it uses the site date format and may be shown in
	 * a local calendar by a date plugin. It must never be reused for
	 * machine-readable output (use KhabarSEO\Core\Time for that).
	 *
	 * @return array<string, callable>
	 */
	public function resolvers(): array {
		return array(
			'sitename' => static function (): string {
				return Text::plain( (string) get_bloginfo( 'name' ) );
			},
			'tagline'  => static function (): string {
				return Text::plain( (string) get_bloginfo( 'description' ) );
			},
			'title'    => array( $this, 'var_title' ),
			'category' => array( $this, 'var_category' ),
			'author'   => array( $this, 'var_author' ),
			'date'     => array( $this, 'var_date' ),
			'page'     => array( $this, 'var_page' ),
			'excerpt'  => array( $this, 'var_excerpt' ),
		);
	}

	/**
	 * %title%: the name of what is shown.
	 *
	 * @return string
	 */
	public function var_title(): string {
		switch ( $this->type ) {
			case self::FRONT:
				return Text::plain( (string) get_bloginfo( 'name' ) );
			case self::FRONT_PAGE:
			case self::POSTS_PAGE:
			case self::SINGULAR:
				return Text::plain( (string) $this->object->post_title );
			case self::TERM:
				return Text::plain( (string) $this->object->name );
			case self::AUTHOR:
				return Text::plain( (string) $this->object->display_name );
			case self::POST_TYPE_ARCHIVE:
				return Text::plain( (string) $this->object->labels->name );
			case self::DATE:
				return $this->date_label();
			case self::SEARCH:
				/* translators: %s: search terms. */
				return sprintf( __( 'Search results for “%s”', 'khabar-seo' ), Text::plain( (string) get_search_query( false ) ) );
			case self::NOT_FOUND:
				return __( 'Page not found', 'khabar-seo' );
		}
		return '';
	}

	/**
	 * Human label of a date archive in the site date format.
	 *
	 * @return string
	 */
	private function date_label(): string {
		global $wp_query;
		$first = ( $wp_query instanceof \WP_Query && ! empty( $wp_query->posts ) ) ? $wp_query->posts[0] : null;
		if ( ! $first instanceof \WP_Post ) {
			return '';
		}
		$formats = array(
			'year'  => 'Y',
			'month' => 'F Y',
			'day'   => (string) get_option( 'date_format' ),
		);
		return Text::plain( (string) get_the_date( $formats[ $this->date_level ] ?? 'Y', $first ) );
	}

	/**
	 * %category%: the first category of a post, or the current category.
	 *
	 * @return string
	 */
	public function var_category(): string {
		if ( self::TERM === $this->type && 'category' === $this->object->taxonomy ) {
			return Text::plain( (string) $this->object->name );
		}
		$post = $this->post();
		if ( null === $post || ! is_object_in_taxonomy( $post->post_type, 'category' ) ) {
			return '';
		}
		$terms = get_the_category( $post->ID );
		$term  = $terms ? $terms[0] : null;
		$term  = apply_filters( 'khs_primary_category', $term, $post );
		return $term instanceof \WP_Term ? Text::plain( (string) $term->name ) : '';
	}

	/**
	 * %author%: the post author or the author of the archive.
	 *
	 * @return string
	 */
	public function var_author(): string {
		if ( self::AUTHOR === $this->type ) {
			return Text::plain( (string) $this->object->display_name );
		}
		$post = $this->post();
		if ( null === $post || ! post_type_supports( $post->post_type, 'author' ) ) {
			return '';
		}
		return Text::plain( (string) get_the_author_meta( 'display_name', (int) $post->post_author ) );
	}

	/**
	 * %date%: human publication date (singular) or the archive date.
	 *
	 * @return string
	 */
	public function var_date(): string {
		if ( self::DATE === $this->type ) {
			return $this->date_label();
		}
		$post = $this->post();
		return null === $post ? '' : Text::plain( (string) get_the_date( '', $post ) );
	}

	/**
	 * %page%: "Page 2 of 5" beyond the first page, otherwise empty.
	 *
	 * @return string
	 */
	public function var_page(): string {
		$current = 1;
		$total   = 1;
		if ( $this->is_deep_page() ) {
			$current = $this->paged;
			$total   = max( $this->paged, $this->max_pages );
		} elseif ( $this->page > 1 && null !== $this->post() ) {
			$current = $this->page;
			$total   = max( $this->page, substr_count( (string) $this->post()->post_content, '<!--nextpage-->' ) + 1 );
		}
		if ( $current < 2 ) {
			return '';
		}
		/* translators: 1: current page number, 2: total number of pages. */
		return sprintf( __( 'Page %1$s of %2$s', 'khabar-seo' ), number_format_i18n( $current ), number_format_i18n( $total ) );
	}

	/**
	 * %excerpt%: written excerpt, else (optionally) the start of the text;
	 * term description; author biography.
	 *
	 * @return string
	 */
	public function var_excerpt(): string {
		$length = (int) Settings::get( 'titles.desc_length' );
		switch ( $this->type ) {
			case self::FRONT_PAGE:
			case self::POSTS_PAGE:
			case self::SINGULAR:
				$auto = 'auto' === Settings::get( 'titles.desc_fallback' );
				return Excerpt::for_post( $this->object, $auto, $length );
			case self::TERM:
				return Text::truncate( Text::plain( (string) $this->object->description ), $length );
			case self::AUTHOR:
				return Text::truncate( Text::plain( (string) get_the_author_meta( 'description', (int) $this->object->ID ) ), $length );
		}
		return '';
	}
}
