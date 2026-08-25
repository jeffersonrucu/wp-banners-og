<?php
/**
 * "Appearance" screen: palette, typography and brand images of the banners.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Settings {

	const MENU_SLUG    = 'banners-og-appearance';
	const OPTION_GROUP = 'banners_og_theme_group';

	/**
	 * @var string
	 */
	private static $page_hook = '';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 11 );
		add_action( 'admin_init', [ __CLASS__, 'register_setting' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	public static function register_menu(): void {
		self::$page_hook = (string) add_submenu_page(
			Banners_OG_Admin::MENU_SLUG,
			__( 'Banner appearance', 'banners-og' ),
			__( 'Appearance', 'banners-og' ),
			'manage_options',
			self::MENU_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function register_setting(): void {
		register_setting(
			self::OPTION_GROUP,
			Banners_OG_Theme::OPTION,
			[
				'type'              => 'array',
				'default'           => Banners_OG_Theme::defaults(),
				'sanitize_callback' => [ 'Banners_OG_Theme', 'sanitize' ],
				'show_in_rest'      => false,
			]
		);
	}

	public static function enqueue( string $hook ): void {
		if ( '' === self::$page_hook || $hook !== self::$page_hook ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_style( 'banners-og-admin', BANNERS_OG_URL . 'assets/css/admin.css', [], BANNERS_OG_VERSION );
		wp_add_inline_style( 'banners-og-admin', Banners_OG_Theme::css_variables() );
		wp_enqueue_script( 'banners-og-settings', BANNERS_OG_URL . 'assets/js/settings.js', [ 'media-editor' ], BANNERS_OG_VERSION, true );

		wp_localize_script(
			'banners-og-settings',
			'BannersOGSettings',
			[
				'chooseTitle'  => __( 'Select image', 'banners-og' ),
				'chooseButton' => __( 'Use this image', 'banners-og' ),
			]
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$theme        = Banners_OG_Theme::get();
		$option       = Banners_OG_Theme::OPTION;
		$presets      = Banners_OG_Theme::font_presets();
		$preset_key   = (string) $theme['font_preset'];
		$fonts        = Banners_OG_Theme::fonts();
		$sample_title = Banners_OG_Theme::brand();
		?>
		<div class="wrap bog-wrap">
			<h1><?php esc_html_e( 'Banner appearance', 'banners-og' ); ?></h1>

			<?php settings_errors(); ?>

			<p class="bog-intro">
				<?php esc_html_e( 'The layouts read these values, so they follow the identity of the site instead of a hardcoded brand.', 'banners-og' ); ?>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<h2><?php esc_html_e( 'Palette', 'banners-og' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php foreach ( Banners_OG_Theme::colors() as $key => $label ) : ?>
						<tr>
							<th scope="row">
								<label for="bog-color-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label>
							</th>
							<td>
								<input type="text"
										class="bog-color"
										id="bog-color-<?php echo esc_attr( $key ); ?>"
										name="<?php echo esc_attr( $option . '[' . $key . ']' ); ?>"
										value="<?php echo esc_attr( (string) $theme[ $key ] ); ?>"
										pattern="#[0-9a-fA-F]{3,8}">
								<input type="color"
										aria-label="<?php echo esc_attr( $label ); ?>"
										value="<?php echo esc_attr( (string) $theme[ $key ] ); ?>"
										data-bog-color-for="bog-color-<?php echo esc_attr( $key ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</table>

				<h2><?php esc_html_e( 'Typography', 'banners-og' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bog-font-preset"><?php esc_html_e( 'Font pairing', 'banners-og' ); ?></label>
						</th>
						<td>
							<select id="bog-font-preset" name="<?php echo esc_attr( $option . '[font_preset]' ); ?>" data-bog-font-preset>
								<?php foreach ( $presets as $key => $preset ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"
										<?php selected( $preset_key, $key ); ?>
										data-heading="<?php echo esc_attr( $preset['heading'] ); ?>"
										data-body="<?php echo esc_attr( $preset['body'] ); ?>">
										<?php echo esc_html( $preset['label'] ); ?>
									</option>
								<?php endforeach; ?>
								<option value="<?php echo esc_attr( Banners_OG_Theme::PRESET_CUSTOM ); ?>"
									<?php selected( $preset_key, Banners_OG_Theme::PRESET_CUSTOM ); ?>
									data-heading="<?php echo esc_attr( (string) $theme['font_heading'] ); ?>"
									data-body="<?php echo esc_attr( (string) $theme['font_body'] ); ?>">
									<?php esc_html_e( 'Custom — I will type the font stacks', 'banners-og' ); ?>
								</option>
							</select>

							<div class="bog-font-sample" data-bog-font-sample>
								<span class="bog-font-sample__heading" data-bog-sample-heading
									style="font-family:<?php echo esc_attr( $fonts['heading'] ); ?>">
									<?php echo esc_html( $sample_title ); ?>
								</span>
								<span class="bog-font-sample__body" data-bog-sample-body
									style="font-family:<?php echo esc_attr( $fonts['body'] ); ?>">
									<?php esc_html_e( 'The subtitle and the footer of the banner use this face.', 'banners-og' ); ?>
								</span>
							</div>

							<p class="description">
								<?php esc_html_e( 'All pairings use fonts that already exist on the reader device, so no external font is downloaded.', 'banners-og' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<div class="bog-advanced" data-bog-advanced <?php echo Banners_OG_Theme::PRESET_CUSTOM === $preset_key ? '' : 'hidden'; ?>>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row">
								<label for="bog-font-heading"><?php esc_html_e( 'Heading font stack', 'banners-og' ); ?></label>
							</th>
							<td>
								<input type="text" class="large-text code" id="bog-font-heading"
										name="<?php echo esc_attr( $option . '[font_heading]' ); ?>"
										value="<?php echo esc_attr( (string) $theme['font_heading'] ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bog-font-body"><?php esc_html_e( 'Body font stack', 'banners-og' ); ?></label>
							</th>
							<td>
								<input type="text" class="large-text code" id="bog-font-body"
										name="<?php echo esc_attr( $option . '[font_body]' ); ?>"
										value="<?php echo esc_attr( (string) $theme['font_body'] ); ?>">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="bog-font-url"><?php esc_html_e( 'Web font stylesheet', 'banners-og' ); ?></label>
							</th>
							<td>
								<input type="url" class="large-text code" id="bog-font-url"
										name="<?php echo esc_attr( $option . '[font_css_url]' ); ?>"
										value="<?php echo esc_attr( (string) $theme['font_css_url'] ); ?>"
										placeholder="https://">
								<p class="description">
									<?php esc_html_e( 'Only needed when the stacks above name a font that is not installed on the reader device. Loading it adds an external request.', 'banners-og' ); ?>
								</p>
							</td>
						</tr>
					</table>
				</div>

				<h2><?php esc_html_e( 'Brand', 'banners-og' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="bog-brand"><?php esc_html_e( 'Brand name', 'banners-og' ); ?></label>
						</th>
						<td>
							<input type="text" class="regular-text" id="bog-brand"
									name="<?php echo esc_attr( $option . '[brand]' ); ?>"
									value="<?php echo esc_attr( (string) $theme['brand'] ); ?>"
									placeholder="<?php echo esc_attr( (string) get_bloginfo( 'name' ) ); ?>">
							<p class="description"><?php esc_html_e( 'Empty falls back to the site title.', 'banners-og' ); ?></p>
						</td>
					</tr>
					<?php
					self::render_image_field(
						'logo_id',
						__( 'Horizontal logo', 'banners-og' ),
						__( 'Used by the layouts with a logo slot. Empty falls back to the logo of the active theme.', 'banners-og' ),
						(int) $theme['logo_id']
					);

					self::render_image_field(
						'mark_id',
						__( 'Square mark', 'banners-og' ),
						__( 'Symbol shown by the layouts. Empty falls back to the site icon.', 'banners-og' ),
						(int) $theme['mark_id']
					);
					?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function render_image_field( string $key, string $label, string $description, int $attachment_id ): void {
		$option = Banners_OG_Theme::OPTION;
		$url    = $attachment_id > 0 ? wp_get_attachment_image_url( $attachment_id, 'medium' ) : '';
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<div class="bog-media" data-bog-media>
					<input type="hidden"
							name="<?php echo esc_attr( $option . '[' . $key . ']' ); ?>"
							value="<?php echo esc_attr( (string) $attachment_id ); ?>"
							data-bog-media-input>

					<div class="bog-media__preview" data-bog-media-preview>
						<?php if ( is_string( $url ) && '' !== $url ) : ?>
							<img src="<?php echo esc_url( $url ); ?>" alt="">
						<?php endif; ?>
					</div>

					<p>
						<button type="button" class="button" data-bog-media-select>
							<?php esc_html_e( 'Select image', 'banners-og' ); ?>
						</button>
						<button type="button" class="button-link bog-media__remove" data-bog-media-remove>
							<?php esc_html_e( 'Remove', 'banners-og' ); ?>
						</button>
					</p>

					<p class="description"><?php echo esc_html( $description ); ?></p>
				</div>
			</td>
		</tr>
		<?php
	}
}
