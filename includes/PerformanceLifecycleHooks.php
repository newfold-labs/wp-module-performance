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
use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache;
use NewfoldLabs\WP\Module\Performance\Images\ImageRewriteHandler;
use NewfoldLabs\WP\Module\Performance\Skip404\Skip404;

use function NewfoldLabs\WP\Module\Performance\get_cache_level;
use function NewfoldLabs\WP\ModuleLoader\container;

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
	 * Whether plugin_hooks has already run (avoids double-registering).
	 *
	 * @var bool
	 */
	protected $plugin_hooks_done = false;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'newfold_container_set', array( $this, 'plugin_hooks' ) );
			add_action( 'plugins_loaded', array( $this, 'hooks' ) );
			// If Redis config was removed from wp-config but our drop-in is still present, remove it so the site does not break.
			add_action( 'plugins_loaded', array( ObjectCache::class, 'maybe_remove_dropin_if_unavailable' ), 1 );

			// Do not call container() here. The ModuleLoader's container() creates and locks in
			// an empty container if called before the host plugin calls setContainer(), which
			// causes "No entry was found for 'plugin'" when Features etc. run.

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
		if ( $this->plugin_hooks_done ) {
			return;
		}
		$this->plugin_hooks_done = true;
		$this->container         = $container;

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
		// Purge object cache on shutdown so the next request reads active_plugins from DB (not Redis).
		add_action( 'shutdown', array( $this, 'purge_object_cache_on_shutdown' ), PHP_INT_MAX );
		// Cache feature bits.
		File::on_activation();
		Browser::on_activation();

		// Restore object-cache drop-in if Redis constants exist and user had it enabled before deactivation.
		ObjectCache::maybe_restore_on_activation();

		// Image rewrite rules.
		ImageRewriteHandler::on_activation();

		// Skip404 rules based on current option value.
		Skip404::maybe_add_rules( Skip404::get_value() );

		// Ensure EPC is off and removes its rules
		$this->nfd_force_disable_epc_options();
	}

	/**
	 * Delete object-cache keys for active_plugins and alloptions so the next request reads from DB.
	 * Prevents stale plugin list when object cache (e.g. Redis) is enabled, which can make
	 * activation/deactivation appear to fail the first time.
	 *
	 * @return void
	 */
	protected function delete_plugin_list_option_cache() {
		ObjectCache::clear_options_object_cache();
	}

	/**
	 * Purge object cache (options + full flush + runtime) on shutdown after activate/deactivate.
	 * Ensures the next request reads active_plugins from DB even if something re-cached after our hooks.
	 *
	 * @return void
	 */
	public function purge_object_cache_on_shutdown() {
		$this->delete_plugin_list_option_cache();
		ObjectCache::flush_object_cache();
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
	}

	/**
	 * Deactivation/Disable: remove Cache + Skip404.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function on_deactivation() {
		// Purge object cache on shutdown so the next request reads active_plugins from DB (not Redis).
		add_action( 'shutdown', array( $this, 'purge_object_cache_on_shutdown' ), PHP_INT_MAX );

		// Cache feature bits.
		File::on_deactivation();
		Browser::on_deactivation();

		// Remove our object-cache drop-in if present (only deletes if it's our file).
		ObjectCache::on_deactivation();

		// Remove image rewrite rules.
		ImageRewriteHandler::on_deactivation();

		// Remove all headers written by ResponseHeaderManager.
		$response_header_manager = $this->get_response_header_manager();
		if ( $response_header_manager ) {
			$response_header_manager->remove_all_headers();
		}

		// Remove Skip404 rules.
		Skip404::remove_rules();

		// Hand settings back to EPC to match the brand plugin's current values
		$this->nfd_sync_epc_from_brand();
	}

	/**
	 * On cache level change, update the response header and clean up legacy EPC option.
	 *
	 * @return void
	 */
	public function on_cache_level_change() {

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

	/**
	 * Force Endurance Page Cache off by clamping its options to 0.
	 * Triggers EPC to remove its own rules, then tidies the options.
	 */
	private function nfd_force_disable_epc_options(): void {
		$changed = false;

		// Clamp EPC options to 0 so its own code tears down rules.
		if ( (int) get_option( 'endurance_cache_level', 0 ) !== 0 ) {
			update_option( 'endurance_cache_level', 0 );
			$changed = true;
		}
		if ( (int) get_option( 'epc_skip_404_handling', 0 ) !== 0 ) {
			update_option( 'epc_skip_404_handling', 0 );
			$changed = true;
		}

		// If anything changed, write .htaccess once and tidy options.
		if ( $changed ) {
			if ( ! function_exists( 'save_mod_rewrite_rules' ) ) {
				require_once ABSPATH . 'wp-admin/includes/misc.php';
			}
			// Causes WP to regenerate rules; EPC listeners (if loaded) have just been triggered by the updates above.
			save_mod_rewrite_rules();

			// Optional cleanup so these don't linger in the DB.
			delete_option( 'endurance_cache_level' );
			delete_option( 'epc_skip_404_handling' );
		}
	}

	/**
	 * When the brand plugin is deactivated, mirror its current settings into EPC.
	 * - EPC cache level (endurance_cache_level) is set to the current brand cache level (0–3).
	 * - EPC skip404 (epc_skip_404_handling) is set to the current brand skip404 value (0/1).
	 */
	private function nfd_sync_epc_from_brand(): void {
		// Clamp to EPC's range 0–3
		$brand_level = (int) max( 0, min( 3, get_cache_level() ) );

		// Brand Skip404: true/false -> EPC 1/0
		$brand_skip404 = Skip404::get_value() ? 1 : 0;

		// Write EPC options to reflect the brand plugin's current state
		update_option( 'endurance_cache_level', $brand_level );
		update_option( 'epc_skip_404_handling', $brand_skip404 );

		// Ask WP to regenerate .htaccess so EPC can add/remove its own rules accordingly
		if ( ! function_exists( 'save_mod_rewrite_rules' ) ) {
			require_once ABSPATH . 'wp-admin/includes/misc.php';
		}
		save_mod_rewrite_rules();
	}
}
