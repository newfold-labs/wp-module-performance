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
			$upload = $this->replace_original_with_webp( $upload, $optimized_image_path );
		} else {
			$this->register_webp_as_new_media( $optimized_image_path );
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
		// Delete the original file from disk
		$original_file_path = $upload['file'];

		if ( $this->delete_original_file( $original_file_path ) ) {
			// Update the upload array to use the WebP file
			$upload['file'] = $webp_file_path;
			$upload['url']  = trailingslashit( wp_upload_dir()['url'] ) . wp_basename( $webp_file_path );
			$upload['type'] = 'image/webp';
		}

		return $upload;
	}

	/**
	 * Registers the WebP file as a standalone media item in the Media Library.
	 *
	 * @param string $webp_file_path The path to the optimized WebP file.
	 */
	private function register_webp_as_new_media( $webp_file_path ) {
		$upload_dir = wp_upload_dir();
		$webp_url   = trailingslashit( $upload_dir['url'] ) . wp_basename( $webp_file_path );

		// Prepare the attachment data
		$attachment_data = array(
			'post_mime_type' => 'image/webp',
			'post_title'     => wp_basename( $webp_file_path ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the WebP file as a new attachment
		$attachment_id = wp_insert_attachment( $attachment_data, $webp_file_path );

		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		// Generate and update attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$metadata = wp_generate_attachment_metadata( $attachment_id, $webp_file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Deletes the original uploaded file from the filesystem.
	 *
	 * @param string $file_path The path to the original file.
	 * @return bool True on success, false on failure.
	 */
	private function delete_original_file( $file_path ) {
		if ( file_exists( $file_path ) ) {
			if ( wp_delete_file( $file_path ) ) {
				return true;
			}
		}

		return false;
	}
}
