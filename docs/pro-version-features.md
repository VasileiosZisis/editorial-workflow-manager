# Editorial Workflow Manager Pro: Consolidated Feature Plan

This document consolidates the paid-feature recommendations from:

- [Competitive Research Report](deep-research-report.md)
- [Competitive Analysis v2](deep-research-report-v2.md)

It defines the proposed **Pro core** and the optional **Agency**, **Publisher**, and **Compliance** packs. Competitor features mentioned only as market context are not automatically included; this plan contains the features the reports recommend for Editorial Workflow Manager.

## Product boundary

The paid product should extend the free plugin from **editorial consistency** into **automation, accountability, scale, and governance**.

- **Free** remains a complete, lightweight Gutenberg checklist and readiness product.
- **Pro** adds automated validation, enforcement, approvals, notifications, portability, integrations, and support.
- **Agency Pack** adds multi-site reuse, client review, branding, and agency-scale administration.
- **Publisher Pack** adds pipeline planning, assignments, schedules, queues, and workload visibility.
- **Compliance Pack** adds controlled approvals, durable evidence, retention, and audit exports.

Paid functionality must be delivered in a separate Pro add-on or other WordPress.org-compatible architecture. The free WordPress.org plugin must not ship disabled, trial-limited, license-gated, or partially implemented Pro functionality.

## Pro core

### 1. Automated Requirements rule engine

Add machine-verifiable checklist items alongside the existing manual items.

Initial rule library:

- Featured image is present.
- Excerpt is present.
- Minimum word count.
- Maximum word count.
- H1 count.
- Minimum or maximum category count.
- Minimum or maximum tag count.
- Image alternative text is present.
- Minimum internal-link count.
- Required date field is populated.
- Required author field is populated.

Rule-engine behavior:

- Configure rules per template and post type.
- Evaluate rules in Gutenberg and on the server.
- Clearly distinguish automatic results from manually completed items.
- Re-evaluate when relevant post data changes.
- Cache results without allowing stale results to bypass server-side enforcement.
- Provide an extensible PHP and JavaScript rule registry for future integrations.
- Allow third-party extensions to register additional validation rules.

### 2. Enforcement and policy modes

Allow each workflow to choose how incomplete requirements affect publishing.

- **Advisory:** show readiness guidance without interrupting publishing.
- **Warning:** require explicit confirmation before continuing.
- **Restrict publishing:** prevent publication until required items and rules pass.
- Configure the mode by post type, template, or role.
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

### 6. Template portability

- Export checklist templates as versioned JSON.
- Import JSON with schema validation and a safe preview.
- Preserve required/optional items, helper text, URLs, automated rules, and policy settings.
- Detect conflicts and offer create-new, skip, or update behavior.
- Copy a template to another site through an explicit administrator action.
- Keep imports additive and backward compatible.

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

### 8. Optional Classic Editor support

This is a secondary Pro feature, not a change to the Gutenberg-first positioning.

- Provide a minimal checklist metabox.
- Show manual and automated requirement results.
- Support submission and approval actions.
- Apply the same server-side publish gate.
- Avoid promising feature parity with the Gutenberg experience unless it is fully tested.

### 9. Pro administration and support

- Pro settings and capability architecture.
- Safe additive database migrations.
- A setup flow for enabling rules, policy modes, integrations, and approvals.
- Compatibility checks against the required free-plugin version.
- Preserve all existing templates, mappings, checklist state, and readiness data when Pro is activated or removed.
- Premium product documentation and support.

## Agency Pack

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
- Copy templates between managed sites.
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

## Publisher Pack

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

The research specifically recommends building this after the Pro rules engine and enforcement foundation because calendar competition is already crowded.

## Compliance Pack

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
2. Automated Requirements engine with core WordPress rules.
3. Advisory, warning, and restrict-publishing modes.
4. Server-side enforcement and role-based bypass policies.

### Phase 2: Accountability

1. Assignees and lightweight approval states.
2. Email notifications and workflow hooks.
3. Basic audit log.
4. Template import/export.

### Phase 3: Integrations

1. Yoast SEO, Rank Math, and All in One SEO.
2. Advanced Custom Fields.
3. Co-Authors Plus or PublishPress Authors.
4. WPML or Polylang.
5. Multisite compatibility and optional WooCommerce expansion.

### Phase 4: Premium packs

1. Agency Pack.
2. Publisher Pack.
3. Compliance Pack.

## Features deliberately excluded from the Pro scope

These competitor capabilities were discussed in the reports but were not recommended as part of Editorial Workflow Manager's paid differentiation:

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
