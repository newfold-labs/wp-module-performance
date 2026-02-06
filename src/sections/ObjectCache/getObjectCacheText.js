import { __ } from '@wordpress/i18n';

const getObjectCacheText = () => ( {
	objectCacheTitle: __( 'Object Caching', 'wp-module-performance' ),
	objectCacheDescription: __(
		'Store database query results in Redis so repeated requests are served from memory instead of the database. Clear cache above also flushes the object cache when enabled.',
		'wp-module-performance'
	),
	objectCacheToggleLabel: __( 'Enable Object Caching', 'wp-module-performance' ),
	objectCacheSaved: __( 'Object cache setting saved', 'wp-module-performance' ),
	objectCacheErrorTitle: __( 'Failed to update object cache', 'wp-module-performance' ),
	objectCacheOverwrittenNotice: __(
		"Another plugin's object cache is active. Disable it in that plugin to use this toggle.",
		'wp-module-performance'
	),
} );

export default getObjectCacheText;
