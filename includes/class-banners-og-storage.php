<?php
/**
 * Where the generated banners live: uploads/banners-og, outside the media
 * library so an editor cannot delete them by accident.
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Storage {

	const DIRNAME   = 'banners-og';
	const MAX_BYTES = 4194304; // 4 MB.

	// Marks the attachments created by the plugin, so uninstall can clean them.
	const META_MANAGED = '_banners_og_managed';

	/**
	 * @return array{dir:string, url:string}
	 */
	public static function paths(): array {
		$uploads = wp_get_upload_dir();

		return [
			'dir' => $uploads['basedir'] . '/' . self::DIRNAME,
			'url' => self::base_url( $uploads ) . '/' . self::DIRNAME,
		];
	}

	/**
	 * Base URL the banners are served from.
	 *
	 * They are plain files and not attachments, so an offload plugin (S3 and
	 * friends) rewrites the uploads URL to its bucket without ever copying
	 * them there — the bucket would answer 403. When the uploads URL points
	 * somewhere else, the file is served from the site itself.
	 *
	 * @param array<string, mixed> $uploads
	 */
	private static function base_url( array $uploads ): string {
		$base    = (string) $uploads['baseurl'];
		$basedir = (string) $uploads['basedir'];
		$content = untrailingslashit( WP_CONTENT_DIR );

		// Only when the banner really sits on the disk of the site: with
		// uploads on a stream (s3://), the rewritten URL is the one that
		// answers, and pointing at the site would 404.
		$is_local = false === strpos( $basedir, '://' ) && 0 === strpos( $basedir, $content );

		if ( $is_local && wp_parse_url( $base, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
			$base = content_url( substr( $basedir, strlen( $content ) ) );
		}

		/**
		 * Filters the base URL of the generated banners, for a site that serves
		 * `uploads/` in its own way.
		 */
		return untrailingslashit( (string) apply_filters( 'banners_og_uploads_url', $base ) );
	}

	/**
	 * Whether the banners are stored as attachments of the media library.
	 *
	 * A site that offloads uploads (S3 and friends) serves that folder from
	 * another host, and only attachments make that trip: a plain file would be
	 * published from a bucket that never received it. Everywhere else the
	 * banners stay out of the library, which is where they belong.
	 */
	public static function uses_attachments(): bool {
		$uploads   = wp_get_upload_dir();
		$offloaded = wp_parse_url( (string) $uploads['baseurl'], PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST );

		/**
		 * Filters whether a generated banner becomes an attachment.
		 */
		return (bool) apply_filters( 'banners_og_use_attachments', $offloaded );
	}

	/**
	 * Stored banners are either a file name or the ID of an attachment.
	 */
	private static function is_attachment( string $stored ): bool {
		return '' !== $stored && ctype_digit( $stored );
	}

	/**
	 * Public URL of a stored banner, or null when the file is gone.
	 */
	public static function image_url( string $file ): ?string {
		if ( self::is_attachment( $file ) ) {
			$url = wp_get_attachment_url( (int) $file );

			return is_string( $url ) && '' !== $url ? $url : null;
		}

		$file = wp_basename( $file );

		if ( '' === $file ) {
			return null;
		}

		$paths = self::paths();

		if ( ! file_exists( $paths['dir'] . '/' . $file ) ) {
			return null;
		}

		return $paths['url'] . '/' . $file;
	}

	public static function delete( string $file ): void {
		if ( self::is_attachment( $file ) ) {
			wp_delete_attachment( (int) $file, true );

			return;
		}

		$file = wp_basename( $file );

		if ( '' === $file ) {
			return;
		}

		$path = self::paths()['dir'] . '/' . $file;

		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
	}

	/**
	 * Creates the banners directory on first use and drops an index.php in it,
	 * so servers with autoindex enabled do not list the banners.
	 *
	 * Deliberately lazy instead of running on activation: activation often runs
	 * as another user (WP-CLI as root, for instance), which would leave the
	 * directory unwritable for the web server.
	 */
	public static function ensure_dir(): bool {
		$dir = self::paths()['dir'];

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$index = $dir . '/index.php';

		if ( file_exists( $index ) ) {
			return true;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;

		if ( WP_Filesystem() ) {
			$wp_filesystem->put_contents( $index, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
		}

		return true;
	}

	/**
	 * Stores the JPEG posted by the browser and removes the previous one.
	 *
	 * @param string $slug     Base file name, without extension.
	 * @param string $old_file Banner being replaced, if any.
	 *
	 * @return array{file:string, url:string}|WP_Error|null Null when no file was posted.
	 */
	public static function receive( string $slug, string $old_file ) {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- The AJAX callbacks verify the nonce before calling this.
		if ( ! isset( $_FILES['banner'] ) || ! is_array( $_FILES['banner'] ) ) {
			return null;
		}

		$error = isset( $_FILES['banner']['error'] ) ? absint( $_FILES['banner']['error'] ) : UPLOAD_ERR_NO_FILE;

		if ( UPLOAD_ERR_NO_FILE === $error ) {
			return null;
		}

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'banners_og_upload', __( 'The banner could not be uploaded.', 'banners-og' ), [ 'status' => 400 ] );
		}

		$tmp  = isset( $_FILES['banner']['tmp_name'] ) ? sanitize_text_field( wp_unslash( (string) $_FILES['banner']['tmp_name'] ) ) : '';
		$size = isset( $_FILES['banner']['size'] ) ? absint( $_FILES['banner']['size'] ) : 0;

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new WP_Error( 'banners_og_upload', __( 'The banner could not be uploaded.', 'banners-og' ), [ 'status' => 400 ] );
		}

		if ( $size < 1 || $size > self::MAX_BYTES ) {
			return new WP_Error( 'banners_og_size', __( 'The generated image is too large.', 'banners-og' ), [ 'status' => 400 ] );
		}

		$info = getimagesize( $tmp );

		if ( ! $info || IMAGETYPE_JPEG !== $info[2] ) {
			return new WP_Error( 'banners_og_type', __( 'The generated image is not a valid JPEG.', 'banners-og' ), [ 'status' => 400 ] );
		}

		if ( BANNERS_OG_WIDTH !== (int) $info[0] || BANNERS_OG_HEIGHT !== (int) $info[1] ) {
			return new WP_Error(
				'banners_og_dimensions',
				sprintf(
					/* translators: 1: expected width in pixels, 2: expected height in pixels. */
					__( 'The banner must be %1$d by %2$d pixels.', 'banners-og' ),
					BANNERS_OG_WIDTH,
					BANNERS_OG_HEIGHT
				),
				[ 'status' => 400 ]
			);
		}

		if ( ! self::ensure_dir() ) {
			return new WP_Error( 'banners_og_mkdir', __( 'The banners directory could not be created.', 'banners-og' ), [ 'status' => 500 ] );
		}

		// A timestamped name busts the cache of the social networks on every rebuild.
		$filename = sanitize_file_name( $slug . '-' . gmdate( 'YmdHis' ) . '.jpg' );

		$overrides = [
			'test_form'                => false,
			'mimes'                    => [ 'jpg|jpeg' => 'image/jpeg' ],
			'unique_filename_callback' => static function () use ( $filename ): string {
				return $filename;
			},
		];

		require_once ABSPATH . 'wp-admin/includes/file.php';

		add_filter( 'upload_dir', [ __CLASS__, 'filter_upload_dir' ] );

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated above; wp_handle_upload() does the sanitizing.
		$upload = wp_handle_upload( $_FILES['banner'], $overrides );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

		remove_filter( 'upload_dir', [ __CLASS__, 'filter_upload_dir' ] );

		if ( isset( $upload['error'] ) || ! isset( $upload['file'], $upload['url'] ) ) {
			// Surface what wp_handle_upload() complained about: the caller is an
			// administrator or an editor, and a generic message is undebuggable.
			$reason = isset( $upload['error'] ) && is_string( $upload['error'] )
				? $upload['error']
				: __( 'The banner file could not be written.', 'banners-og' );

			return new WP_Error( 'banners_og_write', $reason, [ 'status' => 500 ] );
		}

		$stored = wp_basename( (string) $upload['file'] );
		$url    = (string) $upload['url'];

		if ( self::uses_attachments() ) {
			$attachment_id = self::attach( (string) $upload['file'], $url, $slug );

			if ( is_wp_error( $attachment_id ) ) {
				return $attachment_id;
			}

			$stored = (string) $attachment_id;
			$url    = (string) wp_get_attachment_url( $attachment_id );
		}

		if ( $old_file !== $stored ) {
			self::delete( $old_file );
		}

		return [
			'file' => $stored,
			'url'  => $url,
		];
	}

	/**
	 * Turns the written file into an attachment, which is what an offload
	 * plugin listens for to copy it to its bucket and serve it from there.
	 *
	 * @return int|WP_Error
	 */
	private static function attach( string $path, string $url, string $slug ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = wp_insert_attachment(
			[
				'guid'           => $url,
				'post_mime_type' => 'image/jpeg',
				/* translators: %s: banner name, such as og-cover or og-product-12. */
				'post_title'     => sprintf( __( 'OG banner: %s', 'banners-og' ), $slug ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			],
			$path,
			0,
			true
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		update_post_meta( $attachment_id, self::META_MANAGED, 1 );

		// No intermediate sizes: the banner is published whole, and every extra
		// size would be one more file for the offload plugin to copy.
		add_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );
		remove_filter( 'intermediate_image_sizes_advanced', '__return_empty_array' );

		return $attachment_id;
	}

	/**
	 * Points wp_handle_upload() at uploads/banners-og instead of the
	 * year/month folders of the media library.
	 *
	 * @param array<string, string> $dirs
	 *
	 * @return array<string, string>
	 */
	public static function filter_upload_dir( array $dirs ): array {
		$dirs['subdir'] = '/' . self::DIRNAME;
		$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
		$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];

		return $dirs;
	}
}
