<?php
/**
 * First-run setup wizard. Each step saves its own fields; the last step applies
 * the "News site, recommended" preset and switches front-end output on.
 *
 * @package KhabarSEO
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Core\Capabilities;
use KhabarSEO\Core\Presets;
use KhabarSEO\Core\Schema;
use KhabarSEO\Core\Settings;
use KhabarSEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Wizard.
 */
final class Wizard {

	private const LAST_STEP = 5;

	/**
	 * Registers the handlers.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_khs_wizard_step', array( $this, 'handle_step' ) );
		add_action( 'admin_post_khs_wizard_finish', array( $this, 'handle_finish' ) );
	}

	/**
	 * URL of a wizard step.
	 *
	 * @param int $step Step number.
	 * @return string
	 */
	private function step_url( int $step ): string {
		return add_query_arg(
			array(
				'page' => Menu::WIZARD_SLUG,
				'step' => $step,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Renders the wizard.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'khabar-seo' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only step selection, clamped to 1..5.
		$step  = isset( $_GET['step'] ) ? max( 1, min( self::LAST_STEP, absint( $_GET['step'] ) ) ) : 1;
		$steps = Schema::wizard_steps();

		echo '<div class="wrap khs-wrap khs-wizard">';
		echo '<h1>' . esc_html__( 'KhabarSEO setup', 'khabar-seo' ) . '</h1>';
		Flash::render();

		echo '<ol class="khs-steps">';
		foreach ( $steps as $number => $info ) {
			printf(
				'<li%1$s>%2$s</li>',
				$number === $step ? ' aria-current="step" class="is-current"' : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static attributes.
				esc_html( $info['title'] )
			);
		}
		echo '</ol>';

		echo '<div class="khs-card">';
		echo '<h2>' . esc_html( $steps[ $step ]['title'] ) . '</h2>';
		echo '<p>' . esc_html( $steps[ $step ]['intro'] ) . '</p>';

		if ( self::LAST_STEP === $step ) {
			$this->render_review();
		} else {
			$this->render_step_form( $step );
		}
		echo '</div></div>';
	}

	/**
	 * Form for steps 1 to 4.
	 *
	 * @param int $step Step number.
	 * @return void
	 */
	private function render_step_form( int $step ): void {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="khs-form">';
		echo '<input type="hidden" name="action" value="khs_wizard_step" />';
		echo '<input type="hidden" name="step" value="' . (int) $step . '" />';
		wp_nonce_field( 'khs_wizard_step' );
		foreach ( Schema::step_fields( $step ) as $key ) {
			FieldRenderer::render( $key );
		}
		echo '<p class="khs-actions">';
		if ( $step > 1 ) {
			echo '<a class="button" href="' . esc_url( $this->step_url( $step - 1 ) ) . '">' . esc_html__( 'Back', 'khabar-seo' ) . '</a> ';
		}
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save and continue', 'khabar-seo' ) . '</button> ';
		echo '<a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=' . Menu::SLUG ) ) . '">' . esc_html__( 'Skip for now', 'khabar-seo' ) . '</a>';
		echo '</p></form>';
	}

	/**
	 * Review step.
	 *
	 * @return void
	 */
	private function render_review(): void {
		echo '<table class="widefat striped khs-review"><tbody>';
		for ( $step = 1; $step < self::LAST_STEP; $step++ ) {
			foreach ( Schema::step_fields( $step ) as $key ) {
				if ( 'indexing.tag_min_posts' === $key && 'min_posts' !== Settings::get( 'indexing.tags' ) ) {
					continue;
				}
				printf(
					'<tr><th scope="row">%1$s</th><td>%2$s</td><td><a href="%3$s">%4$s</a></td></tr>',
					esc_html( Schema::ui( $key )['label'] ),
					esc_html( FieldRenderer::value_label( $key ) ),
					esc_url( $this->step_url( $step ) ),
					esc_html__( 'Change', 'khabar-seo' )
				);
			}
		}
		echo '</tbody></table>';

		$state = Plugin::output_state();
		if ( in_array( 'conflict', $state['reasons'], true ) ) {
			$names = implode( ', ', array_column( $state['conflicts'], 'name' ) );
			echo '<div class="notice notice-warning inline"><p>' . esc_html(
				/* translators: %s: names of other SEO plugins. */
				sprintf( __( 'Another SEO plugin is active (%s). You can finish setup now, but KhabarSEO stays switched off until you deactivate it.', 'khabar-seo' ), $names )
			) . '</p></div>';
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="khs_wizard_finish" />';
		wp_nonce_field( 'khs_wizard_finish' );
		echo '<p class="khs-actions"><a class="button" href="' . esc_url( $this->step_url( self::LAST_STEP - 1 ) ) . '">' . esc_html__( 'Back', 'khabar-seo' ) . '</a> ';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Finish and apply the recommended preset', 'khabar-seo' ) . '</button></p>';
		echo '</form>';
	}

	/**
	 * Saves one step and moves on.
	 *
	 * @return void
	 */
	public function handle_step(): void {
		check_admin_referer( 'khs_wizard_step' );
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'khabar-seo' ), '', array( 'response' => 403 ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$step = isset( $_POST['step'] ) ? max( 1, min( self::LAST_STEP - 1, absint( $_POST['step'] ) ) ) : 1;

		$result = SettingsPage::collect( Schema::step_fields( $step ) );

		if ( 1 === $step && '' === ( $result['values']['site.publication_name'] ?? '' ) ) {
			Flash::add( 'error', __( 'Please enter the publication name to continue.', 'khabar-seo' ) );
			wp_safe_redirect( $this->step_url( 1 ) );
			exit;
		}
		foreach ( $result['errors'] as $key => $code ) {
			Flash::add( 'warning', SettingsPage::error_message( $key, $code ) );
		}
		$saved = Settings::update( $result['values'] );
		if ( ! $saved['ok'] ) {
			Flash::add( 'error', __( 'Your answers could not be saved.', 'khabar-seo' ) );
			wp_safe_redirect( $this->step_url( $step ) );
			exit;
		}
		wp_safe_redirect( $this->step_url( $step + 1 ) );
		exit;
	}

	/**
	 * Applies the preset and finishes the wizard.
	 *
	 * @return void
	 */
	public function handle_finish(): void {
		check_admin_referer( 'khs_wizard_finish' );
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'khabar-seo' ), '', array( 'response' => 403 ) );
		}
		if ( '' === (string) Settings::get( 'site.publication_name' ) ) {
			Flash::add( 'error', __( 'Please enter the publication name first.', 'khabar-seo' ) );
			wp_safe_redirect( $this->step_url( 1 ) );
			exit;
		}
		$result = Presets::apply_news_recommended();
		if ( $result['ok'] ) {
			Flash::add( 'success', __( 'Setup finished. The "News site, recommended" preset was applied; you can edit everything on the settings tabs.', 'khabar-seo' ) );
		} else {
			Flash::add( 'error', __( 'The setup could not be completed.', 'khabar-seo' ) );
		}
		wp_safe_redirect(
			add_query_arg(
				array(
					'page' => Menu::SLUG,
					'tab'  => 'status',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
