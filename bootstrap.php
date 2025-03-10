<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Cache\CacheFeatureHooks;

if ( function_exists( 'add_filter' ) ) {
	add_filter(
		'newfold/features/filter/register',
		function ( $features ) {
			return array_merge( $features, array( PerformanceFeature::class ) );
		}
	);
}

new CacheFeatureHooks();

require_once __DIR__ . '/includes/BurstSafetyMode/init.php';
