# WordPress.org Release Readiness Auditor — v1.0.1

Evidence-based WordPress.org plugin preflight for an actual candidate ZIP or source tree. Compares WordPress.org release metadata, checks packaging, and now **explicitly traces whether upgrades/reactivation preserve intentional administrator choices** rather than reseeding cleared options or changing strict policies.

## Use

Load this Agent Skill with the ZIP root `SKILL.md`, then ask the agent to audit your plugin release candidate, slug and planned version. If available, provide the prior version's source and any release ZIP or test logs.

**Suggested prompt**

> Use `wordpress-org-release-readiness-auditor` to audit the current WordPress.org release candidate for this plugin. Compare the main PHP header/readme/ZIP, then trace all upgrade/activation/seed paths for settings preservation. Specifically distinguish fresh-install missing values from intentionally cleared/disabled settings, custom mappings and strict policy settings. Show exact read/write paths and an upgrade scenario matrix. Read only; do not run commands, change files or publish.

This version is a candidate, not a Codex-proven improvement over v1.0.0. The optional Python 3.9+ scanner only checks metadata and package structure; running it requires separate user permission. No write privileges, secrets, SVN access, network, PHP runtime or WordPress site access is required by default.

## Limitations

No guarantee of acceptance by WordPress.org, safe runtime upgrades, licensing compliance, security or compatibility. Actual ZIP/SVN/Plugin Check/WordPress integration evidence must be supplied and verified to claim checks passed. Without prior source or clear user-intent semantics, some upgrade issues remain `POTENTIAL` or `NOT CHECKED`.

## Version history

- **1.0.1 (2026-11-17):** Add mandatory persisted-configuration preservation trace, clearer upgrade-state matrix, pre-existing-vs-introduced distinction, and stronger false-positive standards. Scanner remains metadata-only.
- **1.0.0:** Initial release preflight, metadata and package auditing.
