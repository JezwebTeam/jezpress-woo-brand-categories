<?php
/**
 * Brand archive filter bar (Category + Price + Sort).
 *
 * Renders a Myer/Radley-style horizontal filter+sort bar for a brand archive and
 * applies the selections to the archive's main product query. Query-param based
 * (reload, not AJAX) so it is cache-friendly and SEO-clean:
 *
 *   - Category : the existing clean combo URL (/{base}/{brand}/{category}/) —
 *                reuses JPWBC_Rewrites' jpwbc_cat filtering. Indexable.
 *   - Price    : ?jpwbc_min_price / ?jpwbc_max_price — a _price meta clause added
 *                to the brand archive main query here.
 *   - Sort     : ?orderby — WooCommerce's native catalog ordering (applied by
 *                WC_Query on product archives; we only render the control).
 *
 * Any price/sort-filtered view is set to noindex,follow to avoid index bloat;
 * the clean category combo stays indexable (handled by JPWBC_SEO_RankMath).
 *
 * @package JezPress\WooBrandCategories
 * @since   1.13.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Filter bar handler.
 *
 * @since 1.13.0
 */
class JPWBC_Filter {

	/**
	 * Allowlisted WooCommerce catalog orderby keys (value => label built at render).
	 *
	 * @since 1.13.0
	 * @var array<int, string>
	 */
	private const ORDERBY_KEYS = array( 'menu_order', 'popularity', 'date', 'price', 'price-desc' );

	/**
	 * Query handler.
	 *
	 * @since 1.13.0
	 * @var JPWBC_Query
	 */
	private JPWBC_Query $query;

	/**
	 * Rewrite handler (URL builders + brand base).
	 *
	 * @since 1.13.0
	 * @var JPWBC_Rewrites
	 */
	private JPWBC_Rewrites $rewrites;

	/**
	 * Plugin settings.
	 *
	 * @since 1.13.0
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Most-recent instance, for the Elementor widget to reach the renderer.
	 *
	 * @since 1.13.0
	 * @var JPWBC_Filter|null
	 */
	private static ?JPWBC_Filter $instance = null;

	/**
	 * Get the active instance.
	 *
	 * @since 1.13.0
	 *
	 * @return JPWBC_Filter|null
	 */
	public static function instance(): ?JPWBC_Filter {
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.13.0
	 *
	 * @param JPWBC_Query          $query    Query handler.
	 * @param JPWBC_Rewrites       $rewrites Rewrite handler.
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	public function __construct( JPWBC_Query $query, JPWBC_Rewrites $rewrites, array $settings ) {
		$this->query    = $query;
		$this->rewrites = $rewrites;
		$this->settings = $settings;
		self::$instance = $this;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.13.0
	 */
	public function register_hooks(): void {
		add_shortcode( 'jpwbc_brand_filter', array( $this, 'shortcode' ) );
		add_shortcode( 'jpwbc_category_filter', array( $this, 'category_shortcode' ) );
		add_filter( 'posts_clauses', array( $this, 'price_clauses' ), 10, 2 );
		add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ) );
		add_filter( 'rank_math/frontend/robots', array( $this, 'filter_rank_math_robots' ) );
	}

	/* ---------------------------------------------------------------------
	 * Read + sanitize the current filter params (read-only GET navigation).
	 * ------------------------------------------------------------------- */

	/**
	 * Current minimum price filter, or null if unset.
	 *
	 * @since 1.13.0
	 *
	 * @return float|null
	 */
	private function current_min(): ?float {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public navigation filter; value sanitized to a non-negative float.
		if ( ! isset( $_GET['jpwbc_min_price'] ) || '' === $_GET['jpwbc_min_price'] ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return max( 0.0, (float) wp_unslash( $_GET['jpwbc_min_price'] ) );
	}

	/**
	 * Current maximum price filter, or null if unset.
	 *
	 * @since 1.13.0
	 *
	 * @return float|null
	 */
	private function current_max(): ?float {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public navigation filter; value sanitized to a non-negative float.
		if ( ! isset( $_GET['jpwbc_max_price'] ) || '' === $_GET['jpwbc_max_price'] ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return max( 0.0, (float) wp_unslash( $_GET['jpwbc_max_price'] ) );
	}

	/**
	 * Current sort key (allowlisted), or '' for the default (Recommended).
	 *
	 * @since 1.13.0
	 *
	 * @return string
	 */
	private function current_orderby(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public navigation filter; value checked against an allowlist.
		$raw = isset( $_GET['orderby'] ) ? sanitize_key( (string) wp_unslash( $_GET['orderby'] ) ) : '';
		// WooCommerce sends 'price-desc'; sanitize_key strips the hyphen, so re-map.
		if ( 'pricedesc' === $raw ) {
			$raw = 'price-desc';
		}
		return in_array( $raw, self::ORDERBY_KEYS, true ) && 'menu_order' !== $raw ? $raw : '';
	}

	/**
	 * Whether the current request has any price/sort filter applied.
	 *
	 * @since 1.13.0
	 *
	 * @return bool
	 */
	private function is_filtered(): bool {
		return null !== $this->current_min() || null !== $this->current_max() || $this->has_sort_param();
	}

	/**
	 * Whether any non-default sort is applied — including sorts WooCommerce
	 * honours but this widget doesn't list (e.g. rating, rand), so those
	 * duplicate-content views are noindexed too.
	 *
	 * @since 1.13.0
	 *
	 * @return bool
	 */
	private function has_sort_param(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public navigation filter.
		$raw = isset( $_GET['orderby'] ) ? sanitize_key( (string) wp_unslash( $_GET['orderby'] ) ) : '';
		return '' !== $raw && 'menu_order' !== $raw;
	}

	/* ---------------------------------------------------------------------
	 * Query + robots hooks.
	 * ------------------------------------------------------------------- */

	/**
	 * Constrain the brand archive main query to the price range.
	 *
	 * Filters on WooCommerce's wc_product_meta_lookup table (one row per product,
	 * with min_price/max_price columns) via posts_clauses — the same mechanism as
	 * WooCommerce's own price filter. A _price postmeta meta_query would join one
	 * row per variation on variable products, inflating found_posts (and thus the
	 * pagination page count) even though the displayed products are de-duplicated.
	 *
	 * @since 1.16.1
	 *
	 * @param array<string, string> $clauses SQL clauses.
	 * @param \WP_Query              $q       The query.
	 * @return array<string, string>
	 */
	public function price_clauses( $clauses, $q ) {
		if ( ! is_array( $clauses ) || ! $q instanceof \WP_Query ) {
			return $clauses;
		}
		if ( is_admin() || ! jpwbc_woocommerce_ready() ) {
			return $clauses;
		}
		if ( ! is_tax( JPWBC_BRAND_TAXONOMY ) && ! JPWBC_Attr_Index::is_product_archive_context() ) {
			return $clauses;
		}
		// Apply to the archive main query AND the Elementor "Products (current
		// query)" widget's own product query (which is not the main query).
		$post_types = (array) $q->get( 'post_type' );
		if ( ! $q->is_main_query() && ! in_array( 'product', $post_types, true ) ) {
			return $clauses;
		}
		// Hand-picked loops (related products, up-sells, a manual selection) name
		// their posts outright and are not the archive the filter bar controls.
		//
		// Deliberately NOT also skipping no_found_rows queries: that would catch
		// WC's product widgets, but it would equally catch an archive loop whose
		// pagination is turned off — and silently not filtering the grid is a far
		// worse failure than a widget on a filtered page showing filtered items.
		if ( ! $q->is_main_query() && ! empty( $q->get( 'post__in' ) ) ) {
			return $clauses;
		}

		$min = $this->current_min();
		$max = $this->current_max();
		if ( null === $min && null === $max ) {
			return $clauses;
		}
		if ( null !== $min && null !== $max && $min > $max ) {
			$swap = $min;
			$min  = $max;
			$max  = $swap;
		}

		global $wpdb;
		$lookup = $wpdb->prefix . 'wc_product_meta_lookup';

		// Guard: the lookup table is WooCommerce core (3.6+), but bail safely if absent.
		if ( ! isset( $clauses['join'], $clauses['where'] ) ) {
			return $clauses;
		}

		if ( false === strpos( (string) $clauses['join'], 'jpwbc_pl' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table/column identifiers are $wpdb-derived, not user input.
			$clauses['join'] .= " INNER JOIN {$lookup} jpwbc_pl ON {$wpdb->posts}.ID = jpwbc_pl.product_id ";
		}

		// Overlap semantics (as WooCommerce): the product's price range intersects
		// the requested range. One lookup row per product → no row multiplication.
		if ( null !== $min ) {
			$clauses['where'] .= $wpdb->prepare( ' AND jpwbc_pl.max_price >= %f ', $min );
		}
		if ( null !== $max ) {
			$clauses['where'] .= $wpdb->prepare( ' AND jpwbc_pl.min_price <= %f ', $max );
		}

		return $clauses;
	}

	/**
	 * Force noindex,follow on price/sort-filtered brand views (WP core robots).
	 *
	 * @since 1.13.0
	 *
	 * @param array<string, bool|int|string> $robots Robots directives.
	 * @return array<string, bool|int|string>
	 */
	public function filter_wp_robots( array $robots ): array {
		if ( $this->is_filtered_brand_archive() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Force noindex on price/sort-filtered brand views (Rank Math).
	 *
	 * @since 1.13.0
	 *
	 * @param array<string, string> $robots Rank Math robots array.
	 * @return array<string, string>
	 */
	public function filter_rank_math_robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();
		if ( $this->is_filtered_brand_archive() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/**
	 * Whether the current front-end request is a filtered brand archive.
	 *
	 * @since 1.13.0
	 *
	 * @return bool
	 */
	private function is_filtered_brand_archive(): bool {
		if ( ! jpwbc_woocommerce_ready() || ! $this->is_filtered() ) {
			return false;
		}
		return is_tax( JPWBC_BRAND_TAXONOMY ) || JPWBC_Attr_Index::is_product_archive_context();
	}

	/* ---------------------------------------------------------------------
	 * Rendering.
	 * ------------------------------------------------------------------- */

	/**
	 * [jpwbc_brand_filter] shortcode callback.
	 *
	 * @since 1.13.0
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'brand'           => '',
				'show_category'   => 'yes',
				'show_price'      => 'yes',
				'show_sort'       => 'yes',
				'show_attributes' => 'yes',
				'attributes'      => '',
				'ajax'            => 'yes',
				'results'         => 'ul.products',
			),
			is_array( $atts ) ? $atts : array(),
			'jpwbc_brand_filter'
		);

		$truthy = static function ( $v ): bool {
			return 'yes' === $v || '1' === (string) $v || 'true' === $v;
		};

		return $this->render_filter_bar(
			array(
				'brand'           => is_string( $atts['brand'] ) ? $atts['brand'] : '',
				'show_category'   => $truthy( $atts['show_category'] ),
				'show_price'      => $truthy( $atts['show_price'] ),
				'show_sort'       => $truthy( $atts['show_sort'] ),
				'show_attributes' => $truthy( $atts['show_attributes'] ),
				'attributes'      => is_string( $atts['attributes'] ) ? $atts['attributes'] : '',
				'ajax'            => $truthy( $atts['ajax'] ),
				'results_selector' => is_string( $atts['results'] ) ? $atts['results'] : 'ul.products',
			)
		);
	}

	/**
	 * Render the filter bar for the current (or overridden) brand.
	 *
	 * @since 1.13.0
	 *
	 * @param array<string, mixed> $args brand, show_category, show_price, show_sort, labels.
	 * @return string
	 */
	public function render_filter_bar( array $args = array() ): string {
		if ( empty( $this->settings['enabled'] ) || ! jpwbc_woocommerce_ready() ) {
			return '';
		}

		$brand = $this->resolve_brand( isset( $args['brand'] ) ? (string) $args['brand'] : '' );
		if ( ! $brand instanceof \WP_Term ) {
			return '';
		}

		jpwbc_enqueue_frontend_assets();

		$active_cat = sanitize_title( (string) get_query_var( JPWBC_QUERY_VAR ) );
		$brand_url  = $this->rewrites->brand_url( $brand->slug );

		// Current view path — category selections navigate by path; price/sort ride as query params.
		$base_url = ( '' !== $active_cat )
			? $this->rewrites->combo_url( $brand->slug, $active_cat )
			: $brand_url;

		$min     = $this->current_min();
		$max     = $this->current_max();
		$orderby = $this->current_orderby();

		// Params to carry across category links (price + sort + facets), so switching category keeps them.
		$preserve = array();
		if ( null !== $min ) {
			$preserve['jpwbc_min_price'] = $min;
		}
		if ( null !== $max ) {
			$preserve['jpwbc_max_price'] = $max;
		}
		if ( '' !== $orderby ) {
			$preserve['orderby'] = $orderby;
		}

		// Attribute facets (Colour/Style/…) from the indexer, plus their selections.
		$facets     = array();
		$attr_index = JPWBC_Attr_Index::instance();
		if ( $attr_index instanceof JPWBC_Attr_Index ) {
			foreach ( $attr_index->selected_facets() as $fkey => $fslugs ) {
				$preserve[ 'jpwbc_af_' . $fkey ] = $fslugs;
			}
			$show_attrs = ! isset( $args['show_attributes'] ) || ! empty( $args['show_attributes'] );
			if ( $show_attrs ) {
				$allow  = array();
				$raw    = isset( $args['attributes'] ) ? (string) $args['attributes'] : '';
				if ( '' !== $raw ) {
					$allow = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) );
				}
				$facets = $attr_index->get_brand_facets( (int) $brand->term_id, $allow );
			}
		}

		$categories = array();
		foreach ( $this->query->get_brand_categories( (int) $brand->term_id ) as $cat ) {
			$categories[] = array(
				'name'      => $cat['name'],
				'slug'      => $cat['slug'],
				'count'     => (int) $cat['count'],
				'url'       => $this->rewrites->combo_url( $brand->slug, $cat['slug'] ),
				'is_active' => $cat['slug'] === $active_cat,
			);
		}

		$data = array(
			'settings'      => $this->settings,
			'brand'         => array(
				'term_id' => (int) $brand->term_id,
				'name'    => $brand->name,
				'slug'    => $brand->slug,
				'url'     => $brand_url,
			),
			'categories'    => $categories,
			'facets'        => $facets,
			'active_cat'    => $active_cat,
			'base_url'      => $base_url,
			'preserve'      => $preserve,
			'price_range'   => $this->get_brand_price_range( (int) $brand->term_id ),
			'current_min'   => $min,
			'current_max'   => $max,
			'current_sort'  => $orderby,
			'sort_options'  => $this->sort_options(),
			'show_category'    => ! isset( $args['show_category'] ) || ! empty( $args['show_category'] ),
			'show_price'       => ! isset( $args['show_price'] ) || ! empty( $args['show_price'] ),
			'show_sort'        => ! isset( $args['show_sort'] ) || ! empty( $args['show_sort'] ),
			'ajax'             => ! isset( $args['ajax'] ) || ! empty( $args['ajax'] ),
			'results_selector' => isset( $args['results_selector'] ) && '' !== (string) $args['results_selector'] ? (string) $args['results_selector'] : 'ul.products',
			'is_filtered'      => $this->is_filtered(),
		);

		return jpwbc_get_template( 'brand-filter-bar.php', $data );
	}

	/**
	 * [jpwbc_category_filter] shortcode callback.
	 *
	 * @since 1.20.0
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 * @return string
	 */
	public function category_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'category'         => '',
				'show_category'    => 'yes',
				'show_price'       => 'yes',
				'show_sort'        => 'yes',
				'show_attributes'  => 'yes',
				'attributes'       => '',
				'ajax'             => 'yes',
				'results'          => 'ul.products',
			),
			is_array( $atts ) ? $atts : array(),
			'jpwbc_category_filter'
		);

		$truthy = static function ( $v ): bool {
			return in_array( strtolower( (string) $v ), array( '1', 'yes', 'true', 'on' ), true );
		};

		return $this->render_category_filter_bar(
			array(
				'category'         => (string) $atts['category'],
				'show_category'    => $truthy( $atts['show_category'] ),
				'show_price'       => $truthy( $atts['show_price'] ),
				'show_sort'        => $truthy( $atts['show_sort'] ),
				'show_attributes'  => $truthy( $atts['show_attributes'] ),
				'attributes'       => (string) $atts['attributes'],
				'ajax'             => $truthy( $atts['ajax'] ),
				'results_selector' => (string) $atts['results'],
			)
		);
	}

	/**
	 * Render the Product Category Filter Bar.
	 *
	 * Same controls as the brand bar, but the Category dropdown lists the whole
	 * product_cat tree: top-level categories as rows, each expandable in place
	 * to reveal its children. Selecting a category navigates to WooCommerce's
	 * own category archive URL, so no extra rewrite rules or indexing rules are
	 * needed — those archives are already canonical.
	 *
	 * @since 1.20.0
	 *
	 * @param array<string, mixed> $args category, show_*, attributes, ajax, results_selector.
	 * @return string
	 */
	public function render_category_filter_bar( array $args = array() ): string {
		if ( empty( $this->settings['enabled'] ) || ! jpwbc_woocommerce_ready() ) {
			return '';
		}
		if ( ! taxonomy_exists( JPWBC_CAT_TAXONOMY ) ) {
			return '';
		}

		jpwbc_enqueue_frontend_assets();

		// Current category: an explicit slug wins, otherwise the archive being viewed.
		$current = null;
		$forced  = isset( $args['category'] ) ? sanitize_title( (string) $args['category'] ) : '';
		if ( '' !== $forced ) {
			$term = get_term_by( 'slug', $forced, JPWBC_CAT_TAXONOMY );
			if ( $term instanceof \WP_Term ) {
				$current = $term;
			}
		} elseif ( is_tax( JPWBC_CAT_TAXONOMY ) ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Term ) {
				$current = $queried;
			}
		}

		$shop_url  = function_exists( 'wc_get_page_permalink' ) ? (string) wc_get_page_permalink( 'shop' ) : home_url( '/' );
		$base_url  = $current instanceof \WP_Term ? (string) get_term_link( $current ) : $shop_url;
		$base_url  = is_string( $base_url ) ? $base_url : $shop_url;
		$active    = $current instanceof \WP_Term ? (string) $current->slug : '';

		$min     = $this->current_min();
		$max     = $this->current_max();
		$orderby = $this->current_orderby();

		// Params carried across category links so switching category keeps them.
		$preserve = array();
		if ( null !== $min ) {
			$preserve['jpwbc_min_price'] = $min;
		}
		if ( null !== $max ) {
			$preserve['jpwbc_max_price'] = $max;
		}
		if ( '' !== $orderby ) {
			$preserve['orderby'] = $orderby;
		}

		$scope_id = $current instanceof \WP_Term ? (int) $current->term_id : 0;
		$scope_tax = $current instanceof \WP_Term ? JPWBC_CAT_TAXONOMY : '';

		$facets     = array();
		$attr_index = JPWBC_Attr_Index::instance();
		if ( $attr_index instanceof JPWBC_Attr_Index ) {
			foreach ( $attr_index->selected_facets() as $fkey => $fslugs ) {
				$preserve[ 'jpwbc_af_' . $fkey ] = $fslugs;
			}
			if ( ! isset( $args['show_attributes'] ) || ! empty( $args['show_attributes'] ) ) {
				$allow = array();
				$raw   = isset( $args['attributes'] ) ? (string) $args['attributes'] : '';
				if ( '' !== $raw ) {
					$allow = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'strlen' ) );
				}
				$facets = $attr_index->get_facets( $scope_tax, $scope_id, $allow );
			}
		}

		$data = array(
			'settings'         => $this->settings,
			'current'          => array(
				'term_id' => $scope_id,
				'name'    => $current instanceof \WP_Term ? $current->name : '',
				'slug'    => $active,
				'url'     => $base_url,
			),
			'shop_url'         => $shop_url,
			'category_tree'    => $this->get_category_tree( $active ),
			'facets'           => $facets,
			'active_cat'       => $active,
			'base_url'         => $base_url,
			'preserve'         => $preserve,
			'price_range'      => $this->get_term_price_range( $scope_tax, $scope_id ),
			'current_min'      => $min,
			'current_max'      => $max,
			'current_sort'     => $orderby,
			'sort_options'     => $this->sort_options(),
			'show_category'    => ! isset( $args['show_category'] ) || ! empty( $args['show_category'] ),
			'show_price'       => ! isset( $args['show_price'] ) || ! empty( $args['show_price'] ),
			'show_sort'        => ! isset( $args['show_sort'] ) || ! empty( $args['show_sort'] ),
			'ajax'             => ! isset( $args['ajax'] ) || ! empty( $args['ajax'] ),
			'results_selector' => isset( $args['results_selector'] ) && '' !== (string) $args['results_selector'] ? (string) $args['results_selector'] : 'ul.products',
			'is_filtered'      => $this->is_filtered(),
		);

		return jpwbc_get_template( 'category-filter-bar.php', $data );
	}

	/**
	 * The product_cat tree: top-level categories, each with its children.
	 *
	 * Cached for an hour under the plugin's cache version, which JPWBC_Cache
	 * already bumps on any product_cat change, so a new category shows up
	 * without a manual flush. (A wp_cache_get_last_changed() key would mint a
	 * fresh transient on every request without a persistent object cache.)
	 *
	 * @since 1.20.0
	 *
	 * @param string $active_slug Slug of the category being viewed ('' = none).
	 * @return array<int, array{name:string, slug:string, count:int, url:string, is_active:bool, has_active_child:bool, children:array<int, array{name:string, slug:string, count:int, url:string, is_active:bool}>}>
	 */
	private function get_category_tree( string $active_slug = '' ): array {
		$key  = 'jpwbc_cat_tree_v' . JPWBC_Cache::current_version();
		$tree = get_transient( $key );

		if ( ! is_array( $tree ) ) {
			$tree  = array();
			$terms = get_terms(
				array(
					'taxonomy'   => JPWBC_CAT_TAXONOMY,
					'hide_empty' => true,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
			if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
				return array();
			}

			// Group children under their parent in one pass; terms whose parent is
			// itself hidden (empty) are promoted to the top level rather than lost.
			$by_parent = array();
			$known     = array();
			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$known[ (int) $term->term_id ] = true;
				$by_parent[ (int) $term->parent ][] = $term;
			}

			$row = static function ( \WP_Term $term ): array {
				$url = get_term_link( $term );
				return array(
					'name'  => (string) $term->name,
					'slug'  => (string) $term->slug,
					'count' => (int) $term->count,
					'url'   => is_string( $url ) ? $url : '',
				);
			};

			$roots = array();
			foreach ( $terms as $term ) {
				if ( ! $term instanceof \WP_Term ) {
					continue;
				}
				$parent = (int) $term->parent;
				if ( 0 === $parent || empty( $known[ $parent ] ) ) {
					$roots[] = $term;
				}
			}

			// Walk the full chain: a three-level tree must not drop its
			// grandchildren. Depth drives the indent in the template.
			$descend = static function ( int $parent_id, int $depth ) use ( &$descend, $by_parent, $row ): array {
				$out = array();
				foreach ( $by_parent[ $parent_id ] ?? array() as $child ) {
					$entry          = $row( $child );
					$entry['depth'] = $depth;
					$out[]          = $entry;
					foreach ( $descend( (int) $child->term_id, $depth + 1 ) as $deeper ) {
						$out[] = $deeper;
					}
				}
				return $out;
			};

			foreach ( $roots as $term ) {
				$entry             = $row( $term );
				$entry['depth']    = 0;
				$entry['children'] = $descend( (int) $term->term_id, 1 );
				$tree[]            = $entry;
			}

			set_transient( $key, $tree, HOUR_IN_SECONDS );
		}

		// Active flags are per-request, never cached.
		foreach ( $tree as $i => $entry ) {
			$tree[ $i ]['is_active']        = ( '' !== $active_slug && $entry['slug'] === $active_slug );
			$tree[ $i ]['has_active_child'] = false;
			foreach ( $entry['children'] as $j => $child ) {
				$child_active = ( '' !== $active_slug && $child['slug'] === $active_slug );
				$tree[ $i ]['children'][ $j ]['is_active'] = $child_active;
				if ( $child_active ) {
					$tree[ $i ]['has_active_child'] = true;
				}
			}
		}

		return $tree;
	}

	/**
	 * Sort options (orderby key => label).
	 *
	 * @since 1.13.0
	 *
	 * @return array<string, string>
	 */
	private function sort_options(): array {
		return array(
			'menu_order' => __( 'Recommended', 'jezpress-woo-brand-categories' ),
			'price'      => __( 'Price: Low to High', 'jezpress-woo-brand-categories' ),
			'price-desc' => __( 'Price: High to Low', 'jezpress-woo-brand-categories' ),
			'date'       => __( 'Recently Added', 'jezpress-woo-brand-categories' ),
			'popularity' => __( 'Most Popular', 'jezpress-woo-brand-categories' ),
		);
	}

	/**
	 * Cached [min,max] price bounds for a brand's published products.
	 *
	 * @since 1.13.0
	 *
	 * @param int $brand_id Brand term id.
	 * @return array{min:int, max:int}
	 */
	private function get_brand_price_range( int $brand_id ): array {
		return $this->get_term_price_range( JPWBC_BRAND_TAXONOMY, $brand_id );
	}

	/**
	 * Published price range for the products in a term, or the whole catalogue.
	 *
	 * @since 1.20.0
	 *
	 * @param string $taxonomy Scoping taxonomy ('' = whole catalogue).
	 * @param int    $term_id  Scoping term id (0 = whole catalogue).
	 * @return array{min:int, max:int}
	 */
	private function get_term_price_range( string $taxonomy, int $term_id ): array {
		$fallback = array(
			'min' => 0,
			'max' => 0,
		);
		$scoped = ( '' !== $taxonomy && $term_id > 0 );
		if ( '' !== $taxonomy && $term_id <= 0 ) {
			return $fallback;
		}

		$key    = 'jpwbc_price_range_' . ( $scoped ? md5( $taxonomy . '_' . $term_id ) : 'all' );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['min'], $cached['max'] ) ) {
			return array(
				'min' => (int) $cached['min'],
				'max' => (int) $cached['max'],
			);
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names are $wpdb props; every value is prepared, and the unscoped statement has no variables at all (see below).
		$lookup     = $wpdb->prefix . 'wc_product_meta_lookup';
		$scope_join = '';
		$args       = array();
		$scope_ids  = jpwbc_scope_term_ids( $taxonomy, $term_id );
		if ( ! empty( $scope_ids ) ) {
			// IN (…): a category archive includes its children's products.
			$placeholders = implode( ', ', array_fill( 0, count( $scope_ids ), '%d' ) );
			$scope_join   = "INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
					AND tt.taxonomy = %s AND tt.term_id IN ( {$placeholders} )";
			$args[]       = $taxonomy;
			foreach ( $scope_ids as $scope_id ) {
				$args[] = $scope_id;
			}
		}

		// Read the bounds from the same source price_clauses() filters on — the
		// WC lookup table — so the slider can't offer a range the filter won't
		// honour for variable products. Joining posts keeps variations out.
		$sql = "SELECT MIN( lk.min_price ) AS mn, MAX( lk.max_price ) AS mx
			FROM {$lookup} lk
			INNER JOIN {$wpdb->posts} p ON p.ID = lk.product_id
				AND p.post_type = 'product' AND p.post_status = 'publish'
			{$scope_join}
			WHERE lk.min_price IS NOT NULL";

		// prepare() warns when a query carries no placeholders, which is the
		// catalogue-wide case: there the statement contains no variables at all.
		if ( ! empty( $args ) ) {
			$sql = $wpdb->prepare( $sql, $args );
		}
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

		$range = array(
			'min' => is_array( $row ) && isset( $row['mn'] ) ? (int) floor( (float) $row['mn'] ) : 0,
			'max' => is_array( $row ) && isset( $row['mx'] ) ? (int) ceil( (float) $row['mx'] ) : 0,
		);

		// A null row means the lookup table is missing or the query failed — don't
		// cache that for an hour, or the slider stays hidden long after a fix.
		if ( null !== $row ) {
			set_transient( $key, $range, HOUR_IN_SECONDS );
		}

		return $range;
	}

	/**
	 * Resolve the brand from an override slug or the queried object.
	 *
	 * @since 1.13.0
	 *
	 * @param string $override Optional brand slug.
	 * @return \WP_Term|null
	 */
	private function resolve_brand( string $override ): ?\WP_Term {
		if ( '' !== $override ) {
			$term = get_term_by( 'slug', sanitize_title( $override ), JPWBC_BRAND_TAXONOMY );
			return $term instanceof \WP_Term ? $term : null;
		}

		if ( jpwbc_woocommerce_ready() && is_tax( JPWBC_BRAND_TAXONOMY ) ) {
			$obj = get_queried_object();
			if ( $obj instanceof \WP_Term && JPWBC_BRAND_TAXONOMY === $obj->taxonomy ) {
				return $obj;
			}
		}

		return null;
	}
}
