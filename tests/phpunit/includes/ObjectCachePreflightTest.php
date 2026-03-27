<?php
// phpcs:disable

namespace NewfoldLabs\WP\Module\Performance\Cache {
	use Patchwork;
	use WP_Mock;
	use WP_Mock\Tools\TestCase;

	/**
	 * Preflight snapshot tests (no live Redis).
	 */
	class ObjectCachePreflightTest extends TestCase {

		public function setUp(): void {
			WP_Mock::setUp();
			Patchwork\restoreAll();

			WP_Mock::passthruFunction( '__' );
		}

		public function tearDown(): void {
			WP_Mock::tearDown();
			Patchwork\restoreAll();
		}

		public function test_preflight_snapshot_marks_phpredis_missing_without_live_ping() {
			Patchwork\redefine(
				'extension_loaded',
				function ( $ext ) {
					return 'redis' === $ext ? false : \extension_loaded( $ext );
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'is_configured_in_wp_config' ),
				function () {
					return true;
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'constants_visible_this_request' ),
				function () {
					return true;
				}
			);

			$snapshot = \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCachePreflight::snapshot( false );

			$this->assertFalse( $snapshot['extensionLoaded'] );
			$this->assertFalse( $snapshot['redisPingOk'] );
			$this->assertSame( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes::PHPREDIS_MISSING, $snapshot['preflightCode'] );
		}

		public function test_preflight_before_provision_requires_hiive_when_wp_config_has_no_creds() {
			Patchwork\redefine(
				'extension_loaded',
				function ( $ext ) {
					return 'redis' === $ext ? true : \extension_loaded( $ext );
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Data\HiiveConnection::class, 'is_connected' ),
				function () {
					return false;
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'is_configured_in_wp_config' ),
				function () {
					return false;
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'constants_visible_this_request' ),
				function () {
					return false;
				}
			);

			$snapshot = \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCachePreflight::snapshot( false );

			$this->assertTrue( $snapshot['extensionLoaded'] );
			$this->assertFalse( $snapshot['configuredInWpConfig'] );
			$this->assertFalse( $snapshot['hiiveConnected'] );
			$this->assertSame(
				\NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes::HIIVE_NOT_CONNECTED,
				$snapshot['preflightCode']
			);
		}

		public function test_preflight_credentials_missing_when_hiive_connected_and_wp_config_has_no_creds() {
			Patchwork\redefine(
				'extension_loaded',
				function ( $ext ) {
					return 'redis' === $ext ? true : \extension_loaded( $ext );
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Data\HiiveConnection::class, 'is_connected' ),
				function () {
					return true;
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'is_configured_in_wp_config' ),
				function () {
					return false;
				}
			);

			Patchwork\redefine(
				array( \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCache::class, 'constants_visible_this_request' ),
				function () {
					return false;
				}
			);

			$snapshot = \NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCachePreflight::snapshot( false );

			$this->assertTrue( $snapshot['hiiveConnected'] );
			$this->assertSame(
				\NewfoldLabs\WP\Module\Performance\Cache\Types\ObjectCacheErrorCodes::CREDENTIALS_MISSING,
				$snapshot['preflightCode']
			);
		}
	}
}
