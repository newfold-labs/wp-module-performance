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

	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use Patchwork;

	/**
	 * Tests for the cached server-side Redis availability probe.
	 */
	class RedisServiceAvailabilityTest extends TestCase {

		/**
		 * State written back by the code under test, or null when nothing was written.
		 *
		 * @var array|null
		 */
		private $saved;

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();
			WP_Mock::passthruFunction( '__' );
			$this->saved = null;

			// The probe reads its own short timeout before either call.
			WP_Mock::onFilter( 'newfold_performance_redis_probe_timeout_seconds' )->with( 5 )->reply( 5 );
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		/**
		 * Stand in for the stored probe state and capture anything written back.
		 *
		 * @param array|false $stored What the option returns.
		 */
		private function given_stored_state( $stored ) {
			WP_Mock::userFunction( 'get_site_option' )
				->once()
				->with( RedisServiceAvailability::STATE_OPTION, array() )
				->andReturn( $stored );

			WP_Mock::userFunction( 'update_site_option' )
				->andReturnUsing(
					function ( $key, $value ) {
						$this->saved = $value;
						return true;
					}
				);
		}

		/**
		 * A stored answer that has not expired yet.
		 *
		 * @param string $answer '1' or '0'.
		 * @return array
		 */
		private function fresh_state( string $answer ): array {
			return array(
				'answer' => $answer,
				'next'   => time() + 600,
			);
		}

		/**
		 * Assert the answer written back, and roughly when the next probe is due.
		 *
		 * @param string $answer   Expected answer.
		 * @param int    $ttl      Expected seconds until the next probe.
		 * @param int    $failures Expected consecutive indeterminate count.
		 */
		private function assert_saved( string $answer, int $ttl, int $failures = 0 ) {
			$this->assertIsArray( $this->saved, 'Expected the probe result to be stored.' );
			$this->assertSame( $answer, $this->saved['answer'] );
			$this->assertEqualsWithDelta( time() + $ttl, $this->saved['next'], 5 );
			$this->assertSame( $failures, $this->saved['failures'] );
		}

		/**
		 * Give the probe a usable hosting context.
		 */
		private function given_hosting_context() {
			Patchwork\redefine(
				array( RedisCredentialsProvisioner::class, 'get_hosting_context' ),
				function () {
					return array(
						'token'   => 'jwt',
						'site_id' => '12345',
					);
				}
			);
		}

		/**
		 * A stored "available" answer is returned without any probe (no context/HTTP call).
		 */
		public function test_stored_available_short_circuits_probe() {
			$this->given_stored_state( $this->fresh_state( '1' ) );

			// Probe collaborators must not be touched.
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
			$this->assertFalse( $context_called, 'Probe should not run while a stored answer is still current.' );
			$this->assertNull( $this->saved, 'A current answer should not be rewritten.' );
		}

		/**
		 * A stored "unavailable" answer returns false without probing.
		 */
		public function test_stored_unavailable_short_circuits_probe() {
			$this->given_stored_state( $this->fresh_state( '0' ) );

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assertNull( $this->saved );
		}

		/**
		 * Nothing stored + daemon active -> true, held for the long (available) TTL.
		 */
		public function test_probe_daemon_active_stores_available() {
			$this->given_stored_state( false );
			$this->given_hosting_context();
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return array(
						'obj_cache_installed'  => false,
						'obj_cache_enabled'    => false,
						'redis_service_active' => true,
					);
				}
			);

			$this->assertTrue( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved( '1', RedisServiceAvailability::TTL_AVAILABLE );
		}

		/**
		 * An answer whose hold has elapsed is re-probed rather than reused.
		 */
		public function test_elapsed_state_reprobes() {
			$this->given_stored_state(
				array(
					'answer' => '0',
					'next'   => time() - 1,
				)
			);
			$this->given_hosting_context();
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return array( 'redis_service_active' => true );
				}
			);

			$this->assertTrue( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved( '1', RedisServiceAvailability::TTL_AVAILABLE );
		}

		/**
		 * Nothing stored + daemon inactive (2xx body says false) -> false, held for the shorter TTL.
		 */
		public function test_probe_daemon_inactive_stores_unavailable() {
			$this->given_stored_state( false );
			$this->given_hosting_context();
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return array( 'redis_service_active' => false );
				}
			);

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved( '0', RedisServiceAvailability::TTL_UNAVAILABLE );
		}

		/**
		 * A `redisServiceInactive` HUAPI error is definitive: unavailable, held for the unavailable TTL.
		 */
		public function test_probe_service_inactive_error_is_definitive_unavailable() {
			$this->given_stored_state( false );
			$this->given_hosting_context();
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

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved( '0', RedisServiceAvailability::TTL_UNAVAILABLE );
		}

		/**
		 * Same classification, but through the real client with only the HTTP layer faked, using the
		 * body HUAPI actually sends.
		 *
		 * The test above stubs the client, so it only checks our own idea of the error shape. This one
		 * covers the wire format, where a mismatch shows up as the wrong TTL instead of passing quietly.
		 */
		public function test_huapi_wire_error_shape_classifies_as_definitive_unavailable() {
			$this->given_stored_state( false );

			if ( ! defined( 'NFD_SITES_API' ) ) {
				define( 'NFD_SITES_API', 'https://hosting.uapi.newfold.com/' );
			}
			WP_Mock::onFilter( 'newfold_performance_hosting_uapi_base_url' )
				->with( 'https://hosting.uapi.newfold.com/' )
				->reply( 'https://hosting.uapi.newfold.com/' );
			WP_Mock::userFunction( 'trailingslashit' )->andReturnUsing(
				function ( $s ) {
					return rtrim( (string) $s, '/' ) . '/';
				}
			);

			$this->given_hosting_context();

			WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( array( 'stub' => true ) );
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 512 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode( array( 'error' => RedisServiceAvailability::CUSTOMER_ERROR_SERVICE_INACTIVE ) )
			);

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved( '0', RedisServiceAvailability::TTL_UNAVAILABLE );
		}

		/**
		 * An unknown HUAPI error is indeterminate: fails safe to false, held only briefly so it re-probes soon.
		 */
		public function test_probe_unknown_error_is_indeterminate() {
			$this->given_stored_state( false );
			$this->given_hosting_context();
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return new \WP_Error( 'nfd_hosting_uapi_error', 'boom', array( 'status' => 500 ) );
				}
			);

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved( '0', RedisServiceAvailability::TTL_INDETERMINATE, 1 );
		}

		/**
		 * Each consecutive indeterminate probe waits twice as long as the one before it.
		 */
		public function test_repeated_indeterminate_probes_back_off() {
			$this->given_stored_state(
				array(
					'answer'   => '0',
					'next'     => time() - 1,
					'failures' => 3,
				)
			);
			$this->given_hosting_context();
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return new \WP_Error( 'nfd_hosting_uapi_error', 'boom', array( 'status' => 500 ) );
				}
			);

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			// Fourth consecutive failure: 300 * 2^3.
			$this->assert_saved( '0', RedisServiceAvailability::TTL_INDETERMINATE * 8, 4 );
		}

		/**
		 * The backoff stops growing at the ceiling, and so does the stored count.
		 */
		public function test_indeterminate_backoff_stops_at_the_ceiling() {
			$this->given_stored_state(
				array(
					'answer'   => '0',
					'next'     => time() - 1,
					'failures' => RedisServiceAvailability::MAX_FAILURES,
				)
			);
			$this->given_hosting_context();
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return new \WP_Error( 'nfd_hosting_uapi_error', 'boom', array( 'status' => 500 ) );
				}
			);

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved(
				'0',
				RedisServiceAvailability::TTL_INDETERMINATE_MAX,
				RedisServiceAvailability::MAX_FAILURES
			);
		}

		/**
		 * A server that answers again clears the backoff, so the next blip starts from the short delay.
		 */
		public function test_definitive_answer_clears_the_failure_count() {
			$this->given_stored_state(
				array(
					'answer'   => '0',
					'next'     => time() - 1,
					'failures' => 5,
				)
			);
			$this->given_hosting_context();
			Patchwork\redefine(
				array( HostingUapiClient::class, 'get_site_performance_redis' ),
				function ( $token, $site_id ) {
					return array( 'redis_service_active' => true );
				}
			);

			$this->assertTrue( RedisServiceAvailability::is_daemon_available() );
			$this->assert_saved( '1', RedisServiceAvailability::TTL_AVAILABLE, 0 );
		}

		/**
		 * When the hosting context cannot be fetched (e.g. Hiive not connected), the probe is indeterminate:
		 * false, held only briefly.
		 */
		public function test_probe_no_context_is_indeterminate() {
			$this->given_stored_state( false );
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

			$this->assertFalse( RedisServiceAvailability::is_daemon_available() );
			$this->assertFalse( $uapi_called, 'HUAPI must not be probed without a hosting context.' );
			$this->assert_saved( '0', RedisServiceAvailability::TTL_INDETERMINATE, 1 );
		}
	}
}
