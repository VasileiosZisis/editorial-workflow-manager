=== Editorial Workflow Manager ===
Contributors: vzisis
Tags: editorial, checklist, workflow, publishing, gutenberg
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial checklist and pre-publish workflow for the WordPress editor. Create reusable checklists and get clear readiness feedback before publishing.

== Description ==

**The Gutenberg editorial checklist for teams that want consistent publishing quality without a heavy workflow suite.**

**Editorial Workflow Manager** helps content teams, editors, agencies, and multi-author WordPress sites follow a consistent pre-publish process directly inside the **WordPress block editor (Gutenberg)**.

Create reusable editorial checklist templates, assign different checklists to different post types, and give authors a clear view of what is complete and what still needs attention before publishing.

No complex workflow builder. No front-end output. No hard publish blocking.

Just a focused editorial checklist workflow that helps your team publish with more consistency and fewer missed steps.

=== Make your publishing process repeatable ===

Turn your editorial standards into reusable checklists that appear directly where your team writes and edits content.

* Create reusable checklist templates.
* Mark checklist items as **Required** or **Optional**.
* Add helper text and optional reference links to individual items.
* Assign different checklist templates to different post types.
* Track checklist progress separately for every post or page.
* Duplicate existing templates to create new workflows faster.

Required items determine whether a post is shown as **Ready** or **Incomplete**, while optional items can provide additional guidance without affecting readiness.

=== See what is ready to publish ===

Editorial Workflow Manager gives authors and editors clear readiness feedback across the WordPress admin.

* **Editorial Checklist sidebar** inside the block editor.
* **Ready / Incomplete status** with required-item progress.
* **Post Status panel** summary while editing.
* **Pre-publish warning** when required items are still incomplete.
* **Readiness column** in WordPress post lists for mapped post types.
* **Readiness filters** for Ready, Incomplete, and Not calculated content.
* **Missing required-item details** directly in post lists.
* **Bulk and all-post readiness recalculation** for mapped post types.
* **Editorial Readiness dashboard summary** for managers.

The pre-publish warning is intentionally **non-blocking**. Your team stays informed without the plugin taking control of the WordPress publishing process.

=== Get started quickly ===

New installations include a guided setup experience designed to get teams working quickly.

* **Quickstart wizard** for choosing post types and assigning checklist templates.
* **One-time editor tour** introducing the checklist sidebar and readiness system.
* **Curated starter templates** you can use as-is or customize:

  * Blog SEO
  * News Fact-Check
  * Accessibility Review
  * Client Approval

You can edit, duplicate, and adapt these templates to match your own publishing standards.

=== Built for real editorial workflows ===

Use Editorial Workflow Manager for:

* **Blogs and content teams** — SEO review steps, featured images, categories, links, fact-checking, and publishing standards.
* **News and editorial sites** — source confirmation, fact-checking, legal review steps, and editor sign-off requirements.
* **Agencies** — client review steps, accessibility checks, brand requirements, and delivery standards.
* **Multi-author sites** — give every contributor the same clear publishing process.
* **Custom post types** — assign the appropriate checklist to each supported content type.

=== Key features ===

* **Gutenberg-native workflow** — the checklist lives directly inside the WordPress block editor.
* **Reusable checklist templates** — create a repeatable process once and use it across content.
* **Required and Optional items** — distinguish publishing requirements from helpful guidance.
* **Helper text and reference links** — give authors context without leaving the checklist.
* **Checklist template duplication** — clone and customize existing workflows.
* **Per-post checklist progress** — each post or page maintains its own completion state.
* **Readiness tracking** — see Ready or Incomplete status throughout the editor and post list.
* **Non-blocking pre-publish guidance** — warn authors about missing required items without preventing publication.
* **Post-type mapping** — use different checklists for different types of content.
* **Starter templates** — begin with practical workflows for blogging, fact-checking, accessibility, and client review.
* **Quickstart onboarding** — configure the plugin and open the editor with less setup friction.
* **Accessible workflows** — keyboard-friendly controls, visible focus states, contextual labels, and live status announcements.
* **Backward-compatible upgrades** — existing checklist data and legacy templates remain supported.

=== Lightweight by design ===

Editorial Workflow Manager focuses on one job: helping WordPress teams follow a consistent editorial checklist before publishing.

It does **not** add content to your site's front end, and it does not try to replace WordPress with a complex project-management or enterprise workflow system.

Use it when you want a clear, practical publishing process directly inside Gutenberg.

== Getting started ==

1. Activate the plugin and complete the **Quickstart** wizard.
2. Choose the post types where editorial checklists should appear.
3. Confirm the starter template mappings, then open the editor.
4. Follow the one-time sidebar tour in the **block editor**.
5. Complete checklist items and watch the readiness/progress summary update.
6. Adjust mappings later in **Settings → Editorial Workflow** or edit templates in **Checklist Templates**.

== Installation ==

1. Upload the `editorial-workflow-manager` folder to `/wp-content/plugins/`, or install via **Plugins → Add New**.
2. Activate the plugin in wp-admin.
3. On activation, default checklist templates are created.
4. On fresh installs, a Quickstart wizard opens to help you assign starter templates and launch the editor tour.

== Frequently Asked Questions ==

= What does the Quickstart wizard do? =
On fresh installs, the plugin can guide an administrator through choosing post types, assigning starter templates, and opening a post editor screen with the checklist sidebar highlighted.

= Can I dismiss the quickstart or editor tour? =
Yes. Dismissal is stored per user, so one admin can skip onboarding without affecting another admin's setup flow.

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

= Can I duplicate checklist templates? =
Yes. Use the **Duplicate** row action on the Checklist Templates screen to create an editable copy.

== Screenshots ==

1. Editorial Checklist sidebar in the block editor (Gutenberg) with required progress.
2. Checklist template editor with required/optional items and reorder controls.
3. Settings screen for assigning checklist templates to post types.
4. Pre-publish checklist warning when required items are missing.
5. wp-admin post list with readiness filters, expanded missing-item details, and recalculation controls.
6. Editorial Readiness dashboard summary with Ready, Incomplete, and Not calculated counts.

== Changelog ==

= 1.0.0 =
* Added exact Ready, Incomplete, and Not calculated filters to mapped post lists.
* Added expandable missing required-item details to the Readiness column.
* Added selected-post and batched all-post readiness recalculation tools.
* Added an Editorial Readiness dashboard summary with links to filtered post lists.

= 0.12.0 =
* Added Feedback links on the Installed Plugins row and Editorial Workflow settings page.
* Added a per-user WordPress.org review prompt after five unique posts reach Ready.
* Added a 30-day snooze and permanent dismissal without telemetry or automatic external requests.

= 0.11.0 =
* Limited checklist editor assets to valid block-editor post screens and supported post types.
* Consolidated duplicate Gutenberg editor subscriptions into one shared checklist state.
* Memoized checked-item normalization and membership lookups without changing checklist behavior or storage.

= 0.10.0 =
* Improved keyboard and screen-reader support across the checklist sidebar, pre-publish warning, Quickstart, settings, and editor tour.
* Added contextual template row actions, live reorder/add/remove announcements, deterministic focus handling, and associated validation errors.
* Added visible focus styling for plugin-owned controls and fixed the editor-tour spotlight state.

= 0.9.0 =
* Added a Duplicate action for Checklist Templates.
* Added curated starter templates for Blog SEO, News Fact-Check, Accessibility Review, and Client Approval.
* Added upgrade handling so missing starter templates are created without overwriting existing templates.

= 0.8.0 =
* Added optional helper text and reference URLs to checklist template items.
* Added collapsed checklist item details in the block editor sidebar.

= 0.7.0 =
* Added a wp-admin Readiness column for mapped post types so teams can scan per-post checklist status from the post list.
* Added shared server-side readiness calculation and lazy cache metadata for list-table rendering.
* Added cache invalidation when checklist templates or post type mappings change.

= 0.6.0 =
* Added a fresh-install Quickstart wizard for selecting post types and assigning starter templates.
* Added a lightweight one-time editor tour that auto-opens and highlights the Editorial Checklist sidebar.
* Stored quickstart and editor-tour dismissal state per user.

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

= 1.0.0 =
Adds manager-focused readiness filters, missing-item details, recalculation tools, and a dashboard summary without changing checklist data.

= 0.12.0 =
Adds passive WordPress.org feedback links and a dismissible, per-user review prompt after five checklist completions.

= 0.11.0 =
Reduces unnecessary block-editor asset loading and repeated checklist calculations without changing existing workflows or data.

= 0.10.0 =
Improves keyboard navigation, screen-reader context, focus visibility, dynamic announcements, and template-editor validation without changing checklist data.

= 0.9.0 =
Adds template duplication and creates missing curated starter templates without changing existing checklist mappings.

= 0.8.0 =
Checklist template items can now include optional helper text and a reference URL. Existing templates continue to work unchanged.

= 0.7.0 =
Mapped post types now show a Readiness column in wp-admin post lists. Readiness cache values are generated lazily and invalidated when templates or mappings change.

= 0.6.0 =
Fresh installs now include a Quickstart wizard and one-time editor sidebar tour to help administrators configure and discover the checklist workflow faster.

= 0.5.0 =
Checklist Template permissions are now limited to roles with page-management capabilities. By default, this means Editors and Administrators can manage templates, while Authors and Contributors may lose access after updating.

= 0.4.0 =
Introduces Required vs Optional checklist items, clearer readiness indicators, and an improved template editor. Legacy templates continue to work; saving a legacy template upgrades it to UUID-based v2 items for more stable tracking.

= 0.3.2 =
Adds uninstall cleanup to remove plugin data when the plugin is deleted.

= 0.3.0 =
First public release of Editorial Workflow Manager.
