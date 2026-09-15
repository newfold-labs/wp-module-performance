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
	use NewfoldLabs\WP\Module\Data\HiiveConnection;

	/**
	 * Tests the request timeout the Hiive client actually sends.
	 *
	 * The availability probe runs while an admin page renders and makes two calls back to back, so
	 * the timeout it passes is the difference between a slow upstream costing a few seconds and
	 * costing a minute. Nothing else in the suite reaches this class.
	 */
	class HiiveHelperTest extends TestCase {

		/**
		 * Args handed to wp_remote_request.
		 *
		 * @var array
		 */
		private $args;

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();
			WP_Mock::passthruFunction( '__' );
			$this->args = array();

			if ( ! defined( 'NFD_HIIVE_URL' ) ) {
				define( 'NFD_HIIVE_URL', 'https://hiive.cloud/api' );
			}
			WP_Mock::onFilter( 'newfold_performance_hiive_api_base_url' )
				->with( 'https://hiive.cloud/api' )
				->reply( 'https://hiive.cloud/api' );
			WP_Mock::userFunction( 'untrailingslashit' )->andReturnUsing(
				function ( $value ) {
					return rtrim( (string) $value, '/' );
				}
			);

			Patchwork\redefine(
				array( HiiveConnection::class, 'is_connected' ),
				function () {
					return true;
				}
			);
			Patchwork\redefine(
				array( HiiveConnection::class, 'get_auth_token' ),
				function () {
					return 'token';
				}
			);

			WP_Mock::userFunction( 'wp_remote_request' )->andReturnUsing(
				function ( $url, $args ) {
					$this->args = $args;
					return array( 'stub' => true );
				}
			);
			WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
			WP_Mock::userFunction( 'wp_remote_retrieve_body' )->andReturn( '{}' );
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		/**
		 * A caller that passes a timeout gets that timeout, and the shared default is not consulted.
		 */
		public function test_explicit_timeout_is_sent() {
			$helper = new HiiveHelper( '/sites/v1/customer', array(), 'GET', 5 );
			$helper->send_request();

			$this->assertSame( 5, $this->args['timeout'] );
		}

		/**
		 * A caller that passes nothing still gets the shared default, so provisioning is unaffected.
		 */
		public function test_shared_default_timeout_is_sent_when_none_given() {
			WP_Mock::onFilter( 'newfold_performance_hiive_request_timeout_seconds' )
				->with( 30 )
				->reply( 30 );

			$helper = new HiiveHelper( '/sites/v1/customer', array(), 'GET' );
			$helper->send_request();

			$this->assertSame( 30, $this->args['timeout'] );
		}

		/**
		 * The probe's own budget is what reaches the wire when the probe asks for it.
		 */
		public function test_probe_timeout_reaches_the_request() {
			WP_Mock::onFilter( 'newfold_performance_redis_probe_timeout_seconds' )
				->with( 5 )
				->reply( 5 );

			$helper = new HiiveHelper(
				'/sites/v1/customer',
				array(),
				'GET',
				SiteApisConfig::redis_probe_timeout_seconds()
			);
			$helper->send_request();

			$this->assertSame( 5, $this->args['timeout'] );
		}
	}
}
