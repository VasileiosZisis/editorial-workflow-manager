# Illustrated PR review: synthetic case (not a real vulnerability report)

This fictional example represents one possible result from the **deliberately flawed** fixture described in the separate seller test kit. File names, data, and WordPress code are invented for teaching purposes. No live PR or plugin has been audited here, and no tests were executed.

## Gate

**Decision: BLOCK**
**Scope:** Fictional patch changing three files: `includes/api.php`, `includes/hooks.php`, `includes/approval.php`.
**Tests:** Not run; source review only.
**Reason:** Two confirmed high-severity regressions plus one medium issue.

## Confirmed PR-introduced findings

| Severity | Changed area | Finding |
| --- | --- | --- |
| High | `includes/api.php` | Requests route now public, and list query no longer filters rows by authenticated client |
| High | `includes/hooks.php` | Save callback requires two arguments but hook registration still passes only one |
| Medium | `includes/approval.php` | Approval notice fires before the status update is confirmed successful |

### PRG-01 — HIGH — Private request list exposed

**Evidence:** The REST permission callback changes from an authenticated-user check to `__return_true`, while the query is changed from a server-derived client-user filter to a full table read. The response returns request subjects. **Before:** unauthenticated visitors were denied and authenticated clients saw only their rows. **After:** the changed route can return rows belonging to every client. **Smallest fix:** restore meaningful permission and trusted client scoping. **Regression:** create two synthetic clients and records, call the route logged out and as each client; verify no private records cross boundaries. The paired permission and query changes are treated as **one root cause**.

### PRG-02 — HIGH — Fatal when a request post is saved

**Evidence:** The hook still registers `demo_on_request_saved` with accepted-argument count `1`, but its changed signature requires both `$post_id` and `$post`. Normal WordPress dispatch provides only the configured argument, so the callback cannot receive its required parameter. **Smallest fix:** update the hook's accepted-argument count to match the callback, if the hook supplies those arguments, or revise the signature safely. **Regression:** save a synthetic `demo_request` post through the actual WordPress hook and assert no fatal error and correct metadata update.

### PRG-03 — MEDIUM — False approval notification when persistence fails

**Evidence:** The changed handler dispatches its decision notice before checking whether `update_post_meta()` successfully saved the approved state. **Failure sequence:** current state is `open` → notice hook fires → underlying status write fails → handler returns false, while the notice has already been triggered. **Smallest fix:** move notification after successful durable state/history changes. **Regression:** inject a failing update and assert no decision notification is emitted.

## Follow-up

Fix all three changed paths, then run WordPress integration tests for the REST permissions/query, save-hook callback signature, and failed-persistence behavior. Verify results in an authorized local test environment. This is a static fictional illustration; findings are not discoveries in a real project.
