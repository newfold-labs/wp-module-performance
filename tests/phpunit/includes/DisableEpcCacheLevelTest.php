<?php
// phpcs:disable

namespace {
	// The helper asks the filesystem whether EPC is installed, so point the
	// constant at a directory this test owns.
	if ( ! defined( 'WPMU_PLUGIN_DIR' ) ) {
		define( 'WPMU_PLUGIN_DIR', sys_get_temp_dir() . '/nfd-perf-mu-plugins' );
	}
}

namespace NewfoldLabs\WP\Module\Performance {

	use WP_Mock;
	use WP_Mock\Tools\TestCase;

	/**
	 * EPC is switched off by clamping its cache level to zero and leaving the
	 * option in place. Removing it is what hands EPC back its default of 2.
	 */
	class DisableEpcCacheLevelTest extends TestCase {

		/**
		 * Path to the stand-in EPC must-use plugin.
		 *
		 * @var string
		 */
		private $epc_file;

		/**
		 * Set up the test environment.
		 */
		public function setUp(): void {
			WP_Mock::setUp();

			$this->epc_file = WPMU_PLUGIN_DIR . '/endurance-page-cache.php';

			if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
				mkdir( WPMU_PLUGIN_DIR, 0777, true );
			}

			WP_Mock::userFunction( 'absint' )->andReturnUsing(
				function ( $value ) {
					return abs( intval( $value ) );
				}
			);
		}

		/**
		 * Tear down the test environment.
		 */
		public function tearDown(): void {
			if ( file_exists( $this->epc_file ) ) {
				unlink( $this->epc_file );
			}

			WP_Mock::tearDown();
		}

		/**
		 * Put the stand-in EPC must-use plugin in place.
		 */
		private function install_epc() {
			file_put_contents( $this->epc_file, '<?php' );
		}

		/**
		 * Sites without EPC have nothing to clamp, and writing the option anyway
		 * would leave a stray row behind on every one of them.
		 */
		public function test_does_nothing_without_epc() {
			WP_Mock::userFunction( 'get_option' )->never();
			WP_Mock::userFunction( 'update_option' )->never();

			$this->assertFalse( disable_epc_cache_level() );
		}

		/**
		 * EPC reads the option with a default of 2, so an option that is not
		 * there is the state that needs writing, not one that can be skipped.
		 */
		public function test_absent_option_is_written() {
			$this->install_epc();

			WP_Mock::userFunction( 'get_option' )
				->once()
				->with( 'endurance_cache_level', false )
				->andReturn( false );
			WP_Mock::userFunction( 'update_option' )
				->once()
				->with( 'endurance_cache_level', 0 )
				->andReturn( true );

			$this->assertTrue( disable_epc_cache_level() );
		}

		/**
		 * A level EPC would act on is clamped, and the option stays put.
		 */
		public function test_non_zero_level_is_clamped_and_kept() {
			$this->install_epc();

			WP_Mock::userFunction( 'get_option' )
				->once()
				->with( 'endurance_cache_level', false )
				->andReturn( 2 );
			WP_Mock::userFunction( 'update_option' )
				->once()
				->with( 'endurance_cache_level', 0 )
				->andReturn( true );
			WP_Mock::userFunction( 'delete_option' )->never();

			$this->assertTrue( disable_epc_cache_level() );
		}

		/**
		 * Already off, so there is nothing to write.
		 */
		public function test_zero_level_is_left_alone() {
			$this->install_epc();

			WP_Mock::userFunction( 'get_option' )
				->once()
				->with( 'endurance_cache_level', false )
				->andReturn( 0 );
			WP_Mock::userFunction( 'update_option' )->never();

			$this->assertFalse( disable_epc_cache_level() );
		}

		/**
		 * Options come back from the database as strings.
		 */
		public function test_zero_level_stored_as_a_string_is_left_alone() {
			$this->install_epc();

			WP_Mock::userFunction( 'get_option' )
				->once()
				->with( 'endurance_cache_level', false )
				->andReturn( '0' );
			WP_Mock::userFunction( 'update_option' )->never();

			$this->assertFalse( disable_epc_cache_level() );
		}
	}
}
