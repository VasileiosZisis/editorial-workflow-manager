# Editorial Workflow Manager

Editorial Workflow Manager adds editorial checklists to the WordPress block editor so teams can run a consistent pre-publish workflow.

## Free Version Features

- Reusable checklist templates (`ediworman_template` CPT).
- Checklist Template management is limited to users with page-management capabilities by default (typically Editors and Administrators).
- Row-based template editor with add/remove/reorder controls.
- Required and optional checklist items.
- Per-post checklist state in Gutenberg.
- Clear readiness/progress indicators:
  - Sidebar summary.
  - Status and visibility panel.
  - Non-blocking pre-publish warning.
- Template mapping by post type from plugin settings.
- Default templates created on activation.
- Translation-ready (`editorial-workflow-manager` text domain).

## Getting Started

1. Create or edit a checklist template under `Checklist Templates`.
2. Add items, reorder them, and mark each item as required or optional.
3. Assign the template to a post type in `Settings -> Editorial Workflow`.
4. Open a post in the block editor and use the `Editorial Checklist` sidebar.
5. Complete required items until the readiness UI reports the checklist is ready.

## Data Model (Backward Compatible)

- Legacy template items: `_ediworman_items` (`array<string>` labels).
- V2 template items: `_ediworman_items_v2` (`array<{id,label,required}>`) where `id` is UUID.
- Legacy checked state: `_ediworman_checked_items` (`array<string>` labels).
- V2 checked state: `_ediworman_checked_item_ids` (`array<string>` UUIDs).

Legacy templates remain supported. When a legacy template is edited/saved in the row editor, it is upgraded to v2 and the legacy mirror meta remains written for compatibility.

## Scope Notes

- Free version does not hard-block publishing.
- No front-end output; behavior is admin/editor only.
- By default, only Editors and Administrators can create, edit, or delete Checklist Templates.
- Built for block editor (Gutenberg), not Classic Editor.
- Readiness depends on required items only; optional items do not block completion.
