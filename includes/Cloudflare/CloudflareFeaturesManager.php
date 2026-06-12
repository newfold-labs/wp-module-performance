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
	 * Identifier for the obsolete Cloudflare optimization header fragment.
	 *
	 * Retained only so the stale `.htaccess` block can be removed from sites that
	 * still have it. No fragment is ever registered under this ID again.
	 *
	 * @var string
	 */
	private const FRAGMENT_ID = 'nfd.cloudflare.optimization.header';

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
	 * Runs once per site (guarded by an option flag). Unregistering by ID updates
	 * the htaccess module's persisted state and removes the block on the next write,
	 * so the cleanup rolls out across existing installs without manual edits.
	 *
	 * The guard flag is only set after the unregister actually runs. If the htaccess
	 * module isn't loaded yet on this request, we leave the flag unset and retry on a
	 * later request rather than permanently skipping the cleanup.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function maybe_remove_legacy_htaccess_fragment(): void {
		if ( get_option( self::CLEANUP_FLAG ) ) {
			return;
		}

		if ( ! class_exists( HtaccessApi::class ) ) {
			return;
		}

		HtaccessApi::unregister( self::FRAGMENT_ID );

		update_option( self::CLEANUP_FLAG, true, false );
	}
}
