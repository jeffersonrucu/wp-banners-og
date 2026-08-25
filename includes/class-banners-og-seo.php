<?php
/**
 * Bridge to the SEO plugins.
 *
 * When Yoast SEO, Rank Math, All in One SEO or SEOPress is active the plugin
 * stops printing its own og:* tags, so the generated banner would never reach a
 * social network. Here it is handed over to whoever is printing the tags.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Seo {

	/**
	 * Banner of the current request: array once resolved, null when there is
	 * none, false while it has not been looked up yet.
	 *
	 * @var array{url:string, width:int, height:int, mime:string}|null|false
	 */
	private static $image = false;

	public static function init(): void {
		// Yoast SEO. The image filters only run when Yoast already found an
		// image of its own, so a post with no featured image keeps its choice.
		add_filter( 'wpseo_opengraph_image', [ __CLASS__, 'url' ] );
		add_filter( 'wpseo_twitter_image', [ __CLASS__, 'url' ] );
		add_filter( 'wpseo_opengraph_image_width', [ __CLASS__, 'width' ] );
		add_filter( 'wpseo_opengraph_image_height', [ __CLASS__, 'height' ] );
		add_filter( 'wpseo_opengraph_image_type', [ __CLASS__, 'mime' ] );

		// Rank Math.
		add_filter( 'rank_math/opengraph/facebook/image', [ __CLASS__, 'url' ] );
		add_filter( 'rank_math/opengraph/twitter/image', [ __CLASS__, 'url' ] );

		// SEOPress.
		add_filter( 'seopress_social_og_thumb', [ __CLASS__, 'url' ] );
		add_filter( 'seopress_social_twitter_card_thumb', [ __CLASS__, 'url' ] );

		// All in One SEO.
		add_filter( 'aioseo_facebook_tags', [ __CLASS__, 'aioseo_tags' ] );
		add_filter( 'aioseo_twitter_tags', [ __CLASS__, 'aioseo_tags' ] );
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function url( $value ) {
		$image = self::image();

		return null !== $image ? $image['url'] : $value;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function width( $value ) {
		return null !== self::image() ? BANNERS_OG_WIDTH : $value;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function height( $value ) {
		return null !== self::image() ? BANNERS_OG_HEIGHT : $value;
	}

	/**
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	public static function mime( $value ) {
		$image = self::image();

		return null !== $image ? $image['mime'] : $value;
	}

	/**
	 * All in One SEO hands over the whole tag list. Its shape is not part of a
	 * public contract, so only a tag that is already a URL string is replaced.
	 *
	 * @param mixed $tags
	 *
	 * @return mixed
	 */
	public static function aioseo_tags( $tags ) {
		$image = self::image();

		if ( null === $image || ! is_array( $tags ) ) {
			return $tags;
		}

		foreach ( [ 'og:image', 'og:image:secure_url', 'twitter:image' ] as $tag ) {
			if ( isset( $tags[ $tag ] ) && is_string( $tags[ $tag ] ) ) {
				$tags[ $tag ] = $image['url'];
			}
		}

		foreach ( [
			'og:image:width'  => BANNERS_OG_WIDTH,
			'og:image:height' => BANNERS_OG_HEIGHT,
		] as $tag => $size ) {
			if ( isset( $tags[ $tag ] ) && is_scalar( $tags[ $tag ] ) ) {
				$tags[ $tag ] = (string) $size;
			}
		}

		return $tags;
	}

	/**
	 * @return array{url:string, width:int, height:int, mime:string}|null
	 */
	private static function image(): ?array {
		if ( false !== self::$image ) {
			return self::$image;
		}

		// The banner of the request is read from the main query, which only
		// answers for a regular front end render — not the admin, not REST.
		$renders_tags = ! is_admin() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST );

		/**
		 * Filters whether the banner replaces the image chosen by the SEO
		 * plugin. Returning false leaves the SEO plugin alone.
		 */
		self::$image = $renders_tags && apply_filters( 'banners_og_seo_bridge', true )
			? Banners_OG_Meta::current_image()
			: null;

		return self::$image;
	}
}
