# Community Standards — Design

## Goal

Add GitHub community health files so the repo shows 100% on the "Community Standards" meter and gives contributors a clear front door.

## Scope

**Add:**

- `CODE_OF_CONDUCT.md` — Contributor Covenant 2.1, contact `david@run-as-root.sh`
- `CONTRIBUTING.md` — short. Covers: branch/PR flow, commit message style (conventional commits, matches existing `git log`), how to run `phpunit` / `phpstan` / `phpcs`, link to `docs/` for deeper architecture
- `SECURITY.md` — private disclosure via `david@run-as-root.sh`. Supported: latest minor on `main`. No CVE pipeline yet, just email
- `.github/ISSUE_TEMPLATE/bug_report.yml` — YAML issue form. Fields: Magento/Mage-OS version, PHP version, seeder version, reproduction steps, expected vs actual, logs
- `.github/ISSUE_TEMPLATE/feature_request.yml` — YAML issue form. Fields: problem, proposed solution, alternatives considered
- `.github/ISSUE_TEMPLATE/config.yml` — `blank_issues_enabled: false`, optional contact link to README

**Keep:** existing `.github/PULL_REQUEST_TEMPLATE.md` — already terse and on-brand.

**Skip:**

- `FUNDING.yml` — user declined for now
- `SUPPORT.md` — overkill; README + issues are enough
- `GOVERNANCE.md` — single-maintainer repo, not meaningful

## Style

Match the existing repo voice: terse, no fluff, short bullet lists over prose. Code of Conduct is the exception — boilerplate Contributor Covenant, unchanged wording, because that's the whole point of using a standard.

## Non-goals

- No CI changes
- No CHANGELOG entry (community files are meta, not user-facing behavior)
- No discussion templates (not using GitHub Discussions)
