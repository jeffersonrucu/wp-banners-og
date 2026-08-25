<?php
/**
 * Per-content metabox: every supported post type gets its own banner.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Metabox {

	const NONCE_FIELD  = 'banners_og_metabox_nonce';
	const NONCE_ACTION = 'banners_og_metabox';

	public static function init(): void {
		add_action( 'add_meta_boxes', [ __CLASS__, 'register' ] );
		add_action( 'save_post', [ __CLASS__, 'save' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	public static function register(): void {
		add_meta_box(
			'banners-og-banner',
			__( 'OG banner (social sharing)', 'banners-og' ),
			[ __CLASS__, 'render' ],
			Banners_OG_Templates::post_types(),
			'normal',
			'default'
		);
	}

	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( $screen && in_array( $screen->post_type, Banners_OG_Templates::post_types(), true ) ) {
			Banners_OG_Plugin::enqueue_editor_assets();
		}
	}

	public static function render( WP_Post $post ): void {
		$settings = Banners_OG_Plugin::get_post_settings( $post->ID );
		$kinds    = Banners_OG_Templates::kinds();
		$fields   = Banners_OG_Templates::fields();
		$defaults = Banners_OG_Templates::defaults();

		/**
		 * Filters the layout defaults used as the placeholders of one post, so
		 * a module can derive them from the content itself.
		 */
		$filtered = apply_filters( 'banners_og_post_defaults', $defaults, $post );
		$defaults = is_array( $filtered ) ? $filtered : $defaults;

		$kind          = (string) $settings['kind'];
		$kind_defaults = $defaults[ $kind ] ?? $defaults[ Banners_OG_Templates::first_kind() ];
		$image_url     = (string) ( Banners_OG_Storage::image_url( (string) get_post_meta( $post->ID, Banners_OG_Plugin::META_IMAGE, true ) ) ?? '' );
		$post_title    = (string) ( get_the_title( $post ) ?: '' );

		wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
		?>
		<div class="bog-metabox bog-editor"
			data-context="post"
			data-post-id="<?php echo esc_attr( (string) $post->ID ); ?>"
			data-kind="<?php echo esc_attr( $kind ); ?>"
			data-auto-kind="<?php echo esc_attr( Banners_OG_Templates::default_kind_for_post_type( $post->post_type ) ); ?>"
			data-defaults="<?php echo esc_attr( (string) wp_json_encode( $defaults ) ); ?>"
			data-post-title="<?php echo esc_attr( $post_title ); ?>"
			data-foot-placeholder="<?php echo esc_attr( self::foot_placeholder( $post ) ); ?>">

			<p class="bog-note">
				<?php
				echo wp_kses(
					__( 'This content gets its <strong>own banner, rebuilt automatically on save</strong>: the banner title takes the post title and the previous image is replaced. Tick the box below only to change the layout or the copy.', 'banners-og' ),
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

							if ( 'title' === $key && '' !== $post_title ) {
								$placeholder = $post_title;
							}

							Banners_OG_Admin::render_field(
								$key,
								$field,
								(string) ( $settings[ $key ] ?? '' ),
								'banners_og[' . $key . ']',
								$placeholder
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
						__( 'No banner generated yet. It is created when you save this content.', 'banners-og' )
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

	public static function save( int $post_id, WP_Post $post ): void {
		$nonce = isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '';

		if (
			! wp_verify_nonce( $nonce, self::NONCE_ACTION )
			|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			|| wp_is_post_revision( $post_id )
			|| ! current_user_can( 'edit_post', $post_id )
			|| ! in_array( $post->post_type, Banners_OG_Templates::post_types(), true )
		) {
			return;
		}

		$kind = isset( $_POST['banners_og']['kind'] ) ? sanitize_key( wp_unslash( $_POST['banners_og']['kind'] ) ) : '';

		$settings = self::read_fields();

		$settings['enabled'] = empty( $_POST['banners_og']['enabled'] ) ? 0 : 1;
		$settings['kind']    = Banners_OG_Templates::is_kind( $kind )
			? $kind
			: Banners_OG_Templates::default_kind_for_post_type( $post->post_type );

		update_post_meta( $post_id, Banners_OG_Plugin::META_SETTINGS, $settings );
	}

	/**
	 * @return array<string, string>
	 */
	private static function read_fields(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- wp_verify_nonce() runs in save() before this.
		$raw = [];

		foreach ( Banners_OG_Templates::fields() as $key => $field ) {
			if ( ! isset( $_POST['banners_og'][ $key ] ) || ! is_scalar( $_POST['banners_og'][ $key ] ) ) {
				continue;
			}

			$raw[ $key ] = 'textarea' === $field['type']
				? sanitize_textarea_field( wp_unslash( $_POST['banners_og'][ $key ] ) )
				: sanitize_text_field( wp_unslash( $_POST['banners_og'][ $key ] ) );
		}
        // phpcs:enable WordPress.Security.NonceVerification.Missing

		return Banners_OG_Templates::sanitize_fields( $raw );
	}

	private static function foot_placeholder( WP_Post $post ): string {
		$host = Banners_OG_Templates::site_host();
		$path = wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH );

		if ( is_string( $path ) && '' !== $path && '/' !== $path ) {
			$host .= untrailingslashit( $path );
		}

		return $host;
	}
}
