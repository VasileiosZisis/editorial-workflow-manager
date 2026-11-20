# Data integrity, backward compatibility and migrations

Evaluate these only when the diff changes persistence, public interfaces, or lifecycle behavior.

## Stored data invariants

- Identify authoritative options, post/user meta, CPTs, custom tables, event history, cache keys and cron options affected by the change.
- Compare reads and writes across all callers. A changed key/schema without a migration or compatibility read may strand existing installs, while a read-only alias may intentionally preserve backward compatibility.
- When changing meaning or format of a value, track both existing-record reads and newly written records. Avoid assuming all sites start from blank state.
- For bulk updates or imports, trace partial-failure handling, idempotency, duplicates, validation and expected performance. Don't automatically require transactions for every WordPress API call.
- Client/tenant relationships: confirm the new query returns only records appropriate to the current actor when the product is multi-client.
- Event/history updates: distinguish latest-summary metadata from append-only events; inspect only if PR modifies the workflow.

## Upgrades vs activation

An upgrade normally does not simply rerun a plugin's activation hook. If the patch introduces a custom table, option or meta format, look for an explicit upgrade routine that detects an older schema version and migrates existing data. Consider read compatibility, backfill costs, data-loss risk and failure recovery. `dbDelta()` must be used correctly if applicable, but do not demand it when the project uses another valid schema approach.

## API/compatibility invariants

- Public PHP functions/classes/hooks, REST namespaces/routes/response shapes and filter argument lists may be consumed by other plugins/themes.
- A renamed callback or method can be safe if all internal callers and documented integrations are updated; check before flagging.
- Public PHP signatures and accepted hook arguments must agree with callers, particularly after adding required arguments.
- If deleting legacy data, first verify contractual retention, uninstall semantics and migration intent; don't assume destructive cleanup is always forbidden.

## Test guidance

- Start from an **existing plugin installation** fixture if reviewing upgrades; testing a fresh activation alone misses migration failures.
- Test data-read consistency before and after upgrade and verify existing records are preserved.
- For permissions, use two synthetic users/clients and assert both the allowed result and the denied result.
- For state changes, assert authoritative stored state plus one relevant side effect rather than checking only a returned HTTP status.
- For failures, inject a realistic error rather than asserting a fragile implementation detail.
