<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Listens for media uploads and manages image optimization processing.
 */
class ImageUploadListener {
	/**
	 * The service class for image optimization.
	 *
	 * @var ImageService
	 */
	private $image_service;

	/**
	 * Whether to delete the original uploaded file after optimization.
	 *
	 * @var bool
	 */
	private $delete_original;

	/**
	 * Constructor to initialize the listener.
	 *
	 * @param bool $delete_original Whether to delete the original file after optimization.
	 */
	public function __construct( $delete_original = false ) {
		$this->image_service   = new ImageService();
		$this->delete_original = $delete_original;
		$this->register_hooks();
	}

	/**
	 * Registers the WordPress hooks for listening to media uploads.
	 */
	private function register_hooks() {
		add_filter( 'wp_handle_upload', array( $this, 'handle_media_upload' ), 10, 2 );
		add_action( 'add_attachment', array( $this, 'process_attachment_metadata' ) );
	}

	/**
	 * Intercepts media uploads and optimizes images via the Cloudflare Worker.
	 *
	 * @param array $upload The upload array with file data.
	 * @return array The modified upload array or the original array on failure.
	 */
	public function handle_media_upload( $upload ) {
		$optimized_image_path = $this->image_service->optimize_image( $upload['url'], $upload['file'] );

		if ( is_wp_error( $optimized_image_path ) ) {
			return $upload;
		}

		if ( $this->delete_original ) {
			$result = $this->image_service->replace_original_with_webp( $upload['file'], $optimized_image_path );
			if ( is_wp_error( $result ) ) {
				return $upload;
			}

			return $result;
		} else {
			$this->image_service->register_webp_as_new_media( $optimized_image_path );
		}

		return $upload;
	}

	/**
	 * Processes attachment metadata after the attachment is created.
	 *
	 * @param int $attachment_id The attachment ID.
	 */
	public function process_attachment_metadata( $attachment_id ) {
		$attachment_file = get_attached_file( $attachment_id );
		$transient_key   = 'nfd_webp_metadata_' . md5( $attachment_file );
		$metadata        = get_transient( $transient_key );

		if ( $metadata ) {
			// Update postmeta
			update_post_meta( $attachment_id, '_nfd_performance_image_optimized', 1 );
		}
	}
}
