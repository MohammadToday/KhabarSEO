<?php
/**
 * Central settings schema. The settings page, wizard, sanitizer, defaults,
 * export/import and reset are all derived from this single class.
 *
 * Definitions contain no translated strings so front-end requests never load
 * translations to read a default. UI strings live in ui() and are only built
 * in the admin.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Settings schema.
 */
final class Schema {

	/**
	 * Schema version. Bump together with a new Migrations step.
	 */
	public const VERSION = 2;

	/**
	 * Default title template for singular content (post title first, site name last).
	 */
	public const TPL_SINGULAR = '%title% %sep% %sitename%';

	/**
	 * Default title template for lists (archives, search), with the page number.
	 */
	public const TPL_LIST = '%title% %sep% %page% %sep% %sitename%';

	/**
	 * Memoized definitions.
	 *
	 * @var array|null
	 */
	private static ?array $definitions = null;

	/**
	 * Memoized UI strings.
	 *
	 * @var array|null
	 */
	private static ?array $ui = null;

	/**
	 * Field definitions keyed by "group.name".
	 *
	 * Keys: type, default, tab, section, wizard (step, 0 = none), min, max
	 * (null = unbounded), required, recommended, depends (key => value),
	 * dynamic, internal, options (choice values in display order),
	 * source (post_types|taxonomies for tpl_map) and item_default (tpl_map).
	 *
	 * @return array<string, array>
	 */
	public static function definitions(): array {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$base = array(
			'type'         => 'text',
			'default'      => '',
			'tab'          => '',
			'section'      => '',
			'wizard'       => 0,
			'min'          => null,
			'max'          => null,
			'required'     => false,
			'recommended'  => null,
			'depends'      => null,
			'dynamic'      => '',
			'internal'     => false,
			'options'      => array(),
			'source'       => '',
			'item_default' => '',
		);

		$tpl = static function ( string $tab, string $section, string $fallback, string $type = 'template' ): array {
			return array(
				'type'        => $type,
				'tab'         => $tab,
				'section'     => $section,
				'default'     => $fallback,
				'recommended' => $fallback,
				'max'         => 300,
			);
		};

		$raw = array(
			// Setup.
			'site.publication_name'    => array(
				'type'     => 'text',
				'tab'      => 'setup',
				'section'  => 'publication',
				'wizard'   => 1,
				'max'      => 120,
				'required' => true,
			),
			'site.alternate_name'      => array(
				'type'    => 'text',
				'tab'     => 'setup',
				'section' => 'publication',
				'wizard'  => 1,
				'max'     => 120,
			),
			'site.language'            => array(
				'type'    => 'lang',
				'tab'     => 'setup',
				'section' => 'content',
				'wizard'  => 2,
				'dynamic' => 'language',
			),
			'site.locale'              => array(
				'type'    => 'locale',
				'tab'     => 'setup',
				'section' => 'content',
				'wizard'  => 2,
				'dynamic' => 'locale',
			),
			'site.news_post_types'     => array(
				'type'    => 'post_types',
				'tab'     => 'setup',
				'section' => 'content',
				'wizard'  => 2,
				'default' => array( 'post' ),
			),

			// Titles & Meta.
			'titles.separator'         => array(
				'type'        => 'choice',
				'tab'         => 'titles',
				'section'     => 'format',
				'wizard'      => 4,
				'default'     => '-',
				'recommended' => '-',
				'options'     => array( '-', '–', '|', '·' ),
			),
			'titles.home_title'        => $tpl( 'titles', 'home', '%sitename% %sep% %tagline%' ),
			'titles.home_desc'         => $tpl( 'titles', 'home', '%tagline%' ),
			'titles.singular_title'    => array_merge(
				$tpl( 'titles', 'singular', '', 'tpl_map' ),
				array(
					'default'      => array(),
					'recommended'  => null,
					'source'       => 'post_types',
					'item_default' => self::TPL_SINGULAR,
				)
			),
			'titles.singular_desc'     => array_merge(
				$tpl( 'titles', 'singular', '', 'tpl_map' ),
				array(
					'default'      => array(),
					'recommended'  => null,
					'source'       => 'post_types',
					'item_default' => '%excerpt%',
				)
			),
			'titles.term_title'        => array_merge(
				$tpl( 'titles', 'terms', '', 'tpl_map' ),
				array(
					'default'      => array(),
					'recommended'  => null,
					'source'       => 'taxonomies',
					'item_default' => self::TPL_LIST,
				)
			),
			'titles.term_desc'         => array_merge(
				$tpl( 'titles', 'terms', '', 'tpl_map' ),
				array(
					'default'      => array(),
					'recommended'  => null,
					'source'       => 'taxonomies',
					'item_default' => '%excerpt%',
				)
			),
			'titles.author_title'      => $tpl( 'titles', 'archive_titles', self::TPL_LIST ),
			'titles.author_desc'       => $tpl( 'titles', 'archive_titles', '%excerpt%' ),
			'titles.archive_title'     => $tpl( 'titles', 'archive_titles', self::TPL_LIST ),
			'titles.date_title'        => $tpl( 'titles', 'archive_titles', self::TPL_LIST ),
			'titles.search_title'      => $tpl( 'titles', 'special', self::TPL_LIST ),
			'titles.notfound_title'    => $tpl( 'titles', 'special', self::TPL_SINGULAR ),
			'titles.desc_fallback'     => array(
				'type'        => 'choice',
				'tab'         => 'titles',
				'section'     => 'descriptions',
				'default'     => 'auto',
				'recommended' => 'auto',
				'options'     => array( 'auto', 'manual' ),
			),
			'titles.desc_length'       => array(
				'type'        => 'int',
				'tab'         => 'titles',
				'section'     => 'descriptions',
				'default'     => 160,
				'recommended' => 160,
				'min'         => 50,
				'max'         => 400,
				'depends'     => array( 'titles.desc_fallback' => 'auto' ),
			),

			// Indexing & Archives.
			'indexing.tags'            => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'min_posts',
				'recommended' => 'min_posts',
				'options'     => array( 'noindex_all', 'min_posts', 'index_all' ),
			),
			'indexing.tag_min_posts'   => array(
				'type'        => 'int',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 3,
				'recommended' => 3,
				'min'         => 1,
				'max'         => 50,
				'depends'     => array( 'indexing.tags' => 'min_posts' ),
			),
			'indexing.categories'      => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'index',
				'recommended' => 'index',
				'options'     => array( 'index', 'noindex' ),
			),
			'indexing.authors'         => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'with_posts',
				'recommended' => 'with_posts',
				'options'     => array( 'with_posts', 'noindex' ),
			),
			'indexing.date'            => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'noindex',
				'recommended' => 'noindex',
				'options'     => array( 'noindex', 'index' ),
			),
			'indexing.attachment'      => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'redirect',
				'recommended' => 'redirect',
				'options'     => array( 'redirect', 'leave' ),
			),
			'indexing.pagination'      => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'index_self',
				'recommended' => 'index_self',
				'options'     => array( 'index_self', 'noindex' ),
			),
			'robots.max_snippet'       => array(
				'type'        => 'int',
				'tab'         => 'indexing',
				'section'     => 'snippets',
				'default'     => -1,
				'recommended' => -1,
				'min'         => -1,
				'max'         => 10000,
			),
			'robots.max_image_preview' => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'snippets',
				'default'     => 'large',
				'recommended' => 'large',
				'options'     => array( 'large', 'standard', 'none' ),
			),
			'robots.max_video_preview' => array(
				'type'        => 'int',
				'tab'         => 'indexing',
				'section'     => 'snippets',
				'default'     => -1,
				'recommended' => -1,
				'min'         => -1,
				'max'         => 36000,
			),
			'canonical.strip_params'   => array(
				'type'        => 'list',
				'tab'         => 'indexing',
				'section'     => 'canonical',
				'default'     => array( 'utm_*', 'fbclid', 'gclid', 'yclid', 'msclkid', 'mc_cid', 'mc_eid' ),
				'recommended' => null,
				'max'         => 40,
			),
			'canonical.on_noindex'     => array(
				'type'        => 'bool',
				'tab'         => 'indexing',
				'section'     => 'canonical',
				'default'     => false,
				'recommended' => false,
			),

			// Social (output arrives in Part 1C).
			'social.og_enabled'        => array(
				'type'        => 'bool',
				'tab'         => 'social',
				'section'     => 'cards',
				'default'     => true,
				'recommended' => true,
			),
			'social.twitter_enabled'   => array(
				'type'        => 'bool',
				'tab'         => 'social',
				'section'     => 'cards',
				'default'     => true,
				'recommended' => true,
			),
			'social.default_image_id'  => array(
				'type'    => 'media',
				'tab'     => 'social',
				'section' => 'defaults',
				'wizard'  => 4,
				'default' => 0,
			),
			'social.twitter_card'      => array(
				'type'        => 'choice',
				'tab'         => 'social',
				'section'     => 'cards',
				'wizard'      => 4,
				'default'     => 'summary_large_image',
				'recommended' => 'summary_large_image',
				'options'     => array( 'summary_large_image', 'summary' ),
			),
			'social.twitter_site'      => array(
				'type'    => 'handle',
				'tab'     => 'social',
				'section' => 'cards',
				'wizard'  => 4,
			),

			// Advanced.
			'advanced.legacy_title'    => array(
				'type'        => 'bool',
				'tab'         => 'advanced',
				'section'     => 'compat',
				'default'     => false,
				'recommended' => false,
			),
			'advanced.keep_data'       => array(
				'type'        => 'bool',
				'tab'         => 'advanced',
				'section'     => 'data',
				'default'     => true,
				'recommended' => true,
			),

			// Internal state (never rendered, exported or imported).
			'state.schema'             => array(
				'type'     => 'int',
				'default'  => 0,
				'internal' => true,
			),
			'state.plugin'             => array(
				'type'     => 'text',
				'internal' => true,
			),
			'state.wizard_done'        => array(
				'type'     => 'bool',
				'default'  => false,
				'internal' => true,
			),
			'state.wizard_time'        => array(
				'type'     => 'int',
				'default'  => 0,
				'internal' => true,
			),
		);

		self::$definitions = array();
		foreach ( $raw as $key => $spec ) {
			self::$definitions[ $key ] = array_merge( $base, $spec, array( 'key' => $key ) );
		}
		return self::$definitions;
	}

	/**
	 * One field definition.
	 *
	 * @param string $key Field key.
	 * @return array|null
	 */
	public static function field( string $key ): ?array {
		return self::definitions()[ $key ] ?? null;
	}

	/**
	 * Field keys.
	 *
	 * @param bool $include_internal Include internal state keys.
	 * @return string[]
	 */
	public static function keys( bool $include_internal = false ): array {
		$keys = array();
		foreach ( self::definitions() as $key => $spec ) {
			if ( $include_internal || ! $spec['internal'] ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Flat defaults for every field.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$out = array();
		foreach ( self::definitions() as $key => $spec ) {
			$out[ $key ] = $spec['default'];
		}
		return $out;
	}

	/**
	 * Defaults in the nested storage shape (group => name => value).
	 *
	 * @return array<string, array>
	 */
	public static function defaults_nested(): array {
		$out = array();
		foreach ( self::defaults() as $key => $value ) {
			list( $group, $name )   = explode( '.', $key, 2 );
			$out[ $group ][ $name ] = $value;
		}
		return $out;
	}

	/**
	 * Keys of the editable fields shown in a tab.
	 *
	 * @param string $tab Tab id.
	 * @return string[]
	 */
	public static function tab_fields( string $tab ): array {
		$keys = array();
		foreach ( self::definitions() as $key => $spec ) {
			if ( ! $spec['internal'] && $spec['tab'] === $tab ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Keys of the fields asked in a wizard step.
	 *
	 * @param int $step Step number.
	 * @return string[]
	 */
	public static function step_fields( int $step ): array {
		$keys = array();
		foreach ( self::definitions() as $key => $spec ) {
			if ( ! $spec['internal'] && $spec['wizard'] === $step ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * Tabs that are shown: those with fields, plus the custom Tools and Status tabs.
	 *
	 * @return array<string, string> Tab id => label.
	 */
	public static function visible_tabs(): array {
		$tabs = array();
		foreach ( self::tab_labels() as $id => $label ) {
			if ( 'tools' === $id || 'status' === $id || self::tab_fields( $id ) ) {
				$tabs[ $id ] = $label;
			}
		}
		return $tabs;
	}

	/**
	 * Tab labels (admin only).
	 *
	 * @return array<string, string>
	 */
	public static function tab_labels(): array {
		return array(
			'setup'    => __( 'Setup', 'khabar-seo' ),
			'titles'   => __( 'Titles & Meta', 'khabar-seo' ),
			'indexing' => __( 'Indexing & Archives', 'khabar-seo' ),
			'social'   => __( 'Social', 'khabar-seo' ),
			'advanced' => __( 'Advanced', 'khabar-seo' ),
			'tools'    => __( 'Tools', 'khabar-seo' ),
			'status'   => __( 'Status', 'khabar-seo' ),
		);
	}

	/**
	 * Section titles (admin only).
	 *
	 * @return array<string, string>
	 */
	public static function section_labels(): array {
		return array(
			'publication'    => __( 'Publication', 'khabar-seo' ),
			'content'        => __( 'Language and news content', 'khabar-seo' ),
			'format'         => __( 'Title format', 'khabar-seo' ),
			'home'           => __( 'Home page', 'khabar-seo' ),
			'singular'       => __( 'Articles, pages and other content', 'khabar-seo' ),
			'terms'          => __( 'Categories, tags and other taxonomies', 'khabar-seo' ),
			'archives'       => __( 'Archives', 'khabar-seo' ),
			'archive_titles' => __( 'Author, date and content type archives', 'khabar-seo' ),
			'special'        => __( 'Search results and missing pages', 'khabar-seo' ),
			'descriptions'   => __( 'Automatic descriptions', 'khabar-seo' ),
			'snippets'       => __( 'Search snippets', 'khabar-seo' ),
			'canonical'      => __( 'Canonical URLs', 'khabar-seo' ),
			'defaults'       => __( 'Defaults', 'khabar-seo' ),
			'cards'          => __( 'Link previews', 'khabar-seo' ),
			'compat'         => __( 'Theme compatibility', 'khabar-seo' ),
			'data'           => __( 'Data', 'khabar-seo' ),
		);
	}

	/**
	 * Intro paragraph per section (admin only, optional).
	 *
	 * @return array<string, string>
	 */
	public static function section_intros(): array {
		return array(
			'cards'    => __( 'Open Graph and X (Twitter) tags are output from version 0.3 on. You can prepare the settings now.', 'khabar-seo' ),
			'snippets' => __( 'These rules apply to every indexable page. They also limit what AI features in search can show from your pages.', 'khabar-seo' ),
		);
	}

	/**
	 * Wizard step titles and intros (admin only). Step 5 is the review.
	 *
	 * @return array<int, array{title:string,intro:string}>
	 */
	public static function wizard_steps(): array {
		return array(
			1 => array(
				'title' => __( 'Your publication', 'khabar-seo' ),
				'intro' => __( 'Tell search engines who you are. The name must match how your publication appears, or will appear, in Google News / Publisher Center.', 'khabar-seo' ),
			),
			2 => array(
				'title' => __( 'Language and news content', 'khabar-seo' ),
				'intro' => __( 'We detected these from your site language. Change them if they are wrong.', 'khabar-seo' ),
			),
			3 => array(
				'title' => __( 'Indexing', 'khabar-seo' ),
				'intro' => __( 'Choose which archive pages search engines may index. The recommended choices suit most news sites.', 'khabar-seo' ),
			),
			4 => array(
				'title' => __( 'Titles and social previews', 'khabar-seo' ),
				'intro' => __( 'Set the title separator and the defaults used when a link is shared on social networks or Telegram.', 'khabar-seo' ),
			),
			5 => array(
				'title' => __( 'Review and finish', 'khabar-seo' ),
				'intro' => __( 'Check your answers. Finishing applies the "News site, recommended" preset and switches KhabarSEO on. You can edit everything later.', 'khabar-seo' ),
			),
		);
	}

	/**
	 * UI strings for one field (admin only).
	 *
	 * @param string $key Field key.
	 * @return array{label:string,help:string,guide:string,options:array}
	 */
	public static function ui( string $key ): array {
		if ( null === self::$ui ) {
			self::$ui = self::build_ui();
		}
		$ui = self::$ui[ $key ] ?? array( 'label' => $key );
		return array_merge(
			array(
				'label'   => $key,
				'help'    => '',
				'guide'   => '',
				'options' => array(),
			),
			$ui
		);
	}

	/**
	 * Builds the UI strings.
	 *
	 * @return array<string, array>
	 */
	private static function build_ui(): array {
		$list_guide = sprintf( /* translators: %s: template variable name, keep it as is. */ __( 'Lists continue on page 2, 3 and so on. %s adds "Page 2 of 5" there, so each page has its own title; it is empty on page 1.', 'khabar-seo' ), '%page%' );

		return array(
			'site.publication_name'    => array(
				'label' => __( 'Publication name', 'khabar-seo' ),
				'help'  => __( 'Exactly as it appears, or will appear, in Google News Publisher Center.', 'khabar-seo' ),
				'guide' => __( 'Google compares this name with your Publisher Center listing, so spelling, spacing and script must be identical. Leave out text in parentheses.', 'khabar-seo' ),
			),
			'site.alternate_name'      => array(
				'label' => __( 'Alternate name (optional)', 'khabar-seo' ),
				'help'  => __( 'A second name readers use, such as an abbreviation or the name in another script.', 'khabar-seo' ),
				'guide' => __( 'Used later as the website alternate name in structured data. Leave empty if you have none.', 'khabar-seo' ),
			),
			'site.language'            => array(
				'label' => __( 'Language code', 'khabar-seo' ),
				'help'  => __( 'Two- or three-letter ISO 639 code, for example fa or en. Empty means: detect from the site language.', 'khabar-seo' ),
				'guide' => __( 'Used for structured data and the news sitemap. The detected value is almost always right.', 'khabar-seo' ),
			),
			'site.locale'              => array(
				'label' => __( 'Locale for link previews', 'khabar-seo' ),
				'help'  => __( 'Format language_COUNTRY, for example fa_IR or en_US. Empty means: detect from the site language.', 'khabar-seo' ),
				'guide' => __( 'Sent as og:locale so social networks and Telegram know the language of your pages.', 'khabar-seo' ),
			),
			'site.news_post_types'     => array(
				'label' => __( 'News post types', 'khabar-seo' ),
				'help'  => __( 'Content types that are published as news articles.', 'khabar-seo' ),
				'guide' => __( 'Posts are news by default. Add another type only if it holds dated, bylined reports. Leave static pages out.', 'khabar-seo' ),
			),
			'titles.separator'         => array(
				'label'   => __( 'Title separator', 'khabar-seo' ),
				'help'    => sprintf( /* translators: %s: template variable name, keep it as is. */ __( 'The symbol that %s stands for, between the article title and the site name.', 'khabar-seo' ), '%sep%' ),
				'guide'   => __( 'This is cosmetic: Google mentions hyphens, colons and pipes as typical delimiters. A plain hyphen is the safest in right-to-left text.', 'khabar-seo' ),
				'options' => array(
					'-' => array( 'label' => __( 'Hyphen ( - )', 'khabar-seo' ) ),
					'–' => array( 'label' => __( 'En dash ( – )', 'khabar-seo' ) ),
					'|' => array( 'label' => __( 'Vertical bar ( | )', 'khabar-seo' ) ),
					'·' => array( 'label' => __( 'Middle dot ( · )', 'khabar-seo' ) ),
				),
			),
			'titles.home_title'        => array(
				'label' => __( 'Home page title', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( 'On the home page, %s is the site name.', 'khabar-seo' ), '%title%' ),
				'guide' => __( 'Your name plus your tagline tells searchers who you are. If the tagline is empty, only the name is used.', 'khabar-seo' ),
			),
			'titles.home_desc'         => array(
				'label' => __( 'Home page description', 'khabar-seo' ),
				'help'  => __( 'Shown under your home page in search results, if the search engine uses it.', 'khabar-seo' ),
				'guide' => __( 'Describe your coverage in one or two plain sentences, for example the topics and region you report on.', 'khabar-seo' ),
			),
			'titles.singular_title'    => array(
				'label' => __( 'Title template per content type', 'khabar-seo' ),
				'help'  => __( 'Used for each article, page or other content item. A title written in the editor box replaces it for that item.', 'khabar-seo' ),
				'guide' => __( 'Put the article title first and the site name last: readers see the story first, and the name still identifies you. Google rewrites titles that look like boilerplate.', 'khabar-seo' ),
			),
			'titles.singular_desc'     => array(
				'label' => __( 'Description template per content type', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( '%s is the excerpt, or the start of the text when automatic descriptions are on.', 'khabar-seo' ), '%excerpt%' ),
				'guide' => __( 'A short summary that matches the article works best. Identical descriptions on every page do not help.', 'khabar-seo' ),
			),
			'titles.term_title'        => array(
				'label' => __( 'Title template per taxonomy', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( 'For category, tag and other taxonomy pages. %s is the term name.', 'khabar-seo' ), '%title%' ),
				'guide' => $list_guide,
			),
			'titles.term_desc'         => array(
				'label' => __( 'Description template per taxonomy', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( '%s is the term description you write in WordPress.', 'khabar-seo' ), '%excerpt%' ),
				'guide' => __( 'Write a description for your main categories; it helps readers understand what the section covers.', 'khabar-seo' ),
			),
			'titles.author_title'      => array(
				'label' => __( 'Author page title', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( '%s is the author display name.', 'khabar-seo' ), '%title%' ),
				'guide' => $list_guide,
			),
			'titles.author_desc'       => array(
				'label' => __( 'Author page description', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( '%s is the biographical info from the user profile.', 'khabar-seo' ), '%excerpt%' ),
				'guide' => __( 'A real biography supports reader trust in your reporting.', 'khabar-seo' ),
			),
			'titles.archive_title'     => array(
				'label' => __( 'Content type archive title', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( 'Lists of one content type (if your site has them). %s is the content type name.', 'khabar-seo' ), '%title%' ),
				'guide' => $list_guide,
			),
			'titles.date_title'        => array(
				'label' => __( 'Date archive title', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( '%s is the year, month or day in your site date format.', 'khabar-seo' ), '%title%' ),
				'guide' => $list_guide,
			),
			'titles.search_title'      => array(
				'label' => __( 'Search results title', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( '%s is "Search results for" plus the search terms.', 'khabar-seo' ), '%title%' ),
				'guide' => __( 'Search result pages are never indexed, so this title is only seen by visitors.', 'khabar-seo' ),
			),
			'titles.notfound_title'    => array(
				'label' => __( 'Title of missing pages (404)', 'khabar-seo' ),
				'help'  => sprintf( /* translators: %s: template variable name, keep it as is. */ __( '%s is "Page not found".', 'khabar-seo' ), '%title%' ),
				'guide' => __( 'Missing pages answer with status 404 and are never indexed; the title only helps visitors.', 'khabar-seo' ),
			),
			'titles.desc_fallback'     => array(
				'label'   => __( 'When an article has no excerpt', 'khabar-seo' ),
				'help'    => sprintf( /* translators: %s: template variable name, keep it as is. */ __( 'Decides what %s contains for articles without a written excerpt.', 'khabar-seo' ), '%excerpt%' ),
				'guide'   => __( 'Google encourages generated descriptions for large sites where writing each one by hand is impossible. Sections hidden from snippets are never used.', 'khabar-seo' ),
				'options' => array(
					'auto'   => array(
						'label' => __( 'Use the start of the article text', 'khabar-seo' ),
						'guide' => __( 'Every article gets a description that matches its content, even when editors are in a hurry.', 'khabar-seo' ),
					),
					'manual' => array(
						'label' => __( 'Only use excerpts written by editors', 'khabar-seo' ),
						'guide' => __( 'Articles without an excerpt get no description; search engines then pick text from the page themselves.', 'khabar-seo' ),
					),
				),
			),
			'titles.desc_length'       => array(
				'label' => __( 'Length of automatic descriptions (characters)', 'khabar-seo' ),
				'help'  => __( 'Only used when KhabarSEO builds a description from the article text. Text is cut at a word boundary.', 'khabar-seo' ),
				'guide' => __( 'There is no length limit for descriptions; search engines shorten them to fit the screen. This only keeps automatic descriptions to a sensible size.', 'khabar-seo' ),
			),
			'indexing.tags'            => array(
				'label'   => __( 'Tag pages', 'khabar-seo' ),
				'help'    => __( 'Tag archives often become thin copies of category pages.', 'khabar-seo' ),
				'guide'   => __( 'Tags with only one or two articles add little value, so the middle option is best for most news sites.', 'khabar-seo' ),
				'options' => array(
					'noindex_all' => array(
						'label' => __( 'Do not index tag pages', 'khabar-seo' ),
						'guide' => __( 'Keeps all tag pages out of search. Choose this if you use tags loosely.', 'khabar-seo' ),
					),
					'min_posts'   => array(
						'label' => __( 'Index only tags with enough articles', 'khabar-seo' ),
						'guide' => __( 'Tags with several articles work as useful topic hubs; near-empty ones are kept out.', 'khabar-seo' ),
					),
					'index_all'   => array(
						'label' => __( 'Index all tag pages', 'khabar-seo' ),
						'guide' => __( 'Risky for news sites: many tags hold only one or two articles and can lower overall quality.', 'khabar-seo' ),
					),
				),
			),
			'indexing.tag_min_posts'   => array(
				'label' => __( 'Minimum articles for an indexable tag', 'khabar-seo' ),
				'help'  => __( 'Tags with fewer published articles are set to noindex.', 'khabar-seo' ),
				'guide' => __( 'Three is a good balance between useful hubs and thin pages.', 'khabar-seo' ),
			),
			'indexing.categories'      => array(
				'label'   => __( 'Category pages', 'khabar-seo' ),
				'help'    => __( 'Categories are your main topic hubs. This also applies to other public taxonomies except tags.', 'khabar-seo' ),
				'guide'   => __( 'Indexing them lets readers find your coverage of a topic from search.', 'khabar-seo' ),
				'options' => array(
					'index'   => array(
						'label' => __( 'Index category pages', 'khabar-seo' ),
						'guide' => __( 'Categories usually deserve to appear in search as topic landing pages.', 'khabar-seo' ),
					),
					'noindex' => array(
						'label' => __( 'Do not index category pages', 'khabar-seo' ),
						'guide' => __( 'Choose this only if categories are internal and not meant as public landing pages.', 'khabar-seo' ),
					),
				),
			),
			'indexing.authors'         => array(
				'label'   => __( 'Author pages', 'khabar-seo' ),
				'help'    => __( 'Author pages show who is behind your reporting.', 'khabar-seo' ),
				'guide'   => __( 'Showing real authors supports reader trust. Authors without articles stay out of search.', 'khabar-seo' ),
				'options' => array(
					'with_posts' => array(
						'label' => __( 'Index authors who have published articles', 'khabar-seo' ),
						'guide' => __( 'Makes your bylines discoverable while keeping empty profiles out.', 'khabar-seo' ),
					),
					'noindex'    => array(
						'label' => __( 'Do not index author pages', 'khabar-seo' ),
						'guide' => __( 'Choose this if you prefer not to expose author profiles to search.', 'khabar-seo' ),
					),
				),
			),
			'indexing.date'            => array(
				'label'   => __( 'Date archives', 'khabar-seo' ),
				'help'    => __( 'Pages that list articles by year, month or day.', 'khabar-seo' ),
				'guide'   => __( 'They repeat content found elsewhere on your site, so keeping them out of search is better.', 'khabar-seo' ),
				'options' => array(
					'noindex' => array(
						'label' => __( 'Do not index date archives', 'khabar-seo' ),
						'guide' => __( 'Avoids duplicate listings of the same articles.', 'khabar-seo' ),
					),
					'index'   => array(
						'label' => __( 'Index date archives', 'khabar-seo' ),
						'guide' => __( 'Rarely useful; only if readers browse your news by date.', 'khabar-seo' ),
					),
				),
			),
			'indexing.attachment'      => array(
				'label'   => __( 'Attachment pages', 'khabar-seo' ),
				'help'    => __( 'The page WordPress can create for each uploaded image or file. When one is shown, it is always set to noindex.', 'khabar-seo' ),
				'guide'   => __( 'These pages are thin. Redirecting visitors to the article keeps all authority in one place.', 'khabar-seo' ),
				'options' => array(
					'redirect' => array(
						'label' => __( 'Redirect to the parent article', 'khabar-seo' ),
						'guide' => __( 'Visitors and search engines land on the published article. Files without a published article keep the WordPress behavior.', 'khabar-seo' ),
					),
					'leave'    => array(
						'label' => __( 'Leave WordPress behavior unchanged', 'khabar-seo' ),
						'guide' => __( 'WordPress either shows the attachment page or sends visitors to the file, depending on its "attachment pages" setting.', 'khabar-seo' ),
					),
				),
			),
			'indexing.pagination'      => array(
				'label'   => __( 'Page 2 and later of lists', 'khabar-seo' ),
				'help'    => __( 'Category, tag, author and home page lists that continue on further pages.', 'khabar-seo' ),
				'guide'   => __( 'Google advises giving each page its own canonical URL, never page 1. Indexable deep pages help older stories get discovered.', 'khabar-seo' ),
				'options' => array(
					'index_self' => array(
						'label' => __( 'Index page 2 and later', 'khabar-seo' ),
						'guide' => __( 'Each page points to itself as canonical and can be crawled normally.', 'khabar-seo' ),
					),
					'noindex'    => array(
						'label' => __( 'Set page 2 and later to noindex', 'khabar-seo' ),
						'guide' => __( 'Keeps deep pages out of search but can make older articles harder to discover.', 'khabar-seo' ),
					),
				),
			),
			'robots.max_snippet'       => array(
				'label' => __( 'Maximum snippet length (max-snippet)', 'khabar-seo' ),
				'help'  => __( '-1 lets the search engine choose; 0 shows no text snippet; another number is a character limit.', 'khabar-seo' ),
				'guide' => __( 'Limits also apply to normal search snippets and AI features. Keep -1 unless you have a legal or business reason.', 'khabar-seo' ),
			),
			'robots.max_image_preview' => array(
				'label'   => __( 'Image preview size (max-image-preview)', 'khabar-seo' ),
				'help'    => __( 'The largest image preview search engines may show for your pages.', 'khabar-seo' ),
				'guide'   => __( 'Google Discover recommends large previews; smaller previews make news cards less visible.', 'khabar-seo' ),
				'options' => array(
					'large'    => array(
						'label' => __( 'Large', 'khabar-seo' ),
						'guide' => __( 'Allows large images, as used by Google Discover.', 'khabar-seo' ),
					),
					'standard' => array(
						'label' => __( 'Standard', 'khabar-seo' ),
						'guide' => __( 'Only a default-size preview.', 'khabar-seo' ),
					),
					'none'     => array(
						'label' => __( 'None', 'khabar-seo' ),
						'guide' => __( 'No image preview at all.', 'khabar-seo' ),
					),
				),
			),
			'robots.max_video_preview' => array(
				'label' => __( 'Maximum video preview (max-video-preview, seconds)', 'khabar-seo' ),
				'help'  => __( '-1 means no limit; 0 allows at most a still image.', 'khabar-seo' ),
				'guide' => __( 'Keep -1 so video reports can show a preview.', 'khabar-seo' ),
			),
			'canonical.strip_params'   => array(
				'label' => __( 'Tracking parameters removed from canonical URLs', 'khabar-seo' ),
				'help'  => __( 'One per line. A trailing * matches a prefix, for example utm_*.', 'khabar-seo' ),
				'guide' => __( 'Campaign tags must never become part of a canonical URL. Canonicals are built from the real article address, so this mainly cleans URLs entered by hand.', 'khabar-seo' ),
			),
			'canonical.on_noindex'     => array(
				'label' => __( 'Also output a canonical on noindex pages', 'khabar-seo' ),
				'help'  => __( 'By default, pages set to noindex get no canonical link.', 'khabar-seo' ),
				'guide' => __( 'A canonical says "index this URL", which contradicts noindex. Leave this off unless a search engine tool asks for it.', 'khabar-seo' ),
			),
			'social.og_enabled'        => array(
				'label' => __( 'Output Open Graph tags', 'khabar-seo' ),
				'help'  => __( 'Tags that Facebook, Telegram and LinkedIn use to build link previews.', 'khabar-seo' ),
				'guide' => __( 'Turn off only if your theme already outputs them and you cannot disable the theme version.', 'khabar-seo' ),
			),
			'social.twitter_enabled'   => array(
				'label' => __( 'Output X (Twitter) card tags', 'khabar-seo' ),
				'help'  => __( 'Tags that X uses to build link previews.', 'khabar-seo' ),
				'guide' => __( 'Keep this on unless your theme already outputs them.', 'khabar-seo' ),
			),
			'social.default_image_id'  => array(
				'label' => __( 'Default social image', 'khabar-seo' ),
				'help'  => __( 'Used when an article has no suitable image. At least 1200 px wide is recommended.', 'khabar-seo' ),
				'guide' => __( 'Use a photo or neutral artwork, not your logo. Google Discover prefers large, representative images.', 'khabar-seo' ),
			),
			'social.twitter_card'      => array(
				'label'   => __( 'X (Twitter) card type', 'khabar-seo' ),
				'help'    => __( 'How links to your articles look on X.', 'khabar-seo' ),
				'guide'   => __( 'The large image card gives news headlines more visual weight.', 'khabar-seo' ),
				'options' => array(
					'summary_large_image' => array(
						'label' => __( 'Large image card', 'khabar-seo' ),
						'guide' => __( 'Shows a wide image above the headline. Best for news.', 'khabar-seo' ),
					),
					'summary'             => array(
						'label' => __( 'Small card', 'khabar-seo' ),
						'guide' => __( 'Shows a small thumbnail beside the text. Useful if your images are small.', 'khabar-seo' ),
					),
				),
			),
			'social.twitter_site'      => array(
				'label' => __( 'X (Twitter) account', 'khabar-seo' ),
				'help'  => __( 'Optional, for example @yournews.', 'khabar-seo' ),
				'guide' => __( 'Adds your publication account to link previews.', 'khabar-seo' ),
			),
			'advanced.legacy_title'    => array(
				'label' => __( 'Control titles in themes without title-tag support', 'khabar-seo' ),
				'help'  => __( 'Only matters for older themes that print the title themselves with wp_title(). The Status page tells you if yours does.', 'khabar-seo' ),
				'guide' => __( 'Such themes often add the site name again after wp_title(), which would show it twice. Turn this on only after checking the result with Status, Scan now.', 'khabar-seo' ),
			),
			'advanced.keep_data'       => array(
				'label' => __( 'Keep my data when the plugin is deleted', 'khabar-seo' ),
				'help'  => __( 'Settings and logs stay in the database after deletion.', 'khabar-seo' ),
				'guide' => __( 'Keeping data protects you from accidental loss. Turn it off to remove everything when the plugin is deleted.', 'khabar-seo' ),
			),
		);
	}
}
