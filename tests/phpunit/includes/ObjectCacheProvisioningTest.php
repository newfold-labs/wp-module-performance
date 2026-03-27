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

namespace NewfoldLabs\WP\Module\Performance\Cache {
	use Patchwork;
	use WP_Mock;
	use WP_Mock\Tools\TestCase;

	/**
	 * Object cache REST error shape tests.
	 */
	class ObjectCacheProvisioningTest extends TestCase {

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();

			WP_Mock::passthruFunction( '__' );
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		public function test_object_cache_toggle_returns_code_on_failure() {
			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'enable' ),
				function () {
					return array(
						'success' => false,
						'code'    => \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes::PHPREDIS_MISSING,
						'message' => 'missing',
					);
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'get_state' ),
				function () {
					return array(
						'available'   => false,
						'enabled'     => false,
						'overwritten' => false,
						'ours'        => false,
						'preflight'   => array(),
					);
				}
			);

			$request = new \WP_REST_Request();
			$request->set_param( 'objectCache', array( 'enabled' => true ) );

			$controller = new \NewfoldLabs\WP\Module\Performance\RestApi\CacheController();
			$response   = $controller->update_settings( $request );

			$this->assertInstanceOf( \WP_REST_Response::class, $response );
			$this->assertSame( 400, $response->get_status() );

			$data = $response->get_data();
			$this->assertFalse( $data['result'] );
			$this->assertSame( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes::PHPREDIS_MISSING, $data['code'] );
			$this->assertArrayHasKey( 'objectCache', $data );
		}
	}
}
