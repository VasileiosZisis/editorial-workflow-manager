# FICTIONAL release review (demonstration, no real plugin)

**Candidate:** fictional `folio-review-2.2.0.zip` for slug `folio-review`. **Decision:** HOLD. This is invented illustrative input, not a verified release or an allegation about a real product.

- **R-01 (BLOCKER / CONFIRMED, ZIP evidence):** Main PHP `Version: 2.2.0`, readme `Stable tag: 2.1.0`. Reconcile and rebuild; WordPress.org SVN tag not inspected.
- **R-02 (WARNING / POTENTIAL, source trace):** A fictional upgrader invokes a default-config seeder if `$settings['approval_mode']` is `empty()`. If `approval_mode = 0` represents the administrator's supported disabled choice, an upgrade could re-enable approval. Need to inspect settings validation and downstream enforcement **before** claiming a bug. No runtime reproduction.

**Upgrade matrix:** fresh install missing setting → default expected; prior existing disabled setting → expected to remain disabled unless documented migration; custom mode → preserve. **Verification:** Plugin Check, readme validator, SVN and WordPress upgrade test **NOT RUN**. No tags or publication actions performed.
