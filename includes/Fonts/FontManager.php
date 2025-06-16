<?php

namespace NewfoldLabs\WP\Module\Performance\Fonts;

use NewfoldLabs\WP\Module\Performance\Cloudflare\CloudflareFeaturesManager;
use NewfoldLabs\WP\ModuleLoader\Container;
use NewfoldLabs\WP\Module\Performance\Fonts\FontSettings;
use NewfoldLabs\WP\Module\Performance\Permissions;

/**
 * Manages the initialization of font optimization settings and listeners.
 */
class FontManager {

	/**
	 * Constructor to initialize the FontManager.
	 *
	 * Registers settings and conditionally initializes related services.
	 *
	 * @param Container $container Dependency injection container.
	 */
	public function __construct( Container $container ) {
		$this->initialize_settings( $container );
		$this->maybe_initialize_cloudflare_fonts_handler();
	}

	/**
	 * Initializes the FontSettings class to register settings.
	 *
	 * @param Container $container Dependency injection container.
	 */
	private function initialize_settings( Container $container ) {
		new FontSettings( $container );
	}

	/**
	 * Initializes Cloudflare-related font optimization handler if optimization is enabled.
	 */
	private function maybe_initialize_cloudflare_fonts_handler() {
		if ( Permissions::rest_is_authorized_admin() ) {
			new CloudflareFeaturesManager();
		}
	}
}
