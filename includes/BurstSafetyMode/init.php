<?php

use NewfoldLabs\WP\Module\Performance\BurstSafetyMode\Skip404 as BurstSkip404;
use NewfoldLabs\WP\Module\Performance\BurstSafetyMode\Browser as BurstBrowser;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Browser as CacheBrowser;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Skip404 as CacheSkip404;
use NewfoldLabs\WP\Module\Performance\ResponseHeaderManager;


$newfold_burst_safety_mode = function_exists( 'get_option' ) ? (bool) get_option( 'newfold_burst_safety_mode', false ) : false;
$newfold_cache_level       = function_exists( 'newfold_cache_level' ) ? (int) get_option( 'newfold_cache_level', 0 ) : 0;

// Check if Performance feature is enabled and it's necessary reset the cache options
if ( class_exists( 'NewfoldLabs\WP\Module\Performance\PerformanceFeatureHooks' ) ) {
	if ( $newfold_burst_safety_mode ) {
		$browser = new CacheBrowser();
		$browser::maybeAddRules( $newfold_cache_level );

		$skip_404_handling = (bool) get_option( 'newfold_skip_404_handling', true );

		if ( ! $skip_404_handling ) {
			$skip404 = new CacheSkip404();
			$skip404::maybeAddRules( false );
		}

		$responseHeaderManager = new ResponseHeaderManager();
		$responseHeaderManager->addHeader( 'X-Newfold-Cache-Level', $newfold_cache_level );

		delete_option( 'newfold_burst_safety_mode' );
	}
} else {
	if ( ! $newfold_burst_safety_mode ) {
		$files_to_include = array(
			'htaccess'                => BLUEHOST_PLUGIN_DIR . 'vendor/wp-forge/wp-htaccess-manager/includes/htaccess.php',
			'htaccess_functions'      => BLUEHOST_PLUGIN_DIR . 'vendor/wp-forge/wp-htaccess-manager/includes/functions.php',
			'skip404'                 => BLUEHOST_PLUGIN_DIR . 'vendor/newfold-labs/wp-module-performance/includes/BurstSafetyMode/Skip404.php',
			'browser'                 => BLUEHOST_PLUGIN_DIR . 'vendor/newfold-labs/wp-module-performance/includes/BurstSafetyMode/browser.php',
			'response_header_manager' => BLUEHOST_PLUGIN_DIR . 'vendor/newfold-labs/wp-module-performance/includes/BurstSafetyMode/ResponseHeaderManager.php',
		);

		foreach ( $files_to_include as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		define( 'BURST_SAFETY_CACHE_LEVEL', 3 );

		$skip404 = new BurstSkip404();

		if ( BURST_SAFETY_CACHE_LEVEL !== $newfold_cache_level && class_exists( BurstBrowser::class ) ) {
			$browser = new BurstBrowser();
		}

		update_option( 'newfold_burst_safety_mode', true );
	}
}
