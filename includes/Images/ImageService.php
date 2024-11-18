<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Optimizes images using a Cloudflare Worker and saves them locally.
 */
class ImageService {

	/**
	 * Cloudflare Worker URL for image optimization.
	 */
	private const WORKER_URL = 'https://hiive.cloud/workers/image-optimization';

	/**
	 * Optimizes an uploaded image by sending it to the Cloudflare Worker and saving the result as WebP.
	 *
	 * @param string $image_url The URL of the uploaded image.
	 * @param string $original_file_path The original file path of the uploaded image.
	 * @return string|WP_Error The path to the optimized WebP file or a WP_Error on failure.
	 */
	public function optimize_image( $image_url, $original_file_path ) {
		// Validate the image URL
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return new \WP_Error(
				'nfd_performance_error',
				__( 'The provided image URL is invalid.', 'wp-module-performance' )
			);
		}

		// Make a POST request to the Cloudflare Worker
		$response = wp_remote_post(
			self::WORKER_URL . '?image=' . rawurlencode( $image_url ),
			array(
				'method'  => 'POST',
				'timeout' => 30,
			)
		);

		// Handle errors from the HTTP request
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'nfd_performance_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'Error connecting to Cloudflare Worker: %s', 'wp-module-performance' ),
					$response->get_error_message()
				)
			);
		}

		// Check for HTTP 400-series errors
		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 400 <= $response_code && 499 >= $response_code ) {
			$error_message = $this->get_response_message( $response );
			return new \WP_Error(
				'nfd_performance_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'Client error from Cloudflare Worker: %s', 'wp-module-performance' ),
					$error_message
				)
			);
		} elseif ( 500 <= $response_code ) { // Yoda condition
			return new \WP_Error(
				'nfd_performance_error',
				__( 'Server error from Cloudflare Worker. Please try again later.', 'wp-module-performance' )
			);
		}

		$optimized_image_body = wp_remote_retrieve_body( $response );
		$content_type         = wp_remote_retrieve_header( $response, 'content-type' );
		if ( empty( $optimized_image_body ) || 'image/webp' !== $content_type ) {
			$error_message = $this->get_response_message( $response ) ?? __( 'Invalid response from Cloudflare Worker.', 'wp-module-performance' );
			return new \WP_Error(
				'nfd_performance_error',
				$error_message
			);
		}

		// Save the WebP image to the same directory as the original file
		$webp_file_path = $this->generate_webp_file_path( $original_file_path );
		if ( is_wp_error( $webp_file_path ) ) {
			return $webp_file_path;
		}
		if ( true !== $this->save_file( $webp_file_path, $optimized_image_body ) ) {
			return new \WP_Error(
				'nfd_performance_error',
				__( 'Failed to save the optimized WebP image.', 'wp-module-performance' )
			);
		}

		return $webp_file_path;
	}

	/**
	 * Generates a WebP file path based on the original file path.
	 *
	 * @param string $original_file_path The original file path.
	 * @return string|WP_Error The WebP file path or a WP_Error on failure.
	 */
	private function generate_webp_file_path( $original_file_path ) {
		$path_info = pathinfo( $original_file_path );

		if ( ! isset( $path_info['dirname'], $path_info['filename'] ) ) {
			return new \WP_Error(
				'nfd_performance_error',
				__( 'Invalid file path for generating WebP.', 'wp-module-performance' )
			);
		}

		return $path_info['dirname'] . '/' . $path_info['filename'] . '.webp';
	}

	/**
	 * Saves the content to a file.
	 *
	 * @param string $file_path The path where the file will be saved.
	 * @param string $content The content to save.
	 * @return bool True on success, false on failure.
	 */
	private function save_file( $file_path, $content ) {
		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->put_contents( $file_path, $content, FS_CHMOD_FILE ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Retrieves the response message from wp_remote_post response.
	 *
	 * @param array $response The HTTP response from wp_remote_post.
	 * @return string|null The response message or null if unavailable.
	 */
	private function get_response_message( $response ) {
		$code    = wp_remote_retrieve_response_code( $response );
		$message = wp_remote_retrieve_response_message( $response );

		if ( $code && $message ) {
			return sprintf(
				/* translators: 1: HTTP response code, 2: Response message */
				__( 'HTTP %1$d: %2$s', 'wp-module-performance' ),
				$code,
				$message
			);
		}

		return null;
	}
}
