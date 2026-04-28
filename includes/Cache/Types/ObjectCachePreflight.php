<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

use NewfoldLabs\WP\Module\Data\HiiveConnection;

/**
 * Read-only diagnostics for Redis object cache readiness.
 *
 * Must not perform remote Hiive/HUAPI HTTP calls; local Hiive connection state is allowed.
 */
final class ObjectCachePreflight {

	/**
	 * Build a structured preflight snapshot for UI/REST.
	 *
	 * @param bool $include_live_ping When false, avoids connecting to Redis (settings endpoint safe).
	 * @return array<string, mixed>
	 */
	public static function snapshot( $include_live_ping = false ) {
		$extension_loaded = extension_loaded( 'redis' );
		$configured       = ObjectCache::is_configured_in_wp_config();
		$constants_now    = ObjectCache::constants_visible_this_request();

		// Only resolve when provisioning could run (same path as enable() before HTTP); avoids loading Hiive when phpredis is missing.
		$hiive_connected = false;
		if ( $extension_loaded && ! $configured ) {
			$hiive_connected = HiiveConnection::is_connected();
		} elseif ( $extension_loaded && $configured ) {
			$hiive_connected = true;
		}

		$ping_ok = null;
		$code    = null;
		$message = null;

		if ( ! $extension_loaded ) {
			$ping_ok = false;
			$code    = ObjectCacheErrorCodes::PHPREDIS_MISSING;
			$message = __( 'Object caching is not supported on this server.', 'wp-module-performance' );
		} elseif ( ! $configured ) {
			$ping_ok = false;
			// Same order as RedisCredentialsProvisioner::provision_enable_redis_via_hosting_api() before HTTP.
			if ( ! $hiive_connected ) {
				$code    = ObjectCacheErrorCodes::HIIVE_NOT_CONNECTED;
				$message = __(
					'Object cache cannot be enabled automatically right now.',
					'wp-module-performance'
				);
			}
			// When wp-config lacks creds but Hiive is connected, enabling will provision creds via the
			// hosting API — that's a normal "ready to enable" state, not an error worth surfacing in the UI.
		} elseif ( $include_live_ping ) {
			// Important: bootstrap WP_REDIS_* from wp-config when constants exist in file but aren't defined() yet.
			ObjectCache::bootstrap_redis_connection_constants_for_preflight();

			$ping    = PhpRedisPinger::ping();
			$ping_ok = (bool) ( $ping['ok'] ?? false );
			if ( ! $ping_ok ) {
				$code    = ObjectCacheErrorCodes::REDIS_UNREACHABLE;
				$message = __( 'Could not connect to the object cache.', 'wp-module-performance' );
			}
		}

		return array(
			'extensionLoaded'             => $extension_loaded,
			'configuredInWpConfig'        => $configured,
			'constantsVisibleThisRequest' => $constants_now,
			'hiiveConnected'              => $hiive_connected,
			'redisPingOk'                 => $ping_ok,
			'preflightCode'               => $code,
			'preflightMessage'            => $message,
		);
	}
}
