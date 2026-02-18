<?php

namespace NewfoldLabs\WP\Module\Performance\RestApi;

use NewfoldLabs\WP\Module\Performance\Permissions;
use NewfoldLabs\WP\Module\Performance\Cache\CacheExclusion;
use NewfoldLabs\WP\Module\Performance\Cache\CacheManager;
use NewfoldLabs\WP\Module\Performance\Cache\CachePurgingService;
use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache;

use function NewfoldLabs\WP\ModuleLoader\container;
use function NewfoldLabs\WP\Module\Performance\get_cache_level;
use function NewfoldLabs\WP\Module\Performance\get_cache_exclusion;

/**
 * Class CacheExclusionController
 */
class CacheController {
	/**
	 * REST namespace
	 *
	 * @var string
	 */
	protected $namespace = 'newfold-performance/v1';

	/**
	 * REST base
	 *
	 * @var string
	 */
	protected $rest_base = '/cache';

	/**
	 * Registers rest routes for PluginsController class.
	 *
	 * @return void
	 */
	public function register_routes() {

		\register_rest_route(
			$this->namespace,
			$this->rest_base . '/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( Permissions::class, 'rest_is_authorized_admin' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( Permissions::class, 'rest_is_authorized_admin' ),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'purge_all' ),
					'permission_callback' => array( Permissions::class, 'rest_is_authorized_admin' ),
				),
			)
		);
	}

	/**
	 * Get the settings
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		// If user preference is "on" but the drop-in is missing, restore it so the UI shows enabled.
		ObjectCache::maybe_restore_dropin();
		$response = array(
			'cacheExclusion' => get_cache_exclusion(),
			'cacheLevel'     => get_cache_level(),
			'objectCache'    => ObjectCache::get_state(),
		);
		return new \WP_REST_Response( $response, 200 );
	}

	/**
	 * Update the settings
	 *
	 * @param \WP_REST_Request $request the request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( \WP_REST_Request $request ) {

		if ( $request->has_param( 'cacheExclusion' ) ) {
			$cache_exclusion = $request->get_param( 'cacheExclusion' );
			$normalized      = CacheExclusion::normalize( $cache_exclusion );
			if ( ! preg_match( CacheExclusion::CACHE_EXCLUSION_VALIDATE_REGEX, $normalized ) ) {
				return new \WP_REST_Response(
					array(
						'result'  => false,
						'message' => 'Invalid cache exclusion format.',
					),
					400
				);
			}
			$result = update_option( CacheExclusion::OPTION_CACHE_EXCLUSION, $normalized );
			if ( $result ) {
				return new \WP_REST_Response( array( 'result' => true ), 200 );
			}
			return new \WP_REST_Response( array( 'result' => false ), 400 );
		}

		if ( $request->has_param( 'cacheLevel' ) ) {
			$cache_level = (int) $request->get_param( 'cacheLevel' );
			$result      = update_option( CacheManager::OPTION_CACHE_LEVEL, $cache_level );
			if ( $result ) {
				// When cache is disabled, turn off object caching too so the UI stays in sync.
				if ( $cache_level <= 0 ) {
					ObjectCache::disable();
				}
				$response = array( 'result' => true );
				if ( $cache_level <= 0 ) {
					$response['objectCache'] = ObjectCache::get_state();
				}
				return new \WP_REST_Response( $response, 200 );
			}
			return new \WP_REST_Response( array( 'result' => false ), 400 );
		}

		if ( $request->has_param( 'objectCache' ) ) {
			$object_cache = $request->get_param( 'objectCache' );
			if ( is_array( $object_cache ) && isset( $object_cache['enabled'] ) ) {
				$enable = (bool) $object_cache['enabled'];
				if ( $enable ) {
					$out = ObjectCache::enable();
				} else {
					$out = ObjectCache::disable();
				}
				if ( $out['success'] ) {
					// When enabling: only purge page caches so we don't flush object cache (avoids logging out the user).
					// When disabling: purge everything including object cache.
					$purger = container()->get( 'cachePurger' );
					if ( $enable ) {
						$purger->purge_page_caches();
					} else {
						$purger->purge_all();
					}
					return new \WP_REST_Response( array( 'result' => true ), 200 );
				}
				return new \WP_REST_Response(
					array(
						'result'  => false,
						'message' => isset( $out['message'] ) ? $out['message'] : '',
					),
					400
				);
			}
		}

		return new \WP_REST_Response( array( 'result' => false ), 400 );
	}

	/**
	 * Clears the entire cache (page cache and object cache when enabled).
	 */
	public function purge_all() {

		container()->get( 'cachePurger' )->purge_all();
		ObjectCache::flush_object_cache();

		return array(
			'status'  => 'success',
			'message' => 'Cache purged',
		);
	}
}
