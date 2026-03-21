=== Editorial Workflow Manager ===
Contributors: vzisis
Tags: editorial, checklist, workflow, publishing, gutenberg
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial checklist and pre-publish workflow for the WordPress editor. Create reusable checklists and get clear readiness feedback before publishing.

== Description ==

**Editorial Workflow Manager** adds an **editorial checklist** to the WordPress **block editor (Gutenberg)** so your team can follow a consistent **pre-publish checklist** every time you publish.

It’s built for content teams, agencies, and editors who want a lightweight **publishing checklist workflow** inside the editor — with clear “ready vs incomplete” feedback — **without hard publish blocking**.

This plugin does not output anything on the front end.

=== What you can do ===

* Create reusable **checklist templates** (required + optional items).
* Assign different **editorial checklists** to different post types.
* Track per-post checklist progress in the editor sidebar.
* See readiness and progress in the sidebar, the post status panel, and the pre-publish panel.

=== Typical use cases ===

* Blog posts: SEO checks, featured image, categories/tags, internal links, fact check.
* News/Editorial: legal review, source confirmation, editor sign-off checklist.
* Agencies: client approvals checklist, accessibility checks, brand requirements.
* Teams: consistent publishing workflow across authors and editors.

== Key features ==

* **Checklist templates** – manage reusable checklists as a custom post type.
* **Template editor (row-based UI)** – add, remove, reorder items; mark each item Required or Optional.
* **Required vs Optional items** – readiness is based on required items only.
* **Per-post checklist state** – each post/page stores its own checklist progress.
* **Gutenberg / Block Editor sidebar** – “Editorial Checklist” panel inside the editor.
* **Readiness + progress indicators**
  * Sidebar summary with Ready / Incomplete and required progress
  * Post Status panel summary while editing
  * Non-blocking pre-publish warning when required items are missing
* **Different checklist per post type** – assign templates in Settings.
* **Default templates included** on activation.
* **Backward compatible upgrades** – legacy templates still work and upgrade to UUID-based v2 items when saved.

== Getting started ==

1. Go to **Checklist Templates → All Templates**.
2. Review the default templates or create your own.
3. Edit a template to add items, reorder them, and mark each item as **Required** or **Optional**.
4. Go to **Settings → Editorial Workflow**.
5. Assign a checklist template to each post type you want to support.
6. Edit a post in the **block editor** and open the **Editorial Checklist** sidebar.
7. Complete items and watch the readiness/progress summary update.

== Installation ==

1. Upload the `editorial-workflow-manager` folder to `/wp-content/plugins/`, or install via **Plugins → Add New**.
2. Activate the plugin in wp-admin.
3. On activation, default checklist templates are created.
4. Assign a template to a post type in **Settings → Editorial Workflow**.

== Frequently Asked Questions ==

= Does the plugin block publishing when required items are missing? =
No. The pre-publish warning is **non-blocking**.

= Does this work with Classic Editor? =
No. The checklist UI is built for **Gutenberg / the block editor** only.

= Can I use different checklists per post type? =
Yes. Assign templates in **Settings → Editorial Workflow**.

= Do optional items affect readiness? =
No. Readiness is based on **REQUIRED** items only.

= What happens to older templates/checklist data? =
Legacy templates and label-based checked state remain supported.

Templates are now stored in an upgraded **v2** format with UUID-based item IDs for more stable matching. When a legacy template is edited and saved in the new editor, it is upgraded to v2 automatically. A compatibility meta mirror is still maintained for legacy support.

== Screenshots ==

1. Editorial Checklist sidebar in the block editor (Gutenberg) with required progress.
2. Checklist template editor with required/optional items and reorder controls.
3. Settings screen for assigning checklist templates to post types.
4. Pre-publish checklist warning when required items are missing.

== Changelog ==

= 0.5.0 =
* Changed Checklist Template permissions to use WordPress page capabilities.
* Only Editors and Administrators can now manage Checklist Templates by default; Authors and Contributors may lose access.
* Added a one-time admin notice after update to warn site owners about the capability change.

= 0.4.0 =
* Added Required vs Optional checklist items.
* Added clearer readiness/progress indicators across the sidebar, post status panel, and pre-publish panel.
* Improved template editing UX with a row-based editor (add/remove/reorder, required/optional per item).
* Introduced UUID-based v2 template items and checked-state tracking for stable matching when labels change (after template upgrade).
* Hardened template saving against malformed/empty submissions to reduce accidental data loss.
* Kept backward compatibility by continuing to read/write legacy label-based meta.

= 0.3.4 =
* Various security improvements (better data validation and sanitization).

= 0.3.3 =
* Small syntax fix in uninstall.php.

= 0.3.2 =
* Added uninstall.php cleanup to remove plugin data when the plugin is deleted.

= 0.3.1 =
* Added non-blocking pre-publish checklist warning when checklists are incomplete.
* Added checklist activity hint ("Last updated by X on [date/time]").

= 0.3.0 =
* First public release.

== Upgrade Notice ==

= 0.5.0 =
Checklist Template permissions are now limited to roles with page-management capabilities. By default, this means Editors and Administrators can manage templates, while Authors and Contributors may lose access after updating.

= 0.4.0 =
Introduces Required vs Optional checklist items, clearer readiness indicators, and an improved template editor. Legacy templates continue to work; saving a legacy template upgrades it to UUID-based v2 items for more stable tracking.

= 0.3.2 =
Adds uninstall cleanup to remove plugin data when the plugin is deleted.

= 0.3.0 =
First public release of Editorial Workflow Manager.
