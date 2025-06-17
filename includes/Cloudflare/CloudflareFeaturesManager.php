<?php

namespace NewfoldLabs\WP\Module\Performance\Cloudflare;

use NewfoldLabs\WP\Module\Performance\Fonts\FontSettings;
use NewfoldLabs\WP\Module\Performance\Images\ImageSettings;
use WP_Forge\WP_Htaccess_Manager\htaccess;

/**
 * Handles detection and tracking of Cloudflare Polish, Mirage, and Font Optimization.
 */
class CloudflareFeaturesManager {

	private const MARKER = 'Newfold CF Optimization Header';

	/**
	 * Constructor to register hooks for settings changes.
	 */
	public function __construct() {
		add_action( 'update_option_nfd_image_optimization', array( $this, 'on_image_optimization_change' ), 10, 2 );
		add_action( 'add_option_nfd_image_optimization', array( $this, 'on_image_optimization_change' ), 10, 2 );
		add_action( 'update_option_nfd_fonts_optimization', array( $this, 'on_fonts_optimization_change' ), 10, 2 );
		add_action( 'add_option_nfd_fonts_optimization', array( $this, 'on_fonts_optimization_change' ), 10, 2 );
		add_action( 'update_option_nfd_site_capabilities', array( $this, 'on_site_capabilities_change' ), 10, 2 );
		add_action( 'add_option_nfd_site_capabilities', array( $this, 'on_site_capabilities_change' ), 10, 2 );
	}

	/**
	 * Handles image optimization setting changes.
	 *
	 * @param array $old_value Previous value.
	 * @param array $new_value New value.
	 */
	public function on_image_optimization_change( $old_value, $new_value ) {
		$this->update_htaccess_header( $new_value, get_option( 'nfd_fonts_optimization', false ) );
	}

	/**
	 * Handles font optimization setting changes.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $new_value New value.
	 */
	public function on_fonts_optimization_change( $old_value, $new_value ) {
		$this->update_htaccess_header( get_option( 'nfd_image_optimization', array() ), $new_value );
	}

	/**
	 * Handles site capabilities change and triggers a settings refresh.
	 *
	 * @param mixed $old_value Previous value.
	 * @param mixed $new_value New value.
	 */
	public function on_site_capabilities_change( $old_value, $new_value ) {
		if ( is_array( $new_value ) ) {
			ImageSettings::maybe_refresh_with_capabilities( $new_value );
			FontSettings::maybe_refresh_with_capabilities( $new_value );
		}
	}

	/**
	 * Updates the .htaccess header based on current optimization settings.
	 *
	 * @param array $image_settings Array of image optimization settings.
	 * @param mixed $fonts_enabled  Whether font optimization is enabled.
	 */
	private function update_htaccess_header( $image_settings, $fonts_enabled ) {
		$images_cloudflare = isset( $image_settings['cloudflare'] ) ? $image_settings['cloudflare'] : array();
		$fonts_cloudflare  = isset( $fonts_enabled['cloudflare'] ) ? $fonts_enabled['cloudflare'] : array();

		$mirage_enabled     = ! empty( $images_cloudflare['mirage'] );
		$polish_enabled     = ! empty( $images_cloudflare['polish'] );
		$fonts_enabled_flag = ! empty( $fonts_cloudflare['fonts'] );

		$mirage_hash = $mirage_enabled ? substr( sha1( 'mirage' ), 0, 8 ) : '';
		$polish_hash = $polish_enabled ? substr( sha1( 'polish' ), 0, 8 ) : '';
		$fonts_hash  = $fonts_enabled_flag ? substr( sha1( 'fonts' ), 0, 8 ) : '';

		$header_value = "{$mirage_hash}-{$polish_hash}-{$fonts_hash}";
		$rules        = array();

		if ( $mirage_enabled || $polish_enabled || $fonts_enabled_flag ) {
			$rules = array(
				'<IfModule mod_headers.c>',
				"\tHeader set Set-Cookie \"nfd-enable-cf-opt={$header_value}; path=/; Max-Age=86400; HttpOnly\" env=nfd_cf_opt",
				'</IfModule>',
				'# Exclude admin and API paths',
				'SetEnvIf Request_URI "^/wp-admin/" no_nfd_cf',
				'SetEnvIf Request_URI "^/wp-json/" no_nfd_cf',
				'SetEnvIf Request_URI "^/xmlrpc.php" no_nfd_cf',
				'SetEnvIf Request_URI "^/wp-login.php" no_nfd_cf',
				'SetEnvIf Request_URI "^/admin-ajax.php" no_nfd_cf',
				'# Apply CF cookie on all non-admin, non-API requests',
				'SetEnvIf Request_URI ".*" nfd_cf_opt=!no_nfd_cf',
			);
		}

		$htaccess = new htaccess( self::MARKER );
		if ( empty( $rules ) ) {
			$htaccess->removeContent();
		} else {
			$htaccess->addContent( $rules );
		}
	}
}
