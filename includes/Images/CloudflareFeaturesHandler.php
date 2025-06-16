<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

use WP_Forge\WP_Htaccess_Manager\htaccess;

/**
 * Handles detection and tracking of Cloudflare Polish, Mirage, and Font Optimization.
 */
class CloudflareFeaturesHandler {

	private const MARKER = 'Newfold CF Optimization Header';

	/**
	 * Constructor to register hooks for settings changes.
	 */
	public function __construct() {
		add_action( 'update_option_nfd_image_optimization', array( $this, 'on_image_optimization_change' ), 10, 2 );
		add_action( 'update_option_nfd_fonts_optimization', array( $this, 'on_fonts_optimization_change' ), 10, 2 );
		add_action( 'update_option_nfd_site_capabilities', array( $this, 'on_site_capabilities_change' ), 10, 2 );
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
		}
	}

	/**
	 * Updates the .htaccess header based on current optimization settings.
	 *
	 * @param array $image_settings Array of image optimization settings.
	 * @param mixed $fonts_enabled  Whether font optimization is enabled.
	 */
	private function update_htaccess_header( $image_settings, $fonts_enabled ) {
		$cloudflare = isset( $image_settings['cloudflare'] ) ? $image_settings['cloudflare'] : array();

		$mirage_enabled = ! empty( $cloudflare['mirage'] );
		$polish_enabled = ! empty( $cloudflare['polish'] );
		$fonts_enabled  = ! empty( $fonts_enabled );

		$mirage_hash = $mirage_enabled ? substr( sha1( 'mirage' ), 0, 8 ) : '';
		$polish_hash = $polish_enabled ? substr( sha1( 'polish' ), 0, 8 ) : '';
		$fonts_hash  = $fonts_enabled ? substr( sha1( 'fonts' ), 0, 8 ) : '';

		$header_value = "{$mirage_hash}-{$polish_hash}-{$fonts_hash}";

		$rules = array();

		if ( $mirage_enabled || $polish_enabled || $fonts_enabled ) {
			$rules = array(
				'<IfModule mod_headers.c>',
				"\tHeader set X-NFD-CF-Optimization \"{$header_value}\" env=nfd_cf_opt",
				'</IfModule>',
				'# Match static asset requests',
				'SetEnvIf Request_URI "\\.(jpe?g|png|gif|webp|woff2?|ttf|otf|eot|css)$" nfd_cf_opt',
				'SetEnvIf Request_URI "fonts.googleapis.com" nfd_cf_opt',
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
