<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Listens for media uploads and manages image optimization processing.
 */
class ImageUploadListener {
	/**
	 * The service class for image optimization.
	 *
	 * @var ImagesService
	 */
	private $images_service;

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
		$this->images_service  = new ImageService();
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
		// Optimize the image
		$optimized_image = $this->images_service->optimize_image( $upload['url'], $upload['file'] );

		// Handle errors during optimization
		if ( is_wp_error( $optimized_image ) ) {
			return $optimized_image; // Return the error for handling
		}

		// Conditionally delete the original file after successful optimization
		if ( $this->delete_original ) {
			$delete_result = $this->delete_original_file( $upload['file'] );
			if ( is_wp_error( $delete_result ) ) {
				return $delete_result; // Return the error if file deletion fails
			}
		}

		// Update the upload array to use the optimized WebP image
		$upload = $this->update_upload_array( $upload, $optimized_image );

		return $upload;
	}

	/**
	 * Updates the upload array to reflect the optimized WebP image.
	 *
	 * @param array  $upload The original upload array.
	 * @param string $optimized_file_path The path to the optimized WebP file.
	 * @return array The updated upload array.
	 */
	private function update_upload_array( $upload, $optimized_file_path ) {
		$upload_dir    = wp_upload_dir();
		$optimized_url = trailingslashit( $upload_dir['url'] ) . wp_basename( $optimized_file_path );

		return array(
			'file' => $optimized_file_path,
			'url'  => $optimized_url,
			'type' => 'image/webp',
		);
	}

	/**
	 * Deletes the original uploaded file from the filesystem.
	 *
	 * @param string $file_path The path to the original file.
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	private function delete_original_file( $file_path ) {
		if ( file_exists( $file_path ) ) {
			if ( ! wp_delete_file( $file_path ) ) {
				return new \WP_Error(
					'nfd_performance_error',
					sprintf(
						/* translators: %s: File path */
						__( 'Failed to delete original file: %s', 'wp-module-performance' ),
						$file_path
					)
				);
			}
		} else {
			return new \WP_Error(
				'nfd_performance_error',
				sprintf(
					/* translators: %s: File path */
					__( 'Original file not found: %s', 'wp-module-performance' ),
					$file_path
				)
			);
		}

		return true; // File deletion successful
	}
}
