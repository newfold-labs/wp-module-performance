<?php
// phpcs:disable

namespace NewfoldLabs\WP\Module\Performance\Cache\Types {

	use WP_Mock;
	use WP_Mock\Tools\TestCase;
	use WP_Mock\Matcher\AnyInstance;
	use NewfoldLabs\WP\Module\Performance\OptionListener;
	use Patchwork;

	/**
	 * Test the boot-time re-registration of the browser cache fragment.
	 */
	class BrowserBootstrapRegisterTest extends TestCase {

		/**
		 * Cache levels addRules() was called with during the test.
		 *
		 * @var array
		 */
		private $registered = array();

		/**
		 * Set up the test environment.
		 */
		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();

			WP_Mock::userFunction( 'absint' )->andReturnUsing(
				function ( $value ) {
					return abs( intval( $value ) );
				}
			);

			// Stop at the boundary this test cares about. What addRules() then
			// renders is covered by BrowserCacheFragmentTest.
			$this->registered = array();
			$registered       = &$this->registered;

			Patchwork\redefine(
				array( Browser::class, 'addRules' ),
				function ( $cache_level ) use ( &$registered ) {
					$registered[] = $cache_level;
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
		 * The re-render is only worth anything if it is actually wired up, and
		 * both the hook and the priority are load-bearing. admin_init at 20 has
		 * to land before the htaccess Manager reconciles at PHP_INT_MAX.
		 */
		public function test_constructor_wires_the_boot_hooks() {
			// The option listeners hook a handful of add/update/delete actions of
			// their own. They are not what this test is about.
			Patchwork\redefine(
				array( OptionListener::class, '__construct' ),
				function () {}
			);

			WP_Mock::expectFilterAdded(
				'newfold_update_htaccess',
				array( new AnyInstance( Browser::class ), 'on_rewrite' ),
				10,
				1
			);
			WP_Mock::expectActionAdded(
				'admin_init',
				array( Browser::class, 'maybe_bootstrap_register' ),
				20
			);
			WP_Mock::expectActionAdded(
				'init',
				array( Browser::class, 'maybe_bootstrap_register_on_cron' ),
				5
			);

			new Browser();

			$this->assertConditionsMet();
		}

		/**
		 * The init hook exists for cron only. Front-end and REST traffic reach it
		 * too, and must not pay for this.
		 */
		public function test_front_end_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( false );

			Browser::maybe_bootstrap_register_on_cron();

			$this->assertSame( array(), $this->registered );
		}

		/**
		 * Cron has no admin_init, so it goes through the init hook instead.
		 */
		public function test_cron_request_registers() {
			WP_Mock::userFunction( 'wp_doing_cron' )->andReturn( true );
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
			WP_Mock::userFunction( 'get_option' )->once()->andReturn( 3 );

			Browser::maybe_bootstrap_register_on_cron();

			$this->assertSame( array( 3 ), $this->registered );
		}

		/**
		 * admin-ajax fires admin_init, and heartbeat hits it every few seconds, so
		 * it is skipped rather than re-rendering on every poll.
		 */
		public function test_admin_ajax_request_does_nothing() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( true );
			WP_Mock::userFunction( 'get_option' )->never();

			Browser::maybe_bootstrap_register();

			$this->assertSame( array(), $this->registered );
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

			$this->assertSame( array(), $this->registered );
		}

		/**
		 * An ordinary admin page load is what this is for.
		 */
		public function test_admin_request_registers() {
			WP_Mock::userFunction( 'wp_doing_ajax' )->andReturn( false );
			WP_Mock::userFunction( 'is_multisite' )->andReturn( false );
			WP_Mock::userFunction( 'get_option' )->once()->andReturn( 2 );

			Browser::maybe_bootstrap_register();

			$this->assertSame( array( 2 ), $this->registered );
		}

		/**
		 * A set level is passed straight through.
		 */
		public function test_option_change_registers_the_new_level() {
			WP_Mock::userFunction( 'is_multisite' )->andReturn( false );

			Browser::maybeAddRules( 0 );
			Browser::maybeAddRules( 3 );

			$this->assertSame( array( 0, 3 ), $this->registered );
		}

		/**
		 * Deleting the option is not the same as setting it to zero, so the
		 * listener's null resolves through the default rather than turning
		 * caching off in the file.
		 */
		public function test_deleted_option_falls_back_to_the_default_level() {
			WP_Mock::userFunction( 'get_option' )->once()->andReturn( 2 );

			Browser::maybeAddRules( null );

			$this->assertSame( array( 2 ), $this->registered );
		}

		/**
		 * One .htaccess serves a whole network, so asserting the off state for
		 * one site would turn mod_expires off for every site on it. Level 0 keeps
		 * removing the block there.
		 */
		public function test_multisite_removes_rather_than_asserting_off() {
			WP_Mock::userFunction( 'is_multisite' )->andReturn( true );

			$unregistered = false;
			Patchwork\redefine(
				array( Browser::class, 'removeRules' ),
				function () use ( &$unregistered ) {
					$unregistered = true;
				}
			);

			Browser::maybeAddRules( 0 );

			$this->assertTrue( $unregistered, 'Level 0 on a network should unregister.' );
			$this->assertSame( array(), $this->registered );
		}

		/**
		 * Above level 0 a network registers as usual. Only the off switch is
		 * withheld there.
		 */
		public function test_multisite_still_registers_above_zero() {
			WP_Mock::userFunction( 'is_multisite' )->andReturn( true );

			Browser::maybeAddRules( 3 );

			$this->assertSame( array( 3 ), $this->registered );
		}

		/**
		 * Caching turned off is a setting rather than an absence, so level 0 boots
		 * into a registration like any other level.
		 */
		public function test_caching_off_still_registers() {
			WP_Mock::userFunction( 'get_option' )->once()->andReturn( 0 );

			Browser::bootstrap_register();

			$this->assertSame( array( 0 ), $this->registered );
		}
	}
}
