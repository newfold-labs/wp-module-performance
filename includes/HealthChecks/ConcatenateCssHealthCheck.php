<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for CSS concatenation.
 */
class ConcatenateCSSHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-concatenate-css';
		$this->title        = esc_html__( 'Concatenate CSS', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'CSS files are concatenated', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'CSS files are not concatenated', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Concatenating CSS can improve performance by reducing the number of requests.', 'newfold-performance-module' );
	}

	/**
	 * Test if CSS files are concatenated.
	 *
	 * @return bool
	 */
	public function test() {
		return ! empty( get_option( 'jetpack_boost_status_minify-css', array() ) );
	}
}
