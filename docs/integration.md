# Integration

## How the module registers

The module registers with the Newfold Module Loader via bootstrap.php. It provides a PerformanceFeature that integrates with wp-module-htaccess (rules) and wp-module-installer (setup). The host plugin typically registers a performance service in the container; other code can enable/disable page cache and object cache.

## Dependencies

Requires wp-module-context, wp-module-features, wp-module-installer, wp-module-htaccess, credis, wp-forge/wp-htaccess-manager, wp-forge/collection, wpscholar/url. See [dependencies.md](dependencies.md).
