<?php
/**
 * Tabbed settings panel. Every tab except Tools and Status is generated from
 * the schema; saving only touches the fields that were rendered on the tab.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Core\Capabilities;
use KhabarSEO\Core\Sanitizer;
use KhabarSEO\Core\Schema;
use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Settings page.
 */
final class SettingsPage {

	/**
	 * Registers the save handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_khs_save_settings', array( $this, 'handle_save' ) );
	}

	/**
	 * Current tab from the query string, limited to visible tabs.
	 *
	 * @return string
	 */
	private function current_tab(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection, validated against a whitelist.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'setup';
		$tabs = Schema::visible_tabs();
		return isset( $tabs[ $tab ] ) ? $tab : (string) array_key_first( $tabs );
	}

	/**
	 * Renders the page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'khabar-seo' ), '', array( 'response' => 403 ) );
		}
		$tabs = Schema::visible_tabs();
		$tab  = $this->current_tab();

		echo '<div class="wrap khs-wrap">';
		echo '<h1>' . esc_html__( 'KhabarSEO', 'khabar-seo' ) . '</h1>';
		Flash::render();

		echo '<nav class="nav-tab-wrapper khs-tabs" aria-label="' . esc_attr__( 'KhabarSEO sections', 'khabar-seo' ) . '">';
		foreach ( $tabs as $id => $label ) {
			printf(
				'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
				esc_url(
					add_query_arg(
						array(
							'page' => Menu::SLUG,
							'tab'  => $id,
						),
						admin_url( 'admin.php' )
					)
				),
				$id === $tab ? ' nav-tab-active' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static class.
				$id === $tab ? ' aria-current="page"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attribute.
				esc_html( $label )
			);
		}
		echo '</nav>';

		if ( 'tools' === $tab ) {
			( new ToolsPage() )->render_tab();
		} elseif ( 'status' === $tab ) {
			( new StatusPage() )->render_tab();
		} else {
			$this->render_form( $tab );
		}
		echo '</div>';
	}

	/**
	 * Renders the schema-driven form of a tab.
	 *
	 * @param string $tab Tab id.
	 * @return void
	 */
	private function render_form( string $tab ): void {
		$sections = array();
		foreach ( Schema::tab_fields( $tab ) as $key ) {
			$sections[ Schema::field( $key )['section'] ][] = $key;
		}
		$labels = Schema::section_labels();

		echo '<p class="khs-legend">' . esc_html__( 'Choices marked "Recommended" are the best fit for most news sites.', 'khabar-seo' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khs-form">';
		echo '<input type="hidden" name="action" value="khs_save_settings" />';
		echo '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />';
		wp_nonce_field( 'khs_save_settings' );

		foreach ( $sections as $section => $keys ) {
			echo '<section class="khs-section">';
			echo '<h2>' . esc_html( $labels[ $section ] ?? $section ) . '</h2>';
			foreach ( $keys as $key ) {
				FieldRenderer::render( $key );
			}
			echo '</section>';
		}
		if ( 'indexing' === $tab ) {
			echo '<p class="description">' . esc_html__( 'Internal search results are always set to noindex, follow.', 'khabar-seo' ) . '</p>';
		}
		submit_button();
		echo '</form>';
	}

	/**
	 * Reads and sanitizes the submitted fields. Callers must verify the nonce first.
	 *
	 * @param string[] $allowed Keys that may be processed.
	 * @return array{values:array<string,mixed>,errors:array<string,string>}
	 */
	public static function collect( array $allowed ): array {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Every caller verifies the nonce before calling.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Unslashed here; sanitized per field by Sanitizer using the schema.
		$present = isset( $_POST['khs_fields'] ) && is_array( $_POST['khs_fields'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['khs_fields'] ) ) : array();
		$input   = isset( $_POST['khs'] ) && is_array( $_POST['khs'] ) ? wp_unslash( $_POST['khs'] ) : array();
		// phpcs:enable
		$keys = array_values( array_intersect( $allowed, $present ) );
		return Sanitizer::sanitize_many( $input, $keys );
	}

	/**
	 * Human message for a validation error.
	 *
	 * @param string $key  Field key.
	 * @param string $code Error code.
	 * @return string
	 */
	public static function error_message( string $key, string $code ): string {
		$label = Schema::ui( $key )['label'];
		switch ( $code ) {
			case 'format':
				/* translators: %s: field label. */
				return sprintf( __( '%s: the value has an invalid format and was cleared.', 'khabar-seo' ), $label );
			case 'range':
				/* translators: %s: field label. */
				return sprintf( __( '%s: the number was outside the allowed range and was adjusted.', 'khabar-seo' ), $label );
			case 'length':
				/* translators: %s: field label. */
				return sprintf( __( '%s: the text was too long and was shortened.', 'khabar-seo' ), $label );
		}
		/* translators: %s: field label. */
		return sprintf( __( '%s: the value was not valid and the default was used.', 'khabar-seo' ), $label );
	}

	/**
	 * Saves one tab.
	 *
	 * @return void
	 */
	public function handle_save(): void {
		check_admin_referer( 'khs_save_settings' );
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'khabar-seo' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : 'setup';
		if ( ! isset( Schema::visible_tabs()[ $tab ] ) || in_array( $tab, array( 'tools', 'status' ), true ) ) {
			$tab = 'setup';
		}

		$result = self::collect( Schema::tab_fields( $tab ) );
		foreach ( $result['errors'] as $key => $code ) {
			Flash::add( 'warning', self::error_message( $key, $code ) );
		}
		$saved = Settings::update( $result['values'] );
		if ( $saved['ok'] ) {
			Flash::add( 'success', __( 'Settings saved.', 'khabar-seo' ) );
		} elseif ( 'size' === $saved['error'] ) {
			Flash::add( 'error', __( 'Settings were not saved: they exceed the 8 KB limit for autoloaded data.', 'khabar-seo' ) );
		} else {
			Flash::add( 'error', __( 'Settings could not be saved.', 'khabar-seo' ) );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => Menu::SLUG,
					'tab'  => $tab,
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
