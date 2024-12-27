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
	 * Default settings for image optimization.
	 *
	 * @var array
	 */
	private const DEFAULT_SETTINGS = array(
		'enabled'                            => false,
		'bulk_optimization'                  => false,
		'prefer_optimized_image_when_exists' => false,
		'auto_optimized_uploaded_images'     => array(
			'enabled'                    => false,
			'auto_delete_original_image' => false,
		),
		'lazy_loading'                       => array(
			'enabled' => false,
		),
	);

	/**
	 * Constructor to initialize the settings and the listener.
	 */
	public function __construct() {
		$this->register_settings();
		$this->initialize_settings();
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
				'default'           => self::DEFAULT_SETTINGS,
				'show_in_rest'      => array(
					'schema' => array(
						'type'                 => 'object',
						'properties'           => array(
							'enabled'           => array(
								'type'        => 'boolean',
								'description' => __( 'Enable image optimization.', 'wp-module-performance' ),
								'default'     => self::DEFAULT_SETTINGS['enabled'],
							),
							'prefer_optimized_image_when_exists' => array(
								'type'        => 'boolean',
								'description' => __( 'Prefer WebP format when it exists.', 'wp-module-performance' ),
								'default'     => self::DEFAULT_SETTINGS['prefer_optimized_image_when_exists'],
							),
							'auto_optimized_uploaded_images' => array(
								'type'        => 'object',
								'description' => __( 'Auto-optimized uploaded images settings.', 'wp-module-performance' ),
								'properties'  => array(
									'enabled' => array(
										'type'        => 'boolean',
										'description' => __( 'Automatically optimize uploaded images.', 'wp-module-performance' ),
										'default'     => self::DEFAULT_SETTINGS['auto_optimized_uploaded_images']['enabled'],
									),
									'auto_delete_original_image' => array(
										'type'        => 'boolean',
										'description' => __( 'Delete the original uploaded image after optimization.', 'wp-module-performance' ),
										'default'     => self::DEFAULT_SETTINGS['auto_optimized_uploaded_images']['auto_delete_original_image'],
									),
								),
							),
							'lazy_loading'      => array(
								'type'        => 'object',
								'description' => __( 'Settings for lazy loading.', 'wp-module-performance' ),
								'properties'  => array(
									'enabled' => array(
										'type'        => 'boolean',
										'description' => __( 'Enable lazy loading.', 'wp-module-performance' ),
										'default'     => self::DEFAULT_SETTINGS['lazy_loading']['enabled'],
									),
								),
							),
							'bulk_optimization' => array(
								'type'        => 'boolean',
								'description' => __( 'Enable bulk optimization of images.', 'wp-module-performance' ),
								'default'     => self::DEFAULT_SETTINGS['bulk_optimization'],
							),
						),
						'additionalProperties' => false,
					),
				),
			)
		);
	}

	/**
	 * Initializes the setting if it does not exist.
	 */
	private function initialize_settings() {
		$current_settings = get_option( self::SETTING_KEY, false );

		// If the settings do not exist, initialize them with default values.
		if ( false === $current_settings ) {
			add_option( self::SETTING_KEY, self::DEFAULT_SETTINGS );
		}
	}

	/**
	 * Sanitizes the `nfd_image_optimization` settings.
	 *
	 * @param array $settings The input settings.
	 * @return array The sanitized settings.
	 */
	public function sanitize_settings( $settings ) {
		return array(
			'enabled'                            => ! empty( $settings['enabled'] ),
			'prefer_optimized_image_when_exists' => ! empty( $settings['prefer_optimized_image_when_exists'] ),
			'auto_optimized_uploaded_images'     => array(
				'enabled'                    => ! empty( $settings['auto_optimized_uploaded_images']['enabled'] ),
				'auto_delete_original_image' => ! empty( $settings['auto_optimized_uploaded_images']['auto_delete_original_image'] ),
			),
			'lazy_loading'                       => array(
				'enabled' => ! empty( $settings['lazy_loading']['enabled'] ),
			),
			'bulk_optimization'                  => ! empty( $settings['bulk_optimization'] ),
		);
	}

	/**
	 * Checks if image optimization is enabled.
	 *
	 * @return bool True if optimization is enabled, false otherwise.
	 */
	public static function is_optimization_enabled() {
		$settings = get_option( self::SETTING_KEY, self::DEFAULT_SETTINGS );
		return ! empty( $settings['enabled'] );
	}

	/**
	 * Checks if auto-optimization for uploaded images is enabled.
	 *
	 * @return bool True if auto-optimization is enabled, false otherwise.
	 */
	public static function is_auto_optimization_enabled() {
		$settings = get_option( self::SETTING_KEY, self::DEFAULT_SETTINGS );
		return ! empty( $settings['auto_optimized_uploaded_images']['enabled'] );
	}

	/**
	 * Checks if auto-deletion of the original image is enabled.
	 *
	 * @return bool True if auto-deletion is enabled, false otherwise.
	 */
	public static function is_auto_delete_enabled() {
		$settings = get_option( self::SETTING_KEY, self::DEFAULT_SETTINGS );
		return ! empty( $settings['auto_optimized_uploaded_images']['auto_delete_original_image'] );
	}

	/**
	 * Checks if lazy loading is enabled.
	 *
	 * @return bool True if lazy loading is enabled, false otherwise.
	 */
	public static function is_lazy_loading_enabled() {
		$settings = get_option( self::SETTING_KEY, self::DEFAULT_SETTINGS );
		return ! empty( $settings['lazy_loading']['enabled'] );
	}

	/**
	 * Checks if bulk optimization is enabled.
	 *
	 * @return bool True if bulk optimization is enabled, false otherwise.
	 */
	public static function is_bulk_optimization_enabled() {
		$settings = get_option( self::SETTING_KEY, self::DEFAULT_SETTINGS );
		return ! empty( $settings['bulk_optimization'] );
	}

	/**
	 * Checks if WebP preference is enabled.
	 *
	 * @return bool True if WebP preference is enabled, false otherwise.
	 */
	public static function is_webp_preference_enabled() {
		$settings = get_option( self::SETTING_KEY, self::DEFAULT_SETTINGS );
		return ! empty( $settings['prefer_optimized_image_when_exists'] );
	}
}
