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
			return new \WP_Error( 'nfd_hosting_uapi_error', __( 'Missing HUAPI credentials.', 'wp-module-performance' ) );
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
				sprintf(
					/* translators: 1: HTTP status code, 2: response snippet */
					__( 'Hosting API returned HTTP %1$s: %2$s', 'wp-module-performance' ),
					(string) $code,
					self::snippet( $raw )
				),
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
	 * Extract a stable customer-facing error string from a decoded JSON body when present.
	 *
	 * @param array $data Decoded JSON.
	 */
	private static function extract_customer_error( array $data ): ?string {
		// Common shapes: { "customer_error": "..." } or nested under error/details.
		if ( isset( $data['customer_error'] ) && is_string( $data['customer_error'] ) && '' !== $data['customer_error'] ) {
			return $data['customer_error'];
		}

		if ( isset( $data['error'] ) && is_array( $data['error'] ) ) {
			$err = $data['error'];
			if ( isset( $err['customer_error'] ) && is_string( $err['customer_error'] ) ) {
				return $err['customer_error'];
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
