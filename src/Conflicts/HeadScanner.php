<?php
/**
 * Rendered <head> scanner.
 *
 * Runs only from the admin (Status page button). It requests the site's own
 * pages over a loopback request, never an external host, and never on public
 * page loads. parse() is pure and unit-testable.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Conflicts;

use KhabarSEO\Core\Settings;
use KhabarSEO\Core\Url;

defined( 'ABSPATH' ) || exit;

/**
 * Head scanner.
 */
final class HeadScanner {

	private const MAX_BYTES = 400000;
	private const MAX_VALUE = 300;

	/**
	 * URLs to scan: the home page and the newest published news post.
	 *
	 * @return array<string, string> Label key => URL.
	 */
	public static function targets(): array {
		$urls  = array( 'home' => home_url( '/' ) );
		$types = (array) Settings::get( 'site.news_post_types' );
		$posts = get_posts(
			array(
				'post_type'        => $types ? $types : array( 'post' ),
				'post_status'      => 'publish',
				'numberposts'      => 1,
				'orderby'          => 'date',
				'order'            => 'DESC',
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);
		if ( $posts ) {
			$urls['post'] = (string) get_permalink( (int) $posts[0] );
		}
		return $urls;
	}

	/**
	 * Fetches and parses one URL of this site.
	 *
	 * @param string $url Absolute URL on this site's host.
	 * @return array{ok:bool,error:string,status:int,parsed:array}
	 */
	public static function scan( string $url ): array {
		$result = array(
			'ok'     => false,
			'error'  => '',
			'status' => 0,
			'parsed' => array(),
		);
		if ( ! Url::same_host( $url, home_url( '/' ) ) ) {
			$result['error'] = 'host';
			return $result;
		}
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 15,
				'redirection' => 3,
				'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core filter used for loopback requests.
				'user-agent'  => 'KhabarSEO-HeadScan/' . KHS_VERSION,
				'headers'     => array( 'Cache-Control' => 'no-cache' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			$result['error'] = 'request';
			return $result;
		}
		$result['status'] = (int) wp_remote_retrieve_response_code( $response );
		if ( $result['status'] < 200 || $result['status'] >= 300 ) {
			$result['error'] = 'status';
			return $result;
		}
		$parsed = self::parse( (string) wp_remote_retrieve_body( $response ) );
		if ( isset( $parsed['error'] ) ) {
			$result['error'] = (string) $parsed['error'];
			return $result;
		}
		$result['ok']     = true;
		$result['parsed'] = $parsed;
		return $result;
	}

	/**
	 * Parses the <head> of an HTML document.
	 *
	 * @param string $html Full HTML or just the head.
	 * @return array Parsed tags, or array( 'error' => 'dom' ) without ext-dom.
	 */
	public static function parse( string $html ): array {
		if ( ! class_exists( '\DOMDocument' ) ) {
			return array( 'error' => 'dom' );
		}
		$end  = stripos( $html, '</head>' );
		$head = false === $end ? substr( $html, 0, self::MAX_BYTES ) : substr( $html, 0, min( $end + 7, self::MAX_BYTES ) );

		$out = array(
			'title'       => array(),
			'canonical'   => array(),
			'robots'      => array(),
			'description' => array(),
			'og'          => array(),
			'twitter'     => array(),
			'jsonld'      => array(
				'count'   => 0,
				'types'   => array(),
				'invalid' => 0,
			),
			'generator'   => array(),
			'links'       => array(),
			'marker'      => 1 === preg_match( '/<!--\s*KhabarSEO/i', $head ),
		);

		$previous = libxml_use_internal_errors( true );
		$doc      = new \DOMDocument();
		$doc->loadHTML( '<?xml encoding="UTF-8">' . $head, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		foreach ( $doc->getElementsByTagName( 'title' ) as $node ) {
			$out['title'][] = self::clip( $node->textContent ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
		}
		foreach ( $doc->getElementsByTagName( 'link' ) as $node ) {
			$rel = strtolower( trim( $node->getAttribute( 'rel' ) ) );
			if ( 'canonical' === $rel ) {
				$out['canonical'][] = self::clip( $node->getAttribute( 'href' ) );
			} elseif ( '' !== $rel ) {
				$out['links'][ $rel ] = ( $out['links'][ $rel ] ?? 0 ) + 1;
			}
		}
		foreach ( $doc->getElementsByTagName( 'meta' ) as $node ) {
			$name     = strtolower( trim( $node->getAttribute( 'name' ) ) );
			$property = strtolower( trim( $node->getAttribute( 'property' ) ) );
			$content  = self::clip( $node->getAttribute( 'content' ) );
			if ( 'robots' === $name ) {
				$out['robots'][] = $content;
			} elseif ( 'description' === $name ) {
				$out['description'][] = $content;
			} elseif ( 'generator' === $name ) {
				$out['generator'][] = $content;
			} elseif ( 0 === strpos( $property, 'og:' ) || 0 === strpos( $property, 'article:' ) ) {
				$out['og'][ $property ][] = $content;
			} elseif ( 0 === strpos( $name, 'twitter:' ) ) {
				$out['twitter'][ $name ][] = $content;
			}
		}
		foreach ( $doc->getElementsByTagName( 'script' ) as $node ) {
			if ( 'application/ld+json' !== strtolower( trim( $node->getAttribute( 'type' ) ) ) ) {
				continue;
			}
			++$out['jsonld']['count'];
			$json = json_decode( $node->textContent, true, 32 ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOM API.
			if ( ! is_array( $json ) ) {
				++$out['jsonld']['invalid'];
				continue;
			}
			foreach ( self::collect_types( $json ) as $type ) {
				$out['jsonld']['types'][] = $type;
			}
		}
		$out['jsonld']['types'] = array_slice( array_values( array_unique( $out['jsonld']['types'] ) ), 0, 30 );
		return $out;
	}

	/**
	 * Lists duplicate or noteworthy findings for the Status page.
	 *
	 * @param array $parsed Result of parse().
	 * @return array<int, array{level:string,code:string,detail:string}>
	 */
	public static function findings( array $parsed ): array {
		$out = array();
		foreach ( array( 'title', 'canonical', 'robots', 'description' ) as $tag ) {
			$count = count( $parsed[ $tag ] ?? array() );
			if ( $count > 1 ) {
				$out[] = array(
					'level'  => 'warn',
					'code'   => 'dup_' . $tag,
					'detail' => (string) $count,
				);
			} elseif ( 0 === $count && in_array( $tag, array( 'canonical', 'robots' ), true ) ) {
				$out[] = array(
					'level'  => 'info',
					'code'   => 'none_' . $tag,
					'detail' => '',
				);
			}
		}
		foreach ( (array) ( $parsed['og'] ?? array() ) as $property => $values ) {
			if ( count( $values ) > 1 && 'article:tag' !== $property && 'article:author' !== $property ) {
				$out[] = array(
					'level'  => 'warn',
					'code'   => 'dup_og',
					'detail' => (string) $property,
				);
			}
		}
		if ( ! empty( $parsed['og'] ) ) {
			$out[] = array(
				'level'  => empty( $parsed['marker'] ) ? 'warn' : 'info',
				'code'   => 'og_present',
				'detail' => (string) count( $parsed['og'] ),
			);
		}
		if ( ! empty( $parsed['jsonld']['count'] ) ) {
			$out[] = array(
				'level'  => 'info',
				'code'   => 'jsonld_present',
				'detail' => (string) $parsed['jsonld']['count'],
			);
		}
		return $out;
	}

	/**
	 * Collects @type values recursively (also inside @graph).
	 *
	 * @param array $node JSON-LD node.
	 * @return string[]
	 */
	private static function collect_types( array $node ): array {
		$types = array();
		if ( isset( $node['@type'] ) ) {
			foreach ( (array) $node['@type'] as $type ) {
				if ( is_string( $type ) ) {
					$types[] = $type;
				}
			}
		}
		foreach ( $node as $child ) {
			if ( is_array( $child ) ) {
				$types = array_merge( $types, self::collect_types( $child ) );
			}
		}
		return $types;
	}

	/**
	 * Limits a stored value's length.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function clip( string $value ): string {
		$value = trim( preg_replace( '/\s+/u', ' ', $value ) ?? '' );
		return mb_substr( $value, 0, self::MAX_VALUE, 'UTF-8' );
	}
}
