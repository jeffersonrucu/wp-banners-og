<?php
/**
 * Visual theme of the banners: palette, typography and brand images.
 *
 * Everything a site needs to look like itself lives here, so the templates stay
 * brand agnostic and read their colors from CSS custom properties.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Theme {

	const OPTION = 'banners_og_theme';

	// Sentinel for "I want to type the font stacks myself".
	const PRESET_CUSTOM = 'custom';

	/**
	 * Palette keys and their labels, used by the settings screen.
	 *
	 * @return array<string, string>
	 */
	public static function colors(): array {
		return [
			'bg'     => __( 'Light background', 'banners-og' ),
			'bg_alt' => __( 'Alternate background', 'banners-og' ),
			'dark'   => __( 'Dark background', 'banners-og' ),
			'accent' => __( 'Headings', 'banners-og' ),
			'text'   => __( 'Body text', 'banners-og' ),
			'muted'  => __( 'Supporting text', 'banners-og' ),
			'rule'   => __( 'Rules and dividers', 'banners-og' ),
		];
	}

	/**
	 * Ready-made font pairings, so picking a typography is a choice and not a
	 * CSS font stack typed by hand. Only fonts that ship with the operating
	 * systems, so no external request is needed.
	 *
	 * @return array<string, array{label:string, heading:string, body:string}>
	 */
	public static function font_presets(): array {
		$sans  = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
		$serif = 'Georgia, "Times New Roman", Times, serif';
		$mono  = 'ui-monospace, SFMono-Regular, Menlo, Consolas, "Liberation Mono", monospace';

		$presets = [
			'sans'      => [
				'label'   => __( 'Modern — sans serif throughout', 'banners-og' ),
				'heading' => $sans,
				'body'    => $sans,
			],
			'classic'   => [
				'label'   => __( 'Classic — serif headings, sans serif text', 'banners-og' ),
				'heading' => $serif,
				'body'    => $sans,
			],
			'editorial' => [
				'label'   => __( 'Editorial — serif throughout', 'banners-og' ),
				'heading' => $serif,
				'body'    => $serif,
			],
			'technical' => [
				'label'   => __( 'Technical — sans serif headings, monospaced text', 'banners-og' ),
				'heading' => $sans,
				'body'    => $mono,
			],
		];

		/**
		 * Filters the font pairings offered on the appearance screen.
		 */
		$filtered = apply_filters( 'banners_og_font_presets', $presets );
		$out      = [];

		foreach ( is_array( $filtered ) ? $filtered : $presets as $key => $preset ) {
			$key = sanitize_key( (string) $key );

			if ( '' === $key || self::PRESET_CUSTOM === $key || ! is_array( $preset ) ) {
				continue;
			}

			$out[ $key ] = [
				'label'   => (string) ( $preset['label'] ?? $key ),
				'heading' => self::sanitize_font_family( (string) ( $preset['heading'] ?? '' ) ),
				'body'    => self::sanitize_font_family( (string) ( $preset['body'] ?? '' ) ),
			];
		}

		return [] !== $out ? $out : $presets;
	}

	/**
	 * Heading and body stacks in use: from the chosen preset, or the manual
	 * fields when the preset is "custom".
	 *
	 * @return array{heading:string, body:string}
	 */
	public static function fonts(): array {
		$theme   = self::get();
		$presets = self::font_presets();
		$preset  = (string) $theme['font_preset'];

		if ( isset( $presets[ $preset ] ) ) {
			return [
				'heading' => $presets[ $preset ]['heading'],
				'body'    => $presets[ $preset ]['body'],
			];
		}

		return [
			'heading' => self::sanitize_font_family( (string) $theme['font_heading'] ),
			'body'    => self::sanitize_font_family( (string) $theme['font_body'] ),
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'bg'           => '#f7f7f5',
			'bg_alt'       => '#ece9e4',
			'dark'         => '#1f2933',
			'accent'       => '#1f2933',
			'text'         => '#4b5563',
			'muted'        => '#9ca3af',
			'rule'         => '#cbd2d9',
			'font_preset'  => 'classic',
			'font_heading' => 'Georgia, "Times New Roman", Times, serif',
			'font_body'    => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
			'font_css_url' => '',
			'brand'        => '',
			'logo_id'      => 0,
			'mark_id'      => 0,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, [] );

		return wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
	}

	/**
	 * Brand name printed by the templates that have no logo slot.
	 */
	public static function brand(): string {
		$theme = self::get();
		$brand = trim( (string) $theme['brand'] );

		return '' !== $brand ? $brand : (string) get_bloginfo( 'name' );
	}

	/**
	 * Horizontal logo. Falls back to the theme custom logo.
	 */
	public static function logo_url(): string {
		$theme = self::get();
		$url   = self::attachment_url( (int) $theme['logo_id'] );

		if ( '' !== $url ) {
			return $url;
		}

		return self::attachment_url( (int) get_theme_mod( 'custom_logo' ) );
	}

	/**
	 * Square mark/symbol. Falls back to the site icon.
	 */
	public static function mark_url(): string {
		$theme = self::get();
		$url   = self::attachment_url( (int) $theme['mark_id'] );

		if ( '' !== $url ) {
			return $url;
		}

		return (string) get_site_icon_url( 512 );
	}

	/**
	 * Optional web font stylesheet (empty by default: no external request).
	 */
	public static function font_css_url(): string {
		$theme = self::get();

		return (string) $theme['font_css_url'];
	}

	/**
	 * Custom properties consumed by the template CSS. Custom layouts should
	 * reuse these instead of hardcoding colors.
	 */
	public static function css_variables(): string {
		$theme = self::get();
		$lines = [];

		foreach ( array_keys( self::colors() ) as $key ) {
			$color = sanitize_hex_color( (string) $theme[ $key ] );

			if ( $color ) {
				$lines[] = sprintf( '--bog-%s:%s;', str_replace( '_', '-', $key ), $color );
			}
		}

		$fonts   = self::fonts();
		$lines[] = sprintf( '--bog-font-heading:%s;', $fonts['heading'] );
		$lines[] = sprintf( '--bog-font-body:%s;', $fonts['body'] );

		// Not used by the shipped layouts, which stay away from brand art as
		// background; exposed because a custom layout may well want it.
		$mark    = self::mark_url();
		$lines[] = sprintf( '--bog-mark-url:%s;', '' !== $mark ? sprintf( 'url("%s")', esc_url( $mark ) ) : 'none' );

		return ':root{' . implode( '', $lines ) . '}';
	}

	/**
	 * Font stacks end up inside a CSS declaration, so anything that could close
	 * it or open a new rule is dropped.
	 */
	public static function sanitize_font_family( string $value ): string {
		$value = preg_replace( '/[^A-Za-z0-9 ,._\'"-]/', '', $value );
		$value = trim( (string) $value );

		return '' !== $value ? $value : 'sans-serif';
	}

	/**
	 * @param array<string, mixed>|mixed $input
	 *
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : [];
		$defaults = self::defaults();
		$out      = [];

		foreach ( array_keys( self::colors() ) as $key ) {
			$color       = sanitize_hex_color( sanitize_text_field( (string) ( $input[ $key ] ?? '' ) ) );
			$out[ $key ] = $color ? $color : $defaults[ $key ];
		}

		$preset             = sanitize_key( (string) ( $input['font_preset'] ?? '' ) );
		$out['font_preset'] = isset( self::font_presets()[ $preset ] ) || self::PRESET_CUSTOM === $preset
			? $preset
			: $defaults['font_preset'];

		$out['font_heading'] = self::sanitize_font_family( sanitize_text_field( (string) ( $input['font_heading'] ?? '' ) ) );
		$out['font_body']    = self::sanitize_font_family( sanitize_text_field( (string) ( $input['font_body'] ?? '' ) ) );
		$out['font_css_url'] = esc_url_raw( (string) ( $input['font_css_url'] ?? '' ), [ 'http', 'https' ] );
		$out['brand']        = sanitize_text_field( (string) ( $input['brand'] ?? '' ) );
		$out['logo_id']      = absint( $input['logo_id'] ?? 0 );
		$out['mark_id']      = absint( $input['mark_id'] ?? 0 );

		return $out;
	}

	private static function attachment_url( int $attachment_id ): string {
		if ( $attachment_id < 1 ) {
			return '';
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'full' );

		return is_string( $url ) ? $url : '';
	}
}
