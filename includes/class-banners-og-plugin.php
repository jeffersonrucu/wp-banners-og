<?php
/**
 * Bootstrap: storage keys, wiring and the assets shared by both editors.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Plugin {

	const OPTION_DEFAULTS = 'banners_og_defaults';
	const META_SETTINGS   = '_banners_og';
	const META_IMAGE      = '_banners_og_image';
	const NONCE_ACTION    = 'banners_og_nonce';

	public static function init(): void {
		Banners_OG_Meta::init();
		Banners_OG_Ajax::init();

		if ( is_admin() ) {
			Banners_OG_Admin::init();
			Banners_OG_Settings::init();
			Banners_OG_Metabox::init();
		}
	}

	/**
	 * Per-content banner settings, with the layout of the post type as fallback.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_post_settings( int $post_id ): array {
		$meta = get_post_meta( $post_id, self::META_SETTINGS, true );
		$meta = is_array( $meta ) ? $meta : [];

		$defaults = Banners_OG_Templates::empty_fields() + [
			'enabled' => 0,
			'kind'    => Banners_OG_Templates::default_kind_for_post_type( (string) ( get_post_type( $post_id ) ?: 'post' ) ),
		];

		return wp_parse_args( $meta, $defaults );
	}

	/**
	 * Data handed to the browser side of the generator.
	 *
	 * @return array<string, mixed>
	 */
	public static function js_config(): array {
		$fields = [];

		foreach ( Banners_OG_Templates::fields() as $key => $field ) {
			$fields[] = [
				'key'  => $key,
				'type' => $field['type'],
			];
		}

		return [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
			'width'   => BANNERS_OG_WIDTH,
			'height'  => BANNERS_OG_HEIGHT,
			'fields'  => $fields,
			'kinds'   => array_keys( Banners_OG_Templates::kinds() ),
			'brand'   => Banners_OG_Theme::brand(),
			'images'  => [
				'logo' => Banners_OG_Theme::logo_url(),
				'mark' => Banners_OG_Theme::mark_url(),
			],
			'i18n'    => [
				'generating' => __( 'Building banner…', 'banners-og' ),
				'saving'     => __( 'Saving…', 'banners-og' ),
				'saved'      => __( 'Banner updated.', 'banners-og' ),
				'error'      => __( 'The banner could not be generated.', 'banners-og' ),
				'current'    => __( 'Current banner:', 'banners-og' ),
			],
		];
	}

	/**
	 * Assets shared by the settings page and the metabox.
	 */
	public static function enqueue_editor_assets(): void {
		$font_css = Banners_OG_Theme::font_css_url();

		if ( '' !== $font_css ) {
			wp_enqueue_style( 'banners-og-fonts', $font_css, [], BANNERS_OG_VERSION );
		}

		wp_enqueue_style( 'banners-og-admin', BANNERS_OG_URL . 'assets/css/admin.css', [], BANNERS_OG_VERSION );
		wp_add_inline_style( 'banners-og-admin', Banners_OG_Theme::css_variables() );

		wp_enqueue_script(
			'banners-og-html2canvas',
			BANNERS_OG_URL . 'assets/vendor/html2canvas/html2canvas.min.js',
			[],
			'1.4.1',
			true
		);

		wp_enqueue_script(
			'banners-og-banner',
			BANNERS_OG_URL . 'assets/js/banner.js',
			[ 'banners-og-html2canvas' ],
			BANNERS_OG_VERSION,
			true
		);

		wp_localize_script( 'banners-og-banner', 'BannersOGData', self::js_config() );

		/**
		 * Fires after the generator assets are enqueued.
		 *
		 * Hook here to enqueue the CSS and JS of a custom layout, depending on
		 * the `banners-og-admin` style and the `banners-og-banner` script.
		 */
		do_action( 'banners_og_enqueue_assets' );
	}
}
