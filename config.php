<?php
/**
 * Plugin-wide constants.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

define( 'BANNERS_OG_VERSION', '2.2.0' );
define( 'BANNERS_OG_NAME', 'Banners OG' );
define( 'BANNERS_OG_FILE', __DIR__ . '/banners-og.php' );
define( 'BANNERS_OG_DIR', plugin_dir_path( __FILE__ ) );
define( 'BANNERS_OG_URL', plugin_dir_url( __FILE__ ) );

// Open Graph recommends 1200x630; the generator and the meta tags share these.
define( 'BANNERS_OG_WIDTH', 1200 );
define( 'BANNERS_OG_HEIGHT', 630 );
