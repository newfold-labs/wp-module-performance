<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\CacheTypes\Browser;
use NewfoldLabs\WP\Module\Performance\CacheTypes\File;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Skip404;
use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Performance Class
 */
class Performance {

	/**
	 * The option name where the cache level is stored.
	 *
	 * @var string
	 */
	const OPTION_CACHE_LEVEL = 'newfold_cache_level';

	/**
	 * The option name where the "Skip WordPress 404 Handling for Static Files" option is stored.
	 *
	 * @var string
	 */
	const OPTION_SKIP_404 = 'newfold_skip_404_handling';

	/**
	 * URL parameter used to purge the entire cache.
	 *
	 * @var string
	 */
	const PURGE_ALL = 'nfd_purge_all';

	/**
	 * URL parameter used to purge the cache for a specific URL.
	 *
	 * @var string
	 */
	const PURGE_URL = 'nfd_purge_url';

	/**
	 * The HTML ID of the section in the settings where performance options can be managed.
	 */
	const SETTINGS_ID = 'newfold-performance-settings';

	/**
	 * The name of the performance settings section.
	 */
	const SETTINGS_SECTION = 'newfold_performance_settings_section';

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container the container
	 */
	public function __construct( Container $container ) {

		$this->container = $container;
		$this->configureContainer( $container );

		$this->hooks( $container );

		$cacheManager = new CacheManager( $container );
		$cachePurger  = new CachePurgingService( $cacheManager->getInstances() );

		// Ensure that purgeable cache types are enabled before showing the UI.
		if ( $cachePurger->canPurge() ) {
			add_action( 'admin_bar_menu', array( $this, 'adminBarMenu' ), 100 );
		}

		$container->set( 'cachePurger', $cachePurger );

		$container->set( 'hasMustUsePlugin', file_exists( WPMU_PLUGIN_DIR . '/endurance-page-cache.php' ) );
	}

	/**
	 * Constructor.
	 *
	 * @param Container $container the container
	 */
	public function configureContainer( Container $container ) {

		global $is_apache;

		$container->set( 'isApache', $is_apache );

		$container->set(
			'responseHeaderManager',
			$container->service(
				function () {
					return new ResponseHeaderManager();
				}
			)
		);
	}

	/**
	 * Add hooks.
	 *
	 * @param Container $container the container
	 */
	public function hooks( Container $container ) {

		add_action( 'admin_init', array( $this, 'registerSettings' ), 11 );

		new OptionListener( self::OPTION_CACHE_LEVEL, array( $this, 'onCacheLevelChange' ) );

		/**
		 * On CLI requests, mod_rewrite is unavailable, so it fails to update
		 * the .htaccess file when save_mod_rewrite_rules() is called. This
		 * forces that to be true so updates from WP CLI work.
		 */
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			add_filter( 'got_rewrite', '__return_true' );
		}

		add_filter(
			'mod_rewrite_rules',
			function ( $content ) {
				add_action(
					'shutdown',
					function () {
						do_action( 'newfold_update_htaccess' );
					}
				);

				return $content;
			}
		);

		add_action( 'after_mod_rewrite_rules', array( $this, 'onRewrite' ) );
		add_filter( 'action_scheduler_retention_period', array( $this, 'nfd_asr_default' ) );
		add_filter( 'action_scheduler_cleanup_batch_size', array( $this, 'nfd_as_cleanup_batch_size' ) );
	}

	/**
	 * Update the default action scheduler retention period to 5 days instead of 30.
	 * The actions scheduler table tends to grow to gigantic sizes and this should help.
	 *
	 * @hooked action_scheduler_retention_period
	 * @see ActionScheduler_QueueCleaner::delete_old_actions()
	 *
	 * @param int $retention_period Minimum scheduled age in seconds of the actions to be deleted.
	 *
	 * @return int New retention period in seconds.
	 */
	public function nfd_asr_default( $retention_period ) {
		return 5 * constant( 'DAY_IN_SECONDS' );
	}

	/**
	 * Increase the batch size for the cleanup process from default of 20 to 1000.
	 *
	 * @hooked action_scheduler_cleanup_batch_size
	 * @see ActionScheduler_QueueCleaner::get_batch_size()
	 *
	 * @param int $batch_size Existing batch size; default is 20.
	 *
	 * @return int 1000 when running the cleanup process, otherwise the existing batch size.
	 */
	public function nfd_as_cleanup_batch_size( $batch_size ) {
		/**
		 * Apply only to {@see ActionScheduler_QueueCleaner::delete_old_actions()} and not to
		 * {@see ActionScheduler_QueueCleaner::reset_timeouts()} or
		 * {@see ActionScheduler_QueueCleaner::mark_failures()} batch sizes.
		 */
		if ( ! did_filter( 'action_scheduler_retention_period' ) ) {
			return $batch_size;
		}

		return 1000;
	}

	/**
	 * When updating mod rewrite rules, also update our rewrites as appropriate.
	 */
	public function onRewrite() {
		$this->onCacheLevelChange( getCacheLevel() );
	}

	/**
	 * On cache level change, update the response headers.
	 *
	 * @param int|null $cacheLevel The cache level.
	 */
	public function onCacheLevelChange( $cacheLevel ) {
		/**
		 * Respone Header Manager from container
		 *
		 * @var ResponseHeaderManager $responseHeaderManager
		 */
		$responseHeaderManager = $this->container->get( 'responseHeaderManager' );
		$responseHeaderManager->addHeader( 'X-Newfold-Cache-Level', absint( $cacheLevel ) );

		// Remove the old option from EPC, if it exists
		if ( $this->container->get( 'hasMustUsePlugin' ) && absint( get_option( 'endurance_cache_level', 0 ) ) ) {
			update_option( 'endurance_cache_level', 0 );
			delete_option( 'endurance_cache_level' );
		}
	}

	/**
	 * Register settings
	 */
	public function registerSettings() {

		global $wp_settings_fields;

		$section_name = self::SETTINGS_SECTION;

		add_settings_section(
			$section_name,
			'<span id="' . self::SETTINGS_ID . '">' . esc_html__( 'Caching', 'newfold-performance-module' ) . '</span>',
			'__return_false',
			'general'
		);

		add_settings_field(
			self::OPTION_CACHE_LEVEL,
			__( 'Cache Level', 'newfold-performance-module' ),
			__NAMESPACE__ . '\\getCacheLevelDropdown',
			'general',
			$section_name
		);

		register_setting( 'general', self::OPTION_CACHE_LEVEL );

		// Remove the setting from EPC if it exists - TODO: Remove when no longer using EPC
		if ( $this->container->get( 'hasMustUsePlugin' ) ) {
			unset( $wp_settings_fields['general']['epc_settings_section'] );
			unregister_setting( 'general', 'endurance_cache_level' );
		}
	}

	/**
	 * Add options to the WordPress admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar the admin bar
	 */
	public function adminBarMenu( \WP_Admin_Bar $wp_admin_bar ) {

		// If the EPC MU plugin exists, remove its cache clearing options.
		if ( $this->container->get( 'hasMustUsePlugin' ) ) {
			$wp_admin_bar->remove_node( 'epc_purge_menu' );
		}

		if ( current_user_can( 'manage_options' ) ) {

			$wp_admin_bar->add_node(
				array(
					'id'    => 'nfd_purge_menu',
					'title' => __( 'Caching', 'newfold-module-performance' ),
				)
			);

			$wp_admin_bar->add_node(
				array(
					'id'     => 'nfd_purge_menu-purge_all',
					'title'  => __( 'Purge All', 'newfold-module-performance' ),
					'parent' => 'nfd_purge_menu',
					'href'   => add_query_arg( array( self::PURGE_ALL => true ) ),
				)
			);

			if ( ! is_admin() ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'nfd_purge_menu-purge_single',
						'title'  => __( 'Purge This Page', 'newfold-module-performance' ),
						'parent' => 'nfd_purge_menu',
						'href'   => add_query_arg( array( self::PURGE_URL => true ) ),
					)
				);
			}

			$wp_admin_bar->add_node(
				array(
					'id'     => 'nfd_purge_menu-cache_settings',
					'title'  => __( 'Cache Settings', 'newfold-module-performance' ),
					'parent' => 'nfd_purge_menu',
					'href'   => admin_url( 'options-general.php#' . Performance::SETTINGS_ID ),
				)
			);
		}
	}
}
