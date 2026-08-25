<?php
/**
 * @wordpress-plugin
 *
 * Plugin Name:       Banners OG
 * Plugin URI:        https://github.com/jeffersonrucu/wp-banners-og
 * Description:       Builds 1200x630 Open Graph banners in the WordPress admin and publishes them as og:image. No external service, no GD or Imagick.
 * Version:           2.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            Jefferson Oliveira
 * Author URI:        https://github.com/jeffersonrucu
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       banners-og
 * Domain Path:       /languages
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once __DIR__ . '/config.php';

require_once BANNERS_OG_DIR . 'includes/class-banners-og-theme.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-templates.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-storage.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-plugin.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-activator.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-meta.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-seo.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-ajax.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-admin.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-settings.php';
require_once BANNERS_OG_DIR . 'includes/class-banners-og-metabox.php';

register_activation_hook( __FILE__, [ 'Banners_OG_Activator', 'activate' ] );

add_action( 'plugins_loaded', [ 'Banners_OG_Plugin', 'init' ] );
