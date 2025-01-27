<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for page caching.
 */
class PageCachingHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-page-caching';
		$this->title        = esc_html__( 'Page Caching', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Page caching is enabled', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Page caching is disabled', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Page caching can improve performance by bypassing PHP and database queries for faster page loads.', 'newfold-performance-module' );
	}

	/**
	 * Test if page caching is enabled.
	 *
	 * @return bool
	 */
	public function test() {
		return get_option( 'newfold_cache_level' ) >= 2;
	}
}
