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
			add_action( 'newfold_container_set', array( $this, 'pluginHooks') );
		}
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
