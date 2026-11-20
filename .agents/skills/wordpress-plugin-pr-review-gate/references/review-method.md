# Differential review method: scope before checklist

A PR review should determine **what broke because of this PR**, not list every problem an AI agent can imagine in the repository.

## Recover the change

- Note the exact base/head relationship, changed files, additions, deletions, affected existing behavior, and relevant PR/issue description. For rebased patches use the comparison actually supplied; avoid guessing the Git history.
- Learn the application's real invariants from repository instructions and existing consumers. Do not invent business constraints from common industry patterns.
- Classify each touched surface: pure presentation, REST/AJAX/admin-post, hook registration, shortcode/template, permissions, data persistence, file handling, imports/exports, caches, scheduling, migration/versioning, build assets, and tests.
- Review modified lines first; expand only to the nearest callers and dependencies whose behavior determines correctness. Cite the change plus the contextual callsite.
- If the PR only changes readme documentation, don't demand a security audit. If it only changes translation strings, don't recommend database migrations.

## Traceability worksheet (internal, keep short)

| Change | Entry / caller | Old invariant | New path / behavior | Existing guard / consumer | Outcome | Evidence |
| --- | --- | --- | --- | --- | --- | --- |
| Changed helper | actual hook | observed | changed | actual source | confirmed/uncertain/safe | changed location |

## Think in before/after scenarios

For each concern, explain:

1. **Before** the PR, what did the relevant user/request see?
2. **After**, what exact input and conditions trigger the change?
3. Why is the new result contrary to the documented contract, existing caller's expectation, or intended security boundary?
4. Is there a short path that prevents the alleged failure?
5. What is the smallest targeted fix that preserves the PR's purpose?
6. What one test would regress if the fix is absent?

A generalized best-practice concern without a demonstrable regression is not a PR finding. If the baseline already had the same issue, mention it only when the user explicitly requests a broader audit or this PR newly exposes that weakness.

## Review traps

- Assuming `is_admin()` checks capability; it only describes request context.
- Assuming a WordPress nonce proves permissions; it does not.
- Treating a REST `permission_callback` returning true as a bug when the route intentionally exposes public content.
- Calling an authenticated private REST route secure solely because it's behind `is_user_logged_in()`; multi-client records may need ownership checks.
- Claiming a race condition without showing a possible interleaving or missing atomic guard.
- Flagging a method rename when all consumers and compatibility paths were updated; check usages first.
- Treating a refactored or moved function as changed behavior without comparing old and new semantics.
- Treating all `update_post_meta()` false return values as write failures; an unchanged value can also lead to false in some uses.
- Inferring that `wp_mail()` success proves delivery.
- Treating broad WordPress coding standard preferences as merge blockers absent an actual operational effect.
- Confusing plugin installation/activation with upgrade: updating an existing plugin does not normally invoke an activation hook anew.

## Optional inputs

A review can begin with a full PR diff, a unified patch, or base/head snapshots. If the user supplies only a PR URL and the connected environment can open it in read mode, inspect the correct repository, branch and patch. If the environment cannot access it, explain the limitation; don't fabricate the diff. Never acquire external account access or fetch unapproved private sources.
