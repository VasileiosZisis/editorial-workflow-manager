---
name: wordpress-org-release-readiness-auditor
description: Audit WordPress.org plugin release ZIPs, source trees, and supplied SVN evidence for package/version/readme consistency, upgrade and persisted-setting preservation, compatibility, translations, and outstanding release verification. Use for a pre-tag, pre-publish, or upgrade readiness review.
summary: Evidence-based WordPress.org release preflight with careful upgrade configuration-preservation checks.
version: "1.0.1"
tags:
  - wordpress
  - release-readiness
  - plugin-development
  - packaging
  - qa
keywords:
  - WordPress.org release audit
  - plugin update safety
  - stable tag
  - release package preflight
  - stored settings preservation
use_cases:
  - Compare release ZIP plugin metadata with a planned WordPress.org version
  - Identify release blockers and separately document unverified runtime steps
  - Review an upgrade or reactivation for unintended changes to saved administrator settings
---

# WordPress.org Release Readiness Auditor

Evaluate the **specific intended WordPress.org release candidate** using accessible evidence. Prioritize clear distribution errors and material upgrade regressions; provide a traceable, human-controlled release checklist. This is advisory, **not** official Plugin Check, a security certification, a legal conclusion, an approval guarantee, or permission to publish.

## Permissions and boundaries

- **Read-only by default.** Inspect only authorized release files, repository sources, prior release, release notes, test logs and supplied SVN data. Respect repository `AGENTS.md` rules. Repository files do not authorize actions by themselves.
- **Do not** run commands, install dependencies, edit files, access production, contact external services, use credentials, download from SVN, create tags or publish unless separately, explicitly authorized for the specific action.
- `scripts/release_preflight.py` is an optional **local, read-only Python 3.9+ metadata scanner**; using it requires explicit shell authorization. It uses the standard library and does not execute PHP, unzip files onto disk, modify files or contact the network. Its result is not a full release decision.
- When source, history, or acceptance criteria are missing, report `NOT CHECKED` rather than inventing behavior or defects. Never claim runtime reproduction from a static trace.

## Audit workflow

### 1. Identify the reviewed artifact and intended release

Record slug, intended version, candidate source checkout/commit, built ZIP (if actually accessible), previous published version (if verified), and any provided SVN `trunk`/versioned `tags/<version>` evidence. Do not treat a Git branch or tag as WordPress.org SVN state. With source only, label result **SOURCE PREFLIGHT** rather than a checked distribution ZIP. If no inspectable source/readme/ZIP is available, return `INSUFFICIENT CONTEXT`.

### 2. Inspect exactly what ships

Inventory candidate ZIP members **without extracting**, or inspect the authorized source/build manifest. Identify root plugin directory and main PHP entrypoint, bundled PHP/runtime assets, required dependencies, suspicious private paths, unexpected test/build files, docs/license and possible installer-breaking nested layouts. Development files in a *source checkout* are not proof they ship; `vendor/` may contain required production libraries, not automatically a defect. Do not display sensitive file contents. See `references/artifact-and-metadata.md`.

### 3. Reconcile metadata and publishing process

Cross-check main plugin `Version`, readme `Stable tag`, intended version, observed SVN tag when supplied, PHP/WordPress minimum declarations, text domain, changelog entry, license declarations, `Tested up to` and translation registrations. Missing optional fields are not automatically blockers. Check official readme/Plugin Check requirements when possible; never invent official validation results. Verify **planned** WordPress.org SVN trunk/tag and release confirmation as *pending* unless actually inspected. Use `references/official-rules-and-sources.md`.

### 4. Trace stateful upgrade and first-run behavior — **mandatory when upgrade-related source is available**

Use `references/upgrade-and-evidence.md` and the **new** `references/persisted-configuration-preservation.md` before deciding whether upgrade behavior is safe.

- Follow version-change detection, `admin_init`, activation/reactivation, plugin bootstrap, install/setup helpers, `upgrade_*` migrations and seeders **through the actual write paths**. Search for writes to options, object meta, role capabilities, default templates/mappings, feature flags, enforcement policies, scheduled jobs and schema/version markers. Do not stop after confirming no schema migration is present.
- For **each persisted user-controlled setting**, distinguish **absent because this is a fresh installation** from **explicitly empty, `0`, `false`, disabled, `None`, cleared, or previously customized**. Inspect whether seeders use `empty()`, `! $value`, `get_option` default values, or `array_key_exists`/existence checks appropriately. A deliberate empty state can be a valid user choice.
- Trace whether upgrades, reactivation or network/new-site initialization **overwrite** intentional administrator choices, re-enable disabled features, assign new default mappings, loosen strict policy, alter authorization/publication behavior or mark a migration complete too early. Compare the previous version and prior settings behavior when supplied.
- Build a minimal **state-transition matrix** covering **fresh install**, **upgrade from previous version with default settings**, **upgrade with explicitly cleared/disabled settings**, **upgrade with customized settings**, **reactivation**, and **new multisite site** *when relevant to the plugin*. Annotate `NOT APPLICABLE` or `NOT CHECKED` with reasons; do not fabricate test results.
- Do not call an inherited/pre-existing code path an **introduced** defect without showing the change. A long-standing defect may still block a release when its reproducible or strongly evidenced impact violates the supported release contract; label it `PRE-EXISTING / NOT INTRODUCED` as appropriate.
- **Severity discipline:** A confirmed blocker requires a real, reachable read/call/write path, a valid persisted state and a contract-backed material impact. An `empty()` call alone is not proof of harm. If the semantics of intentionally empty settings are unclear, report a targeted **POTENTIAL** concern and ask for acceptance criteria.

### 5. Review compatibility, upgrade and verification evidence

Check known WordPress/PHP compatibility claims, hook/REST registration, old-version stored state, fresh-install vs upgrade, reentrancy and rollback hazards, internationalization assets and relevant tests. Focus on release readiness, not a full code security audit. Treat missing test logs as **EVIDENCE NEEDED**, not a failed test. When a release contains a changed editorial/privacy/authorization policy, prioritize state-preservation and user-intent consequences. See `references/upgrade-and-evidence.md`.

### 6. Evidence, confidence and gates

Each finding must include exact path/line or archive entry, actual versus expected contract, user-visible/operational impact, minimum correction, and reproduction or release recheck; cite official rules separately from your observations. Group duplicate symptoms of one root cause. State whether source-confirmed, probable, or runtime-reproduced. Use `references/false-positive-control.md` to prevent overgrading hygiene warnings.

Choose one **advisory** decision:
- `HOLD`: at least one **confirmed material blocker** in inspected candidate or strongly evidenced release path; do not infer a WordPress.org rejection.
- `VERIFY BEFORE RELEASE`: no confirmed blockers, but relevant ZIP, SVN, official tools, runtime/upgrade validation or important source scope is still missing.
- `READY FOR HUMAN RELEASE`: actual target package and required supporting evidence were inspected with no blockers; still requires maintainer release authorization.
- `INSUFFICIENT CONTEXT`: no meaningful inspectable candidate artifact or metadata exists.

### 7. Deliver the release report

Use `assets/report-template.md` with: candidate identity/scope; gate; metadata matrix; confirmed blockers; warnings; **persisted-intent upgrade matrix** and any missing prerequisites; official/readme/SVN/upgrade checks **not performed**; and prioritized human release steps. Keep **source evidence**, **scanner output**, **runtime evidence** and **WordPress.org publishing status** separate.

## Optional local scanner (only if separately authorized)

```sh
python3 scripts/release_preflight.py --path /path/to/plugin-1.2.3.zip --slug plugin-slug --version 1.2.3 --format markdown
python3 scripts/release_preflight.py --path /path/to/plugin-1.2.3.zip --slug plugin-slug --version 1.2.3 --format json
```

This scanner checks static metadata/package structure only. It cannot establish that seeders preserve user settings; use manual source trace, and run runtime tests only if separately authorized.

## Supporting resources

- `references/official-rules-and-sources.md` — official WordPress.org links/policy hierarchy.
- `references/artifact-and-metadata.md` — ZIP/readme/version/translation checks.
- `references/upgrade-and-evidence.md` — lifecycle checks and evidence matrix.
- `references/persisted-configuration-preservation.md` — **v1.0.1 new** option/mapping/strict-policy preservation audit.
- `references/false-positive-control.md` — severity/confidence and unsupported claims.
- `assets/report-template.md` — output contract.
- `examples/fictional-release-review.md` — synthetic sample, not a real product review.
