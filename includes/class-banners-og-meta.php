<?php
/**
 * Open Graph and Twitter Card tags on the front end.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Meta {

	public static function init(): void {
		add_action( 'wp_head', [ __CLASS__, 'render' ], 2 );
	}

	public static function render(): void {
		// Stays quiet when an SEO plugin already prints the same tags.
		$has_seo_plugin = defined( 'WPSEO_VERSION' )
			|| class_exists( 'RankMath' )
			|| defined( 'AIOSEO_VERSION' )
			|| defined( 'SEOPRESS_VERSION' );

		/**
		 * Filters whether the plugin prints the og:* and twitter:* tags.
		 */
		if ( ! apply_filters( 'banners_og_output_tags', ! $has_seo_plugin ) ) {
			return;
		}

		$data = self::build();

		self::tag( 'og:locale', get_locale() );
		self::tag( 'og:site_name', (string) get_bloginfo( 'name' ) );
		self::tag( 'og:type', $data['type'] );
		self::tag( 'og:title', $data['title'] );
		self::tag( 'og:description', $data['description'] );
		self::tag( 'og:url', $data['url'] );

		if ( $data['image'] ) {
			self::tag( 'og:image', $data['image']['url'] );
			self::tag( 'og:image:width', (string) $data['image']['width'] );
			self::tag( 'og:image:height', (string) $data['image']['height'] );
			self::tag( 'og:image:type', $data['image']['mime'] );
		}

		self::tag( 'twitter:card', 'summary_large_image', 'name' );
		self::tag( 'twitter:title', $data['title'], 'name' );
		self::tag( 'twitter:description', $data['description'], 'name' );

		if ( $data['image'] ) {
			self::tag( 'twitter:image', $data['image']['url'], 'name' );
		}
	}

	private static function tag( string $property, string $content, string $attr = 'property' ): void {
		if ( '' === trim( $content ) ) {
			return;
		}

		printf(
			'<meta %1$s="%2$s" content="%3$s" />' . "\n",
			esc_attr( $attr ),
			esc_attr( $property ),
			esc_attr( wp_strip_all_tags( $content ) )
		);
	}

	/**
	 * @return array{title:string, description:string, url:string, type:string, image:array{url:string, width:int, height:int, mime:string}|null}
	 */
	private static function build(): array {
		$title       = wp_get_document_title();
		$description = (string) get_bloginfo( 'description' );
		$url         = home_url( '/' );
		$type        = 'website';
		$image       = self::current_image();

		if ( is_singular() ) {
			$post = get_queried_object();

			if ( $post instanceof WP_Post ) {
				$url  = (string) get_permalink( $post );
				$type = is_singular( 'post' ) ? 'article' : 'website';

				$settings = Banners_OG_Plugin::get_post_settings( $post->ID );
				$excerpt  = has_excerpt( $post )
					? get_the_excerpt( $post )
					: wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 32 );

				if ( ! empty( $settings['enabled'] ) && ! empty( $settings['sub'] ) ) {
					$description = (string) $settings['sub'];
				} elseif ( $excerpt ) {
					$description = (string) $excerpt;
				}
			}
		} elseif ( is_post_type_archive() ) {
			$url = (string) get_post_type_archive_link( (string) get_query_var( 'post_type' ) );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$term = get_queried_object();
			$link = $term instanceof WP_Term ? get_term_link( $term ) : '';
			$url  = is_string( $link ) && '' !== $link ? $link : $url;
		} elseif ( is_author() ) {
			$author = get_queried_object();
			$url    = $author instanceof WP_User ? (string) get_author_posts_url( $author->ID ) : $url;
		}

		/**
		 * Filters the whole payload before it becomes meta tags.
		 */
		return apply_filters( 'banners_og_meta_data', compact( 'title', 'description', 'url', 'type', 'image' ) );
	}

	/**
	 * Banner that answers for the current request, whatever the context is.
	 *
	 * Public so the SEO bridge publishes the same image as the tags printed
	 * here.
	 *
	 * @return array{url:string, width:int, height:int, mime:string}|null
	 */
	public static function current_image(): ?array {
		if ( is_singular() ) {
			$post = get_queried_object();

			if ( $post instanceof WP_Post ) {
				return self::post_image( $post->ID );
			}
		}

		return self::default_image( Banners_OG_Templates::default_kind_for_context() );
	}

	/**
	 * Banner of a single post, falling back to the default of the context.
	 *
	 * @return array{url:string, width:int, height:int, mime:string}|null
	 */
	private static function post_image( int $post_id ): ?array {
		$file  = (string) get_post_meta( $post_id, Banners_OG_Plugin::META_IMAGE, true );
		$image = self::payload( $file );

		if ( $image ) {
			return $image;
		}

		return self::default_image( Banners_OG_Templates::default_kind_for_context() );
	}

	/**
	 * @return array{url:string, width:int, height:int, mime:string}|null
	 */
	private static function default_image( string $kind ): ?array {
		$defaults = Banners_OG_Templates::defaults();
		$image    = self::payload( (string) ( $defaults[ $kind ]['image'] ?? '' ) );

		if ( $image ) {
			return $image;
		}

		// Falls back to the first layout while the others have no banner yet.
		$first = Banners_OG_Templates::first_kind();

		return $first !== $kind ? self::payload( (string) ( $defaults[ $first ]['image'] ?? '' ) ) : null;
	}

	/**
	 * @return array{url:string, width:int, height:int, mime:string}|null
	 */
	private static function payload( string $file ): ?array {
		$url = Banners_OG_Storage::image_url( $file );

		if ( ! $url ) {
			return null;
		}

		return [
			'url'    => $url,
			'width'  => BANNERS_OG_WIDTH,
			'height' => BANNERS_OG_HEIGHT,
			'mime'   => 'image/jpeg',
		];
	}
}
