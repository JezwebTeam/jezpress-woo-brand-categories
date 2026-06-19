# CLAUDE.md - JezPress Woo Brand Categories

## Reusable standards (auto-loaded)

These ship committed in `.claude/` and travel with the plugin. They auto-load via the lines below.

@.claude/build-playbook.md
@.claude/scaffold-standard.md
@.claude/LEDGER.md

> Phase-specific docs load on demand (not auto-loaded): `.claude/updater-contract.md` (Phase 4), `.claude/audit-guide.md` (every audit gate). The release pipeline (bump → audit → tag → push → upload → changelog → dashboard tabs → LEDGER → updater verify → Google Chat) is the **`/release` skill** (`.claude/skills/release/`). A committed `jezpress-code-reviewer` subagent (`.claude/agents/`) and a scoped `settings.json` ship in `.claude/` too. See `.claude/README.md`.

## Overview

**JezPress Woo Brand Categories** adds in-brand product-category navigation to WooCommerce brand archives. On a brand archive (e.g. `/brands/dmc/`) it renders a dropdown of the product categories that brand actually has products in, with counts, and serves SEO-friendly clean combo URLs (`/brands/{brand}/{category}/`) that filter the archive to that brand + category. Built for Birch Wholesale (birchcreative.com.au) — ticket ISS-2026-01253. See `PLAN.md` for the full spec and build phases.

**Slug:** `jezpress-woo-brand-categories` · **Prefix:** `jpwbc` / `JPWBC` · **Namespace seg:** `WooBrandCategories`
**Requires:** WordPress 6.4+, PHP 8.1+, WooCommerce 9.6+ (native `product_brand` taxonomy)
**Licence-gated:** Yes (JezPress Manager + update server)
**Central Plugin:** [jezpress-manager](https://github.com/JezwebTeam/jezpress-manager)
**SEO target:** Rank Math · **Page builder:** Elementor (brand archive = Theme Builder template, post ID 3529)

## Quick Start

### 1. Copy the Template

```bash
cp -r wp-plugin-template your-plugin-slug
cd your-plugin-slug
```

### 2. Replace Placeholders

Replace all placeholders with your plugin-specific values:

| Placeholder | Replace With | Example |
|-------------|--------------|---------|
| `JezPress Woo Brand Categories` | Human-readable plugin name | `JezPress Woo Mailchimp` |
| `jezpress-woo-brand-categories` | Plugin slug (lowercase, hyphens) | `jezpress-woo-mailchimp` |
| `In-brand product-category navigation and clean brand+category URLs for WooCommerce brand archives.` | Short description | `WooCommerce Mailchimp integration` |
| `adds an in-brand product-category dropdown and SEO-friendly clean URLs to WooCommerce brand archives` | Full description for readme | `provides newsletter subscription...` |
| `WooBrandCategories` | PHP namespace segment | `WooMailchimp` |
| `jpwbc` | Function/option prefix (lowercase) | `jwm` |
| `JPWBC` | Class name prefix (uppercase) | `JWM` |
| `JPWBC` | Constant prefix (uppercase) | `JWM` |

### 3. Rename Files

```bash
mv wp-plugin-template.php your-plugin-slug.php
mv includes/class-jpwbc-loader.php includes/class-yourprefix-loader.php
mv includes/class-jpwbc-admin.php includes/class-yourprefix-admin.php
mv includes/class-jpwbc-license.php includes/class-yourprefix-license.php
mv includes/class-jpwbc-updater.php includes/class-yourprefix-updater.php
mv assets/css/jpwbc-admin.css assets/css/yourprefix-admin.css
mv assets/js/jpwbc-admin.js assets/js/yourprefix-admin.js
```

### 4. Register with JezPress Update Server

```bash
jezpress login                           # Authenticate with Google
jezpress plugins create your-plugin-slug # Create plugin entry
```

## File Structure

```
your-plugin-slug/
├── your-plugin-slug.php              # Main plugin bootstrap
├── CLAUDE.md                          # This documentation (AI context)
├── readme.txt                         # WordPress.org style readme
├── uninstall.php                      # Cleanup on uninstall
├── index.php                          # Security (silence is golden)
├── includes/
│   ├── index.php                      # Security
│   ├── class-{prefix}-loader.php      # Hook registration system
│   ├── class-{prefix}-admin.php       # Admin settings page
│   ├── class-{prefix}-license.php     # JezPress license handler
│   └── class-{prefix}-updater.php     # JezPress auto-updater
├── assets/
│   ├── index.php                      # Security
│   ├── css/
│   │   ├── index.php                  # Security
│   │   └── {prefix}-admin.css         # Admin styles
│   ├── js/
│   │   ├── index.php                  # Security
│   │   └── {prefix}-admin.js          # Admin JavaScript
│   └── images/
│       └── index.php                  # Security
└── languages/
    └── index.php                      # Security
```

## Architecture

### Design Patterns

- **Singleton Pattern** - License class uses singleton for single instance
- **Loader Pattern** - Centralized hook registration via Loader class
- **Fluent Interface** - Updater class uses method chaining

### Initialization Flow

```
plugins_loaded (priority 20)
    └── {prefix}_init()
        ├── Include class files
        ├── Initialize Loader
        ├── Initialize Admin
        ├── Initialize License (singleton)
        ├── Register with JezPress Manager
        ├── Initialize Updater
        └── Loader->run() (register all hooks)
```

### Hook Priorities

| Plugin | Priority | Purpose |
|--------|----------|---------|
| JezPress Manager | 5 | Central dashboard |
| Your Plugin | 20 | After manager is ready |

## JezPress Manager Integration

### Registration

Your plugin automatically registers with JezPress Manager when active:

```php
if ( class_exists( 'JezPress_Manager' ) && method_exists( 'JezPress_Manager', 'register' ) ) {
    JezPress_Manager::register(
        array(
            'slug'           => 'jezpress-woo-brand-categories',
            'name'           => 'JezPress Woo Brand Categories',
            'version'        => JPWBC_VERSION,
            'license_status' => $license->get_status(),
            'settings_url'   => admin_url( 'admin.php?page=jpwbc-settings' ),
        )
    );
}
```

### Available Registration Parameters

| Parameter | Required | Type | Description |
|-----------|----------|------|-------------|
| `slug` | Yes | string | Unique plugin identifier |
| `name` | Yes | string | Display name |
| `version` | Yes | string | Current version |
| `license_status` | No | string | `active`, `inactive`, `expired` |
| `settings_url` | No | string | Link to settings page |
| `menu_title` | No | string | Submenu title |
| `callback` | No | callable | Render callback for submenu page |
| `icon` | No | string | Dashicon class |
| `position` | No | int | Menu position |

### Quick Check for Manager Availability

```php
// Use constant for quick check
if ( defined( 'JEZPRESS_MANAGER_ACTIVE' ) && JEZPRESS_MANAGER_ACTIVE ) {
    // Manager is active
}
```

## JezPress Platform Services

### Update Server

- **URL:** https://updates.jezpress.com
- **Update Endpoint:** `/api/v1/update` (GET)
- **License Endpoints:**
  - `/api/v1/license/activate` (POST)
  - `/api/v1/license/deactivate` (POST)
  - `/api/v1/license/validate` (POST)

### CLI Commands

```bash
jezpress login                              # Authenticate with Google
jezpress whoami                             # Check current user
jezpress plugins list --mine                # List your plugins
jezpress plugins get <slug>                 # Plugin details
jezpress plugins preflight <slug> <zip>     # Validate before upload
jezpress plugins upload <slug> <zip>        # Upload new version
jezpress docs                               # Full documentation
```

### MCP Server

For Claude Desktop/Claude Code integration:
- **URL:** https://mcp.jezpress.com/mcp
- Provides tools for plugin management directly from AI assistants

## Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Function prefix | `{prefix}_` | `jwm_get_option()` |
| Class prefix | `JPWBC_` | `class JWM_Admin` |
| Constant prefix | `JPWBC_` | `JWM_VERSION` |
| Option prefix | `{prefix}_` | `jwm_api_key` |
| Hook prefix | `{prefix}_` | `do_action('jwm_after_save')` |
| Meta key prefix | `_{prefix}_` | `_jwm_subscribed` |
| Nonce action | `{prefix}_` | `{prefix}_save_settings` |
| AJAX action | `{prefix}_` | `wp_ajax_{prefix}_test` |
| Transient prefix | `{prefix}_` | `{prefix}_cache_data` |

## Code Standards

### PHP Requirements

- **PHP 8.1+** required
- Use `declare(strict_types=1);`
- Type declarations for parameters and return types
- Constructor property promotion where appropriate
- Strict comparison operators (`===`, `!==`)
- Early returns to reduce nesting

### WordPress Coding Standards

```php
// File header
defined( 'ABSPATH' ) || exit;

// Escaping output
echo esc_html( $text );
echo esc_url( $url );
echo esc_attr( $attribute );
echo wp_kses_post( $html );

// Sanitizing input
$text = sanitize_text_field( $_POST['text'] );
$email = sanitize_email( $_POST['email'] );
$key = sanitize_key( $_POST['key'] );

// Nonce verification
if ( ! wp_verify_nonce( $_POST['nonce'], '{prefix}_action' ) ) {
    wp_die( 'Security check failed.' );
}

// Capability checks
if ( ! current_user_can( 'manage_options' ) ) {
    return;
}
```

### PHPDoc Standards

```php
/**
 * Short description.
 *
 * Longer description if needed.
 *
 * @since 1.0.0
 *
 * @param string $param_name Description.
 * @return bool Description.
 */
```

## Security Requirements

1. **ABSPATH Check** - All files must have `defined( 'ABSPATH' ) || exit;`
2. **Nonce Verification** - All form submissions and AJAX requests
3. **Capability Checks** - Before any admin actions
4. **Input Sanitization** - All user input before storage
5. **Output Escaping** - All output before display
6. **index.php** - Every directory must have security index.php

## Adding New Features

### Adding a New Class

1. Create file: `includes/class-{prefix}-feature.php`
2. Add ABSPATH check and declare strict types
3. Include in main plugin file:
   ```php
   require_once JPWBC_PLUGIN_DIR . 'includes/class-{prefix}-feature.php';
   ```
4. Initialize in `{prefix}_init()`:
   ```php
   $feature = new JPWBC_Feature();
   $loader->add_action( 'hook_name', $feature, 'method_name' );
   ```

### Adding Settings

1. Register setting in `JPWBC_Admin::register_settings()`:
   ```php
   register_setting(
       $this->option_group,
       '{prefix}_new_setting',
       array(
           'type'              => 'string',
           'sanitize_callback' => 'sanitize_text_field',
           'default'           => '',
       )
   );
   ```

2. Add to uninstall.php options array

3. Add default in activation hook if needed

### Adding AJAX Handlers

1. Register in loader:
   ```php
   $loader->add_action( 'wp_ajax_{prefix}_action', $admin, 'handle_action' );
   ```

2. Implement handler:
   ```php
   public function handle_action(): void {
       check_ajax_referer( '{prefix}_admin_nonce', 'nonce' );

       if ( ! current_user_can( 'manage_options' ) ) {
           wp_send_json_error( array( 'message' => 'Permission denied.' ) );
       }

       // Process request...

       wp_send_json_success( array( 'message' => 'Success!' ) );
   }
   ```

## Release Workflow

### Version Bump

1. Update version in plugin header
2. Update version constant
3. Update changelog in readme.txt

### Create ZIP

```bash
# From parent directory
zip -r plugin-slug.zip plugin-slug \
    -x "*.git*" \
    -x "*CLAUDE.md" \
    -x "*PLAN.md" \
    -x "*.DS_Store"
```

### Upload to JezPress

```bash
jezpress plugins preflight plugin-slug ./plugin-slug.zip
jezpress plugins upload plugin-slug ./plugin-slug.zip
```

### ZIP Structure (Critical)

```
plugin-slug.zip
└── plugin-slug/                    ← Folder MUST match slug
    ├── plugin-slug.php             ← Main file MUST be slug.php
    ├── includes/
    ├── assets/
    ├── languages/
    ├── readme.txt
    └── uninstall.php
```

## MCP Tools (if connected)

Ask Claude: "Get the JezPress platform guide" for full documentation.

## Google Chat Notifications

When asked to "send release to Google Chat" or "notify Google Chat about release", build the cardsV2 payload below and POST it to the Jezweb release webhook.

**Webhook URL source.** The URL is stored in user-level memory at `~/.claude/memory/jezpress-google-chat-webhook.md`. **Never paste the URL into any committed file** — these repos are public on GitHub and anyone reading the file can post (spam, phishing) into the Jezweb space. The "do not commit" rule is hard.

**Auto-sent on every release (policy 2026-06-17 — no per-release approval).** Run the full release pipeline first (tag → workflow → JezPress upload → changelog CLI → dashboard tab patches), then post the card automatically. The `GCHAT_WEBHOOK_URL` GitHub repo secret stays UNSET so the GitHub workflow never double-fires — the card is posted from the `/release` flow instead.

**Threaded, one parent per plugin per day.** The card is posted with a per-plugin date threadKey (`jezpress-woo-brand-categories-<Sydney date>`) and `messageReplyOption=REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD`. The first release of the day creates the parent card; later releases that day post as **replies in that thread**. The date string is the only state — no message ID to track.

### How to Send Release Notification

```bash
# Source the webhook from user-level memory at run time. Never commit the URL.
WEBHOOK="$(grep -oP 'https://chat\.googleapis\.com/\S+' ~/.claude/memory/jezpress-google-chat-webhook.md | head -n1)"
VERSION="X.Y.Z"  # ← set this to the version being announced

# Per-plugin date threadKey (Australia/Sydney): first release of the day = parent card,
# later releases that day = replies under it.
THREAD_KEY="jezpress-woo-brand-categories-$(TZ='Australia/Sydney' date +%Y-%m-%d)"

# CHANGELOG: paste the plain-text bullets from readme.txt's `= X.Y.Z =` block,
# joined with `<br>` (Google Chat textParagraph treats HTML <br> as a line break).
CHANGELOG="Initial release.<br>- Feature one.<br>- Feature two."

cat > /tmp/jpwbc-gchat.json <<JSON
{
  "thread": { "threadKey": "${THREAD_KEY}" },
  "cardsV2": [{
    "cardId": "release-jezpress-woo-brand-categories-${VERSION}",
    "card": {
      "header": {
        "title": "🚀 JezPress Woo Brand Categories",
        "subtitle": "Version ${VERSION} Released"
      },
      "sections": [
        {
          "header": "What's New",
          "widgets": [
            { "textParagraph": { "text": "${CHANGELOG}" } }
          ]
        },
        {
          "widgets": [
            {
              "buttonList": {
                "buttons": [
                  { "text": "GitHub Release", "onClick": { "openLink": { "url": "https://github.com/JezwebTeam/jezpress-woo-brand-categories/releases/tag/v${VERSION}" } } },
                  { "text": "View Plugin",    "onClick": { "openLink": { "url": "https://admin.jezpress.com/dashboard/plugins/jezpress-woo-brand-categories" } } },
                  { "text": "Download Latest","onClick": { "openLink": { "url": "https://admin.jezpress.com/api/update-server/plugins/jezpress-woo-brand-categories/download?version=${VERSION}" } } }
                ]
              }
            }
          ]
        }
      ]
    }
  }]
}
JSON

# messageReplyOption is a QUERY param; the webhook URL already has ?key=&token=, so append with &.
curl --fail-with-body -s -X POST "${WEBHOOK}&messageReplyOption=REPLY_MESSAGE_FALLBACK_TO_NEW_THREAD" \
  -H 'Content-Type: application/json; charset=UTF-8' \
  --data-binary @/tmp/jpwbc-gchat.json

rm -f /tmp/jpwbc-gchat.json
```

The `JezPress Woo Brand Categories`, `jezpress-woo-brand-categories`, `jpwbc` tokens get substituted to literal values during the scaffold's placeholder pass — so this block lands in every new plugin's CLAUDE.md pre-customised for that plugin.

### If the webhook is rotated

Update only `~/.claude/memory/jezpress-google-chat-webhook.md`. Every plugin's CLAUDE.md reads the URL from there at run time, so no per-plugin edits are needed.

## Full Docs

Run `jezpress docs` for complete platform guide with troubleshooting.

## Testing Checklist

### Before Release

- [ ] Plugin activates without errors
- [ ] Plugin deactivates without errors
- [ ] Settings page loads correctly
- [ ] Settings save and persist
- [ ] License activation works
- [ ] License deactivation works
- [ ] License status displays correctly
- [ ] Update check works (Check for updates link)
- [ ] JezPress Manager integration works (if manager active)
- [ ] No PHP warnings or notices
- [ ] All admin pages are accessible
- [ ] AJAX requests work correctly
- [ ] Nonces are verified properly
- [ ] Capabilities are checked

### Compatibility

- [ ] Works with latest WordPress
- [ ] Works with PHP 8.1
- [ ] Works without JezPress Manager
- [ ] Works with JezPress Manager

## Troubleshooting

### Plugin Not Appearing in JezPress Manager

1. Check if `JezPress_Manager` class exists
2. Verify registration happens after priority 5
3. Check that slug is not empty

### License Issues

1. Verify API URL is correct (`https://updates.jezpress.com`)
2. Check license key format
3. Verify site URL matches registered domain
4. Clear license data and re-activate

### Update Issues

1. Check transient cache (clear with "Check for updates")
2. Verify plugin slug matches JezPress
3. Check license key is passed to updater
4. Enable WP_DEBUG_LOG and check logs

## References

- **JezPress Manager:** https://github.com/abnercalapiz/jezpress-manager
- **JezPress Woo Mailchimp:** https://github.com/abnercalapiz/jezpress-woo-mailchimp
- **JezPress Admin:** https://admin.jezpress.com
- **JezPress CLI:** `npm install -g @jezweb/jezpress-cli`
- **Support:** jez@jezweb.net
