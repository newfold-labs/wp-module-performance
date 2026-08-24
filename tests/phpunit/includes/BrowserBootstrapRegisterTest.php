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
		 * The init hook exists for cron only. Front-end and REST traffic reach it
		 * too, and must not pay for this.
		 */
		public function test_front_end_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( false );
			WP_Mock::userFunction( 'get_option' )->never();

			Browser::maybe_bootstrap_register_on_cron();

			$this->assertConditionsMet();
		}

		/**
		 * Cron has no admin_init, so it goes through the init hook instead.
		 */
		public function test_cron_request_reads_the_cache_level() {
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( true );
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
			WP_Mock::userFunction( 'get_option' )->once()->andReturn( 0 );

			Browser::maybe_bootstrap_register_on_cron();

			$this->assertConditionsMet();
		}

		/**
		 * admin-ajax fires admin_init, and heartbeat hits it every few seconds, so
		 * it is skipped rather than re-rendering on every poll.
		 */
		public function test_admin_ajax_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( true );
			WP_Mock::userFunction( 'get_option' )->never();

			Browser::maybe_bootstrap_register();

			$this->assertConditionsMet();
		}

		/**
		 * A network shares one .htaccess while the rendered rules are per site, so
		 * re-rendering on boot would have each site undo the last one.
		 */
		public function test_multisite_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'is_multisite' )->andReturn( true );
			WP_Mock::userFunction( 'get_option' )->never();

			Browser::maybe_bootstrap_register();

			$this->assertConditionsMet();
		}

		/**
		 * An ordinary admin page load is what this is for.
		 */
		public function test_admin_request_reads_the_cache_level() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
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
