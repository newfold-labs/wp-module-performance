<?php
// phpcs:disable

namespace NewfoldLabs\WP\Module\Performance\Cache\Types {

	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use Patchwork;

	/**
	 * Test the boot-time re-registration guards of the file cache fragment.
	 *
	 * Only the guards live here. What bootstrap_register() decides after them
	 * reads the brand off the module container, which a unit test cannot stand
	 * up, so that half belongs in an integration test.
	 */
	class FileBootstrapRegisterTest extends TestCase {

		/**
		 * Whether bootstrap_register() was reached.
		 *
		 * @var bool
		 */
		private $reached = false;

		/**
		 * Set up the test environment.
		 */
		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();

			$this->reached = false;
			$reached       = &$this->reached;

			Patchwork\redefine(
				array( File::class, 'bootstrap_register' ),
				function () use ( &$reached ) {
					$reached = true;
				}
			);
		}

		/**
		 * Tear down the test environment.
		 */
		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		/**
		 * admin-ajax fires admin_init, and heartbeat hits it every few seconds, so
		 * it is skipped rather than re-rendering on every poll.
		 */
		public function test_admin_ajax_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( true );

			File::maybe_bootstrap_register();

			$this->assertFalse( $this->reached );
		}

		/**
		 * A network shares one .htaccess while the rendered rules are per site, so
		 * re-rendering on boot would have each site undo the last one.
		 */
		public function test_multisite_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'is_multisite' )->andReturn( true );

			File::maybe_bootstrap_register();

			$this->assertFalse( $this->reached );
		}

		/**
		 * An ordinary admin page load is what this is for.
		 */
		public function test_admin_request_reaches_the_register() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'is_multisite' )->andReturn( false );

			File::maybe_bootstrap_register();

			$this->assertTrue( $this->reached );
		}
	}
}
