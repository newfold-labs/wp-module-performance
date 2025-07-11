<?php

namespace NewfoldLabs\WP\Module\Performance\LinkPrefetch;

use NewfoldLabs\WP\Module\Data\SiteCapabilities;
use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Handles link prefetch functionality.
 */
class LinkPrefetch {

	/**
	 * Allowed behavior values.
	 *
	 * @var array
	 */
	public const VALID_BEHAVIORS = array( 'mouseHover', 'mouseDown' );

	/**
	 * Allowed mobile behavior values.
	 *
	 * @var array
	 */
	public const VALID_MOBILE_BEHAVIORS = array( 'touchstart', 'viewport' );

	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Option name for link prefetch settings.
	 *
	 * @var string
	 */
	public static $option_name = 'nfd_link_prefetch_settings';

	/**
	 * Site capabilities for link prefetch Click.
	 *
	 * @var bool
	 */
	public static $has_link_prefetch_click = false;

	/**
	 * Site capabilities for link prefetch Hover.
	 *
	 * @var bool
	 */
	public static $has_link_prefetch_hover = false;

	/**
	 * Default settings.
	 *
	 * @var array
	 */
	public static $default_settings = array(
		'activeOnDesktop' => false,
		'behavior'        => 'mouseHover',
		'hoverDelay'      => 60,
		'instantClick'    => false,
		'activeOnMobile'  => false,
		'mobileBehavior'  => 'touchstart',
		'ignoreKeywords'  => '#,?',
	);

	/**
	 * Constructor.
	 *
	 * @param Container $container The dependency injection container.
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		$capabilities = ( new SiteCapabilities() )->all();

		$this::$has_link_prefetch_click = array_key_exists( 'hasLinkPrefetchClick', $capabilities ) ? $capabilities['hasLinkPrefetchClick'] : null;
		$this::$has_link_prefetch_hover = array_key_exists( 'hasLinkPrefetchHover', $capabilities ) ? $capabilities['hasLinkPrefetchHover'] : null;

		if ( false === $this::$has_link_prefetch_click && false === $this::$has_link_prefetch_hover ) {
			delete_option( self::$option_name );
			return;
		}

		add_filter( 'newfold-runtime', array( $this, 'add_to_runtime' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		if ( ! is_admin() ) {
			add_filter( 'script_loader_tag', array( $this, 'add_defer' ), 10, 2 );
		}
	}

	/**
	 * Adds values to the runtime object.
	 *
	 * @param array $sdk The runtime object.
	 *
	 * @return array Modified runtime object.
	 */
	public function add_to_runtime( $sdk ) {
		$current_settings = get_option( self::$option_name, false );

		if ( false === $current_settings ) {
			if ( $this::$has_link_prefetch_click || $this::$has_link_prefetch_hover ) {
				self::$default_settings['activeOnDesktop'] = true;
				self::$default_settings['activeOnMobile']  = true;
			}

			if ( $this::$has_link_prefetch_click ) {
				self::$default_settings['behavior']       = 'mouseDown';
				self::$default_settings['mobileBehavior'] = 'touchstart';
			}

			if ( $this::$has_link_prefetch_hover ) {
				self::$default_settings['behavior']       = 'mouseHover';
				self::$default_settings['mobileBehavior'] = 'viewport';
			}

			$current_settings = self::$default_settings;
		}

		return array_merge(
			$sdk,
			array( 'linkPrefetch' => array( 'settings' => $current_settings ) )
		);
	}

	/**
	 * Enqueues the link prefetch script.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		$settings = get_option( self::$option_name, self::$default_settings );
		if ( ! $settings['activeOnDesktop'] && ! $settings['activeOnMobile'] ) {
			return;
		}
		$settings['isMobile'] = wp_is_mobile();
		wp_enqueue_script(
			'linkprefetcher',
			NFD_PERFORMANCE_BUILD_URL . '/assets/link-prefetch.min.js',
			array(),
			$this->container->plugin()->version,
			true
		);
		wp_add_inline_script(
			'linkprefetcher',
			'window.LP_CONFIG = ' . wp_json_encode( $settings ),
			'before'
		);
	}

	/**
	 * Adds a defer attribute to the script tag.
	 *
	 * @param string $tag    The HTML script tag.
	 * @param string $handle The handle of the script.
	 *
	 * @return string Modified HTML script tag.
	 */
	public function add_defer( $tag, $handle ) {
		if ( 'linkprefetcher' === $handle && false === strpos( $tag, 'defer' ) ) {
			$tag = preg_replace( ':(?=></script>):', ' defer', $tag );
		}
		return $tag;
	}

	/**
	 * Retrieves the current link prefetch settings.
	 *
	 * @return array Current settings.
	 */
	public static function get_settings() {
		return get_option( self::$option_name, self::$default_settings );
	}

	/**
	 * Updates the link prefetch settings.
	 *
	 * @param array $settings The settings to update.
	 *
	 * @return boolean
	 */
	public static function update_settings( $settings ) {
		return update_option( self::$option_name, $settings );
	}
}
