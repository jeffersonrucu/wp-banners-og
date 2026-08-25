<?php
/**
 * WooCommerce: a banner for every product, with the photo, the price and the
 * category of the product filled in on their own.
 *
 * Loaded only when WooCommerce is active. See README.md, "WooCommerce".
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Woocommerce {

	const KIND      = 'product';
	const POST_TYPE = 'product';

	public static function init(): void {
		add_filter( 'banners_og_post_types', [ __CLASS__, 'post_types' ] );
		add_filter( 'banners_og_kinds', [ __CLASS__, 'kinds' ] );
		add_filter( 'banners_og_fields', [ __CLASS__, 'fields' ] );
		add_filter( 'banners_og_fields_for_context', [ __CLASS__, 'fields_for_context' ], 10, 3 );
		add_filter( 'banners_og_template_defaults', [ __CLASS__, 'template_defaults' ], 10, 2 );
		add_filter( 'banners_og_post_defaults', [ __CLASS__, 'post_defaults' ], 10, 2 );
		add_filter( 'banners_og_default_kind_for_post_type', [ __CLASS__, 'default_kind_for_post_type' ], 10, 2 );
		add_filter( 'banners_og_default_kind_for_context', [ __CLASS__, 'default_kind_for_context' ] );
		add_action( 'banners_og_enqueue_assets', [ __CLASS__, 'enqueue' ] );
	}

	/**
	 * @param array<int, string> $types
	 *
	 * @return array<int, string>
	 */
	public static function post_types( array $types ): array {
		$types[] = self::POST_TYPE;

		return array_values( array_unique( $types ) );
	}

	/**
	 * @param array<string, string> $kinds
	 *
	 * @return array<string, string>
	 */
	public static function kinds( array $kinds ): array {
		$kinds[ self::KIND ] = __( 'Product', 'banners-og' );

		return $kinds;
	}

	/**
	 * Adds the price right after the subtitle, so the fields read in the same
	 * order as the banner, and lets the product layout print the brand too.
	 *
	 * @param array<string, array<string, mixed>> $fields
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields( array $fields ): array {
		$own = [
			'show_price' => [
				'label'       => __( 'Show the price on the banner', 'banners-og' ),
				'type'        => 'toggle',
				'description' => __( 'The price is always the current price of the product; this only says whether it is printed.', 'banners-og' ),
				'kinds'       => [ self::KIND ],
			],
			'show_photo' => [
				'label'       => __( 'Show the photo on the banner', 'banners-og' ),
				'type'        => 'toggle',
				'description' => '',
				'kinds'       => [ self::KIND ],
			],
			'photo'      => [
				'label'       => __( 'Photo', 'banners-og' ),
				'type'        => 'image',
				'description' => __( 'Empty uses the image of the product. It has to live on this same domain, otherwise the banner cannot be captured.', 'banners-og' ),
				'kinds'       => [ self::KIND ],
			],
		];

		if ( isset( $fields['brand']['kinds'] ) && is_array( $fields['brand']['kinds'] ) ) {
			$fields['brand']['kinds'][] = self::KIND;
		}

		if ( ! isset( $fields['sub'] ) ) {
			return array_merge( $fields, $own );
		}

		$out = [];

		foreach ( $fields as $key => $field ) {
			$out[ $key ] = $field;

			if ( 'sub' === $key ) {
				$out = array_merge( $out, $own );
			}
		}

		return $out;
	}

	/**
	 * The Banners OG screen has no product in front of it: picking one photo
	 * there would say nothing about the catalogue. A product with no image of
	 * its own already falls back to the brand mark.
	 *
	 * @param array<string, array<string, mixed>> $fields
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function fields_for_context( array $fields, string $kind, string $context ): array {
		if ( self::KIND === $kind && 'default' === $context ) {
			unset( $fields['photo'] );
		}

		return $fields;
	}

	/**
	 * @param array<string, string> $defaults
	 *
	 * @return array<string, string>
	 */
	public static function template_defaults( array $defaults, string $kind ): array {
		if ( self::KIND !== $kind ) {
			return $defaults;
		}

		return array_merge(
			$defaults,
			[
				'eyebrow'    => __( 'Store', 'banners-og' ),
				'title'      => (string) get_bloginfo( 'name' ),
				'sub'        => (string) get_bloginfo( 'description' ),
				'show_price' => '1',
				'show_photo' => '1',
				'foot'       => Banners_OG_Templates::site_host(),
			]
		);
	}

	/**
	 * Placeholders of the metabox: what the product itself already says.
	 *
	 * @param array<string, array<string, string>> $defaults
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function post_defaults( array $defaults, WP_Post $post ): array {
		$product = self::product( $post );

		if ( null === $product || ! isset( $defaults[ self::KIND ] ) ) {
			return $defaults;
		}

		// What the product leaves empty keeps answering to the copy saved on
		// the Banners OG screen.
		$from_product = array_filter(
			[
				'eyebrow' => self::category( $post ),
				'sub'     => self::summary( $product ),
				'photo'   => self::image_url( $product ),
			],
			static function ( string $value ): bool {
				return '' !== $value;
			}
		);

		$defaults[ self::KIND ] = array_merge( $defaults[ self::KIND ], $from_product );

		return $defaults;
	}

	public static function default_kind_for_post_type( string $kind, string $post_type ): string {
		return self::POST_TYPE === $post_type ? self::KIND : $kind;
	}

	public static function default_kind_for_context( string $kind ): string {
		if ( ! function_exists( 'is_product_taxonomy' ) ) {
			return $kind;
		}

		return is_product_taxonomy() || is_post_type_archive( self::POST_TYPE ) ? self::KIND : $kind;
	}

	public static function enqueue(): void {
		wp_enqueue_style(
			'banners-og-woocommerce',
			BANNERS_OG_URL . 'assets/css/woocommerce.css',
			[ 'banners-og-admin' ],
			Banners_OG_Plugin::asset_version( 'assets/css/woocommerce.css' )
		);

		wp_enqueue_script(
			'banners-og-woocommerce',
			BANNERS_OG_URL . 'assets/js/woocommerce.js',
			[ 'banners-og-banner' ],
			Banners_OG_Plugin::asset_version( 'assets/js/woocommerce.js' ),
			true
		);

		// The price is never typed: it is whatever the product costs right now.
		$post    = get_post();
		$product = $post instanceof WP_Post ? self::product( $post ) : null;

		wp_localize_script(
			'banners-og-woocommerce',
			'BannersOGWoo',
			[ 'price' => null !== $product ? self::price( $product ) : '' ]
		);
	}

	private static function product( WP_Post $post ): ?WC_Product {
		if ( self::POST_TYPE !== $post->post_type || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}

		$product = wc_get_product( $post );

		return $product instanceof WC_Product ? $product : null;
	}

	private static function image_url( ?WC_Product $product ): string {
		$image_id = null !== $product ? (int) $product->get_image_id() : 0;
		$url      = Banners_OG_Storage::drawable_url( $image_id, 'large' );

		/**
		 * Filters the product photo printed by the product layout. It has to be
		 * same-origin, otherwise the capture fails.
		 */
		return (string) apply_filters( 'banners_og_product_image_url', $url, $product );
	}

	private static function price( WC_Product $product ): string {
		$html = (string) $product->get_price_html();

		// On sale WooCommerce prints the old price in <del> and repeats both in
		// screen reader spans; on the banner that would read as three prices.
		$html = (string) preg_replace( '#<del\b[^>]*>.*?</del>#is', '', $html );
		$html = (string) preg_replace( '#<span[^>]*class="[^"]*screen-reader-text[^"]*"[^>]*>.*?</span>#is', '', $html );
		$text = wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES, (string) get_bloginfo( 'charset' ) ) );

		return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	}

	private static function category( WP_Post $post ): string {
		$terms = get_the_terms( $post, 'product_cat' );

		if ( ! is_array( $terms ) || [] === $terms ) {
			return '';
		}

		$term = reset( $terms );

		return $term->name;
	}

	private static function summary( WC_Product $product ): string {
		$text = wp_strip_all_tags( strip_shortcodes( (string) $product->get_short_description() ) );

		return '' !== trim( $text ) ? wp_trim_words( $text, 24 ) : '';
	}
}
