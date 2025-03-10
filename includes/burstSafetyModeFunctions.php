<?php
namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Cache\Types\Browser;
use NewfoldLabs\WP\Module\Performance\Cache\Types\Skip404;
use NewfoldLabs\WP\Module\Performance\Cache\ResponseHeaderManager;

$newfold_burst_safety_mode = (bool) get_option( 'newfold_burst_safety_mode' );

$site_cache_level = get_option( 'newfold_cache_level', 3 );
$site_skip404     = (bool) get_option( 'newfold_skip_404_handling', true );

if ( defined( 'BURST_SAFETY_MODE' ) && BURST_SAFETY_MODE ) {
	if ( false === $newfold_burst_safety_mode ) {
		$current_level = get_option( CacheManager::OPTION_CACHE_LEVEL );
		update_option( 'newfold_burst_safety_mode', true );
		update_option( 'newfold_burst_safety_mode_site_cache_level', $current_level );
		$browser = new Browser();
		$browser::maybeAddRules( 3 );
		if ( function_exists( 'get_skip404_option' ) && ! get_skip404_option() ) {
			$skip404 = new Skip404();
			$skip404::maybeAddRules( true );
		}
		$response_header_manager = new ResponseHeaderManager();
		$response_header_manager->add_header( 'X-Newfold-Cache-Level', 3 );
	}
} elseif ( $newfold_burst_safety_mode ) {
	$cache_level = get_option( 'newfold_burst_safety_mode_site_cache_level' );
	$browser     = new Browser();
	$browser::maybeAddRules( $cache_level );
	if ( function_exists( 'get_skip404_option' ) && ! get_skip404_option() ) {
		$skip404 = new Skip404();
		$skip404::maybeAddRules( false );
	}
	$response_header_manager = new ResponseHeaderManager();
	$response_header_manager->add_header( 'X-Newfold-Cache-Level', $cache_level );
	delete_option( 'newfold_burst_safety_mode' );
	delete_option( 'newfold_burst_safety_mode_site_cache_level' );
}
