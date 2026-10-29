---
name: wp-module-performance
title: Changelog
description: Notable changes by release.
updated: 2026-10-29
---

# Changelog

## Unreleased

### Removed

- **Jetpack Boost integration** from the Performance admin UI and module bootstrap ([PRESS0-5232](https://newfold.atlassian.net/browse/PRESS0-5232)).
  - Removed Advanced settings block, `sdk.jetpackboost` runtime data, `/newfold-performance/v1/jetpack/*` REST routes, and fresh-install auto-install of Jetpack Boost.
  - **Migration:** Critical CSS, minify, and defer-JS settings are managed in **Jetpack Boost** (`wp-admin/admin.php?page=jetpack-boost`) when that plugin is installed. Site Health checks may still read Jetpack Boost options when the plugin is active.
