#!/usr/bin/env php
<?php
/**
 * Extracts translatable strings into languages/khabar-seo.pot.
 *
 * Usage: php bin/make-pot.php
 *
 * Needs only PHP (no WP-CLI). Reads string literals passed to the WordPress
 * gettext functions with the "khabar-seo" text domain, plus "translators:"
 * comments. Calls with non-literal arguments are reported and skipped.
 *
 * @package KhabarSEO
 */

// phpcs:disable -- Developer CLI script.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root   = dirname( __DIR__ );
$domain = 'khabar-seo';

// Function => argument positions (0-based): msgid, plural, context, domain.
$functions = array(
	'__'            => array( 'msgid' => 0, 'domain' => 1 ),
	'_e'            => array( 'msgid' => 0, 'domain' => 1 ),
	'esc_html__'    => array( 'msgid' => 0, 'domain' => 1 ),
	'esc_html_e'    => array( 'msgid' => 0, 'domain' => 1 ),
	'esc_attr__'    => array( 'msgid' => 0, 'domain' => 1 ),
	'esc_attr_e'    => array( 'msgid' => 0, 'domain' => 1 ),
	'_x'            => array( 'msgid' => 0, 'context' => 1, 'domain' => 2 ),
	'esc_html_x'    => array( 'msgid' => 0, 'context' => 1, 'domain' => 2 ),
	'esc_attr_x'    => array( 'msgid' => 0, 'context' => 1, 'domain' => 2 ),
	'_n'            => array( 'msgid' => 0, 'plural' => 1, 'domain' => 3 ),
	'_nx'           => array( 'msgid' => 0, 'plural' => 1, 'context' => 3, 'domain' => 4 ),
);

$files = array( $root . '/khabar-seo.php', $root . '/uninstall.php' );
foreach ( array( 'src', 'data' ) as $dir ) {
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root . '/' . $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		if ( 'php' === $file->getExtension() ) {
			$files[] = $file->getPathname();
		}
	}
}
sort( $files );

/**
 * Decodes a PHP string literal token.
 */
function khs_literal( string $token ): string {
	$quote = $token[0];
	$body  = substr( $token, 1, -1 );
	if ( "'" === $quote ) {
		return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
	}
	return stripcslashes( $body );
}

/**
 * Escapes a string for a PO file.
 */
function khs_po( string $s ): string {
	return '"' . str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s ) . '"';
}

$entries = array();
$skipped = array();

foreach ( $files as $path ) {
	$rel    = ltrim( str_replace( $root, '', $path ), '/' );
	$tokens = token_get_all( (string) file_get_contents( $path ) );
	$count  = count( $tokens );
	$line   = 1;
	$comment = '';
	for ( $i = 0; $i < $count; $i++ ) {
		$t = $tokens[ $i ];
		if ( is_array( $t ) ) {
			$line = $t[2];
			if ( in_array( $t[0], array( T_COMMENT, T_DOC_COMMENT ), true ) && false !== stripos( $t[1], 'translators:' ) ) {
				$comment = trim( preg_replace( '#^/\*+\s*|\s*\*+/$|^//\s*#', '', $t[1] ) );
				$comment = trim( preg_replace( '/\s*\n\s*\*?\s*/', ' ', $comment ) );
			}
		}
		if ( ! is_array( $t ) || T_STRING !== $t[0] || ! isset( $functions[ $t[1] ] ) ) {
			continue;
		}
		// Skip method calls and definitions (->__(), ::__(), function __()).
		$prev = $i - 1;
		while ( $prev >= 0 && is_array( $tokens[ $prev ] ) && T_WHITESPACE === $tokens[ $prev ][0] ) {
			--$prev;
		}
		if ( $prev >= 0 && is_array( $tokens[ $prev ] ) && in_array( $tokens[ $prev ][0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION ), true ) ) {
			continue;
		}
		$j = $i + 1;
		while ( $j < $count && is_array( $tokens[ $j ] ) && T_WHITESPACE === $tokens[ $j ][0] ) {
			++$j;
		}
		if ( '(' !== ( $tokens[ $j ] ?? '' ) ) {
			continue;
		}
		// Collect arguments until the matching parenthesis.
		$args  = array();
		$cur   = array();
		$depth = 0;
		for ( $k = $j; $k < $count; $k++ ) {
			$tk = $tokens[ $k ];
			if ( '(' === $tk || '[' === $tk ) {
				++$depth;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $tk || ']' === $tk ) {
				--$depth;
				if ( 0 === $depth ) {
					$args[] = $cur;
					break;
				}
			} elseif ( ',' === $tk && 1 === $depth ) {
				$args[] = $cur;
				$cur    = array();
				continue;
			}
			if ( is_array( $tk ) && in_array( $tk[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$cur[] = $tk;
		}
		$spec = $functions[ $t[1] ];
		$get  = static function ( string $name ) use ( $args, $spec ) {
			if ( ! isset( $spec[ $name ] ) || ! isset( $args[ $spec[ $name ] ] ) ) {
				return null;
			}
			$a = $args[ $spec[ $name ] ];
			if ( 1 === count( $a ) && is_array( $a[0] ) && T_CONSTANT_ENCAPSED_STRING === $a[0][0] ) {
				return khs_literal( $a[0][1] );
			}
			return false;
		};
		$dom = $get( 'domain' );
		if ( $dom !== $domain ) {
			if ( false === $dom ) {
				$skipped[] = "$rel:$line domain is not a literal";
			}
			continue;
		}
		$msgid  = $get( 'msgid' );
		$plural = $get( 'plural' );
		$ctx    = $get( 'context' );
		if ( ! is_string( $msgid ) || false === $plural || false === $ctx ) {
			$skipped[] = "$rel:$line message is not a literal";
			continue;
		}
		$key = ( is_string( $ctx ) ? $ctx : '' ) . "\x04" . $msgid;
		if ( ! isset( $entries[ $key ] ) ) {
			$entries[ $key ] = array(
				'msgid'   => $msgid,
				'plural'  => is_string( $plural ) ? $plural : null,
				'context' => is_string( $ctx ) ? $ctx : null,
				'refs'    => array(),
				'comment' => '',
			);
		}
		$entries[ $key ]['refs'][] = "$rel:$line";
		if ( '' !== $comment && '' === $entries[ $key ]['comment'] && $line - 3 <= $line ) {
			$entries[ $key ]['comment'] = $comment;
		}
		$comment = '';
	}
}

$out   = array();
$out[] = '# Copyright (C) KhabarSEO contributors';
$out[] = '# This file is distributed under the GPL-2.0-or-later.';
$out[] = 'msgid ""';
$out[] = 'msgstr ""';
$out[] = '"Project-Id-Version: KhabarSEO 0.1.0\\n"';
$out[] = '"Report-Msgid-Bugs-To: https://github.com/OWNER/khabar-seo/issues\\n"';
$out[] = '"MIME-Version: 1.0\\n"';
$out[] = '"Content-Type: text/plain; charset=UTF-8\\n"';
$out[] = '"Content-Transfer-Encoding: 8bit\\n"';
$out[] = '"Language-Team: LANGUAGE <LL@li.org>\\n"';
$out[] = '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"';
$out[] = '"X-Domain: ' . $domain . '\\n"';
$out[] = '';

foreach ( $entries as $e ) {
	if ( '' !== $e['comment'] ) {
		$out[] = '#. ' . $e['comment'];
	}
	$out[] = '#: ' . implode( ' ', array_unique( $e['refs'] ) );
	if ( preg_match( '/%(?:\d+\$)?[sd]/', $e['msgid'] ) ) {
		$out[] = '#, php-format';
	}
	if ( null !== $e['context'] ) {
		$out[] = 'msgctxt ' . khs_po( $e['context'] );
	}
	$out[] = 'msgid ' . khs_po( $e['msgid'] );
	if ( null !== $e['plural'] ) {
		$out[] = 'msgid_plural ' . khs_po( $e['plural'] );
		$out[] = 'msgstr[0] ""';
		$out[] = 'msgstr[1] ""';
	} else {
		$out[] = 'msgstr ""';
	}
	$out[] = '';
}

file_put_contents( $root . '/languages/khabar-seo.pot', implode( "\n", $out ) );
fwrite( STDERR, count( $entries ) . " strings written to languages/khabar-seo.pot\n" );
foreach ( $skipped as $s ) {
	fwrite( STDERR, "skipped: $s\n" );
}
exit( $skipped ? 2 : 0 );
