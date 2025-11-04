<?php

namespace NewfoldLabs\WP\Module\Performance\Images;

use DOMDocument;
use Exception;

/**
 * Manages the initialization and application of lazy loading for images.
 */
class ImageLazyLoader {

	/**
	 * Exclusion rules for lazy loading.
	 * Defines classes and attributes that should prevent lazy loading from being applied to specific images.
	 *
	 * @var array
	 */
	private static $exclusions = array(
		'classes'    => array(
			'nfd-performance-not-lazy',
			'a3-notlazy',
			'disable-lazyload',
			'no-lazy',
			'no-lazyload',
			'skip-lazy',
		),
		'attributes' => array(
			'data-lazy-src',
			'data-crazy-lazy="exclude"',
			'data-no-lazy',
			'data-no-lazy="1"',
		),
	);

	/**
	 * List of content filters where lazy loading will be applied.
	 * These filters modify various types of WordPress content.
	 *
	 * @var array
	 */
	private static $content_filters = array(
		'the_content',
		'post_thumbnail_html',
		'widget_text',
		'get_avatar',
	);

	/**
	 * Constructor to initialize the lazy loading feature.
	 */
	public function __construct() {
		// Enqueue the lazy loader script with inline settings.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_lazy_loader' ) );

		// Add filters to apply lazy loading to various content types.
		foreach ( self::$content_filters as $filter ) {
			add_filter( $filter, array( $this, 'apply_lazy_loading' ) );
		}

		// Hook into Gutenberg block rendering to apply lazy loading.
		add_filter( 'render_block', array( $this, 'apply_lazy_loading_to_blocks' ), 10, 2 );
	}

	/**
	 * Applies lazy loading to images within Gutenberg blocks.
	 *
	 * @param string $block_content The HTML content of the block.
	 * @param array  $block The block data array.
	 * @return string Modified block content with lazy loading applied.
	 */
	public function apply_lazy_loading_to_blocks( $block_content, $block ) {
		// Only target core/image blocks or other blocks with images.
		if ( 'core/image' === $block['blockName'] || strpos( $block_content, '<img' ) !== false ) {
			return $this->apply_lazy_loading( $block_content );
		}

		return $block_content;
	}

	/**
	 * Enqueues the lazy loader script file and adds inline exclusion settings.
	 */
	public function enqueue_lazy_loader() {
		$script_path = NFD_PERFORMANCE_BUILD_DIR . '/assets/image-lazy-loader.min.js';
		$script_url  = NFD_PERFORMANCE_BUILD_URL . '/assets/image-lazy-loader.min.js';

		// Register the script with version based on file modification time.
		wp_register_script(
			'nfd-performance-lazy-loader',
			$script_url,
			array(),
			file_exists( $script_path ) ? filemtime( $script_path ) : false,
			true
		);

		// Inject the exclusion settings into the script.
		wp_add_inline_script(
			'nfd-performance-lazy-loader',
			$this->get_inline_script(),
			'before'
		);

		wp_enqueue_script( 'nfd-performance-lazy-loader' );
	}

	/**
	 * Generates the inline script to define lazy loading exclusions.
	 * This script populates the `window.nfdPerformance` object with exclusion rules.
	 *
	 * @return string JavaScript code to inline.
	 */
	private function get_inline_script() {
		return 'window.nfdPerformance = window.nfdPerformance || {};
        window.nfdPerformance.imageOptimization = window.nfdPerformance.imageOptimization || {};
        window.nfdPerformance.imageOptimization.lazyLoading = ' . wp_json_encode( self::$exclusions ) . ';';
	}

	/**
	 * Cleans up content by replacing specific patterns with replacements.
	 * This method is used to sanitize or modify content before lazy loading is applied.
	 *
	 * @param string $pattern Regular expression pattern to match.
	 * @param string $search String to search for in the content.
	 * @param string $replace String to replace the search string with.
	 * @param string $content The content to be cleaned.
	 * @return string The cleaned content.
	 */
	public function clean_content( $pattern, $search, $replace, $content ) {

		if ( empty( $content ) || empty( $pattern ) || empty( $search ) ) {
			return $content;
		}

		$content = preg_replace_callback(
			$pattern,
			function ( $matches ) use ( $search, $replace ) {
				$cleanedIframe = str_replace( $search, $replace, $matches[0] );
				return $cleanedIframe;
			},
			$content
		);
		return $content;
	}

	/**
	 * Applies lazy loading to images in HTML content.
	 * Skips images with specified exclusion classes or attributes.
	 *
	 * @param string $content The HTML content to process.
	 * @return string Modified HTML content with lazy loading applied, or original content on error.
	 */
	public function apply_lazy_loading( $content ) {
		// Return unmodified content if it is empty.
		if ( empty( $content ) ) {
			return $content;
		}

		$exclusion_classes    = self::$exclusions['classes'];
		$exclusion_attributes = self::$exclusions['attributes'];

		$content = preg_replace_callback(
			'/<img\b([^>]*)>/i',
			function ( $matches ) use ( $exclusion_classes, $exclusion_attributes ) {
				$img_tag = $matches[0];

				// check for exclusion classes
				if ( preg_match( '/class=["\']([^"\']+)["\']/', $img_tag, $class_match ) ) {
					$classes = explode( ' ', $class_match[1] );
					foreach ( $exclusion_classes as $excluded ) {
						if ( in_array( $excluded, $classes, true ) ) {
							return $img_tag;
						}
					}
				}

				// Check for exclusion attributes
				foreach ( $exclusion_attributes as $excluded_attr ) {
					if ( preg_match( '/' . preg_quote( $excluded_attr, '/' ) . '(\s*=\s*["\'][^"\']*["\'])?/', $img_tag ) ) {
						return $img_tag;
					}
				}

				// Not add lazy if already present
				if ( preg_match( '/\bloading\s*=\s*["\']?lazy["\']?/i', $img_tag ) ) {
					return $img_tag;
				}

				// add loading="lazy" attribute
				return preg_replace( '/<img\b/', '<img loading="lazy"', $img_tag, 1 );
			},
			$content
		);

		return $content;
	}
}
