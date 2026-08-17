<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

/**
 * Minimal Hosting UAPI client for site-scoped endpoints.
 */
final class HostingUapiClient {

	/**
	 * PUT /v1/sites/{site_id}/performance/redis
	 *
	 * @param string $huapi_jwt HUAPI JWT (from Hiive customer payload).
	 * @param string $site_id   HAL site id (digits).
	 * @param bool   $enabled   Desired redis enablement.
	 * @return true|\WP_Error
	 */
	public static function put_site_performance_redis( $huapi_jwt, $site_id, $enabled ) {
		$huapi_jwt = (string) $huapi_jwt;
		$site_id   = (string) $site_id;

		if ( '' === $huapi_jwt || '' === $site_id ) {
			return new \WP_Error( 'nfd_hosting_uapi_error', __( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ) );
		}

		$base = SiteApisConfig::hosting_uapi_base_url();
		$url  = $base . 'v1/sites/' . rawurlencode( $site_id ) . '/performance/redis';

		$body = array( 'state' => (bool) $enabled );

		/**
		 * Allow adjusting request body for environments that require 0/1 instead of JSON booleans.
		 *
		 * @param array  $body
		 * @param string $site_id
		 */
		$body = apply_filters( 'newfold_performance_hosting_uapi_redis_toggle_body', $body, $site_id );

		$args = array(
			'method'  => 'PUT',
			'timeout' => SiteApisConfig::hosting_uapi_request_timeout_seconds(),
			'headers' => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $huapi_jwt,
			),
			'body'    => wp_json_encode( $body ),
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( $code < 200 || $code >= 300 ) {
			$data           = json_decode( $raw, true );
			$customer_error = is_array( $data ) ? self::extract_customer_error( $data ) : null;

			$err = new \WP_Error(
				'nfd_hosting_uapi_error',
				__( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ),
				array(
					'status'         => $code,
					'body'           => $raw,
					'customer_error' => $customer_error,
					'decoded'        => is_array( $data ) ? $data : null,
				)
			);

			/**
			 * Filter WP_Error for Hosting UAPI failures (map/customize).
			 *
			 * @param \WP_Error $err
			 * @param int       $code
			 * @param string    $raw
			 */
			return apply_filters( 'newfold_performance_hosting_uapi_redis_toggle_error', $err, $code, $raw );
		}

		return true;
	}

	/**
	 * GET /v1/sites/{site_id}/performance/redis
	 *
	 * Reads the server-side Redis status for the site. The authoritative "can this box run object
	 * cache" signal is `redis_service_active` (HAL `daemon_active`): the phpredis PHP extension being
	 * loaded is NOT sufficient, because the Redis daemon can be down/absent while the extension is
	 * present (e.g. legacy CentOS 7 / hostmonster boxes where Redis was never deployed).
	 *
	 * @param string $huapi_jwt HUAPI JWT (from Hiive customer payload).
	 * @param string $site_id   HAL site id (digits).
	 * @return array{obj_cache_installed?:bool, obj_cache_enabled?:bool, redis_service_active?:bool}|\WP_Error
	 */
	public static function get_site_performance_redis( $huapi_jwt, $site_id ) {
		$huapi_jwt = (string) $huapi_jwt;
		$site_id   = (string) $site_id;

		if ( '' === $huapi_jwt || '' === $site_id ) {
			return new \WP_Error( 'nfd_hosting_uapi_error', __( 'Could not read object cache status right now.', 'wp-module-performance' ) );
		}

		$base = SiteApisConfig::hosting_uapi_base_url();
		$url  = $base . 'v1/sites/' . rawurlencode( $site_id ) . '/performance/redis';

		$args = array(
			'method'  => 'GET',
			'timeout' => SiteApisConfig::hosting_uapi_request_timeout_seconds(),
			'headers' => array(
				'Accept'        => 'application/json',
				'Authorization' => 'Bearer ' . $huapi_jwt,
			),
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code < 200 || $code >= 300 ) {
			$customer_error = is_array( $data ) ? self::extract_customer_error( $data ) : null;

			return new \WP_Error(
				'nfd_hosting_uapi_error',
				__( 'Could not read object cache status right now.', 'wp-module-performance' ),
				array(
					'status'         => $code,
					'body'           => $raw,
					'customer_error' => $customer_error,
					'decoded'        => is_array( $data ) ? $data : null,
				)
			);
		}

		// A 2xx with an unparseable body is not a usable status. Treat it as an error (indeterminate)
		// rather than silently returning an empty array, so the availability probe re-checks soon
		// instead of caching a false "unavailable" from a malformed upstream response.
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				'nfd_hosting_uapi_error',
				__( 'Could not read object cache status right now.', 'wp-module-performance' ),
				array(
					'status' => $code,
					'body'   => $raw,
				)
			);
		}

		return $data;
	}

	/**
	 * Extract a stable customer-facing error string from a decoded JSON body when present.
	 *
	 * HUAPI sends its errors as `{"error": "redisServiceInactive"}`, so the string form has to be
	 * read or callers that branch on a specific error never match. The other shapes are kept for
	 * anything sitting in front of HUAPI that wraps or renames the field.
	 *
	 * @param array $data Decoded JSON.
	 */
	private static function extract_customer_error( array $data ): ?string {
		if ( isset( $data['customer_error'] ) && is_string( $data['customer_error'] ) && '' !== $data['customer_error'] ) {
			return $data['customer_error'];
		}

		if ( isset( $data['error'] ) ) {
			// HUAPI's own shape, where the error field holds the customer error string itself.
			if ( is_string( $data['error'] ) && '' !== $data['error'] ) {
				return $data['error'];
			}

			// A gateway in front of HUAPI that nests the customer error under an error object.
			if (
				is_array( $data['error'] )
				&& isset( $data['error']['customer_error'] )
				&& is_string( $data['error']['customer_error'] )
				&& '' !== $data['error']['customer_error']
			) {
				return $data['error']['customer_error'];
			}
		}

		return null;
	}

	/**
	 * Truncate raw response text for error messages.
	 *
	 * @param string $raw Response body.
	 * @return string Trimmed substring, max 240 characters.
	 */
	private static function snippet( string $raw ): string {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return '';
		}

		return function_exists( 'mb_substr' )
			? (string) mb_substr( $raw, 0, 240 )
			: (string) substr( $raw, 0, 240 );
	}
}
