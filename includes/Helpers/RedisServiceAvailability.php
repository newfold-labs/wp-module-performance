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
 * The result is cached so the settings page / runtime SDK does not make a network call on every
 * render.
 */
final class RedisServiceAvailability {

	/**
	 * Transient key this state used to live in. Kept only so flush() can clear it on sites that
	 * cached an answer before the move to an option.
	 *
	 * @var string
	 */
	const TRANSIENT_KEY = 'nfd_performance_redis_service_available';

	/**
	 * Option holding the probe state: the last answer and the earliest time we may probe again.
	 *
	 * An option rather than a transient, for two reasons.
	 *
	 * A transient on a site running another plugin's object-cache drop-in lives only in that cache
	 * and never falls back to the database. When that cache is broken or non-persistent every read
	 * misses, so the probe runs on every admin page load with no floor at all. Options do fall back
	 * to the database, so the floor holds no matter what the object cache is doing.
	 *
	 * And it is network-wide: the Redis daemon belongs to the server, not to a blog, so a multisite
	 * network should answer this once rather than once per blog. On single sites get_site_option()
	 * is get_option().
	 *
	 * Not \NewfoldLabs\WP\Module\Data\Helpers\Transient, which solves the drop-in half of this: it
	 * is a TTL cache, and the answer here has to outlive the retry schedule so the toggle keeps its
	 * last known state while we are backing off. It is also per-blog.
	 *
	 * @var string
	 */
	const STATE_OPTION = 'nfd_performance_redis_service_state';

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
	 * Delay (seconds) before the first retry after an indeterminate probe (Hiive/HUAPI unreachable).
	 * Short, so we re-probe soon rather than hiding the UI for a long time after a transient blip.
	 * Each further consecutive failure doubles it, up to self::TTL_INDETERMINATE_MAX.
	 *
	 * @var int
	 */
	const TTL_INDETERMINATE = 300; // 5 minutes.

	/**
	 * Ceiling for the indeterminate retry delay.
	 *
	 * @var int
	 */
	const TTL_INDETERMINATE_MAX = 43200; // 12 hours.

	/**
	 * Cap on the stored failure count, so the number cannot grow without bound once the delay has
	 * reached its ceiling anyway.
	 *
	 * @var int
	 */
	const MAX_FAILURES = 16;

	/**
	 * Option holding the probe lock: the time the in-flight probe's claim runs out.
	 *
	 * @var string
	 */
	const LOCK_OPTION = 'nfd_performance_redis_service_probe_lock';

	/**
	 * How long one request may hold the probe lock before another may try.
	 *
	 * @var int
	 */
	const LOCK_TTL = 60; // 1 minute.

	/**
	 * Constant that stops the probe from making any network call. Define it in wp-config.php:
	 * `define( 'NFD_DISABLE_REDIS_AVAILABILITY_PROBE', true );`
	 *
	 * A constant rather than only a filter, so a host can switch this off across a fleet without
	 * shipping a plugin release.
	 *
	 * @var string
	 */
	const DISABLE_PROBE_CONSTANT = 'NFD_DISABLE_REDIS_AVAILABILITY_PROBE';

	/**
	 * Answer already worked out during this request, or null before the first lookup.
	 *
	 * @var bool|null
	 */
	private static $answer_this_request = null;

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
	 * Cached. When a probe cannot determine the answer we keep serving the last one the server gave
	 * us, and fail safe to false only when it has never answered, so the UI is not offered on a box
	 * that would error on enable.
	 *
	 * @return bool
	 */
	public static function is_daemon_available(): bool {
		if ( null === self::$answer_this_request ) {
			self::$answer_this_request = self::resolve();
		}

		return self::$answer_this_request;
	}

	/**
	 * Work out the answer for this request.
	 *
	 * @return bool
	 */
	private static function resolve(): bool {
		$state = self::read_state();

		if ( self::probe_disabled() || time() < $state['next'] ) {
			return '1' === $state['answer'];
		}

		// One request at a time goes to the network. The rest serve what we already have rather than
		// queueing up behind it: several admin requests landing together on an answer that has just
		// fallen due would otherwise all probe, and against a slow upstream all block.
		if ( ! self::claim_probe() ) {
			return '1' === $state['answer'];
		}

		$available = self::probe_and_store( $state );

		self::release_probe();

		return $available;
	}

	/**
	 * Probe and store whatever the server tells us.
	 *
	 * @param array{answer:string, next:int, failures:int} $state State as it stood before the probe.
	 * @return bool
	 */
	private static function probe_and_store( array $state ): bool {
		$result = self::probe();

		if ( null === $result ) {
			// Indeterminate: hold on to the last answer the server gave us and retry later. Writing
			// '0' here would hide the object cache toggle for the whole backoff window every time
			// Hiive had a bad few minutes, which is what made a long backoff unaffordable before.
			$failures = min( $state['failures'] + 1, self::MAX_FAILURES );
			self::write_state( $state['answer'], self::indeterminate_delay( $failures ), $failures );
			return '1' === $state['answer'];
		}

		self::write_state(
			$result ? '1' : '0',
			$result ? self::TTL_AVAILABLE : self::TTL_UNAVAILABLE
		);

		return $result;
	}

	/**
	 * Whether the probe may make a network call at all.
	 *
	 * Switching it off leaves the last answer the server gave us in place: a site that has probed
	 * before keeps the UI it had, and one that never has stays fail-safe hidden. A brand that wants
	 * the toggle shown regardless can still say so through
	 * `newfold_performance_object_cache_ui_available`.
	 *
	 * @return bool
	 */
	private static function probe_disabled(): bool {
		$disabled = defined( self::DISABLE_PROBE_CONSTANT ) && constant( self::DISABLE_PROBE_CONSTANT );

		/**
		 * Filters whether the server-side Redis availability probe is switched off.
		 *
		 * @param bool $disabled Default: whether NFD_DISABLE_REDIS_AVAILABILITY_PROBE is set.
		 */
		return (bool) apply_filters( 'newfold_performance_disable_redis_availability_probe', $disabled );
	}

	/**
	 * Try to become the request that probes.
	 *
	 * Best effort. WordPress has no atomic option primitive, so two requests arriving in the same
	 * instant can both win. It still collapses the ordinary stampede, which is a handful of admin
	 * requests landing on a due answer at once.
	 *
	 * @return bool
	 */
	private static function claim_probe(): bool {
		$held_until = (int) get_site_option( self::LOCK_OPTION, 0 );

		if ( $held_until > time() ) {
			return false;
		}

		update_site_option( self::LOCK_OPTION, time() + self::LOCK_TTL );

		return true;
	}

	/**
	 * Give up the probe lock.
	 *
	 * @return void
	 */
	private static function release_probe() {
		update_site_option( self::LOCK_OPTION, 0 );
	}

	/**
	 * Clear the cached availability result so the next read re-probes.
	 *
	 * @return void
	 */
	public static function flush() {
		self::reset_request_cache();
		delete_site_option( self::STATE_OPTION );
		delete_site_option( self::LOCK_OPTION );
		// Sites that cached an answer before this moved to an option still have the transient.
		delete_transient( self::TRANSIENT_KEY );
	}

	/**
	 * Forget the answer worked out earlier in this request.
	 *
	 * Only matters where one PHP process serves more than one logical request, such as WP-CLI or a
	 * test run.
	 *
	 * @return void
	 */
	public static function reset_request_cache() {
		self::$answer_this_request = null;
	}

	/**
	 * Read the stored probe state, normalised.
	 *
	 * @return array{answer:string, next:int, failures:int} `answer` is '1', '0', or '' when nothing
	 *                                                       is stored yet.
	 */
	private static function read_state(): array {
		$stored = get_site_option( self::STATE_OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$answer = isset( $stored['answer'] ) ? (string) $stored['answer'] : '';

		return array(
			'answer'   => in_array( $answer, array( '1', '0' ), true ) ? $answer : '',
			'next'     => isset( $stored['next'] ) ? (int) $stored['next'] : 0,
			'failures' => isset( $stored['failures'] ) ? max( 0, (int) $stored['failures'] ) : 0,
		);
	}

	/**
	 * Store an answer and hold off probing again for the given number of seconds.
	 *
	 * @param string $answer   '1', '0', or '' when the server has never answered.
	 * @param int    $ttl      Seconds until the next probe is allowed.
	 * @param int    $failures Consecutive indeterminate probes. Zero once the server answers.
	 * @return void
	 */
	private static function write_state( string $answer, int $ttl, int $failures = 0 ) {
		update_site_option(
			self::STATE_OPTION,
			array(
				'answer'   => $answer,
				'next'     => time() + $ttl,
				'failures' => $failures,
			)
		);
	}

	/**
	 * How long to wait before retrying after consecutive indeterminate probes.
	 *
	 * Doubles each time, up to the ceiling. The delay used to be a flat five minutes, which had it
	 * backwards: a probe is indeterminate precisely when Hiive or the hosting API is unhealthy, so
	 * every site answered a struggling upstream by asking it far more often than when it was
	 * healthy, and kept doing so for as long as the trouble lasted.
	 *
	 * @param int $failures Consecutive indeterminate probes, including this one.
	 * @return int Seconds.
	 */
	private static function indeterminate_delay( int $failures ): int {
		$delay = self::TTL_INDETERMINATE * pow( 2, max( 0, $failures - 1 ) );

		return (int) min( $delay, self::TTL_INDETERMINATE_MAX );
	}

	/**
	 * Ask the hosting API whether the Redis daemon is active on this site's server.
	 *
	 * Both calls use the short probe timeout: this runs while an admin page renders, and a probe that
	 * times out is indeterminate and retried later rather than fatal.
	 *
	 * @return bool|null True/false when the server answered definitively; null when the answer could
	 *                   not be determined (Hiive not connected, token/site missing, or a transient
	 *                   HTTP error) and the caller should not cache the result for long.
	 */
	private static function probe() {
		$timeout = SiteApisConfig::redis_probe_timeout_seconds();

		$context = RedisCredentialsProvisioner::get_hosting_context( $timeout );
		if ( is_wp_error( $context ) ) {
			return null;
		}

		$status = HostingUapiClient::get_site_performance_redis( $context['token'], $context['site_id'], $timeout );

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
