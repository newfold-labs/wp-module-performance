<?php

namespace NewfoldLabs\WP\Module\Performance\CacheTypes;

use NewfoldLabs\WP\Module\Performance\Concerns\Purgeable;
use NewfoldLabs\WP\Module\Performance\OptionListener;
use NewfoldLabs\WP\Module\Performance\Performance;
use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Performance\CacheExclusion;
use WP_Forge\WP_Htaccess_Manager\htaccess;
use wpscholar\Url;

use function NewfoldLabs\WP\Module\Performance\getCacheLevel;
use function NewfoldLabs\WP\Module\Performance\removeDirectory;
use function NewfoldLabs\WP\Module\Performance\shouldCachePages;
use function WP_Forge\WP_Htaccess_Manager\removeMarkers;

/**
 * File cache type.
 */
class File extends CacheBase implements Purgeable {
	/**
	 * The directory where cached files live.
	 *
	 * @var string
	 */
	const CACHE_DIR = WP_CONTENT_DIR . '/newfold-page-cache/';

	/**
	 * The file marker name.
	 *
	 * @var string
	 */
	const MARKER = 'Newfold File Cache';

	/**
	 * Whether or not the code for this cache type should be loaded.
	 *
	 * @param Container $container Dependency injection container.
	 *
	 * @return bool
	 */
	public static function shouldEnable( Container $container ) {
		return (bool) $container->has( 'isApache' ) && $container->get( 'isApache' );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {

		new OptionListener( Performance::OPTION_CACHE_LEVEL, array( __CLASS__, 'maybeAddRules' ) );
		new OptionListener( CacheExclusion::OPTION_CACHE_EXCLUSION, array( __CLASS__, 'exclusionChange' ) );

		add_action( 'init', array( $this, 'maybeGeneratePageCache' ) );
		add_action( 'newfold_update_htaccess', array( $this, 'onRewrite' ) );
	}

	/**
	 * Manage on exlcusion option change.
	 */
	public static function exclusionChange() {
		self::maybeAddRules( getCacheLevel() );
	}

	/**
	 * When updating mod rewrite rules, also update our rewrites as appropriate.
	 */
	public function onRewrite() {
		self::maybeAddRules( getCacheLevel() );
	}

	/**
	 * Determine whether to add or remove rules based on caching level.
	 *
	 * @param  int $cacheLevel  The caching level.
	 */
	public static function maybeAddRules( $cacheLevel ) {
		absint( $cacheLevel ) > 1 ? self::addRules() : self::removeRules();
	}

	/**
	 * Add our content to the .htaccess file.
	 *
	 * @return bool
	 */
	public static function addRules() {

		$base = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$path = str_replace( get_home_path(), '/', self::CACHE_DIR );

		$content = <<<HTACCESS
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteBase {$base}
	RewriteRule ^{$path}/ - [L]
	RewriteCond %{REQUEST_METHOD} !POST
	RewriteCond %{QUERY_STRING} !.*=.*
	RewriteCond %{HTTP_COOKIE} !(wordpress_test_cookie|comment_author|wp\-postpass|wordpress_logged_in|wptouch_switch_toggle|wp_woocommerce_session_) [NC]
	RewriteCond %{HTTP:Cache-Control} ^((?!no-cache).)*$
	RewriteCond %{DOCUMENT_ROOT}{$path}/$1/_index.html -f
	RewriteRule ^(.*)\$ {$path}/$1/_index.html [L]
</IfModule>
HTACCESS;

		$htaccess = new htaccess( self::MARKER );

		return $htaccess->addContent( $content );
	}

	/**
	 * Remove our content from the .htaccess file.
	 */
	public static function removeRules() {
		removeMarkers( self::MARKER );
	}

	/**
	 * Initiate the generation of a page cache for a given request, if necessary.
	 */
	public function maybeGeneratePageCache() {
		if ( $this->isCacheable() ) {
			if ( $this->shouldCache() ) {
				ob_start( array( $this, 'write' ) );
			}
		} else {
			nocache_headers();
		}
	}

	/**
	 * Write page content to cache.
	 *
	 * @param  string $content  Page content to be cached.
	 *
	 * @return string
	 */
	public function write( $content ) {
		if ( ! empty( $content ) ) {

			$path = $this->getStoragePathForRequest();
			$file = $this->getStorageFileForRequest();

			if ( false !== strpos( $content, '</html>' ) ) {
				$content .= "\n<!--Generated by Newfold Page Cache-->";
			}

			if ( ! is_dir( $path ) ) {
				mkdir( $path, 0755, true );
			}
			file_put_contents( $file, $content, LOCK_EX ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		}

		return $content;
	}

	/**
	 * Check if the current request is cacheable.
	 *
	 * @return bool
	 */
	public function isCacheable() {
		// The request URI should never be empty – even for the homepage it should be '/'
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		// Don't cache if pretty permalinks are disabled
		if ( false === get_option( 'permalink_structure' ) ) {
			return false;
		}

		// Only cache front-end pages
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		// Don't cache REST API requests
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		// Never cache requests made via WP-CLI
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return false;
		}

		// Don't cache if there are URL parameters present
		if ( isset( $_GET ) && ! empty( $_GET ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		// Don't cache if handling a form submission
		if ( isset( $_POST ) && ! empty( $_POST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return false;
		}

		// Don't cache if a user is logged in.
		if ( function_exists( 'is_user_logged_in' ) && is_user_logged_in() ) {
			return false;
		}

		global $wp_query;
		if ( isset( $wp_query ) ) {

			// Don't cache 404 pages or RSS feeds
			if ( is_404() || is_feed() ) {
				return false;
			}
		}

		// Don't cache private pages
		if ( 'private' === get_post_status() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if we should cache the current request.
	 *
	 * @return bool
	 */
	public function shouldCache() {

		// If page caching is disabled, then don't cache
		if ( ! shouldCachePages() ) {
			return false;
		}

		// Check cache exclusion.
		$cache_exclusion_parameters = $this->exclusions();

		if ( ! empty( $cache_exclusion_parameters ) ) {
			foreach ( $cache_exclusion_parameters as $param ) {
				if ( stripos( $_SERVER['REQUEST_URI'], $param ) !== false ) {
					return false;
				}
			}
		}

		// Don't cache if a file exists and hasn't expired
		$file = $this->getStorageFileForRequest();
		if ( file_exists( $file ) && filemtime( $file ) + $this->getExpirationTimeframe() > time() ) {
			return false;
		}

		return true;
	}

	/**
	 * Get an array of strings that should not be present in the URL for a request to be cached.
	 *
	 * @return array
	 */
	protected function exclusions() {
		$default                = array( 'cart', 'checkout', 'wp-admin', '@', '%', ':', ';', '&', '=', '.', rest_get_url_prefix() );
		$cache_exclusion_option = array_map( 'trim', explode( ',', get_option( CacheExclusion::OPTION_CACHE_EXCLUSION ) ) );
		return array_merge( $default, $cache_exclusion_option );
	}

	/**
	 * Get expiration duration.
	 *
	 * @return int
	 */
	protected function getExpirationTimeframe() {
		switch ( getCacheLevel() ) {
			case 2:
				return 2 * HOUR_IN_SECONDS;
			case 3:
				return 8 * HOUR_IN_SECONDS;
			default:
				return 0;
		}
	}

	/**
	 * Purge everything from the cache.
	 */
	public function purgeAll() {
		removeDirectory( self::CACHE_DIR );
	}

	/**
	 * Purge a specific URL from the cache.
	 *
	 * @param string $url the url to purge.
	 */
	public function purgeUrl( $url ) {
		$path = $this->getStoragePathForRequest();

		if ( trailingslashit( self::CACHE_DIR ) === $path ) {
			if ( file_exists( self::CACHE_DIR . '/_index.html' ) ) {
				unlink( self::CACHE_DIR . '/_index.html' );
			}

			return;
		}

		removeDirectory( $this->getStoragePathForRequest() );
	}

	/**
	 * Get storage path for a given request.
	 *
	 * @return string
	 */
	protected function getStoragePathForRequest() {
		static $path;

		if ( ! isset( $path ) ) {
			$url      = new Url();
			$basePath = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			$path     = trailingslashit( self::CACHE_DIR . str_replace( $basePath, '', esc_url( $url->path ) ) );
		}

		return $path;
	}

	/**
	 * Get storage file for a given request.
	 *
	 * @return string
	 */
	protected function getStorageFileForRequest() {
		return $this->getStoragePathForRequest() . '_index.html';
	}

	/**
	 * Handle activation logic.
	 */
	public static function onActivation() {
		self::maybeAddRules( getCacheLevel() );
	}

	/**
	 * Handle deactivation logic.
	 */
	public static function onDeactivation() {

		// Remove file cache rules from .htaccess
		self::removeRules();

		// Remove all statically cached files
		removeDirectory( self::CACHE_DIR );
	}
}
