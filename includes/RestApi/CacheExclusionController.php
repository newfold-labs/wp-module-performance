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
					'args'                => array(
						'excludedUrls'         => array(
							'type'              => 'string',
							'description'       => 'Comma-separated string of URLs to exclude from caching.',
							'required'          => true, // Now marked as required
							'sanitize_callback' => function ( $value ) {
								// Sanitize each URL in the comma-separated string
								return implode(
									',',
									array_map( 'sanitize_text_field', explode( ',', $value ) )
								);
							},
						),

						'doNotCacheErrorPages' => array(
							'type'              => 'boolean',
							'description'       => 'Whether to prevent caching of error pages (400 and 500).',
							'required'          => true,
							'sanitize_callback' => 'rest_sanitize_boolean',
							'validate_callback' => function ( $param ) {
								// Ensure the value is a valid boolean or equivalent
								return is_bool( $param ) || in_array( $param, array( 'true', 'false', true, false ), true );
							},
						),

					),
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
		// Retrieve the cache exclusion option once
		$cache_exclusion = get_option( CacheExclusion::OPTION_CACHE_EXCLUSION, get_default_cache_exclusions() );

		// Extract the specific keys
		$excluded_urls            = $cache_exclusion['excludedUrls'] ?? '';
		$do_not_cache_error_pages = $cache_exclusion['doNotCacheErrorPages'] ?? false;

		return new \WP_REST_Response(
			array(
				'excludedUrls'         => $excluded_urls,
				'doNotCacheErrorPages' => $do_not_cache_error_pages,
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

		// Retrieve current settings and merge with defaults
		$current_cache_exclusion = get_option( CacheExclusion::OPTION_CACHE_EXCLUSION, array() );

		// Extract and sanitize the new values from the request
		$excluded_urls            = $request->get_param( 'excludedUrls' );
		$do_not_cache_error_pages = $request->get_param( 'doNotCacheErrorPages' );

		// Merge the updated values into the current settings
		$updated_cache_exclusion = array_merge(
			$current_cache_exclusion,
			array(
				'excludedUrls'         => $excluded_urls,
				'doNotCacheErrorPages' => $do_not_cache_error_pages,
			)
		);

		if ( update_option( CacheExclusion::OPTION_CACHE_EXCLUSION, $updated_cache_exclusion ) ) {
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
