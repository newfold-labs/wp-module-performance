<?php

namespace NewfoldLabs\WP\Module\Performance\Cache;

use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Performance\OptionListener;
use NewfoldLabs\WP\Module\Performance\Cache\CacheTypes\Browser;
use NewfoldLabs\WP\Module\Performance\Cache\CacheTypes\File;
use NewfoldLabs\WP\Module\Performance\Cache\CacheTypes\Skip404;

use function NewfoldLabs\WP\Module\Performance\getCacheLevel;

/**
 * Add activation/deactivation hooks for the performance feature.
 **/
class CacheFeatureHooks {

    /**
     * Dependency injection container.
     *
     * @var Container
     */
    protected $container;

    /**
     * Constructor.
     */
    public function __construct() {
        if ( function_exists( 'add_action' ) ) {
            add_action( 'newfold_container_set', array( $this, 'pluginHooks' ) );
            add_action( 'plugins_loaded', array( $this, 'hooks' ) );
        }

        new OptionListener( CacheManager::OPTION_CACHE_LEVEL, array( $this, 'onCacheLevelChange' ) );
    }

    /**
     * Hooks for plugin activation/deactivation
     *
     * @param Container $container from the plugin
     */
    public function pluginHooks( Container $container ) {
        $this->container = $container;
        register_activation_hook( $container->plugin()->file, array( $this, 'onActivation' ) );
        register_deactivation_hook( $container->plugin()->file, array( $this, 'onDeactivation' ) );
    }

    /**
     * Add hooks.
     */
    public function hooks() {
        add_action( 'newfold/features/action/onEnable:performance', array( $this, 'onActivation' ) );
        add_action( 'newfold/features/action/onDisable:performance', array( $this, 'onDeactivation' ) );
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

    /**
     * On cache level change, update the response headers.
     *
     * @param int|null $cacheLevel The cache level.
     */
    public function onCacheLevelChange( $cacheLevel ) {
        /**
         * Respone Header Manager from container
         *
         * @var ResponseHeaderManager $responseHeaderManager
         */
        $responseHeaderManager = $this->container->get( 'responseHeaderManager' );
        $responseHeaderManager->addHeader( 'X-Newfold-Cache-Level', absint( $cacheLevel ) );

        // Remove the old option from EPC, if it exists.
        if ( $this->container->get( 'hasMustUsePlugin' ) && absint( get_option( 'endurance_cache_level', 0 ) ) ) {
            update_option( 'endurance_cache_level', 0 );
            delete_option( 'endurance_cache_level' );
        }
    }
}
