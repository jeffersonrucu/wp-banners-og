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

$banners_og_attachments = get_posts(
	[
		'post_type'      => 'attachment',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
		'meta_key'       => '_banners_og_managed', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- runs once, on uninstall.
	]
);

foreach ( $banners_og_attachments as $banners_og_attachment_id ) {
	wp_delete_attachment( (int) $banners_og_attachment_id, true );
}

$banners_og_uploads = wp_get_upload_dir();
$banners_og_dir     = $banners_og_uploads['basedir'] . '/banners-og';

if ( is_dir( $banners_og_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';

	global $wp_filesystem;

	if ( WP_Filesystem() ) {
		$wp_filesystem->delete( $banners_og_dir, true );
	}
}
