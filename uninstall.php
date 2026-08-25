<?php
/**
 * Removes everything the plugin created: options, post meta and the generated
 * banner files.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

delete_option( 'banners_og_defaults' );
delete_option( 'banners_og_theme' );

delete_post_meta_by_key( '_banners_og' );
delete_post_meta_by_key( '_banners_og_image' );

$banners_og_uploads = wp_get_upload_dir();
$banners_og_dir     = $banners_og_uploads['basedir'] . '/banners-og';

if ( is_dir( $banners_og_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	global $wp_filesystem;

	if ( WP_Filesystem() ) {
		$wp_filesystem->delete( $banners_og_dir, true );
	}
}
