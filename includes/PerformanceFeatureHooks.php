<?php
namespace NewfoldLabs\WP\Module\Performance;

use function NewfoldLabs\WP\Context\getContext;
use function NewfoldLabs\WP\Module\Features\disable as disableFeature;

/**
 * This class adds performance feature hooks.
 **/
class PerformanceFeatureHooks {

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( function_exists( 'add_action' ) ) {
			add_action( 'plugins_loaded', array( $this, 'hooks' ) );
		}
	}

	/**
	 * Add hooks.
	 */
	public function hooks() {
		// Filter vale based on context
		add_filter( 'newfold/features/filter/isEnabled:performance', array( $this, 'filterValue' ) );
		// Force disable based on context
		add_action( 'newfold/features/action/onEnable:performance', array( $this, 'maybeDisable' ) );
		// Check if should disable on setup
		add_action( 'after_setup_theme', array( $this, 'maybeDisable' ) );
	}

	/**
	 * Feature filter based on context.
	 *
	 * @param boolean $value the value
	 * @return boolean the filtered value
	 */
	public function filterValue( $value ) {
		if ( $this->shouldDisable() ) {
			$value = false;
		}
		return $value;
	}

	/**
	 * Maybe disable the feature.
	 *
	 * @return void
	 */
	public function maybeDisable() {
		if ( $this->shouldDisable() ) {
			disableFeature( 'performance' );
		}
	}

	/**
	 * Context condition for disabling feature.
	 *
	 * @return boolean whether the feature should be disabled
	 */
	public function shouldDisable() {
		// check for atomic context
		return 'atomic' === getContext( 'platform' );
	}
}
