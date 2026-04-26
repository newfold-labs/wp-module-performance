<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

use NewfoldLabs\WP\Module\Data\HiiveConnection;

/**
 * Thin Hiive HTTP helper (duplicated from wp-module-hosting for module independence).
 */
class HiiveHelper {

	/**
	 * Base URL for Hiive API requests.
	 *
	 * @var string
	 */
	private $api_base_url;

	/**
	 * API path appended to the base URL.
	 *
	 * @var string
	 */
	private $endpoint;

	/**
	 * Request body for POST-like methods or query args for GET/DELETE.
	 *
	 * @var array
	 */
	private $body;

	/**
	 * HTTP method (GET, POST, PUT, PATCH, DELETE).
	 *
	 * @var string
	 */
	private $method;

	/**
	 * Constructor.
	 *
	 * @param string $endpoint API endpoint (relative to NFD_HIIVE_URL).
	 * @param array  $body     Request body / query args for GET/DELETE.
	 * @param string $method   HTTP method.
	 */
	public function __construct( $endpoint, $body = array(), $method = 'POST' ) {
		if ( ! defined( 'NFD_HIIVE_URL' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Platform constant.
			define( 'NFD_HIIVE_URL', 'https://hiive.cloud/api' );
		}

		$this->api_base_url = (string) apply_filters( 'newfold_performance_hiive_api_base_url', constant( 'NFD_HIIVE_URL' ) );
		$this->endpoint     = (string) $endpoint;
		$this->body         = is_array( $body ) ? $body : array();
		$this->method       = strtoupper( (string) $method );
	}

	/**
	 * Sends the request to Hiive.
	 *
	 * @return string|\WP_Error Response body string on success.
	 */
	public function send_request() {
		if ( ! HiiveConnection::is_connected() ) {
			return new \WP_Error(
				'nfd_hiive_error',
				__( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' )
			);
		}

		$url = untrailingslashit( $this->api_base_url ) . $this->endpoint;

		$args = array(
			'method'  => $this->method,
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . HiiveConnection::get_auth_token(),
			),
			'timeout' => SiteApisConfig::hiive_request_timeout_seconds(),
		);

		if ( in_array( $this->method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
			$args['body'] = wp_json_encode( $this->body );
		}

		if ( in_array( $this->method, array( 'GET', 'DELETE' ), true ) && ! empty( $this->body ) ) {
			$url = add_query_arg( $this->body, $url );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'nfd_hiive_error',
				__( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ),
				array(
					'status' => $code,
					'body'   => wp_remote_retrieve_body( $response ),
				)
			);
		}

		return wp_remote_retrieve_body( $response );
	}
}
