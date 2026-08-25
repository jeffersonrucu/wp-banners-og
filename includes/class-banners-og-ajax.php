<?php
/**
 * AJAX endpoints: receive the JPEG rendered by the browser and replace the
 * banner of the target context (site default or a single post).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Ajax {

	const IMAGE_ACTION = 'banners_og_image';

	public static function init(): void {
		add_action( 'wp_ajax_banners_og_save_default', [ __CLASS__, 'save_default' ] );
		add_action( 'wp_ajax_banners_og_save_post', [ __CLASS__, 'save_post' ] );
		add_action( 'wp_ajax_banners_og_save_term', [ __CLASS__, 'save_term' ] );
		add_action( 'wp_ajax_' . self::IMAGE_ACTION, [ __CLASS__, 'image' ] );
	}

	/**
	 * Serves an attachment from the domain of the site.
	 *
	 * html2canvas refuses to export a canvas touched by a cross-origin image,
	 * and a site that offloads its media publishes it from another host — with
	 * no local copy left to point at. Going through here is what puts the logo
	 * and the product photo inside the generated banner.
	 *
	 * It takes an attachment ID and never a URL: an endpoint that fetches what
	 * it is told to fetch is a server-side request forgery waiting to happen.
	 */
	public static function image(): void {
		check_ajax_referer( Banners_OG_Plugin::NONCE_ACTION );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

		if ( ! $id || ! wp_attachment_is_image( $id ) ) {
			wp_die( '', '', [ 'response' => 404 ] );
		}

		$size = isset( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : 'full';
		$size = in_array( $size, array_merge( get_intermediate_image_sizes(), [ 'full' ] ), true ) ? $size : 'full';

		$body = self::image_body( $id, $size );

		if ( null === $body ) {
			wp_die( '', '', [ 'response' => 404 ] );
		}

		header( 'Content-Type: ' . ( (string) get_post_mime_type( $id ) ?: 'image/jpeg' ) );
		header( 'Content-Length: ' . strlen( $body ) );
		header( 'Cache-Control: private, max-age=300' );

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary image data, already typed by the header above.
		echo $body;
		exit;
	}

	/**
	 * The bytes of an attachment: the local file when it is still there, the
	 * published URL when the media lives somewhere else.
	 */
	private static function image_body( int $id, string $size ): ?string {
		$path = get_attached_file( $id );

		if ( 'full' === $size && is_string( $path ) && '' !== $path && file_exists( $path ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';

			global $wp_filesystem;

			if ( WP_Filesystem() ) {
				$contents = $wp_filesystem->get_contents( $path );

				if ( is_string( $contents ) ) {
					return $contents;
				}
			}
		}

		$url = wp_get_attachment_image_url( $id, $size );

		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}

		$response = wp_remote_get( $url, [ 'timeout' => 10 ] );

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return '' !== $body && strlen( $body ) <= Banners_OG_Storage::MAX_BYTES ? $body : null;
	}

	public static function save_default(): void {
		check_ajax_referer( Banners_OG_Plugin::NONCE_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'banners-og' ) ], 403 );
		}

		$payload = self::read_payload();
		$kind    = $payload['kind'];

		if ( ! Banners_OG_Templates::is_kind( $kind ) ) {
			wp_send_json_error( [ 'message' => __( 'Unknown layout.', 'banners-og' ) ], 400 );
		}

		$defaults = Banners_OG_Templates::defaults();
		$current  = $defaults[ $kind ];

		$defaults[ $kind ] = array_merge( $current, $payload['fields'] );

		$image = Banners_OG_Storage::receive( 'og-' . $kind, (string) ( $current['image'] ?? '' ) );

		if ( is_wp_error( $image ) ) {
			self::fail( $image );
		}

		if ( $image ) {
			$defaults[ $kind ]['image'] = $image['file'];
		}

		Banners_OG_Templates::save_defaults( $defaults );

		wp_send_json_success(
			[
				'message'  => __( 'Default banner updated.', 'banners-og' ),
				'imageUrl' => $image['url'] ?? '',
			]
		);
	}

	public static function save_post(): void {
		check_ajax_referer( Banners_OG_Plugin::NONCE_ACTION, 'nonce' );

		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'banners-og' ) ], 403 );
		}

		$post = get_post( $post_id );

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, Banners_OG_Templates::post_types(), true ) ) {
			wp_send_json_error( [ 'message' => __( 'Unsupported content.', 'banners-og' ) ], 400 );
		}

		$payload = self::read_payload();
		$kind    = $payload['kind'];

		$settings            = $payload['fields'];
		$settings['enabled'] = empty( $_POST['enabled'] ) ? 0 : 1;
		$settings['kind']    = Banners_OG_Templates::is_kind( $kind )
			? $kind
			: Banners_OG_Templates::default_kind_for_post_type( $post->post_type );

		update_post_meta( $post_id, Banners_OG_Plugin::META_SETTINGS, $settings );

		$old_file = (string) get_post_meta( $post_id, Banners_OG_Plugin::META_IMAGE, true );
		$image    = Banners_OG_Storage::receive( 'og-' . $post->post_type . '-' . $post_id, $old_file );

		if ( is_wp_error( $image ) ) {
			self::fail( $image );
		}

		if ( $image ) {
			update_post_meta( $post_id, Banners_OG_Plugin::META_IMAGE, $image['file'] );
		}

		wp_send_json_success(
			[
				'message'  => __( 'Banner updated.', 'banners-og' ),
				'imageUrl' => $image['url'] ?? '',
			]
		);
	}

	public static function save_term(): void {
		check_ajax_referer( Banners_OG_Plugin::NONCE_ACTION, 'nonce' );

		$term_id = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$term    = $term_id ? get_term( $term_id ) : null;

		if ( ! $term instanceof WP_Term || ! in_array( $term->taxonomy, Banners_OG_Templates::taxonomies(), true ) ) {
			wp_send_json_error( [ 'message' => __( 'Unsupported content.', 'banners-og' ) ], 400 );
		}

		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to do this.', 'banners-og' ) ], 403 );
		}

		$payload = self::read_payload();
		$kind    = $payload['kind'];

		$settings            = $payload['fields'];
		$settings['enabled'] = empty( $_POST['enabled'] ) ? 0 : 1;
		$settings['kind']    = Banners_OG_Templates::is_kind( $kind )
			? $kind
			: Banners_OG_Templates::default_kind_for_taxonomy( $term->taxonomy );

		update_term_meta( $term_id, Banners_OG_Plugin::META_SETTINGS, $settings );

		$old_file = (string) get_term_meta( $term_id, Banners_OG_Plugin::META_IMAGE, true );
		$image    = Banners_OG_Storage::receive( 'og-' . $term->taxonomy . '-' . $term_id, $old_file );

		if ( is_wp_error( $image ) ) {
			self::fail( $image );
		}

		if ( $image ) {
			update_term_meta( $term_id, Banners_OG_Plugin::META_IMAGE, $image['file'] );
		}

		wp_send_json_success(
			[
				'message'  => __( 'Banner updated.', 'banners-og' ),
				'imageUrl' => $image['url'] ?? '',
			]
		);
	}

	/**
	 * Reads the posted layout and copy, sanitized per field type.
	 *
	 * @return array{kind:string, fields:array<string, string>}
	 */
	private static function read_payload(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer() runs in the callbacks above.
        // phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_fields() cleans every value by its field type.
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$raw  = [];

		foreach ( Banners_OG_Templates::fields() as $key => $field ) {
			if ( ! isset( $_POST['fields'][ $key ] ) || ! is_scalar( $_POST['fields'][ $key ] ) ) {
				continue;
			}

			$raw[ $key ] = wp_unslash( $_POST['fields'][ $key ] );
		}
        // phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        // phpcs:enable WordPress.Security.NonceVerification.Missing

		return [
			'kind'   => $kind,
			'fields' => Banners_OG_Templates::sanitize_fields( $raw ),
		];
	}

	private static function fail( WP_Error $error ): void {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 400;

		wp_send_json_error( [ 'message' => $error->get_error_message() ], $status );
	}
}
