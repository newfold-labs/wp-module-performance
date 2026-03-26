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
				'PHP Redis (phpredis) is not available on this server. Ask your host to enable the Redis extension for your PHP version.',
				'wp-module-performance'
			),
			credentials_missing: __(
				'Redis credentials are not present in wp-config.php yet.',
				'wp-module-performance'
			),
			credentials_pending_reload: __(
				'Redis credentials are being applied. We will retry automatically in a few seconds.',
				'wp-module-performance'
			),
			hiive_not_connected: __(
				'This site is not connected to Hiive, so Redis credentials cannot be provisioned automatically.',
				'wp-module-performance'
			),
			huapi_token_unavailable: __(
				'Hosting token is not available yet. Please try again shortly or contact support.',
				'wp-module-performance'
			),
			hal_site_id_missing: __(
				'Hosting site id is not available yet. Please try again shortly or contact support.',
				'wp-module-performance'
			),
			redis_unreachable: __( 'Could not connect to Redis with the current credentials.', 'wp-module-performance' ),
			redisServiceInactive: __( 'Redis service is not active on the server yet.', 'wp-module-performance' ),
			phpVersionUnsupported: __(
				'Your PHP version does not meet requirements for Redis on this hosting environment.',
				'wp-module-performance'
			),
			dropInUnavailable: __( 'Hosting reports the Redis drop-in is not available yet.', 'wp-module-performance' ),
			wordpressNotFound: __( 'Hosting could not locate WordPress for this site.', 'wp-module-performance' ),
			nfd_hiive_error: __( 'Could not reach Hiive to provision Redis credentials.', 'wp-module-performance' ),
			nfd_hosting_uapi_error: __( 'Hosting API could not enable Redis for this site.', 'wp-module-performance' ),
			huapi_error: __( 'Hosting API returned an unexpected error while enabling Redis.', 'wp-module-performance' ),
			download_failed: __( 'Failed to download the Redis object cache drop-in.', 'wp-module-performance' ),
			invalid_dropin: __( 'Downloaded drop-in content was invalid.', 'wp-module-performance' ),
			write_failed: __( 'Could not write object-cache.php. Check file permissions.', 'wp-module-performance' ),
		};

		if ( code && map[ code ] ) {
			return map[ code ];
		}

		return fallbackMessage || '';
	},
} );

export default getObjectCacheText;
