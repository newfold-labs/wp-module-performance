<?php
namespace NewfoldLabs\WP\Module\Performance\RestApi;

/**
 * Performace Rest Api Class
 */
final class RestApi {

	/**
	 * List of custom REST API controllers
	 *
	 * @var array
	 */
	protected $controllers = array(
		'NewfoldLabs\\WP\\Module\\Performance\\RestApi\\LinkPrefetchController',
	);

	/**
	 * Setup the custom REST API
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
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
			$instance = new $Controller();
			$instance->register_routes();
		}
	}
}
