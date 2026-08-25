<?php
/**
 * Per-term banner: a category or a tag gets its own banner, the way a post
 * does, instead of every archive sharing the default of the layout.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Termbox {

	const NONCE_FIELD  = 'banners_og_termbox_nonce';
	const NONCE_ACTION = 'banners_og_termbox';

	public static function init(): void {
		foreach ( Banners_OG_Templates::taxonomies() as $taxonomy ) {
			add_action( $taxonomy . '_edit_form', [ __CLASS__, 'render' ], 10, 2 );
		}

		add_action( 'edited_term', [ __CLASS__, 'save' ], 10, 3 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	public static function enqueue( string $hook ): void {
		if ( 'term.php' !== $hook ) {
			return;
		}

		$screen = get_current_screen();

		if ( $screen && in_array( $screen->taxonomy, Banners_OG_Templates::taxonomies(), true ) ) {
			Banners_OG_Plugin::enqueue_editor_assets();
		}
	}

	/**
	 * Per-term banner settings, with the layout of the taxonomy as fallback.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_term_settings( int $term_id, string $taxonomy ): array {
		$meta = get_term_meta( $term_id, Banners_OG_Plugin::META_SETTINGS, true );
		$meta = is_array( $meta ) ? $meta : [];

		$kind = isset( $meta['kind'] ) && Banners_OG_Templates::is_kind( (string) $meta['kind'] )
			? (string) $meta['kind']
			: Banners_OG_Templates::default_kind_for_taxonomy( $taxonomy );

		$defaults = Banners_OG_Templates::empty_fields() + [
			'enabled' => 0,
			'kind'    => $kind,
		];

		$layout = Banners_OG_Templates::defaults()[ $kind ] ?? [];

		foreach ( Banners_OG_Templates::fields() as $key => $field ) {
			if ( 'toggle' === $field['type'] ) {
				$defaults[ $key ] = (string) ( $layout[ $key ] ?? '' );
			}
		}

		return wp_parse_args( $meta, $defaults );
	}

	public static function render( WP_Term $term, string $taxonomy ): void {
		if ( ! current_user_can( 'edit_term', $term->term_id ) ) {
			return;
		}

		$settings = self::get_term_settings( $term->term_id, $taxonomy );
		$kinds    = Banners_OG_Templates::kinds();
		$fields   = Banners_OG_Templates::fields();
		$defaults = Banners_OG_Templates::defaults();

		/** This filter also answers for terms; the second argument says which. */
		$filtered = apply_filters( 'banners_og_term_defaults', $defaults, $term );
		$defaults = is_array( $filtered ) ? $filtered : $defaults;

		$kind          = (string) $settings['kind'];
		$kind_defaults = $defaults[ $kind ] ?? $defaults[ Banners_OG_Templates::first_kind() ];
		$image_url     = (string) ( Banners_OG_Storage::image_url( (string) get_term_meta( $term->term_id, Banners_OG_Plugin::META_IMAGE, true ) ) ?? '' );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="bog-metabox bog-editor"
			data-context="term"
			data-term-id="<?php echo esc_attr( (string) $term->term_id ); ?>"
			data-kind="<?php echo esc_attr( $kind ); ?>"
			data-auto-kind="<?php echo esc_attr( Banners_OG_Templates::default_kind_for_taxonomy( $taxonomy ) ); ?>"
			data-defaults="<?php echo esc_attr( (string) wp_json_encode( $defaults ) ); ?>"
			data-post-title="<?php echo esc_attr( $term->name ); ?>"
			data-foot-placeholder="<?php echo esc_attr( self::foot_placeholder( $term ) ); ?>">

			<h2><?php esc_html_e( 'OG banner (social sharing)', 'banners-og' ); ?></h2>

			<p class="bog-note">
				<?php
				echo wp_kses(
					__( 'This term gets its <strong>own banner, rebuilt automatically on save</strong>: the banner title takes the term name and the previous image is replaced. Tick the box below only to change the layout or the copy.', 'banners-og' ),
					[ 'strong' => [] ]
				);
				?>
			</p>

			<p class="bog-toggle">
				<label>
					<input type="checkbox" name="banners_og[enabled]" value="1" class="bog-enabled" <?php checked( ! empty( $settings['enabled'] ) ); ?>>
					<strong><?php esc_html_e( 'Customize the layout and the copy of this banner', 'banners-og' ); ?></strong>
				</label>
			</p>

			<div class="bog-card__body">
				<div class="bog-fields">
					<div class="bog-custom" <?php echo empty( $settings['enabled'] ) ? 'hidden' : ''; ?>>
						<label>
							<span><?php esc_html_e( 'Layout', 'banners-og' ); ?></span>
							<select name="banners_og[kind]" class="bog-field" data-field="kind">
								<?php foreach ( $kinds as $value => $label ) : ?>
									<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $kind, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</label>

						<?php foreach ( $fields as $key => $field ) : ?>
							<?php
							$placeholder = (string) ( $kind_defaults[ $key ] ?? '' );

							if ( 'title' === $key ) {
								$placeholder = $term->name;
							}

							$hidden = [] !== $field['kinds'] && ! in_array( $kind, $field['kinds'], true );

							Banners_OG_Admin::render_field(
								$key,
								$field,
								(string) ( $settings[ $key ] ?? '' ),
								'banners_og[' . $key . ']',
								$placeholder,
								$hidden
							);
							?>
						<?php endforeach; ?>
					</div>

					<div class="bog-card__actions">
						<button type="button" class="button button-primary bog-generate">
							<?php esc_html_e( 'Generate banner now', 'banners-og' ); ?>
						</button>
						<span class="bog-status" aria-live="polite"></span>
					</div>

					<?php
					Banners_OG_Admin::render_current(
						$image_url,
						__( 'No banner generated yet. It is created when you update this term.', 'banners-og' )
					);
					?>
				</div>

				<div class="bog-preview">
					<div class="bog-stage-wrap">
						<div class="bog-stage"></div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public static function save( int $term_id, int $tt_id, string $taxonomy ): void {
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

		if (
			! wp_verify_nonce( $nonce, self::NONCE_ACTION )
			|| ! in_array( $taxonomy, Banners_OG_Templates::taxonomies(), true )
			|| ! current_user_can( 'edit_term', $term_id )
		) {
			return;
		}

		$kind = isset( $_POST['banners_og']['kind'] ) ? sanitize_key( wp_unslash( $_POST['banners_og']['kind'] ) ) : '';

		$settings = self::read_fields();

		$settings['enabled'] = empty( $_POST['banners_og']['enabled'] ) ? 0 : 1;
		$settings['kind']    = Banners_OG_Templates::is_kind( $kind )
			? $kind
			: Banners_OG_Templates::default_kind_for_taxonomy( $taxonomy );

		update_term_meta( $term_id, Banners_OG_Plugin::META_SETTINGS, $settings );
	}

	/**
	 * @return array<string, string>
	 */
	private static function read_fields(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- wp_verify_nonce() runs in save() before this.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_fields() cleans every value by its field type.
		$raw = [];

		foreach ( Banners_OG_Templates::fields() as $key => $field ) {
			if ( ! isset( $_POST['banners_og'][ $key ] ) || ! is_scalar( $_POST['banners_og'][ $key ] ) ) {
				continue;
			}

			$raw[ $key ] = wp_unslash( $_POST['banners_og'][ $key ] );
		}
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:enable WordPress.Security.NonceVerification.Missing

		return Banners_OG_Templates::sanitize_fields( $raw );
	}

	private static function foot_placeholder( WP_Term $term ): string {
		$host = Banners_OG_Templates::site_host();
		$link = get_term_link( $term );
		$path = is_string( $link ) ? wp_parse_url( $link, PHP_URL_PATH ) : '';

		if ( is_string( $path ) && '' !== $path && '/' !== $path ) {
			$host .= untrailingslashit( $path );
		}

		return $host;
	}
}
