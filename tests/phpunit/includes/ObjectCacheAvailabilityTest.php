<?php
// phpcs:disable

namespace NewfoldLabs\WP\Module\Performance\Cache\Types {

	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use Patchwork;
	use NewfoldLabs\WP\Module\Performance\Helpers\RedisServiceAvailability;

	/**
	 * Tests that get_state()['available'] (the toggle-visibility flag) reflects real Redis
	 * availability instead of always defaulting to true.
	 */
	class ObjectCacheAvailabilityTest extends TestCase {

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();
			WP_Mock::passthruFunction( '__' );
			if ( ! defined( 'WP_CONTENT_DIR' ) ) {
				define( 'WP_CONTENT_DIR', sys_get_temp_dir() . '/wp-content-performance-test' );
			}

			// No drop-in on disk for these tests.
			Patchwork\redefine( 'file_exists', function ( $_path ) {
				return false;
			} );
			// Keep preflight snapshot out of these unit tests (it has its own coverage).
			Patchwork\redefine(
				array( ObjectCachePreflight::class, 'snapshot' ),
				function ( $_live = false ) {
					return array();
				}
			);
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		/**
		 * The regression case: Redis constants absent AND the server daemon is unavailable ->
		 * the UI is NOT offered (available = false), so the toggle can't be clicked into an error.
		 */
		public function test_available_false_when_daemon_unavailable_and_unconfigured() {
			Patchwork\redefine( array( ObjectCache::class, 'is_available' ), function () {
				return false;
			} );
			Patchwork\redefine( array( RedisServiceAvailability::class, 'is_daemon_available' ), function () {
				return false;
			} );

			// The filter must receive a computed default of false (not the old hard-coded true).
			WP_Mock::onFilter( 'newfold_performance_object_cache_ui_available' )->with( false )->reply( false );

			$state = ObjectCache::get_state();
			$this->assertFalse( $state['available'], 'Toggle must be hidden where Redis cannot run.' );
			$this->assertFalse( $state['enabled'] );
		}

		/**
		 * Happy path preserved: constants absent but the daemon IS available (box can provision on
		 * first enable) -> the UI is still offered.
		 */
		public function test_available_true_when_daemon_available_even_if_unconfigured() {
			Patchwork\redefine( array( ObjectCache::class, 'is_available' ), function () {
				return false;
			} );
			Patchwork\redefine( array( RedisServiceAvailability::class, 'is_daemon_available' ), function () {
				return true;
			} );

			WP_Mock::onFilter( 'newfold_performance_object_cache_ui_available' )->with( true )->reply( true );

			$state = ObjectCache::get_state();
			$this->assertTrue( $state['available'], 'Toggle must remain available on boxes that can run Redis.' );
		}

		/**
		 * Already-configured sites always show the UI (so it can be turned off) without a network probe.
		 */
		public function test_available_true_when_configured_without_probing_server() {
			Patchwork\redefine( array( ObjectCache::class, 'is_available' ), function () {
				return true;
			} );

			$probed = false;
			Patchwork\redefine( array( RedisServiceAvailability::class, 'is_daemon_available' ), function () use ( &$probed ) {
				$probed = true;
				return false;
			} );

			WP_Mock::onFilter( 'newfold_performance_object_cache_ui_available' )->with( true )->reply( true );

			$state = ObjectCache::get_state();
			$this->assertTrue( $state['available'] );
			$this->assertFalse( $probed, 'Configured sites must not trigger the server-side availability probe.' );
		}
	}
}
