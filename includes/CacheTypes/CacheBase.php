<?php

namespace NewfoldLabs\WP\Module\Performance\CacheTypes;

use NewfoldLabs\WP\ModuleLoader\Container;

abstract class CacheBase {

	/**
	 * Dependency injection container.
	 *
	 * @var Container $container
	 */
	protected $container;

	/**
	 * Whether or not the code for this cache type should be loaded.
	 *
	 * @return bool
	 */
	public static function shouldEnable( Container $container ) {
		return true;
	}

	/**
	 * Set the dependency injection container
	 *
	 * @param Container $container Dependency injection container
	 *
	 * @return void
	 */
	public function setContainer( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Get the dependency injection container.
	 *
	 * @return Container
	 */
	public function getContainer() {
		return $this->container;
	}

}
