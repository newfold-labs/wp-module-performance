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

	/**
	 * Tests for the HUAPI GET /performance/redis status client.
	 */
	class HostingUapiClientTest extends TestCase {

		public function setUp(): void {
			WP_Mock::setUp();
			WP_Mock::passthruFunction( '__' );
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
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
		}

		/**
		 * A 2xx JSON response is decoded and returned as an array.
		 */
		public function test_get_returns_decoded_status_on_success() {
			WP_Mock::userFunction( 'wp_remote_request' )
				->once()
				->andReturnUsing(
					function ( $url, $args ) {
						$this->assertStringEndsWith( 'v1/sites/12345/performance/redis', $url );
						$this->assertSame( 'GET', $args['method'] );
						$this->assertSame( 'Bearer jwt-token', $args['headers']['Authorization'] );
						return array( 'stub' => true );
					}
				);
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode(
					array(
						'obj_cache_installed'  => false,
						'obj_cache_enabled'    => false,
						'redis_service_active' => true,
					)
				)
			);

			$result = HostingUapiClient::get_site_performance_redis( 'jwt-token', '12345' );

			$this->assertIsArray( $result );
			$this->assertTrue( $result['redis_service_active'] );
		}

		/**
		 * A 2xx response with an unparseable body becomes a WP_Error (indeterminate), not an empty array.
		 */
		public function test_get_treats_unparseable_2xx_as_error() {
			WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( array( 'stub' => true ) );
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( 'not json at all' );

			$result = HostingUapiClient::get_site_performance_redis( 'jwt-token', '12345' );

			$this->assertInstanceOf( \WP_Error::class, $result );
			$data = $result->get_error_data();
			$this->assertSame( 200, $data['status'] );
		}

		/**
		 * A non-2xx response becomes a WP_Error carrying the customer_error string from the body.
		 */
		public function test_get_maps_non_2xx_to_wp_error_with_customer_error() {
			WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( array( 'stub' => true ) );
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 512 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode( array( 'customer_error' => 'redisServiceInactive' ) )
			);

			$result = HostingUapiClient::get_site_performance_redis( 'jwt-token', '12345' );

			$this->assertInstanceOf( \WP_Error::class, $result );
			$data = $result->get_error_data();
			$this->assertSame( 512, $data['status'] );
			$this->assertSame( 'redisServiceInactive', $data['customer_error'] );
		}

		/**
		 * HUAPI sends its errors as {"error": "redisServiceInactive"}. The probe needs that string to
		 * tell a box with no Redis daemon apart from a passing blip, so it has to survive extraction.
		 */
		public function test_get_extracts_customer_error_from_huapi_string_shape() {
			WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( array( 'stub' => true ) );
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 512 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode( array( 'error' => 'redisServiceInactive' ) )
			);

			$result = HostingUapiClient::get_site_performance_redis( 'jwt-token', '12345' );

			$this->assertInstanceOf( \WP_Error::class, $result );
			$data = $result->get_error_data();
			$this->assertSame( 512, $data['status'] );
			$this->assertSame( 'redisServiceInactive', $data['customer_error'] );
		}

		/**
		 * A gateway that wraps the error in an object is still understood.
		 */
		public function test_get_extracts_customer_error_from_wrapped_shape() {
			WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( array( 'stub' => true ) );
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 512 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode( array( 'error' => array( 'customer_error' => 'phpVersionUnsupported' ) ) )
			);

			$result = HostingUapiClient::get_site_performance_redis( 'jwt-token', '12345' );

			$data = $result->get_error_data();
			$this->assertSame( 'phpVersionUnsupported', $data['customer_error'] );
		}

		/**
		 * An error body with no recognisable customer error yields null rather than a guess.
		 */
		public function test_get_customer_error_is_null_when_absent() {
			WP_Mock::userFunction( 'wp_remote_request' )->once()->andReturn( array( 'stub' => true ) );
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 500 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode( array( 'detail' => 'upstream exploded' ) )
			);

			$result = HostingUapiClient::get_site_performance_redis( 'jwt-token', '12345' );

			$data = $result->get_error_data();
			$this->assertNull( $data['customer_error'] );
		}

		/**
		 * Missing token or site id short-circuits to a WP_Error without any HTTP call.
		 */
		public function test_get_returns_wp_error_on_missing_args() {
			WP_Mock::userFunction( 'wp_remote_request' )->never();

			$result = HostingUapiClient::get_site_performance_redis( '', '12345' );
			$this->assertInstanceOf( \WP_Error::class, $result );

			$result2 = HostingUapiClient::get_site_performance_redis( 'jwt', '' );
			$this->assertInstanceOf( \WP_Error::class, $result2 );
		}
	}
}
