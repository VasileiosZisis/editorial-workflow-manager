# Editorial Workflow Manager

**The Gutenberg editorial checklist for teams that want consistent publishing quality without a heavy workflow suite.**

Editorial Workflow Manager helps editors and content teams create reusable checklists, track readiness, and follow a consistent pre-publish process directly inside the WordPress block editor.

Current release: **1.7.0**

## Technical highlights

- **Gutenberg integration:** Uses WordPress editor components and the `core/editor` data store to keep checklist state, live rule feedback, and pre-publish guidance connected to the editing workflow.
- **Configurable rules:** Combines reusable manual checklists with eleven configurable automatic requirements and an extensible [PHP/JavaScript rule registration API](docs/rule-registration-api.md). PHP evaluation is authoritative; optional JavaScript evaluators provide provisional live feedback.
- **Server-authoritative publication checks:** Opt-in Block mode evaluates saved post data before first publication or scheduling through the core REST controller and supported programmatic save APIs, then revalidates scheduled posts when publication is due. See [enforcement behavior and integration limits](docs/publication-policy.md).
- **Backward compatibility:** Supports legacy label-based templates and checked state alongside UUID-based items, upgrades templates when saved in the row editor, and maintains legacy template metadata for compatibility.

## Free Version Features

- Reusable checklist templates (`ediworman_template` CPT).
- Checklist Template management is limited to users with page-management capabilities by default (typically Editors and Administrators).
- Row-based template editor with add/remove/reorder controls.
- Keyboard and screen-reader support across checklist and template workflows.
- Editor assets and checklist state are scoped to avoid unnecessary admin work.
- Duplicate action for quickly cloning checklist templates.
- Local JSON template import/export with server-side preview, validation, and new-copy imports.
- Required and optional checklist items.
- Eleven configurable automatic requirements: featured image, excerpt, minimum/maximum words, minimum unique internal links, category/tag presence, minimum/maximum categories, minimum/maximum tags, and image alternative-text coverage.
- Public PHP and JavaScript registration API for code-defined automatic requirements.
- Opt-in Advisory / Block publication policy per post type, with authoritative server checks and scheduled-publication revalidation.
- Live automatic-result status in the block-editor sidebar.
- Optional helper text and a reference URL per checklist item.
- Per-post checklist state in Gutenberg.
- Clear readiness/progress indicators:
  - wp-admin post list Readiness column for mapped post types.
  - Ready, Incomplete, and Not calculated post-list filters.
  - Expandable missing required-item details.
  - Manager dashboard readiness summary.
  - Sidebar summary.
  - Status and visibility panel.
  - Pre-publish guidance reflecting the post type's Advisory or Block policy.
- Template mapping by post type from plugin settings.
- Curated starter templates created on activation and upgrade.
- Fresh-install Quickstart wizard for initial setup.
- One-time editor tour that opens and highlights the checklist sidebar.
- Feedback links to the official WordPress.org support forum.
- A per-user, dismissible WordPress.org review prompt after five unique checklist completions.
- Selected-post and batched all-post readiness recalculation tools.
- Translation-ready (`editorial-workflow-manager` text domain).

## Getting Started

1. Activate the plugin and complete the Quickstart wizard.
2. Choose the post types where checklists should appear and confirm the starter templates.
3. Open the post editor from the wizard and follow the one-time sidebar tour.
4. Adjust template mappings later in `Settings -> Editorial Workflow`.
5. Complete required items until the readiness UI reports the checklist is ready.
6. Administrators can optionally select Block in `Settings -> Editorial Workflow` to require readiness before first publication or scheduling.

## Documentation and Roadmap

- [WordPress.org listing and release history](readme.txt)
- [Product scope and Free/paid boundaries](docs/product-scope.md)
- [Free roadmap and implementation status](docs/free-roadmap.md)
- [Planned Pro and premium-pack roadmap](docs/pro-roadmap.md)
- [Automatic requirement rule API](docs/rule-registration-api.md)
- [Internal-link counting semantics](docs/internal-link-counting.md)
- [JSON template transfer and format](docs/template-portability.md)
- [Publication policy behavior and integration limits](docs/publication-policy.md)

Product scope defines feature placement, while the roadmaps distinguish completed work from planned additions. Planned Free, Pro, and premium-pack features are not availability claims. Only the features listed under **Free Version Features** are included in the current plugin. Historical research and superseded plans remain available in Git history.

## Data Model (Backward Compatible)

- Legacy template items: `_ediworman_items` (`array<string>` labels).
- V2 template items: `_ediworman_items_v2` (`array<{id,label,description,url,required}>`) where `id` is UUID.
- Automatic template requirements: `_ediworman_automatic_requirements` (built-in rule configuration, including `minimum_word_count: { enabled, minimum }` and `maximum_word_count: { enabled, maximum }`).
- Category/tag limits share that metadata: `minimum_category_count` and `minimum_tag_count` use `{ enabled, minimum }`; `maximum_category_count` and `maximum_tag_count` use `{ enabled, maximum }`.
- Registered custom-rule enablement shares `_ediworman_automatic_requirements`; unavailable namespaced registrations are preserved for provider reactivation.
- Legacy checked state: `_ediworman_checked_items` (`array<string>` labels).
- V2 checked state: `_ediworman_checked_item_ids` (`array<string>` UUIDs).
- Readiness cache: `_ediworman_required_total_cache`, `_ediworman_required_done_cache`, `_ediworman_readiness_cache`.
- Review prompt user state: up to five completed post IDs, a snooze timestamp, and a closed status.
- Publication modes: `publication_modes` inside `ediworman_settings`, keyed by post type (`advisory` or `block`).
- Latest blocked attempt: `_ediworman_publication_blocked` stores a reason, scheduled-hold flag, and timestamp; current explanations are recomputed.
- Settings announcement dismissal: site-scoped user option `ediworman_publication_announcement_dismissed` stores the fixed announcement version `1.7.0`, independently per administrator and site. This JSON-transfer announcement replaces the earlier announcement and appears even if that earlier announcement was dismissed. Routine plugin updates do not reset dismissal of the same announcement; uninstall removes it.

Legacy templates remain supported. When a legacy template is edited/saved in the row editor, it is upgraded to v2 and the legacy mirror meta remains written for compatibility.

## Scope Notes

- JSON transfer creates new, unmapped templates and preserves legacy/V2 items, UUIDs, and rule configuration. It transfers no mappings, policies, users, credentials, or checked state. Upload → preview → confirm works without JavaScript; previews expire after 15 minutes. See [the transfer format and limits](docs/template-portability.md).
- Minimum internal links stores `{ enabled, minimum }` under `minimum_internal_link_count` in the existing automatic-requirements metadata. It is disabled by default with a suggested minimum of 1; enabled values accept whole numbers from 1 to 100,000. It counts unique literal anchor destinations within the site's home host, port, and base path. Fragments, repeated destinations, same-post links, and non-content markup do not count. No destinations are fetched and no dynamic content is rendered. See [the complete counting contract](docs/internal-link-counting.md).
- Category and tag minimum/maximum counts are four separate requirements, disabled by default on existing and starter templates. Initial minimums are 1 and maximums are 5. Minimums accept whole numbers from 1 to 100,000; maximums accept 0 to 100,000. Both bounds are inclusive, and enabled minimum cannot exceed enabled maximum for the same taxonomy. Invalid submissions preserve that taxonomy's previous pair while unrelated valid changes save.
- Counts include distinct, directly assigned terms, not unassigned ancestors or descendants. WordPress's assigned default category counts; a maximum of zero can therefore conflict with its default-category behavior. Unsupported taxonomies are excluded. Gutenberg shows current selections where available; otherwise it shows saved PHP results with save-to-refresh guidance. PHP evaluates final saved assignments. The existing category-or-tag presence rule is unchanged and remains independent.
- Minimum and maximum word counts are independent required rules when enabled. Maximum defaults to 1,000 words but stays disabled on existing and starter templates. Enabled limits accept whole numbers from 1 to 100,000; both may be equal, but minimum cannot exceed maximum. Invalid submissions retain the previous word-limit pair while other valid checklist edits save.
- Word limits use saved post content, excluding shortcode tags, HTML tags/comments, and script/style contents, then decode HTML 4 entities and count Unicode letter/number sequences with internal apostrophes or hyphens. Adjacent HTML elements do not insert spaces; results can differ from the editor's native word-count display. The live checklist mirrors PHP counting, and saved PHP evaluation remains authoritative. Empty content passes a maximum-only check.

- Advisory is the default. Opt-in Block gates first publication (including private posts) and scheduling; already-published/private posts remain editable.
- Failed scheduled publication returns the post to Draft and requires an authorized user to publish or schedule again.
- No front-end output. Server enforcement also applies to REST and supported programmatic saves.
- By default, only Editors and Administrators can create, edit, or delete Checklist Templates.
- Built for block editor (Gutenberg), not Classic Editor.
- Readiness depends on required manual items and enabled, applicable automatic requirements; optional items do not block completion.
- Existing templates keep automatic requirements disabled until an editor enables them. Unsupported rules are excluded for the mapped post type.
- Enabled third-party rules are required. PHP results are authoritative; optional JavaScript evaluators provide provisional live feedback.
- The image alternative-text rule treats empty values and unavailable attachment records as incomplete; templates using intentionally decorative images can leave that rule disabled.
- Helper text and reference links are guidance only; they do not affect readiness.
- Feedback and review features send no telemetry and contact WordPress.org only after an intentional link click.
