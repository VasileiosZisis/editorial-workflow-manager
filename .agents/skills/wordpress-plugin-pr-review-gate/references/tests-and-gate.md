# Severity calibration, gate discipline and tests

## Severity based on demonstrated impact

| Level | Examples with real causal evidence | Gate effect |
| --- | --- | --- |
| Critical | Broad cross-site secret exposure or irreversible systemic data loss | BLOCK |
| High | Cross-client private data leak, supported PHP fatal on common path, unauthorized privileged action, destructive upgrade data loss | BLOCK |
| Medium | Broken status transitions in changed path, missing expected compatibility behavior, confirmed incorrect data serialization or failed workflow side effect | CHANGES REQUESTED |
| Low | Limited UI data display bug or minor operational inconsistency with clear conditions | PASS WITH NOTES unless cumulative impact justifies higher severity |
| Needs verification | Specific unresolved behavior that may be high impact, not yet provable | PASS WITH NOTES or INSUFFICIENT CONTEXT, based on whether review can responsibly assess the PR |

Only call a high defect confirmed if the code establishes actual access/impact, not merely a suspicious function name. Prefer one finding per root cause, not one per affected line.

## Review decisions are not branch protection

The gate is a **human-readable recommendation**, not a GitHub/Bitbucket/GitLab check result, status context, approval, auto-merge or CI job. No file, PR or repository is changed by this skill. Actual repository enforcement requires separate tooling/integration and explicit authorization.

## Tests: classify evidence honestly

- **Existing and inspected:** reviewed implementation and assertions in repository; may still be inadequate.
- **Executed and passed/failed:** only when an allowed tool ran it and returned output. Include exact command and environment if relevant.
- **Proposed, not executed:** the default for a read-only skill; provide a targeted test to demonstrate/fix the finding.
- **Unavailable/unknown:** test setup cannot be established from current context.

### Minimal regression case template

**Arrange:** Configure the smallest realistic WordPress fixtures, actors, records, plugin version, and hook registration.

**Act:** Invoke the actual REST/AJAX/admin-post callback, upgrade path, hook or API operation using the changed input.

**Assert:** Check both the visible result and the persisted state/side effects that identify the bug. If security-sensitive, assert the unauthorized response does not reveal data. If a migration, assert pre-existing data survives.

## False-positive controls

Before issuing findings, explicitly check for:

- A capability or ownership guard in a shared helper/permission callback.
- A migration/compatibility reader elsewhere in the plugin.
- Existing tests covering a supposed missing case.
- A documented intentionally public route returning only public data.
- A fixed/escaped output sink that disproves an injection concern.
- Hooks that stop reentrant calls or intentionally permit repeats.
- A change that simply reorganizes code without altering behavior.
- A mismatch between an expected contract and a purely optional feature.

## Report hygiene

Provide `path:line` if visible and reliable; otherwise `path:function` and `line unavailable`. Focus on changed line(s). Cite both code and reasoning. Do not make severity purely rhetorical. Avoid finding duplicates, recommendations to refactor unrelated code, or suggesting broad tests unrelated to the PR.
