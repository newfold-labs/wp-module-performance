<?php
// phpcs:disable

namespace {
	if ( ! class_exists( 'WP_REST_Request' ) ) {
		class WP_REST_Request {
			private $params = array();
			public function __construct( $method = null, $route = '' ) {}
			public function set_param( $k, $v ) {
				$this->params[ $k ] = $v; }
			public function get_param( $k = null, $d = null ) {
				if ( $k === null ) { return $this->params;
				}
				return array_key_exists( $k, $this->params ) ? $this->params[ $k ] : $d;
			}
			public function has_param( $k ) {
				return array_key_exists( $k, $this->params );
			}
			public function set_params( array $params ) {
				$this->params = $params; }
			public function get_params() {
				return $this->params; }
		}
	}
	if ( ! class_exists( 'WP_REST_Response' ) ) {
		class WP_REST_Response {
			private $data;
			private $status;
			public function __construct( $data = null, $status = 200 ) {
				$this->data   = $data;
				$this->status = $status;
			}
			public function get_status() {
				return $this->status;
			}
			public function get_data() {
				return $this->data;
			}
		}
	}
}

namespace NewfoldLabs\WP\Module\Performance\RestApi {
	if ( ! function_exists( __NAMESPACE__ . '\update_option' ) ) {
		function update_option( $option, $value = null ) {
			return \update_option( $option, $value );
		}
	}
}

namespace NewfoldLabs\WP\Module\Performance\Cache {
	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use Patchwork;

	/**
	 * Test Cache Exclusion input value.
	 */
	class CacheExclusionTest extends TestCase {

		/**
		 * Set up the test environment.
		 */
		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll(); // Ensure Patchwork starts with a clean slate.

			WP_Mock::passthruFunction( '__' );
			WP_Mock::passthruFunction( 'esc_html__' );
		}

		/**
		 * Tear down the test environment.
		 */
		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll(); // Clean up all redefined functions/constants.
		}

		/**
		 * Test updating cache exclusion option for valid datas.
		 */
		public function test_update_cache_exclusion_option_valid() {
			$valid_input = 'cart,checkout,wp-admin,wp-json,page1,page2,page-3';

			$request = new \WP_REST_Request();
			$request->set_param( 'cacheExclusion', $valid_input );

			WP_Mock::userFunction(
				'update_option',
				array(
					'args'   => array( \NewfoldLabs\WP\Module\Performance\Cache\CacheExclusion::OPTION_CACHE_EXCLUSION, $valid_input ),
					'times'  => 1,
					'return' => true,
				)
			);

			$controller = new \NewfoldLabs\WP\Module\Performance\RestApi\CacheController();
			$response   = $controller->update_settings( $request );

			$this->assertInstanceOf( \WP_REST_Response::class, $response );
			$this->assertSame( 200, $response->get_status() );
			$this->assertTrue( $response->get_data()['result'] );
		}
		/**
		 * Test updating cache exclusion option for invalid datas.
		 */
		public function test_update_cache_exclusion_option_invalid() {
			$valid_input = 'cart,checkout,wp-admin,wp-json /membership-account* /membership-checkout* /membership-levels* /join-levels/ /login/';

			$request = new \WP_REST_Request();
			$request->set_param( 'cacheExclusion', $valid_input );

			$controller = new \NewfoldLabs\WP\Module\Performance\RestApi\CacheController();
			$response   = $controller->update_settings( $request );

			$this->assertInstanceOf( \WP_REST_Response::class, $response );
			$this->assertSame( 400, $response->get_status() );
			$this->assertFalse( $response->get_data()['result'] );
		}

		/**
		 * Test that get_cache_exclusion() deletes invalid option and returns default.
		 */
		public function test_get_cache_exclusion_deletes_invalid_option_and_returns_default() {
			$invalid_value = 'cart,checkout,wp-admin,wp-json https://example.com/admin/';
			$option_name   = \NewfoldLabs\WP\Module\Performance\Cache\CacheExclusion::OPTION_CACHE_EXCLUSION;
			$default       = 'cart,checkout,wp-admin,wp-json';

			WP_Mock::userFunction( 'get_option' )
				->with( $option_name, $default )
				->andReturn( $invalid_value );

			WP_Mock::userFunction( 'delete_option' )
				->with( $option_name )
				->once()
				->andReturn( true );

			WP_Mock::userFunction( 'rest_get_url_prefix' )
				->andReturn( 'wp-json' );

			$result = \NewfoldLabs\WP\Module\Performance\get_cache_exclusion();

			$this->assertSame( $default, $result );
		}
	}
}
// phpcs:enable