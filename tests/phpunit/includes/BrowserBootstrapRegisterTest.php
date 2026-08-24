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
		 * Front-end and REST traffic must not pay for this.
		 */
		public function test_front_end_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( false );
			WP_Mock::userFunction( 'is_admin' )->andReturn( false );
			WP_Mock::userFunction( 'get_option' )->never();

			Browser::maybe_bootstrap_register();

			$this->assertConditionsMet();
		}

		/**
		 * admin-ajax counts as admin, and heartbeat hits it every few seconds, so
		 * it is skipped rather than re-rendering on every poll.
		 */
		public function test_admin_ajax_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( true );
			WP_Mock::userFunction( 'get_option' )->never();

			Browser::maybe_bootstrap_register();

			$this->assertConditionsMet();
		}

		/**
		 * Cron has no admin_init, so it goes through this path instead.
		 */
		public function test_cron_request_reads_the_cache_level() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( true );
			WP_Mock::userFunction( 'is_admin' )->andReturn( false );
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
