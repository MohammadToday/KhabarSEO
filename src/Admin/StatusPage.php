<?php
/**
 * Status tab: environment checks, rendered-head scan, diagnostics report.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Conflicts\HeadScanner;
use KhabarSEO\Conflicts\PluginDetector;
use KhabarSEO\Core\Capabilities;
use KhabarSEO\Core\Diagnostics;
use KhabarSEO\Core\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Status page.
 */
final class StatusPage {

	/**
	 * Registers the scan handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_khs_head_scan', array( $this, 'handle_scan' ) );
	}

	/**
	 * Renders the tab content.
	 *
	 * @return void
	 */
	public function render_tab(): void {
		echo '<h2>' . esc_html__( 'Checks', 'khabar-seo' ) . '</h2>';
		echo '<table class="widefat striped khs-checks"><thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Check', 'khabar-seo' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Result', 'khabar-seo' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Details', 'khabar-seo' ) . '</th></tr></thead><tbody>';
		foreach ( Diagnostics::checks() as $row ) {
			printf(
				'<tr><th scope="row">%1$s</th><td><span class="khs-level khs-level--%2$s">%3$s</span></td><td>%4$s</td></tr>',
				esc_html( $row['label'] ),
				esc_attr( $row['level'] ),
				esc_html( Diagnostics::level_label( $row['level'] ) ),
				esc_html( $row['detail'] )
			);
		}
		echo '</tbody></table>';

		$this->render_scan();

		echo '<h2>' . esc_html__( 'Diagnostics', 'khabar-seo' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Paste this into a bug report. It contains no web addresses, keys or personal data.', 'khabar-seo' ) . '</p>';
		echo '<textarea id="khs-diagnostics" class="large-text code" rows="12" readonly dir="ltr">' . esc_textarea( Diagnostics::report() ) . '</textarea>';
		echo '<p><button type="button" class="button" data-khs-copy="#khs-diagnostics">' . esc_html__( 'Copy diagnostics', 'khabar-seo' ) . '</button> ';
		echo '<span class="khs-copied" role="status" hidden>' . esc_html__( 'Copied', 'khabar-seo' ) . '</span></p>';
	}

	/**
	 * Scan button and last results.
	 *
	 * @return void
	 */
	private function render_scan(): void {
		echo '<h2>' . esc_html__( 'Rendered head scan', 'khabar-seo' ) . '</h2>';
		echo '<p>' . esc_html__( 'Requests your home page and your newest news post from this server and lists the SEO tags found in the page head. Run it before switching KhabarSEO on to see which tags your theme or plugins already add.', 'khabar-seo' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="khs_head_scan" />';
		wp_nonce_field( 'khs_head_scan' );
		echo '<p><button type="submit" class="button">' . esc_html__( 'Scan now', 'khabar-seo' ) . '</button></p></form>';

		$scan = Settings::get_ext( 'scan' );
		if ( ! is_array( $scan ) || empty( $scan['targets'] ) ) {
			return;
		}
		$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) ( $scan['time'] ?? time() ) );
		/* translators: %s: date and time of the last scan. */
		echo '<p class="description">' . esc_html( sprintf( __( 'Last scan: %s', 'khabar-seo' ), (string) $when ) ) . '</p>';

		$names = array(
			'home' => __( 'Home page', 'khabar-seo' ),
			'post' => __( 'Newest news post', 'khabar-seo' ),
		);
		foreach ( (array) $scan['targets'] as $label => $result ) {
			echo '<h3>' . esc_html( $names[ $label ] ?? (string) $label ) . '</h3>';
			if ( empty( $result['ok'] ) ) {
				echo '<div class="notice notice-error inline"><p>' . esc_html( $this->scan_error( (string) ( $result['error'] ?? '' ), (int) ( $result['status'] ?? 0 ) ) ) . '</p></div>';
				continue;
			}
			$this->render_result( (array) $result['parsed'], (array) ( $result['findings'] ?? array() ) );
		}
	}

	/**
	 * Result table for one scanned page.
	 *
	 * @param array $parsed   Parsed head.
	 * @param array $findings Findings.
	 * @return void
	 */
	private function render_result( array $parsed, array $findings ): void {
		$rows = array(
			__( 'Title tags', 'khabar-seo' )            => (string) count( $parsed['title'] ?? array() ),
			__( 'Canonical links', 'khabar-seo' )       => implode( ' | ', (array) ( $parsed['canonical'] ?? array() ) ),
			__( 'Robots meta tags', 'khabar-seo' )      => implode( ' | ', (array) ( $parsed['robots'] ?? array() ) ),
			__( 'Meta descriptions', 'khabar-seo' )     => (string) count( (array) ( $parsed['description'] ?? array() ) ),
			__( 'Open Graph properties', 'khabar-seo' ) => (string) count( (array) ( $parsed['og'] ?? array() ) ),
			__( 'X (Twitter) card tags', 'khabar-seo' ) => (string) count( (array) ( $parsed['twitter'] ?? array() ) ),
			__( 'JSON-LD blocks', 'khabar-seo' )        => (string) ( $parsed['jsonld']['count'] ?? 0 ) . ( ! empty( $parsed['jsonld']['types'] ) ? ' (' . implode( ', ', $parsed['jsonld']['types'] ) . ')' : '' ),
			__( 'Generator tags', 'khabar-seo' )        => implode( ' | ', (array) ( $parsed['generator'] ?? array() ) ),
		);
		echo '<table class="widefat striped khs-scan"><tbody>';
		foreach ( $rows as $label => $value ) {
			printf( '<tr><th scope="row">%1$s</th><td dir="auto">%2$s</td></tr>', esc_html( $label ), esc_html( '' === $value ? '—' : $value ) );
		}
		echo '</tbody></table>';

		foreach ( $findings as $finding ) {
			$class = 'warn' === $finding['level'] ? 'notice-warning' : 'notice-info';
			echo '<div class="notice ' . esc_attr( $class ) . ' inline"><p>' . esc_html( $this->finding_text( $finding ) ) . '</p></div>';
		}
		if ( ! empty( $parsed['og'] ) && empty( $parsed['marker'] ) ) {
			$hint = PluginDetector::theme_hint();
			$text = $hint && ! empty( $hint['og_hint'] )
				? $hint['og_hint']
				: __( 'To avoid duplicate Open Graph tags, turn off the Open Graph option in your theme settings or in the plugin that adds them before switching KhabarSEO on.', 'khabar-seo' );
			echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'What to switch off:', 'khabar-seo' ) . '</strong> ' . esc_html( $text ) . '</p></div>';
		}
	}

	/**
	 * Text of one finding.
	 *
	 * @param array $finding Finding.
	 * @return string
	 */
	private function finding_text( array $finding ): string {
		switch ( $finding['code'] ) {
			case 'dup_title':
			case 'dup_canonical':
			case 'dup_robots':
			case 'dup_description':
				/* translators: %s: number of tags found. */
				return sprintf( __( 'This tag appears %s times, but a page should have exactly one.', 'khabar-seo' ), $finding['detail'] );
			case 'dup_og':
				/* translators: %s: Open Graph property name. */
				return sprintf( __( 'The Open Graph property %s appears more than once.', 'khabar-seo' ), $finding['detail'] );
			case 'none_canonical':
				return __( 'No canonical link was found.', 'khabar-seo' );
			case 'none_robots':
				return __( 'No robots meta tag was found (search engine defaults apply).', 'khabar-seo' );
			case 'og_present':
				/* translators: %s: number of Open Graph properties. */
				return sprintf( __( 'Open Graph tags are already present (%s properties).', 'khabar-seo' ), $finding['detail'] );
			case 'jsonld_present':
				/* translators: %s: number of JSON-LD blocks. */
				return sprintf( __( 'Structured data (JSON-LD) is already present (%s blocks).', 'khabar-seo' ), $finding['detail'] );
		}
		return '';
	}

	/**
	 * Text of a scan error.
	 *
	 * @param string $code   Error code.
	 * @param int    $status HTTP status.
	 * @return string
	 */
	private function scan_error( string $code, int $status ): string {
		switch ( $code ) {
			case 'dom':
				return __( 'The PHP DOM extension is missing, so the head cannot be parsed.', 'khabar-seo' );
			case 'status':
				/* translators: %d: HTTP status code. */
				return sprintf( __( 'The page answered with HTTP status %d.', 'khabar-seo' ), $status );
			case 'host':
				return __( 'The address is not on this site, so it was not requested.', 'khabar-seo' );
		}
		return __( 'The page could not be requested from this server (a firewall, basic authentication or DNS may block loopback requests).', 'khabar-seo' );
	}

	/**
	 * Runs the scan.
	 *
	 * @return void
	 */
	public function handle_scan(): void {
		check_admin_referer( 'khs_head_scan' );
		if ( ! Capabilities::can_manage() ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'khabar-seo' ), '', array( 'response' => 403 ) );
		}
		$results = array();
		foreach ( HeadScanner::targets() as $label => $url ) {
			$result             = HeadScanner::scan( $url );
			$result['findings'] = $result['ok'] ? HeadScanner::findings( $result['parsed'] ) : array();
			$results[ $label ]  = $result;
		}
		Settings::set_ext(
			'scan',
			array(
				'time'    => time(),
				'targets' => $results,
			)
		);
		Flash::add( 'success', __( 'Scan finished.', 'khabar-seo' ) );
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
