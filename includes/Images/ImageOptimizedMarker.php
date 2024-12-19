<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Marks optimized images in the WordPress Media Library.
 */
class ImageOptimizedMarker {

	/**
	 * Initializes the class by registering hooks.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_marker_assets' ) );
		add_filter( 'wp_prepare_attachment_for_js', array( $this, 'add_media_library_data_attribute' ), 10, 2 );
	}

	/**
	 * Enqueues JS and CSS files for marking optimized images.
	 */
	public function enqueue_marker_assets() {
		wp_enqueue_script(
			'nfd-performance-optimizer-marker',
			NFD_PERFORMANCE_BUILD_URL . '/image-optimized-marker/image-optimized-marker.min.js',
			array( 'wp-i18n' ),
			filemtime( NFD_PERFORMANCE_BUILD_DIR . '/image-optimized-marker/image-optimized-marker.min.js' ),
			true
		);

		wp_enqueue_style(
			'nfd-performance-optimizer-marker-style',
			NFD_PERFORMANCE_BUILD_URL . '/image-optimized-marker/image-optimized-marker.min.css',
			array(),
			filemtime( NFD_PERFORMANCE_BUILD_DIR . '/image-optimized-marker/image-optimized-marker.min.css' )
		);
	}

	/**
	 * Adds a custom data attribute to media library items if optimized.
	 *
	 * @param array   $response  The prepared attachment response.
	 * @param WP_Post $attachment The current attachment object.
	 *
	 * @return array The modified response.
	 */
	public function add_media_library_data_attribute( $response, $attachment ) {
		if ( get_post_meta( $attachment->ID, '_nfd_performance_image_optimized', true ) ) {
			$response['nfdPerformanceImageOptimized'] = true;
		}

		return $response;
	}
}
