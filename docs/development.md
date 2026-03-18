---
name: wp-module-performance
title: Development
description: Lint, test, and workflow.
updated: 2025-03-18
---

# Development

## Linting

- **PHP:** `composer run cs-lint`, `composer run cs-fix`. Uses phpcs.xml.

## Testing

- **Codeception wpunit:** `composer run test`, `composer run test-coverage`.

## Workflow

1. Make changes in `includes/` or `bootstrap.php`.
2. Run `composer run cs-lint` and `composer run test` before committing.
3. When changing dependencies, update [dependencies.md](dependencies.md). When cutting a release, update **docs/changelog.md**.
