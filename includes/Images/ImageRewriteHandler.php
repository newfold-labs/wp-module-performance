<?php
/**
 * ImageRewriteHandler
 *
 * Manages registration/unregistration of .htaccess fragments that redirect image
 * requests to their optimized WebP counterparts. Uses the centralized
 * HtaccessApi fragment registry to ensure safe, debounced writes.
 *
 * @package NewfoldLabs\WP\Module\Performance\Images
 * @since 1.0.0
 */

namespace NewfoldLabs\WP\Module\Performance\Images;

use NewfoldLabs\WP\Module\Htaccess\Api as HtaccessApi;
use NewfoldLabs\WP\Module\Performance\Images\Fragments\MissingImageRedirectFragment;
use NewfoldLabs\WP\Module\Performance\Images\Fragments\ExistingImageRedirectFragment;

/**
 * Handles the management of .htaccess rules for optimized image redirects.
 *
 * This class listens for image optimization option changes and registers or
 * unregisters the appropriate fragments that:
 *  - Prefer WebP when the original exists and a .webp variant is available.
 *  - Serve a .webp file if the original asset is missing but the .webp exists.
 *
 * @since 1.0.0
 */
class ImageRewriteHandler {

	/**
	 * Human-friendly marker for the "missing image → .webp" rule block.
	 *
	 * Printed in the BEGIN/END comments inside the .htaccess file.
	 *
	 * @var string
	 */
	const MISSING_IMAGE_MARKER = 'Newfold WebP Missing Image Redirect';

	/**
	 * Human-friendly marker for the "existing image → prefer .webp" rule block.
	 *
	 * Printed in the BEGIN/END comments inside the .htaccess file.
	 *
	 * @var string
	 */
	const EXISTING_IMAGE_MARKER = 'Newfold WebP Existing Image Redirect';

	/**
	 * Globally-unique fragment ID for the "missing image → .webp" rules.
	 *
	 * @var string
	 */
	const FRAGMENT_ID_MISSING = 'nfd.images.webp.missing';

	/**
	 * Globally-unique fragment ID for the "existing image → prefer .webp" rules.
	 *
	 * @var string
	 */
	const FRAGMENT_ID_EXISTING = 'nfd.images.webp.existing';

	/**
	 * Constructor. Hooks settings listener.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Listen to image optimization settings changes.
		add_action( 'update_option_nfd_image_optimization', array( $this, 'on_image_setting_change' ), 10, 2 );
	}

	/**
	 * Register the "missing image → .webp" fragment.
	 *
	 * This fragment rewrites requests for non-existent original images to a .webp
	 * counterpart when it exists on disk.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_missing_image_rule(): void {
		HtaccessApi::register(
			new MissingImageRedirectFragment(
				self::FRAGMENT_ID_MISSING,
				self::MISSING_IMAGE_MARKER
			),
			true // queue apply to coalesce writes
		);
	}

	/**
	 * Register the "existing image → prefer .webp" fragment.
	 *
	 * This fragment rewrites requests for existing original images to a .webp
	 * counterpart when it exists (prefers optimized variant).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function add_existing_image_rule(): void {
		HtaccessApi::register(
			new ExistingImageRedirectFragment(
				self::FRAGMENT_ID_EXISTING,
				self::EXISTING_IMAGE_MARKER
			),
			true // queue apply to coalesce writes
		);
	}

	/**
	 * Unregister both image redirect fragments.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function remove_rules(): void {
		HtaccessApi::unregister( self::FRAGMENT_ID_MISSING );
		HtaccessApi::unregister( self::FRAGMENT_ID_EXISTING );
	}

	/**
	 * Activation hook: ensure relevant fragments are registered.
	 *
	 * Note: This registers both fragments; runtime settings may later
	 * unregister one/both via on_image_setting_change().
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function on_activation(): void {
		$this->add_missing_image_rule();
		$this->add_existing_image_rule();
	}

	/**
	 * Deactivation hook: remove all related fragments.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function on_deactivation(): void {
		$this->remove_rules();
	}

	/**
	 * Handle changes to image optimization settings.
	 *
	 * Expects the `nfd_image_optimization` option to be an associative array:
	 * - enabled (bool): master switch for image optimization features.
	 * - auto_optimized_uploaded_images['auto_delete_original_image'] (bool):
	 *     If true, enable "missing image → .webp" rewrite.
	 * - prefer_optimized_image_when_exists (bool):
	 *     If true, enable "existing image → prefer .webp" rewrite.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $old_value Previous settings value (unused by this handler).
	 * @param mixed $new_value New settings value.
	 * @return void
	 */
	public function on_image_setting_change( $old_value, $new_value ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundInImplementedInterface
		// Remove rules if the entire feature is disabled.
		if ( empty( $new_value['enabled'] ) ) {
			$this->remove_rules();
			return;
		}

		// Missing-image redirect toggle (auto-delete original images).
		if ( ! empty( $new_value['auto_optimized_uploaded_images']['auto_delete_original_image'] ) ) {
			$this->add_missing_image_rule();
		} else {
			HtaccessApi::unregister( self::FRAGMENT_ID_MISSING );
		}

		// Prefer-optimized-image toggle.
		if ( ! empty( $new_value['prefer_optimized_image_when_exists'] ) ) {
			$this->add_existing_image_rule();
		} else {
			HtaccessApi::unregister( self::FRAGMENT_ID_EXISTING );
		}
	}
}
