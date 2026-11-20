# Artifact structure and metadata checklist

## Establish actual candidate identity

Label source checkout vs built ZIP vs supplied SVN `trunk`/`tags/<version>`. Do not claim a checkout tree equals the distributable unless file lists/hashes support it. Record intended plugin slug and target version from user/release metadata, not guesses. Archive must not contain unsafe absolute/traversal members; one installable plugin root is normally expected for a ZIP. Inspect main plugin header and readme *inside the actual archive* when provided.

## Compare fields and verify limitations

| Source | Check | Typical significance |
|---|---|---|
| Main PHP `Version` | Intended release version; readme Stable tag; SVN tag when supplied | Stable release selection mismatch may block |
| `readme.txt` Stable tag | Match versioned release and actual SVN tag | Incorrect tag directs directory to older content |
| Main/readme `Requires at least` | Agree when both set, consistent with supported WP versions | Conflict is warning; incompatibility requires code/runtime evidence |
| Main/readme `Requires PHP` | Agree when both set | Warning when inconsistent; do not invent tested support |
| `Tested up to` | Evidence-backed claim | Static string doesn't prove validation |
| Text domain / gettext | Usually slug, translation handling | Mismatch is warning pending impact |
| License/readme | Declaration and bundled dependency licenses | License adjudication is manual; missing standalone license isn't automatic directory rejection |
| Current changelog | Describes current version and changes | Missing entry often warning, not proof of rejection |
| ZIP runtime files | Main PHP, includes, CSS/JS, runtime libraries | Missing required include is a blocker if proven |

Development-only folders (`.git`, `node_modules`, `tests`, caches) are warning/hygiene in a shipped archive; their presence in a source checkout is not proof of shipped defects. A `vendor/` directory can be required for runtime. Optional headers, screenshots and banners are not mandatory by default. Also review plugin assets in SVN `/assets` separately from installed plugin assets.

Reference official sources in `references/official-rules-and-sources.md`. Do not misstate policy certainty. No scanner can verify every required runtime include, PHP/core range, translation catalog or license compatibility.
