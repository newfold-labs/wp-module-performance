<?php

namespace NewfoldLabs\WP\Module\Performance\Cache\Types;

/**
 * Stable machine codes for object cache enable/disable flows (REST + UI).
 */
final class ObjectCacheErrorCodes {

	public const DROPIN_OVERWRITTEN        = 'dropin_overwritten';
	public const CREDENTIALS_MISSING       = 'credentials_missing';
	public const PHPREDIS_MISSING          = 'phpredis_missing';
	public const HIIVE_NOT_CONNECTED       = 'hiive_not_connected';
	public const HUAPI_TOKEN_UNAVAILABLE   = 'huapi_token_unavailable';
	public const HAL_SITE_ID_MISSING       = 'hal_site_id_missing';
	public const HUAPI_ERROR               = 'huapi_error';
	public const CREDENTIALS_PENDING_RELOAD = 'credentials_pending_reload';
	public const REDIS_UNREACHABLE         = 'redis_unreachable';
	public const DOWNLOAD_FAILED           = 'download_failed';
	public const INVALID_DROPIN            = 'invalid_dropin';
	public const WRITE_FAILED              = 'write_failed';
}
