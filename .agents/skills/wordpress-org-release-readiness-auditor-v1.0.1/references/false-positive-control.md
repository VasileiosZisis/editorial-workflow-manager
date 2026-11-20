# Findings, confidence and release-gate discipline

- **BLOCKER / CONFIRMED:** actual release candidate has a proven packaging/version mismatch, or a reachable stateful upgrade path is strongly evidenced to violate a documented material contract. Cite exact read/call/write chain and affected persisted state. Explicitly say runtime **NOT RUN** if appropriate.
- **WARNING / CONFIRMED:** observed mismatch/hygiene defect with limited or non-blocking consequence (e.g., text domain mismatch, development files in shipped ZIP).
- **WARNING / POTENTIAL:** plausible risk that needs acceptance criteria, historical state, or runtime verification to establish behavior.
- **EVIDENCE NEEDED / NOT CHECKED:** no source, source-to-ZIP parity, SVN tag, Plugin Check, readme validator or tests; this is **not** a failed test.

Use one of `HOLD`, `VERIFY BEFORE RELEASE`, `READY FOR HUMAN RELEASE`, `INSUFFICIENT CONTEXT` with the advisory scope described in SKILL.md.

## Prevent false positives

- `empty($option)` is **not** automatically wrong; determine whether empty/None/false is allowed, what the code writes and its downstream consequence.
- “Default seeder runs during upgrade” is not automatically a defect. It may deliberately initialize a new feature without replacing valid old settings.
- Source-only `/tests` or `/vendor` folders don't prove the ZIP includes them; required runtime dependencies belong in the package.
- Missing test logs are an **unverified requirement**, not evidence a runtime bug exists.
- A source trace is not a runtime reproduction; distinguish `CONFIRMED SOURCE PATH` from `RUNTIME REPRODUCED`.
- A pre-existing issue is not necessarily introduced by the new release; label origin explicitly if a baseline is accessible.
- GPL/license declarations and missing license copy require informed review; don't automatically allege legal violation or guaranteed rejection.
- Do not invent WP/PHP versions, WordPress.org listing/SVN state, or Plugin Check result.

## Required finding schema

`ID | severity | confidence | origin (introduced / pre-existing / unknown) | source/ZIP evidence | persisted state + trigger (if upgrade) | contract/impact | minimum fix | exact recheck`.

Group duplicate symptoms from one root cause. Preserve independent findings when distinct remediation or risk justifies them.
