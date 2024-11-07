<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\CacheTypes\CacheBase;
use NewfoldLabs\WP\Module\Performance\RestApi\CacheExclusionController;
use NewfoldLabs\WP\ModuleLoader\Container;
use WP_Forge\Collection\Collection;

use function NewfoldLabs\WP\Module\Performance\getDefaultCacheExclusions;

class CacheManager {

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Array map of API controllers.
	 *
	 * @var array
	 */
	protected $controllers = array(
		'NewfoldLabs\\WP\\Module\\Performance\\RestApi\\CacheExclusionController',
	);

	/**
	 * Constructor.
	 *
	 * @param string[] $supportedCacheTypes Cache types supported by the plugin
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'newfold-runtime', array( $this, 'add_to_runtime' ) );
	}

	/**
	 * Register API routes.
	 */
	public function register_routes() {
		foreach ( $this->controllers as $Controller ) {
			/**
			 * Get an instance of the WP_REST_Controller.
			 *
			 * @var $instance \WP_REST_Controller
			 */
			$instance = new $Controller( $this->container );
			$instance->register_routes();
		}
	}
	
	/**
	 * Add values to the runtime object.
	 *
	 * @param array $sdk The runtime object.
	 *
	 * @return array
	 */
	public function add_to_runtime( $sdk ) {
		return array_merge( $sdk, array( 'cacheExclusion' => get_option( 'cache_exclusion', getDefaultCacheExclusions() ) ) );
	}

	/**
	 * Map of cache types to class names.
	 *
	 * @return string[]
	 */
	protected function classMap() {
		return [
			'browser'    => __NAMESPACE__ . '\\CacheTypes\\Browser',
			'cloudflare' => __NAMESPACE__ . '\\CacheTypes\\Cloudflare',
			'file'       => __NAMESPACE__ . '\\CacheTypes\\File',
			'nginx'      => __NAMESPACE__ . '\\CacheTypes\\Nginx',
			'sitelock'   => __NAMESPACE__ . '\\CacheTypes\\Sitelock',
			'skip404'    => __NAMESPACE__ . '\\CacheTypes\\Skip404',
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
