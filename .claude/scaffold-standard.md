# JezPress scaffold standard — 18-point acceptance checklist

Canonical source = the `wp-plugin-template` repo + `jezpress-manager`. Cross-check against those; this is a verification checklist, not a replacement. Most items are already ✅ by using the template — you're checking the substitution preserved them.

1. **Plugin header**: Name, URI, Description, Version, Author, Author URI, Licence (GPL-2.0+), Licence URI, Text Domain, Domain Path, Requires at least (6.0+), Requires PHP (8.1+); WC headers when applicable.
2. **PHP version gate** at top of bootstrap — admin notice + `deactivate_plugins` if < 8.1.
3. **Constants**: `<PREFIX>_VERSION`, `_PLUGIN_DIR`, `_PLUGIN_URL`, `_PLUGIN_BASENAME`, `_PLUGIN_FILE`.
4. **HPOS + Cart/Checkout block compat** declared on `before_woocommerce_init` (only if a WC plugin).
5. **`index.php` silence file** in every directory.
6. **`declare(strict_types=1);` + ABSPATH guard** at the top of every PHP file.
7. **Single `<prefix>_settings` option** with one sanitiser clamping every key. Defaults centralised; enum allowlists as `const`s (`private`→`public` when consumed externally).
8. **AJAX**: `check_ajax_referer` FIRST → ownership/capability validation → unslash + sanitize every `$_POST`/`$_GET`. No raw superglobal reaches output.
9. **Licence**: hashed option name (`jzwb_lic_<md5(slug)[0..8]>`), singleton handler, daily cron validation.
10. **Updater**: fluent setters, `license_key` as **URL query param** (NOT Bearer). End-to-end test with a real licence via curl. See `updater-contract.md`.
11. **`JezPress_Manager::register()`** in init when available; settings menu falls back to top-level otherwise.
12. **Templates** via `<prefix>_get_template()` (child theme → parent theme → plugin).
13. **Output escaping** in every template: `esc_url` / `esc_attr` / `esc_html` / `wp_kses` w/ allowlist. PHPDoc `@var` shapes.
14. **uninstall.php** removes all `<prefix>_*` options, transients (LIKE cleanup), hashed licence option, cron event — **and any custom tables**.
15. **readme.txt** with Stable tag + `== Description ==`, `== Installation ==`, `== FAQ ==`, `== Screenshots ==`, `== Changelog ==`, `== Upgrade Notice ==`.
16. **`.github/workflows/release.yml`** — copy from canonical template + per-plugin substitutions. Do NOT write from scratch.
17. **Plugin `CLAUDE.md`** at repo root: slug, prefixes, file structure, hook map, settings shape, security baseline, release process.
18. **`## Google Chat Notifications` section in CLAUDE.md** — slug-aware cardsV2 curl that **sources the webhook URL from `~/.claude/memory/` at run time**. The URL is NEVER written into any committed file. Auto-sent on release, threaded one-parent-per-plugin-per-day (`<slug>-<Sydney date>` threadKey + `REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD`); policy 2026-06-17.

**Verify before declaring scaffold complete:** activates/deactivates cleanly, settings persist, no PHP warnings, zero leftover `{PLACEHOLDER}` tokens, works with and without JezPress Manager.
