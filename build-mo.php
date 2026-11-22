#!/usr/bin/env php
<?php
/**
 * Compiles .po files in languages/ to .mo files (no gettext tools needed).
 *
 * Usage: php bin/build-mo.php [path/to/file.po ...]
 *
 * Skips untranslated and fuzzy entries, like msgfmt. Prints a coverage line per file.
 *
 * @package KhabarSEO
 */

// phpcs:disable -- Developer CLI script.

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

/**
 * Unquotes a PO string fragment.
 */
function khs_unquote( string $s ): string {
	$s = trim( $s );
	if ( '' === $s || '"' !== $s[0] ) {
		return '';
	}
	return stripcslashes( substr( $s, 1, -1 ) );
}

/**
 * Parses a PO file into entries: key => value (both byte strings as stored in MO).
 *
 * @return array{0:array<string,string>,1:int,2:int} Entries, translated count, total count.
 */
function khs_parse_po( string $path ): array {
	$lines   = file( $path, FILE_IGNORE_NEW_LINES );
	$entries = array();
	$cur     = null;
	$field   = null;
	$fuzzy   = false;
	$total   = 0;
	$done    = 0;

	$flush = static function () use ( &$cur, &$entries, &$fuzzy, &$total, &$done ) {
		if ( null === $cur || ! isset( $cur['msgid'] ) ) {
			$cur = null;
			return;
		}
		$is_header = '' === $cur['msgid'] && '' === ( $cur['msgctxt'] ?? '' );
		$key       = ( isset( $cur['msgctxt'] ) ? $cur['msgctxt'] . "\x04" : '' ) . $cur['msgid'];
		if ( isset( $cur['msgid_plural'] ) ) {
			$key .= "\0" . $cur['msgid_plural'];
			ksort( $cur['plural'] );
			$value = implode( "\0", $cur['plural'] );
			$has   = '' !== implode( '', $cur['plural'] );
		} else {
			$value = $cur['msgstr'] ?? '';
			$has   = '' !== $value;
		}
		if ( ! $is_header ) {
			++$total;
		}
		if ( $has && ( ! $fuzzy || $is_header ) ) {
			$entries[ $key ] = $value;
			if ( ! $is_header ) {
				++$done;
			}
		}
		$cur   = null;
		$fuzzy = false;
	};

	foreach ( $lines as $line ) {
		$line = rtrim( $line );
		if ( '' === $line ) {
			$flush();
			$field = null;
			continue;
		}
		if ( '#' === $line[0] ) {
			if ( 0 === strpos( $line, '#,' ) && false !== strpos( $line, 'fuzzy' ) ) {
				$fuzzy = true;
			}
			if ( 0 === strpos( $line, '#~' ) ) {
				continue;
			}
			continue;
		}
		if ( preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr)(?:\[(\d+)\])?\s+(".*")$/', $line, $m ) ) {
			if ( 'msgid' === $m[1] && null !== $cur && isset( $cur['msgstr'] ) || ( 'msgctxt' === $m[1] && null !== $cur && isset( $cur['msgid'] ) ) ) {
				$flush();
			}
			if ( null === $cur ) {
				$cur = array( 'plural' => array() );
			}
			$value = khs_unquote( $m[3] );
			if ( 'msgstr' === $m[1] && '' !== $m[2] ) {
				$cur['plural'][ (int) $m[2] ] = $value;
				$field                        = array( 'plural', (int) $m[2] );
			} else {
				$cur[ $m[1] ] = $value;
				$field        = array( $m[1], null );
			}
			continue;
		}
		if ( '"' === $line[0] && null !== $field && null !== $cur ) {
			if ( null !== $field[1] ) {
				$cur['plural'][ $field[1] ] .= khs_unquote( $line );
			} else {
				$cur[ $field[0] ] .= khs_unquote( $line );
			}
		}
	}
	$flush();
	return array( $entries, $done, $total );
}

/**
 * Builds the binary MO content.
 */
function khs_build_mo( array $entries ): string {
	ksort( $entries, SORT_STRING );
	$count   = count( $entries );
	$orig    = '';
	$trans   = '';
	$otable  = array();
	$ttable  = array();
	foreach ( $entries as $key => $value ) {
		$otable[] = array( strlen( $key ), strlen( $orig ) );
		$orig    .= $key . "\0";
		$ttable[] = array( strlen( $value ), strlen( $trans ) );
		$trans   .= $value . "\0";
	}
	$header_size = 28;
	$o_offset    = $header_size;
	$t_offset    = $o_offset + 8 * $count;
	$o_data      = $t_offset + 8 * $count;
	$t_data      = $o_data + strlen( $orig );
	$out         = pack( 'V7', 0x950412de, 0, $count, $o_offset, $t_offset, 0, 0 );
	foreach ( $otable as $row ) {
		$out .= pack( 'V2', $row[0], $o_data + $row[1] );
	}
	foreach ( $ttable as $row ) {
		$out .= pack( 'V2', $row[0], $t_data + $row[1] );
	}
	return $out . $orig . $trans;
}

$files = array_slice( $argv, 1 );
if ( ! $files ) {
	$files = glob( dirname( __DIR__ ) . '/languages/*.po' ) ?: array();
}
$status = 0;
foreach ( $files as $po ) {
	list( $entries, $done, $total ) = khs_parse_po( $po );
	$mo = preg_replace( '/\.po$/', '.mo', $po );
	file_put_contents( $mo, khs_build_mo( $entries ) );
	fwrite( STDERR, sprintf( "%s: %d/%d translated -> %s\n", basename( $po ), $done, $total, basename( $mo ) ) );
	if ( $done < $total ) {
		$status = 3;
	}
}
exit( $status );
