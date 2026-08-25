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
	 * Field types the editors know how to render and sanitize.
	 */
	const TYPES = [ 'text', 'textarea', 'image', 'toggle' ];

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
	 * Editable text fields of the layouts.
	 *
	 * A custom layout may add its own fields through the `banners_og_fields`
	 * filter; supported types are `text`, `textarea`, `image` (media picker,
	 * stores a URL) and `toggle` (checkbox, stores `1` or an empty string). A
	 * field that only
	 * makes sense to some layouts lists them in `kinds` — an empty `kinds`
	 * belongs to every layout.
	 *
	 * @return array<string, array{label:string, type:string, description:string, placeholder:string, kinds:array<int, string>}>
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
			'brand'   => [
				'label'       => __( 'Brand', 'banners-og' ),
				'type'        => 'text',
				'description' => __( 'Leave empty to use the brand name of the Appearance screen.', 'banners-og' ),
				'placeholder' => Banners_OG_Theme::brand(),
				'kinds'       => [ 'cover', 'feature', 'article' ],
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

			$type = (string) ( $field['type'] ?? 'text' );

			$out[ $key ] = [
				'label'       => (string) ( $field['label'] ?? $key ),
				'type'        => in_array( $type, self::TYPES, true ) ? $type : 'text',
				'description' => (string) ( $field['description'] ?? '' ),
				'placeholder' => (string) ( $field['placeholder'] ?? '' ),
				'kinds'       => self::sanitize_kinds( $field['kinds'] ?? [] ),
			];
		}

		return [] !== $out ? $out : $fields;
	}

	/**
	 * Fields one layout shows: the shared ones, plus the ones declared for it.
	 *
	 * @return array<string, array{label:string, type:string, description:string, placeholder:string, kinds:array<int, string>}>
	 */
	public static function fields_for_kind( string $kind, string $context = 'default' ): array {
		$fields = array_filter(
			self::fields(),
			static function ( array $field ) use ( $kind ): bool {
				return [] === $field['kinds'] || in_array( $kind, $field['kinds'], true );
			}
		);

		/**
		 * Filters the fields of a layout for the screen about to render them:
		 * `default` is the Banners OG screen, `post` is the metabox. The same
		 * field can mean different things on each — the site-wide default and
		 * the value of one content.
		 */
		$filtered = apply_filters( 'banners_og_fields_for_context', $fields, $kind, $context );

		return is_array( $filtered ) ? $filtered : $fields;
	}

	/**
	 * @param mixed $kinds
	 *
	 * @return array<int, string>
	 */
	private static function sanitize_kinds( $kinds ): array {
		if ( ! is_array( $kinds ) ) {
			return [];
		}

		$out = [];

		foreach ( $kinds as $kind ) {
			$kind = is_scalar( $kind ) ? sanitize_key( (string) $kind ) : '';

			if ( '' !== $kind ) {
				$out[] = $kind;
			}
		}

		return $out;
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
	 * Every public post type with an editing screen: anything with a URL of its
	 * own is something that gets shared, and the meta tags already answer for
	 * it. Attachments are out — their page is the file, not a banner.
	 *
	 * @return array<int, string>
	 */
	public static function post_types(): array {
		$public = get_post_types(
			[
				'public'  => true,
				'show_ui' => true,
			]
		);

		$types = apply_filters( 'banners_og_post_types', array_values( array_diff( $public, [ 'attachment' ] ) ) );

		return array_values( array_filter( array_map( 'strval', is_array( $types ) ? $types : [] ) ) );
	}

	/**
	 * Taxonomies whose terms get their own banner.
	 *
	 * Same rule as the post types: every public taxonomy with an editing
	 * screen, since each of its terms has an archive of its own. Post formats
	 * are out — they are a flag on the post, not an archive anyone shares.
	 *
	 * @return array<int, string>
	 */
	public static function taxonomies(): array {
		$public = get_taxonomies(
			[
				'public'  => true,
				'show_ui' => true,
			]
		);

		$taxonomies = apply_filters( 'banners_og_taxonomies', array_values( array_diff( $public, [ 'post_format' ] ) ) );

		return array_values( array_filter( array_map( 'strval', is_array( $taxonomies ) ? $taxonomies : [] ) ) );
	}

	public static function default_kind_for_taxonomy( string $taxonomy ): string {
		$map = [
			'category' => 'article',
			'post_tag' => 'article',
		];

		$kind = apply_filters( 'banners_og_default_kind_for_taxonomy', $map[ $taxonomy ] ?? 'cover', $taxonomy );
		$kind = sanitize_key( (string) $kind );

		return self::is_kind( $kind ) ? $kind : self::first_kind();
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
	 * Sanitizes the posted copy. Values arrive raw, straight from the request:
	 * this is where every field of the plugin is cleaned, by its own type.
	 *
	 * @param array<string, mixed> $raw
	 *
	 * @return array<string, string>
	 */
	public static function sanitize_fields( array $raw ): array {
		$out = [];

		foreach ( self::fields() as $key => $field ) {
			$value = isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? (string) $raw[ $key ] : '';

			switch ( $field['type'] ) {
				case 'textarea':
					$out[ $key ] = sanitize_textarea_field( $value );
					break;
				case 'image':
					$out[ $key ] = esc_url_raw( $value );
					break;
				case 'toggle':
					$out[ $key ] = '' !== $value ? '1' : '';
					break;
				default:
					$out[ $key ] = sanitize_text_field( $value );
			}
		}

		return $out;
	}

	public static function site_host(): string {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		return (string) ( preg_replace( '#^www\.#', '', $host ) ?: $host );
	}
}
