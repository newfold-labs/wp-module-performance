<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

/**
 * Centralized defaults for outbound site APIs (Hiive / Hosting UAPI).
 */
final class SiteApisConfig {

	/**
	 * Hosting UAPI base URL (trailing slash).
	 *
	 * Aligns with laravel-hiive `config('services.sites.api_base')` default.
	 */
	public static function hosting_uapi_base_url(): string {
		if ( ! defined( 'NFD_SITES_API' ) ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Platform constant.
			define( 'NFD_SITES_API', 'https://hosting.uapi.newfold.com/' );
		}

		$base = (string) constant( 'NFD_SITES_API' );
		$base = apply_filters( 'newfold_performance_hosting_uapi_base_url', $base );

		return trailingslashit( $base );
	}

	/**
	 * Request timeout in seconds for Hiive HTTP calls.
	 *
	 * @return int
	 */
	public static function hiive_request_timeout_seconds(): int {
		$timeout = 30;
		return (int) apply_filters( 'newfold_performance_hiive_request_timeout_seconds', $timeout );
	}

	/**
	 * Request timeout in seconds for Hosting UAPI HTTP calls.
	 *
	 * @return int
	 */
	public static function hosting_uapi_request_timeout_seconds(): int {
		$timeout = 30;
		return (int) apply_filters( 'newfold_performance_hosting_uapi_request_timeout_seconds', $timeout );
	}
}
