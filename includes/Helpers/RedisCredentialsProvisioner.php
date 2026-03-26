<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

use NewfoldLabs\WP\Module\Data\HiiveConnection;
use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes;

/**
 * Provisions Redis credentials by calling Hosting UAPI, using Hiive customer context.
 */
final class RedisCredentialsProvisioner {

	/**
	 * Attempt to enable Redis at the host layer (writes wp-config constants via GT).
	 *
	 * @return true|\WP_Error
	 */
	public static function provision_enable_redis_via_hosting_api() {
		if ( ! HiiveConnection::is_connected() ) {
			return new \WP_Error(
				ObjectCacheErrorCodes::HIIVE_NOT_CONNECTED,
				__( 'This site is not connected to Hiive, so Redis credentials cannot be provisioned automatically.', 'wp-module-performance' )
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
				__( 'Unexpected response while reading customer data from Hiive.', 'wp-module-performance' )
			);
		}

		$token   = isset( $data['huapi_token'] ) ? (string) $data['huapi_token'] : '';
		$site_id = isset( $data['site_id'] ) ? (string) $data['site_id'] : '';

		if ( '' === $token ) {
			return new \WP_Error(
				ObjectCacheErrorCodes::HUAPI_TOKEN_UNAVAILABLE,
				__( 'HUAPI token is not available yet. Try again in a few minutes or contact support.', 'wp-module-performance' )
			);
		}

		if ( '' === $site_id || ! ctype_digit( $site_id ) ) {
			return new \WP_Error(
				ObjectCacheErrorCodes::HAL_SITE_ID_MISSING,
				__( 'Hosting site id is not available yet. Try again in a few minutes or contact support.', 'wp-module-performance' )
			);
		}

		$result = HostingUapiClient::put_site_performance_redis( $token, $site_id, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return true;
	}
}
