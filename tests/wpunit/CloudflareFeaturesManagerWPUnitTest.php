<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Cloudflare\CloudflareFeaturesManager;
use NewfoldLabs\WP\Module\Htaccess\Api as HtaccessApi;
use NewfoldLabs\WP\Module\Htaccess\Fragment;

/**
 * CloudflareFeaturesManager wpunit tests.
 *
 * The manager advertises which Cloudflare optimizations (Mirage/Polish/Fonts)
 * are active by setting the `nfd-enable-cf-opt` cookie. The load-bearing
 * properties these tests guard:
 *
 *  1. The cookie is set client-side (inline script) and the response carries NO
 *     Set-Cookie header for it — a Set-Cookie makes the response uncacheable to
 *     nginx+/Cloudflare, which is the bug this code replaced.
 *  2. The cookie VALUE is a stable contract the Cloudflare edge rules key on. It
 *     must remain the exact deterministic encoding of the enabled feature set
 *     (mirage|polish|fonts), including the production value when all three are on.
 *  3. The obsolete Set-Cookie `.htaccess` block is removed from existing installs
 *     exactly once.
 *
 * The feature-hash literals below are the values Cloudflare is configured to
 * recognise (also mirrored in tests/playwright/helpers CLOUDFLARE_HASHES). They
 * are asserted as literals on purpose: re-deriving them with substr(sha1()) here
 * would just mirror the implementation and could not catch a value change that
 * silently breaks the Cloudflare contract.
 *
 * @coversDefaultClass \NewfoldLabs\WP\Module\Performance\Cloudflare\CloudflareFeaturesManager
 */
class CloudflareFeaturesManagerWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/**
	 * Cloudflare-recognised feature hashes (the edge contract).
	 */
	private const HASH_MIRAGE = '63a6825d';
	private const HASH_POLISH = '27cab0f2';
	private const HASH_FONTS  = '04d3b602';

	/** Value seen in production when every optimization is enabled. */
	private const VALUE_ALL = '63a6825d27cab0f204d3b602';

	/** Legacy htaccess fragment ID the cleanup must remove. */
	private const LEGACY_FRAGMENT_ID = 'nfd.cloudflare.optimization.header';

	/**
	 * Reset options, the cleanup flag, and any leaked htaccess registry state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'nfd_image_optimization' );
		delete_option( 'nfd_fonts_optimization' );
		delete_option( 'nfd_cf_opt_cookie_htaccess_cleaned' );
		if ( class_exists( HtaccessApi::class ) ) {
			HtaccessApi::registry()->unregister( self::LEGACY_FRAGMENT_ID );
		}
		parent::tearDown();
	}

	/**
	 * Set the Cloudflare image optimizations (mirage / polish).
	 *
	 * @param bool $mirage Enable mirage.
	 * @param bool $polish Enable polish.
	 * @return void
	 */
	private function set_image_optimizations( bool $mirage, bool $polish ): void {
		update_option(
			'nfd_image_optimization',
			array(
				'enabled'    => true,
				'cloudflare' => array(
					'mirage' => array( 'value' => $mirage ),
					'polish' => array( 'value' => $polish ),
				),
			)
		);
	}

	/**
	 * Set the Cloudflare fonts optimization.
	 *
	 * @param bool $fonts Enable fonts.
	 * @return void
	 */
	private function set_fonts_optimization( bool $fonts ): void {
		update_option(
			'nfd_fonts_optimization',
			array(
				'cloudflare' => array(
					'fonts' => array( 'value' => $fonts ),
				),
			)
		);
	}

	/**
	 * Render the markup printed on the wp_head hook.
	 *
	 * @return string
	 */
	private function render_wp_head(): string {
		ob_start();
		do_action( 'wp_head' );
		return (string) ob_get_clean();
	}

	/**
	 * With a feature enabled, the cookie is set client-side via an inline script.
	 *
	 * @return void
	 */
	public function test_sets_cookie_via_inline_script_when_enabled() {
		$this->set_image_optimizations( true, false );
		new CloudflareFeaturesManager();

		$head = $this->render_wp_head();

		$this->assertStringContainsString( 'document.cookie', $head, 'Cookie should be set via JavaScript' );
		$this->assertStringContainsString( 'nfd-enable-cf-opt', $head, 'Cookie name should be present' );
	}

	/**
	 * The response must never carry a Set-Cookie header for this cookie — that is
	 * exactly what made responses uncacheable to nginx+/Cloudflare.
	 *
	 * @return void
	 */
	public function test_response_carries_no_set_cookie_header() {
		$this->set_image_optimizations( true, true );
		new CloudflareFeaturesManager();

		$this->render_wp_head();

		foreach ( headers_list() as $header ) {
			$this->assertStringStartsNotWith(
				'Set-Cookie: nfd-enable-cf-opt',
				$header,
				'The optimization cookie must not be emitted as a response header'
			);
		}
	}

	/**
	 * When every optimization is enabled, the value equals the exact production
	 * cookie value the Cloudflare edge rules key on. This is the contract guard:
	 * change the encoding and Cloudflare silently stops optimizing.
	 *
	 * @return void
	 */
	public function test_cookie_value_matches_production_when_all_enabled() {
		$this->set_image_optimizations( true, true );
		$this->set_fonts_optimization( true );
		new CloudflareFeaturesManager();

		$head = $this->render_wp_head();

		$this->assertStringContainsString( self::VALUE_ALL, $head, 'All-features value must match the production cookie value' );
	}

	/**
	 * The value encodes only the enabled features, in mirage|polish|fonts order,
	 * and never leaks a hash for a disabled feature.
	 *
	 * @return void
	 */
	public function test_value_encodes_only_enabled_features() {
		$manager = new CloudflareFeaturesManager();

		$cases = array(
			// mirage, polish, fonts, expected substring, hashes that must be absent.
			array( true, false, false, self::HASH_MIRAGE, array( self::HASH_POLISH, self::HASH_FONTS ) ),
			array( false, true, false, self::HASH_POLISH, array( self::HASH_MIRAGE, self::HASH_FONTS ) ),
			array( false, false, true, self::HASH_FONTS, array( self::HASH_MIRAGE, self::HASH_POLISH ) ),
			array( true, true, false, self::HASH_MIRAGE . self::HASH_POLISH, array( self::HASH_FONTS ) ),
		);

		foreach ( $cases as $case ) {
			list( $mirage, $polish, $fonts, $expected, $absent ) = $case;
			$this->set_image_optimizations( $mirage, $polish );
			$this->set_fonts_optimization( $fonts );

			$head = $this->render_wp_head();

			$label = "mirage={$mirage} polish={$polish} fonts={$fonts}";
			$this->assertStringContainsString( $expected, $head, "Expected value for {$label}" );
			foreach ( $absent as $absent_hash ) {
				$this->assertStringNotContainsString( $absent_hash, $head, "Disabled feature hash {$absent_hash} must not leak for {$label}" );
			}
		}

		// Keep the analyzer aware the constructed manager is the system under test.
		$this->assertInstanceOf( CloudflareFeaturesManager::class, $manager );
	}

	/**
	 * The cookie carries the expected scope/lifetime directives and is written so
	 * it does not overwrite an already-present cookie.
	 *
	 * @return void
	 */
	public function test_cookie_script_sets_scope_lifetime_and_avoids_overwrite() {
		$this->set_image_optimizations( true, false );
		new CloudflareFeaturesManager();

		$head = $this->render_wp_head();

		$this->assertStringContainsString( 'path=/', $head, 'Cookie should be site-wide' );
		$this->assertStringContainsString( 'max-age=86400', $head, 'Cookie should live 24h' );
		$this->assertStringContainsString( 'SameSite=Lax', $head, 'Cookie should set SameSite=Lax' );
		$this->assertStringContainsString( 'indexOf', $head, 'Script should skip resetting an existing cookie' );
	}

	/**
	 * With no Cloudflare features enabled, nothing is printed.
	 *
	 * @return void
	 */
	public function test_prints_nothing_when_no_features_enabled() {
		new CloudflareFeaturesManager();

		$head = $this->render_wp_head();

		$this->assertStringNotContainsString( 'nfd-enable-cf-opt', $head );
	}

	/**
	 * Partial / malformed settings without a cloudflare key emit no cookie and do
	 * not error (options can be saved in many shapes by other code paths).
	 *
	 * @return void
	 */
	public function test_prints_nothing_for_settings_without_cloudflare_key() {
		update_option( 'nfd_image_optimization', array( 'enabled' => true ) );
		new CloudflareFeaturesManager();

		$head = $this->render_wp_head();

		$this->assertStringNotContainsString( 'nfd-enable-cf-opt', $head );
	}

	/**
	 * The cookie script is wired to the front-end wp_head hook (not an admin or
	 * REST hook), so it only runs while rendering front-end pages.
	 *
	 * @return void
	 */
	public function test_cookie_script_is_hooked_to_wp_head() {
		$manager = new CloudflareFeaturesManager();

		$this->assertNotFalse(
			has_action( 'wp_head', array( $manager, 'print_cookie_script' ) ),
			'print_cookie_script should be hooked to wp_head'
		);
	}

	/**
	 * The one-time cleanup removes an actually-registered legacy fragment from the
	 * htaccess registry (the encoder side — not just a flag flip) and records that
	 * it ran. Guards against the FRAGMENT_ID drifting or the unregister call being
	 * dropped, either of which would strand the Set-Cookie block on live sites.
	 *
	 * @return void
	 */
	public function test_cleanup_removes_registered_legacy_fragment() {
		HtaccessApi::registry()->register( $this->make_legacy_fragment() );
		$this->assertContains(
			self::LEGACY_FRAGMENT_ID,
			HtaccessApi::registry()->ids(),
			'Precondition: legacy fragment is registered'
		);

		( new CloudflareFeaturesManager() )->maybe_remove_legacy_htaccess_fragment();

		$this->assertNotContains(
			self::LEGACY_FRAGMENT_ID,
			HtaccessApi::registry()->ids(),
			'Cleanup should unregister the legacy fragment'
		);
		$this->assertTrue( (bool) get_option( 'nfd_cf_opt_cookie_htaccess_cleaned' ), 'Cleanup should record that it ran' );
	}

	/**
	 * Once the cleanup has run (flag set), it does not run again — a fragment
	 * registered afterwards is left untouched.
	 *
	 * @return void
	 */
	public function test_cleanup_is_guarded_against_rerunning() {
		update_option( 'nfd_cf_opt_cookie_htaccess_cleaned', true, false );
		HtaccessApi::registry()->register( $this->make_legacy_fragment() );

		( new CloudflareFeaturesManager() )->maybe_remove_legacy_htaccess_fragment();

		$this->assertContains(
			self::LEGACY_FRAGMENT_ID,
			HtaccessApi::registry()->ids(),
			'A second cleanup run must be a no-op once the guard flag is set'
		);
	}

	/**
	 * Build a minimal htaccess Fragment under the legacy ID for cleanup tests.
	 *
	 * @return Fragment
	 */
	private function make_legacy_fragment(): Fragment {
		return new class() implements Fragment {
			/**
			 * Fragment ID (matches the legacy CF optimization fragment).
			 *
			 * @return string
			 */
			public function id() {
				return 'nfd.cloudflare.optimization.header';
			}
			/**
			 * Render priority.
			 *
			 * @return int
			 */
			public function priority() {
				return self::PRIORITY_POST_WP;
			}
			/**
			 * Whether only one instance may render.
			 *
			 * @return bool
			 */
			public function exclusive() {
				return true;
			}
			/**
			 * Whether the fragment is enabled.
			 *
			 * @param mixed $context Context snapshot (unused).
			 * @return bool
			 */
			public function is_enabled( $context ) {
				return true;
			}
			/**
			 * Render the fragment body.
			 *
			 * @param mixed $context Context snapshot (unused).
			 * @return string
			 */
			public function render( $context ) {
				return "# BEGIN Newfold CF Optimization Header\n# END Newfold CF Optimization Header";
			}
			/**
			 * Optional regex patches (none).
			 *
			 * @param mixed $context Context snapshot (unused).
			 * @return array
			 */
			public function patches( $context ) {
				return array();
			}
		};
	}
}
