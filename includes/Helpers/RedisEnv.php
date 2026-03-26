<?php

namespace NewfoldLabs\WP\Module\Performance\Helpers;

/**
 * Helpers for reading Redis-related environment variables.
 *
 * Reads from getenv(), $_SERVER, or $_ENV (LiteSpeed / PHP-FPM often omit getenv()).
 */
final class RedisEnv {

	/**
	 * Return a non-empty string for the given environment variable name, or an empty string.
	 *
	 * @param string $name Environment variable name, e.g. WP_REDIS_PASSWORD.
	 */
	public static function string_value( string $name ): string {
		$v = getenv( $name );
		if ( is_string( $v ) && '' !== $v ) {
			return $v;
		}
		if ( isset( $_SERVER[ $name ] ) && is_string( $_SERVER[ $name ] ) && '' !== $_SERVER[ $name ] ) {
			return $_SERVER[ $name ];
		}
		if ( isset( $_ENV[ $name ] ) && is_string( $_ENV[ $name ] ) && '' !== $_ENV[ $name ] ) {
			return $_ENV[ $name ];
		}

		return '';
	}
}
