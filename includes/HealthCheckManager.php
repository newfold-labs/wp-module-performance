<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Add performance health checks.
 */
class HealthCheckManager {

	/**
	 * Health Checks to add.
	 *
	 * @var array
	 */
	public $checks = array();

	/**
	 * Health Check ID prefix.
	 *
	 * @var string
	 */
	public $prefix = 'newfold_performance_';

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container Dependency injection container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		if ( function_exists( 'add_filter' ) ) {
			$this->addHooks();
		}
	}

	/**
	 * Add hooks.
	 */
	public function addHooks() {
		add_filter( 'site_status_tests', array( $this, 'registerHealthChecks' ) );
	}

	/**
	 * Add a health check.
	 *
	 * @param array $options Health check options.
	 */
	public function addHealthCheck( $options ) {
		$options = wp_parse_args(
			$options,
			array(
				'id'          => '',
				'title'       => '',
				'test'        => '',
				'label'       => false, // Setting this will override pass/fail labels.
				'pass'        => '',
				'fail'        => '',
				'text'        => '',
				'status'      => false, // Override the status of the health check: default is good for pass, critical for fail.
				'badge_label' => __( 'Performance', 'newfold-labs' ),
				'badge_color' => 'blue',
				'actions'     => '',
			)
		);

		// Make sure the health check is valid.
		if ( ! ( empty( $options['id'] ) || empty( $options['title'] ) || ! is_callable( $options['test'] ) ) ) {
			$this->checks[ $this->prefix . $options['id'] ] = $options;
		}
	}

	/**
	 * Run a health check.
	 *
	 * @param string $id Health check ID.
	 *
	 * @return array Health check results.
	 */
	public function runHealthCheck( $id ) {
		$check = $this->checks[ $id ];

		// Execute the test.
		$passed = call_user_func( $check['test'] );

		// Return the health check results.
		return array(
			'label'       => $check['label'] ? $check['label'] : ( $passed ? $check['pass'] : $check['fail'] ),
			'status'      => $check['status'] ? $check['status'] : ( $passed ? 'good' : 'critical' ),
			'description' => $check['text'] ? $check['text'] : '',
			'actions'     => $check['actions'] ? $check['actions'] : '',
			'test'        => $check['id'],
			'badge'       => array(
				'label' => $check['badge_label'],
				'color' => $check['badge_color'],
			),
		);
	}

	/**
	 * Add health checks.
	 *
	 * @param array $tests Site Health tests.
	 *
	 * @return array Site Health tests.
	 */
	public function registerHealthChecks( $tests ) {
		// If there are no health checks, don't add any.
		if ( ! is_array( $this->checks ) || empty( $this->checks ) ) {
			return $tests;
		}

		foreach ( $this->checks as $id => $check ) {
			/**
			 * Filter to enable/disable a health check.
			 *
			 * @param bool $do_check Whether to run the health check.
			 */
			$do_check = apply_filters( 'newfold/features/filter/isEnabled:healthChecks:' . $id, true ); // phpcs:ignore
			if ( $do_check ) {
				$tests['direct'][ $id ] = array(
					'label' => $check['title'],
					'test'  => function () use ( $id ) {
						return $this->runHealthCheck( $id );
					},
				);
			}
		}

		return $tests;
	}
}
