<?php
/**
 * Presets. "News site, recommended" applies every schema "recommended" value
 * to the fields the wizard did not ask about. Wizard answers are never overwritten.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Presets.
 */
final class Presets {

	/**
	 * Values of the "News site, recommended" preset, excluding wizard-answered fields.
	 *
	 * @return array<string, mixed>
	 */
	public static function news_recommended(): array {
		$values = array();
		foreach ( Schema::definitions() as $key => $spec ) {
			if ( $spec['internal'] || 0 !== $spec['wizard'] || null === $spec['recommended'] ) {
				continue;
			}
			$values[ $key ] = $spec['recommended'];
		}
		return $values;
	}

	/**
	 * Applies the preset and marks the wizard as finished.
	 *
	 * @return array{ok:bool,error:string}
	 */
	public static function apply_news_recommended(): array {
		$values                      = self::news_recommended();
		$values['state.wizard_done'] = true;
		$values['state.wizard_time'] = time();
		return Settings::update( $values, true );
	}
}
