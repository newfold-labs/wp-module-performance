<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Manages the initialization of image optimization settings and listeners.
 */
class ImageManager {
	/**
	 * Constructor to initialize the ImageManager.
	 * It registers settings and initializes the listener conditionally.
	 */
	public function __construct() {
		$this->initialize_settings();
		$this->maybe_initialize_listener();
		$this->maybe_initialize_lazy_loader();
	}

	/**
	 * Initializes the ImageSettings class to register settings.
	 */
	private function initialize_settings() {
		new ImageSettings();
	}

	/**
	 * Conditionally initializes the ImageUploadListener based on the settings.
	 */
	private function maybe_initialize_listener() {
		if ( ImageSettings::is_optimization_enabled() && ImageSettings::is_auto_optimization_enabled() ) {
			$auto_delete_original_image = ImageSettings::is_auto_delete_enabled();
			new ImageUploadListener( $auto_delete_original_image );
		}
	}

	/**
	 * Conditionally initializes the LazyLoader based on the lazy loading setting.
	 */
	private function maybe_initialize_lazy_loader() {
		if ( ImageSettings::is_optimization_enabled() && ImageSettings::is_lazy_loading_enabled() ) {
			new LazyLoader();
		}
	}
}
