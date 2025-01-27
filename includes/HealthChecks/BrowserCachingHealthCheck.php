<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for browser caching.
 */
class BrowserCachingHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-browser-caching';
		$this->title        = esc_html__( 'Browser Caching', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Browser caching is enabled', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Browser caching is disabled', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Enabling browser caching can improve performance by storing static assets in the browser for faster page loads.', 'newfold-performance-module' );
	}

	/**
	 * Test if browser caching is enabled.
	 *
	 * @return bool
	 */
	public function test() {
		return get_option( 'newfold_cache_level' ) >= 1;
	}
}
