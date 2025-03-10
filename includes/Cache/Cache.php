<?php

namespace NewfoldLabs\WP\Module\Performance\Cache;

use NewfoldLabs\WP\ModuleLoader\Container;

use function NewfoldLabs\WP\Module\Performance\getCacheLevel;

/**
 * Cache manager.
 */
class Cache {

    /**
     * Dependency injection container.
     *
     * @var Container
     */
    protected $container;

    /**
     * Constructor.
     *
     * @param Container $container the container
     */
    public function __construct( Container $container ) {
        $this->container = $container;

        $cacheManager = new CacheManager( $container );
        $cachePurger  = new CachePurgingService( $cacheManager->getInstances() );

        $container->set( 'cachePurger', $cachePurger );

        new CacheExclusion( $container );

        $container->set( 'hasMustUsePlugin', file_exists( WPMU_PLUGIN_DIR . '/endurance-page-cache.php' ) );

        $this->hooks();

        add_action( 'plugins_loaded', array( $this, 'hooks2' ) );

    }

    /**
     * Add hooks.
     */
    public function hooks() {

        add_action( 'after_mod_rewrite_rules', array( $this, 'onRewrite' ) );

        add_action( 'newfold_container_set', array( $this, 'pluginHooks' ) );
        add_action( 'plugins_loaded', array( $this, 'hooks' ) );

        add_action( 'newfold_container_set', array( $this, 'pluginHooks' ) );
    }

    /**
     * When updating mod rewrite rules, also update our rewrites as appropriate.
     */
    public function onRewrite() {
        $this->onCacheLevelChange( getCacheLevel() );
    }

}
