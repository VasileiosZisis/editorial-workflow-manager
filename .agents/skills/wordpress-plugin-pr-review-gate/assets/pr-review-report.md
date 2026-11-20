# WordPress PR Review Gate — report template

## Gate

**Decision:** [BLOCK | CHANGES REQUESTED | PASS WITH NOTES | PASS | INSUFFICIENT CONTEXT]
**Scope:** [base → head / supplied diff; changed files and direct dependencies]
**Intended change:** [one sentence grounded in PR description/code]
**Tests:** [Not run (read-only) / exact executed commands with results]
**Limits:** [missing call path, config, runtime, or environment]

## Confirmed PR-introduced findings

| ID | Severity | Introduced at | Issue |
| --- | --- | --- | --- |
| PRG-01 | high | `path:line` | [short clear failure statement] |

### PRG-01 — [title]

- **Changed location:** `path:line` or `path:function` (line unavailable).
- **Context/evidence:** [old-to-new change, specific caller/consumer/guard].
- **Conditions:** [actor, request, existing data/version, required setup].
- **Failure sequence:** [short reproducible steps or call flow].
- **Why introduced:** [why old path avoided it, or what new path creates it].
- **Impact:** [concrete visible or persistent effect].
- **Smallest fix:** [targeted behavioral correction].
- **Regression test:** Arrange / Act / Assert, using project-specific suite.
- **Confidence:** [high/medium/low; static or executed proof].

If none: **No confirmed PR-introduced defects found in the reviewed scope.**

## Needs verification

- [specific risk, missing fact, shortest authorized local test].

## Test / compatibility follow-ups

- [short priority ordered items, only if relevant].

## Reviewer limits

[Explicit statement of what was not inspected or run. This gate is advisory, not a CI-enforced merge check.]
