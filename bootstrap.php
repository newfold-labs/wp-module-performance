<?php

use NewfoldLabs\WP\Module\Performance\CacheTypes\Browser;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Cloudflare;
use NewfoldLabs\WP\Module\Performance\CacheTypes\File;
use NewfoldLabs\WP\Module\Performance\CacheTypes\Skip404;
use NewfoldLabs\WP\Module\Performance\Performance;
use NewfoldLabs\WP\Module\Performance\ResponseHeaderManager;
use NewfoldLabs\WP\ModuleLoader\Container;

use function NewfoldLabs\WP\Module\Performance\getCacheLevel;
use function NewfoldLabs\WP\ModuleLoader\register;

if ( function_exists( 'add_action' ) ) {

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
