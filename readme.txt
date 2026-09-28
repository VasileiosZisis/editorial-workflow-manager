=== Editorial Workflow Manager ===
Contributors: vzisis
Tags: checklist, editorial workflow, gutenberg, publishing, content workflow
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.2.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Editorial checklist and pre-publish workflow for Gutenberg. Create reusable checklists, automatic checks, and clear publishing readiness.

== Description ==

Editorial Workflow Manager is a WordPress editorial checklist and pre-publish workflow plugin built directly for the Gutenberg block editor.

Create reusable editorial checklist templates, automatically check common publishing requirements, assign different checklists to different post types, and give authors and editors a clear view of what still needs attention before publishing.

It is designed for content teams, editors, agencies, news sites, and multi-author WordPress websites that want a consistent publishing process without a complex workflow or project-management system.

Editorial Workflow Manager does not add anything to your site's front end and does not hard-block publishing.

It gives your team a focused WordPress pre-publish checklist with clear readiness feedback directly inside the editor.

= Create a repeatable editorial checklist =

Turn your publishing standards into reusable WordPress editorial checklists that appear where authors already write and edit content.

* Create reusable checklist templates.
* Mark items as Required or Optional.
* Add helper text to checklist items.
* Add optional reference links for additional guidance.
* Reorder checklist items.
* Duplicate existing templates to build new workflows faster.
* Assign different checklist templates to different post types.
* Track checklist completion separately for each post.
* Use Required items to determine publishing readiness.
* Keep Optional items as guidance without affecting readiness.

This makes it easier for every author and editor to follow the same pre-publish process instead of relying on memory, documents, or separate task lists.

= Automate common pre-publish checks =

Editorial Workflow Manager can combine manual checklist items with automatic content requirements.

Built-in automatic checks include:

* Featured image present.
* Excerpt present.
* Configurable minimum word count.
* Category or tag present.
* Image alternative-text coverage for featured and content images.

Automatic requirements update from the current post content and participate in the same Ready / Incomplete status as manual required items.

Authors can immediately see which requirements are complete and which still need attention.

Existing checklist templates do not enable automatic requirements unless you choose to add them.

If a mapped post type does not support a particular requirement, that rule is ignored instead of being treated as failed.

= See what is ready to publish =

Editorial Workflow Manager provides publishing-readiness feedback throughout the WordPress admin.

* Editorial Checklist sidebar inside Gutenberg.
* Ready / Incomplete status based on required items.
* Required-item progress while editing.
* Post Status panel summary.
* Non-blocking pre-publish warning.
* Readiness column in post lists for mapped post types.
* Ready, Incomplete, and Not calculated post-list filters.
* Expandable details showing missing required items.
* Recalculate readiness for selected posts.
* Batched recalculation for all mapped posts.
* Editorial Readiness dashboard summary for managers.

The pre-publish warning is intentionally non-blocking.

Authors remain in control of WordPress publishing while still receiving a clear warning when required editorial steps are incomplete.

= A Gutenberg checklist where your team already works =

The editorial checklist lives directly inside the WordPress block editor.

Authors do not need to switch between WordPress and a separate project-management tool just to verify publishing requirements.

Use the checklist while writing, reviewing, and preparing content for publication.

Editorial Workflow Manager is designed for Gutenberg / the WordPress block editor and does not provide its checklist interface in the Classic Editor.

= Built for content teams and editorial workflows =

Editorial Workflow Manager can be used for many WordPress publishing processes.

**Blogs and content teams**

Create a repeatable pre-publish checklist for SEO review, featured images, categories, links, formatting, fact-checking, and other publishing standards.

**News and editorial sites**

Use editorial checklists for source confirmation, fact-checking, accessibility review, legal review steps, and editor sign-off requirements.

**Multi-author WordPress sites**

Give contributors, authors, and editors a consistent process so important publishing steps are less likely to be missed.

**Agencies**

Create checklists for brand requirements, accessibility checks, content review, client review steps, and delivery standards.

**Different content types**

Assign different checklist templates to different supported post types so each type of content can have its own publishing requirements.

= Start with ready-made checklist templates =

New installations include starter templates that can be used as-is or customized:

* Blog SEO
* News Fact-Check
* Accessibility Review
* Client Approval

Edit, duplicate, reorder, and adapt the templates to match your own editorial process.

= Quick setup for new sites =

A Quickstart wizard helps administrators configure the plugin after installation.

Use it to:

1. Choose the post types where editorial checklists should appear.
2. Assign starter checklist templates.
3. Open the block editor.
4. Follow the one-time editor tour.
5. Start completing checklist requirements.

Quickstart and editor-tour dismissal preferences are stored per user, so one administrator can dismiss onboarding without affecting another user's experience.

= Extend automatic checks with custom rules =

Developers can register site-specific automatic requirements using the plugin's PHP registration API and optional JavaScript evaluator contract.

Custom rules can participate in the same system as the built-in requirements, including:

* Per-template enablement.
* Authoritative server-side evaluation.
* Optional live Gutenberg feedback.
* Readiness aggregation.
* Saved-result refresh.
* Readiness cache invalidation.

Developer documentation is included in `docs/rule-registration-api.md`, together with a standalone sample extension.

= Key features =

* WordPress editorial checklist inside Gutenberg.
* Reusable pre-publish checklist templates.
* Required and Optional checklist items.
* Automatic publishing requirements.
* Featured-image checking.
* Excerpt checking.
* Minimum word-count checking.
* Category or tag checking.
* Image alternative-text checking.
* Helper text and reference links.
* Template duplication.
* Different checklists for different post types.
* Per-post checklist progress.
* Ready / Incomplete publishing status.
* Non-blocking pre-publish warnings.
* Readiness status in WordPress post lists.
* Missing-requirement details.
* Readiness filters and recalculation tools.
* Editorial Readiness dashboard.
* Starter editorial checklist templates.
* Quickstart setup wizard.
* Gutenberg editor tour.
* Developer API for custom automatic requirements.
* Accessible keyboard and screen-reader workflows.
* Backward-compatible handling of legacy checklist data.

= Lightweight by design =

Editorial Workflow Manager focuses on one job: helping WordPress teams follow a consistent editorial checklist before publishing.

It does not:

* Add content to your site's front end.
* Replace WordPress publishing with a separate workflow system.
* Require a complex workflow builder.
* Hard-block authors from publishing.

Use it when you want a practical Gutenberg pre-publish checklist and clear editorial readiness feedback without adding an enterprise workflow suite.

= Getting started =

1. Install and activate Editorial Workflow Manager.
2. Complete the Quickstart wizard.
3. Choose the post types where editorial checklists should appear.
4. Assign or customize checklist templates.
5. Open a post in the Gutenberg block editor.
6. Complete manual checklist items and automatic requirements.
7. Watch the Ready / Incomplete status update as requirements are completed.
8. Review site-wide readiness from post lists and the Editorial Readiness dashboard.

You can change post-type mappings later under Settings > Editorial Workflow and manage templates from Checklist Templates.

== Screenshots ==

1. Editorial Checklist sidebar in the Gutenberg block editor showing required-item progress and publishing readiness.
2. Checklist Template editor for creating, reordering, and configuring Required and Optional editorial checklist items.
3. Editorial Workflow settings for assigning different checklist templates to different WordPress post types.
4. Non-blocking pre-publish checklist warning showing requirements that still need attention before publishing.
5. WordPress post list with Ready / Incomplete filters, missing-requirement details, and readiness recalculation tools.
6. Editorial Readiness dashboard showing Ready, Incomplete, and Not calculated content across mapped post types.

== Installation ==

1. Install Editorial Workflow Manager from Plugins > Add New, or upload the `editorial-workflow-manager` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Default checklist templates are created automatically.
4. On a fresh installation, follow the Quickstart wizard to choose post types and assign starter templates.
5. Open a post in the Gutenberg block editor and follow the one-time Editorial Checklist tour.
6. Customize templates from Checklist Templates and mappings from Settings > Editorial Workflow.

== Frequently Asked Questions ==

= What is an editorial checklist in WordPress? =

An editorial checklist is a repeatable list of publishing requirements that authors and editors can follow before content goes live.

Editorial Workflow Manager places that checklist directly inside the Gutenberg block editor and tracks whether required items are complete.

= Can I create a pre-publish checklist in Gutenberg? =

Yes.

Editorial Workflow Manager adds an editorial checklist directly to the WordPress block editor. You can create reusable templates containing Required and Optional items and assign them to supported post types.

= Can the plugin automatically check publishing requirements? =

Yes.

Checklist templates can include automatic requirements for featured images, excerpts, minimum word count, category or tag presence, and image alternative-text coverage.

These checks update from the post content and contribute to the same Ready / Incomplete status as required manual checklist items.

= Can I require a featured image before publishing? =

You can add the Featured Image automatic requirement to a checklist template.

When enabled and applicable, the requirement remains incomplete until the post has a featured image.

The plugin reports this through its readiness system and pre-publish warning, but it does not hard-block WordPress publishing.

= Can I check image alt text before publishing? =

Yes.

The image alternative-text requirement checks featured and content images.

Images with empty alternative text or unavailable attachment records are treated as incomplete, and the checklist identifies the image position that needs attention.

If your workflow intentionally uses decorative images with empty alternative text, leave this automatic requirement disabled for that template.

= Does Editorial Workflow Manager block publishing? =

No.

The pre-publish warning is intentionally non-blocking. Authors receive a clear warning when required items are incomplete but WordPress remains in control of the publishing action.

= Can I use different editorial checklists for different post types? =

Yes.

You can assign different checklist templates to different supported post types under Settings > Editorial Workflow.

For example, blog posts can use an SEO-oriented checklist while another content type uses a different editorial process.

= Does it work with custom post types? =

It can be assigned to supported post types through the Editorial Workflow settings.

Automatic requirements that a particular mapped post type cannot support are ignored rather than counted as failures.

= Does it work with Classic Editor? =

No.

The checklist interface is built specifically for Gutenberg / the WordPress block editor.

= Do Optional checklist items affect publishing readiness? =

No.

Only Required items determine whether a post is shown as Ready or Incomplete.

Optional items can provide additional editorial guidance without preventing a post from reaching Ready status.

= How do automatic requirements affect readiness? =

Each enabled and applicable automatic requirement counts as a required item.

Automatic requirements update from the current post content and settings, cannot be manually checked, and appear in readiness summaries, post-list details, filters, recalculation tools, and dashboard totals.

Existing templates do not automatically enable these requirements.

= Can developers add custom automatic requirements? =

Yes.

Version 1.2.0 includes a versioned PHP registration API and an optional JavaScript evaluator contract.

Registered rules can be enabled on individual checklist templates and participate in the same readiness system as built-in automatic requirements.

See `docs/rule-registration-api.md` and the bundled standalone sample extension.

= What does the Quickstart wizard do? =

On fresh installations, Quickstart helps an administrator choose post types, assign starter templates, and open the block editor with the Editorial Checklist sidebar highlighted.

= Can I dismiss Quickstart or the editor tour? =

Yes.

Dismissal is stored per user, so one administrator can skip onboarding without changing another user's onboarding state.

= Can I duplicate an editorial checklist template? =

Yes.

Use the Duplicate row action on the Checklist Templates screen to create an editable copy of an existing template.

= What happens to existing or older checklist data? =

Legacy templates and label-based checked state remain supported.

Templates use an upgraded v2 format with UUID-based item IDs for more stable matching. When a legacy template is edited and saved using the newer editor, it is upgraded automatically.

A compatibility metadata mirror is maintained for legacy support.

== Changelog ==

= 1.2.0 =

* Added a versioned PHP and JavaScript API for code-defined automatic requirements.
* Added per-template enablement for registered automatic rules.
* Added authoritative server-side evaluation for custom rules.
* Added optional live Gutenberg evaluation.
* Added saved-result refresh for registered rules.
* Added dependency-aware readiness invalidation.
* Added registry fingerprinting for extension activation, deactivation, and rule-version changes.
* Added developer documentation and a standalone sample rule extension.

= 1.1.0 =

* Added five configurable automatic requirements for featured images, excerpts, minimum word count, category/tag presence, and image alternative text.
* Added live automatic-result status to the Gutenberg checklist sidebar.
* Integrated automatic results with readiness caches, post-list details, filters, recalculation tools, and dashboard totals.
* Kept existing templates unchanged until automatic requirements are explicitly enabled.
* Excluded unsupported automatic rules from readiness evaluation.

= 1.0.0 =

* Added Ready, Incomplete, and Not calculated filters to mapped post lists.
* Added expandable missing-required-item details to the Readiness column.
* Added selected-post and batched all-post readiness recalculation tools.
* Added an Editorial Readiness dashboard summary with links to filtered post lists.