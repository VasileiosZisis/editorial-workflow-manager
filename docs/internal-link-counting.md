# Minimum internal links

Added in 1.6.0 as the Free built-in `minimum_internal_link_count` rule. Enable it on a checklist template and set a whole-number minimum from 1 to 100,000. It defaults to disabled, with a suggested minimum of 1. Invalid enabled thresholds preserve the previous rule configuration while unrelated valid edits save. Disabled rules retain their previous threshold when their input is missing or invalid.

## What counts

The counter reads literal `<a href>` markup from stored post content, including nested blocks and Custom HTML. It counts unique destinations within the current site's `home_url()` host, normalized port, and base path. A site at `https://example.test/news/` includes `/news/story` and excludes `/other/story`, `/newsletter/story`, and other hosts or subdomains. Each multisite site uses its own home URL.

HTTP(S), protocol-relative, root-relative, and path-relative URLs qualify. Relative paths resolve against the prospective published permalink, falling back to the home URL when unavailable. Gutenberg uses its current permalink when available; PHP derives the prospective permalink from a cloned saved post, including its unique slug, without changing database status. Draft and scheduled checks therefore use the same URL context as publication.

Files and images qualify, including anchors wrapping images. The counter never checks existence, follows redirects, loads resources, or makes external requests. A broken link may therefore satisfy this rule.

## Destination identity

Repeated destinations count once. Identity ignores fragments and HTTP/HTTPS differences, normalizes hostname case and protocol-default ports, resolves dot segments, decodes percent-encoded unreserved ASCII characters, uppercases remaining percent escapes, encodes Unicode path/query characters as UTF-8, and ignores trailing slashes. Path case, repeated inner slashes, and query strings remain significant; query parameters are not reordered. Thus `/news/story#one` and `/news/story/#two` count once, while `/news/story?view=one` and `/news/story?view=two` count twice.

Empty, fragment-only, query-only, malformed, credential-bearing, and non-HTTP(S) references do not count. Destinations matching the normalized current post URL do not count. Explicit non-default ports must match. Hosts use ASCII DNS names (including punycode) or bracketed hexadecimal IPv6 notation; Unicode host names and IPv4-mapped IPv6 notation are outside the accepted grammar.

## Literal-content boundary

Comments, shortcode attributes, and markup inside `script`, `style`, `textarea`, `title`, and `template` elements are ignored. Literal HTML between shortcode tags remains eligible. Dynamic blocks, synced-pattern references, and shortcode output are not rendered or expanded. Escaped markup is text, not an anchor.

The PHP and JavaScript tokenizers use the same quote-aware grammar and the first `href` attribute. Semicolon-terminated numeric references and the shared canonical HTML5 named-reference vocabulary (plus `AMP`, `LT`, `GT`, and `QUOT` aliases) are decoded in attribute values. Other entity spellings remain literal. Malformed individual links are ignored; evaluation errors fail safely.

## Readiness and enforcement

Each enabled, applicable rule contributes one required item. Unsupported post types without editor content are excluded. Results appear in the editor, missing-requirement details, post-list readiness and filters, recalculation, and dashboard totals. Content/template changes and URL-related options (including Reading settings) invalidate readiness. Slug insertions, changes, and deletions also invalidate rule caches because they can affect another draft's prospective unique slug. Ancestor page slug/parent changes, category permalink changes, and author nicename changes invalidate affected rule caches. Scheduled posts keep their already-reserved slug. JavaScript is provisional; uncached PHP validation governs Block publication and scheduled-post checks. Advisory remains the default.

## Regression checks

Run `php tests/internal-links.php` and `node tests/internal-links.mjs` from the repository. The latter exercises the production JavaScript helper in a runtime with no DOM or network APIs and compares it with PHP using shared fixtures. Set `EDIWORMAN_PHP_BINARY` when PHP is not on PATH.

On a local `.local` or `.test` development site with the plugin active, run `wp eval-file /absolute/path/to/tests/wordpress-integration.php allow-local-fixtures`. It creates test-owned posts, temporarily changes mappings/permalink/Reading settings, and restores settings and deletes its posts in a `finally` block. Never use this runner on production.
