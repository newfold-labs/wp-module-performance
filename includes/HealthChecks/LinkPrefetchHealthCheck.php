<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for link prefetching.
 */
class LinkPrefetchHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-link-prefetch';
		$this->title        = esc_html__( 'Link Prefetching', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Link prefetching is enabled', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Link prefetching is disabled', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Link prefetching can improve performance by loading pages immediately before they are requested.', 'newfold-performance-module' );
	}

	/**
	 * Test if link prefetching is enabled.
	 *
	 * @return bool
	 */
	public function test() {
		$enabled = get_option( 'nfd_link_prefetch_settings', array() );
		return isset( $enabled['activeOnDesktop'] ) && $enabled['activeOnDesktop'];
	}
}
