<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for Cloudflare.
 */
class CloudflareHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-cloudflare';
		$this->title        = esc_html__( 'Cloudflare enabled', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Cloudflare integration is enabled', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Cloudflare integration is disabled', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Cloudflare integration can improve performance and security.', 'newfold-performance-module' );
	}

	/**
	 * Test for Cloudflare integration.
	 *
	 * @return bool
	 */
	public function test() {
		return isset( $_SERVER['HTTP_CF_RAY'] );
	}
}
