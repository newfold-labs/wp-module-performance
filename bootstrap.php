<?php

use function NewfoldLabs\WP\Context\getContext;
use function NewfoldLabs\WP\Module\Features\disable as disableFeature;

if ( function_exists( 'add_action' ) ) {

	// Filter as needed based on context
	add_filter( 'newfold/features/filter/isEnabled:performance', 'performanceFeatureFilter' );

	// Force disable based on context
	add_action( 'newfold/features/action/onEnable:performance', 'maybeDisable' );

	// if atomic context, disable performance feature
	add_action( 'after_setup_theme', 'maybeDisable' );

}

// Maybe disable based on context
function maybeDisable() {
	if ( shouldDisablePerformanceFeature() ) {
		disableFeature('performance');
	}
}

// Feature filter based on context
function performanceFeatureFilter( $value ) {
	if ( shouldDisablePerformanceFeature() ) {
		$value = false;
	}
	return $value;
}

function shouldDisablePerformanceFeature() {
	return 'atomic' === getContext( 'platform' );
}