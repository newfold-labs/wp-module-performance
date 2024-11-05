<?php

namespace NewfoldLabs\WP\Module\Performance\RestApi;

use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Class JetpackController
 *
 * @package NewfoldLabs\WP\Module\Performance
 */
class JetpackController {

	/**
	 * The REST route namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'newfold-performance/v1';

	/**
	 * The REST route base.
	 *
	 * @var string
	 */
	protected $rest_base = '/jetpack';

	/**
	 * Container
	 *
	 * @var [type]
	 */
	protected $container;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	protected $plugin_slug = 'jetpack-boost';


	/**
	 * JetpackController constructor.
	 *
	 * @param Container $container the container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Register API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			$this->rest_base . '/get_options',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_options' ),
				'permission_callback' => function () {
					return 'manage_options';
				},
			)
		);
		\register_rest_route(
			$this->namespace,
			$this->rest_base . '/set_options',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_options' ),
					'permission_callback' => function () {
						return 'manage_options';
					},
				),
			)
		);
	}

	/**
	 * Get options
	 *
	 * @return WP_REST_Response
	 */
	public function get_options() {
		return new \WP_REST_Response(
			array(
				'is_module_active'    => defined( 'JETPACK_BOOST_VERSION' ),
				'critical-css'        => get_option( 'jetpack_boost_status_critical-css' ),
				'render-blocking-js'  => get_option( 'jetpack_boost_status_render-blocking-js' ),
				'minify-js'           => get_option( 'jetpack_boost_status_minify-js', array() ),
				'minify-js-excludes'  => implode( ',', get_option( 'jetpack_boost_ds_minify_js_excludes', array() ) ),
				'minify-css'          => get_option( 'jetpack_boost_status_minify-css', array() ),
				'minify-css-excludes' => implode( ',', get_option( 'jetpack_boost_ds_minify_css_excludes', array() ) ),
			),
			200
		);
	}


	/**
	 * Set options
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function set_options( $request ) {
		$data = $request->get_params();
		if ( isset( $data['field'] ) ) {
			$field = $data['field'];
			if ( in_array( $field['id'], array( 'minify-js-excludes', 'minify-css-excludes' ) ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
				$id    = str_replace( '-', '_', $field['id'] );
				$value = explode( ',', $field['value'] );
				$data  = update_option( 'jetpack_boost_ds_' . $id, $value );
			} else {
				$data = update_option( 'jetpack_boost_status_' . $field['id'], $field['value'] );
			}
		}
		return new \WP_REST_Response(
			array( $data ),
			200
		);
	}
}
