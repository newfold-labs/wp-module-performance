<?php

namespace NewfoldLabs\WP\Module\Performance\Concerns;

/**
 * Interface for cache types that can be purged.
 */
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
	 * @param string $url The URL to purge.
	 *
	 * @return void
	 */
	public function purgeUrl( $url );
}
