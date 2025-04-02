<?php

namespace NewfoldLabs\WP\Module\Performance;

use Automattic\Jetpack\Current_Plan;

use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Installer\Services\PluginInstaller;
use NewfoldLabs\WP\Module\Performance\Images\ImageManager;
use NewfoldLabs\WP\Module\Performance\RestApi\RestApi;
use NewfoldLabs\WP\Module\Performance\Data\Constants;
use NewfoldLabs\WP\Module\Performance\Services\I18nService;
use NewfoldLabs\WP\Module\Performance\LinkPrefetch\LinkPrefetch;
use NewfoldLabs\WP\Module\Performance\Cache\Cache;
use NewfoldLabs\WP\Module\Performance\Cache\ResponseHeaderManager;
use NewfoldLabs\WP\Module\Performance\Skip404\Skip404;
use NewfoldLabs\WP\Module\Performance\JetpackBoost\JetpackBoost;

use function NewfoldLabs\WP\Module\Performance\get_cache_level;

/**
 * Main class for the performance module.
 */
class Performance {

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

		$this->hooks();

		new Cache( $container );
		new Skip404( $container );
		new PerformanceWPCLI();
		new Constants( $container );
		new ImageManager( $container );
		new HealthChecks( $container );

		new LinkPrefetch( $container );

		new JetpackBoost( $container );

		add_action( 'admin_bar_menu', array( $this, 'admin_bar_menu' ), 100 );
		add_action( 'admin_menu', array( $this, 'add_sub_menu_page' ) );
		add_filter( 'nfd_plugin_subnav', array( $this, 'add_nfd_subnav' ) );

		if ( Permissions::is_authorized_admin() || Permissions::rest_is_authorized_admin() ) {
			new RestAPI();
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		! defined( 'NFD_PERFORMANCE_PLUGIN_LANGUAGES_DIR' ) && define( 'NFD_PERFORMANCE_PLUGIN_LANGUAGES_DIR', dirname( $container->plugin()->file ) . '/vendor/newfold-labs/wp-module-performance/languages' );
		new I18nService( $container );
	}

	/**
	 * Constructor.
	 *
	 * @param Container $container the container.
	 */
	public function configureContainer( Container $container ) {

		$is_apache = false;

		// Ensure $is_apache is properly set, with a fallback for WP-CLI environment
		if ( NFD_WPCLI::is_executing_wp_cli() ) {
			// Attempt to detect Apache based on the SERVER_SOFTWARE header
			$is_apache = isset( $_SERVER['SERVER_SOFTWARE'] ) && stripos( $_SERVER['SERVER_SOFTWARE'], 'apache' ) !== false;

			// Check for the existence of an .htaccess file (commonly used in Apache environments)
			if ( ! $is_apache && file_exists( ABSPATH . '.htaccess' ) ) {
				$is_apache = true;
			}
		} else {
			global $is_apache;
		}

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
	 */
	public function hooks() {

		add_action( 'admin_init', array( $this, 'remove_epc_settings' ), 99 );

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

		add_filter( 'action_scheduler_retention_period', array( $this, 'nfd_asr_default' ) );
		add_filter( 'action_scheduler_cleanup_batch_size', array( $this, 'nfd_as_cleanup_batch_size' ) );
	}

	/**
	 * Remove EPC Settings if needed
	 *
	 * @return void
	 */
	public function remove_epc_settings() {
		global $wp_settings_fields, $wp_settings_sections;
		//phpcs:ignore
		// Remove the setting from EPC if it exists - TODO: Remove when no longer using EPC
		if ( $this->container->get( 'hasMustUsePlugin' ) ) {
			unset( $wp_settings_fields['general']['epc_settings_section'] );
			unset( $wp_settings_sections['general']['epc_settings_section'] );
			unregister_setting( 'general', 'endurance_cache_level' );
			unregister_setting( 'general', 'epc_skip_404_handling' );
		}
	}

	/**
	 * Update the default action scheduler retention period to 5 days instead of 30.
	 * The actions scheduler table tends to grow to gigantic sizes and this should help.
	 *
	 * @hooked action_scheduler_retention_period
	 * @see ActionScheduler_QueueCleaner::delete_old_actions()
	 *
	 * @return int New retention period in seconds.
	 */
	public function nfd_asr_default() {
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
	 * Add options to the WordPress admin bar.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar the admin bar.
	 */
	public function admin_bar_menu( \WP_Admin_Bar $wp_admin_bar ) {

		// If the EPC MU plugin exists, remove its cache clearing options.
		if ( $this->container->get( 'hasMustUsePlugin' ) ) {
			$wp_admin_bar->remove_node( 'epc_purge_menu' );
		}

		if ( current_user_can( 'manage_options' ) ) {
			$wp_admin_bar->add_node(
				array(
					'id'    => 'nfd_purge_menu',
					'title' => __( 'Caching', 'wp-module-performance' ),
				)
			);

			$cache_level = get_cache_level();
			if ( $cache_level > 0 ) {
				$wp_admin_bar->add_node(
					array(
						'id'     => 'nfd_purge_menu-purge_all',
						'title'  => __( 'Purge All', 'wp-module-performance' ),
						'parent' => 'nfd_purge_menu',
						'href'   => add_query_arg( array( self::PURGE_ALL => true ) ),
					)
				);

				if ( ! is_admin() ) {
					$wp_admin_bar->add_node(
						array(
							'id'     => 'nfd_purge_menu-purge_single',
							'title'  => __( 'Purge This Page', 'wp-module-performance' ),
							'parent' => 'nfd_purge_menu',
							'href'   => add_query_arg( array( self::PURGE_URL => true ) ),
						)
					);
				}
			}

			$brand = $this->container->get( 'plugin' )['id'];
			$wp_admin_bar->add_node(
				array(
					'id'     => 'nfd_purge_menu-cache_settings',
					'title'  => __( 'Cache Settings', 'wp-module-performance' ),
					'parent' => 'nfd_purge_menu',
					'href'   => admin_url( "admin.php?page=$brand#/performance" ),
				)
			);
		}
	}
	/**
	 * Add performance menu in WP/Settings
	 */
	public function add_sub_menu_page() {
		$brand = $this->container->get( 'plugin' )['id'];
		add_management_page(
			__( 'Performance', 'wp-module-performance' ),
			__( 'Performance', 'wp-module-performance' ),
			'manage_options',
			admin_url( "admin.php?page=$brand#/performance" ),
			null,
			5
		);
	}

	/**
	 * Add to the Newfold subnav.
	 *
	 * @param array $subnav The nav array.
	 * @return array The filtered nav array
	 */
	public function add_nfd_subnav( $subnav ) {
		$brand = $this->container->get( 'plugin' )['id'];
		$performance = array(
			'route'    => $brand . '#/performance',
			'title'    => __( 'Performance', 'wp-module-performance' ),
			'priority' => 30,
		);
		array_push( $subnav, $performance );
		return $subnav;
	}

	/**
	 * Enqueue styles and styles in admin
	 */
	public function enqueue_styles() {
		$brand = $this->container->plugin()->brand;
		if ( is_settings_page( $brand ) ) {
			$plugin_url = $this->container->plugin()->url . get_styles_path();
			wp_register_style( 'wp-module-performance-styles', $plugin_url, array(), $this->container->plugin()->version );
			wp_enqueue_style( 'wp-module-performance-styles' );
		}
	}
}
