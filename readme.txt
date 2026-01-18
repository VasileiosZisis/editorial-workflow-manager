=== Editorial Workflow Manager ===
Contributors: vzisis
Tags: editorial, workflow, checklist, content, publishing
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.3.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial checklists inside the WordPress block editor, so teams can follow a consistent review process before publishing.

== Description ==

Editorial Workflow Manager adds **editorial checklists** to the WordPress block editor (Gutenberg), so you and your team can follow a consistent review process before publishing posts and pages.

This first (free) version is designed with agencies and content teams in mind:

* Create reusable **checklist templates** for different types of content.
* Map templates to post types (e.g. Posts, Pages).
* Tick items as you work using an **Editorial Checklist** sidebar in the editor.
* See a soft status warning in the **Status & visibility** panel when the checklist is incomplete.

No publish blocking or approvals in this free version. A separate Pro add-on is planned with those features.

### Key features

* **Checklist templates** – manage reusable checklists as a custom post type (“Checklist Templates”).
* **Per-post checklists** – each post or page stores which items are completed.
* **Gutenberg sidebar** – “Editorial Checklist” panel integrated into the block editor.
* **Template per post type** – map each post type to a default checklist template.
* **Default templates included**:
  * Blog Post SOP
  * Landing Page QA
  * Announcement / News Post
* **Soft status notice and pre-publish warning** –
  * In “Status & visibility”:
    * `Checklist: X / Y items done` while items are incomplete.
    * `Checklist complete.` once everything is ticked.
  * In the pre-publish panel:
    * Non-blocking warning if the checklist is incomplete when you click Publish.
* **Tiny activity hint** – the checklist sidebar shows “Last updated by X on [date/time]”, based on the last saved edit.
* **Translation-ready** – text domain `editorial-workflow-manager` and `/languages` directory.

This plugin does not add any front-end output. Everything is editor/admin only.

== Installation ==

1. Upload the `editorial-workflow-manager` folder to the `/wp-content/plugins/` directory, or install it through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. On activation, the plugin will create a few default checklist templates for you.

### Getting started

1. Go to **Checklist Templates → All Templates**  
   * Review or edit the defaults:
     * Blog Post SOP
     * Landing Page QA
     * Announcement / News Post
   * Or create your own checklist templates (one item per line in the textarea).
2. Go to **Settings → Editorial Workflow**  
   * Map each post type (e.g. Posts, Pages) to a checklist template.  
   * By default, “Blog Post SOP” is mapped to Posts.
3. Edit a post in the block editor  
   * Open **More tools & options (⋮) → Plugins → Editorial Checklist**.  
   * Tick items as you complete them – the state is saved with the post.
4. In the **Status & visibility** panel  
   * See `Checklist: X / Y items done` when items are incomplete.  
   * See `Checklist complete.` when all items are ticked.
   * The checklist sidebar shows “Last updated by X on date/time”, based on the last saved edit.

== Frequently Asked Questions ==

= Does this plugin block publishing if the checklist is incomplete? =

Not in the free version. The free version shows a **soft warning** in the editor: a status line and a **non-blocking pre-publish notice** if the checklist is incomplete. You can still publish. Hard publish blocking and approval workflows are planned for the Pro version.

= Does this plugin change anything on the front end of my site? =

No. Editorial Workflow Manager only affects the WordPress admin/editor. It does not output anything on the front end.

= Which editor does this work with? =

The plugin is built for the **block editor** (Gutenberg). Classic Editor is not supported.

= Can I create different checklists for different content types? =

Yes. You can create multiple checklist templates and map them to different post types (e.g. one for Posts, one for Pages, etc.) via **Settings → Editorial Workflow**.

= Can I translate the plugin? =

Yes. The plugin is translation-ready. The text domain is `editorial-workflow-manager` and there is a `/languages` folder where `.po` / `.mo` files can be placed.

== Screenshots ==

1. Checklist templates in the admin area.
2. Template edit screen.
3. Mapping post types to checklist templates in **Settings → Editorial Workflow**.
4. The “Editorial Checklist” sidebar in the block editor.

== Changelog ==

= 0.3.2 =
* Added uninstall.php cleanup to remove plugin data when the plugin is deleted.

= 0.3.1 =
* Added non-blocking pre-publish checklist warning when checklists are incomplete.
* Added tiny activity hint in the checklist sidebar (“Last updated by X on date/time”).

= 0.3.0 =
* First public release.
* Checklist templates custom post type.
* Per-post checklist state stored via post meta.
* Settings page to map templates to post types.
* Default templates created on activation (Blog Post SOP, Landing Page QA, Announcement / News Post).
* Gutenberg sidebar: Editorial Checklist panel with persistent checkboxes.
* Soft checklist status notice in the post status panel.
* Translation-ready setup.

== Upgrade Notice ==

= 0.3.2 =
Adds uninstall.php cleanup to remove plugin data when the plugin is deleted.

= 0.3.0 =
First public release of Editorial Workflow Manager. Includes checklist templates, per-post checklists, a Gutenberg sidebar, default templates on activation, and soft status warnings in the editor.
