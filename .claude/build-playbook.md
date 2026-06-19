# JezPress plugin build playbook (7 phases)

Follow these phases **in order** when scaffolding/building a JezPress plugin. Each phase has an audit gate. Don't reorder; don't skip the audits. This sequences *when* to do things — `scaffold-standard.md` is *how*.

## Phase 0 — Plan & scope *(no audit)*
- Define the **single thing** the plugin does. Resist scope creep.
- Pick: slug (`jezpress-<thing>`), fn prefix (`<x>_`), class/const prefix (`<X>_`), namespace segment.
- Create the GitHub repo **under the JezwebTeam org** (not a personal account). Actions auto-enable.

## Phase 1 — Scaffold *(full phase-end audit)*
- Start from `wp-plugin-template` (clone or copy the local working copy). Drop the template `.git/` before `git init`.
- Bootstrap, licence, updater, admin, HPOS, security baseline, uninstall, manager registration are pre-wired — do NOT rebuild these.
- Substitute placeholders: `{PLUGIN_NAME}` `{PLUGIN_SLUG}` `{PLUGIN_NAMESPACE}` `{PREFIX}` `{CLASS_PREFIX}` `{CONST_PREFIX}` `{PLUGIN_DESCRIPTION}` `{PLUGIN_DESCRIPTION_LONG}`. Rename files first, then content-replace. Verify zero leftover `{[A-Z_]*}` tokens (case-sensitive).
- Write the plugin's own `CLAUDE.md`, a repo-root `PLAN.md` (see `PLAN.template.md`), and seed `.claude/LEDGER.md` from `LEDGER.template.md` (empty rows until the first release).
- Run the 18-point checklist (`scaffold-standard.md`) as a verification pass.

## Phase 2 — Settings + admin *(full audit)*
- `<X>_Admin` with a single `<x>_settings` option + one sanitiser that clamps every key (allowlists for enums as `const`s).
- Tabs: General / Documentation / License.

## Phase 3 — Core feature *(full audit)*
- The thing the plugin does. AJAX pattern: `check_ajax_referer` FIRST → ownership/capability validation → `wp_unslash` + sanitize every superglobal → escape on output.
- Templates via `<x>_get_template()` (child → parent → plugin).

## Phase 4 — Licence + Updater *(full audit + live `curl` test)*
- `<X>_License` singleton, hashed option `jzwb_lic_<md5(slug)[0..8]>`. `<X>_Updater` fluent chain.
- **CRITICAL:** `license_key` as a **URL query param**, NOT a Bearer header — see `updater-contract.md`.
- Live-verify against `updates.jezpress.com` with a real key before declaring done (silent-fail risk otherwise).

## Phase 5 — Release infrastructure *(full audit)*
- Copy the canonical `.github/workflows/release.yml`; apply the per-plugin substitutions. Do NOT write from scratch.
- Write `readme.txt` (Stable tag + all sections), `languages/<slug>.pot`, `uninstall.php` (remove all options/transients/licence/cron + any custom tables).
- Set repo secret `JEZPRESS_TOKEN`. Leave `GCHAT_WEBHOOK_URL` **unset** — the Google Chat card is posted by the `/release` skill, not CI, so keeping the secret unset avoids double-posting.

## Phase 6 — First release *(pre-release audit MANDATORY)*
- Bump `1.0.0` across: plugin header `Version:`, `<PREFIX>_VERSION`, `readme.txt` `Stable tag:`, `.pot`, CLAUDE.md.
- Run the pre-release audit (non-negotiable gate). Apply Critical/High → re-audit if material.
- Tag → push → watch the workflow. Then changelog CLI + dashboard tab patches + Google Chat — all orchestrated by the **`/release` skill** (`.claude/skills/release/`).
- **Append a row to `.claude/LEDGER.md`** (newest first): version, date, audit verdict, one-line summary, JezPress + dashboard status. Do this every release, not just 1.0.0.

## Phase 7 — First deployment *(no audit)*
- Add a `## Known deployments` row to CLAUDE.md (prod URL, contact, owner, settings, quirks).
- File bugs as ERPNext issues with `client_management: <domain>`.

## Audit gates
| Phase | Audit |
|---|---|
| 0 Plan | — |
| 1 Scaffold | Full (architecture) |
| 2 Settings | Full (persistence) |
| 3 Core | Full (attack surface) |
| 4 Licence+Updater | Full + live curl |
| 5 Release infra | Full (pipeline) |
| 6 First release | **Pre-release (mandatory)** |
| 7 Onboarding | — |

Audits = dispatch a senior WP plugin code-review subagent (read-only, CRITICAL/HIGH/MEDIUM/LOW with file:line). If subagents are unavailable, do a manual self-review and **say so explicitly**.
