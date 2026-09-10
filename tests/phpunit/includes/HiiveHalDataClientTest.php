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

	use NewfoldLabs\WP\Module\Data\HiiveConnection;
	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use Patchwork;

	/**
	 * Tests for Hiive HAL refresh and investigation flag client.
	 */
	class HiiveHalDataClientTest extends TestCase {

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();
			WP_Mock::passthruFunction( '__' );
			WP_Mock::passthruFunction( 'wp_json_encode' );
			WP_Mock::passthruFunction( 'untrailingslashit' );

			if ( ! defined( 'NFD_HIIVE_URL' ) ) {
				define( 'NFD_HIIVE_URL', 'https://hiive.cloud/api' );
			}

			WP_Mock::onFilter( 'newfold_performance_hiive_api_base_url' )
				->with( 'https://hiive.cloud/api' )
				->reply( 'https://hiive.cloud/api' );
			WP_Mock::onFilter( 'newfold_performance_hiive_request_timeout_seconds' )
				->with( 30 )
				->reply( 30 );
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		public function test_refresh_customer_data_returns_error_when_hiive_not_connected() {
			Patchwork\redefine(
				array( HiiveConnection::class, 'is_connected' ),
				function () {
					return false;
				}
			);

			$result = HiiveHalDataClient::refresh_customer_data();
			$this->assertTrue( is_wp_error( $result ) );
			$this->assertSame( 'hiive_not_connected', $result->get_error_code() );
		}

		public function test_refresh_customer_data_returns_decoded_payload() {
			Patchwork\redefine(
				array( HiiveConnection::class, 'is_connected' ),
				function () {
					return true;
				}
			);
			Patchwork\redefine(
				array( HiiveConnection::class, 'get_auth_token' ),
				function () {
					return 'site-token';
				}
			);

			WP_Mock::userFunction( 'wp_remote_request' )
				->once()
				->andReturnUsing(
					function ( $url, $args ) {
						$this->assertStringEndsWith( '/sites/v1/hal/refresh-customer-data', $url );
						$this->assertSame( 'POST', $args['method'] );
						$this->assertSame( 'Bearer site-token', $args['headers']['Authorization'] );
						return array( 'stub' => true );
					}
				);
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn(
				// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- Test fixture.
				json_encode(
					array(
						'refreshed' => true,
						'tenant_id' => '123',
					)
				)
			);

			$result = HiiveHalDataClient::refresh_customer_data();
			$this->assertIsArray( $result );
			$this->assertTrue( $result['refreshed'] );
			$this->assertSame( '123', $result['tenant_id'] );
		}

		public function test_flag_investigation_returns_true_on_success() {
			Patchwork\redefine(
				array( HiiveConnection::class, 'is_connected' ),
				function () {
					return true;
				}
			);
			Patchwork\redefine(
				array( HiiveConnection::class, 'get_auth_token' ),
				function () {
					return 'site-token';
				}
			);

			WP_Mock::userFunction( 'wp_remote_request' )
				->once()
				->andReturnUsing(
					function ( $url, $args ) {
						$this->assertStringEndsWith( '/sites/v1/hal/flag-investigation', $url );
						$this->assertSame( 'POST', $args['method'] );
						return array( 'stub' => true );
					}
				);
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( '{}' );

			$result = HiiveHalDataClient::flag_investigation( 'test reason', 'wp-module-performance' );
			$this->assertTrue( $result );
		}
	}
}
