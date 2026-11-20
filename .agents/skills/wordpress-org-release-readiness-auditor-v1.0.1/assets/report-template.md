# WordPress.org release readiness report

**Candidate:** `<source/ZIP>` **Commit or hash:** `<observed or not checked>`  
**Target slug and version:** `<values>`  
**Scope:** `SOURCE / ZIP / SVN / prior release / live listing actually inspected`  
**Permissions/actions:** `<read-only / any explicitly authorized commands>`  
**Advisory decision:** `HOLD | VERIFY BEFORE RELEASE | READY FOR HUMAN RELEASE | INSUFFICIENT CONTEXT`

## Executive summary and metadata consistency

| Signal | Observed source/path and value | Expected | Finding / not checked |
|---|---|---|---|

## Confirmed release blockers and warnings

**R-01 — [severity] [confidence] [introduced/pre-existing/unknown] — Title**
- Evidence: exact path, line and statement or ZIP member.
- Contract and impact: expected vs actual, why meaningful.
- Fix and recheck: smallest change and test/inspection that would establish correction.
- Verification level: source only / ZIP static / actual runtime result with observed command.

## Persisted configuration and upgrade preservation matrix

| Scenario | Stored state before | Call path / write path | Expected after | Actual source evidence | Runtime verification |
|---|---|---|---|---|---|
| Fresh installation | | | | | |
| Existing defaults upgrade | | | | | |
| **Intentionally cleared or disabled** upgrade | | | | | |
| Customized setting upgrade | | | | | |
| Reactivation | | | | | |
| Multisite new site (if relevant) | | | | | |

## Verification still needed

- Final built ZIP, release manifest/hash and actual WordPress.org SVN tag (distinguish what was inspected).
- Official Plugin Check and readme validator: actual result or **NOT RUN**.
- WordPress/PHP minimum, fresh installation, prior-version upgrade with cleared and custom settings, reactivation, multisite: actual results or **NOT RUN/NOT APPLICABLE**.
- License, translations and external dependency checks: actual evidence or **NOT VERIFIED**.

## Prioritized human release checklist

1. Address confirmed blockers, rebuild and recheck exact final artifact.
2. Run authorized disposable-site compatibility and upgrade regressions, preserving intentional settings.
3. Validate official tools and WordPress.org SVN tag, then human-confirm release.

**Actions not performed:** no file changes, Git/SVN actions, network or runtime tests unless separately authorized and recorded.
