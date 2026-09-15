<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

use NewfoldLabs\WP\Module\Data\HiiveConnection;

/**
 * Hiive HAL customer-data refresh and investigation flag endpoints.
 */
final class HiiveHalDataClient {

	/**
	 * Ask Hiive to refresh HAL customer data (tenant_id, site_id) for this site.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function refresh_customer_data() {
		if ( ! HiiveConnection::is_connected() ) {
			return new \WP_Error( 'hiive_not_connected', 'Hiive is not connected.' );
		}

		$hiive = new HiiveHelper(
			'/sites/v1/hal/refresh-customer-data',
			array( 'force' => true ),
			'POST'
		);

		$response = $hiive->send_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data = json_decode( (string) $response, true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'hiive_hal_refresh_error', 'Invalid Hiive HAL refresh response.' );
		}

		return $data;
	}

	/**
	 * Flag this site in Hiive for manual investigation when automatic recovery fails.
	 *
	 * @param string $reason Human-readable reason (max 180 chars).
	 * @param string $source Originating component.
	 * @return true|\WP_Error
	 */
	public static function flag_investigation( $reason, $source = 'wp-module-performance' ) {
		if ( ! HiiveConnection::is_connected() ) {
			return new \WP_Error( 'hiive_not_connected', 'Hiive is not connected.' );
		}

		$hiive = new HiiveHelper(
			'/sites/v1/hal/flag-investigation',
			array(
				'reason' => (string) $reason,
				'source' => (string) $source,
			),
			'POST'
		);

		$response = $hiive->send_request();
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}
}
