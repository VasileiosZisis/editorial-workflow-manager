# Editorial Workflow Manager: Current Plugin Recommendations (v3)

This document translates [Competitive Research Report v3](deep-research-report-v3.md) into a focused feature plan for the current free plugin. It supersedes the previous recommendations document.

The current `1.0.0` plugin is already complete within its lightweight Gutenberg checklist and readiness scope. The v3 research recommends strengthening that same product loop rather than adding calendars, project management, social scheduling, or a broad approval suite to Free.

## Decision summary

| Decision | Features |
|---|---|
| **Add to Free** | Automated Requirements Lite with five built-in checks. |
| **Add to Free** | A stable PHP and JavaScript rule-registration API for code-defined checks. |
| **Add to Free after the rule architecture is validated** | A basic opt-in **Advisory / Block** publication policy per post type. |
| **Keep in Pro** | Advanced rules, no-code rule composition, integrations, granular policies, bypass governance, approvals, notifications, audit history, portability, Classic Editor support, and premium administration. |
| **Keep in premium packs** | Agency, Publisher, and Compliance capabilities. |
| **Do not add to the core roadmap** | Social scheduling, social analytics, generic project management, AI content generation, or a mandatory SaaS dependency. |

The intended value ladder is:

- **Free verifies basic editorial readiness.**
- **Pro applies organization-specific policy, accountability, integrations, and portability.**
- **Premium packs add agency scale, publishing operations, and compliance governance.**

## 1. Automated Requirements Lite

Add automatic checks alongside the existing manual required and optional checklist items.

### Included free rules

#### Featured image present

- Pass when the post has a valid featured image.
- Show a clear missing-featured-image message when incomplete.
- Hide or mark the rule unavailable for post types without thumbnail support.

#### Excerpt present

- Pass when the stored excerpt contains meaningful text after normalization.
- Do not treat generated excerpts as an explicitly completed excerpt.

#### Minimum word count

- Count readable post content consistently on the client and server.
- Provide a limited administrator-configured minimum.
- Exclude markup and non-content artifacts from the count where practical.

#### Basic taxonomy presence

- Require at least one assigned category and/or tag where those taxonomies apply.
- Do not expose irrelevant controls for post types that do not support the selected taxonomy.
- Keep configurable minimum and maximum taxonomy counts in Pro.

#### Image alternative-text coverage

- Evaluate relevant content images and the featured image where applicable.
- Identify which image is missing alternative text.
- Document how decorative images and unavailable attachment records are handled.
- Keep advanced ALT-length and conditional accessibility policies in Pro.

### Rule behavior

- Automatic rules must be visually distinct from manual checklist items.
- Each result needs a stable rule ID, translated label, pass/fail state, and actionable explanation.
- Automatic rules participate in the same Ready, Incomplete, and Not calculated model as manual required items.
- Optional manual items remain non-blocking.
- Rules re-evaluate when relevant post content, metadata, taxonomies, authors, or media change.
- Gutenberg feedback may update immediately, but the server remains authoritative.
- Cached results may improve list-table and dashboard performance but must never authorize publication when stale.
- Malformed or unavailable rule results must not be silently treated as passing.

### Integration with existing `1.0.0` manager visibility

- The Readiness column includes automatic-rule progress.
- Missing-item details include failed automatic-rule labels and useful diagnostics.
- Ready, Incomplete, and Not calculated filters remain exact.
- Selected-post and all-post recalculation re-evaluate automatic rules.
- Dashboard counts reflect the combined manual and automatic readiness result.
- Existing search, status, taxonomy, pagination, and third-party filters remain compatible.

## 2. Public rule-registration API

The rule engine should be extensible in Free so agencies and developers can implement site-specific validation without modifying the plugin.

### PHP contract

- Register a uniquely prefixed rule ID and metadata.
- Declare supported post types and required data dependencies.
- Provide a server-side evaluation callback.
- Return a normalized result containing status and an actionable message.
- Provide cache-invalidation dependencies where the rule needs them.
- Reject malformed registrations and duplicate IDs safely.

### JavaScript contract

- Allow an optional Gutenberg-side evaluator or data adapter.
- Use a versioned localized or REST data contract.
- Require a server-side evaluator for any rule that can affect publication blocking.
- Keep editor and server result semantics aligned.

### Developer experience

- Version the public rule-result schema.
- Document lifecycle, registration timing, capabilities, caching, and error behavior.
- Ship a small sample extension that registers one custom rule.
- Add deprecation handling before changing an established contract.

The Free API should not include a no-code rule builder, third-party commercial adapters, conditional policy composition, or premium support guarantees.

## 3. Basic opt-in publication blocking

Add this only after the rule engine and server validation have proven reliable.

### Free policy scope

- Provide only **Advisory** and **Block** modes.
- Configure the mode per mapped post type.
- Default every post type to Advisory so existing behavior does not change after update.
- Block publication when a required manual item or enabled free automatic rule fails.
- Revalidate current results authoritatively on the server before allowing publication.
- Cover Gutenberg, REST updates, Quick Edit, bulk actions, and direct status transitions.
- Provide an accessible editor explanation listing what must be completed.
- Let an authorized administrator return the post type to Advisory mode through settings.

### Deliberately excluded from the free gate

- Per-template enforcement modes.
- Per-role policy modes.
- Post-level exceptions.
- Role-based bypass permissions.
- One-off bypasses.
- Mandatory bypass reasons.
- Bypass audit events.
- Multi-step or no-bypass compliance policies.

Those capabilities remain a meaningful Pro and Compliance upgrade.

## 4. Supporting architecture

Define these boundaries before implementing the first automatic rule.

### Evaluation layers

Keep the following responsibilities separate:

1. Rule registration and configuration.
2. Client-side presentation and provisional evaluation.
3. Server-side authoritative evaluation.
4. Readiness aggregation.
5. Publication-policy evaluation.
6. Pro integrations and advanced policy extensions.

### Stored data

- Prefix every option, post-meta key, cache key, action, script handle, and REST/AJAX identifier with `ediworman_` or `_ediworman_` as appropriate.
- Version stored rule configuration and result schemas.
- Add only the data required by enabled Free rules.
- Invalidate results when templates, mappings, rule configuration, or relevant post data changes.
- Remove newly introduced data during single-site and multisite uninstall.
- Use safe additive migrations and preserve all current templates, mappings, checklist state, and readiness data.

### Security and reliability

- Pair every state-changing nonce with a capability check.
- Sanitize and validate input when received and escape output at render time.
- Enforce Block mode on the server; editor controls are explanatory, not authoritative.
- Prevent autosaves, revisions, imports, REST requests, and bulk operations from accidentally bypassing policy.
- Fail predictably when a registered third-party rule throws an error or returns malformed data.
- Add no telemetry or automatic external requests.

### Performance and accessibility

- Load rule UI assets only on eligible mapped editor or manager screens.
- Avoid reparsing unchanged block content on every render.
- Batch recalculation using the existing manager-visibility approach.
- Preserve keyboard operation, visible focus, screen-reader labels, and live announcements.
- Keep post-list and dashboard queries bounded and based on cached aggregate results.

## 5. Features that remain Pro

The v3 report does not recommend moving the complete Pro foundation into Free.

### Advanced Automated Requirements

- Maximum word count.
- H1 count.
- Configurable minimum and maximum category or tag counts beyond basic presence.
- Internal-link thresholds.
- Required date fields.
- Required author fields.
- Advanced ALT-length or conditional accessibility rules.
- Conditional rules.
- No-code custom rule composition.
- Rules based on external plugins or organization-specific data.

### Granular enforcement and governance

- Advisory, Warning, and Restrict modes by template or role.
- Role-specific policies.
- Role-based bypass capabilities.
- Mandatory bypass reasons.
- Audit records for bypasses and policy changes.

### Accountability and communication

- Reviewer or approver assignment.
- Ready for Review, Approved, and Changes Requested states.
- Approval notes and reassignment.
- Configurable email notifications and reminders.
- Signed webhooks, retries, and delivery logs.
- Operational audit timeline.

### Integrations and portability

- Yoast SEO, Rank Math, and All in One SEO.
- Advanced Custom Fields.
- Co-Authors Plus or PublishPress Authors.
- WPML or Polylang.
- WooCommerce workflows.
- JSON template import/export and conflict handling.
- Cross-site template copying and synchronization.
- Optional Classic Editor and later page-builder compatibility.

### Premium packs

- **Agency:** external and multiple reviewers, secure review links, client mode, rendered-page review enhancements, white labeling, bundles, network synchronization, digests, and client/site reporting.
- **Publisher:** custom statuses, role-aware transitions, ownership, due dates, queues, calendar, Kanban, workload views, reminders, and task claiming.
- **Compliance:** multi-step approvals, separation of duties, no-bypass policy, append-only logs, retention, evidence, formal exports, Slack, and Microsoft Teams governance delivery.

## 6. Recommended implementation order

### Phase 1: Foundation

1. Define the versioned rule and result schemas.
2. Separate registration, evaluation, readiness aggregation, and policy evaluation.
3. Define cache invalidation and backward-compatible migrations.

### Phase 2: Free automatic rules

1. Featured image.
2. Excerpt.
3. Minimum word count.
4. Basic category/tag presence.
5. Image alternative-text coverage.
6. Integrate all five with editor readiness and manager visibility.

### Phase 3: Extensibility

1. Publish the PHP registration API.
2. Publish the optional JavaScript contract.
3. Add API documentation and a sample extension.

### Phase 4: Optional enforcement

1. Add Advisory / Block settings per mapped post type.
2. Add authoritative server-side revalidation.
3. Cover every supported publication path and recovery state.
4. Update onboarding, settings help, README, `readme.txt`, screenshots, changelog, and upgrade notice.

## Acceptance criteria

- A mapped post can combine manual required items, optional items, built-in rules, and registered third-party rules.
- The same inputs produce equivalent editor and server results.
- Readiness filters, missing details, recalculation, and dashboard counts reflect automatic rules correctly.
- No stale or missing cache entry can authorize publication in Block mode.
- Block mode is disabled by default and configured only per post type in Free.
- Free contains no locked advanced-rule controls, Pro placeholders, license checks, quotas, or trial expirations.
- A separate extension can register and display a custom rule without modifying plugin code.
- Existing legacy and UUID templates continue to work without migration loss.
- Uninstall removes all newly introduced plugin data on single-site and multisite installations.
- PHP syntax, PHPCS, JavaScript syntax, Plugin Check, accessibility checks, and manual cross-version validation pass before release.

## Explicitly deferred or excluded

- Calendar, Kanban, queue, and workload features remain late Publisher Pack work.
- Approvals, assignments, comments, and notifications remain paid workflow capabilities.
- Social scheduling and social analytics are excluded.
- Generic task or project management is excluded.
- AI content generation is excluded.
- Core checklist behavior must not depend on a mandatory external SaaS service.
