<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\RestApi\RestApi;
use NewfoldLabs\WP\Module\Performance\Data\Constants;

/**
 * Module loading wpunit tests.
 *
 * @coversDefaultClass \NewfoldLabs\WP\Module\Performance\Performance
 */
class ModuleLoadingWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Verify core module classes exist.
	 *
	 * RestApi\CacheController is not asserted here because it can fail to load in CI
	 * when its dependencies (e.g. CacheManager, container) are not fully available.
	 *
	 * @return void
	 */
	public function test_module_classes_load() {
		$this->assertTrue( class_exists( Performance::class ), 'Performance class should exist' );
		$this->assertTrue( class_exists( PerformanceFeature::class ), 'PerformanceFeature class should exist' );
		$this->assertTrue( class_exists( Permissions::class ), 'Permissions class should exist' );
		$this->assertTrue( class_exists( RestApi::class ), 'RestApi class should exist' );
		$this->assertTrue( class_exists( Constants::class ), 'Data\Constants class should exist' );
		$this->assertTrue( class_exists( Cache\Cache::class ), 'Cache\Cache class should exist' );
	}

	/**
	 * Verify WordPress factory is available.
	 *
	 * @return void
	 */
	public function test_wordpress_factory_available() {
		$this->assertTrue( function_exists( 'get_option' ) );
		$this->assertNotEmpty( get_option( 'blogname' ) );
	}

	/**
	 * Performance constants are defined.
	 *
	 * @return void
	 */
	public function test_performance_constants() {
		$this->assertSame( 'nfd_purge_all', Performance::PURGE_ALL );
		$this->assertSame( 'nfd_purge_url', Performance::PURGE_URL );
		$this->assertSame( 'nfd-performance', Performance::PAGE_SLUG );
	}
}
