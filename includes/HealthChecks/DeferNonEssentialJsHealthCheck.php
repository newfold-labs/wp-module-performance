<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for deferring non-essential JavaScript.
 */
class DeferNonEssentialJsHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-defer-non-essential-js';
		$this->title        = esc_html__( 'Defer Non-Essential JavaScript', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Non-essential JavaScript is deferred', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Non-essential JavaScript is not deferred', 'newfold-performance-module' );
		$this->description  = esc_html__( 'JavaScript can be deferred to improve performance by loading it after the page has loaded.', 'newfold-performance-module' );
	}

	/**
	 * Test if non-essential JavaScript is deferred.
	 *
	 * @return bool
	 */
	public function test() {
		return get_option( 'jetpack_boost_status_render-blocking-js', false );
	}
}
