<?php

namespace NewfoldLabs\WP\Module\Performance;

use NewfoldLabs\WP\ModuleLoader\Container;

/**
 * Link Prefetch Class
 */
class LinkPrefetch {
	/**
	 * Dependency injection container.
	 *
	 * @var Container
	 */
	protected $container;

	/**
	 * Constructor.
	 *
	 * @param Container $container the container
	 */
	public function __construct( Container $container ) {
		$this->container = $container;

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'addDefer' ), 10, 2 );
	}
	/**
	 * Enqueue de script.
	 *
	 * return void
	 */
	public function enqueueScripts() {
		$plugin_url = $this->container->plugin()->url . $this->getScriptPath();
		$settings   = get_option( 'nfd_linkPrefetch', $this->getDefaultSettings() );

		if ( ! $settings['activeOnDesktop'] && ! $settings['activeOnMobile'] ) { return; }

		$settings['isMobile'] = wp_is_mobile();

		wp_enqueue_script( 'linkprefetcher', $plugin_url, array(), $this->container->plugin()->version, true );
		wp_add_inline_script( 'linkprefetcher', 'window.LP_CONFIG = ' . wp_json_encode( $settings ), 'before' );
	}

	/**
	 * Get js script path.
	 *
	 * return string
	 */
	public function getScriptPath() {
		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		return 'vendor/newfold-labs/wp-module-performance/assets/js/linkPrefetch' . $suffix . '.js';
	}

	/**
	 * Get link prefetch default settings.
	 *
	 * return array
	 */
	public function getDefaultSettings() {
		return array(
			'activeOnDesktop' => false,
			'behavior'        => 'mouseHover',
			'hoverDelay'      => 60,
			'instantClick'    => false,
			'activeOnMobile'  => false,
			'mobileBehavior'  => 'viewport',
			'ignoreKeywords'  => 'wp-admin,#,?',
		);
	}

	/**
	 * Add defer attribute to the script.
	 *
	 * @param string $tag html tag.
	 * @param string $handle handle of the script.
	 *
	 * return string
	 */
	public function addDefer( $tag, $handle ) {
		if ( 'linkprefetcher' === $handle && false === strpos( $tag, 'defer' ) ) {
			$tag = preg_replace( ':(?=></script>):', ' defer', $tag );
		}
		return $tag;
	}
}
