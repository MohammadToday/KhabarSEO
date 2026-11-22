<?php
/**
 * Versioned settings migrations.
 *
 * To change the schema: bump Schema::VERSION and add a step keyed by the new
 * version number. Steps receive the nested stored array and must return it.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Migration runner.
 */
final class Migrations {

	/**
	 * Steps keyed by the schema version they migrate TO.
	 *
	 * @return array<int, callable>
	 */
	private static function steps(): array {
		return array(
			1 => array( self::class, 'to_v1' ),
		);
	}

	/**
	 * Runs all steps above $from on a nested stored array.
	 *
	 * @param array $stored Nested stored settings.
	 * @param int   $from   Version the data currently has.
	 * @return array
	 */
	public static function run( array $stored, int $from ): array {
		$steps = self::steps();
		ksort( $steps );
		foreach ( $steps as $version => $callback ) {
			if ( $version > $from ) {
				$stored = (array) call_user_func( $callback, $stored );
			}
		}
		$stored['state']['schema'] = Schema::VERSION;
		$stored['state']['plugin'] = defined( 'KHS_VERSION' ) ? KHS_VERSION : '';
		return $stored;
	}

	/**
	 * Migrates an imported flat map (key => value) to the current schema.
	 * No renamed keys exist yet, so this is the extension point for future ones.
	 *
	 * @param array $flat Flat settings from an export.
	 * @param int   $from Schema version of the export.
	 * @return array
	 */
	public static function migrate_flat( array $flat, int $from ): array {
		return (array) apply_filters( 'khs_migrate_import', $flat, $from );
	}

	/**
	 * Baseline: make sure the state group exists.
	 *
	 * @param array $stored Nested stored settings.
	 * @return array
	 */
	public static function to_v1( array $stored ): array {
		if ( ! isset( $stored['state'] ) || ! is_array( $stored['state'] ) ) {
			$stored['state'] = array();
		}
		return $stored;
	}
}
