# PLAN.md — JezPress Woo Brand Categories

**Slug:** `jezpress-woo-brand-categories` · **Prefixes:** fn `{prefix}_` / class `JPWBC_` / const `JPWBC_` · **Namespace:** `JezPress\WooBrandCategories`
**Status:** Phase 1 (scaffold) — created <YYYY-MM-DD>.

> Dev-only file. Excluded from the release ZIP (`-x "*PLAN.md"`). Mirrors `.claude/build-playbook.md`; tick items as each phase passes its audit gate.

## The single thing it does
<one paragraph — the one job; resist scope creep>

## Confirmed decisions (locked)
1. <decision + why; rejected alternatives>
2. ...

## Build phases (playbook-aligned)
- [ ] **Phase 0 — Plan & scope.** Naming locked. GitHub repo under JezwebTeam org. *(no audit)*
- [ ] **Phase 1 — Scaffold.** Template copied, placeholders substituted (verify 0 leftovers), files renamed, CLAUDE.md + PLAN.md + `.claude/LEDGER.md` (seeded from `LEDGER.template.md`) written, 18-point pass. *(full audit)*
- [ ] **Phase 2 — Settings + admin.** *(full audit)*
- [ ] **Phase 3 — Core feature.** *(full audit)*
- [ ] **Phase 4 — Licence + Updater.** URL-param licence; live curl test. *(full audit + live test)*
- [ ] **Phase 5 — Release infra.** release.yml + readme.txt + .pot + uninstall.php. *(full audit)*
- [ ] **Phase 6 — First release (1.0.0).** Pre-release audit; tag → workflow → upload → changelog → dashboard patches. *(pre-release audit)*
- [ ] **Phase 7 — First deployment.** Known deployments row; ERPNext issues. *(no audit)*

## Open items / to confirm
- <...>

## Audit-gate note
<record any deferred/manual audits and why>
