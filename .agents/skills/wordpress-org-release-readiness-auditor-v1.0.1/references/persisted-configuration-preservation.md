# Persisted configuration and user-intent preservation (v1.0.1)

This check is required when activation, version updates, data migrations, bootstrap setup or seeders can write durable configuration. It is **general WordPress guidance**, not instructions tied to a single plugin.

## Trace a full write path, not a keyword

1. **Identify stored keys and authoritative format:** `get_option`, `update_option`, `add_option`, transients only when durable intent, `get_site_option`, post/user meta, custom tables, role capabilities, mapping arrays and plugin version markers.
2. **Identify legitimate states:** option absent (fresh install), present default, present customized, present explicitly empty/`0`/`false`/disabled/`None`, invalid/legacy values and network-site-specific settings. Determine product acceptance criteria before labeling an empty value a defect.
3. **Identify lifecycle triggers:** `register_activation_hook`, updater/version checks, `admin_init`, `plugins_loaded`, cron, site-creation hooks, reactivation/uninstall/reinstall. Ask if a method named `create_on_activation` is **also invoked on upgrade**; names do not determine behavior.
4. **Follow conditional branches into actual writes.** For each state, record whether `empty($settings['mapping'])`, `! get_option(...)`, `get_option($name, $default)`, or a missing array key conflates “intentionally cleared” with “never initialized.” Then inspect the value that the write persists and any downstream policy or permission evaluation.
5. **Verify user impact:** Does a saved `None` become a default; does disabled become enabled; does a strict `Block` / deny-by-default setting become advisory/allow; does deletion/remapping change publication, access, notifications or retention? If the write is intentionally specified, do not invent a regression.
6. **Label origin:** compare prior release/commit when available. `INTRODUCED`, `PRE-EXISTING`, or `UNKNOWN ORIGIN`; an existing harmful behavior can be a release blocker but is not a newly introduced bug.
7. **Check idempotence and sequencing:** repeated admin requests, partial migrations, version marker advanced before migration completes, reactivation and multisite site initialization. Trace whether first-time setup and upgrade share a risky seeder.

## Required state-transition matrix

| Scenario | Starting state | Trigger | Expected persistence | Observed source path | Runtime outcome |
|---|---|---|---|---|---|
| First installation | Option absent | activation | intended defaults initialized once | real code or `NOT CHECKED` | `NOT RUN` unless executed |
| Old-version upgrade (defaults) | Existing default values | version change | compatible defaults retained/migrated | ... | ... |
| Old-version upgrade (cleared) | **Option exists but value intentionally `0`/empty/disabled** | version change | explicit choice preserved unless specified otherwise | ... | ... |
| Old-version upgrade (custom) | Existing custom mapping/config | version change | custom state not silently replaced | ... | ... |
| Reactivation | Existing persisted choices | activation | avoid unintended reseed | ... | ... |
| New multisite site | Site not initialized | activation/new site/admin visit | initialize once per site as intended | ... | ... |

Do not require all six where they are genuinely inapplicable; state why. Prioritize explicit clearing + strict policy in high-stakes permission/publication workflows.

## Example (deliberately fictional)

A fictional plugin stores `post_type_templates['post'] = 0` to represent an intentional “None” choice and `publication_modes['post'] = 'block'` to require explicit assignment. An upgrader invokes a seeder that assigns a starter template whenever `empty( $mappings['post'] )`. If source confirms that `0` is a supported intentional state and the seeder mutates it on upgrade, the behavior can change publication enforcement. The finding should cite (1) how `None` is saved, (2) the upgrade trigger, (3) the seeder condition/write and (4) the downstream enforcement path; classify runtime reproduction as **not run** unless actually executed.

If the plugin's contract does not allow empty mappings, this is **not** automatically a defect. Avoid a universal “never seed on upgrade” rule: migrations may deliberately establish newly introduced defaults when required, with documented migration behavior and tests.

## Proposed non-production regression cases

- Fresh install correctly seeds missing settings; no bogus failure for intended setup.
- Upgrade with explicit cleared/disabled setting preserves that choice.
- Upgrade with customized mapping preserves that value.
- Reactivation does not silently undo user intent.
- Strict/block policy remains effective when mapping is intentionally absent.
- New site in network gets documented defaults without rewriting existing sites.

Request a disposable previous-version -> new-version WordPress test environment for **runtime** validation. Static traces and hypothetical cases are not executed test results.
