<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\CacheTypes\CacheBase;
use NewfoldLabs\WP\Module\Performance\CacheTypes\File;
use NewfoldLabs\WP\ModuleLoader\Container;
use WP_Forge\Collection\Collection;

/**
 * Cache manager.
 */
class CacheManager {

	/**
	 * The option name where the cache level is stored.
	 *
	 * @var string
	 */
	public const OPTION_CACHE_LEVEL = 'newfold_cache_level';

	/**
	 * Allowed cache level values.
	 *
	 * @var array
	 */
	public const VALID_CACHE_LEVELS = array( 0, 1, 2, 3 );

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

		add_action( 'init', array( $this, 'remove_file_cache_htaccess_for_bh_hg' ) );
	}

	/**
	 * Map of cache types to class names.
	 *
	 * @return string[]
	 */
	protected function classMap() {
		return array(
			'browser'    => __NAMESPACE__ . '\\CacheTypes\\Browser',
			'cloudflare' => __NAMESPACE__ . '\\CacheTypes\\Cloudflare',
			'file'       => __NAMESPACE__ . '\\CacheTypes\\File',
			'nginx'      => __NAMESPACE__ . '\\CacheTypes\\Nginx',
			'sitelock'   => __NAMESPACE__ . '\\CacheTypes\\Sitelock',
			'skip404'    => __NAMESPACE__ . '\\CacheTypes\\Skip404',
		);
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
		$default_cache_types = array( 'browser', 'skip404' );

		if ( $this->container->has( 'cache_types' ) ) {
			$provided_types = $this->container->get( 'cache_types' );
		} else {
			$provided_types = $default_cache_types;
		}

		return is_array( $provided_types )
		? array_intersect( array_map( 'strtolower', $provided_types ), $this->registeredCacheTypes() )
		: $default_cache_types;
	}


	/**
	 * Get an array of page cache type instances based on the enabled cache types.
	 *
	 * @return CacheBase[] An array of cache type instances.
	 */
	public function getInstances() {
		$instances  = array();
		$collection = new Collection( $this->classMap() );
		$map        = $collection->only( $this->enabledCacheTypes() );
		foreach ( $map as $type => $class ) {
			/**
			 * CacheBase instance.
			 *
			 * @var CacheBase $class
			 */
			if ( $class::shouldEnable( $this->container ) ) {
				$instances[ $type ] = new $class();
				$instances[ $type ]->setContainer( $this->container );
			}
		}

		return $instances;
	}

	/**
	 * Retrieves the cache level setting from the database.
	 *
	 * @return int The cache level value, ensuring an integer is returned.
	 */
	public static function get_cache_level() {
		return (int) get_option( self::OPTION_CACHE_LEVEL, 0 );
	}

	/**
	 * Remove the .htaccess rules for the file cache if the brand is Bluehost or HostGator.
	 */
	public function remove_file_cache_htaccess_for_bh_hg() {
		$brand       = $this->container->plugin()->brand;
		$fixed_bh_hg = get_option( 'nfd_file_cache_fixed_bh_hg', false );
		if ( ( 'bluehost' === $brand || 'hostgator' !== $brand ) && ! $fixed_bh_hg ) {
			File::removeRules();
			update_option( 'nfd_file_cache_fixed_bh_hg', true );
			return;
		}
	}
}
