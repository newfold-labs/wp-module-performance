<?php

namespace NewfoldLabs\WP\Module\Performance;

if ( ! defined( 'NFD_PERFORMANCE_DIR' ) ) {
	define( 'NFD_PERFORMANCE_DIR', __DIR__ );
}

if ( function_exists( 'add_filter' ) ) {
	add_filter(
		'newfold/features/filter/register',
		function ( $features ) {
			return array_merge( $features, array( PerformanceFeature::class ) );
		}
	);
}

new PerformanceFeatureHooks();

require_once __DIR__ . '/includes/BurstSafetyMode/init.php';
