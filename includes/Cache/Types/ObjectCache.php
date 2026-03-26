<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

use NewfoldLabs\WP\Module\Performance\Helpers\RedisEnv;
use NewfoldLabs\WP\Module\Performance\Helpers\RedisCredentialsProvisioner;

/**
 * Object cache (Redis drop-in) management for the performance module.
 *
 * Not a page-cache type; does not go in CacheManager::classMap().
 * Handles detection, enable/disable, and flush of the Redis object-cache drop-in.
 */
class ObjectCache {

	/**
	 * URL to download the object-cache drop-in from.
	 *
	 * @var string
	 */
	const DROPIN_URL = 'https://raw.githubusercontent.com/newfold-labs/wp-drop-in-redis-object-cache/prod/object-cache.php';

	/**
	 * Identifier string in our drop-in file header (Plugin Name). Must match the
	 * drop-in's "Plugin Name:" value so is_our_drop_in() and download validation work.
	 *
	 * @var string
	 */
	const DROPIN_HEADER_IDENTIFIER = 'Redis Object Cache Drop-In';

	/**
	 * Bytes to read from the drop-in file when checking if it is ours.
	 *
	 * @var int
	 */
	const HEADER_READ_BYTES = 2048;

	/**
	 * Minimum constants required for a Redis connection (at least one must be defined).
	 *
	 * @var string[]
	 */
	const REDIS_CONNECTION_CONSTANTS = array(
		'WP_REDIS_PREFIX',
		'WP_REDIS_PASSWORD',
	);

	/**
	 * Option name for persisting user preference (enabled = true, disabled = false).
	 * Used on plugin re-activation to restore the drop-in if it was enabled before deactivation.
	 * When the option is missing (first activation), we enable object cache by default when Redis is available.
	 *
	 * @var string
	 */
	const OPTION_ENABLED_PREFERENCE = 'newfold_object_cache_enabled_preference';

	/**
	 * Cached wp-config existence check (per request).
	 *
	 * @var bool|null
	 */
	private static $wp_config_configured_cache = null;

	/**
	 * Whether the object cache feature is available (Redis connection constant is defined).
	 *
	 * @return bool
	 */
	public static function is_available() {
		foreach ( self::REDIS_CONNECTION_CONSTANTS as $constant ) {
			if ( ! defined( $constant ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether Redis is configured in wp-config.php (at least one connection constant is defined there).
	 * Used for removal: we remove the drop-in when wp-config no longer has Redis config, even if the
	 * drop-in has already defined a constant (e.g. WP_REDIS_PREFIX from WP_CACHE_KEY_SALT or env).
	 * Uses WP-CLI's wp-config-transformer so commented-out defines are not counted as present.
	 * Result is cached per request to avoid repeated file reads and parsing.
	 *
	 * @return bool
	 */
	public static function is_configured_in_wp_config() {
		if ( null !== self::$wp_config_configured_cache ) {
			return self::$wp_config_configured_cache;
		}
		$path = defined( 'WP_CONFIG_FILE' ) ? constant( 'WP_CONFIG_FILE' ) : ( ABSPATH . 'wp-config.php' );
		if ( ! file_exists( $path ) ) {
			$path = dirname( ABSPATH ) . '/wp-config.php';
		}
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			self::$wp_config_configured_cache = false;
			return false;
		}
		try {
			$transformer = new \WPConfigTransformer( $path, true );
			foreach ( self::REDIS_CONNECTION_CONSTANTS as $constant ) {
				if ( ! $transformer->exists( 'constant', $constant ) ) {
					self::$wp_config_configured_cache = false;
					return false;
				}
			}
		} catch ( \Throwable $e ) {
			self::$wp_config_configured_cache = false;
			return false;
		}
		self::$wp_config_configured_cache = true;
		return true;
	}

	/**
	 * Bust the static cache used by is_configured_in_wp_config() within the same request.
	 *
	 * @return void
	 */
	public static function bust_wp_config_cache() {
		self::$wp_config_configured_cache = null;
	}

	/**
	 * Whether required Redis constants are visible to PHP in this request (defined()).
	 *
	 * Note: After hosting writes wp-config mid-request, values may exist in the file before PHP reloads.
	 *
	 * @return bool
	 */
	public static function constants_visible_this_request() {
		foreach ( self::REDIS_CONNECTION_CONSTANTS as $constant ) {
			if ( ! defined( $constant ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Path to the object-cache drop-in file.
	 *
	 * @return string
	 */
	public static function get_drop_in_path() {
		return WP_CONTENT_DIR . '/object-cache.php';
	}

	/**
	 * Check if the file at the given path is our drop-in (by header).
	 *
	 * @param string $path Full path to object-cache.php.
	 * @return bool
	 */
	public static function is_our_drop_in( $path ) {
		if ( ! is_readable( $path ) ) {
			return false;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file header read, not remote.
		$content = @file_get_contents( $path, false, null, 0, self::HEADER_READ_BYTES );
		if ( false === $content || '' === $content ) {
			return false;
		}
		return strpos( $content, self::DROPIN_HEADER_IDENTIFIER ) !== false;
	}

	/**
	 * Get current object cache state for the UI and API.
	 *
	 * `available` controls whether the Object Cache settings block is shown. It is not tied to
	 * wp-config already having Redis constants (provisioning may add them on first enable).
	 * `enabled` is true only when Redis constants are defined in this request and our drop-in is active.
	 *
	 * @return array{available: bool, enabled: bool, overwritten: bool, ours: bool, preflight: array}
	 */
	public static function get_state() {
		$constants_ready = self::is_available();
		$path            = self::get_drop_in_path();
		$exists          = file_exists( $path );
		$ours            = $exists && self::is_our_drop_in( $path );

		/**
		 * Whether to show the Object Cache UI (toggle + copy). Defaults to true so users can enable
		 * object cache and trigger credential provisioning when constants are not present yet.
		 *
		 * @param bool $show_ui Default true.
		 */
		$ui_available = (bool) apply_filters( 'newfold_performance_object_cache_ui_available', true );

		return array(
			'available'   => $ui_available,
			'enabled'     => $constants_ready && $ours,
			'overwritten' => $exists && ! $ours,
			'ours'        => $ours,
			'preflight'   => ObjectCachePreflight::snapshot( false ),
		);
	}

	/**
	 * Enable object cache by downloading and writing the drop-in.
	 * If our drop-in is already present, we do not download again; we just ensure the preference is set.
	 *
	 * @return array{success: bool, message?: string, code?: string}
	 */
	public static function enable() {
		$path   = self::get_drop_in_path();
		$exists = file_exists( $path );
		$ours   = $exists && self::is_our_drop_in( $path );
		if ( $exists && ! $ours ) {
			return array(
				'success' => false,
				'code'    => ObjectCacheErrorCodes::DROPIN_OVERWRITTEN,
				'message' => __( 'Another object cache drop-in is active. Disable it in the other plugin first.', 'wp-module-performance' ),
			);
		}

		if ( ! extension_loaded( 'redis' ) ) {
			return array(
				'success' => false,
				'code'    => ObjectCacheErrorCodes::PHPREDIS_MISSING,
				'message' => __( 'The PHP Redis extension (phpredis) is required before object cache can be enabled.', 'wp-module-performance' ),
			);
		}

		if ( $ours ) {
			$ping = self::run_connectivity_preflight();
			if ( true !== $ping ) {
				if (
					is_array( $ping )
					&& isset( $ping['code'] )
					&& ObjectCacheErrorCodes::REDIS_UNREACHABLE === $ping['code']
				) {
					self::remove_our_dropin_file_and_disable_preference();
				}
				return $ping;
			}
			update_option( self::OPTION_ENABLED_PREFERENCE, true );
			return array( 'success' => true );
		}

		if ( ! self::is_configured_in_wp_config() ) {
			$provision = RedisCredentialsProvisioner::provision_enable_redis_via_hosting_api();
			if ( is_wp_error( $provision ) ) {
				return self::map_wp_error_to_enable_result( $provision );
			}

			self::bust_wp_config_cache();

			if ( ! self::is_configured_in_wp_config() ) {
				return array(
					'success' => false,
					'code'    => ObjectCacheErrorCodes::CREDENTIALS_PENDING_RELOAD,
					'message' => __( 'Redis credentials are being applied. Please wait a few seconds and try again.', 'wp-module-performance' ),
				);
			}
		}

		$ping = self::run_connectivity_preflight();
		if ( true !== $ping ) {
			return $ping;
		}

		$path          = self::get_drop_in_path();
		$dropin_source = self::get_dropin_source_url();
		$response      = wp_remote_get(
			$dropin_source,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'code'    => ObjectCacheErrorCodes::DOWNLOAD_FAILED,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array(
				'success' => false,
				'code'    => ObjectCacheErrorCodes::DOWNLOAD_FAILED,
				'message' => sprintf(
					/* translators: %d: HTTP status code */
					__( 'Failed to download object cache (HTTP %d).', 'wp-module-performance' ),
					$code
				),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) || strpos( $body, self::DROPIN_HEADER_IDENTIFIER ) === false ) {
			return array(
				'success' => false,
				'code'    => ObjectCacheErrorCodes::INVALID_DROPIN,
				'message' => __( 'Downloaded content is not valid. Please try again.', 'wp-module-performance' ),
			);
		}

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->put_contents( $path, $body, FS_CHMOD_FILE ) ) {
			update_option( self::OPTION_ENABLED_PREFERENCE, true );
			return array( 'success' => true );
		}

		// Fallback to file_put_contents if WP_Filesystem failed (e.g. direct method not available).
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Intentional fallback after WP_Filesystem.
		if ( false !== @file_put_contents( $path, $body ) ) {
			update_option( self::OPTION_ENABLED_PREFERENCE, true );
			return array( 'success' => true );
		}

		return array(
			'success' => false,
			'code'    => ObjectCacheErrorCodes::WRITE_FAILED,
			'message' => __( 'Could not write object-cache.php. Check file permissions.', 'wp-module-performance' ),
		);
	}

	/**
	 * Disable object cache by removing the drop-in (only if it is ours).
	 *
	 * @param bool $clear_preference If true (default), set the enabled preference to false. Pass false when called from deactivation so the last user state is preserved.
	 * @return array{success: bool, message?: string}
	 */
	public static function disable( $clear_preference = true ) {
		$path = self::get_drop_in_path();
		if ( ! file_exists( $path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Object cache is not enabled.', 'wp-module-performance' ),
			);
		}
		if ( ! self::is_our_drop_in( $path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Another object cache drop-in is active. Disable it in the other plugin first.', 'wp-module-performance' ),
			);
		}

		// Flush Redis and clear options cache while our drop-in is still active, then remove the file.
		self::flush_object_cache();
		self::clear_options_object_cache();

		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->delete( $path ) ) {
			if ( $clear_preference ) {
				// Store false (do not delete option) so maybe_restore_on_activation knows user turned off.
				update_option( self::OPTION_ENABLED_PREFERENCE, false );
			}
			// Only on this request: flush Redis at shutdown so it stays empty (no repopulation from get_option).
			add_action( 'shutdown', array( self::class, 'flush_and_clear_on_shutdown' ), PHP_INT_MAX );
			return array( 'success' => true );
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Intentional fallback after WP_Filesystem.
		if ( @unlink( $path ) ) {
			if ( $clear_preference ) {
				// Store false (do not delete option) so maybe_restore_on_activation knows user turned off.
				update_option( self::OPTION_ENABLED_PREFERENCE, false );
			}
			// Only on this request: flush Redis at shutdown so it stays empty (no repopulation from get_option).
			add_action( 'shutdown', array( self::class, 'flush_and_clear_on_shutdown' ), PHP_INT_MAX );
			return array( 'success' => true );
		}

		return array(
			'success' => false,
			'message' => __( 'Could not remove object-cache.php. Check file permissions.', 'wp-module-performance' ),
		);
	}

	/**
	 * Remove our object-cache drop-in on plugin/performance deactivation.
	 * Only deletes the file if it is our drop-in. Does not change the enabled preference,
	 * so whatever state the user had (on or off) is preserved for re-activation.
	 *
	 * @return void
	 */
	public static function on_deactivation() {
		self::disable( false );
	}

	/**
	 * Whether the stored preference means "user wants object cache on".
	 * WordPress may store true as 1 or '1' in the database.
	 *
	 * @return bool
	 */
	public static function is_preference_enabled() {
		$preference = get_option( self::OPTION_ENABLED_PREFERENCE, null );
		return in_array( $preference, array( null, true, 1, '1' ), true );
	}

	/**
	 * Restore the drop-in when Redis is available, user preference is "on", and the file is missing.
	 * Used on activation and when serving cache settings so the UI state matches the preference.
	 *
	 * @return void
	 */
	public static function maybe_restore_dropin() {
		if ( ! self::is_configured_in_wp_config() && ! self::is_available() ) {
			return;
		}
		if ( ! self::is_preference_enabled() ) {
			return;
		}
		if ( self::get_state()['ours'] ) {
			return;
		}
		self::enable();
	}

	/**
	 * Determine where to download the drop-in from.
	 *
	 * Prefers a local monorepo path when present, otherwise falls back to DROPIN_URL.
	 *
	 * @return string URL or file path accepted by wp_remote_get (file://).
	 */
	private static function get_dropin_source_url() {
		$local = apply_filters( 'newfold_performance_object_cache_dropin_local_path', self::default_local_dropin_path() );
		if ( is_string( $local ) && '' !== $local && file_exists( $local ) && is_readable( $local ) ) {
			return 'file://' . $local;
		}

		return (string) apply_filters( 'newfold_performance_object_cache_dropin_url', self::DROPIN_URL );
	}

	/**
	 * Default path to the drop-in when this repo includes `wp-drop-in-redis-object-cache/`.
	 *
	 * @return string
	 */
	private static function default_local_dropin_path(): string {
		$module_root = dirname( __DIR__, 3 );
		return $module_root . '/../wp-drop-in-redis-object-cache/object-cache.php';
	}

	/**
	 * Connectivity preflight: ensure wp-config has credentials and Redis responds to PING.
	 *
	 * @return true|array{success:false, code:string, message:string}
	 */
	private static function run_connectivity_preflight() {
		if ( ! self::is_configured_in_wp_config() ) {
			return array(
				'success' => false,
				'code'    => ObjectCacheErrorCodes::CREDENTIALS_MISSING,
				'message' => __( 'Redis credentials are not present in wp-config.php.', 'wp-module-performance' ),
			);
		}

		self::bootstrap_redis_connection_constants_for_preflight();

		$ping = PhpRedisPinger::ping();
		if ( empty( $ping['ok'] ) ) {
			return array(
				'success' => false,
				'code'    => ObjectCacheErrorCodes::REDIS_UNREACHABLE,
				'message' => isset( $ping['message'] ) ? (string) $ping['message'] : __( 'Could not connect to Redis.', 'wp-module-performance' ),
			);
		}

		return true;
	}

	/**
	 * Define missing WP_REDIS_* connection constants from wp-config so phpredis can connect in the same request.
	 *
	 * @return void
	 */
	public static function bootstrap_redis_connection_constants_for_preflight() {
		$settings = array(
			'scheme',
			'host',
			'port',
			'path',
			'password',
			'database',
			'timeout',
			'read_timeout',
			'retry_interval',
		);

		try {
			$t = self::get_wp_config_transformer_readonly();
			if ( $t ) {
				foreach ( $settings as $setting ) {
					$name = sprintf( 'WP_REDIS_%s', strtoupper( $setting ) );
					if ( defined( $name ) ) {
						continue;
					}
					if ( ! $t->exists( 'constant', $name ) ) {
						continue;
					}
					$raw = $t->get_value( 'constant', $name );
					if ( ! is_string( $raw ) ) {
						continue;
					}
					$value = self::parse_wp_config_scalar( $raw );
					if ( null === $value && 'password' === $setting ) {
						// Redis 6 ACL passwords may be a two-element array in wp-config (see parse_wp_config_redis_acl_password).
						$value = self::parse_wp_config_redis_acl_password( $raw );
					}
					if ( null === $value ) {
						continue;
					}

					if ( 'password' === $setting ) {
						if ( is_array( $value ) ) {
							if ( 2 !== count( $value ) ) {
								continue;
							}
						} elseif ( '' === (string) $value ) {
							continue;
						}
					}

					// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Redis drop-in constants.
					define( $name, $value );
				}
			}
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch -- Best-effort bootstrap.
			// Continue: password may still be supplied via environment.
		}

		self::maybe_define_redis_constants_from_environment();
	}

	/**
	 * Define WP_REDIS_PASSWORD / WP_REDIS_USERNAME from the environment when missing (matches common hosting + drop-in patterns).
	 *
	 * @return void
	 */
	private static function maybe_define_redis_constants_from_environment(): void {
		foreach ( array( 'WP_REDIS_USERNAME', 'WP_REDIS_PASSWORD' ) as $name ) {
			if ( defined( $name ) ) {
				continue;
			}
			$val = RedisEnv::string_value( $name );
			if ( '' === $val ) {
				continue;
			}
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Redis drop-in constants.
			define( $name, $val );
		}
	}

	/**
	 * Load a read-only WPConfigTransformer for wp-config.php, or return null if unavailable.
	 *
	 * @return \WPConfigTransformer|null
	 */
	private static function get_wp_config_transformer_readonly() {
		$path = defined( 'WP_CONFIG_FILE' ) ? constant( 'WP_CONFIG_FILE' ) : ( ABSPATH . 'wp-config.php' );
		if ( ! file_exists( $path ) ) {
			$path = dirname( ABSPATH ) . '/wp-config.php';
		}
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return null;
		}

		try {
			return new \WPConfigTransformer( $path, true );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Parse a scalar constant value from raw wp-config text.
	 *
	 * @param string $raw Raw value from WPConfigTransformer::get_value().
	 * @return mixed|null
	 */
	private static function parse_wp_config_scalar( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}

		if ( preg_match( '/^\"(.*)\"$/s', $raw, $m ) ) {
			return stripcslashes( $m[1] );
		}

		if ( preg_match( '/^\'(.*)\'$/s', $raw, $m ) ) {
			return str_replace( array( '\\\\', '\\\'' ), array( '\\', '\'' ), $m[1] );
		}

		if ( 'true' === strtolower( $raw ) ) {
			return true;
		}
		if ( 'false' === strtolower( $raw ) ) {
			return false;
		}

		if ( ctype_digit( $raw ) ) {
			return (int) $raw;
		}

		if ( is_numeric( $raw ) ) {
			return 0 + $raw;
		}

		return null;
	}

	/**
	 * Parse Redis 6 ACL-style password from wp-config when the value is not a scalar string.
	 *
	 * Supports short and long PHP array syntax with two string elements (username and password).
	 *
	 * @param string $raw Raw value from WPConfigTransformer::get_value().
	 * @return array{0:string,1:string}|null
	 */
	private static function parse_wp_config_redis_acl_password( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return null;
		}

		// Short array syntax with two quoted elements.
		if ( '' !== $raw && '[' === $raw[0] ) {
			if ( preg_match( '/^\[\s*\'([^\']*)\'\s*,\s*\'([^\']*)\'\s*\]$/s', $raw, $m ) ) {
				return array( $m[1], $m[2] );
			}
			if ( preg_match( '/^\[\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"\s*,\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"\s*\]$/s', $raw, $m ) ) {
				return array( stripcslashes( $m[1] ), stripcslashes( $m[2] ) );
			}
		}

		// Long array syntax with two single-quoted elements.
		if ( preg_match( '/^array\s*\(\s*\'([^\']*)\'\s*,\s*\'([^\']*)\'\s*\)\s*$/is', $raw, $m ) ) {
			return array( $m[1], $m[2] );
		}
		if ( preg_match( '/^array\s*\(\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"\s*,\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"\s*\)\s*$/is', $raw, $m ) ) {
			return array( stripcslashes( $m[1] ), stripcslashes( $m[2] ) );
		}

		return null;
	}

	/**
	 * Map a WP_Error from credential provisioning to the enable() result shape.
	 *
	 * @param \WP_Error $error Error.
	 * @return array{success:false, code:string, message:string}
	 */
	private static function map_wp_error_to_enable_result( \WP_Error $error ) {
		$code = $error->get_error_code();
		$data = $error->get_error_data();

		$message = $error->get_error_message();

		// Hosting UAPI failures: prefer customer_error as stable machine code when present.
		if ( 'nfd_hosting_uapi_error' === (string) $code && is_array( $data ) && ! empty( $data['customer_error'] ) && is_string( $data['customer_error'] ) ) {
			$code = (string) $data['customer_error'];
		}

		$known = array(
			ObjectCacheErrorCodes::HIIVE_NOT_CONNECTED     => __( 'This site is not connected to Hiive, so Redis credentials cannot be provisioned automatically.', 'wp-module-performance' ),
			ObjectCacheErrorCodes::HUAPI_TOKEN_UNAVAILABLE => __( 'HUAPI token is not available yet. Try again in a few minutes or contact support.', 'wp-module-performance' ),
			ObjectCacheErrorCodes::HAL_SITE_ID_MISSING     => __( 'Hosting site id is not available yet. Try again in a few minutes or contact support.', 'wp-module-performance' ),
			ObjectCacheErrorCodes::REDIS_UNREACHABLE       => __( 'Could not connect to Redis.', 'wp-module-performance' ),
			'nfd_hiive_error'                              => __( 'Could not reach Hiive to provision Redis credentials.', 'wp-module-performance' ),
			'nfd_hosting_uapi_error'                       => __( 'Hosting API could not enable Redis for this site.', 'wp-module-performance' ),
		);

		if ( isset( $known[ $code ] ) ) {
			$message = $known[ $code ];
		}

		return array(
			'success' => false,
			'code'    => (string) $code,
			'message' => $message,
		);
	}

	/**
	 * On plugin/performance activation: if Redis is available and the drop-in file is missing, enable it.
	 *
	 * @return void
	 */
	public static function maybe_restore_on_activation() {
		self::maybe_restore_dropin();
	}

	/**
	 * If Redis config is no longer present (e.g. constants commented out in wp-config) but our drop-in
	 * is still in place, remove the drop-in so WordPress does not load a broken object cache.
	 * Uses wp-config content (not defined()) so we still remove when the drop-in has already defined
	 * a constant (e.g. WP_REDIS_PREFIX from WP_CACHE_KEY_SALT or env). Also clears the enabled preference.
	 * Checks for the drop-in file first so we skip wp-config read/parse on most requests (no drop-in).
	 *
	 * @return void
	 */
	public static function maybe_remove_dropin_if_unavailable() {
		$path = self::get_drop_in_path();
		if ( ! file_exists( $path ) || ! self::is_our_drop_in( $path ) ) {
			return;
		}
		if ( self::is_configured_in_wp_config() ) {
			return;
		}
		self::remove_our_dropin_file_and_disable_preference();
	}

	/**
	 * Delete our object-cache.php and set the enabled preference to false.
	 *
	 * Does not call wp_cache_flush(); used when Redis is unreachable or wp-config no longer has creds
	 * so we avoid invoking a broken object cache backend.
	 *
	 * @return bool True if the file was ours and was removed.
	 */
	private static function remove_our_dropin_file_and_disable_preference(): bool {
		if ( ! self::delete_our_drop_in_file_if_ours() ) {
			return false;
		}
		update_option( self::OPTION_ENABLED_PREFERENCE, false );
		return true;
	}

	/**
	 * Delete our object-cache drop-in when the file exists and matches our header.
	 *
	 * @return bool True if our drop-in existed and was deleted.
	 */
	private static function delete_our_drop_in_file_if_ours(): bool {
		$path = self::get_drop_in_path();
		if ( ! file_exists( $path ) || ! self::is_our_drop_in( $path ) ) {
			return false;
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->delete( $path ) ) {
			return true;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Intentional fallback after WP_Filesystem.
		return (bool) @unlink( $path );
	}

	/**
	 * Flush the object cache (Redis). Only flushes when our drop-in is active.
	 *
	 * Used as part of "Clear cache" action; no separate endpoint.
	 *
	 * @return void
	 */
	public static function flush_object_cache() {
		$state = self::get_state();
		if ( ! $state['enabled'] ) {
			return;
		}
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Clear options-related keys from the object cache so the next request reads from DB.
	 * Call when turning off object cache (or on deactivation) to avoid stale active_plugins/alloptions.
	 *
	 * @return void
	 */
	public static function clear_options_object_cache() {
		if ( ! function_exists( 'wp_cache_delete' ) ) {
			return;
		}
		wp_cache_delete( 'active_plugins', 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'options' );
		}
		if ( function_exists( 'wp_cache_flush_runtime' ) ) {
			wp_cache_flush_runtime();
		}
	}

	/**
	 * Flush entire object cache and clear options keys. Only ever run via shutdown hook
	 * registered in disable() when we have just removed the drop-in (that one request only).
	 * Not run on normal requests—Redis cache is unaffected until the user turns object cache off.
	 *
	 * @return void
	 */
	public static function flush_and_clear_on_shutdown() {
		if ( function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache() && function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
		self::clear_options_object_cache();
	}
}
