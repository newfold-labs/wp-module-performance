<?php

namespace NewfoldLabs\WP\Module\Performance\CacheTypes;

use NewfoldLabs\WP\Module\Performance\OptionListener;
use NewfoldLabs\WP\Module\Performance\Performance;
use NewfoldLabs\WP\ModuleLoader\Container;
use WP_Forge\WP_Htaccess_Manager\htaccess;

use function NewfoldLabs\WP\Module\Performance\getCacheLevel;
use function WP_Forge\WP_Htaccess_Manager\removeMarkers;

class Browser extends CacheBase {

	/**
	 * The file marker name.
	 *
	 * @var string
	 */
	const MARKER = 'Newfold Browser Cache';

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

		new OptionListener( Performance::OPTION_CACHE_LEVEL, [ __CLASS__, 'maybeAddRules' ] );

		add_filter( 'newfold_update_htaccess', [ $this, 'onRewrite' ] );
	}

	/**
	 * When updating .htaccess, also update our rules as appropriate.
	 */
	public function onRewrite() {
		self::maybeAddRules( getCacheLevel() );
	}

	/**
	 * Determine whether to add or remove rules based on caching level.
	 *
	 * @param int|null $cacheLevel The caching level.
	 */
	public static function maybeAddRules( $cacheLevel ) {
		absint( $cacheLevel ) > 0 ? self::addRules( $cacheLevel ) : self::removeRules();
	}

	/**
	 * Remove our rules from the .htaccess file.
	 */
	public static function removeRules() {
		removeMarkers( self::MARKER );
	}

	/**
	 * Add our rules to the .htaccess file.
	 *
	 * @param int $cacheLevel The caching level.
	 *
	 * @return bool
	 */
	public static function addRules( $cacheLevel ) {

		$fileTypeExpirations = self::getFileTypeExpirations( $cacheLevel );

		$tab = "\t";

		$rules[] = '<IfModule mod_expires.c>';
		$rules[] = "{$tab}ExpiresActive On";

		foreach ( $fileTypeExpirations as $fileType => $expiration ) {
			if ( 'default' === $fileType ) {
				$rules[] = "{$tab}ExpiresDefault \"access plus {$expiration}\"";
			} else {
				$rules[] = "{$tab}ExpiresByType {$fileType} \"access plus {$expiration}\"";
			}
		}

		$rules [] = '</IfModule>';

		$htaccess = new htaccess( self::MARKER );

		return $htaccess->addContent( $rules );

	}

	/**
	 * Get the filetype expirations based on the current caching level.
	 *
	 * @param int $cacheLevel The caching level.
	 *
	 * @return string[]
	 */
	protected static function getFileTypeExpirations( int $cacheLevel ) {

		switch ( $cacheLevel ) {
			case 3:
				return [
					'default'         => '1 week',
					'text/html'       => '8 hours',
					'image/jpg'       => '1 week',
					'image/jpeg'      => '1 week',
					'image/gif'       => '1 week',
					'image/png'       => '1 week',
					'text/css'        => '1 week',
					'text/javascript' => '1 week',
					'application/pdf' => '1 month',
					'image/x-icon'    => '1 year',
				];

			case 2:
				return [
					'default'         => '24 hours',
					'text/html'       => '2 hours',
					'image/jpg'       => '24 hours',
					'image/jpeg'      => '24 hours',
					'image/gif'       => '24 hours',
					'image/png'       => '24 hours',
					'text/css'        => '24 hours',
					'text/javascript' => '24 hours',
					'application/pdf' => '1 week',
					'image/x-icon'    => '1 year',
				];

			case 1:
				return [
					'default'         => '5 minutes',
					'text/html'       => '0 seconds',
					'image/jpg'       => '1 hour',
					'image/jpeg'      => '1 hour',
					'image/gif'       => '1 hour',
					'image/png'       => '1 hour',
					'text/css'        => '1 hour',
					'text/javascript' => '1 hour',
					'application/pdf' => '6 hours',
					'image/x-icon'    => '1 year',
				];

			default:
				return [];
		}
	}

	/**
	 * Handle activation logic.
	 */
	public static function onActivation() {
		self::maybeAddRules( getCacheLevel() );
	}

	/**
	 * Handle deactivation logic.
	 */
	public static function onDeactivation() {
		self::removeRules();
	}

}
