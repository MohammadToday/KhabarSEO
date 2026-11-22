#!/usr/bin/env php
<?php
/**
 * Merges languages/khabar-seo.pot into every languages/*.po (like msgmerge,
 * without gettext tools). Existing translations are kept by context + msgid;
 * new strings get an empty msgstr; strings no longer in the POT are dropped.
 *
 * Usage: php bin/merge-po.php [path/to/file.po ...]
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

// phpcs:disable -- Developer CLI script.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

/**
 * Parses a PO/POT file into ordered entries.
 *
 * @return array<int, array{comments:string[],ctx:?string,id:string,plural:?string,str:array<int,string>}>
 */
function khs_po_entries( string $path ): array {
	$entries = array();
	$cur     = null;
	$field   = null;
	$new     = static function (): array {
		return array( 'comments' => array(), 'ctx' => null, 'id' => null, 'plural' => null, 'str' => array() );
	};
	$unq = static function ( string $s ): string {
		$s = trim( $s );
		return stripcslashes( substr( $s, 1, -1 ) );
	};
	foreach ( file( $path, FILE_IGNORE_NEW_LINES ) as $line ) {
		$line = rtrim( $line, "\r" );
		if ( '' === trim( $line ) ) {
			if ( null !== $cur && null !== $cur['id'] ) {
				$entries[] = $cur;
			}
			$cur   = null;
			$field = null;
			continue;
		}
		if ( null === $cur ) {
			$cur = $new();
		}
		if ( '#' === $line[0] ) {
			if ( 0 !== strpos( $line, '#~' ) ) {
				$cur['comments'][] = $line;
			}
			continue;
		}
		if ( preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr)(?:\[(\d+)\])?\s+(".*")$/', $line, $m ) ) {
			$value = $unq( $m[3] );
			if ( 'msgctxt' === $m[1] ) {
				$cur['ctx'] = $value;
				$field      = array( 'ctx', null );
			} elseif ( 'msgid' === $m[1] ) {
				$cur['id'] = $value;
				$field     = array( 'id', null );
			} elseif ( 'msgid_plural' === $m[1] ) {
				$cur['plural'] = $value;
				$field         = array( 'plural', null );
			} else {
				$index                = '' === $m[2] ? 0 : (int) $m[2];
				$cur['str'][ $index ] = $value;
				$field                = array( 'str', $index );
			}
			continue;
		}
		if ( '"' === $line[0] && null !== $field ) {
			if ( 'str' === $field[0] ) {
				$cur['str'][ $field[1] ] .= $unq( $line );
			} else {
				$cur[ $field[0] ] .= $unq( $line );
			}
		}
	}
	if ( null !== $cur && null !== $cur['id'] ) {
		$entries[] = $cur;
	}
	return $entries;
}

/**
 * Quotes a PO string.
 */
function khs_po_quote( string $s ): string {
	return '"' . str_replace( array( '\\', '"', "\n", "\t" ), array( '\\\\', '\\"', '\\n', '\\t' ), $s ) . '"';
}

$root  = dirname( __DIR__ );
$pot   = khs_po_entries( $root . '/languages/khabar-seo.pot' );
$files = array_slice( $argv, 1 );
if ( ! $files ) {
	$files = glob( $root . '/languages/*.po' ) ?: array();
}

$pot_header = array_shift( $pot );
preg_match( '/Project-Id-Version: ([^\n]*)/', $pot_header['str'][0] ?? '', $version );

foreach ( $files as $file ) {
	$old    = khs_po_entries( $file );
	$header = array_shift( $old );
	$known  = array();
	foreach ( $old as $e ) {
		$known[ (string) $e['ctx'] . "\x04" . $e['id'] ] = $e['str'];
	}
	$head = $header['str'][0] ?? '';
	if ( $version ) {
		$head = preg_replace( '/Project-Id-Version: [^\n]*/', 'Project-Id-Version: ' . $version[1], $head );
	}
	$out   = $header['comments'];
	$out[] = 'msgid ""';
	$out[] = 'msgstr ""';
	foreach ( explode( "\n", rtrim( $head, "\n" ) ) as $h ) {
		$out[] = khs_po_quote( $h . "\n" );
	}
	$out[]   = '';
	$missing = 0;
	foreach ( $pot as $e ) {
		$key = (string) $e['ctx'] . "\x04" . $e['id'];
		$str = $known[ $key ] ?? array();
		if ( '' === implode( '', $str ) ) {
			++$missing;
		}
		foreach ( $e['comments'] as $c ) {
			$out[] = $c;
		}
		if ( null !== $e['ctx'] ) {
			$out[] = 'msgctxt ' . khs_po_quote( $e['ctx'] );
		}
		$out[] = 'msgid ' . khs_po_quote( $e['id'] );
		if ( null !== $e['plural'] ) {
			$out[] = 'msgid_plural ' . khs_po_quote( $e['plural'] );
			$out[] = 'msgstr[0] ' . khs_po_quote( $str[0] ?? '' );
			$out[] = 'msgstr[1] ' . khs_po_quote( $str[1] ?? '' );
		} else {
			$out[] = 'msgstr ' . khs_po_quote( $str[0] ?? '' );
		}
		$out[] = '';
	}
	file_put_contents( $file, implode( "\n", $out ) );
	fwrite( STDERR, sprintf( "%s: %d strings, %d untranslated\n", basename( $file ), count( $pot ), $missing ) );
}
