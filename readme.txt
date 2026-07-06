=== JezPress Woo Brand Categories ===
Contributors: jezweb
Tags: woocommerce, product brand, brand archive, product categories, seo
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 9.6
WC tested up to: 10.8
Stable tag: 1.18.1
License: GPL-2.0+
License URI: https://www.gnu.org/licenses/gpl-2.0.html

In-brand product-category navigation and clean brand+category URLs for WooCommerce brand archives.

== Description ==

JezPress Woo Brand Categories adds an in-brand product-category dropdown and SEO-friendly clean URLs to WooCommerce brand archives.

On a brand archive (for example `/brands/dmc/`), visitors can narrow the listing to a specific product category *within that brand* — for instance jumping from the DMC brand page straight to "DMC Pre Cut Fabrics" or "DMC Needles". The dropdown lists only the categories the brand actually has published products in, each with a live product count.

= Features =

* In-brand category dropdown rendered via shortcode `[jpwbc_brand_categories]`, an Elementor widget, or an automatic hook before the brand archive product loop.
* Clean, indexable combo URLs: `/{brand-base}/{brand}/{category}/` (the brand base is taken from your live `product_brand` permalink).
* Filtered archive: a combo URL shows only that brand's products in that category, respecting the existing ordering, pagination and catalog visibility.
* Accurate per-category counts from a single grouped query — no heavy per-request product loading — cached and Redis-friendly.
* Selective indexing: combos at or above a configurable product threshold are indexable with a self-canonical; thinner combos render but are set to `noindex,follow`; empty combos 404.
* Rank Math integration: titles, meta descriptions, canonicals, breadcrumbs and the H1 for combo pages; legacy `?product_cat=` hits canonicalise to the clean URL.
* Optional brand search box; every brand in the list is expandable to reveal its categories inline (lazy-loaded).
* Combo Preview admin tab (per-brand categories, counts, generated URLs, indexing status) and a Cache tab with object-cache status and a one-click rebuild.
* Style tab with colour pickers (active/highlight, toggle chevron, hover accent) — no CSS editing required.
* Trending Brands widget/shortcode — shows the most-clicked brands (click-tracked, cache-safe), with a sensible fallback so it's never empty.
* All Brands (A-Z) widget/shortcode — an alphabet jump-index plus brands grouped under a heading per letter (empty letters are never shown as lonely rows).

= Requirements =

* WordPress 6.4 or higher
* PHP 8.1 or higher
* WooCommerce 9.6 or higher (native `product_brand` taxonomy)
* A valid JezPress license key

= JezPress Manager Integration =

This plugin integrates with [JezPress Manager](https://github.com/JezwebTeam/jezpress-manager) for centralized license management and automatic updates.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/jezpress-woo-brand-categories`, or install through the WordPress plugins screen.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to JezPress Manager > Brand Categories (or the top-level Brand Categories menu) and enter your license key in the License tab.
4. On the Settings tab, choose how the dropdown is placed (shortcode, automatic, or Elementor widget) and configure the options.
5. Drop the `[jpwbc_brand_categories]` shortcode or the "Brand Categories" Elementor widget into your brand archive sidebar. Re-save Permalinks once after enabling clean URLs.

== Frequently Asked Questions ==

= Where do I get a license key? =

License keys are provided when you purchase the plugin from JezPress. Contact jez@jezweb.net if you need assistance.

= Why is a brand+category page showing "noindex"? =

Pages with fewer than the configured "Index threshold" number of products are intentionally set to `noindex,follow` to avoid thin/duplicate pages. Lower the threshold on the Indexing & SEO tab if you want more combos indexed.

= Do the category links work without JavaScript? =

Yes. Every category is a real link to a clean URL. JavaScript only adds expand/collapse, the brand search box and lazy loading of other brands.

= Does it work with my theme / page builder? =

The dropdown inherits your theme's styles. It ships with a shortcode, an Elementor widget and an automatic hook so it can be placed in most contexts, including Elementor Theme Builder archive templates.

== Screenshots ==

1. The in-brand category dropdown on a brand archive sidebar.
2. The Settings tab.
3. The Indexing & SEO tab.
4. The Combo Preview tab showing per-brand categories, counts, URLs and indexing status.

== Changelog ==

= 1.18.1 =
* Improve: refined the Brand Filter Bar styling to match a modern faceted-filter look — cleaner pill controls with hover/open states, softer rounded dropdown panels, custom circular checkboxes for attribute options, and lighter per-option counts.

= 1.18.0 =
* New: the Price filter now has a dual-handle range slider (with min/max input boxes and an Apply button), matching modern shop filters. The slider is bounded by the brand's actual price range and stays in sync with the boxes; it's a progressive enhancement over the plain inputs (which still work without JavaScript).
* Improve: filter-bar styling polished (rounded inputs, full-width Apply) to better match a modern faceted-filter look.

= 1.17.1 =
* Fix: filters (attribute facets + price) now actually filter the product grid when the archive uses Elementor's "Products (Current Query)" widget. That widget runs its own query via WooCommerce's product-shortcode renderer rather than the page's main query, so the filters weren't being applied to it. They now hook that query too (via `woocommerce_shortcode_products_query` and the product lookup join), while still working with themes that use the native archive loop.

= 1.17.0 =
* New: optional value normalisation for attribute facets. Turn on "Fold colour values into base colours" (Brand Categories → Cache) and the index groups messy per-product colours ("1 WHITE", "101 WHITE", "320 DARK NAVY") into a tidy palette (White, Navy, …) so the Colour facet is clean and usable. Off by default; a rebuild applies it. Fully customisable via the `jpwbc_af_normalize_value` filter (map any raw value to any canonical label, for colour, size or any attribute).

= 1.16.1 =
* Fix: the price filter could show extra (empty) pagination pages on brands with variable products. Price filtering now uses WooCommerce's product lookup table (one row per product) instead of a `_price` meta query (which joined one row per variation and inflated the page count).

= 1.16.0 =
* New: only one filter dropdown is open at a time (accordion) — opening one closes the others.
* Change: in AJAX mode the redundant "Apply" (attribute facets) and "Go" (sort) buttons are hidden — those apply instantly on change; the Price "Apply" stays (so typing a range doesn't fire early).
* Improve: the AJAX product-grid selector now falls back through common WooCommerce/Elementor containers (e.g. Elementor's Products widget), so AJAX engages without configuration on more themes.
* New: the attribute index shows a live progress percentage + bar on the Cache tab while a rebuild runs.

= 1.15.0 =
* New: AJAX filtering for the Brand Filter Bar. Selecting a category, price, sort or attribute facet now refreshes the product grid in place (no full page reload), with the URL kept in sync (shareable + back-button friendly) and the filter bar counts/active states updated live. It's a progressive enhancement — with JavaScript off (or if the grid can't be located) it falls back to the existing query-param page loads. New "AJAX filtering" toggle + "Product grid CSS selector" (default `ul.products`) on the widget; `[jpwbc_brand_filter]` gains ajax and results atts.

= 1.14.0 =
* New: dynamic attribute facets (Colour, Style, …) in the Brand Filter Bar. The plugin indexes the store's *custom* (non-taxonomy) variation attributes — including those written by an API sync — into filterable, per-brand facets with product counts, without changing the source data. Each brand shows only the attributes its own products use. Multi-select, query-param based, and noindexed like the other filters.
* New: attribute index rebuild control under JezPress > Brand Categories > Cache (also on product save, a background sweep, and `wp jpwbc-attr-index rebuild`) so the facets self-heal after a sync.
* The Brand Filter Bar widget gains "Show attribute filters" + an optional "Limit to attributes" allowlist; `[jpwbc_brand_filter]` gains show_attributes and attributes atts.

= 1.13.0 =
* New: "Brand Filter Bar" Elementor widget + `[jpwbc_brand_filter]` shortcode — a Myer/Radley-style filter + sort bar for brand archives. Category (via the clean combo URLs, indexable), Price (min/max, applied to the archive product query), and Sort by (Recommended / Price / Recently Added / Most Popular, using WooCommerce's native ordering). Query-param based (reload, no AJAX) so it's cache-friendly; price/sort-filtered views are set to noindex,follow. Works without JavaScript; the Sort control auto-submits when JS is on.

= 1.12.0 =
* New: "Brand Category Filter (chips)" Elementor widget + `[jpwbc_brand_chips]` shortcode — shows the current brand's product categories as a horizontal row of pills (Myer-style), each linking to the clean `/{brand}/{category}/` combo URL, with the active category highlighted and an optional "All {brand}" chip and per-chip product counts. Full Style controls (typography, gap, padding, radius, border, and Normal/Hover/Active colours).

= 1.11.0 =
* New (All Brands A-Z): "Brand list area padding" Style control (responsive top/right/bottom/left) — add padding around the grouped brand list below the alphabet bar.

= 1.10.0 =
* New (All Brands A-Z): "Gap below letter heading" Style control (responsive slider, px/em) — adjust the space between each letter heading (and its underline) and the brand list below it.

= 1.9.0 =
* New (All Brands A-Z): "Sticky heading + alphabet bar" option — pins the heading and A-Z letter bar to the top of the viewport while the brand list scrolls underneath (like the Myer brands page). Includes a "Sticky top offset" control to clear a sticky site header.

= 1.8.0 =
* New (All Brands A-Z): Style controls for the "View all" link — link colour, hover colour and typography (font family, size, weight, etc.), matching the controls already available for the heading, letters and brand list.

= 1.7.0 =
* New (All Brands A-Z): "Letters link to (Brands page URL)" option — make each A-Z letter link to your dedicated Brands page and jump straight to that letter. Ideal for a menu/mega-menu where the letter grid is shown without the brand lists.
* Change: the per-letter section anchors are now stable (e.g. `#jpwbc-az-b` instead of `#jpwbc-az-2-b`), so cross-page letter links from another widget always land on the right section.

= 1.6.0 =
* New (All Brands A-Z): "Alphabet layout" option — choose between the column grid and an inline row of boxed letters (like the Myer brands page).

= 1.5.0 =
* New (All Brands A-Z): a full brands-directory layout — put the brand names under each letter into 1-4 columns, show a per-letter count (e.g. "C (104)"), and add a live brand search box. Ideal for a dedicated "Brands" page.
* New: "Brand link hover colour" control on the Trending Brands and All Brands (A-Z) widgets.

= 1.4.0 =
* New: full Typography control (font family, size, weight, line-height, transform) on the Trending Brands and All Brands (A-Z) widgets — for the heading, the letters, and the brand lists. Replaces the previous font-size-only sliders.
* Change: the Trending Brands list no longer shows bullet markers (matches a clean brand list); the same applies to the per-letter brand lists.

= 1.3.0 =
* New (All Brands A-Z): choose the letter-grid column count (3, 4, 5 or 6; default 5).
* New (All Brands A-Z): "Show brand list under each letter" toggle — turn it off to show just the A-Z letter grid.
* New: Elementor Style controls on the Trending Brands and All Brands (A-Z) widgets — heading font size, list font size, and colours (heading, letters, brand links).

= 1.2.0 =
* New: "Trending Brands" Elementor widget + `[jpwbc_trending_brands]` shortcode — shows the most-clicked brands. Clicks on brand links are tracked (works with page caching); before clicks accrue it falls back to the largest brands so it's never blank.
* New: "All Brands (A-Z)" Elementor widget + `[jpwbc_all_brands]` shortcode — an alphabet jump-index plus brands grouped under a heading per letter (each letter on its own line; empty letters aren't shown), with an optional "View all" link.
* Both new widgets inherit the Style-tab colours.

= 1.1.0 =
* New: a "Style" tab with colour pickers for the active/highlight colour, the toggle chevron, and the hover accent. Colours are applied on the front end as CSS variables, so you can match your theme without editing any CSS.

= 1.0.6 =
* Fix: the brand expand/collapse toggle button no longer inherits theme padding or borders, so the chevron stays correctly sized and aligned across themes.

= 1.0.5 =
* Change: every brand in the list is now expandable by default. The "Other brands clickable" toggle has been removed — it is built-in behaviour, so there is nothing to configure.

= 1.0.4 =
* Fix: the real cause of settings not saving — the sanitiser merged per-tab and could run more than once per save, and the second pass reverted the change. The merge now happens once in the save handler and the sanitiser is pure/idempotent, so saves always persist.
* Change: "Other brands clickable" now defaults to on, so every brand in the list is expandable out of the box.

= 1.0.3 =
* Fix: settings still would not save on sites whose firewall/security layer strips underscore-prefixed form fields. The active tab is now sent as a normal top-level field, so saves persist reliably.

= 1.0.2 =
* Fix: settings now save reliably even on sites where another plugin filters the WordPress options whitelist (the form no longer depends on options.php). Added a "Settings saved" confirmation.

= 1.0.1 =
* Fix: a fatal error (TypeError) on every front-end page when Rank Math is active, caused by the canonical filter receiving a non-string value. The plugin no longer assumes Rank Math passes a string canonical.

= 1.0.0 =
* Initial release.
* In-brand category dropdown (shortcode, Elementor widget, automatic hook).
* Clean, indexable `/{brand}/{category}/` combo URLs with selective indexing.
* Cached grouped category query with per-category counts.
* Rank Math SEO integration (titles, descriptions, canonicals, breadcrumbs, noindex for thin combos).
* Combo Preview and Cache admin tabs.

== Upgrade Notice ==

= 1.18.1 =
Visual polish for the Brand Filter Bar (cleaner pills, panels and circular checkboxes). Hard-refresh once so the new styles load.

= 1.18.0 =
Adds a dual-handle price range slider to the Brand Filter Bar. Hard-refresh once so the new script/styles load.

= 1.17.1 =
Fixes filters not affecting the grid on archives built with Elementor's "Products (Current Query)" widget.

= 1.17.0 =
Adds optional colour-value normalisation for the attribute facets (fold "101 WHITE" → "White"). Enable it on the Cache tab and rebuild the index.

= 1.16.1 =
Fixes extra/empty pagination pages when filtering by price on brands with variable products.

= 1.16.0 =
Filter UX: one dropdown open at a time, redundant Apply/Go buttons hidden in AJAX mode, more robust AJAX grid detection, and an index progress bar. Hard-refresh once so the new script loads.

= 1.15.0 =
Adds AJAX (no-reload) filtering to the Brand Filter Bar. If your archive template uses a custom product container, set the "Product grid CSS selector" on the widget.

= 1.14.0 =
Adds dynamic Colour/Style attribute facets to the Brand Filter Bar (built from custom variation attributes via a self-healing index). After updating, rebuild the index under Brand Categories > Cache.

= 1.13.0 =
Adds a "Brand Filter Bar" widget/shortcode (Category + Price + Sort) for brand archives, query-param based with noindex on filtered views.

= 1.12.0 =
Adds a "Brand Category Filter (chips)" widget/shortcode — the current brand's categories as a Myer-style horizontal pill filter.

= 1.11.0 =
Adds a "Brand list area padding" Style control to the All Brands (A-Z) widget.

= 1.10.0 =
Adds a "Gap below letter heading" Style control to the All Brands (A-Z) widget.

= 1.9.0 =
Adds a "Sticky heading + alphabet bar" option (with top-offset control) to the All Brands (A-Z) widget, Myer-style.

= 1.8.0 =
Adds "View all" link Style controls (colour, hover colour, typography) to the All Brands (A-Z) widget.

= 1.7.0 =
Adds a "Letters link to" option so the A-Z letter grid (e.g. in a menu) can link to your Brands page at each letter. Section anchors are now stable (#jpwbc-az-b); if you hard-coded an old #jpwbc-az-N-x anchor anywhere, update it.

= 1.6.0 =
Adds an "Alphabet layout" option to the All Brands (A-Z) widget: column grid or an inline row of boxed letters.

= 1.5.0 =
Adds a full brands-directory layout (multi-column lists, per-letter counts, brand search) and a link hover colour control.

= 1.4.0 =
Adds full Typography (font family + size) controls to the brand widgets and removes the Trending list bullets. If you set a font size in 1.3.0, re-set it under the new Typography control.

= 1.3.0 =
Adds column control + a list on/off toggle + Elementor Style controls (font sizes, colours) to the brand widgets.

= 1.2.0 =
Adds Trending Brands and All Brands (A-Z) Elementor widgets/shortcodes, with click-tracked trending.

= 1.1.0 =
Adds a Style tab to control the dropdown colours from the admin (no CSS editing).

= 1.0.6 =
Minor CSS fix so the brand toggle button isn't restyled by the theme.

= 1.0.5 =
All brands are now expandable by default; the "Other brands clickable" setting is removed (no longer needed).

= 1.0.4 =
Fixes the underlying settings-save bug (non-idempotent sanitiser reverting the save) and makes all brands clickable by default.

= 1.0.3 =
Fixes settings not saving on sites whose security layer strips underscore-prefixed fields. Completes the 1.0.2 save fix.

= 1.0.2 =
Fixes settings not saving on sites with role/security plugins that filter the options whitelist.

= 1.0.1 =
Critical fix: resolves a site-wide fatal error on front-end pages when Rank Math is active. Upgrade immediately.

= 1.0.0 =
Initial release.
