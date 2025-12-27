# Editorial Workflow Manager

Editorial Workflow Manager adds **editorial checklists** to the WordPress block editor, so teams can follow a consistent review process before publishing.

This is the free (Lite) version focused on agencies managing client blogs and marketing sites.

---

## Features (Free / Lite)

- ✅ **Checklist templates** – create reusable review checklists as a custom post type (“Checklist Templates”).
- ✅ **Per-post checklists** – each post or page gets its own checklist state (which items are done).
- ✅ **Gutenberg sidebar** – “Editorial Checklist” panel in the block editor for ticking items as you work.
- ✅ **Post type mapping** – choose which checklist template applies to each post type (e.g. Posts, Pages).
- ✅ **Default templates included**:
  - Blog Post SOP
  - Landing Page QA
  - Announcement / News Post
- ✅ **Soft status warning** – in “Status & visibility” you see:
  - `Checklist: X / Y items done` (in red when incomplete)
  - `Checklist complete.` when everything is ticked.
- ✅ **Translation-ready** – text domain `editorial-workflow-manager` and `/languages` folder.

No publish blocking or approvals yet – those are planned for the Pro version.

---

## Requirements

- WordPress 6.0+
- PHP 7.4+
- Block editor (Gutenberg) enabled on the post types you want to use.

---

## Installation

1. Upload the `editorial-workflow-manager` folder to your `/wp-content/plugins/` directory, or install it via your development workflow.
2. Activate the plugin via **Plugins → Installed Plugins**.
3. On activation, the plugin will create a few default checklist templates for you.

---

## Getting started

1. Go to **Checklist Templates → All Templates**

   - Review or edit the default templates:
     - Blog Post SOP
     - Landing Page QA
     - Announcement / News Post
   - Or create your own checklist templates (one item per line).

2. Go to **Settings → Editorial Workflow**

   - Map each post type (e.g. Posts, Pages) to a checklist template.
   - By default, “Blog Post SOP” is mapped to Posts.

3. Edit a post in the block editor

   - Open the **⋮ (More tools & options) → Plugins → Editorial Checklist** menu.
   - The **Editorial Checklist** sidebar appears with the checklist for that post type.
   - Tick items as you complete them – the state is saved with the post.

4. Check the status
   - In the **Status & visibility** panel you’ll see:
     - `Checklist: X / Y items done` while items are still incomplete.
     - `Checklist complete.` when everything is ticked.

---

## Roadmap (planned)

- **Pro version**
  - Hard publish gate (block publish/schedule until required items are done).
  - One-step editor approval (with name, timestamp, and note).
- **Future**
  - Multi-step approvals (Editor → Client → Legal).
  - More advanced rules and reports for agencies and regulated businesses.

---

## Development

This plugin is built with:

- A custom post type for checklist templates (`ewm_template`).
- Post meta for checklist state (`_ewm_checked_items`).
- A custom Gutenberg sidebar using the `wp.*` editor packages.

Pull requests and feedback are welcome.
