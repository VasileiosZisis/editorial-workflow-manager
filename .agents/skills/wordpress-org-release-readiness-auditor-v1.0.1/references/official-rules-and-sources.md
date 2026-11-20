# WordPress.org authoritative guidance to verify

Prepared 2026-11-17; official sources evolve. These are reference links, **not proof that checks were performed** in a given release review. When no authorized network access is available, recommend manual verification and don't claim live validation.

- Plugin header requirements: https://developer.wordpress.org/plugins/plugin-basics/header-requirements/
- Readme fields and Stable tag behavior: https://developer.wordpress.org/plugins/wordpress-org/how-your-readme-txt-works/
- SVN trunk/tags workflow: https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/
- Release confirmation process: https://developer.wordpress.org/plugins/wordpress-org/release-confirmation-emails/
- Plugin Directory Guidelines: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/
- Common plugin submission issues: https://developer.wordpress.org/plugins/wordpress-org/common-issues/
- Internationalization/text domains: https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/
- Official Plugin Check: https://wordpress.org/plugins/plugin-check/
- Readme validator: https://wordpress.org/plugins/developers/readme-validator/

## Interpretation boundaries

Versioned WordPress.org releases should reconcile main PHP `Version`, readme `Stable tag` and actual versioned SVN tag. SVN tag presence isn't proved by a local Git tag. Missing optional main headers, standalone license file, `Domain Path`, `load_plugin_textdomain()` or SVN screenshots is not automatically a confirmed failure. WordPress/PHP minimums and `Tested up to` are declarations needing runtime evidence when relevant. Official Plugin Check/readme validator complement—not replace—an evidence-based release review.
