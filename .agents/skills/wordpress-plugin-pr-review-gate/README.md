# WordPress Plugin PR Review Gate — v1.0.0

A focused AI agent skill for reviewing **WordPress plugin Git diffs and pull requests** before merge. It returns an advisory **BLOCK / CHANGES REQUESTED / PASS WITH NOTES / PASS / INSUFFICIENT CONTEXT** decision with code-grounded findings and targeted regression tests.

## What makes it different

- Reviews **introduced changes** rather than running a broad general WordPress audit.
- Traces modified code into existing REST/AJAX handlers, hooks, database writes, upgrade paths and callers.
- Checks WordPress-specific API behavior and supported versions when changes require it.
- Filters false positives and cites actual changed locations.
- Distinguishes source-confirmed findings from missing runtime proof.
- Reports an advisory gate; **it does not approve or block PRs on GitHub automatically.**

## Who it's for

WordPress plugin maintainers, agencies and developers working on custom plugins, especially those shipping updates to existing installations.

## How to use

Install or load `SKILL.md` with a compatible AI coding agent that can read an authorized plugin repo plus its PR diff, patch, or before/after source. Ask:

> Review this WordPress plugin PR against its base. Check introduced security, data, hook, REST/AJAX, compatibility and regression-test risks. Give an advisory gate and only evidence-based findings. Do not edit or run commands.

The agent should load only the references relevant to changed behavior and report in chat. A read-only Git diff tool may be used where available and authorized; otherwise provide a patch or before/after code. A PR URL alone is not sufficient if the agent has no connected access to the repository.

## Permissions and limits

**Required:** read access to user-authorized source files and relevant diff/patch metadata.

**Optional:** read-only Git metadata access via connected repo tools; if the user separately authorizes command execution, the agent can inspect diffs or run tests, but that is not required to use the skill.

**Not required:** write access, network, production login, secrets, API keys, or external services. No scripts are shipped.

**Limitations:** This is an AI-assisted *static review by default*. Findings may be incorrect or incomplete; a human should verify them. CI, a real WordPress environment and integration tests are not automatically run. It is not a security audit certification or formal merge enforcement. It is optimized for **WordPress plugins**, not themes or unrelated PHP applications.

## Included files

- `SKILL.md` — instructions and activation metadata.
- `references/review-method.md` — changed-code tracing.
- `references/wordpress-review-checks.md` — WP security, hooks and compatibility.
- `references/data-compatibility-review.md` — existing-install migrations and persistence.
- `references/tests-and-gate.md` — severity and test standards.
- `assets/pr-review-report.md` — output template.
- `examples/illustrative-review.md` — synthetic sample result.

This skill is an independent third-party developer tool. WordPress is a trademark of the WordPress Foundation; this product is not affiliated with or endorsed by WordPress.org.
