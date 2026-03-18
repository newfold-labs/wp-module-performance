# Agent guidance – wp-module-performance

This file gives AI agents a quick orientation to the repo. For full detail, see the **docs/** directory.

## What this project is

- **wp-module-performance** – A module for managing caching functionality (page cache, object cache). Registers with the Newfold Module Loader; uses Redis (credis), wp-module-context, wp-module-features, wp-module-installer, wp-module-htaccess, and wp-forge/wp-htaccess-manager. Maintained by Newfold Labs.

- **Stack:** PHP 7.3+. See composer.json and docs/dependencies.md.

- **Architecture:** Registers with the loader; provides PerformanceFeature; integrates with htaccess and installer. See docs/integration.md.

## Key paths

| Purpose | Location |
|---------|----------|
| Bootstrap | `bootstrap.php` |
| Feature + helpers | `includes/PerformanceFeature.php`, `includes/functions.php` |
| Tests | `tests/` |

## Essential commands

```bash
composer install
composer run cs-lint
composer run cs-fix
composer run test
```

## Documentation

- **Full documentation** is in **docs/**. Start with **docs/index.md**.
- **CLAUDE.md** is a symlink to this file (AGENTS.md).

---

## Keeping documentation current

When you change code, features, or workflows, update the docs. Keep **docs/index.md** current: when you add, remove, or rename doc files, update the table of contents (and quick links if present). When adding or changing dependencies, update **docs/dependencies.md**. When cutting a release, update **docs/changelog.md**.
