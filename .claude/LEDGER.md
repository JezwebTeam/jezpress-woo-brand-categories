# Release ledger — JezPress Woo Brand Categories

**Slug:** `jezpress-woo-brand-categories`

> Dev-only, append-only **governance log**. Excluded from the release ZIP (the whole `.claude/` folder is excluded). One row per shipped version, **newest first**, appended at Phase 6 of every release once the pre-release audit verdict is known and the dashboard patches are confirmed.
>
> **Why it exists:** the `Audit` and `JezPress`/`Dashboard` columns are the point — they are the only durable record that the audit gate ran and the ship steps completed (git tags + `readme.txt` don't capture that). `Version`/`Date`/`Shipped` are just anchors that also live in git + `readme.txt`; keep `Shipped` to **one line** — do not reproduce the changelog here.

## Audit column values
- `clean` — pre-release audit found no Critical/High.
- `fixes (N H)` / `fixes (N C)` — N High/Critical found and fixed before the tag; re-audited if material.
- `blocked → fixed` — a Critical was present; shipped only after it was resolved (note what in the row).
- `manual` — subagents unavailable, manual self-review only (per `audit-guide.md` — say so explicitly).

## JezPress / Dashboard column values
- **JezPress**: `uploaded` · `skipped` (no token) · `n/a`
- **Dashboard**: `patched` (description + changelog tabs) · `pending` · `n/a`

## Ledger
| Version | Date | Audit | Shipped | JezPress | Dashboard |
|---|---|---|---|---|---|
| 1.5.0 | 2026-06-26 | fixes (1 M) | All Brands (A-Z) full brands-directory options: 1-4 column brand lists, per-letter counts, live brand search; + link hover-colour control on both widgets. Audit PASS; fixed 1 M (per-letter count now skips un-linkable brands in get_brands_grouped so count==render). Deployed to staging 186999 | uploaded | patched |
| 1.4.0 | 2026-06-26 | manual | Typography group controls (font family+size+weight) on Trending + All Brands (A-Z) widgets (heading/letters/lists), replacing the size-only sliders; removed Trending/A-Z list bullets (list-style:none !important). Light diff-review only (Elementor Style-control + CSS, no attack surface, per audit-guide). Deployed to staging 186999 | uploaded | patched |
| 1.3.0 | 2026-06-26 | clean | All Brands (A-Z): column control (3/4/5/6) + show-brand-list toggle; Elementor Style controls (heading/list font size + colours) on both new widgets. Audit PASS (1 M: .pot header-only since scaffold, deferred - no wp-cli i18n). Deployed to staging 186999 | uploaded | patched |
| 1.2.0 | 2026-06-26 | clean | New Trending Brands (click-tracked via nonce'd AJAX + term meta; 1h cache; fallback so never blank) and All Brands (A-Z) (alphabet jump-index + per-letter grouped sections) widgets/shortcodes; shared on-demand asset enqueue; audit PASS (A-Z query cached per finding). CI registered version OK this time; deployed to staging 186999 | uploaded | patched |
| 1.1.0 | 2026-06-26 | clean | New Style tab: colour pickers (active/chevron/accent) output as CSS variables; hex-validated, idempotent save preserved. CI upload step reported success but did NOT register the version — caught by verifying the server, uploaded manually with --ver. Deployed to staging 186999 | uploaded | patched |
| 1.0.6 | 2026-06-26 | manual | Cosmetic CSS: brand toggle button ignores theme padding/border (!important); light diff-review only (no agent, per audit-guide cosmetic exception); deployed to staging 186999 | uploaded | patched |
| 1.0.5 | 2026-06-26 | clean | Remove "Other brands clickable" toggle; all brands expandable by default (built-in); deployed + verified on staging 186999 | uploaded | patched |
| 1.0.4 | 2026-06-26 | clean | REAL settings-save fix: non-idempotent per-tab sanitiser early-returned on its 2nd pass and reverted saves (latent since 1.0.0). Merge moved to handler, sanitiser made idempotent; root cause proven via live POST/sanitise capture on staging 186999; save persistence verified | uploaded | patched |
| 1.0.3 | 2026-06-26 | clean | Settings-save attempt: active tab sent as top-level field (rule out WAF stripping of underscore-prefixed POST keys) — did not resolve; superseded by 1.0.4 | uploaded | patched |
| 1.0.2 | 2026-06-19 | clean | Settings save via admin-post.php (bypass options.php allowed_options whitelist that role/security plugins filter) + "Settings saved" notice; deployed to staging 186999 | uploaded | patched |
| 1.0.1 | 2026-06-19 | clean | Hotfix: site-wide front-end fatal (canonical filter TypeError under strict_types) when Rank Math active; verified on staging site 186999 | uploaded | patched |
| 1.0.0 | 2026-06-19 | fixes (1 H) | Initial release: in-brand category dropdown + clean /brands/{brand}/{category}/ URLs + Rank Math SEO for WooCommerce brand archives | uploaded | patched |
