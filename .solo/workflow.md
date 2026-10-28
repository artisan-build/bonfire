# Workflow — bonfire

Project profile for the `multi-agent-build` skill. The coordinator agent reads this FIRST.

## What this is
`artisan-build/bonfire`: a headless chat engine package for Laravel (rooms, messages, threads, reactions,
mentions, attachments, polls, opt-in HTTP/JSON API, broadcast contract). It ships no UI. Members are
polymorphic (`bonfire_members.memberable`) over the HOST's user model, so a host app never needs a second
account system. Consumers: Scalpels (scalpels.app, community feature, 2026-10-28) and artisan-community (the
reference host; still on an inline copy at `packages/bonfire`, to be converted later).

As of 2026-10-28 THIS repo is the canonical home. Before that, the live code was
`artisan-community/packages/bonfire`, and this repo held a stale one-commit April snapshot.

## Phase & mode
- phase: pre-launch (no published versions yet; v0.2.0 is the first Packagist release)
- default mode: **A-autonomous** (Ed, 2026-10-28)
- merge_policy: merge when CI is green. No human PR review; the local gate, independent review and acceptance
  judge are the quality bar.
- merge method: `gh pr merge --squash --delete-branch`, then confirm with `gh pr view <n> --json state`.

## Hard gate (verified on the committed SHA, clean tree)
- command: `composer ready` (see composer.json scripts; at minimum pint --test, phpstan, pest)
- extra suites: none
- monorepo: no

## CI (the merge gate for Mode A)
- status: active
- workflow: `CI` (`.github/workflows/ci.yml`)
- jobs: `Pint` (`pint`), `PHPStan` (`phpstan`), `Pest` (`pest`), and
  `Lowest dependencies` (`lowest-dependencies`)

## Dependency install (fresh worktree)
- command: `composer install --no-interaction`
- post-install: none (testbench)

## Agent-role constraints and exceptions
- constraints: none
- overrides: none
- invocation quirks: none

## Toolchain conformance: the ride-along rule
Run `composer ready` while finalizing every PR. Whatever it changes rides along as one isolated commit titled
`composer ready`. A new Rector rule or a pint.json change gets its own PR.

## Ship details
- branch naming: feat/<slug>, chore/<slug>
- PR target repo: artisan-build/bonfire
- release: tag only with Ed's per-version OK. Run the full suite on the bump commit first and hard-fail on red.
  Packagist is registered for artisan-build/bonfire.

## Plan & coordination
- plan location: ~/Herd/brain/projects/bonfire/brief-community-gates.md
- Solo project: bonfire
- run-log: scratchpad "bonfire community-gates run-log"

## Stack notes / quirks
- Orchestra Testbench; the package is tested standalone.
- Breaking changes are allowed pre-1.0, but they go in CHANGELOG.md.
