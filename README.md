# Editorial Workflow Manager

Editorial Workflow Manager adds editorial checklists to the WordPress block editor so teams can run a consistent pre-publish workflow.

## Free Version Features

- Reusable checklist templates (`ediworman_template` CPT).
- Checklist Template management is limited to users with page-management capabilities by default (typically Editors and Administrators).
- Row-based template editor with add/remove/reorder controls.
- Keyboard and screen-reader support across checklist and template workflows.
- Editor assets and checklist state are scoped to avoid unnecessary admin work.
- Duplicate action for quickly cloning checklist templates.
- Required and optional checklist items.
- Optional helper text and a reference URL per checklist item.
- Per-post checklist state in Gutenberg.
- Clear readiness/progress indicators:
  - wp-admin post list Readiness column for mapped post types.
  - Sidebar summary.
  - Status and visibility panel.
  - Non-blocking pre-publish warning.
- Template mapping by post type from plugin settings.
- Curated starter templates created on activation and upgrade.
- Fresh-install Quickstart wizard for initial setup.
- One-time editor tour that opens and highlights the checklist sidebar.
- Feedback links to the official WordPress.org support forum.
- A per-user, dismissible WordPress.org review prompt after five unique checklist completions.
- Translation-ready (`editorial-workflow-manager` text domain).

## Getting Started

1. Activate the plugin and complete the Quickstart wizard.
2. Choose the post types where checklists should appear and confirm the starter templates.
3. Open the post editor from the wizard and follow the one-time sidebar tour.
4. Adjust template mappings later in `Settings -> Editorial Workflow`.
5. Complete required items until the readiness UI reports the checklist is ready.

## Data Model (Backward Compatible)

- Legacy template items: `_ediworman_items` (`array<string>` labels).
- V2 template items: `_ediworman_items_v2` (`array<{id,label,description,url,required}>`) where `id` is UUID.
- Legacy checked state: `_ediworman_checked_items` (`array<string>` labels).
- V2 checked state: `_ediworman_checked_item_ids` (`array<string>` UUIDs).
- Readiness cache: `_ediworman_required_total_cache`, `_ediworman_required_done_cache`, `_ediworman_readiness_cache`.
- Review prompt user state: up to five completed post IDs, a snooze timestamp, and a closed status.

Legacy templates remain supported. When a legacy template is edited/saved in the row editor, it is upgraded to v2 and the legacy mirror meta remains written for compatibility.

## Scope Notes

- Free version does not hard-block publishing.
- No front-end output; behavior is admin/editor only.
- By default, only Editors and Administrators can create, edit, or delete Checklist Templates.
- Built for block editor (Gutenberg), not Classic Editor.
- Readiness depends on required items only; optional items do not block completion.
- Helper text and reference links are guidance only; they do not affect readiness.
- Feedback and review features send no telemetry and contact WordPress.org only after an intentional link click.
