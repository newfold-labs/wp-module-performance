<?php

namespace NewfoldLabs\WP\Module\Performance\CacheTypes;

use NewfoldLabs\WP\Module\Performance\OptionListener;
use NewfoldLabs\WP\Module\Performance\Performance;
use NewfoldLabs\WP\ModuleLoader\Container;

use function NewfoldLabs\WP\Module\Performance\getSkip404Option;
use function WP_Forge\WP_Htaccess_Manager\addContent;
use function WP_Forge\WP_Htaccess_Manager\removeMarkers;

class Skip404 extends CacheBase {

	/**
	 * The file marker name.
	 */
	const MARKER = 'Newfold Skip 404 Handling for Static Files';

	/**
	 * Whether or not the code for this cache type should be loaded.
	 *
	 * @param Container $container
	 *
	 * @return bool
	 */
	public static function shouldEnable( Container $container ) {
		return (bool) $container->has( 'isApache' ) && $container->get( 'isApache' );
	}

	/**
	 * Constructor.
	 */
	public function __construct() {

		new OptionListener( Performance::OPTION_SKIP_404, [ __CLASS__, 'maybeAddRules' ] );

		add_filter( 'newfold_update_htaccess', [ $this, 'onUpdateHtaccess' ] );
		add_action( 'admin_init', [ $this, 'registerSettings' ] );
	}

	/**
	 * Register our setting to the main performance settings section.
	 */
	public function registerSettings() {

		add_settings_field(
			Performance::OPTION_SKIP_404,
			__( 'Skip WordPress 404 Handling For Static Files', 'newfold-performance-module' ),
			'NewfoldLabs\\WP\\Module\\Performance\\getSkip404InputField',
			'general',
			Performance::SETTINGS_SECTION
		);

		register_setting( 'general', Performance::OPTION_SKIP_404 );

	}

	/**
	 * When updating .htaccess, also update our rules as appropriate.
	 */
	public function onUpdateHtaccess() {
		self::maybeAddRules( getSkip404Option() );
	}

	/**
	 * Conditionally add or remove .htaccess rules based on option value.
	 *
	 * @param bool|null $shouldSkip404Handling
	 */
	public static function maybeAddRules( $shouldSkip404Handling ) {
		(bool) $shouldSkip404Handling ? self::addRules() : self::removeRules();
	}

	/**
	 * Add our rules to the .htacces file.
	 */
	public static function addRules() {
		$content = <<<HTACCESS
<IfModule mod_rewrite.c>
	RewriteEngine On
	RewriteCond %{REQUEST_FILENAME} !-f
	RewriteCond %{REQUEST_FILENAME} !-d
	RewriteCond %{REQUEST_URI} !(robots\.txt|ads\.txt|[a-z0-9_\-]*sitemap[a-z0-9_\.\-]*\.(xml|xsl|html)(\.gz)?)
	RewriteCond %{REQUEST_URI} \.(css|htc|less|js|js2|js3|js4|html|htm|rtf|rtx|txt|xsd|xsl|xml|asf|asx|wax|wmv|wmx|avi|avif|avifs|bmp|class|divx|doc|docx|eot|exe|gif|gz|gzip|ico|jpg|jpeg|jpe|webp|json|mdb|mid|midi|mov|qt|mp3|m4a|mp4|m4v|mpeg|mpg|mpe|webm|mpp|otf|_otf|odb|odc|odf|odg|odp|ods|odt|ogg|ogv|pdf|png|pot|pps|ppt|pptx|ra|ram|svg|svgz|swf|tar|tif|tiff|ttf|ttc|_ttf|wav|wma|wri|woff|woff2|xla|xls|xlsx|xlt|xlw|zip)$ [NC]
	RewriteRule .* - [L]
</IfModule>
HTACCESS;

		addContent( self::MARKER, $content );
	}

	/**
	 * Remove our rules from the .htaccess file.
	 */
	public static function removeRules() {
		removeMarkers( self::MARKER );
	}

	/**
	 * Handle activation logic.
	 */
	public static function onActivation() {
		self::maybeAddRules( getSkip404Option() );
	}

	/**
	 * Handle deactivation logic.
	 */
	public static function onDeactivation() {
		self::removeRules();
	}

}
