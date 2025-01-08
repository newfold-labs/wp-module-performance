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
				'title' => __( 'Autosave Interval', 'newfold-module-performance' ),
				'pass'  => __( 'Autosaving is set to happen every 30 seconds or more', 'newfold-module-performance' ),
				'fail'  => __( 'Autosaving is set to be frequent, less than every 30 seconds', 'newfold-module-performance' ),
				'text'  => __( 'Setting the autosave interval to a longer period can reduce server load, it is recommended to set it to 30 seconds or more.', 'newfold-module-performance' ),
				'test'  => function () {
					return ( defined( 'AUTOSAVE_INTERVAL' ) && AUTOSAVE_INTERVAL >= 30 );
				},
			)
		);

		// PRESS7-109: Post Revisions.
		$manager->add_health_check(
			array(
				'id'    => 'post-revisions',
				'title' => __( 'Post Revisions', 'newfold-module-performance' ),
				'pass'  => __( 'Number of post revisions is limited to 5 or less', 'newfold-module-performance' ),
				'fail'  => __( 'Number of post revisions is set to a high number', 'newfold-module-performance' ),
				'text'  => __( 'Setting the number of post revisions to a lower number can reduce database bloat.', 'newfold-module-performance' ),
				'test'  => function () {
					return ( defined( 'WP_POST_REVISIONS' ) && WP_POST_REVISIONS <= 5 );
				},
			)
		);

		// PRESS7-110: Empty Trash Days.
		$manager->add_health_check(
			array(
				'id'    => 'empty-trash-days',
				'title' => __( 'Empty Trash Days', 'newfold-module-performance' ),
				'pass'  => __( 'Trash is emptied every 30 days or less', 'newfold-module-performance' ),
				'fail'  => __( 'Trash is emptied less frequently than every 30 days', 'newfold-module-performance' ),
				'text'  => __( 'Emptying the trash more frequently can reduce database bloat.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure trash settings.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=content-section#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					return ( defined( 'EMPTY_TRASH_DAYS' ) && EMPTY_TRASH_DAYS <= 30 );
				},
			)
		);

		// PRESS7-111: Cron Lock Timeout.
		$manager->add_health_check(
			array(
				'id'    => 'wp-cron-lock-timeout',
				'title' => __( 'WP Cron Lock Timeout', 'newfold-module-performance' ),
				'pass'  => __( 'Cron lock timeout is set to 60 seconds or less', 'newfold-module-performance' ),
				'fail'  => __( 'Cron lock timeout is set to a high number', 'newfold-module-performance' ),
				'text'  => __( 'Cron lock timeout affects how long a cron job can run for, setting it to a lower number can improve performance.', 'newfold-module-performance' ),
				'test'  => function () {
					return ( defined( 'WP_CRON_LOCK_TIMEOUT' ) && WP_CRON_LOCK_TIMEOUT <= 300 );
				},
			)
		);

		// PRESS7-118: Permalinks.
		$manager->add_health_check(
			array(
				'id'    => 'permalinks',
				'title' => __( 'Permalinks', 'newfold-module-performance' ),
				'pass'  => __( 'Permalinks are pretty', 'newfold-module-performance' ),
				'fail'  => __( 'Permalinks are not set up', 'newfold-module-performance' ),
				'text'  => __( 'Setting permalinks to anything other than plain can improve performance and SEO.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Set up permalinks.', 'newfold-module-performance' ),
						'url'      => admin_url( 'options-permalink.php' ),
						'external' => false,
					),
				),
				'test'  => function () {
					return empty( get_option( 'permalink_structure' ) );
				},
			)
		);

		// PRESS7-112: Page Caching.
		$manager->add_health_check(
			array(
				'id'      => 'page-caching',
				'title'   => __( 'Page Caching', 'newfold-module-performance' ),
				'pass'    => __( 'Page caching is enabled', 'newfold-module-performance' ),
				'fail'    => __( 'Page caching is disabled', 'newfold-module-performance' ),
				'text'    => __( 'Page caching can improve performance by bypassing PHP and database queries for faster page loads.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure caching.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=cache-type#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					return ( get_option( 'newfold_cache_level' ) >= 2 );
				},
			)
		);

		// PRESS7-113: Browser Caching.
		$manager->add_health_check(
			array(
				'id'      => 'browser-caching',
				'title'   => __( 'Browser Caching', 'newfold-module-performance' ),
				'pass'    => __( 'Browser caching is enabled', 'newfold-module-performance' ),
				'fail'    => __( 'Browser caching is disabled', 'newfold-module-performance' ),
				'text'    => __( 'Enabling browser caching can improve performance by storing static assets in the browser for faster page loads.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure caching.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=cache-type#/performance' ),
						'external' => false,
					),
				),
				'test'    => function () {
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
					'title'   => __( 'Object Caching', 'newfold-module-performance' ),
					'pass'    => __( 'Object caching is enabled', 'newfold-module-performance' ),
					'fail'    => __( 'Object caching is disabled', 'newfold-module-performance' ),
					'text'    => __( 'Object caching saves results from frequent database queries, reducing load times by avoiding repetitive query processing. Object caching is available in all tiers of Bluehost Cloud.', 'newfold-module-performance' ),
					'actions' => array(
						array(
							'label' => __( 'Learn more about Bluehost Cloud Hosting.', 'newfold-module-performance' ),
							'url'   => 'https://www.bluehost.com/hosting/cloud',
							'external' => true,
						),
					),
					'test'    => function () {
						return wp_using_ext_object_cache();
					},
				)
			);
		}

		// PRESS7-107: Cloudflare.
		$manager->add_health_check(
			array(
				'id'    => 'cloudflare-active',
				'title' => __( 'Cloudflare enabled', 'newfold-module-performance' ),
				'pass'  => __( 'Cloudflare integration is enabled', 'newfold-module-performance' ),
				'fail'  => __( 'Cloudflare integration is disabled', 'newfold-module-performance' ),
				'text'  => __( 'Cloudflare integration can improve performance and security.', 'newfold-module-performance' ),
				'test'  => function () {
					return isset( $_SERVER['HTTP_CF_RAY'] );
				},
			)
		);

		// Enable when https://github.com/newfold-labs/wp-module-performance/pull/32 is merged.
		// PRESS7-119: Lazy Loading.
		$manager->add_health_check(
			array(
				'id'      => 'lazy-loading',
				'title'   => __( 'Lazy Loading', 'newfold-module-performance' ),
				'pass'    => __( 'Lazy loading is enabled', 'newfold-module-performance' ),
				'fail'    => __( 'Lazy loading is disabled', 'newfold-module-performance' ),
				'text'    => __( 'Lazy loading can improve performance by only loading images when they are in view.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure lazy loading.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=lazy-loading-enabled#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					$enabled = get_option( 'nfd_image_optimization', array() );
					return ( isset( $enabled['lazy_loading'], $enabled['lazy_loading']['enabled'] ) && $enabled['lazy_loading']['enabled'] );
				},
			)
		);

		// PRESS7-120: Link Prefetching.
		$manager->add_health_check(
			array(
				'id'      => 'link-prefetch',
				'title'   => __( 'Link Prefetching', 'newfold-module-performance' ),
				'pass'    => __( 'Link prefetching is enabled', 'newfold-module-performance' ),
				'fail'    => __( 'Link prefetching is disabled', 'newfold-module-performance' ),
				'text'    => __( 'Link prefetching can improve performance by loading pages immediately before they are requested.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure Link prefetching.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=link-prefetch-behavior#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					$enabled = get_option( 'nfd_link_prefetch_settings', array() );
					return ( isset( $enabled['activeOnDesktop'] ) && $enabled['activeOnDesktop'] );
				},
			)
		);

		// PRESS7-114: Prioritize Critical CSS.
		$manager->add_health_check(
			array(
				'id'      => 'prioritize-critical-css',
				'title'   => __( 'Prioritize Critical CSS', 'newfold-module-performance' ),
				'pass'    => __( 'Critical CSS is prioritized', 'newfold-module-performance' ),
				'fail'    => __( 'Critical CSS is not prioritized', 'newfold-module-performance' ),
				'text'    => __( 'Prioritizing critical CSS can improve performance by loading the most important CSS first.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure Critical CSS.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=critical-css#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					return get_option( 'jetpack_boost_status_critical-css', false );
				},
			)
		);

		// PRESS7-115: Defer Non-Essential JavaScript.
		$manager->add_health_check(
			array(
				'id'      => 'defer-non-essential-javascript',
				'title'   => __( 'Defer Non-Essential JavaScript', 'newfold-module-performance' ),
				'pass'    => __( 'Non-essential JavaScript is deferred', 'newfold-module-performance' ),
				'fail'    => __( 'Non-essential JavaScript is not deferred', 'newfold-module-performance' ),
				'text'    => __( 'JavaScript can be deferred to improve performance by loading it after the page has loaded.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure JavaScript deferral.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=render-blocking-js#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					return get_option( 'jetpack_boost_status_render-blocking-js', false );
				},
			)
		);

		// PRESS7-116: Concatenate JavaScript.
		$manager->add_health_check(
			array(
				'id'      => 'concatenate-js',
				'title'   => __( 'Concatenate JavaScript', 'newfold-module-performance' ),
				'pass'    => __( 'JavaScript files are concatenated', 'newfold-module-performance' ),
				'fail'    => __( 'JavaScript files are not concatenated', 'newfold-module-performance' ),
				'text'    => __( 'Concatenating JavaScript can improve performance by reducing the number of requests.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure JavaScript deferral.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=minify-js#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					return ( ! empty( get_option( 'jetpack_boost_status_minify-js', array() ) ) );
				},
			)
		);

		// PRESS7-117: Concatenate CSS.
		$manager->add_health_check(
			array(
				'id'      => 'concatenate-css',
				'title'   => __( 'Concatenate CSS', 'newfold-module-performance' ),
				'pass'    => __( 'CSS files are concatenated', 'newfold-module-performance' ),
				'fail'    => __( 'CSS files are not concatenated', 'newfold-module-performance' ),
				'text'    => __( 'Concatenating CSS can improve performance by reducing the number of requests.', 'newfold-module-performance' ),
				'actions' => array(
					array(
						'label'    => __( 'Configure JavaScript deferral.', 'newfold-module-performance' ),
						'url'      => admin_url( 'admin.php?page=' . $this->container->plugin()->id . '&nfd-target=minify-csss#/performance' ),
						'external' => false,
					),
				),
				'test'  => function () {
					return ( ! empty( get_option( 'jetpack_boost_status_minify-css', array() ) ) );
				},
			)
		);
	}
}
