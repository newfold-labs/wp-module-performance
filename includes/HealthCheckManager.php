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
	 * Constructor.
	 */
	public function __construct() {
		if ( function_exists( 'add_filter' ) ) {
			$this->add_hooks();
		}
	}

	/**
	 * Add hooks.
	 */
	public function add_hooks() {
		add_filter( 'site_status_tests', array( $this, 'register_health_checks' ) );
	}

	/**
	 * Add a health check.
	 *
	 * The options array can contain the following keys:
	 * - id: A unique identifier for the health check.
	 * - title: The title of the health check.
	 * - test: A callable function that will run the health check and return a boolean.
	 * - label: Optional. If set, it will display both for passed and failed. (User-facing text.)
	 * - pass: The label to display when the health check passes. (User-facing text.)
	 * - fail: The label to display when the health check fails. (User-facing text.)
	 * - text: Optional. Additional text to display below the health check results. (User-facing text.)
	 * - status: Optional. Used to override the status of the health check. Default is 'good' for pass, 'critical' for fail.
	 * - badge_label: Optional. The label to display on the badge. (User-facing text.) Default is 'Performance'.
	 * - badge_color: Optional. The color of the badge. Default is 'blue'.
	 * - actions: Optional. An array of actions to display below the health check results. Each action should contain a 'label' string, a 'url' string, and a 'external' boolean.
	 *
	 * @param array $options Health check options.
	 */
	public function add_health_check( $options ) {
		$options = /* Explain keys and their impact */
		wp_parse_args(
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
	 * Concatenate actions array into a string.
	 *
	 * Label, URL, and external are the keys that should be present in the actions array. The label is the text that is
	 * display as the link, the URL is the link that the label will point to, and external is a boolean that determines
	 * whether the link should show the external icon.
	 *
	 * @param array $actions Actions to concatenate. Should contain an array of 'label', 'url', and 'external'.
	 *
	 * @return string Concatenated actions.
	 */
	public function concat_actions_text_and_link( $actions ) {
		 $actions_string = '';

		foreach ( $actions as $action ) {
			$action = wp_parse_args(
				$action,
				array(
					'label'    => '',
					'url'      => '',
					'external' => false,
				)
			);

			$actions_string .= sprintf(
				'<a href="%1$s" %3$s>%2$s</a>%4$s',
				esc_url( $action['url'] ),
				esc_html( $action['label'] ),
				$action['external'] ? 'target="_blank" rel="noopener"' : '',
				$action['external'] ? sprintf(
					'<span class="screen-reader-text"> (%s)</span><span aria-hidden="true" class="dashicons dashicons-external"></span>',
					__( 'opens in a new tab', 'newfold-module-performance' )
				) : ''
			);
		}

		 return $actions_string;
	}

	/**
	 * Run a health check.
	 *
	 * @param string $id Health check ID.
	 *
	 * @return array Health check results.
	 */
	public function run_health_check( $id ) {
		$check = $this->checks[ $id ];

		// Execute the test.
		$passed = call_user_func( $check['test'] );

		// Return the health check results.
		return array(
			'label'       => $check['label'] ? $check['label'] : ( $passed ? $check['pass'] : $check['fail'] ),
			'status'      => $passed ? 'good' : ( 'critical' === $check['status'] ? 'critical' : 'recommended' ), // Will default to 'recommended', unless 'critical' is passed.
			'description' => sprintf( '<p>%s</p>', $check['text'] ? $check['text'] : '' ),
			'actions'     => is_array( $check['actions'] ) ? $this->concat_actions_text_and_link( $check['actions'] ) : ( $check['actions'] ? $check['actions'] : '' ),
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
	public function register_health_checks( $tests ) {
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
						return $this->run_health_check( $id );
					},
				);
			}
		}

		return $tests;
	}
}
