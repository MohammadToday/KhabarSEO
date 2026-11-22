<?php
/**
 * Dependency-free smoke tests. Run: php tests/smoke.php
 *
 * Stubs the few WordPress functions that Settings, Sanitizer and friends use,
 * so the pure logic can be checked in CI without a WordPress install.
 * (The full PHPUnit suite arrives in Phase 6.)
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

// phpcs:disable -- Test file with WordPress stubs.

define( 'ABSPATH', __DIR__ . '/' );
define( 'KHS_DIR', dirname( __DIR__ ) . '/' );
define( 'KHS_VERSION', '0.0.0-test' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'MINUTE_IN_SECONDS', 60 );

$GLOBALS['khs_options'] = array();
$GLOBALS['khs_locale']  = 'fa_IR';

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['khs_options'] ) ? $GLOBALS['khs_options'][ $name ] : $default;
}
function update_option( $name, $value, $autoload = null ) {
	$GLOBALS['khs_options'][ $name ] = $value;
	return true;
}
function add_option( $name, $value, $deprecated = '', $autoload = 'yes' ) {
	return update_option( $name, $value );
}
function delete_option( $name ) {
	unset( $GLOBALS['khs_options'][ $name ] );
	return true;
}
function maybe_serialize( $value ) {
	return is_array( $value ) || is_object( $value ) ? serialize( $value ) : $value;
}
function do_action( ...$args ) {}
function apply_filters( $hook, $value, ...$args ) {
	return $value;
}
function get_locale() {
	return $GLOBALS['khs_locale'];
}
function sanitize_text_field( $s ) {
	return trim( strip_tags( (string) $s ) );
}
function wp_unslash( $v ) {
	return $v;
}
function absint( $v ) {
	return abs( (int) $v );
}
function get_post_types( $args = array(), $output = 'names' ) {
	$post = (object) array( 'labels' => (object) array( 'name' => 'Posts' ) );
	$page = (object) array( 'labels' => (object) array( 'name' => 'Pages' ) );
	$att  = (object) array( 'labels' => (object) array( 'name' => 'Media' ) );
	return array(
		'post'       => $post,
		'page'       => $page,
		'attachment' => $att,
	);
}
function get_post_type( $id ) {
	return 7 === (int) $id ? 'attachment' : 'post';
}
function wp_attachment_is_image( $id ) {
	return 7 === (int) $id;
}
function wp_timezone() {
	return new DateTimeZone( 'Asia/Tehran' );
}
function get_taxonomies( $args = array(), $output = 'names' ) {
	return array(
		'category' => (object) array( 'labels' => (object) array( 'name' => 'Categories' ) ),
		'post_tag' => (object) array( 'labels' => (object) array( 'name' => 'Tags' ) ),
	);
}
function esc_url_raw( $url, $protocols = null ) {
	$url = trim( (string) $url );
	return preg_match( '#^(https?)://#i', $url ) ? str_replace( ' ', '%20', $url ) : '';
}
function __( $text, $domain = '' ) {
	return $text;
}

require KHS_DIR . 'src/Autoloader.php';
\KhabarSEO\Autoloader::register( KHS_DIR . 'src/' );

use KhabarSEO\Conflicts\HeadScanner;
use KhabarSEO\Core\Environment;
use KhabarSEO\Core\Locale;
use KhabarSEO\Core\Migrations;
use KhabarSEO\Core\Presets;
use KhabarSEO\Core\Sanitizer;
use KhabarSEO\Core\Schema;
use KhabarSEO\Core\Settings;
use KhabarSEO\Core\Text;
use KhabarSEO\Core\Time;
use KhabarSEO\Core\Template;
use KhabarSEO\Core\Url;
use KhabarSEO\Output\Excerpt;
use KhabarSEO\Output\Robots;

$failures = 0;
$passed   = 0;

function check( string $name, $expected, $actual ): void {
	global $failures, $passed;
	if ( $expected === $actual ) {
		++$passed;
		return;
	}
	++$failures;
	fwrite( STDERR, "FAIL: $name\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n" );
}

// ---------------------------------------------------------------- Url.
$fa = "\u{062E}\u{0628}\u{0631}"; // "khabar".
$fa_enc = '%D8%AE%D8%A8%D8%B1';
check( 'url raw vs encoded', Url::normalize( "https://Example.com/$fa/x" ), Url::normalize( "https://example.com/$fa_enc/x" ) );
check( 'url full', 'https://example.com/b/%D8%AE%D8%A8%D8%B1/?x=1', Url::normalize( 'HTTPS://Example.COM:443/a/../b/%d8%ae%d8%a8%d8%b1/?x=1#frag' ) );
check( 'url keeps %2F', 'https://example.com/a%2Fb', Url::normalize( 'https://example.com/a%2fb' ) );
check( 'url decodes unreserved', 'https://example.com/A', Url::normalize( 'https://example.com/%41' ) );
check( 'url space', '/a%20b', Url::normalize( '/a b' ) );
check( 'url empty path', 'https://example.com/', Url::normalize( 'https://example.com' ) );
check( 'url port kept', 'http://example.com:8080/', Url::normalize( 'http://example.com:8080' ) );
check( 'url lone percent', '/100%25', Url::normalize( '/100%' ) );
check( 'url userinfo dropped', 'https://example.com/', Url::normalize( 'https://user:pw@example.com/' ) );
check( 'url subdirectory', 'https://example.com/blog/%D8%AE%D8%A8%D8%B1', Url::normalize( "https://example.com/blog/$fa" ) );
check( 'url path_and_query', '/news/?p=1', Url::path_and_query( 'https://example.com/news/?p=1#x' ) );
check( 'url same host', true, Url::same_host( 'https://EXAMPLE.com/a', 'http://example.com/b' ) );
check( 'url other host', false, Url::same_host( 'https://example.org/a', 'https://example.com/b' ) );
if ( function_exists( 'idn_to_ascii' ) ) {
	$idn_host = "\u{062E}\u{0628}\u{0631}.\u{0627}\u{06CC}\u{0631}\u{0627}\u{0646}";
	check( 'url idn host', 'https://' . idn_to_ascii( $idn_host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 ) . '/x', Url::normalize( "https://$idn_host/x" ) );
}

// ---------------------------------------------------------------- Text.
$zwnj = "\u{200C}";
check( 'text nbsp and tabs', "a b c{$zwnj}d", Text::normalize_space( "a\u{00A0}b\t\n c{$zwnj}d" ) );
check( 'text short unchanged', 'abc', Text::truncate( 'abc', 10 ) );
$long = str_repeat( "\u{0645}\u{06CC}{$zwnj}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645} ", 20 );
$cut  = Text::truncate( $long, 60 );
check( 'text truncate length', true, Text::length( $cut ) <= 60 );
check( 'text truncate valid utf8', true, mb_check_encoding( $cut, 'UTF-8' ) );
check( 'text truncate ellipsis', true, str_ends_with( $cut, '…' ) );
check( 'text no dangling zwnj', false, str_ends_with( $cut, $zwnj . '…' ) );
check( 'text zwnj at cut', "abc\u{2026}", Text::truncate( "abc{$zwnj}def ghi", 5 ) );
check( 'text cut before space', "hello\u{2026}", Text::truncate( 'hello world and more', 6 ) );
check( 'text strip punctuation', "hello\u{2026}", Text::truncate( 'hello, world and more', 7 ) );
check( 'text invalid utf8 safe', true, is_string( Text::truncate( "ab\xC3\x28cd efghij", 5 ) ) );
check( 'text digits untouched', "\u{06F1}\u{06F2}\u{06F3}", Text::normalize_space( " \u{06F1}\u{06F2}\u{06F3} " ) );

// ---------------------------------------------------------------- Time.
$tehran = new DateTimeZone( 'Asia/Tehran' );
check( 'time july tehran', '2026-07-15T12:00:00+03:30', Time::iso_from_gmt( '2026-07-15 08:30:00', $tehran ) );
check( 'time january tehran', '2026-01-15T12:00:00+03:30', Time::iso_from_gmt( '2026-01-15 08:30:00', $tehran ) );
check( 'time local', '2026-07-15T12:00:00+03:30', Time::iso_from_local( '2026-07-15 12:00:00', $tehran ) );
check( 'time zero date', '', Time::iso_from_gmt( '0000-00-00 00:00:00', $tehran ) );
check( 'time machine iso ok', true, Time::is_machine_iso( '2026-07-15T12:00:00+03:30' ) );
check( 'time rejects jalali year', false, Time::is_machine_iso( '1405-04-24T12:00:00+03:30' ) );
check( 'time rejects persian digits', false, Time::is_machine_iso( "\u{06F2}\u{06F0}\u{06F2}\u{06F6}-07-15T12:00:00+03:30" ) );

// ---------------------------------------------------------------- Environment.
$now = new DateTimeImmutable( '2026-11-19 12:00:00', new DateTimeZone( 'UTC' ) );
check( 'php 8.1 eol', 'fail', Environment::php_status( '8.1.30', $now )['level'] );
check( 'php 8.2 soon', 'warn', Environment::php_status( '8.2.0', $now )['level'] );
check( 'php 8.3 ok', 'ok', Environment::php_status( '8.3.6', $now )['level'] );
check( 'php 8.5 ok', 'ok', Environment::php_status( '8.5.12', $now )['level'] );
check( 'php future unknown ok', 'ok', Environment::php_status( '9.0.0', $now )['level'] );
check( 'php 7.2 fail', 'fail', Environment::php_status( '7.2.1', $now )['level'] );
$tz = Environment::timezone_report( $tehran, 2026 );
check( 'tz tehran offsets', array( '+03:30', '+03:30', '' ), array( $tz['jan'], $tz['jul'], $tz['issue'] ) );
check( 'tz utc flagged', 'utc_default', Environment::timezone_report( new DateTimeZone( 'UTC' ), 2026 )['issue'] );

// ---------------------------------------------------------------- Locale.
check( 'locale fa_IR', 'fa_IR', Locale::normalize( 'fa_IR' ) );
check( 'locale dashed', 'fa_IR', Locale::normalize( 'fa-ir' ) );
check( 'locale bare', 'fa', Locale::normalize( 'fa' ) );
check( 'locale bcp47', 'fa-IR', Locale::bcp47( 'fa_IR' ) );

// ---------------------------------------------------------------- Sanitizer.
$err = '';
$s   = static function ( string $key, $raw ) use ( &$err ) {
	return Sanitizer::sanitize_value( Schema::field( $key ), $raw, $err );
};
check( 'san handle strips at', '@yournews', $s( 'social.twitter_site', '@yournews' ) );
check( 'san handle adds at', '@yournews', $s( 'social.twitter_site', 'yournews' ) );
$s( 'social.twitter_site', 'bad handle!' );
check( 'san handle invalid error', 'format', $err );
check( 'san locale normalized', 'fa_IR', $s( 'site.locale', 'fa-ir' ) );
$s( 'site.locale', 'x1_@@' );
check( 'san locale invalid', 'format', $err );
check( 'san lang lower', 'fa', $s( 'site.language', ' FA ' ) );
check( 'san choice invalid', 'min_posts', $s( 'indexing.tags', 'bogus' ) );
check( 'san choice error', 'choice', $err );
check( 'san int clamp max', 50, $s( 'indexing.tag_min_posts', '999' ) );
check( 'san int clamp min', 1, $s( 'indexing.tag_min_posts', '-5' ) );
check( 'san bool off', false, $s( 'social.og_enabled', null ) );
check( 'san bool on', true, $s( 'social.og_enabled', '1' ) );
check( 'san post types', array( 'post' ), $s( 'site.news_post_types', array( 'post', 'attachment', 'evil', 'post' ) ) );
check( 'san media attachment ok', 7, $s( 'social.default_image_id', '7' ) );
check( 'san media not attachment', 0, $s( 'social.default_image_id', '12' ) );
check( 'san text max', 120, mb_strlen( $s( 'site.publication_name', str_repeat( "\u{062E}", 500 ) ), 'UTF-8' ) );
check( 'san text strips tags', 'News', $s( 'site.publication_name', '<b>News</b>' ) );

// ---------------------------------------------------------------- Settings.
check( 'default language dynamic', 'fa', Settings::get( 'site.language' ) );
check( 'default locale dynamic', 'fa_IR', Settings::get( 'site.locale' ) );
check( 'default separator', '-', Settings::get( 'titles.separator' ) );
check( 'default wizard not done', false, Settings::is_on( 'state.wizard_done' ) );
check( 'unknown key null', null, Settings::get( 'nope.nope' ) );

$res = Settings::update( array( 'site.publication_name' => "\u{062E}\u{0628}\u{0631}", 'indexing.tags' => 'index_all', 'state.wizard_done' => true ) );
check( 'update ok', true, $res['ok'] );
check( 'update stored', 'index_all', Settings::get( 'indexing.tags' ) );
check( 'update ignores internal', false, Settings::is_on( 'state.wizard_done' ) );
check( 'update ignores unknown', true, Settings::update( array( 'bogus.key' => 'x' ) )['ok'] );

$export = Settings::export();
check( 'export has no state', false, isset( $export['settings']['state.wizard_done'] ) );
check( 'export format', array( 'khabar-seo', 1 ), array( $export['plugin'], $export['format'] ) );
Settings::reset();
check( 'reset restores default', 'min_posts', Settings::get( 'indexing.tags' ) );
check( 'reset clears name', '', Settings::get( 'site.publication_name' ) );
$imp = Settings::import( json_decode( json_encode( $export ), true ) );
check( 'import ok', true, $imp['ok'] );
check( 'round trip tags', 'index_all', Settings::get( 'indexing.tags' ) );
check( 'round trip name', "\u{062E}\u{0628}\u{0631}", Settings::get( 'site.publication_name' ) );
check( 'import rejects foreign', 'format', Settings::import( array( 'plugin' => 'other' ) )['error'] );
check( 'import rejects non array', 'format', Settings::import( 'x' )['error'] );
$bad = $export;
$bad['settings']['indexing.tags'] = 'EVIL';
$bad['settings']['unknown.key']   = 'x';
Settings::import( $bad );
check( 'import sanitizes', 'min_posts', Settings::get( 'indexing.tags' ) );

$res = Settings::update( array( 'site.alternate_name' => 'x' ) );
$GLOBALS['khs_options'][ Settings::OPTION ]['site']['pad'] = str_repeat( 'x', 9000 );
Settings::flush_cache();
$res = Settings::update( array( 'site.alternate_name' => 'y' ) );
check( 'size cap enforced', 'size', $res['error'] );
Settings::reset();
check( 'reset recovers from oversized', true, Settings::stored_size() < Settings::MAX_BYTES );

Settings::set_ext( 'scan', array( 'a' => 1 ) );
check( 'ext roundtrip', array( 'a' => 1 ), Settings::get_ext( 'scan' ) );

$wiz = Presets::apply_news_recommended();
check( 'preset applies', true, $wiz['ok'] );
check( 'preset marks wizard done', true, Settings::is_on( 'state.wizard_done' ) );
check( 'preset keeps keep_data on', true, Settings::is_on( 'advanced.keep_data' ) );

// ---------------------------------------------------------------- Migrations.
$m = Migrations::run( array(), 0 );
check( 'migration sets schema', Schema::VERSION, $m['state']['schema'] );
check( 'schema keys unique and dotted', true, count( array_filter( Schema::keys( true ), static fn( $k ) => false !== strpos( $k, '.' ) ) ) === count( Schema::keys( true ) ) );
check( 'every tab field has a ui label', true, count( array_filter( Schema::keys(), static fn( $k ) => '' === Schema::ui( $k )['label'] || $k === Schema::ui( $k )['label'] ) ) === 0 );
check( 'every choice option has ui label', true, ( static function () {
	foreach ( Schema::definitions() as $key => $spec ) {
		if ( 'choice' !== $spec['type'] ) {
			continue;
		}
		foreach ( $spec['options'] as $opt ) {
			if ( empty( Schema::ui( $key )['options'][ $opt ]['label'] ) || empty( Schema::ui( $key )['options'][ $opt ]['guide'] ?? ( 'titles.separator' === $key ? 'x' : '' ) ) ) {
				return false;
			}
		}
	}
	return true;
} )() );
check( 'recommended is a valid option', true, ( static function () {
	foreach ( Schema::definitions() as $spec ) {
		if ( 'choice' === $spec['type'] && ! in_array( $spec['recommended'], $spec['options'], true ) ) {
			return false;
		}
	}
	return true;
} )() );
check( 'visible tabs', array( 'setup', 'titles', 'indexing', 'social', 'advanced', 'tools', 'status' ), array_keys( Schema::visible_tabs() ) );

// ---------------------------------------------------------------- HeadScanner.
$html = '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><title>' . $fa . ' | Site</title>'
	. '<link rel="canonical" href="https://example.com/a"><link rel="canonical" href="https://example.com/b">'
	. '<meta name="robots" content="index, follow"><meta name="robots" content="noindex">'
	. '<meta property="og:title" content="One"><meta property="og:title" content="Two"><meta property="og:type" content="article">'
	. '<meta name="twitter:card" content="summary"><meta name="generator" content="WordPress 7.1">'
	. '<link rel="shortlink" href="https://example.com/?p=1">'
	. '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"NewsArticle"},{"@type":["WebSite","Thing"]}]}</script>'
	. '<script type="application/ld+json">not json</script>'
	. '<!-- KhabarSEO 0.1 --></head><body><title>ignored</title></body></html>';
$p = HeadScanner::parse( $html );
check( 'scan title count', 1, count( $p['title'] ) );
check( 'scan persian title', $fa . ' | Site', $p['title'][0] );
check( 'scan canonical count', 2, count( $p['canonical'] ) );
check( 'scan robots count', 2, count( $p['robots'] ) );
check( 'scan og count', 2, count( $p['og']['og:title'] ) );
check( 'scan jsonld', array( 2, 1 ), array( $p['jsonld']['count'], $p['jsonld']['invalid'] ) );
check( 'scan jsonld types', array( 'NewsArticle', 'WebSite', 'Thing' ), $p['jsonld']['types'] );
check( 'scan marker', true, $p['marker'] );
check( 'scan shortlink', 1, $p['links']['shortlink'] );
$codes = array_column( HeadScanner::findings( $p ), 'code' );
check( 'findings dup canonical', true, in_array( 'dup_canonical', $codes, true ) );
check( 'findings dup robots', true, in_array( 'dup_robots', $codes, true ) );
check( 'findings dup og', true, in_array( 'dup_og', $codes, true ) );
check( 'scan no head', array(), HeadScanner::parse( '<html></html>' )['title'] );

// ---------------------------------------------------------------- Template engine (Part 1B).
$zw   = "\u{200C}";
$vars = array(
	'title'    => "اخبار ورزشی امروز",
	'sitename' => "خبر{$zw}نامه",
	'tagline'  => '',
	'page'     => '',
	'category' => 'Sport',
);
check( 'tpl basic', "اخبار ورزشی امروز - خبر{$zw}نامه", Template::render( '%title% %sep% %sitename%', $vars, '-' ) );
check( 'tpl empty page collapses', "اخبار ورزشی امروز - خبر{$zw}نامه", Template::render( '%title% %sep% %page% %sep% %sitename%', $vars, '-' ) );
check( 'tpl empty tagline trailing sep', "خبر{$zw}نامه", Template::render( '%sitename% %sep% %tagline%', $vars, '-' ) );
check( 'tpl leading sep removed', 'Sport', Template::render( '%sep% %category%', $vars, '|' ) );
check( 'tpl pipe separator', 'Sport | x', Template::render( '%category% %sep% x', $vars, '|' ) );
check( 'tpl middle dot', 'Sport · x', Template::render( '%category%%sep%x', $vars, '·' ) );
check( 'tpl unknown kept', 'Sport %foo% 50%', Template::render( '%category% %foo% 50%', $vars, '-' ) );
check( 'tpl zwnj preserved', true, false !== strpos( Template::render( '%sitename%', $vars, '-' ), $zw ) );
check( 'tpl all empty', '', Template::render( '%tagline% %sep% %page%', $vars, '-' ) );
check( 'tpl blank template', '', Template::render( '   ', $vars, '-' ) );
check( 'tpl page present', 'T - Page 2 of 3 - S', Template::render( '%title% %sep% %page% %sep% %sitename%', array( 'title' => 'T', 'page' => 'Page 2 of 3', 'sitename' => 'S' ), '-' ) );
$calls = 0;
$lazy  = array(
	'title'  => 'A',
	'author' => static function () use ( &$calls ) {
		++$calls;
		return 'Writer';
	},
);
Template::render( '%title% %sep% %sitename%', $lazy, '-' );
check( 'tpl lazy: unused resolver not called', 0, $calls );
Template::render( '%author% %author%', $lazy, '-' );
check( 'tpl lazy: called once when used twice', 1, $calls );
check( 'tpl value cannot inject separator', 'A B', Template::render( '%title%', array( 'title' => "A \u{E000} B" ), '-' ) );
check( 'tpl unknown variables', array( '%foo%' ), Template::unknown_variables( '%title% %sep% %foo% %page%' ) );
check( 'tpl uses', true, Template::uses( '%title% %excerpt%', 'excerpt' ) );

// ---------------------------------------------------------------- Persian/ZWNJ description truncation.
$desc = str_repeat( "خبرهای{$zw}مهم امروز را می{$zw}خوانید ", 15 );
$cut  = Text::truncate( Text::plain( $desc ), 160 );
check( 'desc truncate <= 160', true, Text::length( $cut ) <= 160 );
check( 'desc truncate no dangling zwnj', 0, preg_match( '/\x{200C}…$/u', $cut ) );
check( 'desc truncate does not split half-space word', 1, preg_match( '/(می\x{200C}خوانید|امروز|مهم|خبرهای\x{200C}مهم|را)…$/u', $cut ) );
check( 'plain decodes entities', 'A & B "C"', Text::plain( 'A &amp; B <b>&quot;C&quot;</b>' ) );
check( 'plain strips script', 'x y', Text::plain( 'x<script>alert(1)</script>y' ) );

// ---------------------------------------------------------------- Excerpt hidden sections.
$content = "<p>Visible</p>\n<!-- wp:khabar-seo/nosnippet -->\n<div data-nosnippet class=\"wp-block-khabar-seo-nosnippet\"><p>SECRET1</p></div>\n<!-- /wp:khabar-seo/nosnippet -->\n[khs_nosnippet]SECRET2[/khs_nosnippet] <span data-nosnippet>SECRET3</span> end";
$clean   = Excerpt::strip_hidden( $content );
check( 'excerpt strips hidden block', false, strpos( $clean, 'SECRET1' ) );
check( 'excerpt strips shortcode', false, strpos( $clean, 'SECRET2' ) );
check( 'excerpt strips data-nosnippet span', false, strpos( $clean, 'SECRET3' ) );
check( 'excerpt keeps visible', true, false !== strpos( $clean, 'Visible' ) && false !== strpos( $clean, 'end' ) );

// ---------------------------------------------------------------- Robots merge.
$ours = array(
	'noindex'           => false,
	'nofollow'          => false,
	'nosnippet'         => false,
	'max-snippet'       => '-1',
	'max-image-preview' => 'large',
	'max-video-preview' => '-1',
);
check( 'robots default order', array( 'index' => true, 'follow' => true, 'max-image-preview' => 'large', 'max-snippet' => '-1', 'max-video-preview' => '-1' ), Robots::merge( array( 'max-image-preview' => 'large' ), $ours ) );
$r = Robots::merge( array( 'noindex' => true, 'follow' => true ), $ours );
check( 'robots core search noindex kept, no index', array( true, false, true ), array( isset( $r['noindex'] ), isset( $r['index'] ), isset( $r['follow'] ) ) );
$r = Robots::merge( array( 'noindex' => true, 'nofollow' => true ), $ours );
check( 'robots discouraged site keeps nofollow', array( false, true ), array( isset( $r['follow'] ), isset( $r['nofollow'] ) ) );
$r = Robots::merge( array(), array_merge( $ours, array( 'nosnippet' => true ) ) );
check( 'robots nosnippet drops max-snippet', array( true, false ), array( isset( $r['nosnippet'] ), isset( $r['max-snippet'] ) ) );
$r = Robots::merge( array( 'max-image-preview' => 'large' ), array_merge( $ours, array( 'max-image-preview' => 'standard' ) ) );
check( 'robots image preview most restrictive', 'standard', $r['max-image-preview'] );
$r = Robots::merge( array( 'max-snippet' => '50' ), $ours );
check( 'robots max-snippet most restrictive', '50', $r['max-snippet'] );
$r = Robots::merge( array( 'none' => true ), $ours );
check( 'robots none becomes noindex nofollow', array( true, true, false ), array( isset( $r['noindex'] ), isset( $r['nofollow'] ), isset( $r['none'] ) ) );
$r = Robots::merge( array( 'noarchive' => true ), $ours );
check( 'robots keeps foreign directives', true, $r['noarchive'] );

// ---------------------------------------------------------------- Canonical helpers.
check( 'strip utm_*', 'https://example.com/a?x=1', Url::strip_query_params( 'https://example.com/a?utm_source=t&x=1&UTM_Medium=m', array( 'utm_*' ) ) );
check( 'strip fbclid keeps fragment', 'https://example.com/a#top', Url::strip_query_params( 'https://example.com/a?fbclid=abc#top', array( 'fbclid' ) ) );
check( 'strip none', 'https://example.com/a?p=1', Url::strip_query_params( 'https://example.com/a?p=1', array( 'utm_*' ) ) );
check( 'strip paged kept', 'https://example.com/?cat=3&paged=2', Url::strip_query_params( 'https://example.com/?cat=3&paged=2&gclid=x', array( 'gclid' ) ) );
check( 'persian slug canonical uppercase hex', 'https://example.com/%D8%AE%D8%A8%D8%B1/', Url::normalize( 'https://example.com/%d8%ae%d8%a8%d8%b1/' ) );
check( 'persian page 2 canonical', 'https://example.com/%D8%AE%D8%A8%D8%B1/page/2/', Url::normalize( "https://example.com/\u{062E}\u{0628}\u{0631}/page/2/" ) );

// ---------------------------------------------------------------- Sanitizer (Part 1B types).
$err = '';
$f   = static function ( string $key, $raw ) use ( &$err ) {
	return Sanitizer::sanitize_value( Schema::field( $key ), $raw, $err );
};
check( 'san max_snippet -1', -1, $f( 'robots.max_snippet', '-1' ) );
check( 'san max_snippet clamp low', -1, $f( 'robots.max_snippet', '-5' ) );
check( 'san max_snippet clamp error', 'range', $err );
check( 'san max_snippet garbage default', -1, $f( 'robots.max_snippet', 'abc' ) );
check( 'san template unknown var error', 'variables', ( $f( 'titles.home_title', '%sitename% %bogus%' ) || true ) ? $err : '' );
check( 'san template strips tags', '%title% - x', $f( 'titles.home_title', '<b>%title%</b> - x' ) );
check( 'san map drops defaults and unknown slugs', array( 'page' => '%title%' ), $f( 'titles.singular_title', array( 'post' => Schema::TPL_SINGULAR, 'page' => '%title%', 'evil' => 'x' ) ) );
check( 'san map taxonomy', array( 'post_tag' => '%title% %sep% tags' ), $f( 'titles.term_title', array( 'post_tag' => '%title% %sep% tags', 'category' => Schema::TPL_LIST ) ) );
check( 'san list', array( 'utm_*', 'fbclid', 'ref' ), $f( 'canonical.strip_params', "utm_*\nFBCLID, ref\n bad param \n<x>\nfbclid" ) );
check( 'san url ok', 'https://example.com/x', Sanitizer::absolute_url( ' https://example.com/x ' ) );
check( 'san url rejects js', '', Sanitizer::absolute_url( 'javascript:alert(1)' ) );
check( 'san separator middle dot', '·', $f( 'titles.separator', '·' ) );
check( 'san separator old bullet rejected', '-', $f( 'titles.separator', '•' ) );

// ---------------------------------------------------------------- Migration v1 -> v2 and map settings.
$m2 = Migrations::run( array( 'titles' => array( 'separator' => '•' ), 'state' => array( 'schema' => 1 ) ), 1 );
check( 'migrate bullet to middle dot', '·', $m2['titles']['separator'] );
check( 'migrate import bullet', '·', Migrations::migrate_flat( array( 'titles.separator' => '•' ), 1 )['titles.separator'] );
Settings::reset();
check( 'map default post', '%title% %sep% %sitename%', Settings::map_item( 'titles.singular_title', 'post' ) );
Settings::update( array( 'titles.singular_title' => array( 'page' => '%title%' ) ) );
check( 'map override page', '%title%', Settings::map_item( 'titles.singular_title', 'page' ) );
check( 'map default term', Schema::TPL_LIST, Settings::map_item( 'titles.term_title', 'category' ) );
check( 'settings size after defaults', true, Settings::stored_size() < 4096 );

echo "\n$passed passed, $failures failed\n";
exit( $failures > 0 ? 1 : 0 );
