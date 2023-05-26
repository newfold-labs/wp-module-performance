<?php

namespace NewfoldLabs\WP\Module\Performance\CacheTypes;

use NewfoldLabs\WP\Module\Performance\Concerns\Purgeable;
use NewfoldLabs\WP\ModuleLoader\Container;

class Sitelock extends CacheBase implements Purgeable {

	/**
	 * Whether the code for this cache type should be loaded.
	 *
	 * @param  Container  $container
	 *
	 * @return bool
	 */
	public static function shouldEnable( Container $container ) {
		return (bool) \get_option( 'endurance_sitelock_enabled', false );
	}

	/**
	 * Purge all content from the Sitelock CDN cache.
	 *
	 * @return void
	 */
	public function purgeAll() {

		$refresh_token = \get_option( '_mm_refresh_token' );

		if ( false === $refresh_token ) {
			return;
		}

		$endpoint = 'https://my.bluehost.com/cgi/wpapi/cdn_purge';
		$domain   = wp_parse_url( \home_url(), PHP_URL_HOST );
		$query    = add_query_arg( array( 'domain' => $domain ), $endpoint );

		$path = ABSPATH;
		$path = explode( 'public_html/', $path );
		if ( 2 === count( $path ) ) {
			$path = '/public_html/' . $path[1];
		} else {
			return;
		}

		$args = array(
			'headers' => array(
				'x-api-refresh-token' => $refresh_token,
				'x-api-path'          => bin2hex( $path ),
			),
		);

		// If WP_DEBUG is enabled, we want to wait for a response.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$args['blocking'] = false;
			$args['timeout']  = 0.01;
		}

		wp_remote_get( $query, $args );

	}

	/**
	 * Purge a specific URL from the Sitelock CDN cache.
	 *
	 * @param $url
	 *
	 * @return void
	 */
	public function purgeUrl( $url ) {

		$refreshToken = \get_option( '_mm_refresh_token' );

		if ( false === $refreshToken ) {
			return;
		}

		$path    = wp_parse_url( $url, PHP_URL_PATH );
		$pattern = rawurlencode( $path . '$' );
		$domain  = wp_parse_url( \home_url(), PHP_URL_HOST );

		$args = [
			'method'  => 'PUT',
			'headers' => [
				'X-MOJO-TOKEN' => $refreshToken,
			],
		];

		// If WP_DEBUG is enabled, we want to wait for a response.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$args['blocking'] = false;
			$args['timeout']  = 0.01;
		}

		wp_remote_post( "https://my.bluehost.com/api/domains/{$domain}/caches/sitelock/{$pattern}", $args );
	}

}
