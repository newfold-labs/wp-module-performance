---
name: wp-module-performance
title: Integration
description: How the module registers and integrates.
updated: 2026-10-29
---

# Integration

## How the module registers

The module registers with the Newfold Module Loader via bootstrap.php. It provides a PerformanceFeature that integrates with wp-module-htaccess (rules) and wp-module-installer (setup). The host plugin typically registers a performance service in the container; other code can enable/disable page cache and object cache.

## Dependencies

Requires wp-module-context, wp-module-features, wp-module-installer, wp-module-htaccess, credis, wp-forge/wp-htaccess-manager, wp-forge/collection, wpscholar/url. See [dependencies.md](dependencies.md).

## Jetpack Boost (removed from this module)

As of [PRESS0-5232](https://newfold.atlassian.net/browse/PRESS0-5232), Performance no longer embeds Jetpack Boost settings or install Critical CSS from the host Performance screen. Host plugins should point users to the **Jetpack Boost** plugin admin when Boost is installed. The module may still expose Site Health checks that reflect Jetpack Boost option values when that plugin is active.
