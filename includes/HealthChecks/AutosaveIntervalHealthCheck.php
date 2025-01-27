<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for the autosave interval.
 */
class AutosaveIntervalHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-autosave-interval';
		$this->title        = esc_html__( 'Autosave Interval', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Autosaving is set to happen every 30 seconds or more', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Autosaving is set to be frequent, less than every 30 seconds', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Setting the autosave interval to a longer period can reduce server load. It is recommended to set it to 30 seconds or more.', 'newfold-performance-module' );
	}

	/**
	 * Test the autosave interval.
	 *
	 * @return bool
	 */
	public function test() {
		return defined( 'AUTOSAVE_INTERVAL' ) && constant( 'AUTOSAVE_INTERVAL' ) >= 30;
	}
}
