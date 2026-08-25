<?php
/**
 * Activation: seeds the default copy and prepares the banners directory.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Activator {

	public static function activate(): void {
		if ( ! get_option( Banners_OG_Plugin::OPTION_DEFAULTS ) ) {
			Banners_OG_Templates::save_defaults( Banners_OG_Templates::shipped_defaults() );
		}

		if ( ! get_option( Banners_OG_Theme::OPTION ) ) {
			update_option( Banners_OG_Theme::OPTION, Banners_OG_Theme::defaults(), false );
		}
	}
}
