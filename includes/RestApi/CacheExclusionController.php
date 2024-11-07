<?php

namespace NewfoldLabs\WP\Module\Performance\RestApi;

use NewfoldLabs\WP\Module\ECommerce\Permissions;
use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Class CacheExclusionController
 */
class CacheExclusionController {


	/**
	 * REST namespace
	 *
	 * @var string
	 */
	protected $namespace = 'newfold-ecommerce/v1';

	/**
	 * REST base
	 *
	 * @var string
	 */
	protected $rest_base = '/cacheexclusion';

	/**
	 * Container loaded from the brand plugin.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor
	 *
	 * @param Container $container the container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

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
				'settings' => get_option( 'cache_exclusion', '' ),
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
		$cache_exclusion = $request->get_param( 'cache_exclusion' );
		if ( update_option( 'cache_exclusion', $cache_exclusion ) ) {
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
