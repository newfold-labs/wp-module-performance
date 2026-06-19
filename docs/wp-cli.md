---
name: wp-module-performance
title: WP-CLI commands
description: The "wp nfd performance" commands provided by the module.
updated: 2026-06-19
---

# WP-CLI commands

The module registers commands under the `wp nfd performance` namespace. Registration lives in
`includes/PerformanceWPCLI.php` (which maps a command word to a handler class) and the shared
`includes/NFD_WPCLI.php` helper. Each handler is in an `WPCLI/` subdirectory next to the feature it
controls; each public method on a handler is a subcommand.

## Available commands

| Command | Purpose |
|---------|---------|
| `wp nfd performance cache level <0-3>` | Set the cache level. |
| `wp nfd performance cache skip_404 <true\|false>` | Toggle skipping 404 handling. |
| `wp nfd performance cache exclude "<list>"` | Set the comma-separated cache exclusion list. |
| `wp nfd performance link_prefetch ...` | Configure link prefetch settings. |
| `wp nfd performance images ...` | Configure image optimization settings. |
| `wp nfd performance object_cache diagnose` | Run read-only Redis / object cache diagnostics. |

## Object cache diagnostics

```bash
wp nfd performance object_cache diagnose
wp nfd performance object_cache diagnose --format=json
```

Reports phpredis availability, the Redis connection constants, whether they are present in
`wp-config.php`, unix socket reachability, a live Redis `PING` (the same check used when enabling
object cache, so a failure here matches the REST error `redis_unreachable`), the object-cache
drop-in status, and a diagnosis summary.

The command replaces the standalone `redis-diagnostics.php` script that was previously uploaded to a
site root for one-off debugging. It is **read-only**: it never writes files, options, or logs, and
it never prints Redis credentials — `WP_REDIS_PASSWORD` and `WP_REDIS_USERNAME` are reported as
presence only (`(set)` / `(not defined)`), never as values.

The diagnostics engine (`includes/Cache/Types/ObjectCacheDiagnostics.php`) is render-agnostic and
reuses the module's own `ObjectCache`, `ObjectCachePreflight`, and `PhpRedisPinger` classes so the
report reflects exactly what the enable flow sees. The handler
(`includes/Cache/Types/WPCLI/ObjectCacheCommandHandler.php`) renders it as a human-readable report
or as JSON.
