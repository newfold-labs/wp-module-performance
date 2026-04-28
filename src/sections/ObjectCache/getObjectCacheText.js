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
	getObjectCacheErrorDescription: ( code, fallbackMessage ) => {
		const map = {
			dropin_overwritten: __(
				"Another plugin's object cache is active. Disable it in that plugin to use this toggle.",
				'wp-module-performance'
			),
			phpredis_missing: __(
				'Object caching is not supported on this server. Please contact your hosting provider for help.',
				'wp-module-performance'
			),
			credentials_missing: __(
				'Object cache is not configured yet. Please try again in a moment.',
				'wp-module-performance'
			),
			credentials_pending_reload: __(
				'Setting up object cache. We will retry automatically in a few seconds.',
				'wp-module-performance'
			),
			hiive_not_connected: __(
				'Object cache cannot be enabled automatically right now. Please contact support.',
				'wp-module-performance'
			),
			huapi_token_unavailable: __(
				'Could not enable object cache right now. Please try again later.',
				'wp-module-performance'
			),
			hal_site_id_missing: __(
				'Could not enable object cache right now. Please try again later.',
				'wp-module-performance'
			),
			redis_unreachable: __( 'Could not connect to the object cache. Please try again later.', 'wp-module-performance' ),
			redisServiceInactive: __( 'Object cache is not ready on this server yet. Please try again later.', 'wp-module-performance' ),
			phpVersionUnsupported: __(
				'Object caching is not supported on this server. Please contact your hosting provider for help.',
				'wp-module-performance'
			),
			dropInUnavailable: __( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ),
			wordpressNotFound: __( 'Could not enable object cache right now. Please contact support.', 'wp-module-performance' ),
			nfd_hiive_error: __( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ),
			nfd_hosting_uapi_error: __( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ),
			huapi_error: __( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ),
			download_failed: __( 'Could not download object cache files. Please try again later.', 'wp-module-performance' ),
			invalid_dropin: __( 'Could not enable object cache right now. Please try again later.', 'wp-module-performance' ),
			write_failed: __( 'Could not save object cache file. Check file permissions or contact support.', 'wp-module-performance' ),
		};

		if ( code && map[ code ] ) {
			return map[ code ];
		}

		return fallbackMessage || '';
	},
} );

export default getObjectCacheText;
