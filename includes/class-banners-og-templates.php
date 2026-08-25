<?php
/**
 * Layout registry: which banner layouts exist, which fields they expose and
 * what each one falls back to.
 *
 * Everything here is filterable so a site can register its own layout without
 * touching the plugin. See README.md, "Registering a custom layout".
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Templates {

	/**
	 * Layouts available in the editors (kind => label).
	 *
	 * @return array<string, string>
	 */
	public static function kinds(): array {
		$kinds = [
			'cover'   => __( 'Cover', 'banners-og' ),
			'feature' => __( 'Feature', 'banners-og' ),
			'article' => __( 'Article', 'banners-og' ),
			'profile' => __( 'Profile', 'banners-og' ),
		];

		$kinds = apply_filters( 'banners_og_kinds', $kinds );
		$kinds = is_array( $kinds ) ? array_filter( $kinds, 'is_string' ) : [];

		return [] !== $kinds ? $kinds : [ 'cover' => __( 'Cover', 'banners-og' ) ];
	}

	public static function is_kind( string $kind ): bool {
		return array_key_exists( $kind, self::kinds() );
	}

	public static function first_kind(): string {
		$kinds = array_keys( self::kinds() );

		return (string) reset( $kinds );
	}

	/**
	 * Editable text fields shared by every layout.
	 *
	 * A custom layout may add its own fields through the `banners_og_fields`
	 * filter; supported types are `text` and `textarea`.
	 *
	 * @return array<string, array{label:string, type:string, description:string}>
	 */
	public static function fields(): array {
		$fields = [
			'eyebrow' => [
				'label'       => __( 'Eyebrow', 'banners-og' ),
				'type'        => 'text',
				'description' => '',
			],
			'title'   => [
				'label'       => __( 'Title', 'banners-og' ),
				'type'        => 'text',
				'description' => __( 'Leave empty to use the post title.', 'banners-og' ),
			],
			'sub'     => [
				'label'       => __( 'Subtitle', 'banners-og' ),
				'type'        => 'textarea',
				'description' => '',
			],
			'foot'    => [
				'label'       => __( 'Footer (displayed URL)', 'banners-og' ),
				'type'        => 'text',
				'description' => '',
			],
		];

		$filtered = apply_filters( 'banners_og_fields', $fields );
		$out      = [];

		foreach ( is_array( $filtered ) ? $filtered : $fields as $key => $field ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || ! is_array( $field ) ) {
				continue;
			}

			$out[ $key ] = [
				'label'       => (string) ( $field['label'] ?? $key ),
				'type'        => 'textarea' === ( $field['type'] ?? 'text' ) ? 'textarea' : 'text',
				'description' => (string) ( $field['description'] ?? '' ),
			];
		}

		return [] !== $out ? $out : $fields;
	}

	/**
	 * Empty value for every known field, used as the sanitizing schema.
	 *
	 * @return array<string, string>
	 */
	public static function empty_fields(): array {
		return array_fill_keys( array_keys( self::fields() ), '' );
	}

	/**
	 * Shipped default copy for each layout, derived from the site itself.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function shipped_defaults(): array {
		$host    = self::site_host();
		$name    = (string) get_bloginfo( 'name' );
		$tagline = (string) get_bloginfo( 'description' );

		$base = [
			'cover'   => [
				'eyebrow' => '',
				'title'   => $name,
				'sub'     => $tagline,
				'foot'    => $host,
			],
			'feature' => [
				'eyebrow' => __( 'Highlight', 'banners-og' ),
				'title'   => $name,
				'sub'     => $tagline,
				'foot'    => $host,
			],
			'article' => [
				'eyebrow' => __( 'From the blog', 'banners-og' ),
				'title'   => $name,
				'sub'     => $tagline,
				'foot'    => $host,
			],
			'profile' => [
				'eyebrow' => __( 'Team', 'banners-og' ),
				'title'   => $name,
				'sub'     => $tagline,
				'foot'    => $host,
			],
		];

		$out = [];

		foreach ( array_keys( self::kinds() ) as $kind ) {
			$shipped = isset( $base[ $kind ] ) ? $base[ $kind ] : [
				'title' => $name,
				'sub'   => $tagline,
				'foot'  => $host,
			];

			/**
			 * Filters the default copy of a layout. Required for layouts added
			 * through `banners_og_kinds`.
			 */
			$shipped = apply_filters( 'banners_og_template_defaults', $shipped, $kind );

			$out[ $kind ] = wp_parse_args( is_array( $shipped ) ? $shipped : [], self::empty_fields() + [ 'image' => '' ] );
		}

		return $out;
	}

	/**
	 * Saved defaults merged over the shipped ones.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function defaults(): array {
		$saved = get_option( Banners_OG_Plugin::OPTION_DEFAULTS, [] );
		$saved = is_array( $saved ) ? $saved : [];
		$out   = [];

		foreach ( self::shipped_defaults() as $kind => $shipped ) {
			$out[ $kind ] = wp_parse_args( is_array( $saved[ $kind ] ?? null ) ? $saved[ $kind ] : [], $shipped );
		}

		return $out;
	}

	/**
	 * @param array<string, array<string, string>> $defaults
	 */
	public static function save_defaults( array $defaults ): void {
		update_option( Banners_OG_Plugin::OPTION_DEFAULTS, $defaults, false );
	}

	/**
	 * Post types that get the per-content metabox.
	 *
	 * @return array<int, string>
	 */
	public static function post_types(): array {
		$types = apply_filters( 'banners_og_post_types', [ 'post', 'page' ] );

		return array_values( array_filter( array_map( 'strval', is_array( $types ) ? $types : [] ) ) );
	}

	public static function default_kind_for_post_type( string $post_type ): string {
		$map = [
			'post' => 'article',
			'page' => 'cover',
		];

		$kind = apply_filters( 'banners_og_default_kind_for_post_type', $map[ $post_type ] ?? 'cover', $post_type );
		$kind = sanitize_key( (string) $kind );

		return self::is_kind( $kind ) ? $kind : self::first_kind();
	}

	/**
	 * Layout used for the archives and listings of the front end.
	 */
	public static function default_kind_for_context(): string {
		$kind = 'cover';

		if ( is_front_page() ) {
			$kind = 'cover';
		} elseif ( is_home() || is_singular( 'post' ) || is_category() || is_tag() || is_author() || is_date() ) {
			$kind = 'article';
		} elseif ( is_singular() ) {
			$kind = self::default_kind_for_post_type( (string) ( get_post_type() ?: 'page' ) );
		} elseif ( is_post_type_archive() ) {
			$kind = self::default_kind_for_post_type( (string) get_query_var( 'post_type' ) );
		}

		$kind = sanitize_key( (string) apply_filters( 'banners_og_default_kind_for_context', $kind ) );

		return self::is_kind( $kind ) ? $kind : self::first_kind();
	}

	/**
	 * @param array<string, mixed> $raw
	 *
	 * @return array<string, string>
	 */
	public static function sanitize_fields( array $raw ): array {
		$out = [];

		foreach ( self::fields() as $key => $field ) {
			$value = (string) ( $raw[ $key ] ?? '' );

			$out[ $key ] = 'textarea' === $field['type']
				? sanitize_textarea_field( $value )
				: sanitize_text_field( $value );
		}

		return $out;
	}

	public static function site_host(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return (string) ( preg_replace( '#^www\.#', '', $host ) ?: $host );
	}
}
