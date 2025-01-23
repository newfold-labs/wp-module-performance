<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Add performance health checks.
 */
class HealthChecks {

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
	}

	/**
	 * Add health checks.
	 */
	public function add_health_checks() {
		$manager = $this->container->get( 'healthCheckManager' );

		// PRESS7-108: Autosave Interval.
		$manager->add_health_check(
			array(
				'id'    => 'autosave-interval',
				'title' => __( 'Autosave Interval', 'newfold-performance-module' ),
				'pass'  => __( 'Autosaving is set to happen every 30 seconds or more', 'newfold-performance-module' ),
				'fail'  => __( 'Autosaving is set to be frequent, less than every 30 seconds', 'newfold-performance-module' ),
				'text'  => __( 'Setting the autosave interval to a longer period can reduce server load, it is recommended to set it to 30 seconds or more.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( defined( 'AUTOSAVE_INTERVAL' ) && AUTOSAVE_INTERVAL >= 30 );
				},
			)
		);

		// PRESS7-109: Post Revisions.
		$manager->add_health_check(
			array(
				'id'    => 'post-revisions',
				'title' => __( 'Post Revisions', 'newfold-performance-module' ),
				'pass'  => __( 'Number of post revisions is limited to 5 or less', 'newfold-performance-module' ),
				'fail'  => __( 'Number of post revisions is set to a high number', 'newfold-performance-module' ),
				'text'  => __( 'Setting the number of post revisions to a lower number can reduce database bloat.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( defined( 'WP_POST_REVISIONS' ) && WP_POST_REVISIONS <= 5 );
				},
			)
		);

		// PRESS7-110: Empty Trash Days.
		$manager->add_health_check(
			array(
				'id'    => 'empty-trash-days',
				'title' => __( 'Empty Trash Days', 'newfold-performance-module' ),
				'pass'  => __( 'Trash is emptied every 30 days or less', 'newfold-performance-module' ),
				'fail'  => __( 'Trash is emptied less frequently than every 30 days.', 'newfold-performance-module' ),
				'text'  => __( 'Emptying the trash more frequently can reduce database bloat.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS <= 30 );
				},
			)
		);

		// PRESS7-111: Cron Lock Timeout.
		$manager->add_health_check(
			array(
				'id'    => 'wp-cron-lock-timeout',
				'title' => __( 'WP Cron Lock Timeout', 'newfold-performance-module' ),
				'pass'  => __( 'Cron lock timeout is set to 60 seconds or less.', 'newfold-performance-module' ),
				'fail'  => __( 'Cron lock timeout is set to a high number.', 'newfold-performance-module' ),
				'text'  => __( 'Cron lock timeout affects how long a cron job can run for, setting it to a lower number can improve performance.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( defined( 'WP_CRON_LOCK_TIMEOUT' ) && WP_CRON_LOCK_TIMEOUT <= 300 );
				},
			)
		);

		// PRESS7-118: Permalinks.
		$manager->add_health_check(
			array(
				'id'    => 'permalinks',
				'title' => __( 'Permalinks', 'newfold-performance-module' ),
				'pass'  => __( 'Permalinks are pretty', 'newfold-performance-module' ),
				'fail'  => __( 'Permalinks are not set up', 'newfold-performance-module' ),
				'text'  => __( 'Setting permalinks to anything other than plain can improve performance and SEO.', 'newfold-performance-module' ),
				'test'  => function () {
					return empty( get_option( 'permalink_structure' ) );
				},
			)
		);

		// PRESS7-112: Page Caching.
		$manager->add_health_check(
			array(
				'id'    => 'page-caching',
				'title' => __( 'Page Caching', 'newfold-performance-module' ),
				'pass'  => __( 'Page caching is enabled', 'newfold-performance-module' ),
				'fail'  => __( 'Page caching is disabled', 'newfold-performance-module' ),
				'text'  => __( 'Page caching can improve performance by bypassing PHP and database queries for faster page loads.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( get_option( 'newfold_cache_level' ) >= 2 );
				},
			)
		);

		// PRESS7-113: Browser Caching.
		$manager->add_health_check(
			array(
				'id'    => 'browser-caching',
				'title' => __( 'Browser Caching', 'newfold-performance-module' ),
				'pass'  => __( 'Browser caching is enabled', 'newfold-performance-module' ),
				'fail'  => __( 'Browser caching is disabled', 'newfold-performance-module' ),
				'text'  => __( 'Enabling browser caching can improve performance by storing static assets in the browser for faster page loads.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( get_option( 'newfold_cache_level' ) >= 1 );
				},
			)
		);

		// PRESS7-121: Object Caching.
		// Only show object caching health check for Bluehost brand.
		if ( 'bluehost' === $this->container->plugin()->brand ) {
			$manager->add_health_check(
				array(
					'id'      => 'persistent_object_cache',                                                                                                                                                                                                     // Replaces the default test.
					'title' => __( 'Object Caching', 'newfold-performance-module' ),
					'pass'  => __( 'Object caching is enabled', 'newfold-performance-module' ),
					'fail'  => __( 'Object caching is disabled', 'newfold-performance-module' ),
					'text'  => __( 'Object caching saves results from frequent database queries, reducing load times by avoiding repetitive query processing. Object caching is available in all tiers of Bluehost Cloud.', 'newfold-performance-module' ),
					'actions' => sprintf(
						'<a href="https://www.bluehost.com/hosting/cloud" target="_blank">%1$s%2$s</a>',
						__( 'Learn more about Bluehost Cloud Hosting.', 'newfold-performance-module' ),
						sprintf(
							'<span class="screen-reader-text"> (%s)</span><span aria-hidden="true" class="dashicons dashicons-external"></span>',
							__( 'opens in a new tab', 'newfold-performance-module' )
						)
					),
					'test'  => function () {
						return wp_using_ext_object_cache();
					},
				)
			);
		}

		// PRESS7-107: Cloudflare.
		$manager->add_health_check(
			array(
				'id'    => 'cloudflare-active',
				'title' => __( 'Cloudflare enabled', 'newfold-performance-module' ),
				'pass'  => __( 'Cloudflare integration is enabled', 'newfold-performance-module' ),
				'fail'  => __( 'Cloudflare integration is disabled', 'newfold-performance-module' ),
				'text'  => __( 'Cloudflare integration can improve performance and security.', 'newfold-performance-module' ),
				'test'  => function () {
					return isset( $_SERVER['HTTP_CF_RAY'] );
				},
			)
		);

		// PRESS7-119: Lazy Loading.
		$manager->add_health_check(
			array(
				'id'    => 'lazy-loading',
				'title' => __( 'Lazy Loading', 'newfold-performance-module' ),
				'pass'  => __( 'Lazy loading is enabled', 'newfold-performance-module' ),
				'fail'  => __( 'Lazy loading is disabled', 'newfold-performance-module' ),
				'text'  => __( 'Lazy loading can improve performance by only loading images when they are in view.', 'newfold-performance-module' ),
				'test'  => function () {
					$enabled = get_option( 'nfd_image_optimization', array() );
					return ( isset( $enabled['lazy_loading'], $enabled['lazy_loading']['enabled'] ) && $enabled['lazy_loading']['enabled'] );
				},
			)
		);

		// PRESS7-120: Link Prefetching.
		$manager->add_health_check(
			array(
				'id'    => 'link-prefetch',
				'title' => __( 'Link Prefetching', 'newfold-performance-module' ),
				'pass'  => __( 'Link prefetching is enabled', 'newfold-performance-module' ),
				'fail'  => __( 'Link prefetching is disabled', 'newfold-performance-module' ),
				'text'  => __( 'Link prefetching can improve performance by loading pages immediately before they are requested.', 'newfold-performance-module' ),
				'test'  => function () {
					$enabled = get_option( 'nfd_link_prefetch_settings', array() );
					return ( isset( $enabled['activeOnDesktop'] ) && $enabled['activeOnDesktop'] );
				},
			)
		);

		// PRESS7-114: Prioritize Critical CSS.
		$manager->add_health_check(
			array(
				'id'    => 'prioritize-critical-css',
				'title' => __( 'Prioritize Critical CSS', 'newfold-performance-module' ),
				'pass'  => __( 'Critical CSS is prioritized', 'newfold-performance-module' ),
				'fail'  => __( 'Critical CSS is not prioritized', 'newfold-performance-module' ),
				'text'  => __( 'Prioritizing critical CSS can improve performance by loading the most important CSS first.', 'newfold-performance-module' ),
				'test'  => function () {
					return get_option( 'jetpack_boost_status_critical-css', false );
				},
			)
		);

		// PRESS7-115: Defer Non-Essential JavaScript.
		$manager->add_health_check(
			array(
				'id'    => 'defer-non-essential-javascript',
				'title' => __( 'Defer Non-Essential JavaScript', 'newfold-performance-module' ),
				'pass'  => __( 'Non-essential JavaScript is deferred', 'newfold-performance-module' ),
				'fail'  => __( 'Non-essential JavaScript is not deferred', 'newfold-performance-module' ),
				'text'  => __( 'JavaScript can be deferred to improve performance by loading it after the page has loaded.', 'newfold-performance-module' ),
				'test'  => function () {
					return get_option( 'jetpack_boost_status_render-blocking-js', false );
				},
			)
		);

		// PRESS7-116: Concatenate JavaScript.
		$manager->add_health_check(
			array(
				'id'    => 'concatenate-js',
				'title' => __( 'Concatenate JavaScript', 'newfold-performance-module' ),
				'pass'  => __( 'JavaScript files are concatenated', 'newfold-performance-module' ),
				'fail'  => __( 'JavaScript files are not concatenated', 'newfold-performance-module' ),
				'text'  => __( 'Concatenating JavaScript can improve performance by reducing the number of requests.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( ! empty( get_option( 'jetpack_boost_status_minify-js', array() ) ) );
				},
			)
		);

		// PRESS7-117: Concatenate CSS.
		$manager->add_health_check(
			array(
				'id'    => 'concatenate-css',
				'title' => __( 'Concatenate CSS', 'newfold-performance-module' ),
				'pass'  => __( 'CSS files are concatenated', 'newfold-performance-module' ),
				'fail'  => __( 'CSS files are not concatenated', 'newfold-performance-module' ),
				'text'  => __( 'Concatenating CSS can improve performance by reducing the number of requests.', 'newfold-performance-module' ),
				'test'  => function () {
					return ( ! empty( get_option( 'jetpack_boost_status_minify-css', array() ) ) );
				},
			)
		);
	}
}
