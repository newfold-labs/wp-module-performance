<?php

use function NewfoldLabs\WP\Context\getContext;
use function NewfoldLabs\WP\Module\Features\getFeature;

if ( function_exists( 'add_action' ) ) {

	// update as needed based on context
	add_filter(
		'newfold/features/filter/isEnabled/performance',
		function($value) {
			if ( 'atomic' === getContext( 'platform' ) ) {
				$value = false;
			}
			return $value;
		}
	);

    // if atomic context, disable performance feature
	add_action(
		'after_setup_theme',
		function () {
			if ( 'atomic' === getContext( 'platform' ) ) {
				$performanceFeature = getFeature('performance');
				if ( $performanceFeature ) {
					$performanceFeature->disable();
				}
			}
		}
	);
}