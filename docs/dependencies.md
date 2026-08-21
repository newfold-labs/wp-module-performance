---
name: wp-module-performance
title: Dependencies
description: Composer and npm dependencies.
updated: 2026-08-20
---

# Dependencies

## Runtime

| Package | Purpose |
|---------|---------|
| **colinmollenhour/credis** | Redis client for object cache. |
| **newfold-labs/wp-module-context** | Brand/context; used for feature checks. |
| **newfold-labs/wp-module-data** | Hiive connection/auth token for Hiive API requests. |
| **wp-forge/collection** | Collection utilities. |
| **wp-forge/wp-htaccess-manager** | .htaccess rules for page cache. |
| **wpscholar/url** | URL utilities. |
| **newfold-labs/wp-module-features** | Feature flags. |
| **newfold-labs/wp-module-installer** | Install/setup integration. |
| **newfold-labs/wp-module-htaccess** | Htaccess module integration. |

## Dev

- **newfold-labs/wp-php-standards** – PHPCS.
- **johnpbloch/wordpress** – WordPress core for tests.
- **lucatume/wp-browser** – Codeception wpunit.
- **wp-cli/i18n-command** – i18n.

## npm overrides

`@wordpress/scripts` still requires vulnerable versions of a few transitive
packages. `package.json` overrides them with patched releases:

| Package | Version | Reason |
|---------|---------|--------|
| **adm-zip** | `^0.6.0` | Prevents excessive memory allocation from crafted archives. |
| **markdown-it** | `^14.2.0` | Fixes quadratic parsing behavior in the Markdown lint toolchain. |
| **linkify-it** | `^5.0.2` | Fixes quadratic URL scanning used by `markdown-it`. |
| **serialize-javascript** | `^7.0.5` | Fixes code execution and CPU exhaustion issues. |
| **uuid** | `^11.1.1` | Adds buffer bounds checks. |
| **minimatch 3.x** | `^3.1.5` | Fixes regular expression denial of service issues. |

These can be removed when the upstream toolchain no longer resolves to the
vulnerable versions.

`extract-zip` remains at 2.0.1 because no patched release exists. It is pulled
in by the development-only browser test stack through `@wordpress/scripts`,
Lighthouse, and Puppeteer. It is not included in the module build.

The same test stack also resolves `@opentelemetry/core` 1.x through
Lighthouse's Sentry dependency. Forcing 2.x breaks Sentry at load time, and
Dependabot does not report it for this repository. Revisit it when Lighthouse
updates Sentry.
