# JezPress audit guide — phase-end + pre-release gates

Two mandatory gates, same machinery:
- **Phase-end audit** — at the end of every build phase that adds attack surface, persists data, integrates externally, or lands architecture later phases build on. Gates the *build*.
- **Pre-release audit** — before bumping the version on any release. Gates the *ship*. Non-negotiable.

Light diff-review only (no agent) for purely cosmetic phases (icons, CSS polish, copy edits, single-file renames). **State which path you took** so the gate is visibly honoured.

## How to run a gate

1. **Isolate the diff.** `git diff <prev-phase>..HEAD -- <paths>` or the staged/changed files for this phase.
2. **Dispatch the `jezpress-code-reviewer` agent** (read-only) with:
   - the exact diff/scope,
   - the phase name and what it introduced,
   - a phase-tailored sanity checklist (AJAX → nonce+cap+sanitise+ownership; updater → URL/method/auth carrier+fallback+transient namespace; settings → sanitiser allowlist; custom table → prepare+dbDelta+uninstall-drop),
   - the "NOT to flag" list (core funcs, established conventions, out-of-scope code).
3. **Read the verdict.** Surface every CRITICAL/HIGH to the user.

## Acting on findings

- **CRITICAL / HIGH** — fix before declaring the phase complete. Re-audit if the fix is material.
- **MEDIUM** — user judgement: fix now or document as known in `PLAN.md`.
- **LOW** — note in the commit message / PR description.

## Phases requiring a FULL audit (no exceptions)

AJAX/REST endpoints · nonce/capability surfaces · settings persistence & sanitisers · custom tables/SQL · file uploads/filesystem writes · updater/licence/external HTTP · hook map / base classes / template helpers later phases depend on · frontend assets handling user-supplied data.

## Dispatch preference

Use the committed **`jezpress-code-reviewer`** agent (`.claude/agents/`). If subagents are unavailable (e.g. spend limit), do a **manual self-review** against this guide and **say so explicitly** in the user-facing update — a manual pass is not the same as the independent gate, and the user needs to know which they got.

> Why this matters: an architectural mistake caught at Phase 2 costs minutes; the same mistake caught at release forces a refactor touching every later phase. Per-phase gates also keep each report focused on one diff instead of an overwhelming end-of-project sweep.
