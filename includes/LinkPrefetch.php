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

		add_filter( 'newfold-runtime', array( $this, 'add_to_runtime' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueScripts' ) );
		add_filter( 'script_loader_tag', array( $this, 'addDefer' ), 10, 2 );
	}

	/**
	 * Add values to the runtime object.
	 *
	 * @param array $sdk The runtime object.
	 *
	 * @return array
	 */
	public function add_to_runtime( $sdk ) {
		$values = array(
			'settings' => get_option( 'nfd_link_prefetch_settings', static::getDefaultSettings() ),
		);
		return array_merge( $sdk, array( 'linkPrefetch' => $values ) );
	}
	/**
	 * Enqueue de script.
	 *
	 * return void
	 */
	public function enqueueScripts() {
		$settings = get_option( 'nfd_link_prefetch_settings', static::getDefaultSettings() );

		if ( ! $settings['activeOnDesktop'] && ! $settings['activeOnMobile'] ) {
			return;
		}

		$settings['isMobile'] = wp_is_mobile();

		wp_enqueue_script(
			'linkprefetcher',
			NFD_PERFORMANCE_BUILD_URL . '/link-prefetch.min.js',
			array(),
			$this->container->plugin()->version,
			true
		);
		wp_add_inline_script( 'linkprefetcher', 'window.LP_CONFIG = ' . wp_json_encode( $settings ), 'before' );
	}

	/**
	 * Get link prefetch default settings.
	 *
	 * return array
	 */
	public static function getDefaultSettings() {
		return array(
			'activeOnDesktop' => false,
			'behavior'        => 'mouseHover',
			'hoverDelay'      => 60,
			'instantClick'    => false,
			'activeOnMobile'  => false,
			'mobileBehavior'  => 'touchstart',
			'ignoreKeywords'  => '#,?',
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
