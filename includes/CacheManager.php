<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\CacheTypes\CacheBase;
use NewfoldLabs\WP\ModuleLoader\Container;
use wpscholar\Collection;

class CacheManager {

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor.
	 *
	 * @param string[] $supportedCacheTypes Cache types supported by the plugin
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Map of cache types to class names.
	 *
	 * @return string[]
	 */
	protected function classMap() {
		return [
			'browser' => __NAMESPACE__ . '\\CacheTypes\\Browser',
			'file'    => __NAMESPACE__ . '\\CacheTypes\\File',
			'skip404' => __NAMESPACE__ . '\\CacheTypes\\Skip404',
		];
	}

	/**
	 * Get a list of registered cache types.
	 *
	 * @return string[]
	 */
	public function registeredCacheTypes() {
		return array_keys( $this->classMap() );
	}

	/**
	 * Get a list of enabled cache types.
	 *
	 * @return array
	 */
	public function enabledCacheTypes() {
		$cacheTypes = [];
		if ( $this->container->has( 'cache_types' ) ) {
			$providedTypes = $this->container->get( 'cache_types' );
			if ( is_array( $providedTypes ) ) {
				$cacheTypes = array_intersect(
					array_map( 'strtolower', $providedTypes ),
					$this->registeredCacheTypes()
				);
			}
		}

		return $cacheTypes;
	}

	/**
	 * Get an array of page cache type instances based on the enabled cache types.
	 *
	 * @return CacheBase[]
	 */
	public function getInstances() {
		$instances  = [];
		$collection = new Collection( $this->classMap() );
		$map        = $collection->only( $this->enabledCacheTypes() );
		foreach ( $map as $type => $class ) {
			/**
			 * @var CacheBase $class
			 */
			if ( $class::shouldEnable( $this->container ) ) {
				$instances[ $type ] = new $class();
				$instances[ $type ]->setContainer( $this->container );
			}
		}

		return $instances;
	}

}
