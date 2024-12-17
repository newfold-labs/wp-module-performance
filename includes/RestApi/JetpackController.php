<?php
namespace NewfoldLabs\WP\Module\Performance\RestApi;

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
	 * Plugin slug.
	 *
	 * @var string
	 */
	protected $plugin_slug = 'jetpack-boost';

	/**
	 * Register API routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/settings',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'set_options' ),
					'permission_callback' => function () {
						return current_user_can( 'manage_options' );
					},
				),
			)
		);
	}

	/**
	 * Set Jetpack options.
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response
	 */
	public function set_options( $request ) {
		try {
			$params = $request->get_params();

			if ( ! isset( $params['field'] ) || ! is_array( $params['field'] ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'error'   => __( "The parameter 'field' is missing or invalid.", 'newfold-performance-module' ),
					),
					400
				);
			}

			$field = $params['field'];

			if ( ! isset( $field['id'], $field['value'] ) ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'error'   => __( "The fields 'id' and 'value' are required.", 'newfold-performance-module' ),
					),
					400
				);
			}

			if ( 'critical-css-premium' === $field['id'] ) {
				$field['id'] = 'critical-css';
			}

			$option_key   = 'jetpack_boost_status_' . $field['id'];
			$option_value = $field['value'];

			if ( in_array( $field['id'], array( 'minify-js-excludes', 'minify-css-excludes' ), true ) ) {
				$option_key   = 'jetpack_boost_ds_' . str_replace( '-', '_', $field['id'] );
				$option_value = explode( ',', $field['value'] );
			}

			$result = update_option( $option_key, $option_value );

			if ( false === $result ) {
				return new \WP_REST_Response(
					array(
						'success' => false,
						'error'   => __( 'An error occurred while updating the option.', 'newfold-performance-module' ),
					),
					500
				);
			}

			// Success response.
			return new \WP_REST_Response(
				array(
					'success'        => true,
					'updated_option' => $option_key,
					'updated_value'  => $option_value,
				),
				200
			);
		} catch ( \Exception $e ) {
			// Exceptions handling.
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'An error occurred while updating the option.', 'newfold-performance-module' ) . $e->getMessage(),
				),
				500
			);
		}
	}
}
