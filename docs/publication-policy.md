# Publication policy (1.3.0)

Administrators select Advisory or Block per post type in Settings → Editorial Workflow. Advisory is the default on both fresh installs and upgrades. Block requires a valid mapped checklist template when first enabled. If that template is later removed, the policy stays active and publication is held until an administrator fixes the mapping or chooses Advisory.

## What is checked

Block gates a non-public post entering Publish, Private, or Scheduled. Existing published/private posts remain editable. Required manual items and enabled, applicable built-in and registered rules must pass. Optional items and `not_applicable` results are excluded. A valid template with no applicable requirements permits publication. Unavailable rule providers remain excluded under the 1.2.0 API contract; active evaluator errors fail safely.

The gate recalculates current requirements without trusting readiness cache metadata. It saves submitted content, terms, featured media, and checklist metadata under a non-public status, evaluates that saved state, and promotes it only on success. Drafts and autosaves remain available. Live editor feedback is provisional; PHP is authoritative.

## Failed attempts and recovery

Failed attempts preserve submitted work without publishing. Draft and Pending statuses are retained; new posts and failed scheduled posts are held as Draft. Dates are preserved. Core REST returns HTTP 409 with code `ediworman_publication_blocked`, an actionable message, and `post_id`, `post_status`, and `missing_required` data. Refresh the post or save it as a draft, resolve the requirements, and try publication again.

Scheduled posts are checked both when scheduling and when their publication is due. If requirements change and fail at that time, the post becomes Draft and its publication event is cleared. Resolving requirements does not automatically publish it: an authorized user must publish or schedule it again.

The latest failure marker is `_ediworman_publication_blocked`, containing only its reason, scheduled-hold flag, and timestamp. This is recovery state, not an audit history. Editor/list explanations are recomputed and resolved markers are removed lazily. Uninstall removes the marker and existing settings on each site.

## Supported integrations and limits

Gutenberg, the core REST posts controller, Quick Edit, bulk editing, and `wp_insert_post()`/`wp_update_post()` are guarded. Programmatic save functions can still return a post ID when publication is held; integrations must inspect `get_post_status()` before claiming success. Saves with after-insert hooks disabled use the ordinary insert-hook fallback. REST saves defer promotion until metadata and additional fields have completed successfully.

Holding then promoting a post invokes save hooks more than once. Integrations should make save callbacks idempotent and respond to actual public status transitions when publishing side effects are intended. Publication callbacks run only after the gate passes on guarded paths.

WordPress's lower-level `wp_publish_post()` directly writes the public status before its first transition hook. The plugin adds a defensive rollback on that path, but cannot prevent transient visibility or guarantee that third-party publication callbacks will not run. Use the guarded save APIs for integrations that need reliable pre-publication enforcement. Raw SQL status changes and plugins that remove or override enforcement hooks are outside this contract.

There are no role-based bypasses, one-off exceptions, or automatic retries. Administrators can fix the template or return a post type to Advisory. No telemetry or external requests are introduced.
