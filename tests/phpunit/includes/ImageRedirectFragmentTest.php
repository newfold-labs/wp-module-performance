<?php

namespace NewfoldLabs\WP\Module\Performance\Images\Fragments;

use WP_Mock;
use WP_Mock\Tools\TestCase;

/**
 * Test the WebP redirect fragments' htaccess rendering.
 */
class ImageRedirectFragmentTest extends TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		WP_Mock::setUp();
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
	}

	/**
	 * Both fragments, keyed by the label used in failure messages.
	 *
	 * @return array
	 */
	public function fragment_provider() {
		return array(
			'existing' => array( new ExistingImageRedirectFragment( 'nfd.images.webp.existing', 'Newfold WebP Existing' ) ),
			'missing'  => array( new MissingImageRedirectFragment( 'nfd.images.webp.missing', 'Newfold WebP Missing' ) ),
		);
	}

	/**
	 * Test the sibling lookup does not depend on the document root.
	 *
	 * DOCUMENT_ROOT combined with REQUEST_URI only resolves when the URL path
	 * mirrors a real path under the document root, which is not true for Alias
	 * or mismatched-docroot setups.
	 *
	 * @dataProvider fragment_provider
	 *
	 * @param object $fragment Fragment under test.
	 */
	public function test_sibling_lookup_is_docroot_independent( $fragment ) {
		$output = $fragment->render( null );

		$this->assertStringNotContainsString( 'DOCUMENT_ROOT', $output );
		$this->assertStringContainsString( 'RewriteCond %1.webp -f', $output );
	}

	/**
	 * Test the capture feeding %1 is taken from the mapped filesystem path.
	 *
	 * The pattern must be anchored so %1 holds the full path without its
	 * extension rather than a suffix of it.
	 *
	 * @dataProvider fragment_provider
	 *
	 * @param object $fragment Fragment under test.
	 */
	public function test_extension_capture_comes_from_request_filename( $fragment ) {
		$output = $fragment->render( null );

		$this->assertStringContainsString(
			'RewriteCond %{REQUEST_FILENAME} ^(.+)\.(gif|bmp|jpg|jpeg|png|tiff|svg|webp)$ [NC]',
			$output
		);
		$this->assertStringNotContainsString( 'RewriteCond %{REQUEST_URI}', $output );
	}

	/**
	 * Test the rewrite target and flags are preserved.
	 *
	 * @dataProvider fragment_provider
	 *
	 * @param object $fragment Fragment under test.
	 */
	public function test_rewrite_rule_serves_the_webp_variant( $fragment ) {
		$output = $fragment->render( null );

		$this->assertStringContainsString(
			'RewriteRule ^(.+)\.(gif|bmp|jpg|jpeg|png|tiff|svg|webp)$ $1.webp [T=image/webp,E=WEBP_REDIRECT:1,L]',
			$output
		);
		$this->assertStringContainsString( '<IfModule mod_rewrite.c>', $output );
	}

	/**
	 * Test the existing-image fragment only fires when the original is on disk.
	 */
	public function test_existing_fragment_requires_the_original_file() {
		$output = ( new ExistingImageRedirectFragment( 'nfd.images.webp.existing', 'Newfold WebP Existing' ) )->render( null );

		$this->assertStringContainsString( 'RewriteCond %{REQUEST_FILENAME} -f', $output );
		$this->assertStringNotContainsString( 'RewriteCond %{REQUEST_FILENAME} !-f', $output );
	}

	/**
	 * Test the missing-image fragment only fires when nothing is on disk.
	 */
	public function test_missing_fragment_requires_an_absent_file() {
		$output = ( new MissingImageRedirectFragment( 'nfd.images.webp.missing', 'Newfold WebP Missing' ) )->render( null );

		$this->assertStringContainsString( 'RewriteCond %{REQUEST_FILENAME} !-f', $output );
		$this->assertStringContainsString( 'RewriteCond %{REQUEST_FILENAME} !-d', $output );
	}

	/**
	 * Test the marker label wraps the rendered block.
	 *
	 * @dataProvider fragment_provider
	 *
	 * @param object $fragment Fragment under test.
	 */
	public function test_render_is_wrapped_in_marker_comments( $fragment ) {
		$output = $fragment->render( null );
		$label  = 'existing' === substr( $fragment->id(), -8 ) ? 'Newfold WebP Existing' : 'Newfold WebP Missing';

		$this->assertStringStartsWith( '# BEGIN ' . $label, $output );
		$this->assertStringEndsWith( '# END ' . $label, $output );
	}
}
