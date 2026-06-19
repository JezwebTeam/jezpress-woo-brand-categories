# JezPress updater + licence contract

The updater silently fails open — a broken updater returns no error, it just never injects an update. **End-to-end test against the live endpoint with a real licence key before declaring updater work done.**

## The contract (counter-intuitive — memorise it)

| Endpoint | Method | Auth carrier |
|---|---|---|
| `GET /api/v1/update` | GET | **URL query param `license_key=...`** (NOT a Bearer header) |
| `POST /api/v1/license/activate` | POST | JSON body `{"license_key":"..."}` |
| `POST /api/v1/license/validate` | POST | JSON body `{"license_key":"..."}` |
| `POST /api/v1/license/deactivate` | POST | JSON body `{"license_key":"..."}` |
| `* /api/dev/plugins/*` (admin) | varies | `Authorization: Bearer <JWT>` (NOT a licence key) |

`/api/v1/update` and the licence endpoints look symmetric but are NOT. The server reads the licence from the **URL query param only** on `/api/v1/update`; a Bearer header there is silently ignored. Do not "harden" it by moving the key into a header.

**Why this rule exists:** a senior-audit change once moved the licence into an `Authorization: Bearer` header to avoid leaking it in logs. The server ignored the header → every update check got `license_required` → no banner ever appeared. Shipped broken twice before being caught.

## Verification (run before declaring done)

```bash
curl -s "https://updates.jezpress.com/api/v1/update?plugin=<SLUG>&version=<OLD_VERSION>&site_url=https://www.example.com&license_key=<TEST_KEY>" | python -m json.tool
```
Expect: `{ "success": true, "update_available": true, "version": "...", "download_url": "..." }`.

Failure modes:
- `license_required` → server saw no key (sent in a header it doesn't read, or wrong param name — it's `license_key` with an underscore).
- `invalid_license` → key reached server but isn't activated for this site_url/plugin. Activate first.
- HTTP 404 → wrong method (endpoint is GET-only; POST → 404, not 405).
- `update_available: false` when you expect true → reported `version` ≥ latest stable; re-check `<PREFIX>_VERSION` / header / `Stable tag` all match the new value.

## Bring-up checklist
1. Plugin header `Version:`, `<PREFIX>_VERSION`, `readme.txt` `Stable tag:` all match.
2. Updater sends `license_key` as a URL query param, GET, no Bearer header.
3. After first upload, run the curl with an old version → confirm `update_available: true`.
4. In WP admin: set site to older version → "Check for updates" → confirm banner.

> Test licence keys are git-ignored local-only at `~/.claude/memory/` — never commit them.
