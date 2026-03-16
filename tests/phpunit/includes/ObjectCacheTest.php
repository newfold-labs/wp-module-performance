<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use Patchwork;

/**
 * Test ObjectCache reconciliation and preference handling.
 */
class ObjectCacheTest extends TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		WP_Mock::setUp();
		Patchwork\restoreAll();

		WP_Mock::passthruFunction( '__' );
		if ( ! defined( 'WP_CONTENT_DIR' ) ) {
			define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content-performance-test' );
		}
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		Patchwork\restoreAll();
	}

	/**
	 * When the file does not exist, reconcile returns immediately (no-op).
	 */
	public function test_reconcile_non_ours_dropin_file_missing() {
		Patchwork\redefine( 'file_exists', function ( $path ) {
			return false;
		} );

		// get_option should not be called when file is missing.
		WP_Mock::userFunction( 'get_option' )
			->never();

		ObjectCache::reconcile_non_ours_dropin();
		$this->assertTrue( true, 'Reconcile with missing file should return without error.' );
	}

	/**
	 * When the file exists and is our drop-in, reconcile returns immediately (no-op).
	 */
	public function test_reconcile_non_ours_dropin_file_is_ours() {
		Patchwork\redefine( 'file_exists', function ( $path ) {
			return true;
		} );
		Patchwork\redefine( 'is_readable', function ( $path ) {
			return true;
		} );
		Patchwork\redefine( 'file_get_contents', function ( $path ) {
			return '<?php /* ' . ObjectCache::DROPIN_HEADER_IDENTIFIER . ' */';
		} );

		WP_Mock::userFunction( 'get_option' )
			->never();

		ObjectCache::reconcile_non_ours_dropin();
		$this->assertTrue( true, 'Reconcile when file is ours should return without calling get_option.' );
	}

	/**
	 * When file is not ours and preference is disabled, reconcile leaves the file alone (no replace, no delete).
	 */
	public function test_reconcile_non_ours_dropin_preference_disabled_leaves_file() {
		Patchwork\redefine( 'file_exists', function ( $path ) {
			return true;
		} );
		Patchwork\redefine( 'file_get_contents', function ( $path ) {
			return '<?php /* Third-party object cache */';
		} );

		WP_Mock::userFunction( 'get_option' )
			->with( ObjectCache::OPTION_ENABLED_PREFERENCE, ObjectCache::PREFERENCE_NOT_SET_SENTINEL )
			->andReturn( false );

		// enable() and remote get must not be called when preference is disabled.
		WP_Mock::userFunction( 'wp_remote_get' )
			->never();

		ObjectCache::reconcile_non_ours_dropin();
		$this->assertTrue( true, 'Reconcile with preference disabled should leave file alone.' );
	}

	/**
	 * When file is not ours, preference not set, and Redis not available, reconcile leaves the file alone.
	 */
	public function test_reconcile_non_ours_dropin_preference_not_set_redis_not_available_leaves_file() {
		Patchwork\redefine( 'file_exists', function ( $path ) {
			return true;
		} );
		Patchwork\redefine( 'file_get_contents', function ( $path ) {
			return '<?php /* Third-party object cache */';
		} );

		WP_Mock::userFunction( 'get_option' )
			->with( ObjectCache::OPTION_ENABLED_PREFERENCE, ObjectCache::PREFERENCE_NOT_SET_SENTINEL )
			->andReturn( ObjectCache::PREFERENCE_NOT_SET_SENTINEL );

		// is_available() requires WP_REDIS_PREFIX and WP_REDIS_PASSWORD defined.
		Patchwork\redefine(
			'defined',
			function ( $name ) {
				if ( 'WP_REDIS_PREFIX' === $name || 'WP_REDIS_PASSWORD' === $name ) {
					return false;
				}
				return Patchwork\relay();
			}
		);

		WP_Mock::userFunction( 'wp_remote_get' )
			->never();

		ObjectCache::reconcile_non_ours_dropin();
		$this->assertTrue( true, 'Reconcile with preference not set and Redis not available should leave file alone.' );
	}

	/**
	 * When file is not ours and preference is disabled, get_option receives the sentinel as default.
	 */
	public function test_reconcile_preference_not_set_uses_sentinel() {
		Patchwork\redefine( 'file_exists', function ( $path ) {
			return true;
		} );
		Patchwork\redefine( 'file_get_contents', function ( $path ) {
			return '<?php /* Other cache */';
		} );

		WP_Mock::userFunction( 'get_option' )
			->with( ObjectCache::OPTION_ENABLED_PREFERENCE, ObjectCache::PREFERENCE_NOT_SET_SENTINEL )
			->andReturn( ObjectCache::PREFERENCE_NOT_SET_SENTINEL );

		Patchwork\redefine(
			'defined',
			function ( $name ) {
				if ( 'WP_REDIS_PREFIX' === $name || 'WP_REDIS_PASSWORD' === $name ) {
					return false;
				}
				return Patchwork\relay();
			}
		);

		WP_Mock::userFunction( 'wp_remote_get' )
			->never();

		ObjectCache::reconcile_non_ours_dropin();
		$this->assertTrue( true, 'Reconcile with sentinel (preference not set) and Redis not available should leave file alone.' );
	}
}
