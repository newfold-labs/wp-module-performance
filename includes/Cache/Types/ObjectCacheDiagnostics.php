<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

/**
 * Read-only Redis / object cache diagnostics.
 *
 * Produces a structured report describing why object caching may not be working
 * (missing phpredis, unreachable Redis, socket permissions, foreign drop-in, etc.).
 * It only reads state and attempts a single Redis PING; it never writes files,
 * options, or logs, and it never exposes credentials. The Redis password and
 * username are reported as "set"/"not set" presence only — their values are never
 * included in the report, even partially.
 */
final class ObjectCacheDiagnostics {

	const STATUS_OK   = 'ok';
	const STATUS_WARN = 'warn';
	const STATUS_FAIL = 'fail';
	const STATUS_INFO = 'info';

	/**
	 * Redis connection constants we report on (presence and, where safe, value).
	 *
	 * Values are shown for non-sensitive infrastructure settings only. Credentials
	 * (password, username) are listed in self::SENSITIVE_CONSTANTS and reported as
	 * presence only.
	 *
	 * @var string[]
	 */
	const REPORTED_CONSTANTS = array(
		'WP_REDIS_SCHEME',
		'WP_REDIS_PATH',
		'WP_REDIS_HOST',
		'WP_REDIS_PORT',
		'WP_REDIS_PREFIX',
		'WP_REDIS_USERNAME',
		'WP_REDIS_PASSWORD',
		'WP_REDIS_DATABASE',
		'WP_REDIS_DISABLED',
		'WP_REDIS_GRACEFUL',
	);

	/**
	 * Constants whose values are never printed (presence only).
	 *
	 * @var string[]
	 */
	const SENSITIVE_CONSTANTS = array(
		'WP_REDIS_PASSWORD',
		'WP_REDIS_USERNAME',
	);

	/**
	 * Run all diagnostics and return a structured, render-agnostic report.
	 *
	 * The only side effect is bootstrapping the WP_REDIS_* connection constants from wp-config/env
	 * (via ObjectCache, exactly as the Enable preflight does). This is done once, up front, so every
	 * section — the constant listing, the socket checks, and the live ping — reports the same
	 * connection state. It defines constants but writes no files, options, or logs.
	 *
	 * @return array{
	 *     generated:string,
	 *     summary:array{ok:bool, issues:string[]},
	 *     sections:array<int, array{title:string, lines:array<int, array{status:string, message:string}>}>
	 * }
	 */
	public static function run(): array {
		// Mirror the Enable preflight: make WP_REDIS_* visible from wp-config/env before any section reads them.
		ObjectCache::bootstrap_redis_connection_constants_for_preflight();

		$sections = array(
			self::section_environment(),
			self::section_runtime_constants(),
			self::section_wp_config_constants(),
		);

		$socket_section = self::section_socket();
		if ( null !== $socket_section ) {
			$sections[] = $socket_section;
		}

		$connection = self::run_connection_test();
		$sections[] = $connection['section'];
		$sections[] = self::section_dropin();

		$summary    = self::build_summary( $connection['ok'], $connection['message'] );
		$sections[] = $summary['section'];

		return array(
			'generated' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
			'summary'   => array(
				'ok'     => $summary['ok'],
				'issues' => $summary['issues'],
			),
			'sections'  => $sections,
		);
	}

	/**
	 * Build a single report line.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @param string $message Human-readable message.
	 * @return array{status:string, message:string}
	 */
	private static function line( string $status, string $message ): array {
		return array(
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * The configured Redis scheme (lowercased), defaulting to 'tcp' as PhpRedisPinger does.
	 *
	 * @return string
	 */
	private static function current_scheme(): string {
		return defined( 'WP_REDIS_SCHEME' ) ? strtolower( (string) constant( 'WP_REDIS_SCHEME' ) ) : 'tcp';
	}

	/**
	 * The configured Redis unix socket path, or an empty string when not set.
	 *
	 * @return string
	 */
	private static function configured_socket_path(): string {
		return defined( 'WP_REDIS_PATH' ) ? (string) constant( 'WP_REDIS_PATH' ) : '';
	}

	/**
	 * Mask credentials embedded in connection URIs (e.g. redis://user:pass@host) before a message
	 * is included in the report. PhpRedisPinger does not currently emit credentials, but this keeps
	 * the diagnostic safe if an upstream error message ever echoes a DSN.
	 *
	 * @param string $message Message from the pinger.
	 * @return string
	 */
	private static function redact_secrets( string $message ): string {
		$masked = preg_replace( '#([a-z][a-z0-9+.\-]*://)[^/@\s]*:[^/@\s]*@#i', '${1}***:***@', $message );
		return is_string( $masked ) ? $masked : $message;
	}

	/**
	 * Environment section: PHP, SAPI, phpredis availability.
	 *
	 * @return array{title:string, lines:array}
	 */
	private static function section_environment(): array {
		// Raw "label: value" readouts (PHP version, SAPI, paths, "function() = value") are intentionally
		// left untranslated throughout this class: the value is a non-localizable technical token, and
		// translating only the label half yields awkward half-translated lines. Diagnostic *findings*
		// (the full sentences with OK/WARN/FAIL status) are localized.
		$lines = array(
			self::line( self::STATUS_INFO, 'PHP version: ' . PHP_VERSION ),
			self::line( self::STATUS_INFO, 'PHP SAPI: ' . PHP_SAPI ),
		);

		if ( extension_loaded( 'redis' ) ) {
			$version = phpversion( 'redis' );
			$lines[] = self::line(
				self::STATUS_OK,
				sprintf(
					/* translators: %s is the phpredis extension version. */
					__( 'phpredis loaded (version %s).', 'wp-module-performance' ),
					is_string( $version ) && '' !== $version ? $version : 'unknown'
				)
			);
		} else {
			$lines[] = self::line( self::STATUS_FAIL, __( 'phpredis is NOT loaded — object cache cannot work.', 'wp-module-performance' ) );
		}

		return array(
			'title' => __( 'Environment', 'wp-module-performance' ),
			'lines' => $lines,
		);
	}

	/**
	 * Redis constants visible to PHP in this request.
	 *
	 * @return array{title:string, lines:array}
	 */
	private static function section_runtime_constants(): array {
		$lines = array();

		foreach ( self::REPORTED_CONSTANTS as $name ) {
			$lines[] = self::describe_constant( $name );
		}

		if ( 'unix' === self::current_scheme() ) {
			if ( ! defined( 'WP_REDIS_PATH' ) ) {
				$lines[] = self::line( self::STATUS_FAIL, __( 'Unix scheme requires WP_REDIS_PATH, which is not defined.', 'wp-module-performance' ) );
			}
		} elseif ( ! defined( 'WP_REDIS_HOST' ) ) {
			$lines[] = self::line( self::STATUS_WARN, __( 'WP_REDIS_HOST not defined — connection will default to 127.0.0.1:6379.', 'wp-module-performance' ) );
		}

		return array(
			'title' => __( 'Redis constants (this request)', 'wp-module-performance' ),
			'lines' => $lines,
		);
	}

	/**
	 * Describe a single constant without ever exposing sensitive values.
	 *
	 * @param string $name Constant name.
	 * @return array{status:string, message:string}
	 */
	private static function describe_constant( string $name ): array {
		if ( ! defined( $name ) ) {
			return self::line( self::STATUS_INFO, "{$name} = (not defined)" );
		}

		if ( in_array( $name, self::SENSITIVE_CONSTANTS, true ) ) {
			// Presence only — never print the value, not even partially.
			return self::line( self::STATUS_OK, "{$name} = (set)" );
		}

		$value = constant( $name );

		if ( is_bool( $value ) ) {
			return self::line( self::STATUS_OK, "{$name} = " . ( $value ? 'true' : 'false' ) );
		}

		if ( is_array( $value ) ) {
			return self::line( self::STATUS_OK, "{$name} = (array)" );
		}

		return self::line( self::STATUS_OK, "{$name} = " . (string) $value );
	}

	/**
	 * Whether the connection constants are present in the wp-config.php file itself.
	 *
	 * @return array{title:string, lines:array}
	 */
	private static function section_wp_config_constants(): array {
		$lines = array();

		if ( ObjectCache::is_configured_in_wp_config() ) {
			$lines[] = self::line( self::STATUS_OK, __( 'Redis connection constants are present in wp-config.php.', 'wp-module-performance' ) );
		} else {
			$lines[] = self::line(
				self::STATUS_WARN,
				__( 'Redis connection constants are not all present in wp-config.php. They may be provisioned on enable, or supplied by the environment.', 'wp-module-performance' )
			);
		}

		return array(
			'title' => __( 'wp-config.php constants', 'wp-module-performance' ),
			'lines' => $lines,
		);
	}

	/**
	 * Socket / path checks (only when a unix socket path is configured).
	 *
	 * @return array{title:string, lines:array}|null Null when not applicable.
	 */
	private static function section_socket(): ?array {
		$scheme = self::current_scheme();
		$path   = self::configured_socket_path();

		if ( 'unix' !== $scheme && '' === $path ) {
			return null;
		}

		$lines = array( self::line( self::STATUS_INFO, 'Socket path: ' . ( '' === $path ? '(empty)' : $path ) ) );

		if ( '' === $path ) {
			$lines[] = self::line( self::STATUS_FAIL, __( 'Scheme is unix but the socket path is empty.', 'wp-module-performance' ) );
			return array(
				'title' => __( 'Socket / path checks', 'wp-module-performance' ),
				'lines' => $lines,
			);
		}

		$parent = dirname( $path );
		$lines[] = self::line( self::STATUS_INFO, 'Parent directory: ' . $parent );

		$lines[] = is_dir( $parent )
			? self::line( self::STATUS_OK, __( 'Parent directory exists.', 'wp-module-performance' ) )
			: self::line( self::STATUS_FAIL, __( 'Parent directory does not exist.', 'wp-module-performance' ) );

		$lines[] = is_executable( $parent )
			? self::line( self::STATUS_OK, __( 'Parent directory is searchable by PHP.', 'wp-module-performance' ) )
			: self::line( self::STATUS_FAIL, __( 'Parent directory is NOT searchable by PHP (a common cause of "file exists: no").', 'wp-module-performance' ) );

		$lines[] = file_exists( $path )
			? self::line( self::STATUS_OK, __( 'Socket file exists.', 'wp-module-performance' ) )
			: self::line( self::STATUS_FAIL, __( 'Socket file does NOT exist, or PHP cannot see it.', 'wp-module-performance' ) );

		$lines[] = is_readable( $path )
			? self::line( self::STATUS_OK, __( 'Socket file is readable by PHP.', 'wp-module-performance' ) )
			: self::line( self::STATUS_FAIL, __( 'Socket file is NOT readable by PHP.', 'wp-module-performance' ) );

		return array(
			'title' => __( 'Socket / path checks', 'wp-module-performance' ),
			'lines' => $lines,
		);
	}

	/**
	 * Run the live Redis connection test using the module's own pinger.
	 *
	 * @return array{ok:bool, message:string, section:array{title:string, lines:array}}
	 */
	private static function run_connection_test(): array {
		// Connection constants are already bootstrapped in run() before any section is built.
		$ping    = PhpRedisPinger::ping();
		$ok      = (bool) ( $ping['ok'] ?? false );
		$message = isset( $ping['message'] ) && is_string( $ping['message'] ) ? self::redact_secrets( $ping['message'] ) : '';

		if ( $ok ) {
			$lines = array( self::line( self::STATUS_OK, __( 'Redis responded to PING (this is the same check used when enabling object cache).', 'wp-module-performance' ) ) );
		} else {
			$lines = array(
				self::line(
					self::STATUS_FAIL,
					sprintf(
						/* translators: %s is the failure reason reported by Redis. */
						__( 'Redis PING failed: %s', 'wp-module-performance' ),
						'' !== $message ? $message : __( 'unknown error', 'wp-module-performance' )
					)
				),
				self::line( self::STATUS_INFO, __( 'This is the failure that produces the REST error code "redis_unreachable".', 'wp-module-performance' ) ),
			);
		}

		return array(
			'ok'      => $ok,
			'message' => $message,
			'section' => array(
				'title' => __( 'Live Redis connection test', 'wp-module-performance' ),
				'lines' => $lines,
			),
		);
	}

	/**
	 * Object cache drop-in status.
	 *
	 * @return array{title:string, lines:array}
	 */
	private static function section_dropin(): array {
		$state = ObjectCache::get_state();
		$path  = ObjectCache::get_drop_in_path();
		$lines = array( self::line( self::STATUS_INFO, 'Drop-in path: ' . $path ) );

		if ( ! file_exists( $path ) ) {
			$lines[] = self::line( self::STATUS_WARN, __( 'object-cache.php is missing (expected until object cache is enabled).', 'wp-module-performance' ) );
		} elseif ( ! empty( $state['ours'] ) ) {
			$lines[] = self::line( self::STATUS_OK, __( 'object-cache.php is the Newfold Redis drop-in.', 'wp-module-performance' ) );
		} else {
			$lines[] = self::line( self::STATUS_WARN, __( 'object-cache.php exists but is NOT the Newfold drop-in (another plugin may own it).', 'wp-module-performance' ) );
		}

		$lines[] = ! empty( $state['enabled'] )
			? self::line( self::STATUS_OK, __( 'Object cache is enabled and active.', 'wp-module-performance' ) )
			: self::line( self::STATUS_INFO, __( 'Object cache is not currently active for this request.', 'wp-module-performance' ) );

		if ( function_exists( 'wp_using_ext_object_cache' ) ) {
			$lines[] = wp_using_ext_object_cache()
				? self::line( self::STATUS_OK, 'wp_using_ext_object_cache() = true' )
				: self::line( self::STATUS_WARN, 'wp_using_ext_object_cache() = false' );
		}

		return array(
			'title' => __( 'Object cache drop-in', 'wp-module-performance' ),
			'lines' => $lines,
		);
	}

	/**
	 * Build the diagnosis summary from collected signals.
	 *
	 * @param bool   $connect_ok      Whether the live PING succeeded.
	 * @param string $connect_message Failure message from the PING, if any.
	 * @return array{ok:bool, issues:string[], section:array{title:string, lines:array}}
	 */
	private static function build_summary( bool $connect_ok, string $connect_message ): array {
		$issues = array();

		if ( ! extension_loaded( 'redis' ) ) {
			$issues[] = __( 'Install or enable the phpredis PHP extension.', 'wp-module-performance' );
		}

		$socket_path = self::configured_socket_path();
		if ( 'unix' === self::current_scheme() && '' !== $socket_path && ( ! file_exists( $socket_path ) || ! is_readable( $socket_path ) ) ) {
			$issues[] = sprintf(
				/* translators: %s is the Redis unix socket path. */
				__( 'PHP cannot access the Redis unix socket at %s. Hosting must fix socket permissions or provide a TCP host/port.', 'wp-module-performance' ),
				$socket_path
			);
		}

		if ( ! $connect_ok ) {
			$issues[] = sprintf(
				/* translators: %s is the failure reason reported by Redis. */
				__( 'Direct Redis connection failed: %s', 'wp-module-performance' ),
				'' !== $connect_message ? $connect_message : __( 'unknown error', 'wp-module-performance' )
			);
		}

		if ( defined( 'WP_REDIS_DISABLED' ) && constant( 'WP_REDIS_DISABLED' ) ) {
			$issues[] = __( 'WP_REDIS_DISABLED is true — object cache may be intentionally turned off.', 'wp-module-performance' );
		}

		if ( empty( $issues ) ) {
			$lines = array( self::line( self::STATUS_OK, __( 'All checks passed. Object cache should be able to connect to Redis.', 'wp-module-performance' ) ) );
		} else {
			$lines = array( self::line( self::STATUS_FAIL, __( 'Issues found:', 'wp-module-performance' ) ) );
			foreach ( $issues as $index => $issue ) {
				$lines[] = self::line( self::STATUS_FAIL, '  ' . ( $index + 1 ) . '. ' . $issue );
			}
		}

		return array(
			'ok'      => empty( $issues ),
			'issues'  => $issues,
			'section' => array(
				'title' => __( 'Diagnosis summary', 'wp-module-performance' ),
				'lines' => $lines,
			),
		);
	}
}
