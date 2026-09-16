<?php

namespace NewfoldLabs\WP\Module\Performance\Cache;

use NewfoldLabs\WP\ModuleLoader\Container;

use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache;

use function NewfoldLabs\WP\Module\Performance\disable_epc_cache_level;
use function NewfoldLabs\WP\Module\Performance\get_cache_exclusion;
use function NewfoldLabs\WP\Module\Performance\get_cache_level;

/**
 * Cache manager.
 */
class Cache {

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

		$cacheManager = new CacheManager( $container );
		$cachePurger  = new CachePurgingService( $cacheManager->get_instances() );

		$container->set( 'cachePurger', $cachePurger );

		new CacheExclusion( $container );

		$container->set( 'hasMustUsePlugin', file_exists( WPMU_PLUGIN_DIR . '/endurance-page-cache.php' ) );

		$this->hooks();

		add_filter( 'newfold-runtime', array( $this, 'add_to_runtime' ), 100 );
	}

	/**
	 * Add hooks.
	 */
	public function hooks() {
		add_action( 'after_mod_rewrite_rules', array( $this, 'on_rewrite' ) );

		// The clamp otherwise only runs on activation and on a cache level
		// change. EPC is a must-use plugin the host owns, so it can arrive on a
		// site that is already running and already clamped, and then nothing
		// puts it back off: it reads its level as 2 and writes a second expires
		// block next to ours. An admin request re-asserts it. Once the option is
		// already zero the helper is a file check and a comparison.
		//
		// Priority 5 so this lands before EPC's own admin_init reconcile at 10,
		// which then sees the level it is going to keep.
		add_action( 'admin_init', array( $this, 'on_cache_level_change' ), 5 );
	}

	/**
	 * When updating mod rewrite rules, also update our rewrites as appropriate.
	 */
	public function on_rewrite() {
		$this->on_cache_level_change();
	}

	/**
	 * On cache level change, keep EPC switched off.
	 */
	public function on_cache_level_change() {
		disable_epc_cache_level();
	}

	/**
	 * Add to Newfold SDK runtime.
	 *
	 * @param array $sdk SDK data.
	 * @return array SDK data.
	 */
	public function add_to_runtime( $sdk ) {
		// If preference is "on" but the drop-in is missing, restore so runtime has correct enabled state.
		ObjectCache::maybe_restore_dropin();
		$values = array(
			'level'       => get_cache_level(),
			'exclusion'   => get_cache_exclusion(),
			'objectCache' => ObjectCache::get_state(),
		);

		return array_merge( $sdk, array( 'cache' => $values ) );
	}
}
