<?php
/**
 * Renders schema fields with native WordPress markup and classes.
 * Every choice shows a Recommended badge and a short plain-language guide.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Core\Sanitizer;
use KhabarSEO\Core\Schema;
use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Field renderer.
 */
final class FieldRenderer {

	/**
	 * Prints one field.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Current value, null = read from settings.
	 * @return void
	 */
	public static function render( string $key, $value = null ): void {
		$spec = Schema::field( $key );
		if ( null === $spec || $spec['internal'] ) {
			return;
		}
		$ui    = Schema::ui( $key );
		$value = null === $value ? Settings::get( $key ) : $value;
		$id    = 'khs-' . str_replace( array( '.', '_' ), '-', $key );

		$depends = '';
		if ( is_array( $spec['depends'] ) && $spec['depends'] ) {
			$dep_key = (string) array_key_first( $spec['depends'] );
			$depends = sprintf( ' data-khs-depends-key="%1$s" data-khs-depends-value="%2$s"', esc_attr( $dep_key ), esc_attr( (string) $spec['depends'][ $dep_key ] ) );
		}

		printf( '<div class="khs-field khs-field--%1$s"%2$s>', esc_attr( $spec['type'] ), $depends ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		printf( '<input type="hidden" name="khs_fields[]" value="%s" />', esc_attr( $key ) );

		switch ( $spec['type'] ) {
			case 'choice':
				self::choice( $key, $spec, $ui, $value, $id );
				break;
			case 'bool':
				self::bool( $key, $spec, $ui, (bool) $value, $id );
				break;
			case 'post_types':
				self::post_types( $key, $ui, (array) $value, $id );
				break;
			case 'media':
				self::media( $key, $ui, (int) $value, $id );
				break;
			case 'int':
				self::label( $ui['label'], $id, false );
				printf(
					'<input type="number" class="small-text" id="%1$s" name="khs[%2$s]" value="%3$d"%4$s%5$s aria-describedby="%1$s-help" />',
					esc_attr( $id ),
					esc_attr( $key ),
					(int) $value,
					null !== $spec['min'] ? ' min="' . (int) $spec['min'] . '"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Integer attribute.
					null !== $spec['max'] ? ' max="' . (int) $spec['max'] . '"' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Integer attribute.
				);
				self::texts( $ui, $id );
				self::recommended_value( $spec );
				break;
			case 'template':
				self::label( $ui['label'], $id, false );
				printf(
					'<input type="text" class="large-text code khs-template" id="%1$s" name="khs[%2$s]" value="%3$s" dir="auto" maxlength="%4$d" aria-describedby="%1$s-help" />',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( (string) $value ),
					(int) ( $spec['max'] ?? 300 )
				);
				self::texts( $ui, $id );
				self::recommended_value( $spec );
				break;
			case 'tpl_map':
				self::template_map( $key, $spec, $ui, is_array( $value ) ? $value : array(), $id );
				break;
			case 'list':
				self::label( $ui['label'], $id, false );
				printf(
					'<textarea class="regular-text code" rows="5" id="%1$s" name="khs[%2$s]" dir="ltr" aria-describedby="%1$s-help">%3$s</textarea>',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_textarea( implode( "\n", array_map( 'strval', (array) $value ) ) )
				);
				self::texts( $ui, $id );
				break;
			default:
				$ltr = in_array( $spec['type'], array( 'lang', 'locale', 'handle' ), true );
				self::label( $ui['label'], $id, $spec['required'] );
				printf(
					'<input type="text" class="regular-text" id="%1$s" name="khs[%2$s]" value="%3$s"%4$s%5$s aria-describedby="%1$s-help" />',
					esc_attr( $id ),
					esc_attr( $key ),
					esc_attr( (string) $value ),
					$ltr ? ' dir="ltr"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute.
					(int) $spec['max'] > 0 ? ' maxlength="' . (int) $spec['max'] . '"' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Integer attribute.
				);
				self::texts( $ui, $id );
		}
		echo '</div>';
	}

	/**
	 * "Recommended: value" line for numbers and templates.
	 *
	 * @param array $spec Definition.
	 * @return void
	 */
	private static function recommended_value( array $spec ): void {
		if ( null === $spec['recommended'] || '' === (string) $spec['recommended'] ) {
			return;
		}
		/* translators: %s: recommended value. */
		$text = sprintf( __( 'Recommended: %s', 'khabar-seo' ), (string) $spec['recommended'] );
		printf( '<p class="khs-recommended"><span class="khs-badge" dir="auto">%s</span></p>', esc_html( $text ) );
	}

	/**
	 * One template input per post type or taxonomy.
	 *
	 * @param string $key   Field key.
	 * @param array  $spec  Definition.
	 * @param array  $ui    UI strings.
	 * @param array  $value Stored overrides (slug => template).
	 * @param string $id    Base id.
	 * @return void
	 */
	private static function template_map( string $key, array $spec, array $ui, array $value, string $id ): void {
		$objects = 'taxonomies' === $spec['source'] ? Sanitizer::selectable_taxonomies() : Sanitizer::selectable_post_types();
		echo '<fieldset class="khs-choice khs-map">';
		printf( '<legend class="khs-label">%s</legend>', esc_html( $ui['label'] ) );
		printf( '<p class="description" id="%1$s-help">%2$s</p>', esc_attr( $id ), esc_html( $ui['help'] ) );
		echo '<table class="form-table khs-map__table" role="presentation"><tbody>';
		foreach ( $objects as $slug => $object ) {
			$input_id = $id . '-' . sanitize_html_class( $slug );
			printf(
				'<tr><th scope="row"><label for="%1$s">%2$s</label> <code>%3$s</code></th><td><input type="text" class="large-text code khs-template" id="%1$s" name="khs[%4$s][%3$s]" value="%5$s" dir="auto" maxlength="%6$d" aria-describedby="%7$s-help" /></td></tr>',
				esc_attr( $input_id ),
				esc_html( $object->labels->name ),
				esc_attr( $slug ),
				esc_attr( $key ),
				esc_attr( isset( $value[ $slug ] ) && is_string( $value[ $slug ] ) ? $value[ $slug ] : (string) $spec['item_default'] ),
				(int) ( $spec['max'] ?? 300 ),
				esc_attr( $id )
			);
		}
		echo '</tbody></table>';
		if ( '' !== $ui['guide'] ) {
			printf( '<p class="khs-guide">%s</p>', esc_html( $ui['guide'] ) );
		}
		/* translators: %s: default template. */
		$text = sprintf( __( 'Recommended: %s', 'khabar-seo' ), (string) $spec['item_default'] );
		printf( '<p class="khs-recommended"><span class="khs-badge" dir="auto">%s</span></p>', esc_html( $text ) );
		echo '</fieldset>';
	}

	/**
	 * Human-readable value for the wizard review.
	 *
	 * @param string $key Field key.
	 * @return string
	 */
	public static function value_label( string $key ): string {
		$spec  = Schema::field( $key );
		$value = Settings::get( $key );
		if ( null === $spec ) {
			return '';
		}
		switch ( $spec['type'] ) {
			case 'choice':
				$ui = Schema::ui( $key );
				return (string) ( $ui['options'][ $value ]['label'] ?? $value );
			case 'bool':
				return $value ? __( 'Yes', 'khabar-seo' ) : __( 'No', 'khabar-seo' );
			case 'post_types':
				$types = Sanitizer::selectable_post_types();
				$names = array();
				foreach ( (array) $value as $slug ) {
					$names[] = isset( $types[ $slug ] ) ? $types[ $slug ]->labels->name : $slug;
				}
				return $names ? implode( ', ', $names ) : __( 'None', 'khabar-seo' );
			case 'media':
				return (int) $value > 0 ? __( 'Image selected', 'khabar-seo' ) : __( 'None', 'khabar-seo' );
		}
		return '' === (string) $value ? '—' : (string) $value;
	}

	/**
	 * Label element.
	 *
	 * @param string $text     Label text.
	 * @param string $id       Control id.
	 * @param bool   $required Whether to show the required mark.
	 * @return void
	 */
	private static function label( string $text, string $id, bool $required ): void {
		printf(
			'<label class="khs-label" for="%1$s">%2$s%3$s</label>',
			esc_attr( $id ),
			esc_html( $text ),
			$required ? ' <span class="khs-required" aria-hidden="true">*</span>' : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static markup.
		);
	}

	/**
	 * Help and guide paragraphs.
	 *
	 * @param array  $ui Field UI strings.
	 * @param string $id Control id.
	 * @return void
	 */
	private static function texts( array $ui, string $id ): void {
		printf( '<p class="description" id="%1$s-help">%2$s</p>', esc_attr( $id ), esc_html( $ui['help'] ) );
		if ( '' !== $ui['guide'] ) {
			printf( '<p class="khs-guide">%s</p>', esc_html( $ui['guide'] ) );
		}
	}

	/**
	 * Recommended badge.
	 *
	 * @param string $text Badge text.
	 * @return string
	 */
	private static function badge( string $text ): string {
		return '<span class="khs-badge">' . esc_html( $text ) . '</span>';
	}

	/**
	 * Radio group.
	 *
	 * @param string $key   Field key.
	 * @param array  $spec  Definition.
	 * @param array  $ui    UI strings.
	 * @param mixed  $value Current value.
	 * @param string $id    Base id.
	 * @return void
	 */
	private static function choice( string $key, array $spec, array $ui, $value, string $id ): void {
		echo '<fieldset class="khs-choice">';
		printf( '<legend class="khs-label">%s</legend>', esc_html( $ui['label'] ) );
		printf( '<p class="description" id="%1$s-help">%2$s</p>', esc_attr( $id ), esc_html( $ui['help'] ) );
		if ( '' !== $ui['guide'] ) {
			printf( '<p class="khs-guide">%s</p>', esc_html( $ui['guide'] ) );
		}
		foreach ( $spec['options'] as $index => $option ) {
			$option_ui = $ui['options'][ $option ] ?? array(
				'label' => (string) $option,
				'guide' => '',
			);
			$option_id = $id . '-' . $index;
			printf(
				'<div class="khs-option"><label for="%1$s"><input type="radio" id="%1$s" name="khs[%2$s]" value="%3$s"%4$s%5$s /> <span class="khs-option__label">%6$s</span></label>%7$s',
				esc_attr( $option_id ),
				esc_attr( $key ),
				esc_attr( (string) $option ),
				checked( (string) $value, (string) $option, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper returns a safe attribute.
				'' !== ( $option_ui['guide'] ?? '' ) ? ' aria-describedby="' . esc_attr( $option_id ) . '-guide"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped inline.
				esc_html( $option_ui['label'] ),
				( null !== $spec['recommended'] && (string) $spec['recommended'] === (string) $option ) ? ' ' . self::badge( __( 'Recommended', 'khabar-seo' ) ) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Badge is escaped.
			);
			if ( ! empty( $option_ui['guide'] ) ) {
				printf( '<p class="khs-guide khs-guide--option" id="%1$s-guide">%2$s</p>', esc_attr( $option_id ), esc_html( $option_ui['guide'] ) );
			}
			echo '</div>';
		}
		echo '</fieldset>';
	}

	/**
	 * Single checkbox.
	 *
	 * @param string $key   Field key.
	 * @param array  $spec  Definition.
	 * @param array  $ui    UI strings.
	 * @param bool   $value Current value.
	 * @param string $id    Control id.
	 * @return void
	 */
	private static function bool( string $key, array $spec, array $ui, bool $value, string $id ): void {
		$badge = '';
		if ( true === $spec['recommended'] ) {
			$badge = ' ' . self::badge( __( 'Recommended', 'khabar-seo' ) );
		} elseif ( false === $spec['recommended'] ) {
			$badge = ' ' . self::badge( __( 'Recommended: off', 'khabar-seo' ) );
		}
		printf(
			'<label class="khs-label" for="%1$s"><input type="checkbox" id="%1$s" name="khs[%2$s]" value="1"%3$s aria-describedby="%1$s-help" /> %4$s%5$s</label>',
			esc_attr( $id ),
			esc_attr( $key ),
			checked( $value, true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper returns a safe attribute.
			esc_html( $ui['label'] ),
			$badge // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Badge is escaped.
		);
		self::texts( $ui, $id );
	}

	/**
	 * Post type checkboxes.
	 *
	 * @param string $key   Field key.
	 * @param array  $ui    UI strings.
	 * @param array  $value Selected slugs.
	 * @param string $id    Base id.
	 * @return void
	 */
	private static function post_types( string $key, array $ui, array $value, string $id ): void {
		echo '<fieldset class="khs-choice">';
		printf( '<legend class="khs-label">%s</legend>', esc_html( $ui['label'] ) );
		printf( '<p class="description" id="%1$s-help">%2$s</p>', esc_attr( $id ), esc_html( $ui['help'] ) );
		foreach ( Sanitizer::selectable_post_types() as $slug => $type ) {
			printf(
				'<div class="khs-option"><label><input type="checkbox" name="khs[%1$s][]" value="%2$s"%3$s /> %4$s <code>%2$s</code></label></div>',
				esc_attr( $key ),
				esc_attr( $slug ),
				checked( in_array( $slug, $value, true ), true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper returns a safe attribute.
				esc_html( $type->labels->name )
			);
		}
		if ( '' !== $ui['guide'] ) {
			printf( '<p class="khs-guide">%s</p>', esc_html( $ui['guide'] ) );
		}
		echo '</fieldset>';
	}

	/**
	 * Media picker (image attachment id).
	 *
	 * @param string $key   Field key.
	 * @param array  $ui    UI strings.
	 * @param int    $value Attachment id.
	 * @param string $id    Control id.
	 * @return void
	 */
	private static function media( string $key, array $ui, int $value, string $id ): void {
		self::label( $ui['label'], $id . '-button', false );
		$thumb = $value > 0 ? wp_get_attachment_image( $value, array( 150, 150 ), false, array( 'class' => 'khs-media__img' ) ) : '';
		printf(
			'<div class="khs-media" data-khs-media data-title="%1$s" data-button="%2$s">',
			esc_attr__( 'Choose an image', 'khabar-seo' ),
			esc_attr__( 'Use this image', 'khabar-seo' )
		);
		printf( '<input type="hidden" id="%1$s" name="khs[%2$s]" value="%3$d" />', esc_attr( $id ), esc_attr( $key ), absint( $value ) );
		printf( '<div class="khs-media__preview">%s</div>', wp_kses_post( $thumb ) );
		printf( '<button type="button" class="button khs-media__select" id="%1$s-button">%2$s</button> ', esc_attr( $id ), esc_html__( 'Select image', 'khabar-seo' ) );
		printf( '<button type="button" class="button-link khs-media__remove"%1$s>%2$s</button>', $value > 0 ? '' : ' hidden', esc_html__( 'Remove image', 'khabar-seo' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute.
		echo '</div>';
		self::texts( $ui, $id );
	}
}
