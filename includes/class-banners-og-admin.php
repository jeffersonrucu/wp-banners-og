<?php
/**
 * "Banners OG" screen: the default banner of every layout.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Admin {

	const MENU_SLUG = 'banners-og';

	/**
	 * @var string
	 */
	private static $page_hook = '';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue' ] );
	}

	public static function register_menu(): void {
		self::$page_hook = (string) add_menu_page(
			__( 'Banners OG', 'banners-og' ),
			__( 'Banners OG', 'banners-og' ),
			'manage_options',
			self::MENU_SLUG,
			[ __CLASS__, 'render_page' ],
			'dashicons-share',
			59
		);
	}

	public static function enqueue( string $hook ): void {
		if ( '' !== self::$page_hook && $hook === self::$page_hook ) {
			Banners_OG_Plugin::enqueue_editor_assets();
		}
	}

	/**
	 * Renders one text field of a layout.
	 *
	 * `$hidden` is for the metabox, where the layout can be switched without a
	 * reload: every field is printed, and the ones that do not belong to the
	 * current layout start hidden.
	 *
	 * @param array{label:string, type:string, description:string, placeholder:string, kinds:array<int, string>} $field
	 */
	public static function render_field( string $key, array $field, string $value, string $name, string $placeholder = '', bool $hidden = false ): void {
		if ( '' === $placeholder ) {
			$placeholder = $field['placeholder'];
		}

		printf(
			'<label class="bog-field-row bog-field-row--%1$s"%2$s%3$s>',
			esc_attr( $field['type'] ),
			[] !== $field['kinds'] ? ' data-kinds="' . esc_attr( implode( ' ', $field['kinds'] ) ) . '"' : '',
			$hidden ? ' hidden' : ''
		);

		// A checkbox reads as "[x] what it does", so its label comes after the
		// control instead of above it.
		if ( 'toggle' !== $field['type'] ) {
			echo '<span>' . esc_html( $field['label'] ) . '</span>';
		}

		switch ( $field['type'] ) {
			case 'textarea':
				printf(
					'<textarea rows="3" class="bog-field" data-field="%1$s" name="%2$s" placeholder="%3$s">%4$s</textarea>',
					esc_attr( $key ),
					esc_attr( $name ),
					esc_attr( $placeholder ),
					esc_textarea( $value )
				);
				break;
			case 'image':
				self::render_image_field( $key, $name, $value, $placeholder );
				break;
			case 'toggle':
				printf(
					'<input type="checkbox" class="bog-field" data-field="%1$s" name="%2$s" value="1"%3$s><span class="bog-field-row__toggle">%4$s</span>',
					esc_attr( $key ),
					esc_attr( $name ),
					checked( '' !== $value, true, false ),
					esc_html( $field['label'] )
				);
				break;
			default:
				printf(
					'<input type="text" class="bog-field" data-field="%1$s" name="%2$s" placeholder="%3$s" value="%4$s">',
					esc_attr( $key ),
					esc_attr( $name ),
					esc_attr( $placeholder ),
					esc_attr( $value )
				);
		}

		if ( '' !== $field['description'] ) {
			echo '<span class="description">' . esc_html( $field['description'] ) . '</span>';
		}

		echo '</label>';
	}

	/**
	 * Media picker of an `image` field. The value is the chosen URL; empty
	 * falls back to `$placeholder`, which is the image the layout would use on
	 * its own.
	 */
	private static function render_image_field( string $key, string $name, string $value, string $placeholder ): void {
		$preview = '' !== $value ? $value : $placeholder;
		?>
		<span class="bog-image" data-bog-image>
			<input type="hidden" class="bog-field" data-field="<?php echo esc_attr( $key ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>">

			<span class="bog-image__preview" data-bog-image-preview
					data-empty="<?php esc_attr_e( 'No image', 'banners-og' ); ?>">
				<?php if ( '' !== $preview ) : ?>
					<img src="<?php echo esc_url( $preview ); ?>" alt="">
				<?php endif; ?>
			</span>

			<span class="bog-image__actions">
				<button type="button" class="button button-small" data-bog-image-select>
					<?php esc_html_e( 'Select image', 'banners-og' ); ?>
				</button>
				<button type="button" class="button-link bog-image__clear" data-bog-image-clear<?php echo '' !== $value ? '' : ' hidden'; ?>>
					<?php esc_html_e( 'Remove', 'banners-og' ); ?>
				</button>
			</span>
		</span>
		<?php
	}

	/**
	 * What the banner of a layout answers for, so the screen says where each
	 * card lands instead of leaving it to be guessed.
	 *
	 * @param array<int, string> $contexts
	 */
	private static function render_uses( array $contexts ): void {
		if ( [] === $contexts ) {
			printf(
				'<span class="bog-uses bog-uses--none">%s</span>',
				esc_html__( 'Only where it is picked by hand', 'banners-og' )
			);

			return;
		}

		echo '<span class="bog-uses">';
		printf( '<span class="bog-uses__label">%s</span>', esc_html__( 'Answers for', 'banners-og' ) );

		foreach ( $contexts as $context ) {
			printf( '<span class="bog-uses__item">%s</span>', esc_html( $context ) );
		}

		echo '</span>';
	}

	/**
	 * Line telling which file is currently serving as og:image.
	 */
	public static function render_current( string $image_url, string $empty_message ): void {
		printf( '<p class="bog-current%s">', '' !== $image_url ? '' : ' is-empty' );

		if ( '' !== $image_url ) {
			printf(
				'%1$s <a href="%2$s" target="_blank" rel="noopener">%3$s</a>',
				esc_html__( 'Current banner:', 'banners-og' ),
				esc_url( $image_url ),
				esc_html( wp_basename( $image_url ) )
			);
		} else {
			echo esc_html( $empty_message );
		}

		echo '</p>';
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$defaults = Banners_OG_Templates::defaults();
		$kinds    = Banners_OG_Templates::kinds();
		?>
		<div class="wrap bog-wrap">
			<h1><?php esc_html_e( 'Open Graph banners', 'banners-og' ); ?></h1>
			<p class="bog-intro">
				<?php
				printf(
					/* translators: 1: banner width in pixels, 2: banner height in pixels. */
					esc_html__( 'Sharing images (%1$d × %2$d) used by the og:image tags of the site. Edit the copy, click "Generate and save" and the banner of that context is replaced right away. Posts and pages can override their own banner in the editor.', 'banners-og' ),
					(int) BANNERS_OG_WIDTH,
					(int) BANNERS_OG_HEIGHT
				);
				?>
			</p>

			<?php foreach ( $kinds as $kind => $label ) : ?>
				<?php
				$values    = $defaults[ $kind ] ?? [];
				$fields    = Banners_OG_Templates::fields_for_kind( $kind, 'default' );
				$image_url = (string) ( Banners_OG_Storage::image_url( (string) ( $values['image'] ?? '' ) ) ?? '' );
				?>
				<div class="bog-card bog-editor" data-context="default" data-kind="<?php echo esc_attr( $kind ); ?>">
					<div class="bog-card__head">
						<div class="bog-card__title">
							<span class="bog-chip">og:image</span>
							<strong><?php echo esc_html( $label ); ?></strong>
							<span class="bog-dim"><?php echo esc_html( BANNERS_OG_WIDTH . ' × ' . BANNERS_OG_HEIGHT ); ?></span>
							<?php self::render_uses( Banners_OG_Templates::contexts_for_kind( $kind ) ); ?>
						</div>
						<div class="bog-card__actions">
							<span class="bog-status" aria-live="polite"></span>
							<button type="button" class="button button-primary bog-generate">
								<?php esc_html_e( 'Generate and save banner', 'banners-og' ); ?>
							</button>
						</div>
					</div>

					<div class="bog-card__body">
						<div class="bog-fields">
							<?php foreach ( $fields as $key => $field ) : ?>
								<?php self::render_field( $key, $field, (string) ( $values[ $key ] ?? '' ), 'banners_og[' . $key . ']' ); ?>
							<?php endforeach; ?>

							<?php
							self::render_current(
								$image_url,
								__( 'No banner generated yet — this context has no og:image until you generate one.', 'banners-og' )
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
			<?php endforeach; ?>
		</div>
		<?php
	}
}
