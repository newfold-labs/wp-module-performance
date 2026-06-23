<?php
/**
 * LegacyCookieCleanup
 *
 * One-time removal of the obsolete Cloudflare optimization `Set-Cookie` block from
 * `.htaccess`.
 *
 * The performance module used to advertise which Cloudflare optimizations
 * (Mirage, Polish, Fonts) were active by setting the `nfd-enable-cf-opt` cookie —
 * first via a `Set-Cookie` `.htaccess` rule, later client-side from JavaScript. A
 * `Set-Cookie` on a front-end response makes that response uncacheable to nginx+
 * and Cloudflare, and the old rule emitted it on every cookieless request, so only
 * visitors that already held the cookie could ever populate the shared cache.
 *
 * The whole cookie mechanism has now been removed — the Cloudflare optimizations
 * are enabled at the zone level instead, so no cookie is needed. This class is all
 * that remains: a guarded, run-once cleanup that strips the legacy `Set-Cookie`
 * block from sites that still carry it (installs upgrading straight from a version
 * before the block was first removed).
 *
 * @package NewfoldLabs\WP\Module\Performance\Cloudflare
 * @since 1.0.0
 */

namespace NewfoldLabs\WP\Module\Performance\Cloudflare;

use NewfoldLabs\WP\Module\Htaccess\Api as HtaccessApi;

/**
 * Removes the obsolete Cloudflare optimization `Set-Cookie` `.htaccess` block.
 *
 * @since 1.0.0
 */
class LegacyCookieCleanup {

	/**
	 * Identifier the legacy Cloudflare optimization block is persisted under.
	 *
	 * Used only to locate and remove that one block from the htaccess module's saved
	 * state. No fragment is ever registered under this ID again.
	 *
	 * @var string
	 */
	private const FRAGMENT_ID = 'nfd.cloudflare.optimization.header';

	/**
	 * Marker label printed in the BEGIN/END comments of the legacy `.htaccess` block.
	 *
	 * On installs migrated to the htaccess "managed marker block" format the block is
	 * baked into the persisted body and is identifiable only by this label (not by the
	 * fragment ID), so the cleanup matches on it directly.
	 *
	 * @var string
	 */
	private const MARKER = 'Newfold CF Optimization Header';

	/**
	 * Option name where the htaccess module persists its composed state.
	 *
	 * Read/written directly (not through the htaccess code) so the cleanup can strip
	 * the legacy block from the persisted body without any change to the htaccess
	 * module. This is the documented option name from that module's Options map.
	 *
	 * @var string
	 */
	private const HTACCESS_STATE_OPTION = 'nfd_module_htaccess_saved_state';

	/**
	 * Option flag recording that the legacy `.htaccess` block has been removed.
	 *
	 * @var string
	 */
	private const CLEANUP_FLAG = 'nfd_cf_opt_cookie_htaccess_cleaned';

	/**
	 * Constructor to register hooks.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// One-time removal of the obsolete Set-Cookie `.htaccess` block.
		add_action( 'init', array( $this, 'maybe_remove_legacy_htaccess_fragment' ) );
	}

	/**
	 * Remove the obsolete Set-Cookie `.htaccess` block from sites that still have it.
	 *
	 * Runs EXACTLY ONCE per site (guarded by an option flag set up front, so it can
	 * never run on more than one request — no per-request retry loop). This touches
	 * ONLY the Cloudflare optimization block: it removes the single state block keyed
	 * by {@see self::FRAGMENT_ID} and the one `# BEGIN/END {@see self::MARKER}` region
	 * from the persisted body, leaving the rest of the state byte-for-byte intact. It
	 * deliberately does NOT call the htaccess unregister API, which would recompose the
	 * whole body from block entries; everything else any module wrote is preserved.
	 *
	 * The persisted state is read directly from its option, which is always available
	 * on `init` regardless of whether the htaccess Manager has booted — so a single
	 * pass is authoritative and there is no boot-timing race that would justify
	 * retrying.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_remove_legacy_htaccess_fragment(): void {
		if ( $this->is_cleanup_done() ) {
			return;
		}

		// Record completion immediately so this runs once and only once per site, even
		// if the state is a no-op (clean site) or in an unexpected shape. The state is
		// read directly from its option below, so one pass is authoritative.
		$this->set_cleanup_done();

		// Surgically remove ONLY the CF optimization block from the persisted state.
		// apply reuses the persisted body verbatim, so every other block is kept.
		if ( $this->strip_legacy_block_from_state() && class_exists( HtaccessApi::class ) ) {
			HtaccessApi::queue_apply( 'nfd-cf-opt-legacy-cleanup' );
		}
	}

	/**
	 * Strip the legacy CF optimization block from the htaccess module's saved state.
	 *
	 * Removes both a fragment entry keyed by {@see self::FRAGMENT_ID} and the block
	 * text from the composed body, then persists the change. The body is what the
	 * htaccess module writes to disk on its next apply, and clearing it also stops the
	 * block being re-detected as a legacy marker label on subsequent applies.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if the state was modified.
	 */
	private function strip_legacy_block_from_state(): bool {
		$state = $this->get_htaccess_state();

		if ( ! is_array( $state ) ) {
			return false;
		}

		$changed = false;

		if ( isset( $state['blocks'] ) && is_array( $state['blocks'] ) && array_key_exists( self::FRAGMENT_ID, $state['blocks'] ) ) {
			unset( $state['blocks'][ self::FRAGMENT_ID ] );
			$changed = true;
		}

		if ( isset( $state['body'] ) && is_string( $state['body'] ) && false !== strpos( $state['body'], '# BEGIN ' . self::MARKER ) ) {
			$clean = $this->remove_marker_block( $state['body'], self::MARKER );
			if ( $clean !== $state['body'] ) {
				$state['body']     = $clean;
				$state['checksum'] = hash( 'sha256', $clean );
				$changed           = true;
			}
		}

		if ( ! $changed ) {
			return false;
		}

		$this->update_htaccess_state( $state );

		return true;
	}

	/**
	 * Remove a `# BEGIN <marker> ... # END <marker>` block from htaccess body text.
	 *
	 * Tolerant of leading whitespace and CR/LF variants; collapses the blank lines
	 * left behind so the remaining body stays well formed.
	 *
	 * @since 1.0.0
	 *
	 * @param string $body   The htaccess body text.
	 * @param string $marker The marker label to remove.
	 * @return string
	 */
	private function remove_marker_block( string $body, string $marker ): string {
		$quoted  = preg_quote( $marker, '/' );
		$pattern = '/(?:\r\n|\r|\n)*[ \t]*#[ \t]*BEGIN[ \t]+' . $quoted . '\b.*?#[ \t]*END[ \t]+' . $quoted . '[^\r\n]*/s';

		$count   = 0;
		$cleaned = preg_replace( $pattern, '', $body, -1, $count );

		// Only treat the body as changed when a complete BEGIN..END block was actually
		// removed. A partial/malformed marker leaves the body untouched so the caller
		// does not write or trigger a rewrite for a no-op.
		if ( null === $cleaned || 0 === $count ) {
			return $body;
		}

		$cleaned = preg_replace( '/(?:\r\n|\r|\n){3,}/', "\n\n", $cleaned );

		return trim( (string) $cleaned, "\r\n" );
	}

	/**
	 * Read the htaccess module's persisted state from its option.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	private function get_htaccess_state(): array {
		$state = is_multisite()
			? get_site_option( self::HTACCESS_STATE_OPTION, array() )
			: get_option( self::HTACCESS_STATE_OPTION, array() );

		return is_array( $state ) ? $state : array();
	}

	/**
	 * Persist the htaccess module's state back to its option.
	 *
	 * @since 1.0.0
	 *
	 * @param array $state State to persist.
	 * @return void
	 */
	private function update_htaccess_state( array $state ): void {
		if ( is_multisite() ) {
			update_site_option( self::HTACCESS_STATE_OPTION, $state );
			return;
		}

		update_option( self::HTACCESS_STATE_OPTION, $state );
	}

	/**
	 * Whether the one-time cleanup has already completed.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function is_cleanup_done(): bool {
		return (bool) ( is_multisite()
			? get_site_option( self::CLEANUP_FLAG )
			: get_option( self::CLEANUP_FLAG ) );
	}

	/**
	 * Record that the one-time cleanup has completed.
	 *
	 * Stored autoloaded so the guard read on every `init` costs no extra query once
	 * the flag is set.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function set_cleanup_done(): void {
		if ( is_multisite() ) {
			update_site_option( self::CLEANUP_FLAG, true );
			return;
		}

		update_option( self::CLEANUP_FLAG, true, true );
	}
}
