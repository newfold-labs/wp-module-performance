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
		add_action( 'set_transient_nfd_site_capabilities', array( $this, 'on_site_capabilities_change' ), 10, 2 );
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
	 * Callback for when the `nfd_site_capabilities` transient is set.
	 *
	 * Triggers a refresh of image and font optimization settings based on updated site capabilities.
	 *
	 * @param mixed $value      The value being set in the transient.
	 * @param int   $expiration The expiration time in seconds.
	 */
	public function on_site_capabilities_change( $value, $expiration ) {
		if ( is_array( $value ) ) {
			ImageSettings::maybe_refresh_with_capabilities( $value );
			FontSettings::maybe_refresh_with_capabilities( $value );
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

		$mirage_enabled     = ! empty( $images_cloudflare['mirage']['value'] );
		$polish_enabled     = ! empty( $images_cloudflare['polish']['value'] );
		$fonts_enabled_flag = ! empty( $fonts_cloudflare['fonts']['value'] );

		$mirage_hash = $mirage_enabled ? substr( sha1( 'mirage' ), 0, 8 ) : '';
		$polish_hash = $polish_enabled ? substr( sha1( 'polish' ), 0, 8 ) : '';
		$fonts_hash  = $fonts_enabled_flag ? substr( sha1( 'fonts' ), 0, 8 ) : '';

		$header_value = "{$mirage_hash}{$polish_hash}{$fonts_hash}";
		$rules        = array();

		if ( $mirage_enabled || $polish_enabled || $fonts_enabled_flag ) {
			$rules = array(
				'<IfModule mod_rewrite.c>',
				"\tRewriteEngine On",
				"\t# Skip setting for admin/API routes",
				"\tRewriteCond %{REQUEST_URI} !/wp-admin/       [NC]",
				"\tRewriteCond %{REQUEST_URI} !/wp-login\\.php   [NC]",
				"\tRewriteCond %{REQUEST_URI} !/wp-json/        [NC]",
				"\tRewriteCond %{REQUEST_URI} !/xmlrpc\\.php     [NC]",
				"\tRewriteCond %{REQUEST_URI} !/admin-ajax\\.php [NC]",
				"\t# Skip if the exact cookie and value are already present",
				"\tRewriteCond %{HTTP_COOKIE} !(^|;\\s*)nfd-enable-cf-opt={$header_value} [NC]",
				"\t# Set env var if we passed all conditions",
				"\tRewriteRule .* - [E=CF_OPT:1]",
				'</IfModule>',
				'<IfModule mod_headers.c>',
				"\t# Set cookie only if env var is present (i.e., exact cookie not found)",
				"\tHeader set Set-Cookie \"nfd-enable-cf-opt={$header_value}; path=/; Max-Age=86400; HttpOnly\" env=CF_OPT",
				'</IfModule>',
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
