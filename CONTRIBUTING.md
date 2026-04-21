# Contributing

Thanks for your interest in improving the Magento 2 Seeder. This repo is small and pragmatic — the guidelines below are short on purpose.

## Ground rules

- Be kind. The [Code of Conduct](CODE_OF_CONDUCT.md) applies.
- Keep PRs focused. One concern per PR.
- Don't break public APIs without a clear migration path — this package is published on Packagist and used by real stores.

## Getting set up

1. Fork and clone the repo.
2. `composer install`
3. Run the test suite to confirm a clean baseline (see below).

For running the seeder against a live Magento / Mage-OS install, see the README for module install instructions.

## Before you open a PR

Run the local checks:

```bash
vendor/bin/phpunit
vendor/bin/phpstan analyse
vendor/bin/phpcs
```

All three must pass. CI runs the same commands.

If your change affects behavior against a real Magento install, smoke-test it against one and note what you ran in the PR description.

## Commit messages

We use [Conventional Commits](https://www.conventionalcommits.org/). Look at `git log` for examples. Common prefixes in this repo:

- `feat(scope):` — new user-facing capability
- `fix(scope):` — bug fix
- `test(scope):` — tests only
- `docs(...):` — README, CHANGELOG, plans
- `chore(release):` — version bump

Breaking changes go in the title with a `!`, e.g. `feat(api)!: rename Seeder facade`.

## Pull requests

- Open against `main`.
- Fill out the PR template — it's short.
- Link the issue if one exists.
- Keep the diff reviewable. If it's growing past ~400 lines, consider splitting.

## Reporting issues

- **Bug?** Use the bug report template.
- **Idea?** Use the feature request template.
- **Security?** Don't open a public issue — see [SECURITY.md](SECURITY.md).

## Releases

Maintainer-only. The flow is: update `CHANGELOG.md`, tag, push, create a GitHub release. Packagist picks it up from there.
