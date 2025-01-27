<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for permalinks.
 */
class PermalinksHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-permalinks';
		$this->title        = esc_html__( 'Permalinks', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Permalinks are pretty', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Permalinks are not set up', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Setting permalinks to anything other than plain can improve performance and SEO.', 'newfold-performance-module' );
	}

	/**
	 * Test the permalinks setting.
	 *
	 * @return bool
	 */
	public function test() {
		return ! empty( get_option( 'permalink_structure' ) );
	}
}
