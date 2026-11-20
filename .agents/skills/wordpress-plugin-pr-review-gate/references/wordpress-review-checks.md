# WordPress-specific PR review checks

Apply selectively to changed paths; this is a **lens**, not a list of obligatory findings.

## Request entry points and permissions

- REST: `register_rest_route` method/route namespace, argument schema, `permission_callback`, request input validation and callback. Confirm that public routes are intentionally public; protected routes need appropriate capability and per-resource authorization.
- AJAX: `wp_ajax_...` and `wp_ajax_nopriv_...` dispatch, authenticated capability checks, client ownership, action-specific nonces for cookie-authenticated state mutations, response and termination behavior.
- admin-post: `admin_post_...`, `admin_post_nopriv_...`, redirect targets, nonce and permission rules on privileged actions.
- Admin screens: `is_admin()` is **not** an authorization test; menus and page callbacks may have distinct permission gates.
- Custom post types, post meta, users and attachments: `current_user_can( 'edit_post', $id )` is resource-aware for WordPress capabilities but may not encode custom tenant membership. Check actual application rules.

## Input, output, and persistence

- Input: unslash and validate values; sanitizing string input doesn't prove a numeric ID belongs to the requester. Consider allow-lists for status and option values.
- Output: escape for HTML text, attributes, URLs, JavaScript or constrained markup context; identify the actual output sink before alleging XSS.
- SQL: verify `$wpdb->prepare()` for untrusted parameters, but recognize that a prepared query can still return cross-client rows without tenant scoping.
- Files: downloaded bytes and original uploaded URLs may have different visibility; don't claim protection based only on an authorized PHP handler.
- Redirects: check trusted destinations and correct response control flow, and whether redirects occur after persisted state changes.
- Caches/transients: ensure new data keys are invalidated when affected; don't claim universal caching requirements.

## Hooks, callbacks and lifecycle

- Registration order, load timing, priority, callback signature and accepted arguments in `add_action`/`add_filter`; when a callback gains a required parameter, check hook-provided arguments and every direct callsite.
- Reentrancy: callbacks calling `wp_update_post()`/similar from save hooks may retrigger hooks. Confirm guard conditions or explicit temporary hook removal.
- Multisite: `switch_to_blog()`/`restore_current_blog()` paths and site-specific tables when the PR changes network behavior.
- Cron: recurrence/unscheduling, overlapping refresh, initial schedule, race conditions and end-of-request consistency.
- Upgrade/install/uninstall: new CPT/table/options behavior across existing installs, correct migrations and cleanup of user-created data.
- Enqueue: correct handles, dependencies, conditionally loaded assets and build-file paths; a missing asset must be shown via actual path mismatch.

## WordPress compatibility and distribution

- Check declared `Requires PHP`, `Requires at least`, WordPress feature availability, Composer/platform and CI matrices **where the change introduces new APIs or syntax**.
- Watch for PHP syntax/API changes unsupported by declared minimum versions, not generalized recommendations to support obsolete environments.
- If modifying release metadata, check main plugin header, `readme.txt` `Stable tag`, internal version constants and build artifact references against the intended release workflow.
- Use WordPress.org-specific submission or distribution rules only if the PR targets that distribution.
- Text domains, localization and unique identifiers matter when actually affected; code-style issues without demonstrated user impact are typically nonblocking notes.

## Authoritative references (use if already authorized to browse)

- WordPress plugin best practices: https://developer.wordpress.org/plugins/plugin-basics/best-practices/
- WordPress common review issues: https://developer.wordpress.org/plugins/wordpress-org/common-issues/
- Security overview: https://developer.wordpress.org/apis/security/
- REST custom endpoints: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- WordPress testing handbook: https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/

This reference informs review judgment, not claims about the codebase's actual WordPress version.
