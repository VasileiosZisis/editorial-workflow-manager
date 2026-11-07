# Local JSON template transfer

Version 1.7.0 adds Free single-template JSON export/import under **Checklist Templates → Import/Export**, with **Export JSON** row actions. Export requires edit permission for the source template. Import requires the template post type's create and publish capabilities (normally Editors and Administrators).

## Workflow

Download one template, transfer the file manually, then upload it on another site. The server validates the file and shows the resulting title, ordered items, rule settings, sanitization changes, and unavailable-provider warnings. Confirm **Create new template**, or cancel. JavaScript is not required.

Imports always create a new published template record that is **not mapped** to a post type. They never replace an existing template, copy site mappings or publication policies, enable Block, or transfer users, credentials, audit records, checked state, or readiness caches. Existing post checklist state remains unchanged. Assign the imported template separately when ready.

The source title is retained when unused; conflicts produce `Title (Imported)`, then `Title (Imported 2)`, etc. Repeated intentional imports create separate copies. Preview titles are rechecked on confirmation. Confirmation claims prevent duplicate processing and serialize competing imports' title selection. A failed metadata write removes the new record and keeps the preview available for retry.

## Version 1 format

```json
{
  "format": "editorial-workflow-manager/template",
  "schema_version": 1,
  "template": {
    "title": "My checklist",
    "item_format": "v2",
    "items": [
      {
        "id": "d452be02-2455-4c21-aac7-efac7e9dd35c",
        "label": "Review the source",
        "required": true,
        "description": "Confirm the source is reliable.",
        "url": "https://example.test/reference"
      }
    ],
    "automatic_requirements": {
      "minimum_internal_link_count": { "enabled": true, "minimum": 2 },
      "vendor/custom-rule": { "enabled": true }
    }
  }
}
```

For `item_format: "legacy"`, `items` is an ordered array of label strings. Legacy strings remain literal and keep current legacy readiness behavior; transfer does not interpret optional markers or migrate the source. V2 UUIDs and required/optional flags are preserved, including the legacy label mirror on the imported record. Older v2 sources missing helper text/reference URLs export with those two supported empty defaults without changing the source metadata.

The exporter includes all eleven built-in settings and existing namespaced custom-rule enablement. Missing built-ins in an uploaded file default to disabled. Present numeric rules require integer bounds within the existing supported range, even when disabled; enabled minimum/maximum pairs cannot contradict each other. Maximum taxonomy counts may be zero. Custom entries accept only `enabled`, with the same namespaced ID syntax as the registration API.

Unavailable providers are preserved and identified in the preview, but remain excluded from readiness under the existing rule API contract. Import does not install provider code. If rule availability changes before confirmation, the user must review an updated preview and confirm again. Public rule API/result versions remain unchanged.

## Validation and privacy

Only `.json` uploads are accepted, at most **1 MiB**, or the PHP/WordPress upload limit if lower. JSON must use UTF-8, match schema version 1, and have only permitted fields with strict object/array, boolean, integer, and supported UUID types. Duplicate item UUIDs, unsupported fields, unknown built-in rule IDs, contradictory ranges, malformed JSON, excessive nesting, and unusable sanitized text/URLs are rejected. A leading UTF-8 BOM is accepted. Named object keys use normal JSON decoder semantics; duplicate object property names resolve to their last value.

No file is added to the Media Library, executed, or fetched from an external URL. Reference URLs are treated as data and displayed as text during preview. Downloads use attachment/no-cache headers and compact UTF-8 when pretty formatting would exceed the transfer limit. A template whose final encoded file exceeds 1 MiB cannot be exported; reduce its text first.

Validated preview data stays in a transient bound to the current user and site for **15 minutes**. Nonces and capabilities protect each action. Opaque tokens cannot access another user's/site's staging data. Cancellation and success remove staging data and claims; an expiry event also cleans abandoned claims, including with an external object cache. Uninstall removes pending transfer events and staging/claim data on each site. No telemetry or remote processing is added.

## Verification

On a local development site with the plugin active, run:

```text
wp eval-file /absolute/path/to/tests/template-portability-integration.php allow-local-fixtures
```

The reversible runner tests validation, legacy/current/older-v2 round trips, Unicode size boundaries, inactive providers, scope/permissions, expiry, interleaved claims, conflict titles, metadata-failure cleanup, and deliberate mapping/readiness. It removes test-owned posts and restores settings in a `finally` block. Run only on `.local`, `.test`, or localhost development sites. Existing internal-link and publication regressions remain separate tests.

For real HTTP upload, nonce, download-header, preview, cancellation, and concurrent-confirmation checks, use the following sequence. Preparation prints a temporary state-file path containing a short-lived test login cookie; keep it private and always run cleanup, including after a failed check. No new user account is created.

```text
wp eval-file /absolute/path/to/tests/template-portability-http-fixture.php prepare
node tests/template-portability-http.mjs /printed/temporary/state-file.json
wp eval-file /absolute/path/to/tests/template-portability-http-fixture.php cleanup /printed/temporary/state-file.json
```

Cleanup removes the test-owned templates/previews and destroys only the temporary login session it created. The HTTP runner requires Node.js 20 or newer and sends cookies only to the local site's original origin.
