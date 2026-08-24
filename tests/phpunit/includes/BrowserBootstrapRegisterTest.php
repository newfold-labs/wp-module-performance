<?php
// phpcs:disable

namespace NewfoldLabs\WP\Module\Performance\Cache\Types {

	use WP_Mock;
	use WP_Mock\Tools\TestCase;

	/**
	 * Test the boot-time re-registration of the browser cache fragment.
	 */
	class BrowserBootstrapRegisterTest extends TestCase {

		/**
		 * Set up the test environment.
		 */
		public function setUp(): void {
			WP_Mock::setUp();

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
			WP_Mock::tearDown();
		}

		/**
		 * A normal front-end request should not look anything up. admin_init and
		 * rest_api_init cover the contexts we care about, so init has nothing to do.
		 */
		public function test_front_end_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( false );
			WP_Mock::userFunction( 'get_option' )->never();

			Browser::maybe_bootstrap_register();

			$this->assertConditionsMet();
		}

		/**
		 * Cron has no admin_init, so it goes through the init path instead.
		 */
		public function test_cron_request_reads_the_cache_level() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( true );
			WP_Mock::userFunction( 'get_option' )->once()->andReturn( 0 );

			Browser::maybe_bootstrap_register();

			$this->assertConditionsMet();
		}

		/**
		 * With browser caching off there is nothing to register, and removal stays
		 * with the option listeners so that booting never queues a write.
		 */
		public function test_no_registration_when_caching_is_off() {
			WP_Mock::userFunction( 'get_option' )->once()->andReturn( 0 );

			Browser::bootstrap_register();

			$this->assertConditionsMet();
		}
	}
}
