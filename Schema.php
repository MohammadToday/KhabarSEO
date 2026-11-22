<?php
/**
 * Central settings schema. The settings page, wizard, sanitizer, defaults,
 * export/import and reset are all derived from this single class.
 *
 * Definitions contain no translated strings so front-end requests never load
 * translations. UI strings live in ui() and are only built in the admin.
 *
 * @package KhabarSEO
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
	public const VERSION = 1;

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
	 * Field keys: type, default, tab, section, wizard (step number, 0 = none),
	 * min, max, required, recommended, depends (key => value), dynamic, internal,
	 * options (choice values, in display order).
	 *
	 * @return array<string, array>
	 */
	public static function definitions(): array {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		$base = array(
			'type'        => 'text',
			'default'     => '',
			'tab'         => '',
			'section'     => '',
			'wizard'      => 0,
			'min'         => 0,
			'max'         => 0,
			'required'    => false,
			'recommended' => null,
			'depends'     => null,
			'dynamic'     => '',
			'internal'    => false,
			'options'     => array(),
		);

		$raw = array(
			'site.publication_name'   => array(
				'type'     => 'text',
				'tab'      => 'setup',
				'section'  => 'publication',
				'wizard'   => 1,
				'max'      => 120,
				'required' => true,
			),
			'site.alternate_name'     => array(
				'type'    => 'text',
				'tab'     => 'setup',
				'section' => 'publication',
				'wizard'  => 1,
				'max'     => 120,
			),
			'site.language'           => array(
				'type'    => 'lang',
				'tab'     => 'setup',
				'section' => 'content',
				'wizard'  => 2,
				'dynamic' => 'language',
			),
			'site.locale'             => array(
				'type'    => 'locale',
				'tab'     => 'setup',
				'section' => 'content',
				'wizard'  => 2,
				'dynamic' => 'locale',
			),
			'site.news_post_types'    => array(
				'type'    => 'post_types',
				'tab'     => 'setup',
				'section' => 'content',
				'wizard'  => 2,
				'default' => array( 'post' ),
			),
			'titles.separator'        => array(
				'type'        => 'choice',
				'tab'         => 'titles',
				'section'     => 'format',
				'wizard'      => 4,
				'default'     => '-',
				'recommended' => '-',
				'options'     => array( '-', '–', '|', '•' ),
			),
			'indexing.tags'           => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'min_posts',
				'recommended' => 'min_posts',
				'options'     => array( 'noindex_all', 'min_posts', 'index_all' ),
			),
			'indexing.tag_min_posts'  => array(
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
			'indexing.categories'     => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'index',
				'recommended' => 'index',
				'options'     => array( 'index', 'noindex' ),
			),
			'indexing.authors'        => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'with_posts',
				'recommended' => 'with_posts',
				'options'     => array( 'with_posts', 'noindex' ),
			),
			'indexing.date'           => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'noindex',
				'recommended' => 'noindex',
				'options'     => array( 'noindex', 'index' ),
			),
			'indexing.attachment'     => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'redirect',
				'recommended' => 'redirect',
				'options'     => array( 'redirect', 'leave' ),
			),
			'indexing.pagination'     => array(
				'type'        => 'choice',
				'tab'         => 'indexing',
				'section'     => 'archives',
				'wizard'      => 3,
				'default'     => 'index_self',
				'recommended' => 'index_self',
				'options'     => array( 'index_self', 'noindex' ),
			),
			'social.og_enabled'       => array(
				'type'        => 'bool',
				'tab'         => 'social',
				'section'     => 'cards',
				'default'     => true,
				'recommended' => true,
			),
			'social.twitter_enabled'  => array(
				'type'        => 'bool',
				'tab'         => 'social',
				'section'     => 'cards',
				'default'     => true,
				'recommended' => true,
			),
			'social.default_image_id' => array(
				'type'    => 'media',
				'tab'     => 'social',
				'section' => 'defaults',
				'wizard'  => 4,
				'default' => 0,
			),
			'social.twitter_card'     => array(
				'type'        => 'choice',
				'tab'         => 'social',
				'section'     => 'cards',
				'wizard'      => 4,
				'default'     => 'summary_large_image',
				'recommended' => 'summary_large_image',
				'options'     => array( 'summary_large_image', 'summary' ),
			),
			'social.twitter_site'     => array(
				'type'    => 'handle',
				'tab'     => 'social',
				'section' => 'cards',
				'wizard'  => 4,
			),
			'advanced.keep_data'      => array(
				'type'        => 'bool',
				'tab'         => 'advanced',
				'section'     => 'data',
				'default'     => true,
				'recommended' => true,
			),
			// Internal state (never rendered, exported or imported).
			'state.schema'            => array(
				'type'     => 'int',
				'default'  => 0,
				'internal' => true,
			),
			'state.plugin'            => array(
				'type'     => 'text',
				'internal' => true,
			),
			'state.wizard_done'       => array(
				'type'     => 'bool',
				'default'  => false,
				'internal' => true,
			),
			'state.wizard_time'       => array(
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
		$labels = self::tab_labels();
		$tabs   = array();
		foreach ( $labels as $id => $label ) {
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
			'publication' => __( 'Publication', 'khabar-seo' ),
			'content'     => __( 'Language and news content', 'khabar-seo' ),
			'format'      => __( 'Title format', 'khabar-seo' ),
			'archives'    => __( 'Archives', 'khabar-seo' ),
			'defaults'    => __( 'Defaults', 'khabar-seo' ),
			'cards'       => __( 'Link previews', 'khabar-seo' ),
			'data'        => __( 'Data', 'khabar-seo' ),
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
		return self::$ui[ $key ] ?? array(
			'label'   => $key,
			'help'    => '',
			'guide'   => '',
			'options' => array(),
		);
	}

	/**
	 * Builds the UI strings.
	 *
	 * @return array<string, array>
	 */
	private static function build_ui(): array {

		return array(
			'site.publication_name'   => array(
				'label' => __( 'Publication name', 'khabar-seo' ),
				'help'  => __( 'Exactly as it appears, or will appear, on Google News / Publisher Center.', 'khabar-seo' ),
				'guide' => __( 'Google compares this name with your Publisher Center listing, so spelling, spacing and script must be identical. Leave out text in parentheses.', 'khabar-seo' ),
			),
			'site.alternate_name'     => array(
				'label' => __( 'Alternate name (optional)', 'khabar-seo' ),
				'help'  => __( 'A second name readers use, such as an abbreviation or the name in another script.', 'khabar-seo' ),
				'guide' => __( 'Used later as the website alternate name in structured data. Leave empty if you have none.', 'khabar-seo' ),
			),
			'site.language'           => array(
				'label' => __( 'Language code', 'khabar-seo' ),
				'help'  => __( 'Two- or three-letter ISO 639 code, for example fa or en. Empty means: detect from the site language.', 'khabar-seo' ),
				'guide' => __( 'Used for structured data and the news sitemap. The detected value is almost always right.', 'khabar-seo' ),
			),
			'site.locale'             => array(
				'label' => __( 'Locale for link previews', 'khabar-seo' ),
				'help'  => __( 'Format language_COUNTRY, for example fa_IR or en_US. Empty means: detect from the site language.', 'khabar-seo' ),
				'guide' => __( 'Sent as og:locale so social networks and Telegram know the language of your pages.', 'khabar-seo' ),
			),
			'site.news_post_types'    => array(
				'label' => __( 'News post types', 'khabar-seo' ),
				'help'  => __( 'Content types that are published as news articles.', 'khabar-seo' ),
				'guide' => __( 'Posts are news by default. Add another type only if it holds dated, bylined reports. Leave static pages out.', 'khabar-seo' ),
			),
			'titles.separator'        => array(
				'label'   => __( 'Title separator', 'khabar-seo' ),
				'help'    => __( 'The symbol between the article title and the site name.', 'khabar-seo' ),
				'guide'   => __( 'This is cosmetic: search engines treat all of these the same. A plain hyphen is the safest in right-to-left text.', 'khabar-seo' ),
				'options' => array(
					'-' => array( 'label' => __( 'Hyphen ( - )', 'khabar-seo' ) ),
					'–' => array( 'label' => __( 'En dash ( – )', 'khabar-seo' ) ),
					'|' => array( 'label' => __( 'Vertical bar ( | )', 'khabar-seo' ) ),
					'•' => array( 'label' => __( 'Bullet ( • )', 'khabar-seo' ) ),
				),
			),
			'indexing.tags'           => array(
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
			'indexing.tag_min_posts'  => array(
				'label' => __( 'Minimum articles for an indexable tag', 'khabar-seo' ),
				'help'  => __( 'Tags with fewer published articles are set to noindex.', 'khabar-seo' ),
				'guide' => __( 'Three is a good balance between useful hubs and thin pages.', 'khabar-seo' ),
			),
			'indexing.categories'     => array(
				'label'   => __( 'Category pages', 'khabar-seo' ),
				'help'    => __( 'Categories are your main topic hubs.', 'khabar-seo' ),
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
			'indexing.authors'        => array(
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
			'indexing.date'           => array(
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
			'indexing.attachment'     => array(
				'label'   => __( 'Attachment pages', 'khabar-seo' ),
				'help'    => __( 'The page WordPress can create for each uploaded image or file.', 'khabar-seo' ),
				'guide'   => __( 'These pages are thin. Redirecting visitors to the article keeps all authority in one place.', 'khabar-seo' ),
				'options' => array(
					'redirect' => array(
						'label' => __( 'Redirect to the parent article', 'khabar-seo' ),
						'guide' => __( 'Visitors and search engines land on the real article instead of a bare image page.', 'khabar-seo' ),
					),
					'leave'    => array(
						'label' => __( 'Leave WordPress behavior unchanged', 'khabar-seo' ),
						'guide' => __( 'The result then depends on your WordPress version and settings.', 'khabar-seo' ),
					),
				),
			),
			'indexing.pagination'     => array(
				'label'   => __( 'Page 2 and later of lists', 'khabar-seo' ),
				'help'    => __( 'Category, tag and author lists that continue on further pages.', 'khabar-seo' ),
				'guide'   => __( 'Each page lists different articles, so letting them be indexed helps older stories get discovered.', 'khabar-seo' ),
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
			'social.og_enabled'       => array(
				'label' => __( 'Output Open Graph tags', 'khabar-seo' ),
				'help'  => __( 'Tags that Facebook, Telegram and LinkedIn use to build link previews.', 'khabar-seo' ),
				'guide' => __( 'Turn off only if your theme already outputs them and you cannot disable the theme version.', 'khabar-seo' ),
			),
			'social.twitter_enabled'  => array(
				'label' => __( 'Output X (Twitter) card tags', 'khabar-seo' ),
				'help'  => __( 'Tags that X uses to build link previews.', 'khabar-seo' ),
				'guide' => __( 'Keep this on unless your theme already outputs them.', 'khabar-seo' ),
			),
			'social.default_image_id' => array(
				'label' => __( 'Default social image', 'khabar-seo' ),
				'help'  => __( 'Used when an article has no suitable image. At least 1200 px wide is recommended.', 'khabar-seo' ),
				'guide' => __( 'Use a photo or neutral artwork, not your logo. Google Discover prefers large, representative images.', 'khabar-seo' ),
			),
			'social.twitter_card'     => array(
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
			'social.twitter_site'     => array(
				'label' => __( 'X (Twitter) account', 'khabar-seo' ),
				'help'  => __( 'Optional, for example @yournews.', 'khabar-seo' ),
				'guide' => __( 'Adds your publication account to link previews.', 'khabar-seo' ),
			),
			'advanced.keep_data'      => array(
				'label' => __( 'Keep my data when the plugin is deleted', 'khabar-seo' ),
				'help'  => __( 'Settings and logs stay in the database after deletion.', 'khabar-seo' ),
				'guide' => __( 'Keeping data protects you from accidental loss. Turn it off to remove everything when the plugin is deleted.', 'khabar-seo' ),
			),
		);
	}
}
