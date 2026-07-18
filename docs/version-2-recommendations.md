# Editorial Workflow Manager: Free-Version Recommendations

## Improvements that genuinely fit the free version

### 1. Better manager visibility

- Filter post-list tables by **Ready** or **Incomplete**.
- Show the specific incomplete required items without opening the editor.
- Add bulk readiness recalculation.
- Optionally add a dismissible dashboard readiness summary.

### 2. Automated test coverage

- Add PHPUnit tests for readiness calculations, migrations, permissions, and uninstall cleanup.
- Add end-to-end tests for Quickstart, checklist completion, warnings, and readiness updates.
- Add accessibility tests for keyboard navigation, focus, live regions, and contrast.
- Test compatibility across supported WordPress and PHP versions.
- Add PHPStan or Psalm alongside the existing PHPCS checks.

This is a particularly strong recommendation: the repository currently has coding-standard tooling but no actual automated test suite.

### 3. Documentation and trust-building

- Publish short GIFs or videos demonstrating the workflow.
- Improve support FAQs and migration documentation.
- Add clear integration and compatibility documentation.
- Publish comparison pages explaining how the plugin differs from PublishPress Checklists and Oasis Workflow.
- Continue deliberate, non-intrusive WordPress.org review generation.

### 4. Sharper positioning and listing copy

Position Free specifically as:

> The Gutenberg editorial checklist for teams that want consistent publishing quality without a bloated workflow suite.

The report also recommends emphasizing:

- Gutenberg-native operation.
- Reusable required and optional checklists.
- Quick setup and low cognitive load.
- Readiness guidance without forced publishing controls.
- Editorial quality assurance rather than generic task management.

### 5. Preserve clean upgrade compatibility

- Keep templates, mappings, and checklist state compatible with any future add-on.
- Use safe, additive migrations.
- Avoid replacing the free plugin with an incompatible Pro fork.
- Do not place disabled or license-gated Pro functionality inside the WordPress.org plugin.

## Recommended changes that the report ultimately assigns to Pro

Although these appear under **Now** in the roadmap, the monetization section later places them in Pro rather than Free.

### 1. Automated validation rules

- Featured image.
- Excerpt.
- Minimum and maximum word count.
- H1 count.
- Category and tag counts.
- Image alt-text presence.
- Internal-link count.
- Required date or author fields.

### 2. Enforcement modes

- Advisory.
- Warning.
- Restrict publishing by role or post type.

### 3. First-party integrations

- Yoast SEO.
- Rank Math.
- All in One SEO.
- Advanced Custom Fields.

### 4. Template portability

- JSON import and export.
- Copy templates between sites.

### 5. Lightweight approvals

- Assignee.
- Ready for review.
- Approved.
- Changes requested.
- Notifications.

### 6. Broader compatibility

- Co-Authors Plus or PublishPress Authors.
- WPML or Polylang.
- Multisite defaults and template synchronization.

## Explicitly later or premium-pack recommendations

These are not proposed as free-version improvements:

- Calendar, queue, Kanban, and workload views.
- External or client reviewers.
- White-label functionality.
- Multi-step approvals.
- Audit trails, retention policies, and evidence exports.
- Compliance controls and restricted sign-off.

## Conclusion

The report supports keeping Free functionally complete in its current scope. The most defensible next free release would concentrate on **manager filters and visibility, automated tests, documentation, and positioning**, while reserving rules, enforcement, integrations, and approvals for a separate Pro add-on.
