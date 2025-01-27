<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for critical CSS prioritization.
 */
class PrioritizeCssHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-prioritize-critical-css';
		$this->title        = esc_html__( 'Prioritize Critical CSS', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Critical CSS is prioritized', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Critical CSS is not prioritized', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Prioritizing critical CSS can improve performance by loading the most important CSS first.', 'newfold-performance-module' );
	}

	/**
	 * Test if critical CSS is prioritized.
	 *
	 * @return bool
	 */
	public function test() {
		return get_option( 'jetpack_boost_status_critical-css', false );
	}
}
