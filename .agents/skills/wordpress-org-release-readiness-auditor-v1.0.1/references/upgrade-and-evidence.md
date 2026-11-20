# Upgrade safety and release evidence

A coherent ZIP can still modify behavior incorrectly. Perform the persisted-intent audit in `references/persisted-configuration-preservation.md` **before** concluding upgrade readiness when source is available.

## Identify upgrade triggers and persistent effects

Trace old/new source where available: `admin_init`, activation hooks, version checks, migrations, options, user/post meta, custom tables, schema version changes, feature flags, capabilities, default mappings, cached state, cron schedules and cleanup. An “activation” seeder may also execute during normal version upgrade or reactivation. Check whether first-install and upgrade are properly separated, and whether version markers are advanced after durable migration success.

For each changed stateful path, record source evidence and distinguish existing behavior from introduced changes. An old bug can remain a current release blocker if the supported release contract is violated; do not claim it was introduced by the new version unless compared.

## Preservation-specific cases (must explicitly consider when applicable)

1. **First install:** Missing key gets documented defaults exactly once.
2. **Upgrading existing defaults:** Old configuration remains supported or intentionally migrated.
3. **Upgrading explicitly cleared/disabled configuration:** Empty/`0`/`false`/`None` remains meaningful; a default seeder must not silently replace it absent an intentional contract.
4. **Upgrading customized settings:** Existing mappings/templates/features stay as configured, unless migration is documented.
5. **Reactivation:** No unwanted reset, including after deactivation.
6. **Multisite:** Existing sites and newly created sites initialize independently when the plugin claims multisite support.

Trace both the write and downstream consequence, especially when strict policy, access controls or publication gates depend on those choices. Do not turn `empty()` alone into a confirmed bug.

## Separate five evidence levels

| Evidence | What it supports | What it does not support |
|---|---|---|
| Source inspection | Branch/call/write path; conditional risk | Actual runtime outcome |
| Release ZIP inspection | Shipped file paths, declared metadata | Actual installation, old-version upgrade |
| Lint / scanner | Executed syntactic/static check | Security, WordPress.org approval |
| Plugin Check/readme validator | Observed official tool result (when actually run) | End-to-end release safety |
| Disposable install/upgrade | Runtime behavior in that controlled environment | All versions, all possible states |

If tests aren't executed, say `NOT RUN` without using a PASS/FAIL label. Never run tests on production data. For prior-release upgrade tests, record actual versions, settings before/after, and test commands only from observed output.

## Release gate reminder

WordPress.org SVN `trunk`, versioned `tags`, readme stable tag and confirmation are distinct from a Git tag or local ZIP. If they are not accessible, mark `NOT CHECKED`. The user controls publication; the skill does not tag or submit.
