<?php
/**
 * "Diagnostics" screen: where the banners are written, which URL they are
 * served from and what the browser gets when it asks for one.
 *
 * The plugin writes plain files instead of attachments, so a site that offloads
 * uploads to a bucket is where this usually goes wrong. This screen says so out
 * loud instead of leaving a broken og:image behind.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Status {

	const MENU_SLUG    = 'banners-og-status';
	const CHECK_ACTION = 'banners_og_check_urls';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ], 12 );
	}

	public static function register_menu(): void {
		add_submenu_page(
			Banners_OG_Admin::MENU_SLUG,
			__( 'Banner diagnostics', 'banners-og' ),
			__( 'Diagnostics', 'banners-og' ),
			'manage_options',
			self::MENU_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$checking  = isset( $_GET['bog-check'] ) && check_admin_referer( self::CHECK_ACTION );
		$report    = self::report( (bool) $checking );
		$check_url = wp_nonce_url(
			add_query_arg(
				[
					'page'      => self::MENU_SLUG,
					'bog-check' => '1',
				],
				admin_url( 'admin.php' )
			),
			self::CHECK_ACTION
		);
		?>
		<div class="wrap bog-wrap">
			<h1><?php esc_html_e( 'Banner diagnostics', 'banners-og' ); ?></h1>

			<p class="bog-intro">
				<?php esc_html_e( 'Where the banners are written, which URL they are published as, and what a request for that URL answers. Copy the report at the bottom when reporting a problem.', 'banners-og' ); ?>
			</p>

			<p>
				<a class="button button-primary" href="<?php echo esc_url( $check_url ); ?>">
					<?php esc_html_e( 'Request the banner URLs now', 'banners-og' ); ?>
				</a>
				<span class="description">
					<?php esc_html_e( 'Asks the server for each banner and shows the HTTP status it answers.', 'banners-og' ); ?>
				</span>
			</p>

			<?php foreach ( $report as $title => $rows ) : ?>
				<h2><?php echo esc_html( $title ); ?></h2>
				<table class="widefat striped bog-status-table">
					<tbody>
						<?php foreach ( $rows as $label => $value ) : ?>
							<tr>
								<th scope="row" style="width:280px"><?php echo esc_html( $label ); ?></th>
								<td><code><?php echo esc_html( $value ); ?></code></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<h2><?php esc_html_e( 'Report', 'banners-og' ); ?></h2>
			<textarea class="large-text code" rows="18" readonly onclick="this.select()"><?php echo esc_textarea( self::as_text( $report ) ); ?></textarea>
		</div>
		<?php
	}

	/**
	 * @return array<string, array<string, string>>
	 */
	private static function report( bool $checking ): array {
		$uploads = wp_get_upload_dir();
		$paths   = Banners_OG_Storage::paths();

		return [
			__( 'Environment', 'banners-og' )     => self::environment( $uploads, $paths ),
			__( 'Offload signals', 'banners-og' ) => self::offload_signals(),
			__( 'Banners', 'banners-og' )         => self::banners( $paths, $checking ),
		];
	}

	/**
	 * @param array<string, mixed>        $uploads
	 * @param array{dir:string, url:string} $paths
	 *
	 * @return array<string, string>
	 */
	private static function environment( array $uploads, array $paths ): array {
		$basedir = (string) $uploads['basedir'];

		return [
			'Banners OG'               => BANNERS_OG_VERSION,
			'WordPress'                => (string) get_bloginfo( 'version' ),
			'PHP'                      => PHP_VERSION,
			'home_url()'               => home_url(),
			'site_url()'               => site_url(),
			'WP_CONTENT_DIR'           => WP_CONTENT_DIR,
			'uploads basedir'          => $basedir,
			'uploads baseurl'          => (string) $uploads['baseurl'],
			'uploads is a stream'      => wp_is_stream( $basedir ) ? 'yes' : 'no',
			'uploads error'            => '' !== (string) $uploads['error'] ? (string) $uploads['error'] : 'none',
			'banners dir'              => $paths['dir'],
			'banners dir exists'       => is_dir( $paths['dir'] ) ? 'yes' : 'no',
			'banners dir writable'     => wp_is_writable( $paths['dir'] ) ? 'yes' : 'no',
			'banners base url'         => $paths['url'],
			'banners url is same host' => wp_parse_url( $paths['url'], PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ? 'yes' : 'no',
			'storage mode'             => Banners_OG_Storage::uses_attachments() ? 'attachment (media library)' : 'plain file',
			'brand logo drawable'      => self::drawable( Banners_OG_Theme::logo_url() ),
			'brand mark drawable'      => self::drawable( Banners_OG_Theme::mark_url() ),
			'brand mark source'        => self::mark_source(),
		];
	}

	/**
	 * Which setting the brand mark comes from, since each one reaches the
	 * canvas by a different route.
	 */
	private static function mark_source(): string {
		$theme = Banners_OG_Theme::get();

		if ( (int) $theme['mark_id'] > 0 ) {
			return 'Appearance screen, attachment #' . (int) $theme['mark_id'];
		}

		$icon = (int) get_option( 'site_icon' );

		if ( $icon > 0 ) {
			return 'site icon, attachment #' . $icon;
		}

		return 'none';
	}

	/**
	 * html2canvas cannot draw a cross-origin image, so a brand image published
	 * from another host silently disappears from the generated file.
	 */
	private static function drawable( string $url ): string {
		if ( '' === $url ) {
			return 'no image';
		}

		if ( false !== strpos( $url, 'action=' . Banners_OG_Ajax::IMAGE_ACTION ) ) {
			return 'yes, served through the site';
		}

		$same_host = wp_parse_url( $url, PHP_URL_HOST ) === wp_parse_url( admin_url(), PHP_URL_HOST );

		return $same_host ? 'yes: ' . $url : 'NO, cross-origin: ' . $url;
	}

	/**
	 * Known plugins and constants that move uploads somewhere else. Only names
	 * and buckets: no key, no secret.
	 *
	 * @return array<string, string>
	 */
	private static function offload_signals(): array {
		$out = [
			'S3 Uploads (Human Made)' => class_exists( 'S3_Uploads\\Plugin' ) || class_exists( 'S3_Uploads' ) ? 'active' : 'no',
			'WP Offload Media'        => class_exists( 'Amazon_S3_And_CloudFront' ) ? 'active' : 'no',
			'Jetpack'                 => class_exists( 'Jetpack' ) ? 'active' : 'no',
			'WooCommerce'             => class_exists( 'WooCommerce' ) ? 'active' : 'no',
		];

		foreach ( [ 'S3_UPLOADS_BUCKET', 'S3_UPLOADS_BUCKET_URL', 'S3_UPLOADS_OBJECT_ACL', 'S3_UPLOADS_REGION', 'UPLOADS' ] as $constant ) {
			if ( ! defined( $constant ) ) {
				$out[ $constant ] = 'not defined';

				continue;
			}

			$value            = constant( $constant );
			$out[ $constant ] = is_scalar( $value ) ? (string) $value : gettype( $value );
		}

		$out['WP-Stateless']           = class_exists( 'wpCloud\\StatelessMedia\\Bootstrap' ) ? 'active' : 'no';
		$out['upload_dir filtered']    = has_filter( 'upload_dir' ) ? 'yes' : 'no';
		$out['upload_path option']     = (string) get_option( 'upload_path', '' );
		$out['upload_url_path option'] = (string) get_option( 'upload_url_path', '' );

		return $out;
	}

	/**
	 * Every banner the site publishes, with the file behind it.
	 *
	 * @param array{dir:string, url:string} $paths
	 *
	 * @return array<string, string>
	 */
	private static function banners( array $paths, bool $checking ): array {
		$out = [];

		foreach ( Banners_OG_Templates::defaults() as $kind => $values ) {
			$file = (string) ( $values['image'] ?? '' );

			if ( '' !== $file ) {
				$out[ 'default: ' . $kind ] = self::describe( $file, $paths, $checking );
			}
		}

		foreach ( self::recent_posts() as $post_id => $file ) {
			$out[ 'post ' . $post_id . ': ' . get_the_title( $post_id ) ] = self::describe( $file, $paths, $checking );
		}

		if ( [] === $out ) {
			$out[ __( 'No banner generated yet', 'banners-og' ) ] = '—';
		}

		return $out;
	}

	/**
	 * @param array{dir:string, url:string} $paths
	 */
	private static function describe( string $file, array $paths, bool $checking ): string {
		if ( ctype_digit( $file ) ) {
			// Attachment mode: what is stored is the ID, not a file name.
			$path   = get_attached_file( (int) $file );
			$exists = is_string( $path ) && '' !== $path && file_exists( $path );
			$label  = 'attachment #' . $file;
		} else {
			$exists = file_exists( $paths['dir'] . '/' . wp_basename( $file ) );
			$label  = $file;
		}

		$url  = (string) ( Banners_OG_Storage::image_url( $file ) ?? '' );
		$line = sprintf( '%s | file %s | %s', $label, $exists ? 'found' : 'MISSING', '' !== $url ? $url : 'no url' );

		if ( ! $checking || '' === $url ) {
			return $line;
		}

		$response = wp_remote_head( $url, [ 'timeout' => 8 ] );

		if ( is_wp_error( $response ) ) {
			return $line . ' | request failed: ' . $response->get_error_message();
		}

		return sprintf(
			'%s | HTTP %d %s',
			$line,
			wp_remote_retrieve_response_code( $response ),
			(string) wp_remote_retrieve_header( $response, 'content-type' )
		);
	}

	/**
	 * @return array<int, string>
	 */
	private static function recent_posts(): array {
		$posts = get_posts(
			[
				'post_type'      => Banners_OG_Templates::post_types(),
				'post_status'    => 'any',
				'posts_per_page' => 5,
				'meta_key'       => Banners_OG_Plugin::META_IMAGE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- diagnostics screen, run by hand.
				'fields'         => 'ids',
			]
		);

		$out = [];

		foreach ( $posts as $post_id ) {
			$out[ (int) $post_id ] = (string) get_post_meta( (int) $post_id, Banners_OG_Plugin::META_IMAGE, true );
		}

		return $out;
	}

	/**
	 * @param array<string, array<string, string>> $report
	 */
	private static function as_text( array $report ): string {
		$lines = [];

		foreach ( $report as $title => $rows ) {
			$lines[] = '## ' . $title;

			foreach ( $rows as $label => $value ) {
				$lines[] = $label . ': ' . $value;
			}

			$lines[] = '';
		}

		return implode( "\n", $lines );
	}
}
