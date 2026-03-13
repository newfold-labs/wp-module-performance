# Getting started

## Prerequisites

- **PHP** 7.3+.
- **Composer.** The module requires credis, wp-module-context, wp-module-features, wp-module-installer, wp-module-htaccess, wp-forge/wp-htaccess-manager, wp-forge/collection, wpscholar/url.

## Install

```bash
composer install
```

## Run tests

```bash
composer run test
composer run test-coverage
```

## Lint

```bash
composer run cs-lint
composer run cs-fix
```

## Using in a host plugin

1. Depend on `newfold-labs/wp-module-performance` (and its dependencies).
2. The module registers with the loader. See [integration.md](integration.md).
