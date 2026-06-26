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
| 1.1.0 | 2026-06-26 | clean | New Style tab: colour pickers (active/chevron/accent) output as CSS variables; hex-validated, idempotent save preserved. CI upload step reported success but did NOT register the version — caught by verifying the server, uploaded manually with --ver. Deployed to staging 186999 | uploaded | patched |
| 1.0.6 | 2026-06-26 | manual | Cosmetic CSS: brand toggle button ignores theme padding/border (!important); light diff-review only (no agent, per audit-guide cosmetic exception); deployed to staging 186999 | uploaded | patched |
| 1.0.5 | 2026-06-26 | clean | Remove "Other brands clickable" toggle; all brands expandable by default (built-in); deployed + verified on staging 186999 | uploaded | patched |
| 1.0.4 | 2026-06-26 | clean | REAL settings-save fix: non-idempotent per-tab sanitiser early-returned on its 2nd pass and reverted saves (latent since 1.0.0). Merge moved to handler, sanitiser made idempotent; root cause proven via live POST/sanitise capture on staging 186999; save persistence verified | uploaded | patched |
| 1.0.3 | 2026-06-26 | clean | Settings-save attempt: active tab sent as top-level field (rule out WAF stripping of underscore-prefixed POST keys) — did not resolve; superseded by 1.0.4 | uploaded | patched |
| 1.0.2 | 2026-06-19 | clean | Settings save via admin-post.php (bypass options.php allowed_options whitelist that role/security plugins filter) + "Settings saved" notice; deployed to staging 186999 | uploaded | patched |
| 1.0.1 | 2026-06-19 | clean | Hotfix: site-wide front-end fatal (canonical filter TypeError under strict_types) when Rank Math active; verified on staging site 186999 | uploaded | patched |
| 1.0.0 | 2026-06-19 | fixes (1 H) | Initial release: in-brand category dropdown + clean /brands/{brand}/{category}/ URLs + Rank Math SEO for WooCommerce brand archives | uploaded | patched |
