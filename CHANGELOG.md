# Changelog

All notable changes to `artisan-build/bonfire` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-10-28

### Added

- Standalone CI for the canonical `artisan-build/bonfire` repository, with Pint,
  PHPStan, Pest, and lowest-supported-dependency jobs.
- Named room access gates registered through `Bonfire::gate()`. Gate checks are
  live, fail closed when missing or broken, and apply alongside normal room
  access rules without changing memberships.
- A host-facing member badge resolver registered through
  `Bonfire::resolveBadgesUsing()`. Normalized, escaped badges are available from
  `Member::badges()` and API v1 member resources.

### Changed

- `bonfire.bulk_mentions` can disable `@channel`, `@here`, and `@everyone`
  fan-out, mention records, and special rendering. It defaults to `true` for
  compatibility.
- This standalone repository is now Bonfire's canonical source and release
  home.

## [0.1.0] - 2026-06-21

This release marks the **headless boundary**: Bonfire is now a standalone chat
engine — domain layer, opt-in HTTP/JSON API, and a broadcast contract — with no
shipped UI.

> [!WARNING]
> **BREAKING CHANGE:** The package no longer ships views, Livewire, or Flux.
> Consumers bring their own UI (or use the HTTP/JSON API). The chat UI that
> previously lived in the package now lives in the host reference app
> (artisan-community).

### Added

- **HTTP/JSON API v1** — opt-in via `BONFIRE_API_ENABLED`, authenticated with
  Laravel Sanctum (cookie-based for SPAs, personal access tokens for native
  clients). Routes are prefixed `bonfire/api`; the host owns the auth guard via
  `config('bonfire.api.middleware')`. The package never adds `laravel/sanctum`
  to its runtime dependencies.
- **Configurable broadcast channel naming** — the `bonfire.user_channel` config
  closure formats the per-user channel name (default
  `App.Models.User.{id}`), replacing the previously hardcoded channel and
  allowing per-tenant namespacing.
- **Real-time documentation** (`docs/realtime.md`) — the full broadcast event
  contract (events, channel names, payloads), the channel authorization the host
  must wire, and the HTTP forward-poll fallback for hosts without a WebSocket
  server.

### Changed

- The chat UI (Volt/Blade/Flux views) was **relocated from the package to the
  host reference app**. The package is now headless and presentation-agnostic.
- Broadcast events now resolve channel names through the `user_channel` closure
  rather than a hardcoded `App.Models.User` string.

### Removed

- Dropped `livewire/livewire` from the package's runtime dependencies — the
  package ships no Livewire components.
- Removed the stale HTML page routes (`bonfire.index`, `bonfire.admin.index`,
  `bonfire.room.show`). Only the attachment download route
  (`bonfire.attachments.show`) plus the opt-in HTTP/JSON API remain.

[0.2.0]: https://github.com/artisan-build/bonfire/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/artisan-build/bonfire/releases/tag/v0.1.0
