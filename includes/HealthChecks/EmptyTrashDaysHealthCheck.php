<?php

namespace NewfoldLabs\WP\Module\Performance\HealthChecks;

/**
 * Health check for empty trash days.
 */
class EmptyTrashDaysHealthCheck extends HealthCheck {
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id           = 'newfold-empty-trash-days';
		$this->title        = esc_html__( 'Empty Trash Days', 'newfold-performance-module' );
		$this->passing_text = esc_html__( 'Trash is emptied every 30 days or less', 'newfold-performance-module' );
		$this->failing_text = esc_html__( 'Trash is emptied less frequently than every 30 days.', 'newfold-performance-module' );
		$this->description  = esc_html__( 'Emptying the trash more frequently can reduce database bloat.', 'newfold-performance-module' );
	}

	/**
	 * Test the empty trash days setting.
	 *
	 * @return bool
	 */
	public function test() {
		return defined( 'EMPTY_TRASH_DAYS' ) && constant( 'EMPTY_TRASH_DAYS' ) <= 30;
	}
}
