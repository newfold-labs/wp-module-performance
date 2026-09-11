---
name: wp-module-performance
title: Object cache
description: How the Redis object cache decides what to offer, what it calls, and how to switch it off.
updated: 2026-09-11
---

# Object cache

The module can install a Redis object-cache drop-in, provisioning credentials through the hosting
API when wp-config has none. Two parts of that reach the network, and both are described here
because they are the parts a host is most likely to need to control.

## Availability probe

The Object Cache toggle is only offered where Redis can actually run. The phpredis extension being
loaded is not enough to tell: on some server generations the extension is present but the daemon
was never deployed, and enabling there fails.

`RedisServiceAvailability` answers that question. When the Redis connection constants are already
defined for this request, from wp-config, the environment, or another plugin's drop-in, the answer is
yes without any network call. Otherwise it asks the server, which takes two calls: Hiive
`GET /sites/v1/customer` for a hosting API token and site id, then Hosting UAPI
`GET /v1/sites/{id}/performance/redis` for the daemon status. A blog that is not connected to Hiive
makes neither call and leaves the stored schedule alone.

The answer is held in the `nfd_performance_redis_service_state` site option:

| Outcome | Held for |
|---------|----------|
| Daemon available | 12 hours |
| Daemon not available | 1 hour |
| Could not tell | 5 minutes, doubling per consecutive failure, up to 12 hours |

An option rather than a transient, for two reasons. On a site running another plugin's
`object-cache.php`, `get_transient()` reads only that cache and never falls back to the database, so
a broken or non-persistent one leaves no floor at all and the probe runs on every admin page load.
And the Redis daemon belongs to the server, not to a blog, so a multisite network answers this once
rather than once per blog.

A probe that cannot determine the answer keeps serving the last answer the server did give, rather
than falling back to "unavailable". Otherwise a few bad minutes upstream would take the toggle away
from every site that had one, for the whole backoff window. Where the server has never answered, it
does fail safe to unavailable, so the toggle is not offered on a box that would error on enable.

That trust has a limit. A box really can lose Redis while Hiive is unreachable, and offering the
toggle there produces the "Could not enable object cache" error this check exists to prevent, so an
answer the server has not confirmed for a week falls back to hidden. A stale "no" needs no such
guard, since hiding is already the safe side.

One request at a time probes, claiming `nfd_performance_redis_service_probe_lock`. The rest serve
what is already stored instead of queueing behind it. Within a request the answer is worked out
once. Both calls use a 5 second timeout, not the 30 second default used for provisioning, because
this runs while an admin page renders and a probe that times out is simply retried later.

A site updating from 3.9.x has its answer in the `nfd_performance_redis_service_available` transient
this state replaced. That is read once and carried over, held for an hour, so the toggle does not
disappear on the first page load after the update and the fleet does not all probe at once.

## Switching the probe off

```php
define( 'NFD_DISABLE_REDIS_AVAILABILITY_PROBE', true );
```

Stops the probe making any network call. A constant so a host can switch it off across a fleet from
wp-config without shipping a release. The last stored answer is still served, so a site that has
probed before keeps the UI it had; one that never has stays fail-safe hidden. To show the toggle
regardless, hook `newfold_performance_object_cache_ui_available`.

## Automatic drop-in management

When the stored preference is on but the drop-in is missing or belongs to another plugin, the module
restores its own, provisioning credentials first if wp-config has none. Attempts are recorded in the
`newfold_object_cache_restore_attempted` site option and left 15 minutes apart, so a site where the
restore cannot succeed does not try again on every admin page load. The same interval covers the
reconcile that runs on `plugins_loaded` for every request. Activation is not held back by it, and
neither is a user turning the toggle on, which calls `enable()` directly.

The interval is always checked before anything is deleted. A call that removed another plugin's
drop-in and only then found itself throttled would leave the site with no drop-in at all.

Note that provisioning still uses the 30 second Hiive timeout rather than the probe's 5 seconds. It
writes wp-config constants through the hosting API, so cutting it short risks leaving that half
done; the interval above is what keeps it off the hot path.

To turn off drop-in install, removal and reconciliation entirely:

```php
define( 'NFD_DISABLE_OBJECT_CACHE_AUTO_MANAGEMENT', true );
```

## Filters

| Filter | Purpose |
|--------|---------|
| `newfold_performance_object_cache_ui_available` | Whether to offer the Object Cache UI. Defaults to the availability answer above. |
| `newfold_performance_disable_redis_availability_probe` | Switch the probe off. Defaults to whether `NFD_DISABLE_REDIS_AVAILABILITY_PROBE` is set. |
| `newfold_performance_redis_probe_timeout_seconds` | Timeout for both probe calls. Default 5. |
| `newfold_performance_hiive_request_timeout_seconds` | Timeout for Hiive calls that do not pass their own. Default 30. |
| `newfold_performance_hosting_uapi_request_timeout_seconds` | Same, for Hosting UAPI. Default 30. |
| `newfold_performance_hiive_api_base_url` | Hiive base URL. |
| `newfold_performance_hosting_uapi_base_url` | Hosting UAPI base URL. |
| `newfold_performance_hosting_uapi_redis_toggle_body` | Request body sent when enabling Redis at the host layer. |
| `newfold_performance_hosting_uapi_redis_toggle_error` | The `WP_Error` returned when that call fails. |
| `newfold_performance_object_cache_dropin_url` | Where the drop-in is downloaded from. |
| `newfold_performance_object_cache_dropin_local_path` | Local drop-in path, preferred over the URL when present. |

## Diagnosing a site

`wp nfd performance object_cache diagnose` reports phpredis, the connection constants, wp-config and
drop-in state, and a live ping. Resolving the drop-in state runs the availability probe, so it can
reach the network. See [wp-cli.md](wp-cli.md).
