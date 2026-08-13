<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

use NewfoldLabs\WP\Module\Data\HiiveConnection;
use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes;

/**
 * Provisions Redis credentials by calling Hosting UAPI, using Hiive customer context.
 */
final class RedisCredentialsProvisioner {

	/**
	 * Fetch the hosting API context (HUAPI token + HAL site id) from the Hiive customer payload.
	 *
	 * Shared by the provisioning (PUT) path and the availability (GET) probe so both use the same
	 * Hiive-connection and token/site-id resolution, with identical error codes.
	 *
	 * @return array{token:string, site_id:string}|\WP_Error
	 */
	public static function get_hosting_context() {
		if ( ! HiiveConnection::is_connected() ) {
			return new \WP_Error(
				ObjectCacheErrorCodes::HIIVE_NOT_CONNECTED,
				__( 'Object cache cannot be enabled automatically right now. Please contact support.', 'wp-module-performance' )
			);
		}

		$hiive = new HiiveHelper( '/sites/v1/customer', array(), 'GET' );
		$resp  = $hiive->send_request();

		if ( is_wp_error( $resp ) ) {
			return $resp;
		}

		$data = json_decode( (string) $resp, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error(
				ObjectCacheErrorCodes::HUAPI_ERROR,
				__( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' )
			);
		}

		$token   = isset( $data['huapi_token'] ) ? (string) $data['huapi_token'] : '';
		$site_id = isset( $data['site_id'] ) ? (string) $data['site_id'] : '';

		if ( '' === $token ) {
			return new \WP_Error(
				ObjectCacheErrorCodes::HUAPI_TOKEN_UNAVAILABLE,
				__( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' )
			);
		}

		if ( '' === $site_id || ! ctype_digit( $site_id ) ) {
			return new \WP_Error(
				ObjectCacheErrorCodes::HAL_SITE_ID_MISSING,
				__( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' )
			);
		}

		return array(
			'token'   => $token,
			'site_id' => $site_id,
		);
	}

	/**
	 * Attempt to enable Redis at the host layer (writes wp-config constants via GT).
	 *
	 * @return true|\WP_Error
	 */
	public static function provision_enable_redis_via_hosting_api() {
		$context = self::get_hosting_context();
		if ( is_wp_error( $context ) ) {
			return $context;
		}

		$result = HostingUapiClient::put_site_performance_redis( $context['token'], $context['site_id'], true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
