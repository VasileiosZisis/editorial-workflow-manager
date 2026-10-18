# Editorial Workflow Manager: Product Scope

This is the authoritative feature-placement document. It defines what belongs in Free, Pro, and the premium packs, not what is already available. Consult the [Free roadmap](free-roadmap.md) for implementation status and the [Pro roadmap](pro-roadmap.md) for the planned paid catalog and delivery sequence.

## Purpose and audience

Editorial Workflow Manager provides reusable Gutenberg editorial checklists and trustworthy publishing readiness without becoming a general project-management suite.

- Authors and editors need clear publishing standards, useful guidance, and understandable missing-requirement messages.
- Content teams and managers need readiness visibility and an optional way to enforce required items.
- Agencies need to reuse templates across sites without rebuilding ordinary publishing standards.
- Developers need a public rule-registration API for code-defined checks.
- Organizations needing formal accountability, integrations, scale, or governance are the intended audience for separate paid add-ons.

Free must be a complete product for basic editorial validation, not a demonstration with locked local functionality. Paid value begins with organization-specific policy, accountable decisions, external-plugin integrations, and managed workflows.

## Feature placement

The Free boundary includes both current functionality and the planned additions identified in the [Free roadmap](free-roadmap.md). Placement is not an availability claim.

| Capability | Placement | Reason |
|---|---|---|
| Reusable templates, required/optional items, helper text, reference links, duplication, and starter templates | Free | The complete manual-checklist workflow is the product foundation. |
| Gutenberg checklist state, pre-publish guidance, onboarding, accessibility, feedback links, and dismissible review prompts | Free | Adoption and everyday usability should not require an add-on. |
| Readiness column, exact filters, missing-item details, recalculation, and dashboard summary | Free | Managers need visibility into the same readiness result authors see. |
| Featured image, excerpt, and basic image alternative-text coverage | Free | Common publishing standards belong in baseline validation. |
| Minimum and maximum word count | Free | Both ends of an ordinary content-length requirement are basic validation. |
| Minimum and maximum category/tag counts | Free | Taxonomy checks should support ordinary publishing standards beyond simple presence. |
| Basic internal-link count requirement | Free | A clear content requirement with broadly useful local validation. |
| Public PHP and optional JavaScript rule-registration API | Free | Developers should be able to add site-specific checks without modifying the plugin. |
| Opt-in Advisory / Block publication policy per post type | Free | Required items must be enforceable; Advisory remains the default. |
| Basic JSON template import/export, including manual transfer between sites | Free | Template reuse reduces adoption friction, particularly for agencies. |
| H1/date/author checks, advanced accessibility rules, conditional rules, no-code composition, and integration-backed validation | Pro | These address specialized content models and organization-specific policy. |
| Granular template/role policies, Warning mode, governed bypasses, reasons, approvals, and assignments | Pro | Accountability and policy governance extend beyond basic readiness. |
| Configurable workflow emails, reminders, signed webhooks, operational audit history, and advanced workflow portability | Pro | These coordinate and trace work across people and systems. |
| First-party integrations, optional Classic Editor support, and premium administration/support | Pro | These introduce dedicated compatibility and operational responsibilities. |
| Client review, branding, bundles, remote fleet operations, and network synchronization | Agency Pack | Managed reuse, client transparency, and agency-scale administration. |
| Custom statuses, ownership, due dates, queues, calendar, Kanban, and workload views | Publisher Pack | Editorial pipeline planning is a separate, later product layer. |
| Multi-step approvals, separation of duties, no-bypass policies, append-only records, evidence, retention, exports, and governance delivery | Compliance Pack | Controlled sign-off and durable evidence serve specialized governance needs. |

## Portability boundary

Free basic JSON portability covers template items and their rule configuration, including required/optional flags, helper text, reference URLs, and registered-rule enablement. A user can export a file and import it on another site. Schema validation, safe input handling, understandable feedback, and preservation of supported data are baseline requirements, not paid upgrades.

Free template transfer does not automatically copy site-level post-type mappings or publication policies, approval workflows, audit records, users, or credentials. An imported template must not silently enable Block mode.

Pro may add portability for Pro-specific policy and approval configuration and advanced controlled update/conflict workflows. The Agency Pack adds multi-template bundles, client libraries, remote copying with previews, network defaults, inheritance, and synchronization. Ordinary manual file transfer between sites remains Free.

## Product and implementation principles

- Keep Gutenberg-first clarity, predictable settings, and a lightweight editor/admin footprint.
- Treat PHP evaluation as authoritative and JavaScript feedback as provisional. Cached readiness must never authorize publication.
- Preserve the existing contracts in the [rule-registration API](rule-registration-api.md) and [publication policy](publication-policy.md), including unavailable-provider behavior and documented lower-level publication limits.
- Preserve legacy and UUID templates, existing configuration, and stored checklist state through additive changes.
- Keep optional checks optional, new rules disabled until configured, and publication enforcement an explicit administrator choice.
- Keep settings and state changes capability-checked and nonce-protected; sanitize and validate inputs, escape output, and preserve keyboard/screen-reader operation.
- Require explicit configuration for external services and document relevant terms and privacy behavior. Add no telemetry or tracking without informed opt-in.
- Do not add public attribution or unsolicited external requests. Support/review destinations are contacted only after intentional clicks.
- Deliver paid functionality in separate add-ons. The WordPress.org plugin must contain no inaccessible Pro implementations, Pro-only scaffolding, license gates, trials, or quotas on built-in functionality.

## Exclusions and priorities

Social scheduling, social analytics, generic project management, AI content generation, and a mandatory external SaaS dependency for core readiness are outside scope. Calendar, Kanban, and workload views remain later Publisher Pack work rather than prerequisites for a useful Free or Pro product.

Use truthful listing copy, practical examples, support FAQs, and demonstrations to explain readiness versus formal approvals and planning. Do not present roadmap entries as shipped functionality or use historical competitor observations as current verified facts.

This document and the two roadmaps replace the historical research/planning documents. Their committed versions remain recoverable through Git; no parallel numbered recommendation documents are maintained.
