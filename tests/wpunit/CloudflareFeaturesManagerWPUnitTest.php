<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Cloudflare\CloudflareFeaturesManager;

/**
 * CloudflareFeaturesManager wpunit tests.
 *
 * Guards the page-cache-safe behaviour of the Cloudflare optimization cookie:
 * it must be set client-side (so HTML responses carry no Set-Cookie and stay
 * cacheable) and must never re-introduce a Set-Cookie .htaccess rule.
 *
 * @coversDefaultClass \NewfoldLabs\WP\Module\Performance\Cloudflare\CloudflareFeaturesManager
 */
class CloudflareFeaturesManagerWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Reset relevant options between tests.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'nfd_image_optimization' );
		delete_option( 'nfd_fonts_optimization' );
		delete_option( 'nfd_cf_opt_cookie_htaccess_cleaned' );
		parent::tearDown();
	}

	/**
	 * Enable the Cloudflare image optimizations (mirage + polish).
	 *
	 * @return void
	 */
	private function enable_cf_image_optimizations(): void {
		update_option(
			'nfd_image_optimization',
			array(
				'enabled'    => true,
				'cloudflare' => array(
					'mirage' => array( 'value' => true ),
					'polish' => array( 'value' => true ),
				),
			)
		);
	}

	/**
	 * Capture the markup printed on the wp_head hook.
	 *
	 * @return string
	 */
	private function render_wp_head(): string {
		ob_start();
		do_action( 'wp_head' );
		return (string) ob_get_clean();
	}

	/**
	 * With CF features enabled, wp_head emits the cookie-setting script.
	 *
	 * @return void
	 */
	public function test_prints_cookie_script_when_features_enabled() {
		$this->enable_cf_image_optimizations();
		new CloudflareFeaturesManager();

		$head = $this->render_wp_head();

		// mirage + polish hashes, concatenated (no fonts).
		$expected_value = substr( sha1( 'mirage' ), 0, 8 ) . substr( sha1( 'polish' ), 0, 8 );

		$this->assertStringContainsString( 'document.cookie', $head, 'Expected an inline cookie-setting script' );
		$this->assertStringContainsString( 'nfd-enable-cf-opt', $head, 'Expected the CF optimization cookie name' );
		$this->assertStringContainsString( $expected_value, $head, 'Expected the deterministic feature-encoded value' );
	}

	/**
	 * The response itself must never carry a Set-Cookie header — that is what
	 * breaks nginx+/Cloudflare page caching. The script sets it client-side.
	 *
	 * @return void
	 */
	public function test_does_not_emit_set_cookie_header() {
		$this->enable_cf_image_optimizations();
		new CloudflareFeaturesManager();

		$this->render_wp_head();

		foreach ( headers_list() as $header ) {
			$this->assertStringStartsNotWith(
				'Set-Cookie: nfd-enable-cf-opt',
				$header,
				'CF optimization cookie must not be sent as a response header'
			);
		}
	}

	/**
	 * With no CF features enabled, nothing is printed.
	 *
	 * @return void
	 */
	public function test_prints_nothing_when_no_features_enabled() {
		new CloudflareFeaturesManager();

		$head = $this->render_wp_head();

		$this->assertStringNotContainsString( 'nfd-enable-cf-opt', $head );
	}

	/**
	 * The fonts flag contributes its hash segment to the value.
	 *
	 * @return void
	 */
	public function test_value_encodes_fonts_segment() {
		$this->enable_cf_image_optimizations();
		update_option(
			'nfd_fonts_optimization',
			array(
				'cloudflare' => array(
					'fonts' => array( 'value' => true ),
				),
			)
		);
		new CloudflareFeaturesManager();

		$head     = $this->render_wp_head();
		$expected = substr( sha1( 'mirage' ), 0, 8 ) . substr( sha1( 'polish' ), 0, 8 ) . substr( sha1( 'fonts' ), 0, 8 );

		$this->assertStringContainsString( $expected, $head );
	}

	/**
	 * The one-time htaccess cleanup is guarded so it runs at most once per site.
	 *
	 * @return void
	 */
	public function test_legacy_htaccess_cleanup_is_guarded() {
		$manager = new CloudflareFeaturesManager();

		$this->assertFalse( (bool) get_option( 'nfd_cf_opt_cookie_htaccess_cleaned' ) );

		$manager->maybe_remove_legacy_htaccess_fragment();

		$this->assertTrue( (bool) get_option( 'nfd_cf_opt_cookie_htaccess_cleaned' ), 'Cleanup should set its guard flag' );
	}
}
