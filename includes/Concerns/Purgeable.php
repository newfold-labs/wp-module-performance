<?php

namespace NewfoldLabs\WP\Module\Performance\Concerns;

interface Purgeable {

	/**
	 * Purge everything for the given cache type.
	 *
	 * @return void
	 */
	public function purgeAll();

	/**
	 * Purge a specific URL for the given cache type.
	 *
	 * @param string $url
	 *
	 * @return void
	 */
	public function purgeUrl( $url );

}
