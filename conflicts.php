<?php
/**
 * Known conflicts. Detection is by plugin directory slug (robust to renamed
 * main files) and by a constant defined by the plugin.
 *
 * `verified_on` is the date an entry was checked against the vendor source.
 * null means "from memory, not verified": a wrong entry only fails to match,
 * and the rendered-head scan on the Status page is the real safety net.
 *
 * Add or correct entries by pull request; keep strings short and factual.
 *
 * @package KhabarSEO
 */

defined( 'ABSPATH' ) || exit;

return array(
	// Active plugins that output SEO meta/structured data: KhabarSEO output stays OFF while one is active.
	'seo_plugins'     => array(
		'wordpress-seo'         => array(
			'name'        => 'Yoast SEO',
			'constant'    => 'WPSEO_VERSION',
			'verified_on' => null,
		),
		'wordpress-seo-premium' => array(
			'name'        => 'Yoast SEO Premium',
			'constant'    => 'WPSEO_PREMIUM_VERSION',
			'verified_on' => null,
		),
		'seo-by-rank-math'      => array(
			'name'        => 'Rank Math SEO',
			'constant'    => 'RANK_MATH_VERSION',
			'verified_on' => null,
		),
		'seo-by-rank-math-pro'  => array(
			'name'        => 'Rank Math SEO PRO',
			'constant'    => '',
			'verified_on' => null,
		),
		'all-in-one-seo-pack'   => array(
			'name'        => 'All in One SEO',
			'constant'    => 'AIOSEO_VERSION',
			'verified_on' => null,
		),
		'wp-seopress'           => array(
			'name'        => 'SEOPress',
			'constant'    => 'SEOPRESS_VERSION',
			'verified_on' => null,
		),
		'autodescription'       => array(
			'name'        => 'The SEO Framework',
			'constant'    => 'THE_SEO_FRAMEWORK_VERSION',
			'verified_on' => null,
		),
		'slim-seo'              => array(
			'name'        => 'Slim SEO',
			'constant'    => 'SLIM_SEO_VER',
			'verified_on' => null,
		),
		'smartcrawl-seo'        => array(
			'name'        => 'SmartCrawl',
			'constant'    => 'SMARTCRAWL_VERSION',
			'verified_on' => null,
		),
		'squirrly-seo'          => array(
			'name'        => 'Squirrly SEO',
			'constant'    => 'SQ_VERSION',
			'verified_on' => null,
		),
	),
	// Plugins that may output overlapping tags: reported as a warning, output is not blocked.
	'overlap_plugins' => array(
		'jetpack' => array(
			'name'        => 'Jetpack',
			'constant'    => 'JETPACK__VERSION',
			'note'        => __( 'Jetpack can output Open Graph tags. Turn that module off to avoid duplicates.', 'khabar-seo' ),
			'verified_on' => null,
		),
		'amp'     => array(
			'name'        => 'AMP',
			'constant'    => 'AMP__VERSION',
			'note'        => __( 'The AMP plugin can add its own metadata to AMP pages.', 'khabar-seo' ),
			'verified_on' => null,
		),
	),
	// Calendar plugins that can rewrite date output and permalinks (machine dates must bypass them).
	'date_plugins'    => array(
		'wp-parsidate' => array(
			'name'        => 'WP-Parsidate',
			'option'      => 'wp_parsidate',
			'date_flag'   => 'persian_date',
			'link_flag'   => 'conv_permalinks',
			// Source read: github.com/wordpress-parsi/wp-parsidate v6.6.0. Date filters (wp_date, date_i18n,
			// get_the_date, get_the_time...) attach only when the locale is fa_IR and "persian_date" is on;
			// only format "U" is exempt. "conv_permalinks" converts post links and day archives.
			'verified_on' => '2026-11-19',
		),
		'wp-jalali'    => array(
			'name'        => 'WP Jalali',
			'option'      => '',
			'date_flag'   => '',
			'link_flag'   => '',
			'verified_on' => null,
		),
	),
	// Theme hints keyed by theme directory slug (parent or child). `og` = where to disable theme Open Graph tags.
	'themes'          => array(
		'jannah' => array(
			'name'        => 'Jannah (TieLabs)',
			// Jannah's docs list an "Open Graph meta" option in the single post settings and state it switches itself
			// off only when it detects another plugin (Yoast, Jetpack). Exact menu path may differ by version: [VERIFY].
			'og_hint'     => __( 'Jannah Theme Options, Single Post settings: turn off "Open Graph meta". Jannah only disables it automatically for plugins it recognizes.', 'khabar-seo' ),
			'schema_hint' => '',
			'verified_on' => '2026-11-19',
		),
	),
);
