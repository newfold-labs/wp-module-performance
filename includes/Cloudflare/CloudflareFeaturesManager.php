<?php
/**
 * CloudflareFeaturesManager
 *
 * Tracks Cloudflare-related optimization toggles (Polish, Mirage, Fonts) and
 * advertises the active set to Cloudflare via a front-end cookie.
 *
 * The cookie is written client-side (JavaScript) rather than through an
 * `.htaccess` `Set-Cookie` response header. A `Set-Cookie` on a front-end
 * response makes that response uncacheable to nginx+ and Cloudflare, and the
 * old rule emitted it on every cookieless request — so only visitors that
 * already held the cookie could ever populate the shared cache. Setting the
 * cookie from JS keeps every HTML response cacheable while still presenting the
 * cookie on subsequent requests, which is all the Cloudflare edge rules key on.
 *
 * @package NewfoldLabs\WP\Module\Performance\Cloudflare
 * @since 1.0.0
 */

namespace NewfoldLabs\WP\Module\Performance\Cloudflare;

use NewfoldLabs\WP\Module\Performance\Fonts\FontSettings;
use NewfoldLabs\WP\Module\Performance\Images\ImageSettings;
use NewfoldLabs\WP\Module\Htaccess\Api as HtaccessApi;

/**
 * Handles detection and tracking of Cloudflare Polish, Mirage, and Font Optimization.
 *
 * @since 1.0.0
 */
class CloudflareFeaturesManager {

	/**
	 * Cookie name the Cloudflare edge rules look for.
	 *
	 * @var string
	 */
	private const COOKIE_NAME = 'nfd-enable-cf-opt';

	/**
	 * Cookie lifetime in seconds (24 hours).
	 *
	 * @var int
	 */
	private const COOKIE_TTL = 86400;

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
	 *
	 * @param mixed $container Optional DI container retained for backwards compatibility.
	 */
	public function __construct( $container = null ) {
		// Set the Cloudflare optimization cookie from the front end, client-side,
		// so HTML responses stay cacheable.
		add_action( 'wp_head', array( $this, 'print_cookie_script' ), 0 );

		// Keep image/font settings in sync when site capabilities change.
		add_action( 'set_transient_nfd_site_capabilities', array( $this, 'on_site_capabilities_change' ), 10, 2 );

		// One-time removal of the obsolete Set-Cookie `.htaccess` block.
		add_action( 'init', array( $this, 'maybe_remove_legacy_htaccess_fragment' ) );
	}

	/**
	 * Callback for when the `nfd_site_capabilities` transient is set.
	 *
	 * Triggers a refresh of image and font optimization settings based on updated
	 * site capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value The value being set in the transient.
	 * @return void
	 */
	public function on_site_capabilities_change( $value ): void {
		if ( is_array( $value ) ) {
			ImageSettings::maybe_refresh_with_capabilities( $value );
			FontSettings::maybe_refresh_with_capabilities( $value );
		}
	}

	/**
	 * Compute the deterministic cookie value for the currently-enabled CF features.
	 *
	 * Mirrors the historical encoding exactly — concatenated 8-char sha1 prefixes
	 * for mirage, polish, and fonts in that order — so the value the Cloudflare
	 * edge rules already key on is unchanged. Returns an empty string when no
	 * features are enabled.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function get_cookie_value(): string {
		$image_settings = get_option( 'nfd_image_optimization', array() );
		$fonts_settings = get_option( 'nfd_fonts_optimization', array() );

		$images_cloudflare = isset( $image_settings['cloudflare'] ) ? (array) $image_settings['cloudflare'] : array();
		$fonts_cloudflare  = isset( $fonts_settings['cloudflare'] ) ? (array) $fonts_settings['cloudflare'] : array();

		$mirage_enabled = ! empty( $images_cloudflare['mirage']['value'] );
		$polish_enabled = ! empty( $images_cloudflare['polish']['value'] );
		$fonts_enabled  = ! empty( $fonts_cloudflare['fonts']['value'] );

		$mirage_hash = $mirage_enabled ? substr( sha1( 'mirage' ), 0, 8 ) : '';
		$polish_hash = $polish_enabled ? substr( sha1( 'polish' ), 0, 8 ) : '';
		$fonts_hash  = $fonts_enabled ? substr( sha1( 'fonts' ), 0, 8 ) : '';

		return "{$mirage_hash}{$polish_hash}{$fonts_hash}";
	}

	/**
	 * Print a tiny inline script that sets the CF optimization cookie client-side.
	 *
	 * Hooked early on `wp_head` (front end only) so the cookie is set before the
	 * parser reaches most sub-resource requests. When no CF features are enabled
	 * nothing is printed and any existing cookie simply expires.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function print_cookie_script(): void {
		$value = $this->get_cookie_value();

		if ( '' === $value ) {
			return;
		}

		$script = sprintf(
			"(function(){var n=%s,v=%s;if(document.cookie.indexOf(n+'='+v)===-1){document.cookie=n+'='+v+'; path=/; max-age=%d; SameSite=Lax';}})();",
			wp_json_encode( self::COOKIE_NAME ),
			wp_json_encode( $value ),
			self::COOKIE_TTL
		);

		if ( function_exists( 'wp_print_inline_script_tag' ) ) {
			wp_print_inline_script_tag( $script );
			return;
		}

		echo '<script>' . $script . '</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value is a sha1 hex string encoded via wp_json_encode.
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
