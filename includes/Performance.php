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
	 * @param Container $container
	 */
	public function __construct( Container $container ) {

		$this->container = $container;
		$this->configureContainer( $container );

		$this->hooks( $container );

		$cacheManager = new CacheManager( $container );
		new CachePurgingService( $cacheManager->getInstances() );

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

		// On deactivation, remove htaccess rules
		register_deactivation_hook(
			$container->plugin()->file,
			function () {

				Skip404::onDeactivation();
				File::onDeactivation();
				Browser::onDeactivation();

				// Remove all headers from .htaccess
				$responseHeaderManager = new ResponseHeaderManager();
				$responseHeaderManager->removeAllHeaders();

			}
		);

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
	 * @param int|null $cacheLevel The cache level.
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

}
