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
		add_action( 'pre_get_posts', array( $this, 'apply_price_filter' ) );
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
	 * Add the _price meta clause to the brand archive main query.
	 *
	 * @since 1.13.0
	 *
	 * @param \WP_Query $q The query being prepared.
	 */
	public function apply_price_filter( \WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() || ! jpwbc_woocommerce_ready() ) {
			return;
		}

		// Only on a product_brand archive query (mirrors JPWBC_Rewrites' detection).
		if ( '' === (string) $q->get( JPWBC_BRAND_TAXONOMY ) ) {
			return;
		}

		$min = $this->current_min();
		$max = $this->current_max();
		if ( null === $min && null === $max ) {
			return;
		}

		if ( null !== $min && null !== $max && $min > $max ) {
			$swap = $min;
			$min  = $max;
			$max  = $swap;
		}

		$clause = array(
			'key'  => '_price',
			'type' => 'NUMERIC',
		);
		if ( null !== $min && null !== $max ) {
			$clause['value']   = array( $min, $max );
			$clause['compare'] = 'BETWEEN';
		} elseif ( null !== $min ) {
			$clause['value']   = $min;
			$clause['compare'] = '>=';
		} else {
			$clause['value']   = $max;
			$clause['compare'] = '<=';
		}

		// AND the price clause with any existing meta_query, wrapping the existing
		// one as a nested group so a pre-existing 'relation' => 'OR' can't turn the
		// price constraint into an optional term.
		$existing = $q->get( 'meta_query' );
		if ( is_array( $existing ) && ! empty( $existing ) ) {
			$meta_query = array(
				'relation' => 'AND',
				$existing,
				$clause,
			);
		} else {
			$meta_query = array( $clause );
		}
		$q->set( 'meta_query', $meta_query );
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
		return jpwbc_woocommerce_ready() && is_tax( JPWBC_BRAND_TAXONOMY ) && $this->is_filtered();
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
				'brand'         => '',
				'show_category' => 'yes',
				'show_price'    => 'yes',
				'show_sort'     => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			'jpwbc_brand_filter'
		);

		$truthy = static function ( $v ): bool {
			return 'yes' === $v || '1' === (string) $v || 'true' === $v;
		};

		return $this->render_filter_bar(
			array(
				'brand'         => is_string( $atts['brand'] ) ? $atts['brand'] : '',
				'show_category' => $truthy( $atts['show_category'] ),
				'show_price'    => $truthy( $atts['show_price'] ),
				'show_sort'     => $truthy( $atts['show_sort'] ),
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

		// Params to carry across category links (price + sort), so switching category keeps them.
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
			'active_cat'    => $active_cat,
			'base_url'      => $base_url,
			'preserve'      => $preserve,
			'price_range'   => $this->get_brand_price_range( (int) $brand->term_id ),
			'current_min'   => $min,
			'current_max'   => $max,
			'current_sort'  => $orderby,
			'sort_options'  => $this->sort_options(),
			'show_category' => ! isset( $args['show_category'] ) || ! empty( $args['show_category'] ),
			'show_price'    => ! isset( $args['show_price'] ) || ! empty( $args['show_price'] ),
			'show_sort'     => ! isset( $args['show_sort'] ) || ! empty( $args['show_sort'] ),
			'is_filtered'   => $this->is_filtered(),
		);

		return jpwbc_get_template( 'brand-filter-bar.php', $data );
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
		$fallback = array(
			'min' => 0,
			'max' => 0,
		);
		if ( $brand_id <= 0 ) {
			return $fallback;
		}

		$key    = 'jpwbc_price_range_' . $brand_id;
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['min'], $cached['max'] ) ) {
			return array(
				'min' => (int) $cached['min'],
				'max' => (int) $cached['max'],
			);
		}

		global $wpdb;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are $wpdb props; values are prepared.
		$sql = $wpdb->prepare(
			"SELECT MIN( CAST( pm.meta_value AS DECIMAL(12,2) ) ) AS mn, MAX( CAST( pm.meta_value AS DECIMAL(12,2) ) ) AS mx
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				AND tt.taxonomy = %s AND tt.term_id = %d
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_price'
			WHERE p.post_type = 'product' AND p.post_status = 'publish' AND pm.meta_value <> ''",
			JPWBC_BRAND_TAXONOMY,
			$brand_id
		);
		$row = $wpdb->get_row( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$range = array(
			'min' => is_array( $row ) && isset( $row['mn'] ) ? (int) floor( (float) $row['mn'] ) : 0,
			'max' => is_array( $row ) && isset( $row['mx'] ) ? (int) ceil( (float) $row['mx'] ) : 0,
		);

		set_transient( $key, $range, HOUR_IN_SECONDS );

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
