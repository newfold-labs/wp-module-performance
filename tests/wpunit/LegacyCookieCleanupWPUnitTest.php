<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Cloudflare\LegacyCookieCleanup;
use NewfoldLabs\WP\Module\Htaccess\Api as HtaccessApi;

/**
 * LegacyCookieCleanup wpunit tests.
 *
 * The Cloudflare optimization cookie feature has been removed. All that remains is
 * a one-time cleanup that strips the obsolete `Set-Cookie` `.htaccess` block from
 * installs that still carry it. The load-bearing properties these tests guard:
 *
 *  1. The cleanup removes ONLY the Cloudflare optimization block — by fragment ID
 *     and by marker label baked into the persisted body — and leaves every other
 *     managed block byte-for-byte intact.
 *  2. It runs EXACTLY ONCE per site and can never loop, even on malformed state.
 *
 * @coversDefaultClass \NewfoldLabs\WP\Module\Performance\Cloudflare\LegacyCookieCleanup
 */
class LegacyCookieCleanupWPUnitTest extends \lucatume\WPBrowser\TestCase\WPTestCase {

	/** Legacy htaccess fragment ID the cleanup must remove. */
	private const LEGACY_FRAGMENT_ID = 'nfd.cloudflare.optimization.header';

	/** Marker label printed in the legacy block's BEGIN/END comments. */
	private const LEGACY_MARKER = 'Newfold CF Optimization Header';

	/** Value seen in production when every optimization was enabled. */
	private const VALUE_ALL = '63a6825d27cab0f204d3b602';

	/** Flag recording the cleanup has run. */
	private const CLEANUP_FLAG = 'nfd_cf_opt_cookie_htaccess_cleaned';

	/** Flag recording the orphaned-option cleanup has run. */
	private const OPTIONS_CLEANUP_FLAG = 'nfd_cf_opt_options_cleaned';

	/** Option where the htaccess module persists its composed state. */
	private const HTACCESS_STATE_OPTION = 'nfd_module_htaccess_saved_state';

	/**
	 * Reset the cleanup flag and any leaked htaccess registry state.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		delete_option( self::CLEANUP_FLAG );
		delete_option( self::OPTIONS_CLEANUP_FLAG );
		delete_option( self::HTACCESS_STATE_OPTION );
		delete_option( 'nfd_fonts_optimization' );
		delete_option( 'nfd_image_optimization' );
		if ( class_exists( HtaccessApi::class ) ) {
			HtaccessApi::registry()->unregister( self::LEGACY_FRAGMENT_ID );
		}
		parent::tearDown();
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

		( new LegacyCookieCleanup() )->maybe_remove_legacy_htaccess_fragment();

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

		( new LegacyCookieCleanup() )->maybe_remove_legacy_htaccess_fragment();

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

		$manager = new LegacyCookieCleanup();
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

		( new LegacyCookieCleanup() )->maybe_remove_legacy_htaccess_fragment();

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

		( new LegacyCookieCleanup() )->maybe_remove_legacy_htaccess_fragment();

		$state = get_option( self::HTACCESS_STATE_OPTION );
		$this->assertArrayHasKey(
			self::LEGACY_FRAGMENT_ID,
			$state['blocks'],
			'A second cleanup run must be a no-op once the guard flag is set'
		);
	}

	/**
	 * The fully-orphaned fonts option is deleted, and the stale cloudflare sub-key is
	 * stripped from the image option while every other image key is left intact.
	 *
	 * @return void
	 */
	public function test_orphaned_option_cleanup_removes_dead_cf_data() {
		update_option(
			'nfd_fonts_optimization',
			array( 'cloudflare' => array( 'fonts' => array( 'value' => true ) ) )
		);
		update_option(
			'nfd_image_optimization',
			array(
				'enabled'      => true,
				'lazy_loading' => array( 'enabled' => true ),
				'cloudflare'   => array(
					'polish' => array( 'value' => true ),
					'mirage' => array( 'value' => true ),
				),
			)
		);

		( new LegacyCookieCleanup() )->maybe_remove_orphaned_options();

		$this->assertFalse( get_option( 'nfd_fonts_optimization' ), 'Orphaned fonts option must be deleted' );

		$image = get_option( 'nfd_image_optimization' );
		$this->assertIsArray( $image );
		$this->assertArrayNotHasKey( 'cloudflare', $image, 'Stale cloudflare sub-key must be stripped' );
		$this->assertTrue( $image['enabled'], 'Other image keys must be preserved' );
		$this->assertSame( array( 'enabled' => true ), $image['lazy_loading'], 'Other image keys must be preserved verbatim' );
		$this->assertTrue( (bool) get_option( self::OPTIONS_CLEANUP_FLAG ), 'Cleanup should record that it ran' );
	}

	/**
	 * The image option is only written when it actually carries a cloudflare key —
	 * a clean install (no dead data) is a no-op beyond setting the guard.
	 *
	 * @return void
	 */
	public function test_orphaned_option_cleanup_is_noop_without_dead_data() {
		update_option( 'nfd_image_optimization', array( 'enabled' => true ) );

		( new LegacyCookieCleanup() )->maybe_remove_orphaned_options();

		$this->assertSame( array( 'enabled' => true ), get_option( 'nfd_image_optimization' ) );
		$this->assertFalse( get_option( 'nfd_fonts_optimization' ) );
		$this->assertTrue( (bool) get_option( self::OPTIONS_CLEANUP_FLAG ) );
	}

	/**
	 * The orphaned-option cleanup uses its OWN guard, independent of the htaccess flag —
	 * so installs that already ran the 3.8.0 htaccess cleanup (htaccess flag set) still
	 * get their orphaned options removed.
	 *
	 * @return void
	 */
	public function test_orphaned_option_cleanup_runs_even_when_htaccess_flag_already_set() {
		update_option( self::CLEANUP_FLAG, true, false ); // 3.8.0 htaccess cleanup already done.
		update_option( 'nfd_fonts_optimization', array( 'cloudflare' => array( 'fonts' => array( 'value' => true ) ) ) );

		( new LegacyCookieCleanup() )->maybe_remove_orphaned_options();

		$this->assertFalse( get_option( 'nfd_fonts_optimization' ), 'Option cleanup must not be gated by the htaccess flag' );
	}

	/**
	 * Once the option cleanup has run (its flag set), it does not run again.
	 *
	 * @return void
	 */
	public function test_orphaned_option_cleanup_is_guarded_against_rerunning() {
		update_option( self::OPTIONS_CLEANUP_FLAG, true, false );
		update_option( 'nfd_fonts_optimization', array( 'cloudflare' => array( 'fonts' => array( 'value' => true ) ) ) );

		( new LegacyCookieCleanup() )->maybe_remove_orphaned_options();

		$this->assertNotFalse(
			get_option( 'nfd_fonts_optimization' ),
			'A second option-cleanup run must be a no-op once its guard flag is set'
		);
	}
}
