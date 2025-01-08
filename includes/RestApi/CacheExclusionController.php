<?php

namespace NewfoldLabs\WP\Module\Performance\RestApi;

use NewfoldLabs\WP\Module\ECommerce\Permissions;
use NewfoldLabs\WP\Module\Performance\CacheExclusion;

use function NewfoldLabs\WP\Module\Performance\get_default_cache_exclusions;

/**
 * Class CacheExclusionController
 */
class CacheExclusionController {
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
	protected $rest_base = '/cache-exclusion';

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
			)
		);
		\register_rest_route(
			$this->namespace,
			$this->rest_base . '/update',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
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
		return new \WP_REST_Response(
			array(
				'cacheExclusion' => get_option( CacheExclusion::OPTION_CACHE_EXCLUSION, get_default_cache_exclusions() ),
			),
			200
		);
	}

	/**
	 * Update the settings
	 *
	 * @param \WP_REST_Request $request the request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( \WP_REST_Request $request ) {
		$cache_exclusion = $request->get_param( 'cacheExclusion' );
		if ( update_option( CacheExclusion::OPTION_CACHE_EXCLUSION, $cache_exclusion ) ) {
			return new \WP_REST_Response(
				array(
					'result' => true,
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'result' => false,
			),
			400
		);
	}
}
