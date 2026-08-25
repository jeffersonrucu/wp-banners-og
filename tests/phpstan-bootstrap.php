<?php
/**
 * Constants PHPStan needs to analyse the plugin outside of WordPress.
 */

declare(strict_types=1);

define('WPINC', 'wp-includes');
define('BANNERS_OG_VERSION', '2.0.0');
define('BANNERS_OG_NAME', 'Banners OG');
define('BANNERS_OG_FILE', __DIR__ . '/../banners-og.php');
define('BANNERS_OG_DIR', __DIR__ . '/../');
define('BANNERS_OG_URL', 'https://example.com/wp-content/plugins/banners-og/');
define('BANNERS_OG_WIDTH', 1200);
define('BANNERS_OG_HEIGHT', 630);
