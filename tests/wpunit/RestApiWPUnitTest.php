<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\RestApi\RestApi;

/**
 * RestApi wpunit tests.
 *
 * @coversDefaultClass \NewfoldLabs\WP\Module\Performance\RestApi\RestApi
 */
class RestApiWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * rest_api_init registers newfold-performance REST routes.
	 *
	 * @return void
	 */
	public function test_rest_api_init_registers_performance_routes() {
		new RestApi();
		do_action( 'rest_api_init' );
		$server = rest_get_server();
		$routes = $server->get_routes();
		$found  = array_filter(
			array_keys( $routes ),
			function ( $route ) {
				return strpos( $route, 'newfold-performance' ) !== false;
			}
		);
		$this->assertNotEmpty( $found, 'Expected newfold-performance routes to be registered' );
	}
}
