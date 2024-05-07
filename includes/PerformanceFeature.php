<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Performance\Performance;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Browser;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Cloudflare;
use NewfoldLabs\WP\Module\Performance\CacheTypes\File;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Skip404;
use NewfoldLabs\WP\Module\Performance\ResponseHeaderManager;

use function NewfoldLabs\WP\Module\Performance\getCacheLevel;
use function NewfoldLabs\WP\ModuleLoader\register;

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
    protected $value = true; // default to on

    /**
     * Initialize performance feature
     * 
     */
    public function initialize() {
        if ( function_exists( 'add_action' ) ) {

            // Register module
            add_action(
                'plugins_loaded',
                function () {
                    register(
                        [
                            'name'     => 'performance',
                            'label'    => __( 'Performance', 'newfold' ),
                            'callback' => function ( Container $container ) {
                                new Performance( $container );
                            },
                            'isActive' => true,
                            'isHidden' => true,
                        ]
                    );

                }
            );

            // Container Hooks
            add_action(
                'newfold_container_set',
                function ( Container $container ) {

                    register_activation_hook(
                        $container->plugin()->file,
                        function () use ( $container ) {
        
                            Skip404::onActivation();
                            File::onActivation();
                            Browser::onActivation();
        
                            // Add headers to .htaccess
                            $responseHeaderManager = new ResponseHeaderManager();
                            $responseHeaderManager->addHeader( 'X-Newfold-Cache-Level', absint( getCacheLevel() ) );
        
                        }
                    );
        
                    register_deactivation_hook(
                        $container->plugin()->file,
                        function () use ( $container ) {
        
                            Skip404::onDeactivation();
                            File::onDeactivation();
                            Browser::onDeactivation();
        
                            // Remove all headers from .htaccess
                            $responseHeaderManager = new ResponseHeaderManager();
                            $responseHeaderManager->removeAllHeaders();
                        }
                    );
                }
            );

        }
    }

}