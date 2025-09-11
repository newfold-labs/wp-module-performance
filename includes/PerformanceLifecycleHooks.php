<?php
/**
 * PerformanceLifecycleHooks
 *
 * Unified lifecycle wiring for Performance features:
 * - Cache headers (File + Browser types, ResponseHeaderManager)
 * - Skip404 rules (.htaccess fragment via HtaccessApi)
 *
 * @package NewfoldLabs\WP\Module\Performance
 * @since 1.0.0
 */

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Cache\CacheManager;
use NewfoldLabs\WP\Module\Performance\Cache\ResponseHeaderManager;
use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Performance\OptionListener;
use NewfoldLabs\WP\Module\Performance\Cache\Types\Browser;
use NewfoldLabs\WP\Module\Performance\Cache\Types\File;
use NewfoldLabs\WP\Module\Performance\Images\ImageRewriteHandler;
use NewfoldLabs\WP\Module\Performance\Skip404\Skip404;

use function NewfoldLabs\WP\Module\Performance\get_cache_level;

/**
 * Class PerformanceLifecycleHooks
 *
 * Combines lifecycle hooks for Cache and Skip404 so you can bootstrap once.
 *
 * @since 1.0.0
 */
class PerformanceLifecycleHooks {

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'newfold_container_set', array( $this, 'plugin_hooks' ) );
			add_action( 'plugins_loaded', array( $this, 'hooks' ) );

			// Keep Cache level header in sync with option changes.
			new OptionListener( CacheManager::OPTION_CACHE_LEVEL, array( $this, 'on_cache_level_change' ) );
		}
	}

	/**
	 * Hooks for plugin activation/deactivation.
	 *
	 * @param Container $container From the plugin.
	 * @return void
	 */
	public function plugin_hooks( Container $container ) {
		$this->container = $container;

		register_activation_hook(
			$container->plugin()->file,
			array( $this, 'on_activation' )
		);

		register_deactivation_hook(
			$container->plugin()->file,
			array( $this, 'on_deactivation' )
		);
	}

	/**
	 * Add feature enable/disable hooks.
	 *
	 * @return void
	 */
	public function hooks() {
		add_action(
			'newfold/features/action/onEnable:performance',
			array( $this, 'on_activation' )
		);

		add_action(
			'newfold/features/action/onDisable:performance',
			array( $this, 'on_deactivation' )
		);
	}

	/**
	 * Activation/Enable: apply Cache + Skip404.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function on_activation() {
		// Cache feature bits.
		File::on_activation();
		Browser::on_activation();

		// Image rewrite rules.
		ImageRewriteHandler::on_activation();

		// Add/refresh cache-level response header.
		$response_header_manager = $this->get_response_header_manager();
		if ( $response_header_manager ) {
			$response_header_manager->add_header( 'X-Newfold-Cache-Level', absint( get_cache_level() ) );
		}

		// Skip404 rules based on current option value.
		Skip404::maybe_add_rules( Skip404::get_value() );
	}

	/**
	 * Deactivation/Disable: remove Cache + Skip404.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function on_deactivation() {
		// Cache feature bits.
		File::on_deactivation();
		Browser::on_deactivation();

		// Remove image rewrite rules.
		ImageRewriteHandler::on_deactivation();

		// Remove all headers written by ResponseHeaderManager.
		$response_header_manager = $this->get_response_header_manager();
		if ( $response_header_manager ) {
			$response_header_manager->remove_all_headers();
		}

		// Remove Skip404 rules.
		Skip404::remove_rules();
	}

	/**
	 * On cache level change, update the response header and clean up legacy EPC option.
	 *
	 * @param int|null $cache_level The cache level.
	 * @return void
	 */
	public function on_cache_level_change( $cache_level ) {
		/**
		 * Response Header Manager from container.
		 *
		 * @var \NewfoldLabs\WP\Module\Performance\ResponseHeaderManager $response_header_manager
		 */
		$response_header_manager = $this->get_response_header_manager();
		if ( $response_header_manager ) {
			$response_header_manager->add_header( 'X-Newfold-Cache-Level', absint( $cache_level ) );
		}

		// Remove the old option from EPC, if it exists.
		if ( $this->container && $this->container->get( 'hasMustUsePlugin' ) && absint( get_option( 'endurance_cache_level', 0 ) ) ) {
			update_option( 'endurance_cache_level', 0 );
			delete_option( 'endurance_cache_level' );
		}
	}

	/**
	 * Helper to fetch ResponseHeaderManager from the container (if available).
	 *
	 * @return \NewfoldLabs\WP\Module\Performance\ResponseHeaderManager|null
	 */
	protected function get_response_header_manager() {
		return new ResponseHeaderManager();
	}
}
