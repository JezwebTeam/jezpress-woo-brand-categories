=== JezPress Woo Brand Categories ===
Contributors: jezweb
Tags: woocommerce, product brand, brand archive, product categories, seo
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
WC requires at least: 9.6
WC tested up to: 10.8
Stable tag: 1.0.3
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
* Optional brand search box and lazy-loaded expansion of other brands' categories.
* Combo Preview admin tab (per-brand categories, counts, generated URLs, indexing status) and a Cache tab with object-cache status and a one-click rebuild.

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

= 1.0.3 =
Fixes settings not saving on sites whose security layer strips underscore-prefixed fields. Completes the 1.0.2 save fix.

= 1.0.2 =
Fixes settings not saving on sites with role/security plugins that filter the options whitelist.

= 1.0.1 =
Critical fix: resolves a site-wide fatal error on front-end pages when Rank Math is active. Upgrade immediately.

= 1.0.0 =
Initial release.
