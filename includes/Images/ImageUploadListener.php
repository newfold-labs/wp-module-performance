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
	}

	/**
	 * Intercepts media uploads and optimizes images via the Cloudflare Worker.
	 *
	 * @param array $upload The upload array with file data.
	 * @return array|WP_Error The modified upload array or WP_Error on failure.
	 */
	public function handle_media_upload( $upload ) {
		$optimized_image_path = $this->image_service->optimize_image( $upload['url'], $upload['file'] );

		if ( is_wp_error( $optimized_image_path ) ) {
			return $upload;
		}

		if ( $this->delete_original ) {
			$upload = $this->image_service->replace_original_with_webp( $upload['file'], $optimized_image_path );
		} else {
			$this->image_service->register_webp_as_new_media( $optimized_image_path );
		}

		return $upload;
	}
}
