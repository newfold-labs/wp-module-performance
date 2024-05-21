<?php

namespace NewfoldLabs\WP\Module\Performance;

add_filter( 
    'newfold/features/filter/register', 
    function( $features ) { 
        return array_merge( $features, array( PerformanceFeature::class ) );
    }
);

new PerformanceFeatureHooks();
