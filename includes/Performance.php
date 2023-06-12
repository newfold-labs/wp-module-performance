<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\CacheTypes\Browser;
use NewfoldLabs\WP\Module\Performance\CacheTypes\File;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Skip404;
use NewfoldLabs\WP\ModuleLoader\Container;

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
	 * @param  Container  $container
	 */
	public function __construct( Container $container ) {

		$this->container = $container;
		$this->configureContainer( $container );

		$this->hooks( $container );

		$cacheManager = new CacheManager( $container );
		$cachePurger  = new CachePurgingService( $cacheManager->getInstances() );

		// Ensure that purgeable cache types are enabled before showing the UI.
		if ( $cachePurger->canPurge() ) {
			add_action( 'admin_bar_menu', [ $this, 'adminBarMenu' ], 999 );
		}

		$container->set( 'cachePurger', $cachePurger );

		$container->set( 'hasMustUsePlugin', file_exists( WPMU_PLUGIN_DIR . '/endurance-page-cache.php' ) );

	}

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
	 */
	public function hooks( Container $container ) {

		add_action( 'admin_init', [ $this, 'registerSettings' ] );

		new OptionListener( self::OPTION_CACHE_LEVEL, [ $this, 'onCacheLevelChange' ] );

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

		add_action( 'after_mod_rewrite_rules', [ $this, 'onRewrite' ] );

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
	 * @param  int|null  $cacheLevel  The cache level.
	 */
	public function onCacheLevelChange( $cacheLevel ) {
		/**
		 * @var ResponseHeaderManager $responseHeaderManager
		 */
		$responseHeaderManager = $this->container->get( 'responseHeaderManager' );
		$responseHeaderManager->addHeader( 'X-Newfold-Cache-Level', absint( $cacheLevel ) );
	}

	public function registerSettings() {

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
	}

	/**
	 * Add options to the WordPress admin bar.
	 *
	 * @param  \WP_Admin_Bar  $wp_admin_bar
	 */
	public function adminBarMenu( \WP_Admin_Bar $wp_admin_bar ) {

		// If the EPC MU plugin exists, don't add an extra cache clearing option.
		if ( $this->container->get( 'hasMustUsePlugin' ) ) {
			return;
		}

		if ( current_user_can( 'manage_options' ) ) {

			$wp_admin_bar->add_node(
				[
					'id'    => 'nfd_purge_menu',
					'title' => __( 'Caching', 'newfold-module-performance' ),
				]
			);

			$wp_admin_bar->add_node(
				[
					'id'     => 'nfd_purge_menu-purge_all',
					'title'  => __( 'Purge All', 'newfold-module-performance' ),
					'parent' => 'nfd_purge_menu',
					'href'   => add_query_arg( [ self::PURGE_ALL => true ] ),
				]
			);

			if ( ! is_admin() ) {
				$wp_admin_bar->add_node(
					[
						'id'     => 'nfd_purge_menu-purge_single',
						'title'  => __( 'Purge This Page', 'newfold-module-performance' ),
						'parent' => 'nfd_purge_menu',
						'href'   => add_query_arg( [ self::PURGE_URL => true ] ),
					]
				);
			}

			$wp_admin_bar->add_node(
				[
					'id'     => 'nfd_purge_menu-cache_settings',
					'title'  => __( 'Cache Settings', 'newfold-module-performance' ),
					'parent' => 'nfd_purge_menu',
					'href'   => admin_url( 'options-general.php#' . Performance::SETTINGS_ID ),
				]
			);
		}

	}

}
