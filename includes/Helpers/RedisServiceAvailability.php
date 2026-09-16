<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes;

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
	 * Transient key indicating a HAL refresh was recently queued on Hiive.
	 *
	 * @var string
	 */
	const HAL_REFRESH_QUEUED_TRANSIENT_KEY = 'nfd_hal_refresh_queued';

	/**
	 * Transient key indicating this site was recently flagged for HUAPI investigation.
	 *
	 * @var string
	 */
	const INVESTIGATION_FLAGGED_TRANSIENT_KEY = 'nfd_hal_investigation_flagged';

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
	 * Cache TTL (seconds) when HUAPI auth fails (403/forbidden). Longer backoff so we do not
	 * hammer HUAPI while Hiive refreshes stale tenant/site metadata on the queue.
	 *
	 * @var int
	 */
	const TTL_HUAPI_AUTH_BACKOFF = 3600; // 1 hour.

	/**
	 * Cache TTL (seconds) for the HAL refresh queued guard transient.
	 *
	 * @var int
	 */
	const TTL_HAL_REFRESH_QUEUED = 3600; // 1 hour.

	/**
	 * Cache TTL (seconds) after flagging a site for investigation.
	 *
	 * @var int
	 */
	const TTL_INVESTIGATION_FLAGGED = 86400; // 24 hours.

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

		$probe = self::probe();

		if ( null === $probe['available'] ) {
			set_transient( self::TRANSIENT_KEY, '0', $probe['cache_ttl'] );

			return false;
		}

		set_transient(
			self::TRANSIENT_KEY,
			$probe['available'] ? '1' : '0',
			$probe['available'] ? self::TTL_AVAILABLE : self::TTL_UNAVAILABLE
		);

		return $probe['available'];
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
	 * @return array{available: ?bool, cache_ttl: int} `available` null when indeterminate;
	 *                                                 `cache_ttl` controls how long to back off.
	 */
	private static function probe(): array {
		if ( get_transient( self::INVESTIGATION_FLAGGED_TRANSIENT_KEY ) ) {
			return array(
				'available'  => null,
				'cache_ttl'  => self::TTL_INVESTIGATION_FLAGGED,
			);
		}

		$context = RedisCredentialsProvisioner::get_hosting_context();
		if ( is_wp_error( $context ) ) {
			if ( self::maybe_queue_hal_refresh_after_context_error( $context ) ) {
				return array(
					'available' => null,
					'cache_ttl' => self::TTL_INDETERMINATE,
				);
			}

			return array(
				'available' => null,
				'cache_ttl' => self::TTL_INDETERMINATE,
			);
		}

		$status = self::probe_huapi_redis( $context );

		if ( is_wp_error( $status ) && self::is_huapi_auth_failure( $status ) ) {
			return self::handle_huapi_auth_failure();
		}

		if ( is_wp_error( $status ) ) {
			$data           = $status->get_error_data();
			$customer_error = ( is_array( $data ) && isset( $data['customer_error'] ) ) ? (string) $data['customer_error'] : '';

			if (
				self::CUSTOMER_ERROR_SERVICE_INACTIVE === $customer_error
				|| self::CUSTOMER_ERROR_PHP_UNSUPPORTED === $customer_error
			) {
				return array(
					'available' => false,
					'cache_ttl' => self::TTL_UNAVAILABLE,
				);
			}

			return array(
				'available' => null,
				'cache_ttl' => self::TTL_INDETERMINATE,
			);
		}

		return array(
			'available' => ! empty( $status['redis_service_active'] ),
			'cache_ttl' => self::TTL_AVAILABLE,
		);
	}

	/**
	 * Probe HUAPI for Redis daemon status using the resolved hosting context.
	 *
	 * @param array{token:string, site_id:string} $context Hosting API context.
	 * @return array<string, mixed>|\WP_Error
	 */
	private static function probe_huapi_redis( array $context ) {
		return HostingUapiClient::get_site_performance_redis( $context['token'], $context['site_id'] );
	}

	/**
	 * Back off HUAPI probes after auth failures; queue HAL refresh once, then flag investigation.
	 *
	 * @return array{available: ?bool, cache_ttl: int}
	 */
	private static function handle_huapi_auth_failure(): array {
		if ( get_transient( self::HAL_REFRESH_QUEUED_TRANSIENT_KEY ) ) {
			if ( ! get_transient( self::INVESTIGATION_FLAGGED_TRANSIENT_KEY ) ) {
				HiiveHalDataClient::flag_investigation(
					'HUAPI redis probe forbidden after queued HAL refresh',
					'wp-module-performance'
				);
				set_transient(
					self::INVESTIGATION_FLAGGED_TRANSIENT_KEY,
					'1',
					self::TTL_INVESTIGATION_FLAGGED
				);
			}

			return array(
				'available' => null,
				'cache_ttl' => self::TTL_INVESTIGATION_FLAGGED,
			);
		}

		if ( self::maybe_queue_hal_refresh() ) {
			return array(
				'available' => null,
				'cache_ttl' => self::TTL_HUAPI_AUTH_BACKOFF,
			);
		}

		return array(
			'available' => null,
			'cache_ttl' => self::TTL_HUAPI_AUTH_BACKOFF,
		);
	}

	/**
	 * Ask Hiive to queue a HAL refresh and return whether the request was accepted.
	 *
	 * @return bool
	 */
	private static function maybe_queue_hal_refresh(): bool {
		if ( get_transient( self::HAL_REFRESH_QUEUED_TRANSIENT_KEY ) ) {
			return false;
		}

		$queued = HiiveHalDataClient::queue_hal_refresh();
		if ( is_wp_error( $queued ) ) {
			return false;
		}

		if ( empty( $queued['queued'] ) ) {
			return false;
		}

		set_transient(
			self::HAL_REFRESH_QUEUED_TRANSIENT_KEY,
			'1',
			self::TTL_HAL_REFRESH_QUEUED
		);

		return true;
	}

	/**
	 * Queue HAL refresh when the hosting context is missing due to stale Hiive customer payload.
	 *
	 * @param \WP_Error $error Context resolution error.
	 * @return bool
	 */
	private static function maybe_queue_hal_refresh_after_context_error( $error ): bool {
		$code = $error->get_error_code();
		if ( ! in_array( $code, array( ObjectCacheErrorCodes::HUAPI_TOKEN_UNAVAILABLE, ObjectCacheErrorCodes::HAL_SITE_ID_MISSING ), true ) ) {
			return false;
		}

		return self::maybe_queue_hal_refresh();
	}

	/**
	 * Whether a HUAPI error indicates an auth/authorization failure (typically stale tenant/site id).
	 *
	 * @param \WP_Error $error HUAPI error.
	 * @return bool
	 */
	private static function is_huapi_auth_failure( $error ): bool {
		$data   = $error->get_error_data();
		$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 0;
		if ( 403 === $status ) {
			return true;
		}

		$customer_error = is_array( $data ) && isset( $data['customer_error'] ) ? (string) $data['customer_error'] : '';

		return 'forbidden' === $customer_error;
	}
}
