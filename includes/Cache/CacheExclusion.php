<?php
namespace NewfoldLabs\WP\Module\Performance\Cache;

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
	 * Option used to store all pages should be excluded from cache.
	 *
	 * @var string
	 */
	const OPTION_CACHE_EXCLUSION = 'nfd_performance_cache_exclusion';

	/**
	 * Regex for validating cache exclusion value. Must match frontend (CacheExclusion section).
	 * Allows: empty string, or lowercase letters, numbers, commas, hyphens only.
	 *
	 * @var string
	 */
	const CACHE_EXCLUSION_VALIDATE_REGEX = '/^[a-z0-9,-]*$/';

	/**
	 * Normalize cache exclusion input to match frontend: strip all whitespace, remove trailing comma.
	 *
	 * @param string $value Raw cache exclusion value.
	 * @return string Normalized value.
	 */
	public static function normalize( $value ) {
		$normalized = preg_replace( '/\s+/', '', (string) $value );
		return preg_replace( '/,$/', '', $normalized );
	}

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
		return array_merge( $sdk, array( 'cacheExclusion' => get_option( self::OPTION_CACHE_EXCLUSION, get_default_cache_exclusions() ) ) );
	}
}
