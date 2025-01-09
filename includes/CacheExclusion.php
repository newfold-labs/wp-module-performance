<?php
namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\ModuleLoader\Container;

use function NewfoldLabs\WP\Module\Performance\get_default_cache_exclusions;

/**
 * Cache Exclusion Class
 */
class CacheExclusion {
	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Option used to store cache exclusion settings.
	 *
	 * @var string
	 */
	const OPTION_CACHE_EXCLUSION = 'nfd_performance_cache_exclusion';

	/**
	 * Constructor.
	 *
	 * @param Container $container the container
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		add_filter( 'newfold-runtime', array( $this, 'add_to_runtime' ) );
	}

	/**
	 * Add values to the runtime object.
	 *
	 * @param array $sdk The runtime object.
	 *
	 * @return array
	 */
	public function add_to_runtime( $sdk ) {
		$cache_exclusion = get_option( self::OPTION_CACHE_EXCLUSION, $this->get_default_cache_exclusion() );

		return array_merge( $sdk, array( 'cacheExclusion' => $cache_exclusion ) );
	}

	/**
	 * Get default cache exclusion settings.
	 *
	 * @return array
	 */
	private function get_default_cache_exclusion() {
		return array(
			'excludedUrls'         => get_default_cache_exclusions(),
			'doNotCacheErrorPages' => false,
		);
	}
}
