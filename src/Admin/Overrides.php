<?php
/**
 * Per-post and per-term overrides: a server-rendered metabox (works in the
 * Block and Classic editors) and fields on the term edit screen.
 *
 * @package   KhabarSEO
 * @copyright 2026 Mohammad Ahmadi Akbari and KhabarSEO contributors
 * @license   GPL-2.0-or-later
 */

namespace KhabarSEO\Admin;

use KhabarSEO\Core\Meta;
use KhabarSEO\Core\Sanitizer;
use KhabarSEO\Core\Settings;
use KhabarSEO\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Override UI.
 */
final class Overrides {

	private const POST_NONCE = 'khs_post_meta';
	private const TERM_NONCE = 'khs_term_meta';

	/**
	 * Registers hooks (admin only).
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'add_meta_boxes', array( $this, 'add_metabox' ) );
		add_action( 'save_post', array( $this, 'save_post' ), 10, 1 );
		add_action( 'admin_init', array( $this, 'register_term_hooks' ) );
	}

	/**
	 * Adds the metabox to every public post type.
	 *
	 * @return void
	 */
	public function add_metabox(): void {
		foreach ( array_keys( Sanitizer::selectable_post_types() ) as $type ) {
			add_meta_box( 'khs-seo', __( 'KhabarSEO', 'khabar-seo' ), array( $this, 'render_metabox' ), $type, 'normal', 'default' );
		}
	}

	/**
	 * Variable reference shown under template inputs.
	 *
	 * @return string Escaped HTML.
	 */
	public static function variables_help(): string {
		$names = array(
			'title'    => __( 'title of this item', 'khabar-seo' ),
			'sitename' => __( 'site name', 'khabar-seo' ),
			'tagline'  => __( 'site tagline', 'khabar-seo' ),
			'sep'      => __( 'separator', 'khabar-seo' ),
			'category' => __( 'first category', 'khabar-seo' ),
			'author'   => __( 'author name', 'khabar-seo' ),
			'date'     => __( 'publication date as shown on the site', 'khabar-seo' ),
			'page'     => __( '"Page 2 of 5" beyond page 1', 'khabar-seo' ),
			'excerpt'  => __( 'excerpt or summary', 'khabar-seo' ),
		);
		$items = array();
		foreach ( $names as $name => $label ) {
			$items[] = '<code>%' . esc_html( $name ) . '%</code> ' . esc_html( $label );
		}
		return '<span class="khs-vars">' . esc_html__( 'Variables:', 'khabar-seo' ) . ' ' . implode( ' · ', $items ) . '</span>';
	}

	/**
	 * Character counter markup.
	 *
	 * @param string $input_id Input id.
	 * @return string Escaped HTML.
	 */
	private static function counter( string $input_id ): string {
		/* translators: %d: number of characters. Keep the placeholder. */
		$label = __( '%d characters', 'khabar-seo' );
		return '<span class="khs-count" data-khs-count-for="' . esc_attr( $input_id ) . '" data-khs-count-label="' . esc_attr( $label ) . '" aria-live="polite"></span>';
	}

	/**
	 * Prints the metabox.
	 *
	 * @param \WP_Post $post Post being edited.
	 * @return void
	 */
	public function render_metabox( $post ): void {
		wp_nonce_field( self::POST_NONCE, self::POST_NONCE . '_nonce' );
		$title     = (string) get_post_meta( $post->ID, Meta::TITLE, true );
		$desc      = (string) get_post_meta( $post->ID, Meta::DESC, true );
		$canonical = (string) get_post_meta( $post->ID, Meta::CANONICAL, true );
		$robots    = Meta::post_robots( (int) $post->ID );
		$template  = Settings::map_item( 'titles.singular_title', (string) $post->post_type );

		echo '<div class="khs-metabox">';
		if ( ! Plugin::output_state()['active'] ) {
			echo '<p class="description"><strong>' . esc_html__( 'KhabarSEO output is switched off.', 'khabar-seo' ) . '</strong> ' . esc_html__( 'Values entered here are saved and used once it is on (see KhabarSEO, Status).', 'khabar-seo' ) . '</p>';
		}

		echo '<p><label class="khs-label" for="khs-title">' . esc_html__( 'SEO title', 'khabar-seo' ) . '</label><br />';
		printf(
			'<input type="text" class="large-text" id="khs-title" name="khs_meta[title]" value="%1$s" placeholder="%2$s" dir="auto" maxlength="%3$d" /> %4$s',
			esc_attr( $title ),
			esc_attr( $template ),
			(int) Meta::MAX_TITLE,
			self::counter( 'khs-title' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in counter().
		);
		echo '</p><p class="description">' . esc_html__( 'Leave empty to use the template shown in grey. Google may rewrite titles that do not match the headline; there is no length limit, long titles are shortened in results.', 'khabar-seo' ) . '<br />' . self::variables_help() . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- variables_help() is escaped.

		echo '<p><label class="khs-label" for="khs-desc">' . esc_html__( 'Meta description', 'khabar-seo' ) . '</label><br />';
		printf(
			'<textarea class="large-text" rows="3" id="khs-desc" name="khs_meta[desc]" dir="auto" maxlength="%1$d">%2$s</textarea> %3$s',
			(int) Meta::MAX_DESC,
			esc_textarea( $desc ),
			self::counter( 'khs-desc' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in counter().
		);
		echo '</p><p class="description">' . esc_html__( 'Leave empty to use the excerpt. There is no length limit; search engines shorten long descriptions to fit the screen.', 'khabar-seo' ) . '</p>';

		echo '<p><label class="khs-label" for="khs-canonical">' . esc_html__( 'Canonical URL', 'khabar-seo' ) . '</label><br />';
		printf( '<input type="url" class="large-text" id="khs-canonical" name="khs_meta[canonical]" value="%s" dir="ltr" placeholder="https://" />', esc_attr( $canonical ) );
		echo '</p><p class="description">' . esc_html__( 'Leave empty: the article points to its own address, which is right for almost every story. Only fill this in if this article is a copy of another page.', 'khabar-seo' ) . '</p>';

		echo '<fieldset class="khs-choice"><legend class="khs-label">' . esc_html__( 'Search engine rules for this item', 'khabar-seo' ) . '</legend>';
		$boxes = array(
			'noindex'   => __( 'Do not show in search results (noindex)', 'khabar-seo' ),
			'nofollow'  => __( 'Do not follow links on this page (nofollow)', 'khabar-seo' ),
			'nosnippet' => __( 'No text snippet in results (nosnippet)', 'khabar-seo' ),
		);
		foreach ( $boxes as $key => $label ) {
			printf(
				'<div class="khs-option"><label><input type="checkbox" name="khs_meta[robots][%1$s]" value="1"%2$s /> %3$s</label></div>',
				esc_attr( $key ),
				checked( $robots[ $key ], true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core helper returns a safe attribute.
				esc_html( $label )
			);
		}
		printf(
			'<p><label for="khs-max-snippet">%1$s</label> <input type="number" class="small-text" id="khs-max-snippet" name="khs_meta[robots][max_snippet]" value="%2$s" min="-1" max="10000" /></p>',
			esc_html__( 'Maximum snippet length (empty = site default)', 'khabar-seo' ),
			esc_attr( null === $robots['max_snippet'] ? '' : (string) $robots['max_snippet'] )
		);
		echo '<p class="description">' . esc_html__( 'nosnippet and max-snippet also shorten normal search snippets and limit AI features in search, not only one feature. To hide only a passage, wrap it in the "Hidden from search snippets" block or in [khs_nosnippet]...[/khs_nosnippet].', 'khabar-seo' ) . '</p>';
		echo '</fieldset></div>';
	}

	/**
	 * Saves the metabox.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save_post( $post_id ): void {
		$nonce = isset( $_POST[ self::POST_NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::POST_NONCE . '_nonce' ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::POST_NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( (int) $post_id ) || ! current_user_can( 'edit_post', (int) $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
		$input = isset( $_POST['khs_meta'] ) && is_array( $_POST['khs_meta'] ) ? wp_unslash( $_POST['khs_meta'] ) : array();
		$this->store( 'post', (int) $post_id, $input );
		$robots = isset( $input['robots'] ) && is_array( $input['robots'] ) ? $input['robots'] : array();
		$max    = isset( $robots['max_snippet'] ) && '' !== trim( (string) $robots['max_snippet'] ) ? $robots['max_snippet'] : null;
		Meta::save_post_robots(
			(int) $post_id,
			array(
				'noindex'     => ! empty( $robots['noindex'] ),
				'nofollow'    => ! empty( $robots['nofollow'] ),
				'nosnippet'   => ! empty( $robots['nosnippet'] ),
				'max_snippet' => $max,
			)
		);
	}

	/**
	 * Sanitizes and stores title, description and canonical.
	 *
	 * @param string $type  "post" or "term".
	 * @param int    $id    Object ID.
	 * @param array  $input Unslashed input.
	 * @return void
	 */
	private function store( string $type, int $id, array $input ): void {
		$error = '';
		Meta::save_string( $type, $id, Meta::TITLE, Sanitizer::template( $input['title'] ?? '', Meta::MAX_TITLE, $error ) );
		Meta::save_string( $type, $id, Meta::DESC, self::description( $input['desc'] ?? '' ) );
		Meta::save_string( $type, $id, Meta::CANONICAL, Sanitizer::absolute_url( $input['canonical'] ?? '' ) );
	}

	/**
	 * Sanitizes a description (single paragraph of plain text).
	 *
	 * @param mixed $raw Raw value.
	 * @return string
	 */
	private static function description( $raw ): string {
		$text = is_scalar( $raw ) ? sanitize_textarea_field( (string) $raw ) : '';
		return Sanitizer::text( $text, Meta::MAX_DESC );
	}

	/**
	 * Adds term fields for every public taxonomy.
	 *
	 * @return void
	 */
	public function register_term_hooks(): void {
		foreach ( array_keys( Sanitizer::selectable_taxonomies() ) as $taxonomy ) {
			add_action( $taxonomy . '_edit_form_fields', array( $this, 'render_term_fields' ), 10, 1 );
			add_action( 'edited_' . $taxonomy, array( $this, 'save_term' ), 10, 1 );
		}
	}

	/**
	 * Prints the term fields (rows of the term edit table).
	 *
	 * @param \WP_Term $term Term.
	 * @return void
	 */
	public function render_term_fields( $term ): void {
		$title     = (string) get_term_meta( $term->term_id, Meta::TITLE, true );
		$desc      = (string) get_term_meta( $term->term_id, Meta::DESC, true );
		$canonical = (string) get_term_meta( $term->term_id, Meta::CANONICAL, true );
		$robots    = Meta::term_robots( (int) $term->term_id );
		$template  = Settings::map_item( 'titles.term_title', (string) $term->taxonomy );

		echo '<tr class="form-field"><th scope="row" colspan="2"><h2>' . esc_html__( 'KhabarSEO', 'khabar-seo' ) . '</h2>';
		wp_nonce_field( self::TERM_NONCE, self::TERM_NONCE . '_nonce' );
		echo '</th></tr>';

		printf(
			'<tr class="form-field"><th scope="row"><label for="khs-term-title">%1$s</label></th><td><input type="text" id="khs-term-title" name="khs_meta[title]" value="%2$s" placeholder="%3$s" dir="auto" maxlength="%4$d" /> %5$s<p class="description">%6$s<br />%7$s</p></td></tr>',
			esc_html__( 'SEO title', 'khabar-seo' ),
			esc_attr( $title ),
			esc_attr( $template ),
			(int) Meta::MAX_TITLE,
			self::counter( 'khs-term-title' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in counter().
			esc_html__( 'Leave empty to use the template shown in grey.', 'khabar-seo' ),
			self::variables_help() // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in variables_help().
		);
		printf(
			'<tr class="form-field"><th scope="row"><label for="khs-term-desc">%1$s</label></th><td><textarea id="khs-term-desc" name="khs_meta[desc]" rows="3" dir="auto" maxlength="%2$d">%3$s</textarea> %4$s<p class="description">%5$s</p></td></tr>',
			esc_html__( 'Meta description', 'khabar-seo' ),
			(int) Meta::MAX_DESC,
			esc_textarea( $desc ),
			self::counter( 'khs-term-desc' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in counter().
			esc_html__( 'Leave empty to use the term description above.', 'khabar-seo' )
		);
		printf(
			'<tr class="form-field"><th scope="row"><label for="khs-term-canonical">%1$s</label></th><td><input type="url" id="khs-term-canonical" name="khs_meta[canonical]" value="%2$s" dir="ltr" placeholder="https://" /><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Canonical URL', 'khabar-seo' ),
			esc_attr( $canonical ),
			esc_html__( 'Leave empty to use the address of this archive (with its page number on page 2 and later).', 'khabar-seo' )
		);
		$options = array(
			''        => __( 'Use the rule from KhabarSEO, Indexing', 'khabar-seo' ),
			'index'   => __( 'Always index', 'khabar-seo' ),
			'noindex' => __( 'Never index (noindex)', 'khabar-seo' ),
		);
		echo '<tr class="form-field"><th scope="row"><label for="khs-term-robots">' . esc_html__( 'Search engines', 'khabar-seo' ) . '</label></th><td><select id="khs-term-robots" name="khs_meta[robots]">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $value ), selected( $robots, $value, false ), esc_html( $label ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selected() returns a safe attribute.
		}
		echo '</select></td></tr>';
	}

	/**
	 * Saves the term fields.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save_term( $term_id ): void {
		$nonce = isset( $_POST[ self::TERM_NONCE . '_nonce' ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::TERM_NONCE . '_nonce' ] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::TERM_NONCE ) || ! current_user_can( 'edit_term', (int) $term_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitized below.
		$input = isset( $_POST['khs_meta'] ) && is_array( $_POST['khs_meta'] ) ? wp_unslash( $_POST['khs_meta'] ) : array();
		$this->store( 'term', (int) $term_id, $input );
		$robots = isset( $input['robots'] ) && in_array( $input['robots'], array( 'index', 'noindex' ), true ) ? $input['robots'] : '';
		Meta::save_string( 'term', (int) $term_id, Meta::ROBOTS, $robots );
	}
}
