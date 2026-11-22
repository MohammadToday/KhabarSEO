<?php
/**
 * Tools tab: export, import, reset, wizard link.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Core\Capabilities;
use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Tools page.
 */
final class ToolsPage {

	private const MAX_IMPORT_BYTES = 262144;

	/**
	 * Registers the handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_khs_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_khs_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_khs_reset', array( $this, 'handle_reset' ) );
	}

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render_tab(): void {
		$action = esc_url( admin_url( 'admin-post.php' ) );

		echo '<section class="khs-section"><h2>' . esc_html__( 'Setup wizard', 'khabar-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Run the guided setup again. Your current answers are shown and nothing is lost.', 'khabar-seo' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( admin_url( 'admin.php?page=' . Menu::WIZARD_SLUG ) ) . '">' . esc_html__( 'Open the setup wizard', 'khabar-seo' ) . '</a></p></section>';

		echo '<section class="khs-section"><h2>' . esc_html__( 'Export settings', 'khabar-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Downloads KhabarSEO settings as a JSON file you can import on another site. It contains no passwords or keys.', 'khabar-seo' ) . '</p>';
		echo '<form method="post" action="' . $action . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo '<input type="hidden" name="action" value="khs_export" />';
		wp_nonce_field( 'khs_export' );
		echo '<p><button type="submit" class="button">' . esc_html__( 'Download settings file', 'khabar-seo' ) . '</button></p></form></section>';

		echo '<section class="khs-section"><h2>' . esc_html__( 'Import settings', 'khabar-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Upload a file exported from KhabarSEO. Every value is checked before it is saved; unknown values are ignored.', 'khabar-seo' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . $action . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo '<input type="hidden" name="action" value="khs_import" />';
		wp_nonce_field( 'khs_import' );
		echo '<p><label for="khs-import-file" class="screen-reader-text">' . esc_html__( 'Settings file', 'khabar-seo' ) . '</label>';
		echo '<input type="file" id="khs-import-file" name="khs_import_file" accept=".json,application/json" required /></p>';
		echo '<p><button type="submit" class="button">' . esc_html__( 'Import settings', 'khabar-seo' ) . '</button></p></form></section>';

		echo '<section class="khs-section"><h2>' . esc_html__( 'Reset to defaults', 'khabar-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Restores every setting to its default and switches KhabarSEO output off until you finish the setup wizard again.', 'khabar-seo' ) . '</p>';
		echo '<form method="post" action="' . $action . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above.
		echo '<input type="hidden" name="action" value="khs_reset" />';
		wp_nonce_field( 'khs_reset' );
		echo '<p><label><input type="checkbox" name="khs_confirm_reset" value="1" /> ' . esc_html__( 'Yes, reset all KhabarSEO settings', 'khabar-seo' ) . '</label></p>';
		echo '<p><button type="submit" class="button button-secondary">' . esc_html__( 'Reset settings', 'khabar-seo' ) . '</button></p></form></section>';
	}

	/**
	 * Verifies capability for a handler.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private function guard( string $nonce_action ): void {
		check_admin_referer( $nonce_action );
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'khabar-seo' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Redirects back to the Tools tab.
	 *
	 * @return void
	 */
	private function back(): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => Menu::SLUG,
					'tab'  => 'tools',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Streams the export file.
	 *
	 * @return void
	 */
	public function handle_export(): void {
		$this->guard( 'khs_export' );
		$json = wp_json_encode( Settings::export(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="khabar-seo-settings-' . gmdate( 'Ymd' ) . '.json"' );
		header( 'X-Content-Type-Options: nosniff' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download, not HTML.
		exit;
	}

	/**
	 * Imports an uploaded file.
	 *
	 * @return void
	 */
	public function handle_import(): void {
		$this->guard( 'khs_import' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in guard(); the file is validated below.
		$file = isset( $_FILES['khs_import_file'] ) && is_array( $_FILES['khs_import_file'] ) ? $_FILES['khs_import_file'] : array();
		// phpcs:enable
		$tmp   = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		$size  = isset( $file['size'] ) ? (int) $file['size'] : 0;

		if ( UPLOAD_ERR_OK !== $error || '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			Flash::add( 'error', __( 'No file was uploaded.', 'khabar-seo' ) );
			$this->back();
		}
		if ( $size < 1 || $size > self::MAX_IMPORT_BYTES ) {
			Flash::add( 'error', __( 'The file is empty or too large.', 'khabar-seo' ) );
			$this->back();
		}
		$raw  = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reads the uploaded temporary file.
		$data = is_string( $raw ) ? json_decode( $raw, true, 8 ) : null;
		if ( ! is_array( $data ) ) {
			Flash::add( 'error', __( 'The file is not valid JSON.', 'khabar-seo' ) );
			$this->back();
		}
		$result = Settings::import( $data );
		if ( $result['ok'] ) {
			/* translators: %d: number of imported settings. */
			Flash::add( 'success', sprintf( __( 'Imported %d settings.', 'khabar-seo' ), $result['applied'] ) );
		} elseif ( 'size' === $result['error'] ) {
			Flash::add( 'error', __( 'Nothing was imported: the settings would exceed the 8 KB limit.', 'khabar-seo' ) );
		} else {
			Flash::add( 'error', __( 'This is not a KhabarSEO settings file.', 'khabar-seo' ) );
		}
		$this->back();
	}

	/**
	 * Resets everything.
	 *
	 * @return void
	 */
	public function handle_reset(): void {
		$this->guard( 'khs_reset' );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		if ( empty( $_POST['khs_confirm_reset'] ) ) {
			Flash::add( 'warning', __( 'Tick the confirmation box to reset the settings.', 'khabar-seo' ) );
			$this->back();
		}
		$result = Settings::reset();
		if ( $result['ok'] ) {
			Flash::add( 'success', __( 'Settings were reset. Run the setup wizard to switch KhabarSEO on again.', 'khabar-seo' ) );
		} else {
			Flash::add( 'error', __( 'The settings could not be reset.', 'khabar-seo' ) );
		}
		$this->back();
	}
}
