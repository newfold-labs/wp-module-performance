<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

use WP_Mock;
use WP_Mock\Tools\TestCase;
use Patchwork;

/**
 * Test health checks.
 */
class ImageLazyLoaderTest extends TestCase {

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		WP_Mock::setUp();
		Patchwork\restoreAll(); // Ensure Patchwork starts with a clean slate.

		WP_Mock::passthruFunction( '__' );
		WP_Mock::passthruFunction( 'esc_html__' );
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		WP_Mock::tearDown();
		Patchwork\restoreAll(); // Clean up all redefined functions/constants.
	}

	/**
	 * Test the ImageLazyLoader class functionality.
	 */
	public function test_image_lazy_loader() {
		WP_Mock::expectActionAdded(
			'wp_enqueue_scripts',
			[ WP_Mock\Functions::type( ImageLazyLoader::class ), 'enqueue_lazy_loader' ]
		);

		$filters_to_mock = array(
			'the_content',
			'post_thumbnail_html',
			'widget_text',
			'get_avatar',
		);

		foreach ( $filters_to_mock as $filter ) {
			WP_Mock::expectFilterAdded(
				$filter,
				[ WP_Mock\Functions::type( ImageLazyLoader::class ), 'apply_lazy_loading' ]
			);
		}

		WP_Mock::expectFilterAdded(
			'render_block',
			[ WP_Mock\Functions::type( ImageLazyLoader::class ), 'apply_lazy_loading_to_blocks' ],
			10,
			2
		);

		$image_lazy_loader = new ImageLazyLoader();
		$html = '<form>
		<input type="hidden" value="1">
		<input name="provider" value="test input" type="hidden">
		<script type="text/template">
			<div>
				Text script tag with html first	</div>
		</script>
		<script type="text/template">
			<div>Text script tag with html second</div>
		</script>
		<h2>Test Title</h2>
		</form>';
		$content = $image_lazy_loader->apply_lazy_loading( $html );
		$this->assertSame($html, $content, 'Content should remain unchanged without images.');

		$html = '<iframe<!-- [et_pb_line_break_holder] -->  src="https://example.com/some_url"<!-- [et_pb_line_break_holder] --> ></iframe>';
		$content = $image_lazy_loader->apply_lazy_loading( $html );
		$this->assertSame($html, $content, 'Content should remain unchanged without images.');
	}
}
