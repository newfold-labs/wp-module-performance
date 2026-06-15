<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Cloudflare\CloudflareFeaturesManager;
use NewfoldLabs\WP\Module\Htaccess\Api as HtaccessApi;

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

	/** Marker label printed in the legacy block's BEGIN/END comments. */
	private const LEGACY_MARKER = 'Newfold CF Optimization Header';

	/** Flag recording the cleanup has run. */
	private const CLEANUP_FLAG = 'nfd_cf_opt_cookie_htaccess_cleaned';

	/** Option where the htaccess module persists its composed state. */
	private const HTACCESS_STATE_OPTION = 'nfd_module_htaccess_saved_state';

	/**
	 * Reset options, the cleanup flag, and any leaked htaccess registry state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( 'nfd_image_optimization' );
		delete_option( 'nfd_fonts_optimization' );
		delete_option( self::CLEANUP_FLAG );
		delete_option( self::HTACCESS_STATE_OPTION );
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
	 * Older installs persist the block as a state entry keyed by the fragment ID. The
	 * cleanup must remove that entry and the matching body region while leaving every
	 * other block entry untouched. Guards against the FRAGMENT_ID drifting.
	 *
	 * @return void
	 */
	public function test_cleanup_removes_block_entry_keyed_by_id() {
		$legacy_block = '# BEGIN ' . self::LEGACY_MARKER . "\n# END " . self::LEGACY_MARKER;
		$other_block  = "# BEGIN Newfold Browser Cache\nExpiresActive On\n# END Newfold Browser Cache";

		update_option(
			self::HTACCESS_STATE_OPTION,
			array(
				'blocks' => array(
					self::LEGACY_FRAGMENT_ID => array(
						'body'     => $legacy_block,
						'priority' => 40,
					),
					'nfd.browser.cache'      => array(
						'body'     => $other_block,
						'priority' => 20,
					),
				),
				'body'   => $other_block . "\n\n" . $legacy_block,
			)
		);

		( new CloudflareFeaturesManager() )->maybe_remove_legacy_htaccess_fragment();

		$state = get_option( self::HTACCESS_STATE_OPTION );

		$this->assertArrayNotHasKey( self::LEGACY_FRAGMENT_ID, $state['blocks'], 'Legacy block entry must be removed' );
		$this->assertArrayHasKey( 'nfd.browser.cache', $state['blocks'], 'Other block entries must be preserved' );
		$this->assertStringNotContainsString( self::LEGACY_MARKER, $state['body'], 'Legacy block must be gone from the body' );
		$this->assertTrue( (bool) get_option( self::CLEANUP_FLAG ), 'Cleanup should record that it ran' );
	}

	/**
	 * The real production case: on installs migrated to the htaccess "managed marker
	 * block" format the legacy block lives in the persisted state BODY, with no
	 * fragment keyed by ID. Unregister-by-ID is a no-op there, so the cleanup must
	 * strip the block straight from the persisted body — and must not touch any other
	 * managed block. This is the gap the original ID-only cleanup silently failed on.
	 *
	 * @return void
	 */
	public function test_cleanup_strips_block_baked_into_persisted_body() {
		$legacy_block = '# BEGIN ' . self::LEGACY_MARKER . "\n"
			. "<IfModule mod_headers.c>\n"
			. "\tHeader set Set-Cookie \"nfd-enable-cf-opt=" . self::VALUE_ALL . "; path=/; Max-Age=86400; HttpOnly\"\n"
			. "</IfModule>\n"
			. '# END ' . self::LEGACY_MARKER;
		$other_block  = "# BEGIN Newfold Browser Cache\nExpiresActive On\n# END Newfold Browser Cache";

		update_option(
			self::HTACCESS_STATE_OPTION,
			array(
				// Note: NO entry keyed by the legacy fragment ID — only the body holds it.
				'blocks'   => array(
					'nfd.browser.cache' => array(
						'body'     => $other_block,
						'priority' => 20,
					),
				),
				'body'     => $other_block . "\n\n" . $legacy_block,
				'checksum' => 'stale',
				'host'     => 'example.test',
				'version'  => '1.0.1',
			)
		);

		( new CloudflareFeaturesManager() )->maybe_remove_legacy_htaccess_fragment();

		$state = get_option( self::HTACCESS_STATE_OPTION );

		$this->assertStringNotContainsString(
			self::LEGACY_MARKER,
			$state['body'],
			'Legacy block must be stripped from the persisted body'
		);
		$this->assertStringContainsString(
			'# BEGIN Newfold Browser Cache',
			$state['body'],
			'Unrelated managed blocks must be preserved'
		);
		$this->assertSame(
			hash( 'sha256', $state['body'] ),
			$state['checksum'],
			'Checksum must be recomputed for the cleaned body'
		);
		$this->assertTrue( (bool) get_option( self::CLEANUP_FLAG ), 'Cleanup should record that it ran' );
	}

	/**
	 * Run-once guarantee: the cleanup must mark itself done after a single pass and
	 * never re-run on subsequent requests, even for an unexpected/unremovable state.
	 * A malformed block (BEGIN with no matching END) must NOT cause an endless
	 * per-request retry loop.
	 *
	 * @return void
	 */
	public function test_cleanup_runs_only_once_even_when_unremovable() {
		$malformed = '# BEGIN ' . self::LEGACY_MARKER . "\nHeader set X 1\n";
		update_option(
			self::HTACCESS_STATE_OPTION,
			array(
				'blocks' => array(),
				'body'   => $malformed,
			)
		);

		$manager = new CloudflareFeaturesManager();
		$manager->maybe_remove_legacy_htaccess_fragment();

		$this->assertTrue(
			(bool) get_option( self::CLEANUP_FLAG ),
			'Cleanup must record completion after one pass so it cannot loop forever'
		);

		// A second invocation is a guarded no-op: it must not modify the state again.
		$after_first = get_option( self::HTACCESS_STATE_OPTION );
		$manager->maybe_remove_legacy_htaccess_fragment();
		$this->assertSame(
			$after_first,
			get_option( self::HTACCESS_STATE_OPTION ),
			'A second run must be a no-op once the flag is set'
		);
	}

	/**
	 * Blast-radius guard: the cleanup must preserve every other managed block verbatim
	 * — WordPress core rules, browser-cache headers, skip-404, etc. Only the CF
	 * optimization block may change.
	 *
	 * @return void
	 */
	public function test_cleanup_preserves_all_other_managed_rules() {
		$wp_core       = "# BEGIN WordPress\nRewriteRule . /index.php [L]\n# END WordPress";
		$browser_cache = "# BEGIN Newfold Browser Cache\nExpiresByType image/webp \"access plus 1 year\"\n# END Newfold Browser Cache";
		$skip_404      = "# BEGIN Newfold Skip 404\nRewriteRule \\.(?:js|css)$ - [L]\n# END Newfold Skip 404";
		$legacy_block  = '# BEGIN ' . self::LEGACY_MARKER . "\n<IfModule mod_headers.c>\nHeader set Set-Cookie \"nfd-enable-cf-opt=" . self::VALUE_ALL . "\"\n</IfModule>\n# END " . self::LEGACY_MARKER;

		$body = $wp_core . "\n\n" . $browser_cache . "\n\n" . $legacy_block . "\n\n" . $skip_404;

		update_option(
			self::HTACCESS_STATE_OPTION,
			array(
				'blocks' => array(
					'WordPress.core'    => array(
						'body'     => $wp_core,
						'priority' => 0,
					),
					'nfd.browser.cache' => array(
						'body'     => $browser_cache,
						'priority' => 10,
					),
					'nfd.skip404'       => array(
						'body'     => $skip_404,
						'priority' => 30,
					),
				),
				'body'   => $body,
			)
		);

		( new CloudflareFeaturesManager() )->maybe_remove_legacy_htaccess_fragment();

		$state = get_option( self::HTACCESS_STATE_OPTION );

		// Only the CF block disappears.
		$this->assertStringNotContainsString( self::LEGACY_MARKER, $state['body'], 'CF block must be removed' );

		foreach ( array( $wp_core, $browser_cache, $skip_404 ) as $preserved ) {
			$this->assertStringContainsString( $preserved, $state['body'], 'Every other block must be preserved byte-for-byte' );
		}

		// No block entry other than the CF one may be added or removed.
		$this->assertSame(
			array( 'WordPress.core', 'nfd.browser.cache', 'nfd.skip404' ),
			array_keys( $state['blocks'] ),
			'Other block entries must be left exactly as they were'
		);
	}

	/**
	 * Once the cleanup has run (flag set), it does not run again — a block left in the
	 * state afterwards is untouched.
	 *
	 * @return void
	 */
	public function test_cleanup_is_guarded_against_rerunning() {
		update_option( self::CLEANUP_FLAG, true, false );
		$legacy_block = '# BEGIN ' . self::LEGACY_MARKER . "\n# END " . self::LEGACY_MARKER;
		update_option(
			self::HTACCESS_STATE_OPTION,
			array(
				'blocks' => array(
					self::LEGACY_FRAGMENT_ID => array(
						'body'     => $legacy_block,
						'priority' => 40,
					),
				),
				'body'   => $legacy_block,
			)
		);

		( new CloudflareFeaturesManager() )->maybe_remove_legacy_htaccess_fragment();

		$state = get_option( self::HTACCESS_STATE_OPTION );
		$this->assertArrayHasKey(
			self::LEGACY_FRAGMENT_ID,
			$state['blocks'],
			'A second cleanup run must be a no-op once the guard flag is set'
		);
	}
}
