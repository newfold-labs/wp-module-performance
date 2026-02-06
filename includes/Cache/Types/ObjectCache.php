<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

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
		'WP_REDIS_HOST',
		'WP_REDIS_SERVERS',
		'WP_REDIS_CLUSTER',
		'WP_REDIS_SHARDS',
		'WP_REDIS_SENTINEL',
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
	 * Whether the object cache feature is available (Redis connection constant is defined).
	 *
	 * @return bool
	 */
	public static function is_available() {
		foreach ( self::REDIS_CONNECTION_CONSTANTS as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}
		return false;
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
	 * @return array{available: bool, enabled: bool, overwritten: bool, ours: bool}
	 */
	public static function get_state() {
		$available = self::is_available();
		$path      = self::get_drop_in_path();
		$exists    = file_exists( $path );
		$ours      = $exists && self::is_our_drop_in( $path );

		return array(
			'available'   => $available,
			'enabled'     => $available && $ours,
			'overwritten' => $available && $exists && ! $ours,
			'ours'        => $ours,
		);
	}

	/**
	 * Enable object cache by downloading and writing the drop-in.
	 * If our drop-in is already present, we do not download again; we just ensure the preference is set.
	 *
	 * @return array{success: bool, message?: string}
	 */
	public static function enable() {
		if ( ! self::is_available() ) {
			return array(
				'success' => false,
				'message' => __( 'Object cache is not available. Configure Redis in wp-config.php first.', 'wp-module-performance' ),
			);
		}
		$state = self::get_state();
		if ( $state['overwritten'] ) {
			return array(
				'success' => false,
				'message' => __( 'Another object cache drop-in is active. Disable it in the other plugin first.', 'wp-module-performance' ),
			);
		}
		if ( $state['ours'] ) {
			update_option( self::OPTION_ENABLED_PREFERENCE, true );
			return array( 'success' => true );
		}

		$path     = self::get_drop_in_path();
		$response = wp_remote_get(
			self::DROPIN_URL,
			array(
				'timeout'   => 15,
				'sslverify' => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return array(
				'success' => false,
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
		if ( ! self::is_available() ) {
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
	 * Also clears the enabled preference so state stays consistent.
	 *
	 * @return void
	 */
	public static function maybe_remove_dropin_if_unavailable() {
		if ( self::is_available() ) {
			return;
		}
		$path = self::get_drop_in_path();
		if ( ! file_exists( $path ) || ! self::is_our_drop_in( $path ) ) {
			return;
		}
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();
		global $wp_filesystem;
		if ( $wp_filesystem && $wp_filesystem->delete( $path ) ) {
			update_option( self::OPTION_ENABLED_PREFERENCE, false );
			return;
		}
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink -- Intentional fallback after WP_Filesystem.
		if ( @unlink( $path ) ) {
			update_option( self::OPTION_ENABLED_PREFERENCE, false );
		}
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
