# `.claude/` — Reusable JezPress plugin knowledge bundle

This folder is **portable, committed, repo-safe** knowledge that Claude Code auto-loads when you open the project (via the `@import` lines in the repo-root `CLAUDE.md`). Copy this whole folder into any JezPress plugin to reuse the standards.

## What's here

| File | Purpose |
|---|---|
| `build-playbook.md` | The ordered 7-phase sequence for building a JezPress plugin end-to-end. Start here. |
| `scaffold-standard.md` | 18-point acceptance checklist + canonical-source pointers for a new scaffold. |
| `updater-contract.md` | The licence/updater HTTP contract (the counter-intuitive `license_key`-in-URL rule). |
| `audit-guide.md` | How to run the phase-end / pre-release audit gates. |
| `PLAN.template.md` | Skeleton for the repo-root `PLAN.md` (the live phase checklist for a build). |
| `LEDGER.template.md` | Skeleton for `.claude/LEDGER.md` — the per-plugin, append-only release + audit log (one row per shipped version). |
| `skills/release/SKILL.md` | The `/release X.Y.Z` skill — the full release pipeline (bump → audit → tag → push → upload → changelog → dashboard tabs → LEDGER → updater verify → Google Chat). Self-contained; uses `{PLUGIN_SLUG}`/`{CONST_PREFIX}`/`{PLUGIN_NAME}` placeholders. |
| `agents/jezpress-code-reviewer.md` | Reusable senior WP code-reviewer subagent (auto-discovered) for the audit gates. |
| `settings.json` | Scoped permission allowlist — auto-approves routine dev + the release steps (push/release/jezpress upload); still prompts for `curl`/`wget`, `gh repo`, and destructive git/deletes. |

> `settings.local.json` (if present) is the harness's machine-local permission cache — git-ignored, NOT part of this bundle. Don't copy it.
>
> `LEDGER.md` (if present) is a **per-plugin instance**, not part of the bundle — when copying `.claude/` into a new plugin, re-seed it from `LEDGER.template.md`, don't carry the old plugin's ledger over.

## How to reuse in a new plugin

1. Copy this `.claude/` folder into the new plugin repo.
2. Add the `@import` block (see repo-root `CLAUDE.md`) so the docs auto-load.
3. Copy `PLAN.template.md` → repo-root `PLAN.md` and fill it in.
4. Copy `LEDGER.template.md` → `.claude/LEDGER.md` and substitute `{PLUGIN_NAME}` / `{PLUGIN_SLUG}`. Leave the rows empty until the first release.

## Hard rule: NO SECRETS in this folder

These repos are public on GitHub. **Never** put licence keys, the Google Chat webhook URL, or any credential here. Secrets stay in the machine-local, git-ignored `~/.claude/memory/` and are sourced at run time only. This bundle is the *non-secret* half of the standards.

> Source of truth: these are repo-local copies of the JezPress standards. The authoritative originals live in `~/.claude/memory/` (user-level) and the `wp-plugin-template` repo. Keep this bundle in sync when the standards evolve.
