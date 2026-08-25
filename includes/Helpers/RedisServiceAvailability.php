<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

/**
 * Authoritative, cached check for whether the Redis service (daemon) is available on this
 * site's server, per the hosting API (HUAPI GET /performance/redis -> HAL `daemon_active`).
 *
 * This exists because the phpredis PHP extension being loaded is NOT a reliable signal: on some
 * server generations (e.g. legacy CentOS 7 / hostmonster boxes) the extension is present but the
 * Redis daemon was never deployed, so offering the object-cache UI there produces "Could not
 * enable object cache" errors. Only the server-side daemon status can tell those boxes apart.
 *
 * The result is cached in a transient so the settings page / runtime SDK does not make a network
 * call on every render.
 */
final class RedisServiceAvailability {

	/**
	 * Transient key for the cached availability result.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'nfd_performance_redis_service_available';

	/**
	 * Cache TTL (seconds) when the daemon is confirmed available. Longer, because a box that has
	 * Redis today is very unlikely to lose it.
	 *
	 * @var int
	 */
	const TTL_AVAILABLE = 43200; // 12 hours.

	/**
	 * Cache TTL (seconds) when the daemon is confirmed NOT available. Shorter, so a box that gets
	 * Redis deployed (e.g. a fleet migration to AlmaLinux) starts offering the toggle within ~1h.
	 *
	 * @var int
	 */
	const TTL_UNAVAILABLE = 3600; // 1 hour.

	/**
	 * Cache TTL (seconds) when the probe was indeterminate (Hiive/HUAPI unreachable). Short, so we
	 * re-probe soon rather than hiding the UI for a long time after a transient blip.
	 *
	 * @var int
	 */
	const TTL_INDETERMINATE = 300; // 5 minutes.

	/**
	 * HUAPI customer-error string returned when the Redis daemon is not running on the server.
	 *
	 * @var string
	 */
	const CUSTOMER_ERROR_SERVICE_INACTIVE = 'redisServiceInactive';

	/**
	 * HUAPI customer-error string returned when no PHP version on the box supports Redis.
	 *
	 * @var string
	 */
	const CUSTOMER_ERROR_PHP_UNSUPPORTED = 'phpVersionUnsupported';

	/**
	 * Whether the Redis service (daemon) is available on this site's server.
	 *
	 * Cached; fails safe to false when the answer cannot be determined, so the UI is not offered on
	 * a box that would error on enable.
	 *
	 * @return bool
	 */
	public static function is_daemon_available(): bool {
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( '1' === $cached || '0' === $cached ) {
			return '1' === $cached;
		}

		$result = self::probe();

		if ( null === $result ) {
			// Indeterminate: fail safe to unavailable, but re-probe soon.
			set_transient( self::TRANSIENT_KEY, '0', self::TTL_INDETERMINATE );
			return false;
		}

		set_transient(
			self::TRANSIENT_KEY,
			$result ? '1' : '0',
			$result ? self::TTL_AVAILABLE : self::TTL_UNAVAILABLE
		);

		return $result;
	}

	/**
	 * Clear the cached availability result so the next read re-probes.
	 *
	 * @return void
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Ask the hosting API whether the Redis daemon is active on this site's server.
	 *
	 * @return bool|null True/false when the server answered definitively; null when the answer could
	 *                   not be determined (Hiive not connected, token/site missing, or a transient
	 *                   HTTP error) and the caller should not cache the result for long.
	 */
	private static function probe() {
		$context = RedisCredentialsProvisioner::get_hosting_context();
		if ( is_wp_error( $context ) ) {
			return null;
		}

		$status = HostingUapiClient::get_site_performance_redis( $context['token'], $context['site_id'] );

		if ( is_wp_error( $status ) ) {
			$data           = $status->get_error_data();
			$customer_error = ( is_array( $data ) && isset( $data['customer_error'] ) ) ? (string) $data['customer_error'] : '';

			// These customer errors mean the box definitively cannot run object cache.
			if (
				self::CUSTOMER_ERROR_SERVICE_INACTIVE === $customer_error
				|| self::CUSTOMER_ERROR_PHP_UNSUPPORTED === $customer_error
			) {
				return false;
			}

			// Any other error (network, auth, unknown) is indeterminate.
			return null;
		}

		return ! empty( $status['redis_service_active'] );
	}
}
