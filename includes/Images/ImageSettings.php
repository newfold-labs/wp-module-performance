<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

/**
 * Manages the registration and sanitization of image optimization settings.
 */
class ImageSettings {
	/**
	 * The setting key for image optimization.
	 */
	private const SETTING_KEY = 'nfd_image_optimization';

	/**
	 * Constructor to initialize the settings and the listener.
	 */
	public function __construct() {
		$this->register_settings();
	}

	/**
	 * Registers the `nfd_image_optimization` setting in WordPress.
	 */
	private function register_settings() {
		register_setting(
			'general',
			self::SETTING_KEY,
			array(
				'type'              => 'object',
				'description'       => __( 'Settings for NFD Image Optimization.', 'wp-module-performance' ),
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'enabled'                        => true,
					'auto_optimized_uploaded_images' => array(
						'enabled'                    => true,
						'auto_delete_original_image' => true,
					),
				),
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'properties'           => array(
							'enabled' => array(
								'type'        => 'boolean',
								'description' => __( 'Enable image optimization.', 'wp-module-performance' ),
								'default'     => false,
							),
							'auto_optimized_uploaded_images' => array(
								'type'        => 'object',
								'description' => __( 'Settings for auto-optimized uploaded images.', 'wp-module-performance' ),
								'properties'  => array(
									'enabled' => array(
										'type'        => 'boolean',
										'description' => __( 'Automatically optimize uploaded images.', 'wp-module-performance' ),
										'default'     => false,
									),
									'auto_delete_original_image' => array(
										'type'        => 'boolean',
										'description' => __( 'Automatically delete original uploaded image.', 'wp-module-performance' ),
										'default'     => false,
									),
								),
							),
						),
						'additionalProperties' => false, // Disallow undefined properties
					),
				),
			)
		);
	}





	/**
	 * Sanitizes the `nfd_image_optimization` settings.
	 *
	 * @param array $settings The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_settings( $settings ) {
		return array(
			'enabled'                        => ! empty( $settings['enabled'] ),
			'auto_optimized_uploaded_images' => array(
				'enabled'                    => ! empty( $settings['auto_optimized_uploaded_images']['enabled'] ),
				'auto_delete_original_image' => ! empty( $settings['auto_optimized_uploaded_images']['auto_delete_original_image'] ),
			),
		);
	}

	/**
	 * Checks if image optimization is enabled.
	 *
	 * @return bool True if optimization is enabled, false otherwise.
	 */
	public static function is_optimization_enabled() {
		$settings = get_option( self::SETTING_KEY, array() );
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Checks if auto-optimization for uploaded images is enabled.
	 *
	 * @return bool True if auto-optimization is enabled, false otherwise.
	 */
	public static function is_auto_optimization_enabled() {
		$settings = get_option( self::SETTING_KEY, array() );
		return ! empty( $settings['auto_optimized_uploaded_images']['enabled'] );
	}

	/**
	 * Checks if auto-deletion of the original image is enabled.
	 *
	 * @return bool True if auto-deletion is enabled, false otherwise.
	 */
	public static function is_auto_delete_enabled() {
		$settings = get_option( self::SETTING_KEY, array() );
		return ! empty( $settings['auto_optimized_uploaded_images']['auto_delete_original_image'] );
	}
}
