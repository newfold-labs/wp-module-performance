<?php
namespace NewfoldLabs\WP\Module\Performance;


use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Browser;
use NewfoldLabs\WP\Module\Performance\CacheTypes\File;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Skip404;
use NewfoldLabs\WP\Module\Performance\ResponseHeaderManager;

use function NewfoldLabs\WP\Module\Performance\getCacheLevel;
use function NewfoldLabs\WP\ModuleLoader\container as getContainer;
use function NewfoldLabs\WP\Context\getContext;
use function NewfoldLabs\WP\Module\Features\disable as disableFeature;
use function NewfoldLabs\WP\Module\Features\isEnabled;

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
			add_action( 'newfold_container_set', array( $this, 'pluginHooks') );
		}
	}

	/**
	 * Add hooks.
	 */
	public function hooks() {
		// Filter vale based on context
		add_filter( 'newfold/features/filter/isEnabled:performance', array( $this, 'filterValue' ) );
		// Force disable based on context
		add_action( 'newfold/features/action/onEnable:performance', array( $this, 'onFeatureEnable' ) );
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
			$this->onDeactivation();
		}
	}

	/**
	 * On enable callback.
	 *
	 * @return void
	 */
	public function onFeatureEnable() {
		$this->maybeDisable();
		if ( isEnabled( 'performance' ) ) {
			$this->onActivation();
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

	/**
	 * Hooks for plugin activation/deactivation
	 */
	public function pluginHooks( Container $container ) {
		register_activation_hook( $container->plugin()->file, array( $this, 'onActivation' ) );
		register_deactivation_hook( $container->plugin()->file, array( $this, 'onDeactivation' ) );
	}

	/**
	 * Activation hook to perform when plugin is activated or feature is enabled
	 */
	public function onActivation() {
		Skip404::onActivation();
		File::onActivation();
		Browser::onActivation();
		// Add headers to .htaccess
		$responseHeaderManager = new ResponseHeaderManager();
		$responseHeaderManager->addHeader( 'X-Newfold-Cache-Level', absint( getCacheLevel() ) );
	}

	/**
	 * Deactivation hook to perform when plugin is deactivated or feature is disabled
	 */
	public function onDeactivation() {
		Skip404::onDeactivation();
		File::onDeactivation();
		Browser::onDeactivation();
		// Remove all headers from .htaccess
		$responseHeaderManager = new ResponseHeaderManager();
		$responseHeaderManager->removeAllHeaders();
	}
}
