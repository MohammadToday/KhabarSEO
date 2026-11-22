<?php
/**
 * URL normalization. One implementation for canonical, og:url, sitemaps and
 * redirects, so Persian (percent-encoded) slugs are encoded identically everywhere.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

declare(strict_types=1);

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * URL helpers.
 */
final class Url {

	/**
	 * Splits a URL into parts without decoding anything.
	 *
	 * @param string $url URL, absolute or relative.
	 * @return array{scheme:string,host:string,port:string,path:string,query:string,has_authority:bool}
	 */
	public static function parts( string $url ): array {
		$out = array(
			'scheme'        => '',
			'host'          => '',
			'port'          => '',
			'path'          => '',
			'query'         => '',
			'has_authority' => false,
		);
		if ( 1 !== preg_match( '~^(?:([A-Za-z][A-Za-z0-9+.\-]*):)?(?://([^/?#]*))?([^?#]*)(?:\?([^#]*))?(?:#.*)?$~s', $url, $m ) ) {
			return $out;
		}
		$out['scheme'] = strtolower( $m[1] );
		$out['path']   = $m[3];
		$out['query']  = $m[4] ?? '';
		if ( isset( $m[2] ) && '' !== $m[2] ) {
			$out['has_authority'] = true;
			$authority            = $m[2];
			$at                   = strrpos( $authority, '@' );
			if ( false !== $at ) {
				$authority = substr( $authority, $at + 1 );
			}
			if ( 1 === preg_match( '~^(.*):(\d+)$~', $authority, $hp ) ) {
				$authority   = $hp[1];
				$out['port'] = $hp[2];
			}
			$out['host'] = $authority;
		}
		return $out;
	}

	/**
	 * Normalizes a URL: lowercase scheme and host, IDN host to ASCII when intl
	 * is available, default port and user info and fragment removed, dot
	 * segments resolved, unreserved characters decoded, everything else
	 * percent-encoded with uppercase hex (UTF-8 bytes for Persian slugs).
	 *
	 * @param string $url URL or path.
	 * @return string
	 */
	public static function normalize( string $url ): string {
		$url = trim( $url );
		if ( '' === $url ) {
			return '';
		}
		$p    = self::parts( $url );
		$path = self::remove_dot_segments( self::encode( $p['path'], "/!$&'()*+,;=:@" ) );
		$out  = '';
		if ( $p['has_authority'] ) {
			$scheme = $p['scheme'];
			$host   = self::normalize_host( $p['host'] );
			$port   = $p['port'];
			if ( ( 'http' === $scheme && '80' === $port ) || ( 'https' === $scheme && '443' === $port ) ) {
				$port = '';
			}
			$out = ( '' !== $scheme ? $scheme . ':' : '' ) . '//' . $host . ( '' !== $port ? ':' . $port : '' );
			if ( '' === $path ) {
				$path = '/';
			}
		} elseif ( '' !== $p['scheme'] ) {
			$out = $p['scheme'] . ':';
		}
		$out .= $path;
		if ( '' !== $p['query'] ) {
			$out .= '?' . self::encode( $p['query'], "/?!$&'()*+,;=:@" );
		}
		return $out;
	}

	/**
	 * Normalized path plus query of a URL (no scheme or host).
	 *
	 * @param string $url URL or path.
	 * @return string
	 */
	public static function path_and_query( string $url ): string {
		$p   = self::parts( self::normalize( $url ) );
		$out = '' === $p['path'] ? '/' : $p['path'];
		return '' !== $p['query'] ? $out . '?' . $p['query'] : $out;
	}

	/**
	 * Removes query parameters whose names match a pattern list. A trailing
	 * "*" matches a prefix (utm_* removes utm_source, utm_medium, ...).
	 * Matching is case-insensitive; other parameters keep their order.
	 *
	 * @param string   $url      URL.
	 * @param string[] $patterns Parameter names or prefixes.
	 * @return string
	 */
	public static function strip_query_params( string $url, array $patterns ): string {
		$q = strpos( $url, '?' );
		if ( false === $q || ! $patterns ) {
			return $url;
		}
		$hash     = strpos( $url, '#', $q );
		$fragment = false === $hash ? '' : substr( $url, $hash );
		$query    = false === $hash ? substr( $url, $q + 1 ) : substr( $url, $q + 1, $hash - $q - 1 );
		$keep     = array();
		foreach ( explode( '&', $query ) as $pair ) {
			if ( '' === $pair ) {
				continue;
			}
			$name = strtolower( rawurldecode( explode( '=', $pair, 2 )[0] ) );
			if ( ! self::matches_any( $name, $patterns ) ) {
				$keep[] = $pair;
			}
		}
		return substr( $url, 0, $q ) . ( $keep ? '?' . implode( '&', $keep ) : '' ) . $fragment;
	}

	/**
	 * Whether a parameter name matches one of the patterns.
	 *
	 * @param string   $name     Lowercase parameter name.
	 * @param string[] $patterns Patterns.
	 * @return bool
	 */
	private static function matches_any( string $name, array $patterns ): bool {
		foreach ( $patterns as $pattern ) {
			$pattern = strtolower( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			if ( '*' === substr( $pattern, -1 ) ) {
				if ( 0 === strpos( $name, substr( $pattern, 0, -1 ) ) ) {
					return true;
				}
			} elseif ( $name === $pattern ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether two URLs point to the same host (after normalization).
	 *
	 * @param string $a First URL.
	 * @param string $b Second URL.
	 * @return bool
	 */
	public static function same_host( string $a, string $b ): bool {
		$ha = self::normalize_host( self::parts( $a )['host'] );
		$hb = self::normalize_host( self::parts( $b )['host'] );
		return '' !== $ha && $ha === $hb;
	}

	/**
	 * Lowercases the host and converts IDN hosts to punycode when possible.
	 *
	 * @param string $host Host.
	 * @return string
	 */
	private static function normalize_host( string $host ): string {
		$host = mb_strtolower( $host, 'UTF-8' );
		if ( 1 === preg_match( '/[^\x20-\x7E]/', $host ) && function_exists( 'idn_to_ascii' ) ) {
			$ascii = idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
			if ( is_string( $ascii ) && '' !== $ascii ) {
				return $ascii;
			}
		}
		return $host;
	}

	/**
	 * Percent-encodes everything except unreserved characters and the allowed
	 * extras; decodes escaped unreserved characters; uppercases hex.
	 *
	 * @param string $s       Component.
	 * @param string $allowed Extra characters that may stay literal.
	 * @return string
	 */
	private static function encode( string $s, string $allowed ): string {
		$out = '';
		$len = strlen( $s );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $s[ $i ];
			if ( '%' === $c && $i + 2 < $len && ctype_xdigit( $s[ $i + 1 ] ) && ctype_xdigit( $s[ $i + 2 ] ) ) {
				$hex  = strtoupper( substr( $s, $i + 1, 2 ) );
				$char = chr( (int) hexdec( $hex ) );
				$out .= self::is_unreserved( $char ) ? $char : '%' . $hex;
				$i   += 2;
				continue;
			}
			if ( self::is_unreserved( $c ) || false !== strpos( $allowed, $c ) ) {
				$out .= $c;
				continue;
			}
			$out .= sprintf( '%%%02X', ord( $c ) );
		}
		return $out;
	}

	/**
	 * RFC 3986 unreserved characters.
	 *
	 * @param string $c Single byte.
	 * @return bool
	 */
	private static function is_unreserved( string $c ): bool {
		return ctype_alnum( $c ) || false !== strpos( '-._~', $c );
	}

	/**
	 * Resolves "." and ".." segments.
	 *
	 * @param string $path Encoded path.
	 * @return string
	 */
	private static function remove_dot_segments( string $path ): string {
		if ( false === strpos( $path, '.' ) ) {
			return $path;
		}
		$segments = explode( '/', $path );
		$out      = array();
		$last     = count( $segments ) - 1;
		foreach ( $segments as $i => $segment ) {
			if ( '..' === $segment ) {
				if ( count( $out ) > 1 ) {
					array_pop( $out );
				}
				if ( $i === $last ) {
					$out[] = '';
				}
			} elseif ( '.' === $segment ) {
				if ( $i === $last ) {
					$out[] = '';
				}
			} else {
				$out[] = $segment;
			}
		}
		return implode( '/', $out );
	}
}
