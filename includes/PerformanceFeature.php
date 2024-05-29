<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\Module\Performance\Performance;

use function NewfoldLabs\WP\ModuleLoader\container as getContainer;

/**
 * Child class for a feature
 *
 * Child classes should define a name property as the feature name for all API calls. This name will be used in the registry.
 * Child class naming convention is {FeatureName}Feature.
 */
class PerformanceFeature extends \NewfoldLabs\WP\Module\Features\Feature {

	/**
	 * The feature name.
	 *
	 * @var string
	 */
	protected $name = 'performance';

	/**
	 * The feature value. Defaults to on.
	 *
	 * @var boolean
	 */
	protected $value = true; // default to on

	/**
	 * Initialize performance feature.
	 */
	public function initialize() {
		if ( function_exists( 'add_action' ) ) {
			// Register module
			add_action(
				'plugins_loaded',
				function () {
					new Performance( getContainer() );
				}
			);
		}
	}
}
