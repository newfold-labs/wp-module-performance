<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Manages bulk optimization functionality for the Media Library.
 */
class ImageBulkOptimizer {

	/**
	 * Constructor to initialize the bulk optimizer feature.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bulk_optimizer_script' ) );
	}

	/**
	 * Enqueues the bulk optimizer script.
	 */
	public function enqueue_bulk_optimizer_script() {
		wp_register_script(
			'nfd-performance-bulk-optimizer',
			NFD_PERFORMANCE_BUILD_URL . '/image-bulk-optimizer/image-bulk-optimizer.min.js',
			array( 'wp-api-fetch', 'wp-element', 'wp-i18n' ),
			filemtime( NFD_PERFORMANCE_BUILD_DIR . '/image-bulk-optimizer/image-bulk-optimizer.min.js' ),
			true
		);

		wp_enqueue_style(
			'nfd-performance-bulk-optimizer-style',
			NFD_PERFORMANCE_BUILD_URL . '/image-bulk-optimizer/image-bulk-optimizer.min.css',
			array(),
			filemtime( NFD_PERFORMANCE_BUILD_DIR . '/image-bulk-optimizer/image-bulk-optimizer.min.css' )
		);

		wp_add_inline_script(
			'nfd-performance-bulk-optimizer',
			$this->get_inline_script(),
			'before'
		);

		wp_enqueue_script( 'nfd-performance-bulk-optimizer' );
	}

	/**
	 * Generates inline settings for the bulk optimizer script.
	 *
	 * @return string JavaScript code to inline.
	 */
	private function get_inline_script() {
		$api_url = add_query_arg(
			'rest_route',
			'/newfold-performance/v1/images/optimize',
			get_rest_url()
		);

		return sprintf(
			'window.nfdPerformance = window.nfdPerformance || {};
			 window.nfdPerformance.imageOptimization = window.nfdPerformance.imageOptimization || {};
			 window.nfdPerformance.imageOptimization.bulkOptimizer = {
				 apiUrl: "%s"
			 };',
			esc_url( $api_url )
		);
	}
}
