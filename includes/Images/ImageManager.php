<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

use NewfoldLabs\WP\Module\Performance\Permissions;
use NewfoldLabs\WP\Module\Performance\Images\RestApi\RestApi;

/**
 * Manages the initialization of image optimization settings and listeners.
 */
class ImageManager {
	/**
	 * Constructor to initialize the ImageManager.
	 * It registers settings and conditionally initializes services.
	 */
	public function __construct() {
		$this->initialize_settings();
		$this->maybe_initialize_upload_listener();
		$this->maybe_initialize_lazy_loader();
		$this->maybe_initialize_bulk_optimizer();
		$this->maybe_initialize_rest_api();
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
	private function maybe_initialize_upload_listener() {
		if ( ImageSettings::is_optimization_enabled() && ImageSettings::is_auto_optimization_enabled() ) {
			$auto_delete_original_image = ImageSettings::is_auto_delete_enabled();
			new ImageUploadListener( $auto_delete_original_image );
		}
	}

	/**
	 * Conditionally initializes the LazyLoader based on settings.
	 */
	private function maybe_initialize_lazy_loader() {
		if ( ImageSettings::is_optimization_enabled() && ImageSettings::is_lazy_loading_enabled() ) {
			new LazyLoader();
		}
	}

	/**
	 * Conditionally initializes the ImageBulkOptimizer only within `wp-admin`.
	 */
	private function maybe_initialize_bulk_optimizer() {
		if ( Permissions::is_authorized_admin() && ImageSettings::is_bulk_optimization_enabled() ) {
			new ImageBulkOptimizer();
		}
	}

	/**
	 * Conditionally initializes the REST API routes only when called via REST.
	 */
	private function maybe_initialize_rest_api() {
		if ( Permissions::rest_is_authorized_admin() ) {
			new RestApi();
		}
	}
}
