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
		$optimized_image = $this->image_service->optimize_image( $upload['url'], $upload['file'] );

		if ( is_wp_error( $optimized_image ) ) {
			return $upload;
		}

		if ( $this->delete_original ) {
			$upload = $this->replace_original_with_webp( $upload, $optimized_image );
		} else {
			$this->add_webp_as_new_attachment( $optimized_image );
		}

		return $upload;
	}

	/**
	 * Replaces the original file with the optimized WebP file in the Media Library.
	 *
	 * @param array  $upload The original upload array.
	 * @param string $webp_file_path The path to the optimized WebP file.
	 * @return array The updated upload array.
	 */
	private function replace_original_with_webp( $upload, $webp_file_path ) {
		// Update the upload array to use the WebP file
		$upload['file'] = $webp_file_path;
		$upload['url']  = trailingslashit( wp_upload_dir()['url'] ) . wp_basename( $webp_file_path );
		$upload['type'] = 'image/webp';

		// Delete the original file from disk
		$this->delete_original_file( $upload['file'] );

		return $upload;
	}

	/**
	 * Adds the optimized WebP file as a new attachment in the Media Library.
	 *
	 * @param string $webp_file_path The path to the optimized WebP file.
	 */
	private function add_webp_as_new_attachment( $webp_file_path ) {

		$attachment_data = array(
			'post_mime_type' => 'image/webp',
			'post_title'     => wp_basename( $webp_file_path ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $webp_file_path );

		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $webp_file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
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
		}

		return true; // File deletion successful
	}
}
