# PLAN.md - JezPress Woo Brand Categories

**Plugin name:** JezPress Woo Brand Categories
**Slug:** `jezpress-woo-brand-categories`
**Prefix:** `jpwbc`
**Text domain:** `jezpress-woo-brand-categories`
**Requires PHP:** 8.1
**Requires WP:** 6.4
**Requires WooCommerce:** 9.6+ (native `product_brand` taxonomy)
**Tested to:** WooCommerce 10.8.x
**Licence-gated:** Yes (JezPress Manager + JezPress update server)

**Ticket:** ISS-2026-01253 - Brand Pages / Categories (Birch Wholesale, High priority)
**Target site:** birchcreative.com.au (Rocket.net site ID 7554)

---

## 1. What the client wants

On a brand archive (e.g. `/brands/dmc/`) the visitor should be able to narrow to a
specific product category *within that brand* - e.g. from the DMC brand page, drill
into "DMC Pre Cut Fabrics" or "DMC Needles". Today the brand archive shows every DMC
product (498) with no in-brand category navigation.

## 2. Agreed UI direction (confirmed via mockups)

The in-brand categories appear as an **inline dropdown under the active brand** in the
existing "Our Brands" sidebar:

- The brand list stays as it is now (full A-Z list of all brands).
- The brand the visitor is currently on (e.g. DMC) is expanded, showing its categories
  nested beneath it with product counts.
- Clicking the brand name collapses/expands the dropdown (chevron rotates).
- An "All {Brand} products" reset link sits at the top of the dropdown.
- Clicking a category navigates to that brand+category view.
- Look-and-feel matches the live site (grey sidebar, pink active state `#9c5468` /
  `#c98a9b`, JezPress teal `#14b8a6` as a light accent).

Two front-end mockups and one backend (admin) mockup have been produced and approved as
the visual reference.

### Open UI choices (confirm with Marianne)
- Only the **current** brand expands (default, lighter), or **any** brand clickable to
  expand its own categories inline?
- Include the small **brand search box** added in the mockup, or keep the sidebar pure
  to the original?

## 3. Live-site facts (verified on site 7554)

- **WooCommerce 10.8.1** - native `product_brand` taxonomy present and in use.
- **180** `product_brand` terms, **195** `product_cat` terms.
- Brand archive URL base: `/brands/` (e.g. `/brands/dmc/`).
- **SEO plugin: Rank Math** (not Yoast) - canonical/robots/breadcrumbs go through
  Rank Math filters.
- **Object Cache Pro** active (Redis) - transient caching is Redis-backed and cheap.
- **Page builder: Elementor + Elementor Pro.** The brand archive is rendered by the
  Elementor Theme Builder template **"Product Archive - Brand" (post ID 3529)**.

### How the current sidebar is built (important)
The "Our Brands" list is **NOT a shortcode**. It is an Elementor **Sitemap widget**
inside template 3529, configured as:
- `sitemap_type_selector: taxonomy`
- `sitemap_source_taxonomy: product_brand`
- `sitemap_title: "Our Brands"`
- CSS class: `brands`, in a 29% / 71% two-column section (sidebar / product grid).

A Sitemap widget can only output a flat term list - it has no concept of in-brand
categories or active/expanded state. **Therefore the integration is: replace that
Sitemap widget with our plugin's output** (shortcode or Elementor widget) dropped into
the same sidebar column of template 3529.

## 4. Decision: SEO-indexable clean URLs (hybrid), not query params

**Chosen: clean rewrite URLs as canonical, with selective indexing.**

Why not `?product_cat=` params: Rank Math/Google treat parameter URLs as thin/duplicate;
they rarely rank and don't accumulate link equity.

Why clean URLs need guardrails: 180 brands x 195 categories = up to ~35,000 theoretical
combos. Auto-indexing all of them risks thin/doorway pages and wasted crawl budget.

**Hybrid rule:**
- Canonical URL format: `/brands/{brand}/{category}/`
- A combo is **indexable** only if it has >= `INDEX_MIN_PRODUCTS` products (default 4).
- Thin combos (1-3) still render and work, but get `noindex,follow`.
- Empty combos -> hidden from the dropdown and 404 if hit directly.
- Each indexable combo gets a unique H1 + short templated intro so pages aren't identical.
- Any legacy `?product_cat=` hit canonicalises to the matching clean URL.

## 5. URL & rewrite design

- Rewrite rule mapping `brands/{brand}/{category}/` onto the `product_brand` archive
  with an added internal var.
  Conceptual: `^brands/([^/]+)/([^/]+)/?$` -> `index.php?product_brand=$1&jpwbc_cat=$2`
- Register `jpwbc_cat` via the `query_vars` filter.
- Flush rewrite rules on activation/deactivation only.
- If `{category}` is not a valid `product_cat` slug, fall back to the normal brand
  archive (or 404) rather than forcing a match.

## 6. Query layer

### 6a. In-brand categories
- `jpwbc_get_brand_categories( $brand_term_id )` returns the `product_cat` terms that
  have >= 1 published product also in the brand, each with a product count.
- Implementation: single term-relationship join (brand term -> products -> their
  product_cat terms -> counts). Avoid loading full product objects.
- Cache: transient `jpwbc_brand_cats_{brand_id}` (Redis-backed), TTL 12h.
- Bust on `save_post_product`, `set_object_terms` (product_brand / product_cat),
  product trash/delete, and a manual "Rebuild caches" admin button.

### 6b. Filtered archive loop
- On a clean combo URL, `pre_get_posts` adds an AND `tax_query` clause for the
  `product_cat` term alongside the existing `product_brand` archive query.
- Respect existing ordering, pagination and catalog visibility.
- Note: the product grid is an Elementor "woocommerce-products" widget set to
  `current_query`, so filtering the main query feeds it correctly with no widget change.

## 7. Front-end output (the dropdown)

- Primary integration: **shortcode** `[jpwbc_brand_categories]` placed in the sidebar
  column of template 3529, replacing the Sitemap widget.
- Also ship an **Elementor widget** ("JezPress > Brand Categories") for drag-in placement.
- Optional auto-hook fallback: `woocommerce_before_shop_loop` (priority ~5) for
  non-Elementor contexts. Guard against double-render.
- Markup: full brand A-Z list; the current brand rendered open with its categories
  nested (count on the right, "All {Brand} products" reset on top).
- Behaviour (small enqueued JS): expand/collapse the active brand; optional brand search;
  active-category highlight. Category links are real `<a>` to clean URLs (work without JS).
- CSS: inherit site styles; match grey sidebar + pink active + teal accent. Enqueue only
  on `product_brand` archives.
- Accessibility: real links, `aria-expanded` on the toggle, `aria-current` on active
  category, keyboard focusable, `prefers-reduced-motion` respected.

## 8. SEO integration (Rank Math)

- Indexable combos: set title (`rank_math/frontend/title`), description
  (`rank_math/frontend/description`), self-canonical (`rank_math/frontend/canonical`),
  H1 "{Brand} - {Category}".
- Thin combos: force `noindex,follow` (`rank_math/frontend/robots`).
- `?product_cat=` param hits: canonical -> clean combo URL.
- Breadcrumbs: Home > Brands > {Brand} > {Category} via Rank Math breadcrumb filter.
- Phase 2 option: feed indexable combos into the Rank Math sitemap; exclude thin ones.

## 9. Admin / settings (mockup approved)

Settings page under the JezPress menu ("Brand Categories"), tabs:
- **Settings:** enable toggle; placement (shortcode / auto-hook / Elementor widget);
  expand-active-by-default; other-brands-clickable; show product counts; brand-search on/off.
- **Indexing & SEO:** clean-URL toggle; `INDEX_MIN_PRODUCTS` threshold (default 4);
  title template; intro-text template (tokens `{brand}` `{category}` `{count}`).
- **Combo preview:** live table per brand showing each category, product count, generated
  URL, and SEO status (Indexed / No-index / Hidden).
- **Cache:** Redis status + last-rebuilt time + "Rebuild caches" button.
- Licence check via JezPress Manager; feature gated behind a valid licence.

## 10. Architecture (JezPress template conformance)

- Built from `wp-plugin-template`; `Jpwbc\` namespace, autoloaded classes.
- Files:
  - `jezpress-woo-brand-categories.php` (bootstrap, guards, constants)
  - `includes/class-jpwbc-rewrites.php`
  - `includes/class-jpwbc-query.php`
  - `includes/class-jpwbc-cache.php`
  - `includes/class-jpwbc-frontend.php` (shortcode, enqueue, markup)
  - `includes/class-jpwbc-elementor-widget.php`
  - `includes/class-jpwbc-seo-rankmath.php`
  - `includes/class-jpwbc-admin.php`
  - `includes/class-jpwbc-license.php` (JezPress Manager glue)
  - `assets/css/jpwbc.css`, `assets/js/jpwbc.js`
  - `readme.txt`, `PLAN.md`
- Guard on boot: WooCommerce active + `product_brand` exists; admin notice + safe bail otherwise.
- HPOS-compatible (no order-table access).

## 11. Build phases

1. **Scaffold** from template; constants, guards, activation flush.
2. **Query + cache** layer; verify counts against known DMC categories.
3. **Rewrites + pre_get_posts** filtering; confirm clean URLs render correct products.
4. **Front-end dropdown** (shortcode + Elementor widget + JS/CSS); place into template 3529.
5. **Rank Math SEO** layer (titles, canonicals, selective noindex, breadcrumbs).
6. **Admin settings** + licence gating + combo preview + cache rebuild.
7. **QA on staging** (Rocket.net staging for 7554), then publish.

## 12. QA checklist

- `/brands/dmc/` shows the dropdown with only categories that have DMC products + correct counts.
- `/brands/dmc/pre-cut-fabrics/` shows only DMC pre-cut-fabric products, paginated, correctly sorted.
- Invalid category segment -> graceful (brand archive or 404, no white screen).
- Thin combo returns `noindex,follow`; healthy combo is indexable with self-canonical.
- `?product_cat=` param canonicalises to the clean URL.
- No double render when placed in the Elementor template.
- Dropdown works without JS (links resolve); expand/collapse works with JS.
- Cache busts within one product save; manual rebuild works.
- No fatal if WooCommerce deactivated or `product_brand` missing.
- Chip/category query served from Redis (no heavy per-request SQL).
- Sidebar styling matches live; responsive to mobile; keyboard accessible.

## 13. Open questions for Marianne / client

1. Brand archive base stays `/brands/` (confirmed live) - any plan to change the slug?
2. Index threshold: is 4 products the right floor for "worth indexing"? (adjustable)
3. Per-combo intro copy: templated line enough for launch, or do they want hand-written intros for top brands?
4. UI: only the current brand expands, or any brand clickable? Keep the added brand search box?
5. Replace the existing Elementor Sitemap "Our Brands" widget in template 3529 directly, or run side-by-side during QA on staging first?

## 14. Rough effort (build only, excludes content)

- Scaffold + query/cache: ~4-6 h
- Rewrites + loop filter: ~3-4 h
- Front-end dropdown (shortcode + Elementor widget + JS/CSS): ~5-7 h
- Rank Math SEO layer: ~3-4 h
- Admin settings + combo preview + cache: ~4-6 h
- QA on staging + template integration: ~3-5 h
- **Total: ~22-32 h** (Claude Code-assisted; lower end if the template scaffold is clean).

## 15. Reference artefacts

- Front-end mockup (live-matched, with DMC dropdown): `birch-frontend-mockup.html`
- Backend admin mockup: `birch-backend-mockup.html`
- Elementor template carrying the sidebar: "Product Archive - Brand", post ID 3529.
