<?php
// phpcs:disable

namespace NewfoldLabs\WP\Module\Performance\Cache\Types {
	use Patchwork;
	use WP_Mock;
	use WP_Mock\Tools\TestCase;

	/**
	 * Tests for the read-only Redis / object cache diagnostics report.
	 */
	class ObjectCacheDiagnosticsTest extends TestCase {

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();

			WP_Mock::passthruFunction( '__' );

			// Drop-in section probes this WP function; mock it so it has a handler in strict mode.
			WP_Mock::userFunction( 'wp_using_ext_object_cache' )->andReturn( true );

			// phpredis available so the report exercises the live-ping path.
			Patchwork\redefine(
				'extension_loaded',
				function ( $ext ) {
					return 'redis' === $ext ? true : \extension_loaded( $ext );
				}
			);

			// Avoid touching wp-config or a real Redis server.
			Patchwork\redefine(
				array( ObjectCache::class, 'bootstrap_redis_connection_constants_for_preflight' ),
				function () {}
			);
			Patchwork\redefine(
				array( ObjectCache::class, 'is_configured_in_wp_config' ),
				function () {
					return true;
				}
			);
			Patchwork\redefine(
				array( ObjectCache::class, 'get_drop_in_path' ),
				function () {
					return __FILE__;
				}
			);
			Patchwork\redefine(
				array( ObjectCache::class, 'get_state' ),
				function () {
					return array(
						'available'   => true,
						'enabled'     => true,
						'overwritten' => false,
						'ours'        => true,
					);
				}
			);
			Patchwork\redefine(
				array( PhpRedisPinger::class, 'ping' ),
				function () {
					return array( 'ok' => true );
				}
			);
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		public function test_report_has_expected_shape() {
			$report = ObjectCacheDiagnostics::run();

			$this->assertArrayHasKey( 'generated', $report );
			$this->assertArrayHasKey( 'summary', $report );
			$this->assertArrayHasKey( 'sections', $report );
			$this->assertTrue( $report['summary']['ok'] );
			$this->assertSame( array(), $report['summary']['issues'] );

			foreach ( $report['sections'] as $section ) {
				$this->assertArrayHasKey( 'title', $section );
				$this->assertArrayHasKey( 'lines', $section );
				foreach ( $section['lines'] as $line ) {
					$this->assertArrayHasKey( 'status', $line );
					$this->assertArrayHasKey( 'message', $line );
				}
			}
		}

		public function test_password_value_is_never_exposed() {
			// A distinctive secret that must not appear anywhere in the report.
			if ( ! defined( 'WP_REDIS_PASSWORD' ) ) {
				define( 'WP_REDIS_PASSWORD', 'sup3r-s3cr3t-redis-pw-DO-NOT-LEAK' );
			}
			if ( ! defined( 'WP_REDIS_HOST' ) ) {
				define( 'WP_REDIS_HOST', '127.0.0.1' );
			}

			$report = ObjectCacheDiagnostics::run();
			$json   = json_encode( $report );

			$this->assertStringNotContainsString( 'sup3r-s3cr3t-redis-pw-DO-NOT-LEAK', $json );

			// The password should still be reported as present (presence only).
			$this->assertStringContainsString( 'WP_REDIS_PASSWORD = (set)', $json );

			// Non-sensitive infrastructure values are allowed through.
			$this->assertStringContainsString( '127.0.0.1', $json );
		}
	}
}
