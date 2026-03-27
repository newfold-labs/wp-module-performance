<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

use NewfoldLabs\WP\Module\Performance\Helpers\RedisEnv;

/**
 * Performs a lightweight Redis connectivity check using phpredis.
 *
 * Mirrors the common connection modes from the Newfold Redis drop-in, but intentionally stays
 * defensive: if we cannot confidently connect, we return failure with a message.
 */
final class PhpRedisPinger {

	/**
	 * Run Redis PING using the active connection mode (single, cluster, or shards).
	 *
	 * @return array{ok:bool, message?:string}
	 */
	public static function ping(): array {
		if ( ! extension_loaded( 'redis' ) || ! class_exists( 'Redis', false ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'phpredis is not available.', 'wp-module-performance' ),
			);
		}

		try {
			if ( defined( 'WP_REDIS_SHARDS' ) && is_array( WP_REDIS_SHARDS ) ) {
				if ( ! class_exists( 'RedisArray', false ) ) {
					return array(
						'ok'      => false,
						'message' => __( 'RedisArray is not available in this PHP Redis build.', 'wp-module-performance' ),
					);
				}
				return self::ping_shards();
			}

			if ( defined( 'WP_REDIS_CLUSTER' ) ) {
				if ( ! class_exists( 'RedisCluster', false ) ) {
					return array(
						'ok'      => false,
						'message' => __( 'RedisCluster is not available in this PHP Redis build.', 'wp-module-performance' ),
					);
				}
				return self::ping_cluster();
			}

			return self::ping_single();
		} catch ( \Throwable $e ) {
			return array(
				'ok'      => false,
				'message' => $e->getMessage(),
			);
		}
	}

	/**
	 * Ping Redis in RedisArray (sharded) mode.
	 *
	 * @return array{ok:bool, message?:string}
	 */
	private static function ping_shards(): array {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Drop-in constant.
		$redis = new \RedisArray( array_values( WP_REDIS_SHARDS ) );
		$pong  = $redis->ping();

		return self::normalize_ping_result( $pong );
	}

	/**
	 * Ping Redis in cluster mode.
	 *
	 * @return array{ok:bool, message?:string}
	 */
	private static function ping_cluster(): array {
		$version = phpversion( 'redis' );
		$version = is_string( $version ) ? $version : '0.0.0';

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Drop-in constant.
		$cluster = WP_REDIS_CLUSTER;

		if ( is_string( $cluster ) ) {
			$redis = new \RedisCluster( $cluster );
			$pong  = $redis->ping();
			return self::normalize_ping_result( $pong );
		}

		if ( ! is_array( $cluster ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Unsupported WP_REDIS_CLUSTER configuration.', 'wp-module-performance' ),
			);
		}

		$parameters = self::build_parameters_from_constants();

		$args = array(
			'cluster'      => self::build_cluster_seeds( $cluster ),
			'timeout'      => $parameters['timeout'],
			'read_timeout' => $parameters['read_timeout'],
			'persistent'   => (bool) $parameters['persistent'],
		);

		if ( isset( $parameters['password'] ) && version_compare( $version, '4.3.0', '>=' ) ) {
			$args['password'] = $parameters['password'];
		}

		if ( version_compare( $version, '5.3.0', '>=' ) && defined( 'WP_REDIS_SSL_CONTEXT' ) && ! empty( WP_REDIS_SSL_CONTEXT ) ) {
			if ( ! array_key_exists( 'password', $args ) ) {
				$args['password'] = null;
			}
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- Drop-in constant.
			$args['ssl'] = WP_REDIS_SSL_CONTEXT;
		}

		$redis = new \RedisCluster( null, ...array_values( $args ) );
		$pong  = $redis->ping();

		return self::normalize_ping_result( $pong );
	}

	/**
	 * Ping a single Redis instance.
	 *
	 * @return array{ok:bool, message?:string}
	 */
	private static function ping_single(): array {
		$parameters = self::build_parameters_from_constants();
		$version    = phpversion( 'redis' );
		$version    = is_string( $version ) ? $version : '0.0.0';

		$redis = new \Redis();

		$retry_interval = $parameters['retry_interval'];
		$args           = array(
			'host'           => $parameters['host'],
			'port'           => (int) $parameters['port'],
			'timeout'        => $parameters['timeout'],
			'reserved'       => '',
			'retry_interval' => null === $retry_interval ? 0 : (int) $retry_interval,
		);

		if ( version_compare( $version, '3.1.3', '>=' ) ) {
			$args['read_timeout'] = $parameters['read_timeout'];
		}

		if ( strcasecmp( 'tls', (string) $parameters['scheme'] ) === 0 ) {
			$args['host'] = sprintf(
				'%s://%s',
				$parameters['scheme'],
				str_replace( 'tls://', '', (string) $parameters['host'] )
			);

			if ( version_compare( $version, '5.3.0', '>=' ) && defined( 'WP_REDIS_SSL_CONTEXT' ) && ! empty( WP_REDIS_SSL_CONTEXT ) ) {
				$args['others'] = array(
					'stream' => WP_REDIS_SSL_CONTEXT,
				);
			}
		}

		if ( strcasecmp( 'unix', (string) $parameters['scheme'] ) === 0 ) {
			$args['host'] = (string) $parameters['path'];
			$args['port'] = -1;
		}

		call_user_func_array( array( $redis, 'connect' ), array_values( $args ) );

		self::phpredis_auth( $redis, $parameters, $version );

		if ( isset( $parameters['database'] ) && $parameters['database'] ) {
			$db = $parameters['database'];
			if ( ctype_digit( (string) $db ) ) {
				$db = (int) $db;
			}
			if ( $db ) {
				$redis->select( $db );
			}
		}

		$pong = $redis->ping();
		return self::normalize_ping_result( $pong );
	}

	/**
	 * Build host:port seed strings from a WP_REDIS_CLUSTER array-style definition.
	 *
	 * @param mixed $cluster_def Cluster definition from WP_REDIS_CLUSTER.
	 * @return array<int, string>
	 */
	private static function build_cluster_seeds( $cluster_def ): array {
		$seeds = array();
		foreach ( (array) $cluster_def as $key => $define ) {
			if ( is_array( $define ) ) {
				$seeds[] = implode( ':', array_map( 'strval', $define ) );
				continue;
			}

			$host    = is_int( $key ) ? strval( $define ) : strval( $key );
			$port    = strval( $define );
			$seeds[] = "{$host}:{$port}";
		}

		return $seeds;
	}

	/**
	 * Interpret a PING response from phpredis as success or failure.
	 *
	 * @param mixed $pong Return value from Redis::ping().
	 * @return array{ok:bool, message?:string}
	 */
	private static function normalize_ping_result( $pong ): array {
		if ( true === $pong ) {
			return array( 'ok' => true );
		}

		if ( is_string( $pong ) && stripos( $pong, 'PONG' ) !== false ) {
			return array( 'ok' => true );
		}

		if ( is_array( $pong ) ) {
			foreach ( $pong as $v ) {
				if ( is_string( $v ) && stripos( $v, 'PONG' ) !== false ) {
					return array( 'ok' => true );
				}
			}
		}

		return array(
			'ok'      => false,
			'message' => __( 'Redis did not respond to PING as expected.', 'wp-module-performance' ),
		);
	}

	/**
	 * Build connection parameters from WP_REDIS_* constants plus optional environment fallbacks.
	 *
	 * @return array{scheme:string,host:string,port:int,path:string,password?:string,database:int|float|string,timeout:float|int,read_timeout:float|int,retry_interval:?int,persistent:bool}
	 */
	private static function build_parameters_from_constants(): array {
		$parameters = array(
			'scheme'         => 'tcp',
			'host'           => '127.0.0.1',
			'port'           => 6379,
			'path'           => '',
			'database'       => 0,
			'timeout'        => 1,
			'read_timeout'   => 1,
			'retry_interval' => null,
			'persistent'     => false,
		);

		$settings = array(
			'scheme',
			'host',
			'port',
			'path',
			'password',
			'username',
			'database',
			'timeout',
			'read_timeout',
			'retry_interval',
		);

		foreach ( $settings as $setting ) {
			$constant = sprintf( 'WP_REDIS_%s', strtoupper( $setting ) );
			if ( defined( $constant ) ) {
				$parameters[ $setting ] = constant( $constant );
			}
		}

		if ( isset( $parameters['password'] ) && '' === $parameters['password'] ) {
			unset( $parameters['password'] );
		}

		if ( ! isset( $parameters['password'] ) ) {
			$from_env = RedisEnv::string_value( 'WP_REDIS_PASSWORD' );
			if ( '' !== $from_env ) {
				$parameters['password'] = $from_env;
			}
		}

		if ( ! isset( $parameters['username'] ) ) {
			$user_env = RedisEnv::string_value( 'WP_REDIS_USERNAME' );
			if ( '' !== $user_env ) {
				$parameters['username'] = $user_env;
			}
		}

		return $parameters;
	}

	/**
	 * Authenticate using the PHP Redis extension: legacy password, ACL array (Redis 6+), or username and password.
	 *
	 * @param \Redis               $redis      Connected client.
	 * @param array<string, mixed> $parameters From build_parameters_from_constants().
	 * @param string               $phpredis_ver Extension version string.
	 */
	private static function phpredis_auth( \Redis $redis, array $parameters, $phpredis_ver ): void {
		if ( ! isset( $parameters['password'] ) ) {
			return;
		}

		$pw = $parameters['password'];

		if ( is_array( $pw ) ) {
			$redis->auth( $pw );
			return;
		}

		$pw_string = self::redis_auth_secret_string( $pw );
		if ( null === $pw_string ) {
			return;
		}

		if ( isset( $parameters['username'] ) && is_string( $parameters['username'] ) && '' !== $parameters['username'] ) {
			if ( version_compare( (string) $phpredis_ver, '5.3.0', '>=' ) ) {
				$redis->auth( $parameters['username'], $pw_string );
				return;
			}
		}

		$redis->auth( $pw_string );
	}

	/**
	 * Coerce password to string for phpredis without triggering PHP 8+ conversion fatals.
	 *
	 * @param mixed $pw Password value from constants or env.
	 * @return string|null String to pass to Redis::auth(), or null if the value cannot be used safely.
	 */
	private static function redis_auth_secret_string( $pw ): ?string {
		if ( is_string( $pw ) ) {
			return $pw;
		}
		if ( is_int( $pw ) || is_float( $pw ) ) {
			return (string) $pw;
		}
		if ( is_bool( $pw ) ) {
			return $pw ? '1' : '';
		}
		if ( is_object( $pw ) && method_exists( $pw, '__toString' ) ) {
			return (string) $pw;
		}

		return null;
	}
}
