<?php
// phpcs:disable

namespace {
	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private $code;
			private $message;
			private $data;
			public function __construct( $code = '', $message = '', $data = '' ) {
				$this->code    = $code;
				$this->message = $message;
				$this->data    = $data;
			}
			public function get_error_code() {
				return $this->code; }
			public function get_error_message() {
				return $this->message; }
			public function get_error_data() {
				return $this->data; }
		}
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) {
			return $thing instanceof \WP_Error;
		}
	}
}

namespace NewfoldLabs\WP\Module\Performance\Helpers {

	use NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes;
	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use Patchwork;

	/**
	 * Tests for the cached server-side Redis availability probe.
	 */
	class RedisServiceAvailabilityTest extends TestCase {

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();
			WP_Mock::passthruFunction( '__' );
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		/**
		 * A cached "available" result is returned without any probe (no context/HTTP call).
		 */
		public function test_cached_available_short_circuits_probe() {
			WP_Mock::userFunction( 'get_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY )
				->andReturn( '1' );

			// Probe collaborators must not be touched, and nothing is re-cached.
			$context_called = false;
			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () use ( &$context_called ) {
					$context_called = true;
					return array(
						'token'   => 't',
						'site_id' => '1',
					);
				}
			);

			$this->assertTrue( RedisServiceAvailability::is_daemon_available() );
			$this->assertFalse( $context_called, 'Probe should not run when a cached result exists.' );
		}

		/**
		 * A cached "unavailable" result returns false without probing.
		 */
		public function test_cached_unavailable_short_circuits_probe() {
			WP_Mock::userFunction( 'get_transient' )
				->once()
				->andReturn( '0' );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
		}

		/**
		 * Uncached + daemon active -> true, cached for the long (available) TTL.
		 */
		public function test_probe_daemon_active_caches_available() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return array(
						'obj_cache_installed' => false,
						'obj_cache_enabled'   => false,
						'redis_service_active' => true,
					);
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '1', RedisServiceAvailability::TTL_AVAILABLE );

			$this->assertTrue( RedisServiceAvailability::is_daemon_available() );
		}

		/**
		 * Uncached + daemon inactive (2xx body says false) -> false, cached for the shorter unavailable TTL.
		 */
		public function test_probe_daemon_inactive_caches_unavailable() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return array( 'redis_service_active' => false );
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '0', RedisServiceAvailability::TTL_UNAVAILABLE );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
		}

		/**
		 * A `redisServiceInactive` HUAPI error is definitive: unavailable, cached for the unavailable TTL.
		 */
		public function test_probe_service_inactive_error_is_definitive_unavailable() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return new \WP_Error(
						'nfd_hosting_uapi_error',
						'nope',
						array( 'customer_error' => RedisServiceAvailability::CUSTOMER_ERROR_SERVICE_INACTIVE )
					);
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '0', RedisServiceAvailability::TTL_UNAVAILABLE );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
		}

		/**
		 * Same classification, but through the real client with only the HTTP layer faked, using the
		 * body HUAPI actually sends.
		 *
		 * The test above stubs the client, so it only checks our own idea of the error shape. This one
		 * covers the wire format, where a mismatch shows up as the wrong TTL instead of passing quietly.
		 */
		public function test_huapi_wire_error_shape_classifies_as_definitive_unavailable() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			if ( ! defined( 'NFD_SITES_API' ) ) {
				define( 'NFD_SITES_API', 'https://hosting.uapi.newfold.com/' );
			}
			WP_Mock::onFilter( 'newfold_performance_hosting_uapi_base_url' )
				->with( 'https://hosting.uapi.newfold.com/' )
				->reply( 'https://hosting.uapi.newfold.com/' );
			WP_Mock::onFilter( 'newfold_performance_hosting_uapi_request_timeout_seconds' )
				->with( 30 )
				->reply( 30 );
			WP_Mock::userFunction( 'trailingslashit' )->andReturnUsing(
				function ( $s ) {
					return rtrim( (string) $s, '/' ) . '/';
				}
			);

			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);

			WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( array( 'stub' => true ) );
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 512 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode( array( 'error' => RedisServiceAvailability::CUSTOMER_ERROR_SERVICE_INACTIVE ) )
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '0', RedisServiceAvailability::TTL_UNAVAILABLE );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
		}

		/**
		 * An unknown HUAPI error is indeterminate: fails safe to false, cached only for the short TTL so it re-probes soon.
		 */
		public function test_probe_unknown_error_is_indeterminate() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return new \WP_Error( 'nfd_hosting_uapi_error', 'boom', array( 'status' => 500 ) );
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '0', RedisServiceAvailability::TTL_INDETERMINATE );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
		}

		/**
		 * When the hosting context cannot be fetched (e.g. Hiive not connected), the probe is indeterminate:
		 * false, cached only for the short TTL.
		 */
		public function test_probe_no_context_is_indeterminate() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return new \WP_Error( 'hiive_not_connected', 'no hiive' );
				}
			);

			// HUAPI must not be called without a context.
			$uapi_called = false;
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) use ( &$uapi_called ) {
					$uapi_called = true;
					return array( 'redis_service_active' => true );
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '0', RedisServiceAvailability::TTL_INDETERMINATE );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assertFalse( $uapi_called, 'HUAPI must not be probed without a hosting context.' );
		}

		/**
		 * A HUAPI 403 should trigger a Hiive HAL refresh and retry the probe once.
		 */
		public function test_probe_403_refreshes_hal_and_retries_successfully() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			$context_calls = 0;
			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () use ( &$context_calls ) {
					++$context_calls;
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);

			$uapi_calls = 0;
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) use ( &$uapi_calls ) {
					++$uapi_calls;
					if ( 1 === $uapi_calls ) {
						return new \WP_Error(
							'nfd_hosting_uapi_error',
							'forbidden',
							array(
								'status'         => 403,
								'customer_error' => 'forbidden',
							)
						);
					}

					return array( 'redis_service_active' => true );
				}
			);

			$refresh_called = false;
			Patchwork\redefine(
				array( HiiveHalDataClient::class, 'refresh_customer_data' ),
				function () use ( &$refresh_called ) {
					$refresh_called = true;
					return array( 'refreshed' => true );
				}
			);

			Patchwork\redefine(
				array( HiiveHalDataClient::class, 'flag_investigation' ),
				function () {
					$this->fail( 'flag_investigation should not run when the retry succeeds.' );
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '1', RedisServiceAvailability::TTL_AVAILABLE );

			$this->assertTrue( RedisServiceAvailability::is_daemon_available() );
			$this->assertTrue( $refresh_called, 'HAL refresh should run after a HUAPI 403.' );
			$this->assertSame( 2, $uapi_calls, 'HUAPI should be probed again after HAL refresh.' );
		}

		/**
		 * A persistent HUAPI 403 after HAL refresh should flag the site for investigation.
		 */
		public function test_probe_403_after_refresh_still_forbidden_flags_investigation() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);

			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return new \WP_Error(
						'nfd_hosting_uapi_error',
						'forbidden',
						array(
							'status'         => 403,
							'customer_error' => 'forbidden',
						)
					);
				}
			);

			Patchwork\redefine(
				array( HiiveHalDataClient::class, 'refresh_customer_data' ),
				function () {
					return array( 'refreshed' => true );
				}
			);

			$flagged = false;
			Patchwork\redefine(
				array( HiiveHalDataClient::class, 'flag_investigation' ),
				function ( $reason, $source ) use ( &$flagged ) {
					$flagged = true;
					$this->assertSame( 'HUAPI redis probe forbidden after HAL refresh', $reason );
					$this->assertSame( 'wp-module-performance', $source );
					return true;
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '0', RedisServiceAvailability::TTL_INDETERMINATE );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assertTrue( $flagged, 'Site should be flagged when HUAPI auth still fails after HAL refresh.' );
		}

		/**
		 * Missing HAL site id in the Hiive customer payload should trigger a HAL refresh before giving up.
		 */
		public function test_probe_context_hal_site_id_missing_refreshes_and_retries() {
			WP_Mock::userFunction( 'get_transient' )->once()->andReturn( false );

			$context_calls = 0;
			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () use ( &$context_calls ) {
					++$context_calls;
					if ( 1 === $context_calls ) {
						return new \WP_Error(
							ObjectCacheErrorCodes::HAL_SITE_ID_MISSING,
							'missing site id'
						);
					}

					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);

			$uapi_called = false;
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) use ( &$uapi_called ) {
					$uapi_called = true;
					return array( 'redis_service_active' => true );
				}
			);

			Patchwork\redefine(
				array( HiiveHalDataClient::class, 'refresh_customer_data' ),
				function () {
					return array( 'refreshed' => true );
				}
			);

			WP_Mock::userFunction( 'set_transient' )
				->once()
				->with( RedisServiceAvailability::TRANSIENT_KEY, '1', RedisServiceAvailability::TTL_AVAILABLE );

			$this->assertTrue( RedisServiceAvailability::is_daemon_available() );
			$this->assertTrue( $uapi_called, 'HUAPI should be probed after HAL refresh fixes the context.' );
		}
	}
}
