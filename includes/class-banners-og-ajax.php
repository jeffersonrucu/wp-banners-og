<?php
/**
 * AJAX endpoints: receive the JPEG rendered by the browser and replace the
 * banner of the target context (site default or a single post).
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Banners_OG_Ajax {

	public static function init(): void {
		add_action( 'wp_ajax_banners_og_save_default', [ __CLASS__, 'save_default' ] );
		add_action( 'wp_ajax_banners_og_save_post', [ __CLASS__, 'save_post' ] );
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

	/**
	 * Reads the posted layout and copy, sanitized per field type.
	 *
	 * @return array{kind:string, fields:array<string, string>}
	 */
	private static function read_payload(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- check_ajax_referer() runs in the callbacks above.
		$kind = isset( $_POST['kind'] ) ? sanitize_key( wp_unslash( $_POST['kind'] ) ) : '';
		$raw  = [];

		foreach ( Banners_OG_Templates::fields() as $key => $field ) {
			if ( ! isset( $_POST['fields'][ $key ] ) || ! is_scalar( $_POST['fields'][ $key ] ) ) {
				continue;
			}

			$raw[ $key ] = 'textarea' === $field['type']
				? sanitize_textarea_field( wp_unslash( $_POST['fields'][ $key ] ) )
				: sanitize_text_field( wp_unslash( $_POST['fields'][ $key ] ) );
		}
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
