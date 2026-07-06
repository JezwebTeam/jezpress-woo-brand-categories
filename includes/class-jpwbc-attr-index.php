<?php
/**
 * Custom-attribute facet indexer.
 *
 * Birch's Colour / Style / … variation attributes are *custom* (non-taxonomy)
 * attributes written by an API sync, so WooCommerce's own layered nav can't
 * filter them. This class derives them into plugin-owned, filterable taxonomies
 * (jpwbc_af_{key}) without changing what the sync writes — it reads each
 * product's _product_attributes and mirrors the values into terms. Because it
 * re-derives from the source, it self-heals after every sync (on product save,
 * a scheduled sweep, a manual rebuild, or `wp jpwbc-attr-index rebuild`).
 *
 * Values are stored raw (e.g. "01 BRIGHT WHITE"); a future normalisation map can
 * be layered on via the jpwbc_af_value_label filter without reindexing.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.14.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attribute facet indexer + facet query.
 *
 * @since 1.14.0
 */
class JPWBC_Attr_Index {

	/**
	 * Taxonomy name prefix for the derived facet taxonomies.
	 *
	 * @since 1.14.0
	 * @var string
	 */
	public const TAX_PREFIX = 'jpwbc_af_';

	/**
	 * Option holding the discovered attributes (key => label).
	 *
	 * @since 1.14.0
	 * @var string
	 */
	private const OPT_ATTRS = 'jpwbc_af_attributes';

	/**
	 * Option holding the backfill state.
	 *
	 * @since 1.14.0
	 * @var string
	 */
	private const OPT_STATE = 'jpwbc_af_index_state';

	/**
	 * Option holding the facet-count cache version (O(1) invalidation that also
	 * works on a persistent object cache, where transients aren't in the DB).
	 *
	 * @since 1.14.0
	 * @var string
	 */
	private const OPT_COUNTS_VER = 'jpwbc_af_counts_ver';

	/**
	 * Option: fold colour-like values into canonical base colours at index time.
	 *
	 * @since 1.17.0
	 * @var string
	 */
	private const OPT_NORMALIZE = 'jpwbc_af_normalize';

	/**
	 * Cron hook for the batched backfill.
	 *
	 * @since 1.14.0
	 * @var string
	 */
	public const CRON_HOOK = 'jpwbc_af_reindex';

	/**
	 * Products processed per backfill batch.
	 *
	 * @since 1.14.0
	 * @var int
	 */
	private const BATCH = 150;

	/**
	 * Facet-count transient TTL.
	 *
	 * @since 1.14.0
	 * @var int
	 */
	private const COUNT_TTL = HOUR_IN_SECONDS;

	/**
	 * Plugin settings.
	 *
	 * @since 1.14.0
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Most-recent instance (for the filter bar to reach facet data).
	 *
	 * @since 1.14.0
	 * @var JPWBC_Attr_Index|null
	 */
	private static ?JPWBC_Attr_Index $instance = null;

	/**
	 * Get the active instance.
	 *
	 * @since 1.14.0
	 *
	 * @return JPWBC_Attr_Index|null
	 */
	public static function instance(): ?JPWBC_Attr_Index {
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.14.0
	 *
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		self::$instance = $this;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.14.0
	 */
	public function register_hooks(): void {
		add_action( 'init', array( $this, 'register_taxonomies' ), 9 );
		add_action( 'woocommerce_update_product', array( $this, 'index_product' ) );
		add_action( 'save_post_product', array( $this, 'on_save_post' ) );
		add_action( self::CRON_HOOK, array( $this, 'run_batch' ) );
		add_action( 'pre_get_posts', array( $this, 'apply_facet_query' ) );
		add_filter( 'woocommerce_shortcode_products_query', array( $this, 'products_widget_query' ), 20 );
		add_filter( 'wp_robots', array( $this, 'filter_wp_robots' ) );
		add_filter( 'rank_math/frontend/robots', array( $this, 'filter_rank_math_robots' ) );

		// Admin: a "Rebuild attribute index" control on the Cache tab.
		add_action( 'jpwbc_render_cache_tab', array( $this, 'render_admin_section' ), 20 );
		add_action( 'admin_post_jpwbc_rebuild_attr_index', array( $this, 'handle_rebuild' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'jpwbc-attr-index', array( $this, 'cli' ) );
		}
	}

	/* ---------------------------------------------------------------------
	 * Taxonomy registration.
	 * ------------------------------------------------------------------- */

	/**
	 * A safe taxonomy key from a raw attribute name.
	 *
	 * @since 1.14.0
	 *
	 * @param string $name Attribute name.
	 * @return string
	 */
	private function key_from_name( string $name ): string {
		return substr( sanitize_key( $name ), 0, 20 );
	}

	/**
	 * Whether value normalisation (colour folding) is enabled.
	 *
	 * @since 1.17.0
	 *
	 * @return bool
	 */
	private function normalize_enabled(): bool {
		return (bool) get_option( self::OPT_NORMALIZE, false );
	}

	/**
	 * Map a raw attribute value to a canonical facet value (or itself).
	 *
	 * Folds colour-like values ("1 WHITE", "101 WHITE", "320 DARK NAVY") into base
	 * colours so the facet lists a tidy palette instead of hundreds of one-offs.
	 * Fully overridable per value via the jpwbc_af_normalize_value filter.
	 *
	 * @since 1.17.0
	 *
	 * @param string $raw   Raw value.
	 * @param string $key   Attribute key.
	 * @param string $label Attribute label.
	 * @return string
	 */
	private function canonical_value( string $raw, string $key, string $label ): string {
		if ( ! $this->normalize_enabled() ) {
			return $raw;
		}
		$canonical = $this->default_canonical( $raw, $key, $label );

		/**
		 * Filter the canonical (grouped) value for a raw attribute value. Return a
		 * label to fold this value into, or '' to keep the raw value as-is.
		 *
		 * @since 1.17.0
		 *
		 * @param string $canonical Default canonical value ('' = keep raw).
		 * @param string $raw       Raw value.
		 * @param string $key       Attribute key.
		 */
		$canonical = (string) apply_filters( 'jpwbc_af_normalize_value', $canonical, $raw, $key );

		return '' !== $canonical ? $canonical : $raw;
	}

	/**
	 * Built-in canonical mapping: base-colour folding for colour-like attributes.
	 *
	 * @since 1.17.0
	 *
	 * @param string $raw   Raw value.
	 * @param string $key   Attribute key.
	 * @param string $label Attribute label.
	 * @return string Canonical value, or '' to keep the raw value.
	 */
	private function default_canonical( string $raw, string $key, string $label ): string {
		$is_colour = ( 'colour' === $key || 'color' === $key
			|| false !== stripos( $label, 'colour' ) || false !== stripos( $label, 'color' ) );
		if ( ! $is_colour ) {
			return '';
		}

		$hay = strtolower( $raw );

		// Keyword => canonical, ordered so specific shades resolve before generics.
		$map = array(
			'rose gold' => 'Rose Gold',
			'navy'      => 'Navy',
			'royal'     => 'Blue',
			'turquoise' => 'Turquoise',
			'aqua'      => 'Aqua',
			'teal'      => 'Teal',
			'blue'      => 'Blue',
			'off white' => 'White',
			'white'     => 'White',
			'cream'     => 'Cream',
			'ivory'     => 'Ivory',
			'black'     => 'Black',
			'charcoal'  => 'Grey',
			'grey'      => 'Grey',
			'gray'      => 'Grey',
			'silver'    => 'Silver',
			'gold'      => 'Gold',
			'fuchsia'   => 'Pink',
			'pink'      => 'Pink',
			'rose'      => 'Pink',
			'scarlet'   => 'Red',
			'red'       => 'Red',
			'burgundy'  => 'Wine',
			'wine'      => 'Wine',
			'emerald'   => 'Green',
			'olive'     => 'Green',
			'green'     => 'Green',
			'aubergine' => 'Purple',
			'eggplant'  => 'Purple',
			'helio'     => 'Purple',
			'purple'    => 'Purple',
			'chocolate' => 'Brown',
			'brown'     => 'Brown',
			'beige'     => 'Beige',
			'natural'   => 'Natural',
			'nude'      => 'Nude',
			'peach'     => 'Peach',
			'orange'    => 'Orange',
			'mustard'   => 'Yellow',
			'maize'     => 'Yellow',
			'curry'     => 'Yellow',
			'yellow'    => 'Yellow',
			'mint'      => 'Mint',
		);

		foreach ( $map as $needle => $canonical ) {
			if ( false !== strpos( $hay, $needle ) ) {
				return $canonical;
			}
		}

		return '';
	}

	/**
	 * The taxonomy name for an attribute key.
	 *
	 * @since 1.14.0
	 *
	 * @param string $key Attribute key.
	 * @return string
	 */
	private function taxonomy_for( string $key ): string {
		return self::TAX_PREFIX . $key;
	}

	/**
	 * Register the derived facet taxonomies from the stored attribute list.
	 *
	 * @since 1.14.0
	 */
	public function register_taxonomies(): void {
		$attrs = get_option( self::OPT_ATTRS, array() );
		if ( ! is_array( $attrs ) ) {
			return;
		}
		foreach ( $attrs as $key => $label ) {
			$this->register_one( (string) $key, (string) $label );
		}
	}

	/**
	 * Register a single facet taxonomy (idempotent).
	 *
	 * @since 1.14.0
	 *
	 * @param string $key   Attribute key.
	 * @param string $label Attribute label.
	 */
	private function register_one( string $key, string $label ): void {
		if ( '' === $key ) {
			return;
		}
		$tax = $this->taxonomy_for( $key );
		if ( taxonomy_exists( $tax ) ) {
			return;
		}
		register_taxonomy(
			$tax,
			'product',
			array(
				'public'            => false,
				'hierarchical'      => false,
				'show_ui'           => false,
				'show_in_menu'      => false,
				'show_in_nav_menus' => false,
				'show_in_rest'      => false,
				'show_admin_column' => false,
				'rewrite'           => false,
				'query_var'         => false,
				'label'             => $label,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Indexing.
	 * ------------------------------------------------------------------- */

	/**
	 * Index a product when its post is saved.
	 *
	 * @since 1.14.0
	 *
	 * @param int $post_id Post id.
	 */
	public function on_save_post( $post_id ): void {
		$post_id = (int) $post_id;
		if ( 'product' === get_post_type( $post_id ) ) {
			$this->index_product( $post_id );
		}
	}

	/**
	 * Mirror a product's custom variation attributes into the facet taxonomies.
	 *
	 * @since 1.14.0
	 *
	 * @param int $product_id Product id.
	 */
	public function index_product( int $product_id ): void {
		if ( $product_id <= 0 ) {
			return;
		}

		// A WC CRUD save fires both woocommerce_update_product and save_post_product;
		// index each product at most once per request.
		static $seen = array();
		if ( isset( $seen[ $product_id ] ) ) {
			return;
		}
		$seen[ $product_id ] = true;

		$attrs = get_post_meta( $product_id, '_product_attributes', true );
		if ( ! is_array( $attrs ) ) {
			$attrs = array();
		}

		$known   = get_option( self::OPT_ATTRS, array() );
		$known   = is_array( $known ) ? $known : array();
		$changed = false;
		$touched = array();

		foreach ( $attrs as $raw_key => $attr ) {
			if ( ! is_array( $attr ) ) {
				continue;
			}
			// Only custom (non-taxonomy) attributes used for variations.
			if ( ! empty( $attr['is_taxonomy'] ) || empty( $attr['is_variation'] ) ) {
				continue;
			}

			$name = isset( $attr['name'] ) && '' !== (string) $attr['name'] ? (string) $attr['name'] : (string) $raw_key;
			$key  = $this->resolve_key( $name, $known );
			if ( '' === $key ) {
				continue;
			}

			if ( ! isset( $known[ $key ] ) ) {
				$known[ $key ] = $name;
				$changed       = true;
			}
			$this->register_one( $key, $name );

			$raw_values = array_values(
				array_filter(
					array_map( 'trim', explode( '|', (string) ( $attr['value'] ?? '' ) ) ),
					static function ( $v ): bool {
						return '' !== $v;
					}
				)
			);

			// Fold each raw value to its canonical facet value (or itself), dedup.
			$values = array();
			foreach ( $raw_values as $rv ) {
				$values[] = $this->canonical_value( $rv, $key, $name );
			}
			$values = array_values( array_unique( $values ) );

			wp_set_object_terms( $product_id, $values, $this->taxonomy_for( $key ), false );
			$touched[ $key ] = true;
		}

		// Clear this product from any previously-known facet it no longer has.
		foreach ( $known as $key => $label ) {
			if ( ! isset( $touched[ $key ] ) && taxonomy_exists( $this->taxonomy_for( (string) $key ) ) ) {
				wp_set_object_terms( $product_id, array(), $this->taxonomy_for( (string) $key ), false );
			}
		}

		if ( $changed ) {
			update_option( self::OPT_ATTRS, $known, false );
			// A newly-discovered attribute changes what facets exist — refresh counts.
			$this->flush_count_cache();
		}
	}

	/**
	 * A stable, collision-safe taxonomy key for a custom attribute name.
	 *
	 * Reuses the existing key when the name is already known; falls back to a
	 * hash-suffixed key if a different name would truncate to the same 20-char key.
	 *
	 * @since 1.14.0
	 *
	 * @param string                $name  Attribute name.
	 * @param array<string, string> $known Known key => name map.
	 * @return string
	 */
	private function resolve_key( string $name, array $known ): string {
		$base = $this->key_from_name( $name );
		if ( '' === $base ) {
			return '';
		}
		// Exact reuse if this name is already stored under some key.
		foreach ( $known as $k => $label ) {
			if ( (string) $label === $name ) {
				return (string) $k;
			}
		}
		// New name whose base key collides with a *different* stored name.
		if ( isset( $known[ $base ] ) ) {
			return substr( $base, 0, 15 ) . '_' . substr( md5( $name ), 0, 4 );
		}
		return $base;
	}

	/* ---------------------------------------------------------------------
	 * Batched backfill.
	 * ------------------------------------------------------------------- */

	/**
	 * Start (or restart) a full rebuild.
	 *
	 * @since 1.14.0
	 */
	public function start_rebuild(): void {
		update_option(
			self::OPT_STATE,
			array(
				'offset'  => 0,
				'done'    => false,
				'updated' => time(),
			),
			false
		);
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5, self::CRON_HOOK );
		}
	}

	/**
	 * Process one backfill batch, rescheduling until complete.
	 *
	 * @since 1.14.0
	 */
	public function run_batch(): void {
		$state  = get_option( self::OPT_STATE, array() );
		$state  = is_array( $state ) ? $state : array();
		$offset = isset( $state['offset'] ) ? max( 0, (int) $state['offset'] ) : 0;

		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => self::BATCH,
				'offset'         => $offset,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				'suppress_filters' => true,
			)
		);

		foreach ( $ids as $id ) {
			$this->index_product( (int) $id );
		}

		$done = count( $ids ) < self::BATCH;
		update_option(
			self::OPT_STATE,
			array(
				'offset'  => $offset + count( $ids ),
				'done'    => $done,
				'updated' => time(),
			),
			false
		);

		if ( ! $done ) {
			wp_schedule_single_event( time() + 30, self::CRON_HOOK );
		} else {
			$this->flush_count_cache();
		}
	}

	/**
	 * Run the whole backfill synchronously (used by WP-CLI).
	 *
	 * @since 1.14.0
	 *
	 * @return int Products processed.
	 */
	public function rebuild_now(): int {
		$offset = 0;
		$total  = 0;
		do {
			$ids = get_posts(
				array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => self::BATCH,
					'offset'         => $offset,
					'fields'         => 'ids',
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
					'suppress_filters' => true,
				)
			);
			foreach ( $ids as $id ) {
				$this->index_product( (int) $id );
				++$total;
			}
			$offset += self::BATCH;
		} while ( count( $ids ) === self::BATCH );

		update_option(
			self::OPT_STATE,
			array(
				'offset'  => $total,
				'done'    => true,
				'updated' => time(),
			),
			false
		);
		$this->flush_count_cache();

		return $total;
	}

	/* ---------------------------------------------------------------------
	 * Front-end: facet query + robots.
	 * ------------------------------------------------------------------- */

	/**
	 * Selected facet slugs per attribute key from the request (sanitized).
	 *
	 * @since 1.14.0
	 *
	 * @return array<string, array<int, string>>
	 */
	public function selected_facets(): array {
		$out   = array();
		$attrs = get_option( self::OPT_ATTRS, array() );
		if ( ! is_array( $attrs ) ) {
			return $out;
		}

		foreach ( $attrs as $key => $label ) {
			$param = 'jpwbc_af_' . $key;
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public navigation filter.
			if ( ! isset( $_GET[ $param ] ) ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw   = wp_unslash( $_GET[ $param ] );
			$raw   = is_array( $raw ) ? $raw : array( $raw );
			$slugs = array_values(
				array_filter(
					array_map(
						static function ( $v ): string {
							return sanitize_title( (string) $v );
						},
						$raw
					),
					static function ( $v ): bool {
						return '' !== $v;
					}
				)
			);
			if ( ! empty( $slugs ) ) {
				$out[ (string) $key ] = $slugs;
			}
		}

		return $out;
	}

	/**
	 * Add the selected facets as AND tax clauses on the brand archive query.
	 *
	 * @since 1.14.0
	 *
	 * @param \WP_Query $q The query being prepared.
	 */
	public function apply_facet_query( \WP_Query $q ): void {
		if ( is_admin() || ! $q->is_main_query() || ! jpwbc_woocommerce_ready() ) {
			return;
		}
		if ( '' === (string) $q->get( JPWBC_BRAND_TAXONOMY ) ) {
			return;
		}

		$selected = $this->selected_facets();
		if ( empty( $selected ) ) {
			return;
		}

		$tax_query = (array) $q->get( 'tax_query' );
		foreach ( $selected as $key => $slugs ) {
			if ( ! taxonomy_exists( $this->taxonomy_for( $key ) ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $this->taxonomy_for( $key ),
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		// Count real (numeric-keyed) clauses; force AND when more than one.
		$clauses = 0;
		foreach ( $tax_query as $k => $v ) {
			if ( 'relation' !== $k ) {
				++$clauses;
			}
		}
		if ( $clauses > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		$q->set( 'tax_query', $tax_query );
	}

	/**
	 * Add the selected facets to the Elementor/WooCommerce "Products" widget query.
	 *
	 * The Products (current query) widget renders via WC_Shortcode_Products with
	 * its OWN query — not the main query — so the pre_get_posts clause above never
	 * reaches it. This filter (applied by WC_Shortcode_Products::parse_query_args)
	 * injects the same facet constraints into that query.
	 *
	 * @since 1.17.1
	 *
	 * @param array<string, mixed> $query_args WP_Query args for the products query.
	 * @return array<string, mixed>
	 */
	public function products_widget_query( $query_args ) {
		if ( ! is_array( $query_args ) || ! jpwbc_woocommerce_ready() || ! is_tax( JPWBC_BRAND_TAXONOMY ) ) {
			return $query_args;
		}

		$selected = $this->selected_facets();
		if ( empty( $selected ) ) {
			return $query_args;
		}

		$tax_query = isset( $query_args['tax_query'] ) && is_array( $query_args['tax_query'] ) ? $query_args['tax_query'] : array();
		foreach ( $selected as $key => $slugs ) {
			if ( ! taxonomy_exists( $this->taxonomy_for( $key ) ) ) {
				continue;
			}
			$tax_query[] = array(
				'taxonomy' => $this->taxonomy_for( $key ),
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}

		$clauses = 0;
		foreach ( $tax_query as $k => $v ) {
			if ( 'relation' !== $k ) {
				++$clauses;
			}
		}
		if ( $clauses > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		$query_args['tax_query'] = $tax_query;

		return $query_args;
	}

	/**
	 * Whether the current request is a facet-filtered brand archive.
	 *
	 * @since 1.14.0
	 *
	 * @return bool
	 */
	private function is_faceted_brand_archive(): bool {
		return jpwbc_woocommerce_ready() && is_tax( JPWBC_BRAND_TAXONOMY ) && ! empty( $this->selected_facets() );
	}

	/**
	 * Noindex faceted views (WP core robots).
	 *
	 * @since 1.14.0
	 *
	 * @param array<string, bool|int|string> $robots Robots directives.
	 * @return array<string, bool|int|string>
	 */
	public function filter_wp_robots( array $robots ): array {
		if ( $this->is_faceted_brand_archive() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Noindex faceted views (Rank Math).
	 *
	 * @since 1.14.0
	 *
	 * @param array<string, string> $robots Rank Math robots array.
	 * @return array<string, string>
	 */
	public function filter_rank_math_robots( $robots ): array {
		$robots = is_array( $robots ) ? $robots : array();
		if ( $this->is_faceted_brand_archive() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}

	/* ---------------------------------------------------------------------
	 * Facet data for the filter bar.
	 * ------------------------------------------------------------------- */

	/**
	 * Facets (with per-value counts) available within a brand.
	 *
	 * Counts are brand-scoped (not cross-faceted) and cached; the widget may pass
	 * an allowlist of attribute keys/names to restrict which facets appear.
	 *
	 * @since 1.14.0
	 *
	 * @param int                $brand_id  Brand term id.
	 * @param array<int, string> $allowlist Attribute keys/names to include ([] = all).
	 * @return array<int, array{key:string, label:string, values:array<int, array{slug:string, name:string, count:int, selected:bool}>}>
	 */
	public function get_brand_facets( int $brand_id, array $allowlist = array() ): array {
		$attrs = get_option( self::OPT_ATTRS, array() );
		if ( ! is_array( $attrs ) || empty( $attrs ) || $brand_id <= 0 ) {
			return array();
		}

		$allow_keys  = array();
		$allow_names = array();
		foreach ( $allowlist as $a ) {
			$a = trim( (string) $a );
			if ( '' === $a ) {
				continue;
			}
			$allow_keys[]  = $this->key_from_name( $a );
			$allow_names[] = strtolower( $a );
		}

		$key    = 'jpwbc_af_counts_' . $brand_id . '_v' . $this->counts_version();
		$counts = get_transient( $key );
		if ( ! is_array( $counts ) ) {
			$counts = $this->query_brand_facet_counts( $brand_id, array_map( 'strval', array_keys( $attrs ) ) );
			set_transient( $key, $counts, self::COUNT_TTL );
		}

		$selected = $this->selected_facets();
		$out      = array();

		foreach ( $attrs as $attr_key => $label ) {
			$attr_key = (string) $attr_key;
			if ( ! empty( $allow_keys ) ) {
				$match = in_array( $attr_key, $allow_keys, true )
					|| in_array( strtolower( (string) $label ), $allow_names, true );
				if ( ! $match ) {
					continue;
				}
			}
			if ( empty( $counts[ $attr_key ] ) || ! is_array( $counts[ $attr_key ] ) ) {
				continue;
			}

			$values      = array();
			$sel_for_key = isset( $selected[ $attr_key ] ) ? $selected[ $attr_key ] : array();
			foreach ( $counts[ $attr_key ] as $row ) {
				$slug = (string) $row['slug'];
				$name = (string) $row['name'];
				/**
				 * Filter a facet value's display label (hook point for a future
				 * normalisation map). Return the label to show for this raw value.
				 *
				 * @since 1.14.0
				 *
				 * @param string $name     Raw value name.
				 * @param string $slug     Value slug.
				 * @param string $attr_key Attribute key.
				 */
				$name     = (string) apply_filters( 'jpwbc_af_value_label', $name, $slug, $attr_key );
				$values[] = array(
					'slug'     => $slug,
					'name'     => $name,
					'count'    => (int) $row['count'],
					'selected' => in_array( $slug, $sel_for_key, true ),
				);
			}

			if ( ! empty( $values ) ) {
				$out[] = array(
					'key'    => $attr_key,
					'label'  => (string) $label,
					'values' => $values,
				);
			}
		}

		return $out;
	}

	/**
	 * Grouped per-value product counts for each attribute within a brand.
	 *
	 * @since 1.14.0
	 *
	 * @param int                $brand_id Brand term id.
	 * @param array<int, string> $keys     Attribute keys.
	 * @return array<string, array<int, array{slug:string, name:string, count:int}>>
	 */
	private function query_brand_facet_counts( int $brand_id, array $keys ): array {
		global $wpdb;
		$out = array();

		foreach ( $keys as $key ) {
			$tax = $this->taxonomy_for( (string) $key );
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}

			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names are $wpdb props; values are prepared.
			$sql = $wpdb->prepare(
				"SELECT t.slug AS slug, t.name AS name, COUNT( DISTINCT p.ID ) AS cnt
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->term_relationships} tr_b ON tr_b.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt_b ON tt_b.term_taxonomy_id = tr_b.term_taxonomy_id
					AND tt_b.taxonomy = %s AND tt_b.term_id = %d
				INNER JOIN {$wpdb->term_relationships} tr_a ON tr_a.object_id = p.ID
				INNER JOIN {$wpdb->term_taxonomy} tt_a ON tt_a.term_taxonomy_id = tr_a.term_taxonomy_id
					AND tt_a.taxonomy = %s
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt_a.term_id
				WHERE p.post_type = 'product' AND p.post_status = 'publish'
				GROUP BY t.slug, t.name
				HAVING cnt > 0
				ORDER BY t.name ASC",
				JPWBC_BRAND_TAXONOMY,
				$brand_id,
				$tax
			);
			$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( is_array( $rows ) && ! empty( $rows ) ) {
				$vals = array();
				foreach ( $rows as $r ) {
					$vals[] = array(
						'slug'  => (string) $r['slug'],
						'name'  => (string) $r['name'],
						'count' => (int) $r['cnt'],
					);
				}
				$out[ (string) $key ] = $vals;
			}
		}

		return $out;
	}

	/**
	 * Current facet-count cache version.
	 *
	 * @since 1.14.0
	 *
	 * @return int
	 */
	private function counts_version(): int {
		return max( 1, (int) get_option( self::OPT_COUNTS_VER, 1 ) );
	}

	/**
	 * Invalidate all cached facet counts at once (O(1), object-cache safe).
	 *
	 * @since 1.14.0
	 */
	private function flush_count_cache(): void {
		update_option( self::OPT_COUNTS_VER, $this->counts_version() + 1, false );
	}

	/* ---------------------------------------------------------------------
	 * Admin + CLI.
	 * ------------------------------------------------------------------- */

	/**
	 * Render the "Attribute filters" section on the Cache tab.
	 *
	 * @since 1.14.0
	 */
	public function render_admin_section(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after a nonce-protected redirect.
		if ( isset( $_GET['jpwbc_msg'] ) && 'attr_reindex' === sanitize_key( wp_unslash( $_GET['jpwbc_msg'] ) ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Attribute index rebuild started.', 'jezpress-woo-brand-categories' ) . '</p></div>';
		}

		$attrs = get_option( self::OPT_ATTRS, array() );
		$attrs = is_array( $attrs ) ? $attrs : array();
		$state = get_option( self::OPT_STATE, array() );
		$state = is_array( $state ) ? $state : array();
		?>
		<hr>
		<h2><?php esc_html_e( 'Attribute filters', 'jezpress-woo-brand-categories' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'Custom (non-taxonomy) variation attributes mirrored into filterable facets for the Brand Filter Bar. Rebuild after a large product sync.', 'jezpress-woo-brand-categories' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Indexed attributes', 'jezpress-woo-brand-categories' ); ?></th>
				<td>
					<?php
					if ( empty( $attrs ) ) {
						esc_html_e( 'None yet — run a rebuild.', 'jezpress-woo-brand-categories' );
					} else {
						echo esc_html( implode( ', ', array_map( 'strval', $attrs ) ) );
					}
					?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Index status', 'jezpress-woo-brand-categories' ); ?></th>
				<td>
					<?php
					if ( ! empty( $state['updated'] ) ) {
						$done   = ! empty( $state['done'] );
						$counts = wp_count_posts( 'product' );
						$total  = $counts instanceof \stdClass && isset( $counts->publish ) ? (int) $counts->publish : 0;
						$offset = isset( $state['offset'] ) ? (int) $state['offset'] : 0;
						$pct    = ( $total > 0 ) ? min( 100, (int) round( $offset / $total * 100 ) ) : ( $done ? 100 : 0 );

						if ( $done ) {
							echo esc_html(
								sprintf(
									/* translators: 1: product count, 2: human time diff */
									__( 'Complete — %1$s products indexed, %2$s ago', 'jezpress-woo-brand-categories' ),
									number_format_i18n( $total > 0 ? $total : $offset ),
									human_time_diff( (int) $state['updated'], time() )
								)
							);
						} else {
							echo esc_html(
								sprintf(
									/* translators: 1: percent, 2: processed count, 3: total count */
									__( 'In progress — %1$d%% (%2$s of ~%3$s products). Reload to refresh.', 'jezpress-woo-brand-categories' ),
									$pct,
									number_format_i18n( $offset ),
									number_format_i18n( $total )
								)
							);
							?>
							<progress value="<?php echo esc_attr( (string) $pct ); ?>" max="100" style="width:220px;margin-left:8px;vertical-align:middle;"></progress>
							<?php
						}
					} else {
						esc_html_e( 'Never run', 'jezpress-woo-brand-categories' );
					}
					?>
				</td>
			</tr>
		</table>
		<p>
			<label>
				<input type="checkbox" form="jpwbc-attr-rebuild-form" name="jpwbc_af_normalize" value="1" <?php checked( $this->normalize_enabled() ); ?>>
				<?php esc_html_e( 'Fold colour values into base colours (e.g. "101 WHITE" → "White"). Requires a rebuild to apply.', 'jezpress-woo-brand-categories' ); ?>
			</label>
		</p>
		<form id="jpwbc-attr-rebuild-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jpwbc_rebuild_attr_index' ); ?>
			<input type="hidden" name="action" value="jpwbc_rebuild_attr_index">
			<?php submit_button( __( 'Save & rebuild attribute index', 'jezpress-woo-brand-categories' ), 'secondary' ); ?>
		</form>
		<?php
	}

	/**
	 * Handle the "Rebuild attribute index" POST.
	 *
	 * @since 1.14.0
	 */
	public function handle_rebuild(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jezpress-woo-brand-categories' ), 403 );
		}
		check_admin_referer( 'jpwbc_rebuild_attr_index' );

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer above.
		update_option( self::OPT_NORMALIZE, isset( $_POST['jpwbc_af_normalize'] ), false );

		$this->start_rebuild();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'jpwbc-settings',
					'tab'       => 'cache',
					'jpwbc_msg' => 'attr_reindex',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * WP-CLI: `wp jpwbc-attr-index rebuild`.
	 *
	 * @since 1.14.0
	 *
	 * @param array<int, string> $args       Positional args.
	 * @param array<string, mixed> $assoc_args Associative args.
	 */
	public function cli( $args, $assoc_args ): void {
		$sub = isset( $args[0] ) ? (string) $args[0] : 'rebuild';
		if ( 'rebuild' !== $sub ) {
			\WP_CLI::error( 'Usage: wp jpwbc-attr-index rebuild' );
			return;
		}
		$count = $this->rebuild_now();
		$attrs = get_option( self::OPT_ATTRS, array() );
		$attrs = is_array( $attrs ) ? $attrs : array();
		\WP_CLI::success(
			sprintf(
				'Indexed %d products. Attributes: %s',
				$count,
				empty( $attrs ) ? '(none)' : implode( ', ', array_map( 'strval', $attrs ) )
			)
		);
	}
}
