<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Manages the initialization of image optimization settings and listeners.
 */
class ImageManager {
	/**
	 * Constructor to initialize the ImagesManager.
	 * It registers settings and initializes the listener conditionally.
	 */
	public function __construct() {
		$this->initialize_settings();
		$this->maybe_initialize_listener();
	}

	/**
	 * Initializes the ImagesSettings class to register settings.
	 */
	private function initialize_settings() {
		new ImageSettings();
	}

	/**
	 * Conditionally initializes the ImagesListener based on the settings.
	 */
	private function maybe_initialize_listener() {
		if ( ImageSettings::is_optimization_enabled() && ImageSettings::is_auto_optimization_enabled() ) {
			$auto_delete_original_image = ImageSettings::is_auto_delete_enabled();
			new ImageUploadListener( $auto_delete_original_image );
		}
	}
}
