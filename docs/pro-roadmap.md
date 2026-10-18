# Editorial Workflow Manager: Pro Roadmap

All functionality in this document is **planned, not available in the current free plugin**. It preserves the detailed paid catalog while recording delivery dependencies, not promised release dates or versions.

Refer to [product scope](product-scope.md) for authoritative feature placement and the [Free roadmap](free-roadmap.md) for implemented foundations and upcoming Free additions. The [rule-registration API](rule-registration-api.md) and [publication policy](publication-policy.md) describe existing technical contracts, not Pro features.

This roadmap covers **Pro core** and the optional **Agency**, **Publisher**, and **Compliance** packs. Basic word/taxonomy limits, internal-link counts, post-type publication blocking, and basic JSON template transfer belong to Free, not the paid rule or portability library.

## Relationship to Free

The paid product should extend the free plugin from **baseline editorial validation** into **organization-specific policy, accountability, portability, scale, and governance**.

Free already provides the rule engine, public registration API, readiness surfaces, and basic Advisory / Block enforcement. Pro must extend those foundations rather than duplicate them or take away Free capabilities. Manual JSON file transfer between sites remains a Free capability once implemented; paid portability covers advanced workflows and managed operations.

Paid functionality must be delivered in a separate Pro add-on or other WordPress.org-compatible architecture. The free WordPress.org plugin must not ship disabled, trial-limited, license-gated, or partially implemented Pro functionality.

## Planned Pro core

### 1. Advanced Automated Requirements

Build organization-specific validation on the existing Free rule engine and public extension contract.

Pro rule library:

- H1 count.
- Required date field is populated.
- Required author field is populated.
- Advanced alternative-text length and conditional accessibility rules.
- Conditional rules.
- No-code custom rule composition.
- Rules backed by supported external-plugin data.

Rule-engine behavior:

- Configure rules per template and post type.
- Evaluate rules in Gutenberg and on the server.
- Clearly distinguish automatic results from manually completed items.
- Re-evaluate when relevant post data changes.
- Cache results without allowing stale results to bypass server-side enforcement.
- Extend the Free PHP and JavaScript rule registry without changing its result semantics.
- Handle missing, disabled, or unavailable integration data safely and explain the outcome. Preserve the Free API contract that inactive rule providers disappear from evaluation; do not claim that provider deactivation is automatically blocked.

### 2. Enforcement and policy modes

Build granular policy controls on the existing Free Advisory / Block setting.

- **Warning:** require explicit confirmation before continuing.
- Configure Advisory, Warning, or Restrict modes by template, post type, or role.
- Enforce restricted publishing on the server as well as in the editor UI.
- Define role-based bypass permissions.
- Optionally require a reason when an authorized user bypasses a gate.
- Record bypass actor, time, post, and reason in the audit log.

The default experience should remain low-friction; hard enforcement is an explicit administrator choice.

### 3. Lightweight approval workflow

Turn checklist readiness into a simple accountability flow without requiring a full workflow designer.

- Assign a post to a reviewer or approver.
- Submit a Ready post for review.
- Provide **Ready for review**, **Approved**, and **Changes requested** states.
- Support a one-step author-to-editor approval flow.
- Allow an approver to include a decision note.
- Display the current assignee, state, and latest decision in the editor.
- Apply capability checks to submission, approval, rejection, reassignment, and bypass actions.
- Preserve WordPress post-status compatibility where practical.
- Record approval actions in the basic audit log.

### 4. Notifications and automation hooks

Notify the right people when workflow actions require attention.

- Email when content is submitted for review.
- Email when content is approved.
- Email when changes are requested.
- Email when an assignment changes.
- Email reminders for overdue review work when due dates are available.
- Per-user and per-event notification preferences.
- Developer hooks for workflow events.
- Signed outgoing webhooks for submitted, approved, changes-requested, bypassed, and overdue events.
- Retry and failure logging for webhook delivery.

Slack and Microsoft Teams delivery belongs in the Compliance Pack unless later packaging research supports moving it into Pro.

### 5. Basic editorial audit log

Provide day-to-day traceability for teams and establish the foundation for the Compliance Pack.

- Log checklist item changes.
- Log automatic-rule result changes.
- Log template and policy changes.
- Log assignments and approval decisions.
- Log publishing-gate bypasses and reasons.
- Show a per-post activity timeline.
- Store actor, action, timestamp, object, and relevant context.
- Restrict log visibility by capability.

Pro provides an operational history. Append-only guarantees, retention policies, evidence attachments, and formal exports belong in the Compliance Pack.

### 6. Advanced workflow portability

Basic JSON template export/import, manual transfer between sites, schema validation, and safe preservation of items/rule configuration belong to Free. Essential import safety is not a paid upgrade.

- Port Pro-specific policy and approval configuration alongside supported template data.
- Add advanced controlled update/merge and conflict-resolution workflows beyond basic safe template import.
- Preview configuration differences and explicitly select create-new, skip, or update behavior for advanced workflow imports.
- Support authenticated, explicit remote transfer of supported Pro configuration; fleet orchestration remains Agency work.
- Keep all imports additive, versioned, backward compatible, and safe for existing Free data.

Advanced bundles, cloning/version notes, network synchronization, and client-specific libraries belong in the Agency Pack.

### 7. First-party integrations

Prioritize integrations in this order.

#### SEO

- Yoast SEO.
- Rank Math.
- All in One SEO.

Expose relevant SEO fields and scores to automated requirements without duplicating or modifying the other plugin's data.

#### Structured content

- Advanced Custom Fields Free and Pro.
- Required ACF values.
- Field-type-aware validation where practical.
- Repeater or group validation as a later advanced rule.

#### Authors and multilingual content

- Co-Authors Plus or PublishPress Authors.
- WPML or Polylang.
- Correct assignee, author, checklist-state, and approval behavior for translations and multi-author posts.

#### Additional compatibility

- Multisite-safe activation, permissions, and data handling.
- WooCommerce product workflows as a later compatibility expansion.

### 8. Optional Classic Editor and later editor compatibility

This is a secondary Pro feature, not a change to the Gutenberg-first positioning.

- Provide a minimal checklist metabox.
- Show manual and automated requirement results.
- Support submission and approval actions.
- Apply the same server-side publish gate.
- Avoid promising feature parity with the Gutenberg experience unless it is fully tested.
- Evaluate dedicated page-builder compatibility only later, after Gutenberg rules, enforcement, and approvals are stable.

### 9. Pro administration and support

- Pro settings and capability architecture.
- Safe additive database migrations.
- A setup flow for enabling rules, policy modes, integrations, and approvals.
- Compatibility checks against the required free-plugin version.
- Preserve all existing templates, mappings, checklist state, and readiness data when Pro is activated or removed.
- Premium product documentation and support.

## Planned Agency Pack

The Agency Pack should focus on **scale, brand control, reusable processes, and client transparency**.

### Client review

- External reviewers who do not require normal editorial access.
- Client-friendly approve and request-changes actions.
- Multiple reviewers where a project requires them.
- Secure, expiring review links if account-free review is supported.
- Client-facing decision notes and status visibility.
- Optional rendered-page review and contextual comments as a later enhancement.

### White-label and client mode

- Replace Pro branding in client-facing plugin screens where permitted.
- Simplify the interface for client reviewers.
- Hide internal workflow details that clients do not need.
- Configure terminology and instructions per client.

### Advanced template portability

- Template bundles for different clients or services.
- Clone templates with version notes.
- Export and import complete workflow configurations.
- Remotely copy templates between managed sites; ordinary manual JSON transfer remains Free.
- Per-client starter-template libraries.
- Preview changes before updating an existing remote template.

### Multisite and fleet management

- Network-level default templates and policies.
- Optional template synchronization across selected sites.
- Site-level overrides with clear inheritance rules.
- Multi-site process patterns for agency-managed installations.

### Agency operations

- Advanced assignment rules.
- Bulk queue views across relevant content.
- Weekly workflow and readiness digests.
- Client- or site-scoped reporting.

## Planned Publisher Pack

The Publisher Pack should focus on **pipeline visibility, scheduling, ownership, and workload**.

### Editorial workflow states

- Custom editorial statuses.
- Status colors and labels.
- Role-aware status transitions.
- Assignment ownership.
- Due dates.
- Status and assignment history.

### Pipeline views

- Global editorial queue.
- Calendar view.
- Kanban view.
- Workload view by author, editor, team, status, and due date.
- Filters for post type, readiness, status, assignee, and deadline.
- An expanded editorial dashboard for managers.

### Planning interactions

- Move content between dates or workflow stages with permission checks.
- Surface overdue and unassigned content.
- Claim available tasks where enabled.
- Send assignment and deadline reminders.
- Preserve checklist readiness as a visible signal throughout planning views.

Build this after the Pro rules, policy, and accountability foundation so planning views remain tied to meaningful readiness and workflow signals rather than becoming a separate generic planning suite.

## Planned Compliance Pack

The Compliance Pack should focus on **controlled sign-off, audit readiness, durable evidence, and governance**.

### Controlled approvals

- Configurable multi-step approval workflows.
- Sequential or role-based approval stages.
- Dual control or separation of duties.
- Prevent a user from approving their own work where policy requires it.
- No-bypass workflows.
- Restricted sign-off controls.
- A stronger permission matrix for each approval stage.
- Complete approval history.

### Audit integrity and retention

- Append-only audit events.
- Durable records for checklist changes, rule results, approvals, assignments, policy changes, and publishing actions.
- Configurable retention policies.
- Authorized legal-hold or retention-exception behavior if required later.
- Clear privacy and data-removal documentation.

### Evidence and exports

- Attach evidence to checklist items or approval decisions.
- Record evidence metadata and access permissions.
- Export approval history, checklist results, automated-rule results, bypasses, and evidence references.
- Produce human-readable audit reports and machine-readable exports.
- Filter exports by post, workflow, date range, actor, and outcome.

### Compliance integrations

- Signed webhooks for governance events.
- Slack notifications.
- Microsoft Teams notifications.
- Delivery logs and retry controls.
- Explicit administrator configuration and documentation for every external service.

## Suggested delivery order

### Phase 1: Pro foundation

1. Pro add-on architecture, compatibility checks, capabilities, and migrations.
2. A setup flow, data preservation on activation/removal, and premium administration/documentation.

### Phase 2: Advanced rules and policies

1. H1/date/author and advanced accessibility rules, conditional rules, and no-code composition built on the Free registry.
2. Per-template and per-role Advisory, Warning, and Restrict policies.
3. Role-based bypass permissions and reasons, with audit recording delivered alongside the first bypass feature.

### Phase 3: Accountability, notifications, and audit

1. Assignees and lightweight approval states.
2. Email notifications and workflow hooks.
3. Basic audit log.
4. Signed webhooks, retries, failure logs, and notification preferences; add overdue reminders when due dates are available.

### Phase 4: Advanced workflow portability

1. Pro policy/approval configuration transfer and advanced controlled import/update workflows.
2. Build on Free JSON template portability rather than introducing a competing basic format or restricting Free transfer.

### Phase 5: Integrations and compatibility

1. Yoast SEO, Rank Math, and All in One SEO.
2. Advanced Custom Fields.
3. Co-Authors Plus or PublishPress Authors.
4. WPML or Polylang.
5. Multisite compatibility and optional WooCommerce expansion.
6. Optional Classic Editor support; evaluate page builders only after the core editor workflows are stable.

### Phase 6: Premium packs

1. Agency Pack.
2. Publisher Pack.
3. Compliance Pack.

## Features deliberately excluded from the Pro scope

Follow the exclusions in [product scope](product-scope.md). In particular, this roadmap does not include:

- Social-media scheduling and auto-posting.
- A generic project-management suite unrelated to editorial readiness.
- AI content generation.
- Mandatory external SaaS processing for core checklist behavior.
- A calendar before automated requirements and enforcement are mature.

## Implementation guardrails

- Keep the free plugin fully functional without a license or Pro add-on.
- Do not place inaccessible Pro implementations, schemas, or Pro-only interfaces in the WordPress.org package.
- Use additive migrations and preserve free-plugin data.
- Apply capability checks and nonce validation to every state-changing action.
- Sanitize and validate input early; escape output at render time.
- Keep enforcement authoritative on the server.
- Make external notifications and webhooks explicit administrator choices.
- Do not add telemetry without informed opt-in.
- Document external-service terms and privacy behavior where applicable.
- Maintain Gutenberg-first clarity and avoid turning the base Pro experience into a heavy workflow suite.
