---
name: release
description: Run the full JezPress release pipeline for this plugin ({PLUGIN_SLUG}) — version bump, mandatory pre-release audit, commit, tag, push, GitHub release workflow, JezPress upload, per-version changelog, dashboard Description/Changelog tab patches, LEDGER append, live updater verification, and an auto-posted Google Chat announcement (threaded one-parent-per-day). Use when the user says "release X.Y.Z", "ship X.Y.Z", or "roll X.Y.Z". Pass the target version (and optionally a one-line summary) as the argument.
argument-hint: X.Y.Z [one-line changelog summary]
---

# Release pipeline — {PLUGIN_SLUG}

Target version: **$ARGUMENTS** (first token = `X.Y.Z`; remainder, if any, = changelog hint).

Run these in order. This skill runs in the main session, so you keep full context. The `ask`-gated steps (`curl`/`wget`, `gh repo`, destructive git) prompt for approval — that's intended. `git push`, `gh release`, `jezpress` upload and the Google Chat announcement **auto-send, no prompt** (policy 2026-06-17). The release gate is the mandatory pre-release audit + the deliberate act of running this skill — not per-step clicks.

Facts for this plugin:
- Slug: `{PLUGIN_SLUG}` · GitHub: `JezwebTeam/{PLUGIN_SLUG}` · Version const: `{CONST_PREFIX}_VERSION`
- Token: `~/.jezpress-token` (Bearer, never commit) · Test key + activated site: `~/.claude/memory/jezpress-test-license-keys.md`
- Audit details: `.claude/audit-guide.md`. Google Chat card recipe: the `## Google Chat Notifications` section in this plugin's `CLAUDE.md`.

## 1. Bump the version in all 4 places
Set `X.Y.Z` in every one, then confirm no stale refs remain:
1. `{PLUGIN_SLUG}.php` plugin header `Version:`
2. `{PLUGIN_SLUG}.php` `define( '{CONST_PREFIX}_VERSION', '…' )`
3. `readme.txt` `Stable tag:`
4. `languages/{PLUGIN_SLUG}.pot` `Project-Id-Version:`

Verify: `grep -rn "<previous version>" {PLUGIN_SLUG}.php readme.txt languages/*.pot` returns nothing version-related.

## 2. Update readme.txt
- Add a new `= X.Y.Z =` block under `== Changelog ==` (newest first).
- Add a matching `= X.Y.Z =` entry under `== Upgrade Notice ==`.
- Copy is grounded in the actual work done / the summary argument. **Don't fabricate.**

## 3. Pre-release audit (MANDATORY gate)
Dispatch the `jezpress-code-reviewer` subagent on the release diff (`git diff <prev tag>..HEAD` or the changed files). Apply every CRITICAL/HIGH finding, then re-audit if the fix was material. Do not tag with unresolved Criticals. See `.claude/audit-guide.md`.

## 4. Commit
Write the message to a file (PowerShell here-strings are invalid in the Bash tool), then commit:
```bash
# write .git/COMMITMSG.txt (subject + body), ending with the Co-Authored-By line
git -c user.name='Jezweb' -c user.email='abner@jezweb.net' commit -F .git/COMMITMSG.txt
rm -f .git/COMMITMSG.txt
```

## 5. Tag + push (auto-approves)
```bash
git tag vX.Y.Z
git push origin main
git push origin vX.Y.Z
```

## 6. Watch the release workflow
```bash
gh run list --workflow=release.yml --limit 1 --json databaseId,status -q '.[0]'
gh run watch <id> --exit-status
```
It gates on header/const/Stable-tag version match — a failure here means step 1 was incomplete. The Node-20 deprecation annotation is a warning only.

## 7. Download the built zip + upload to JezPress
Run as separate commands (a combined `rm && mkdir && download && upload` chain gets denied):
```bash
mkdir -p /tmp/rel<XYZ>
gh release download vX.Y.Z --repo JezwebTeam/{PLUGIN_SLUG} --dir /tmp/rel<XYZ> --clobber
jezpress plugins upload {PLUGIN_SLUG} /tmp/rel<XYZ>/{PLUGIN_SLUG}-X.Y.Z.zip --ver X.Y.Z --channel stable --sync-metadata
```

## 8. Per-version changelog (CLI)
```bash
jezpress plugins changelog {PLUGIN_SLUG} X.Y.Z "<plain text from the = X.Y.Z = readme block>"
```
**Use ASCII punctuation** — straight quotes `'` and hyphens `-`, NOT em-dashes (—) or curly quotes. They pass through the shell mangled and show as mojibake (`â€"`) in the WP "View details" popup.

## 9. Dashboard Description + Changelog tabs (raw API)
The dashboard reads `plugin.sections.description` / `plugin.sections.changelog` — separate from the per-version changelog, so they go via the raw API. Build both from `readme.txt`:
- **Description** = the `== Description ==` block converted to HTML (`<p>`, `<h3>`, `<ul>/<li>`, `<strong>`, `<code>`).
- **Changelog** = ALL versions, newest-first, as plain text `Version X.Y.Z` headers with `  - bullet` lines.

Write each to a **local** temp `.json` file (Windows Python cannot write to `/tmp`; `json.dump` escapes non-ASCII, so console mojibake is cosmetic). Body shape: `{"description": "<html>", "sections": {"description": "<html>"}}` and `{"changelog": "<text>", "sections": {"changelog": "<text>"}}`. Then PATCH **sequentially** (desc first, then chlog — the server clobbers parallel writes):
```bash
curl -s -X PATCH -H "Authorization: Bearer $(cat ~/.jezpress-token)" -H "Content-Type: application/json" \
  --data-binary @<file> "https://updates.jezpress.com/api/dev/plugins/{PLUGIN_SLUG}" -o /dev/null -w "HTTP %{http_code}\n"
```
Delete the temp files after; verify both `sections` lengths are non-zero.

## 10. Append to `.claude/LEDGER.md`
Add one row (newest first): version, date, audit verdict, one-line summary, and JezPress + dashboard status. The ledger is the durable per-plugin release history — append every release, not just 1.0.0.

## 11. Live updater verification (MANDATORY)
```bash
KEY=<read {PLUGIN_SLUG} key from ~/.claude/memory/jezpress-test-license-keys.md>
SITE=<the activated site for that key>
curl -s "https://updates.jezpress.com/api/v1/update?plugin={PLUGIN_SLUG}&version=<PREVIOUS_VERSION>&site_url=$SITE&license_key=$KEY" | python3 -m json.tool
```
Expect `update_available: true` and `version: X.Y.Z`. If `license_required` → key not sent as a URL param; if `update_available:false` → a version bump was missed (step 1).

## 12. Announce on Google Chat (auto-send, threaded one-parent-per-day)
Post the release card automatically — **no approval prompt** (policy 2026-06-17). Group by plugin + day: the first `{PLUGIN_SLUG}` release of the day creates a parent card; later releases that day land as **thread replies** under it. The date string is the only state — no message ID to store.

- **threadKey** = `{PLUGIN_SLUG}-$(TZ='Australia/Sydney' date +%Y-%m-%d)`
- Append to the webhook URL: `&messageReplyOption=REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD`
- Include in the JSON body: `"thread": { "threadKey": "<threadKey>" }`
- Source the webhook from `~/.claude/memory/jezpress-google-chat-webhook.md` at run time — **never commit it**.
- Card copy from the `= X.Y.Z =` readme block (`<br>`-joined, ASCII punctuation); slug `{PLUGIN_SLUG}`, title `{PLUGIN_NAME}`, repo `JezwebTeam/{PLUGIN_SLUG}`.

See the `## Google Chat Notifications` section in this plugin's `CLAUDE.md` for the full threaded cardsV2 curl (it already includes the `thread.threadKey` + `messageReplyOption` wiring). After posting, confirm HTTP 200 and report what shipped (with the updater-verify result).

> Note: `curl` is in the `ask` list, so the dashboard PATCHes (step 9) and this Google Chat POST are the only steps that prompt during a release — by design, since `curl` can reach any URL. Everything else (push, gh release, jezpress upload) auto-approves.
