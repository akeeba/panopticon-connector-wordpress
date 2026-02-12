# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Panopticon Connector for WordPress — a WordPress plugin that exposes REST API endpoints allowing remote management of WordPress sites through [Akeeba Panopticon](https://github.com/akeeba/panopticon). It handles core updates, plugin/theme management, and integrates with Akeeba Backup and Admin Tools Professional.

**License:** AGPL-3.0-or-later
**Requirements:** PHP 7.2+ (platform target 7.2.5), WordPress 5.0+
**Composer dependency:** `z4kn4fein/php-semver` for semantic versioning

## Build

The build system uses Apache Phing with shared build files from `../buildfiles/phing/`. The build relies on external infrastructure not included in this repo.

```bash
composer install          # Install dependencies (vendor/)
phing                     # Default target: creates release ZIP in release/
```

The build replaces `##VERSION##` and `##DATE##` tokens, updates the plugin entry point header, and packages into `release/panopticon-{version}.zip`.

## Testing

There is no automated test suite. Manual API testing is done with JetBrains HTTP Client files in `assets/*.http` (core, extensions, updates, admintools, backup). These require a `http-client.private.env.json` file (gitignored) with site URL and auth token.

## Architecture

### Entry Point and Plugin Lifecycle

`panopticon.php` — contains the `PanopticonPlugin` singleton class. It:
- Manually requires all class files from `includes/` (no autoloader for plugin classes; Composer autoloader is used only for vendor)
- Registers REST routes via the `rest_api_init` action
- Handles plugin self-update checks against `update.json` on GitHub
- Uses `Panopticon_Options_Trait` for the Settings > Panopticon admin page

### REST API Controllers

All routes are under the `v1/panopticon` namespace. Each controller extends `WP_REST_Controller`:

| File | Class | Purpose |
|------|-------|---------|
| `panopticon-core.php` | `Panopticon_Core` | WordPress core update info and installation, DB updates |
| `panopticon-extensions.php` | `Panopticon_Extensions` | List plugins/themes, remote extension install (POST=URL, PUT=upload) |
| `panopticon-updates.php` | `Panopticon_Updates` | Refresh update transients, update individual plugins/themes |
| `panopticon-akeebabackup.php` | `Panopticon_AkeebaBackup` | Akeeba Backup Professional integration info |
| `panopticon-admintools.php` | `Panopticon_AdminTools` | Admin Tools Professional: WAF, htaccess, IP unblock, file scanner |
| `panopticon-server-info.php` | `Panopticon_Server_Info` | System info collection (callable class, no own routes — used by Core) |

### Authentication

Token-based auth implemented in `PanopticonPlugin::authorizeAPIUser()`:
- Token stored as WordPress option `panopticon_token`, hashed with SHA-256 + WP salt
- Accepted headers: `Authorization: Bearer {token}`, `X-Panopticon-Token`, `X-Joomla-Token` (compatibility)
- Authenticates as the first Super Admin (multisite) or Administrator user
- Hooks into `determine_current_user` filter

### WP-CLI Commands

`panopticon-cli-token.php` / `panopticon-cli-namespace.php` — provides `wp panopticon token get` and `wp panopticon token reset`.

## Code Conventions

- PHP 7.2 compatibility required — no typed properties, no union types, no match expressions, no named arguments, no trailing commas in function parameters
- Allman brace style (opening brace on its own line)
- Tab indentation
- Classes are not namespaced; they use a `Panopticon_` prefix
- Class files in `includes/` follow the pattern `panopticon-{name}.php`
- PHPDoc `@since` tags track the version where methods/properties were introduced
- `@var` type annotations on all properties
