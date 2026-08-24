<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

use NewfoldLabs\WP\Module\Performance\OptionListener;
use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Performance\Cache\CacheExclusion;
use NewfoldLabs\WP\Module\Performance\Cache\CacheManager;
use NewfoldLabs\WP\Module\Htaccess\Api as HtaccessApi;
use NewfoldLabs\WP\Module\Performance\Cache\Types\Fragments\BrowserCacheFragment;

use function NewfoldLabs\WP\Module\Performance\get_cache_exclusion;
use function NewfoldLabs\WP\Module\Performance\get_cache_level;

/**
 * Browser cache type.
 *
 * Migrated to new Htaccess Fragment approach:
 *   - Writes are performed by registering/unregistering a fragment.
 *   - Content is rendered by the BrowserCacheFragment class.
 *
 * @package NewfoldLabs\WP\Module\Performance\Cache\Types
 * @since 1.0.0
 */
class Browser extends CacheBase {

	/**
	 * Human-friendly marker label used in BEGIN/END comments rendered
	 * by the fragment. Preserved for readability and parity.
	 *
	 * @var string
	 */
	const MARKER = 'Newfold Browser Cache';

	/**
	 * Registry identifier for this fragment.
	 * Must be globally unique across fragments.
	 *
	 * @var string
	 */
	const FRAGMENT_ID = 'nfd.cache.browser';

	/**
	 * Whether or not the code for this cache type should be loaded.
	 *
	 * @param Container $container Dependency injection container.
	 * @return bool
	 */
	public static function should_enable( Container $container ) {
		return (bool) $container->has( 'isApache' ) && $container->get( 'isApache' );
	}

	/**
	 * Constructor.
	 *
	 * Registers option listeners and filters that keep the fragment in sync
	 * with cache level and exclusion changes.
	 */
	public function __construct() {
		new OptionListener( CacheManager::OPTION_CACHE_LEVEL, array( __CLASS__, 'maybeAddRules' ) );
		new OptionListener( CacheExclusion::OPTION_CACHE_EXCLUSION, array( __CLASS__, 'exclusionChange' ) );

		add_filter( 'newfold_update_htaccess', array( $this, 'on_rewrite' ) );

		// Re-render on boot so the persisted block keeps up with the current code.
		//
		// admin_init rather than init: registering ends up requiring
		// wp-admin/includes/file.php, and on an admin request that file is only
		// loaded at global scope after init has run. Pulling it in earlier would
		// scope the globals it defines to the function that required it.
		add_action( 'admin_init', array( __CLASS__, 'maybe_bootstrap_register' ), 20 );

		// Cron never reaches admin_init, so it needs its own entry point.
		add_action( 'init', array( __CLASS__, 'maybe_bootstrap_register_on_cron' ), 5 );
	}

	/**
	 * Re-render on cron, where admin_init never fires.
	 *
	 * Sites nobody signs into still need to pick up a rule change, and the cron
	 * request is the only other context that can write safely. WP-CLI is left
	 * out on purpose: the writer resolves the .htaccess path from
	 * SCRIPT_FILENAME, which points at the wp binary there.
	 *
	 * @return void
	 */
	public static function maybe_bootstrap_register_on_cron() {
		if ( ! function_exists( 'wp_doing_cron' ) || ! wp_doing_cron() ) {
			return;
		}

		self::maybe_bootstrap_register();
	}

	/**
	 * Decide whether this request should re-render the fragment.
	 *
	 * Re-rendering costs a couple of option reads and a string compare, so it is
	 * kept off the hot paths. Front-end and REST requests never get here, and
	 * admin-ajax is skipped because heartbeat would otherwise run this every few
	 * seconds for no reason. An ordinary admin page load picks up any change.
	 *
	 * @return void
	 */
	public static function maybe_bootstrap_register() {
		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return;
		}

		// A network shares one .htaccess, but the cache level and the base path
		// baked into the rules are per site. Re-rendering here would make each
		// site rewrite the file with its own values and undo the last one, so
		// multisite keeps to the existing setting-change path.
		if ( is_multisite() ) {
			return;
		}

		self::bootstrap_register();
	}

	/**
	 * Re-render the fragment so the saved state matches what this version renders.
	 *
	 * Without this the block is only rebuilt when a setting changes or the plugin
	 * is activated, so a rule change shipped in an update never reaches sites that
	 * are already running. The same applies to the base path in the rules, which
	 * goes stale when home_url changes.
	 *
	 * Api::register only queues a write when the rendered body actually differs,
	 * so this costs nothing once a site is in sync.
	 *
	 * @return void
	 */
	public static function bootstrap_register() {
		$cache_level = get_cache_level();

		// Nothing to register when browser caching is off. Only registration
		// happens here: unregistering queues a write whether or not anything
		// changed, so removal stays with the option listeners.
		if ( absint( $cache_level ) < 1 ) {
			return;
		}

		self::addRules( $cache_level );
	}

	/**
	 * When updating .htaccess, also update our rules as appropriate.
	 *
	 * @return void
	 */
	public function on_rewrite() {
		self::maybeAddRules( get_cache_level() );
	}

	/**
	 * Handle exclusion option change: refresh the fragment.
	 *
	 * @return void
	 */
	public static function exclusionChange() {
		self::maybeAddRules( get_cache_level() );
	}

	/**
	 * Determine whether to add or remove rules based on caching level.
	 *
	 * @param int|null $cache_level The caching level.
	 * @return void
	 */
	public static function maybeAddRules( $cache_level ) {
		absint( $cache_level ) > 0 ? self::addRules( $cache_level ) : self::removeRules();
	}

	/**
	 * Remove our rules by unregistering the fragment.
	 *
	 * @return void
	 */
	public static function removeRules() {
		HtaccessApi::unregister( self::FRAGMENT_ID );
	}

	/**
	 * Add (or replace) our rules by registering a fragment.
	 *
	 * @param int $cache_level The caching level (1–3).
	 * @return void
	 */
	public static function addRules( $cache_level ) {

		// Build the site base path and exclusion pattern.
		$base_path         = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$exclusion_pattern = '';
		$cache_exclusion   = get_cache_exclusion();

		if ( is_string( $cache_exclusion ) && '' !== $cache_exclusion ) {
			$parts             = array_map( 'trim', explode( ',', sanitize_text_field( $cache_exclusion ) ) );
			$exclusion_pattern = implode( '|', array_filter( $parts ) );
		}

		// Register (or replace) a fragment with the current settings.
		HtaccessApi::register(
			new BrowserCacheFragment(
				self::FRAGMENT_ID,
				self::MARKER,
				absint( $cache_level ),
				$exclusion_pattern,
				$base_path
			),
			true // queue apply
		);
	}

	/**
	 * Get the filetype expirations based on the current caching level.
	 *
	 * @param int $cache_level The caching level.
	 * @return array<string,string> Map of mime-type => TTL (human string).
	 */
	public static function getFileTypeExpirations( int $cache_level ) {

		switch ( $cache_level ) {
			case 3:
				return array(
					'default'         => '1 week',
					'text/html'       => '8 hours',
					'image/jpg'       => '1 week',
					'image/jpeg'      => '1 week',
					'image/gif'       => '1 week',
					'image/png'       => '1 week',
					'image/webp'      => '1 week',
					'text/css'        => '1 week',
					'text/javascript' => '1 week',
					'application/pdf' => '1 month',
					'image/x-icon'    => '1 year',
				);

			case 2:
				return array(
					'default'         => '24 hours',
					'text/html'       => '2 hours',
					'image/jpg'       => '24 hours',
					'image/jpeg'      => '24 hours',
					'image/gif'       => '24 hours',
					'image/png'       => '24 hours',
					'image/webp'      => '24 hours',
					'text/css'        => '24 hours',
					'text/javascript' => '24 hours',
					'application/pdf' => '1 week',
					'image/x-icon'    => '1 year',
				);

			case 1:
				return array(
					'default'         => '5 minutes',
					'text/html'       => '0 seconds',
					'image/jpg'       => '1 hour',
					'image/jpeg'      => '1 hour',
					'image/gif'       => '1 hour',
					'image/png'       => '1 hour',
					'image/webp'      => '1 hour',
					'text/css'        => '1 hour',
					'text/javascript' => '1 hour',
					'application/pdf' => '6 hours',
					'image/x-icon'    => '1 year',
				);

			default:
				return array();
		}
	}

	/**
	 * Handle activation logic.
	 *
	 * @return void
	 */
	public static function on_activation() {
		self::maybeAddRules( get_cache_level() );
	}

	/**
	 * Handle deactivation logic.
	 *
	 * @return void
	 */
	public static function on_deactivation() {
		self::removeRules();
	}
}
