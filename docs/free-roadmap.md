# Editorial Workflow Manager: Free Roadmap

This document tracks implementation status and delivery order for the Free plugin. [Product scope](product-scope.md) owns feature placement; technical behavior is documented in the [rule-registration API](rule-registration-api.md) and [publication policy](publication-policy.md).

The current implemented baseline is **1.5.0**. Word-count and taxonomy count limits are implemented; internal-link count and JSON portability remain planned. No future release numbers, dates, or effort estimates are assigned here.

## Completed milestones

| Milestone | Implemented capabilities |
|---|---|
| Checklist foundation, before 1.0.0 | Reusable templates; legacy/UUID checklist state; required/optional items; helper text and reference URLs; duplication and starters; post-type mappings; Gutenberg sidebar and pre-publish guidance; Quickstart and editor tour; accessibility/performance improvements; support links and per-user review prompts. |
| 1.0.0: Manager visibility | Exact Ready, Incomplete, and Not calculated filters; missing-required details; selected and batched recalculation; Editorial Readiness dashboard summary. |
| 1.1.0: Automated Requirements Lite | Featured image, excerpt, minimum words, basic category/tag presence, and image ALT coverage, integrated with editor/server readiness and manager visibility. |
| 1.2.0: Developer extensibility | Versioned PHP/JavaScript rule registration, authoritative PHP evaluation, dependency-driven invalidation, saved-result refresh, API documentation, and a sample extension. |
| 1.3.0: Optional publication blocking | Advisory / Block per post type; uncached validation on supported publication/scheduling paths; due scheduled-post revalidation; saved-work preservation and recovery messages; published/private updates remain editable; settings-only announcement with per-administrator/site dismissal. |
| 1.4.0: Word-count limits | Independently enabled maximum words alongside minimum; inclusive limits; enabled-range validation preserving previous bounds; live counting aligned with existing authoritative PHP semantics; integration with all readiness and enforcement surfaces. |
| 1.5.0: Category/tag count limits | Four independent category/tag minimum and maximum rules; maximum-zero support; taxonomy-pair validation; live/saved editor feedback; term-change invalidation; integration with readiness and publication enforcement; updated settings announcement. |

Publication blocking is already implemented and remains Free. Its documented direct `wp_publish_post()` rollback limitation is not a promise of complete protection for raw or unsupported publication paths.

## Feature delivery order and status

### 1. Maximum word count alongside minimum

**Status:** Implemented in 1.4.0.

**Purpose:** Support ordinary content-length limits without requiring Pro.

**Scope and dependencies:** Extend the existing readable-content counting and template rule configuration with an optional maximum while retaining the current minimum and stored settings. Reuse the rule evaluation, readiness, and publication-policy infrastructure.

**Acceptance criteria:**

- Editors can enable a maximum independently of the existing minimum; existing templates behave unchanged until configured.
- Validate bounds and reject contradictory enabled ranges without discarding valid existing configuration.
- Evaluate boundary values consistently in PHP and Gutenberg using the same counting semantics as the current minimum.
- Report the actual count and required limit in actionable messages, and include failures in every readiness/enforcement surface.

### 2. Minimum/maximum category and tag counts

**Status:** Implemented in 1.5.0.

**Purpose:** Make taxonomy checks useful for ordinary publishing standards beyond presence alone.

**Scope and dependencies:** Add four independently configurable category and tag minimum/maximum rules where supported. Preserve the existing presence-only rule and its configuration unchanged; no general conditional-rule builder is included.

**Acceptance criteria:**

- Configure minimum and maximum counts for categories and tags independently, with clear disabled/unset behavior and valid ranges.
- Count assigned terms, handle empty selections, and document how default categories affect the result.
- Exclude unsupported taxonomies rather than failing irrelevant requirements.
- PHP uses saved assignments, Gutenberg reflects current selections, and relevant taxonomy changes invalidate readiness.
- Preserve prior templates and show useful actual/expected counts across readiness and enforcement.

### 3. Basic internal-link count requirement

**Status:** Planned; next user-facing feature after taxonomy count limits.

**Purpose:** Provide understandable validation for a minimum number of internal links in article content.

**Scope and dependencies:** Add a locally evaluated, template-configured minimum internal-link requirement through the existing rule pipeline. It is not a crawler, broken-link checker, SEO-service integration, or conditional link-analysis suite.

**Acceptance criteria:**

- Define and document consistent handling of relative/absolute URLs, site boundaries, repeated links, fragments, and non-content markup before implementation.
- Apply matching PHP/editor counting semantics, showing actual and required counts.
- Re-evaluate on relevant content changes without fetching destinations or making external requests.
- Use scoped assets and avoid unnecessary repeated parsing of unchanged content.
- Integrate failures into all readiness and publication-policy surfaces without changing existing templates by default.

### 4. Basic JSON template import/export

**Status:** Planned; lower priority, after the additional content rules.

**Purpose:** Let users reuse checklist templates across sites through ordinary file transfer.

**Scope and dependencies:** Export/import versioned template data, including manual items, UUIDs, required/optional flags, helper text, reference URLs, built-in rule settings, and custom-rule enablement. Build on the settled configuration of the preceding rules and the current unavailable-provider preservation contract.

**Acceptance criteria:**

- Export from one site and import on another without requiring Pro or a remote connection.
- Validate schema/version, permitted fields, data types, file size, and authorization; do not execute imported code or fetch file-specified resources.
- Explain invalid or unsupported input without partially corrupting existing templates; essential validation and understandable preview/feedback are Free.
- Preserve supported template data and inactive custom-rule entries without bundling provider code or silently treating unknown rules as passing active checks.
- Handle identity/conflict cases safely and explicitly, avoiding unintended template overwrites or changes to existing posts' checklist state.
- Do not automatically transfer mappings, turn on Block mode, or import users, credentials, audit data, or approval workflows.
- Leave workflow bundles, remote copying/synchronization, and advanced managed update/merge workflows to the paid roadmap.

## Shared delivery requirements

Every new rule must use the current registration/evaluation pipeline and participate in the sidebar, pre-publish guidance, uncached PHP validation, missing-required explanations, readiness column/filters, selected/all-post recalculation, and dashboard totals. JavaScript is provisional; PHP is authoritative, and stale aggregate caches cannot authorize publication.

Preserve existing templates and keys through additive configuration changes. Use declared dependencies for invalidation, maintain legacy/UUID support, retain inactive custom-rule configuration, and respect the current exclusion of unavailable providers from evaluation. Active evaluator errors must fail safely.

All state changes require appropriate capabilities and nonce protection. Maintain translated/escaped output, scoped assets, keyboard operation, visible focus, screen-reader announcements, bounded manager queries, and single-site/multisite data cleanup. Do not add locked Pro controls or change the default Advisory policy.

## Documentation and release verification

These are delivery tasks, not additional shipped product features:

- Keep the README/current-feature list, listing text, screenshots, demos, FAQs, and technical references aligned with what is actually implemented.
- Explain manual checklists, automatic requirements, optional enforcement, and planned paid workflows without overstating availability or legal/compliance guarantees.
- Update the changelog, upgrade guidance, and version metadata when releasing implemented functionality; this roadmap does not preassign versions.
- Verify legacy/new templates, enabled/disabled settings, threshold boundaries, unsupported post types, evaluator errors, inactive providers, invalid input, and permissions.
- Exercise editor and saved-state parity, all manager readiness surfaces, supported publication paths, draft saving, and upgrade/uninstall behavior.
- Run PHP/JavaScript syntax checks, PHPCS, Plugin Check against the distributable, accessibility checks, and appropriate LocalWP/manual compatibility checks before release.

## Optional development-tooling improvements

Repository-based PHPUnit regression tests, repeatable editor E2E/accessibility tests, compatibility-matrix CI, and optional deeper static analysis can improve repeatability. These are separate engineering improvements, not missing end-user capabilities, and are not implied to be installed or shipped with the plugin.
