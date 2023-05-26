<?php

namespace NewfoldLabs\WP\Module\Performance\CacheTypes;

use NewfoldLabs\WP\Module\Performance\Concerns\Purgeable;
use NewfoldLabs\WP\ModuleLoader\Container;

class Cloudflare extends CacheBase implements Purgeable {

	/**
	 * Whether or not the code for this cache type should be loaded.
	 *
	 * @return bool
	 */
	public static function shouldEnable( Container $container ) {
		return (bool) \get_option( 'endurance_cloudflare_enabled', false );
	}

	/**
	 * Check if Cloudflare is enabled.
	 *
	 * @return bool
	 */
	public function isCoudflareEnabled() {
		return $this->getCloudflareTier() !== 0;
	}

	/**
	 * Get the Cloudflare tier.
	 *
	 * @return int|string
	 */
	public function getCloudflareTier() {
		$tier = \get_option( 'endurance_cloudflare_enabled', false );

		if ( ! $tier ) {
			return 0;
		}

		switch ( $tier ) {
			case 'hostgator':
				return 'hostgator';
			case 'india':
				return 'india';
			case 'premium':
				return 'premium';
			default:
				return 'basic';
		}
	}

	/**
	 * Purge all Cloudflare cache.
	 *
	 * @return void
	 */
	public function purgeAll() {
		if ( $this->isCoudflareEnabled() ) {
			$this->purgeRequest();
		}
	}

	/**
	 * Purge a URL from Cloudflare cache.
	 *
	 * @param  string  $url
	 *
	 * @return void
	 */
	public function purgeUrl( $url ) {
		if ( $this->isCoudflareEnabled() ) {
			$this->purgeRequest( [ $url ] );
		}
	}

	/**
	 * Purge multiple URLs from the Cloudflare cache.
	 *
	 * @link https://confluence.newfold.com/pages/viewpage.action?spaceKey=UDEV&title=Cache+Purge+API
	 *
	 * @param  array  $urls
	 *
	 * @return void
	 */
	protected function purgeRequest( $urls = [] ) {
		global $wp_version;

		$queryString = http_build_query( [ 'cf' => $this->getCloudflareTier() ], '', '&' );

		$host          = wp_parse_url( \home_url(), PHP_URL_HOST );
		$pluginBrand   = $this->getContainer()->plugin()->get( 'id' );
		$pluginVersion = $this->getContainer()->plugin()->version;

		$headerName = 'X-' . strtoupper( $pluginBrand ) . '-PLUGIN-PURGE';

		$body = [
			'hosts' => [ $host ],
		];

		if ( $urls ) {
			$body['assets'] = $urls;
		}

		$args = [
			'body'       => wp_json_encode( $body ),
			'compress'   => true,
			'headers'    => [
				$headerName    => 1,
				'Content-Type' => 'application/json',
			],
			'sslverify'  => false,
			'user-agent' => "WordPress/{$wp_version}; {$host}; {$pluginBrand}/v{$pluginVersion}",
		];

		// If WP_DEBUG is enabled, we want to wait for a response.
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			$args['blocking'] = false;
			$args['timeout']  = 0.01;
		}

		wp_remote_post( 'https://cachepurge.bluehost.com/v0/purge?' . $queryString, $args );
	}

}
