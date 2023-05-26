<?php

namespace NewfoldLabs\WP\Module\Performance\CacheTypes;

use NewfoldLabs\WP\Module\Performance\Concerns\Purgeable;

use wpscholar\Url;

class Nginx extends CacheBase implements Purgeable {

	/**
	 * Purge all assets from the Nginx cache.
	 *
	 * @return void
	 */
	public function purgeAll() {
		$this->purgeRequest();
	}

	/**
	 * Purge the Nginx cache for a specific URL.
	 *
	 * @param  string  $url
	 *
	 * @return void
	 */
	public function purgeUrl( $url ) {
		$this->purgeRequest( $url );
	}

	/**
	 * Purge the cache.
	 *
	 * @param  string  $url
	 *
	 * @return void
	 */
	protected function purgeRequest( $url = '' ) {
		global $wp_version;

		$URL = $url ? new Url( $url ) : new Url( \home_url() );

		$pluginBrand   = $this->getContainer()->plugin()->get( 'id' );
		$pluginVersion = $this->getContainer()->plugin()->version;

		$args = array(
			'method'     => 'PURGE',
			'headers'    => array(
				'host' => $URL->host,
			),
			'user-agent' => "WordPress/{$wp_version}; {$URL->host}; {$pluginBrand}/v{$pluginVersion}",
			'sslverify'  => false,
		);

		// If WP_DEBUG is enabled, we want to wait for a response.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$args['blocking'] = false;
			$args['timeout']  = 0.01;
		}

		$path = '/' . ltrim( $URL->path, '/' ) . '.*';

		$httpUrl = $URL::buildUrl(
			array_merge(
				$URL->toArray(),
				[
					'scheme' => 'http',
					'host'   => '127.0.0.1:8080',
					'path'   => $path,
				]
			)
		);

		$httpsUrl = $URL::buildUrl(
			array_merge(
				$URL->toArray(),
				[
					'scheme' => 'https',
					'host'   => '127.0.0.1:8443',
					'path'   => $path,
				]
			)
		);

		wp_remote_request( $httpUrl, $args );
		wp_remote_request( $httpsUrl, $args );
	}

}
