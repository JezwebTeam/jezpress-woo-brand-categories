<?php
/**
 * Brand directory features: Trending Brands + All Brands (A-Z).
 *
 * Provides the data queries, the click-tracking AJAX endpoint, the shortcodes
 * ([jpwbc_trending_brands], [jpwbc_all_brands]) and the render methods the
 * Elementor widgets delegate to.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brand directory handler.
 *
 * @since 1.2.0
 */
class JPWBC_Brands {

	/**
	 * Term meta key storing a brand's accumulated click count.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	public const CLICKS_META = '_jpwbc_clicks';

	/**
	 * Shared instance (for the Elementor widgets to reach the render methods).
	 *
	 * @since 1.2.0
	 * @var JPWBC_Brands|null
	 */
	private static ?JPWBC_Brands $instance = null;

	/**
	 * Get the shared instance.
	 *
	 * @since 1.2.0
	 *
	 * @return JPWBC_Brands
	 */
	public static function instance(): JPWBC_Brands {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks (shortcodes + click-tracking AJAX).
	 *
	 * @since 1.2.0
	 */
	public function register_hooks(): void {
		self::$instance = $this;

		add_shortcode( 'jpwbc_trending_brands', array( $this, 'shortcode_trending' ) );
		add_shortcode( 'jpwbc_all_brands', array( $this, 'shortcode_all_brands' ) );

		add_action( 'wp_ajax_jpwbc_track_click', array( $this, 'ajax_track_click' ) );
		add_action( 'wp_ajax_nopriv_jpwbc_track_click', array( $this, 'ajax_track_click' ) );
	}

	/* ---------------------------------------------------------------------
	 * Data
	 * ------------------------------------------------------------------- */

	/**
	 * Get the trending brands, most-clicked first.
	 *
	 * Sorted by accumulated clicks, then by product count, then name — so the
	 * list is never blank before clicks accrue (it falls back to the biggest
	 * brands). Cached for an hour; trending never needs to be real-time.
	 *
	 * @since 1.2.0
	 *
	 * @param int $limit Maximum brands to return.
	 * @return array<int, array{term_id:int, name:string, slug:string, url:string, clicks:int}>
	 */
	public function get_trending_brands( int $limit ): array {
		$limit = max( 1, min( 50, $limit ) );

		if ( ! jpwbc_woocommerce_ready() ) {
			return array();
		}

		$cache_key = 'jpwbc_trending_' . $limit;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => JPWBC_BRAND_TAXONOMY,
				'hide_empty' => true,
			)
		);
		if ( is_wp_error( $terms ) ) {
			$terms = array();
		}

		$rows = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$rows[] = array(
				'term'   => $term,
				'clicks' => (int) get_term_meta( $term->term_id, self::CLICKS_META, true ),
				'count'  => (int) $term->count,
			);
		}

		usort(
			$rows,
			static function ( array $a, array $b ): int {
				return array( $b['clicks'], $b['count'], $a['term']->name )
					<=> array( $a['clicks'], $a['count'], $b['term']->name );
			}
		);

		$rows = array_slice( $rows, 0, $limit );

		$out = array();
		foreach ( $rows as $row ) {
			$link = get_term_link( $row['term'] );
			$out[] = array(
				'term_id' => (int) $row['term']->term_id,
				'name'    => $row['term']->name,
				'slug'    => $row['term']->slug,
				'url'     => is_wp_error( $link ) ? '' : $link,
				'clicks'  => (int) $row['clicks'],
			);
		}

		set_transient( $cache_key, $out, HOUR_IN_SECONDS );

		return $out;
	}

	/**
	 * Get all brands grouped by first letter (A-Z, then # for non-alpha).
	 *
	 * @since 1.2.0
	 *
	 * @return array<string, array<int, array{term_id:int, name:string, slug:string, url:string}>>
	 */
	public function get_brands_grouped(): array {
		if ( ! jpwbc_woocommerce_ready() ) {
			return array();
		}

		$cache_key = 'jpwbc_allbrands_grouped';
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$terms = get_terms(
			array(
				'taxonomy'   => JPWBC_BRAND_TAXONOMY,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $terms ) ) {
			return array();
		}

		$groups = array();
		foreach ( $terms as $term ) {
			if ( ! $term instanceof \WP_Term ) {
				continue;
			}
			$link = get_term_link( $term );
			if ( is_wp_error( $link ) ) {
				// Skip un-linkable brands so per-letter counts match what renders.
				continue;
			}
			$first = strtoupper( mb_substr( $term->name, 0, 1 ) );
			if ( ! preg_match( '/^[A-Z]$/', $first ) ) {
				$first = '#';
			}
			$groups[ $first ][] = array(
				'term_id' => (int) $term->term_id,
				'name'    => $term->name,
				'slug'    => $term->slug,
				'url'     => $link,
			);
		}

		// Order the letter groups A-Z, with '#' last.
		uksort(
			$groups,
			static function ( string $a, string $b ): int {
				if ( '#' === $a ) {
					return 1;
				}
				if ( '#' === $b ) {
					return -1;
				}
				return strcmp( $a, $b );
			}
		);

		set_transient( $cache_key, $groups, HOUR_IN_SECONDS );

		return $groups;
	}

	/* ---------------------------------------------------------------------
	 * Render
	 * ------------------------------------------------------------------- */

	/**
	 * Render the Trending Brands list.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $args title, count, show_arrow.
	 * @return string
	 */
	public function render_trending( array $args = array() ): string {
		if ( ! jpwbc_woocommerce_ready() ) {
			return '';
		}

		$args = array_merge(
			array(
				'title'      => __( 'Trending Brands', 'jezpress-woo-brand-categories' ),
				'count'      => 8,
				'show_arrow' => true,
			),
			$args
		);

		$brands = $this->get_trending_brands( (int) $args['count'] );
		if ( empty( $brands ) ) {
			return '';
		}

		jpwbc_enqueue_frontend_assets();

		return jpwbc_get_template(
			'trending-brands.php',
			array(
				'title'      => (string) $args['title'],
				'show_arrow' => ! empty( $args['show_arrow'] ),
				'brands'     => $brands,
			)
		);
	}

	/**
	 * Render the All Brands (A-Z) directory.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed> $args title, show_index, show_arrow, view_all_url, view_all_text.
	 * @return string
	 */
	public function render_all_brands( array $args = array() ): string {
		if ( ! jpwbc_woocommerce_ready() ) {
			return '';
		}

		$args = array_merge(
			array(
				'title'              => __( 'All Brands (A-Z)', 'jezpress-woo-brand-categories' ),
				'show_index'         => true,
				'index_layout'       => 'grid',
				'show_groups'        => true,
				'columns'            => 5,
				'list_columns'       => 1,
				'show_letter_counts' => false,
				'show_search'        => false,
				'show_arrow'         => true,
				'index_url'          => '',
				'view_all_url'       => '',
				'view_all_text'      => __( 'View all', 'jezpress-woo-brand-categories' ),
			),
			$args
		);

		$groups = $this->get_brands_grouped();
		if ( empty( $groups ) ) {
			return '';
		}

		jpwbc_enqueue_frontend_assets();

		// A unique instance id so multiple widgets on one page have distinct anchors.
		static $instance = 0;
		++$instance;

		$columns = (int) $args['columns'];
		$columns = ( $columns >= 3 && $columns <= 6 ) ? $columns : 5;

		$list_columns = (int) $args['list_columns'];
		$list_columns = ( $list_columns >= 1 && $list_columns <= 4 ) ? $list_columns : 1;

		return jpwbc_get_template(
			'all-brands-az.php',
			array(
				'title'              => (string) $args['title'],
				'show_index'         => ! empty( $args['show_index'] ),
				'index_layout'       => ( 'inline' === $args['index_layout'] ) ? 'inline' : 'grid',
				'show_groups'        => ! empty( $args['show_groups'] ),
				'columns'            => $columns,
				'list_columns'       => $list_columns,
				'show_letter_counts' => ! empty( $args['show_letter_counts'] ),
				'show_search'        => ! empty( $args['show_search'] ),
				'show_arrow'         => ! empty( $args['show_arrow'] ),
				'index_url'          => esc_url_raw( (string) $args['index_url'] ),
				'view_all_url'       => esc_url_raw( (string) $args['view_all_url'] ),
				'view_all_text'      => (string) $args['view_all_text'],
				'groups'             => $groups,
				'instance'           => $instance,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Shortcodes
	 * ------------------------------------------------------------------- */

	/**
	 * [jpwbc_trending_brands] shortcode.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 * @return string
	 */
	public function shortcode_trending( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'      => __( 'Trending Brands', 'jezpress-woo-brand-categories' ),
				'count'      => 8,
				'show_arrow' => 'yes',
			),
			is_array( $atts ) ? $atts : array(),
			'jpwbc_trending_brands'
		);

		return $this->render_trending(
			array(
				'title'      => $atts['title'],
				'count'      => (int) $atts['count'],
				'show_arrow' => 'yes' === $atts['show_arrow'] || '1' === (string) $atts['show_arrow'],
			)
		);
	}

	/**
	 * [jpwbc_all_brands] shortcode.
	 *
	 * @since 1.2.0
	 *
	 * @param array<string, mixed>|string $atts Attributes.
	 * @return string
	 */
	public function shortcode_all_brands( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'              => __( 'All Brands (A-Z)', 'jezpress-woo-brand-categories' ),
				'show_index'         => 'yes',
				'index_layout'       => 'grid',
				'show_groups'        => 'yes',
				'columns'            => 5,
				'list_columns'       => 1,
				'show_letter_counts' => 'no',
				'show_search'        => 'no',
				'show_arrow'         => 'yes',
				'index_url'          => '',
				'view_all_url'       => '',
				'view_all_text'      => __( 'View all', 'jezpress-woo-brand-categories' ),
			),
			is_array( $atts ) ? $atts : array(),
			'jpwbc_all_brands'
		);

		$truthy = static function ( $v ): bool {
			return 'yes' === $v || '1' === (string) $v || 'true' === $v;
		};

		return $this->render_all_brands(
			array(
				'title'              => $atts['title'],
				'show_index'         => $truthy( $atts['show_index'] ),
				'index_layout'       => (string) $atts['index_layout'],
				'show_groups'        => $truthy( $atts['show_groups'] ),
				'columns'            => (int) $atts['columns'],
				'list_columns'       => (int) $atts['list_columns'],
				'show_letter_counts' => $truthy( $atts['show_letter_counts'] ),
				'show_search'        => $truthy( $atts['show_search'] ),
				'show_arrow'         => $truthy( $atts['show_arrow'] ),
				'index_url'          => $atts['index_url'],
				'view_all_url'       => $atts['view_all_url'],
				'view_all_text'      => $atts['view_all_text'],
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Click tracking
	 * ------------------------------------------------------------------- */

	/**
	 * AJAX: record a brand click (increments the brand's click counter).
	 *
	 * Public, nonce-checked, non-destructive (increments one integer term meta
	 * on an existing product_brand term). Feeds the Trending Brands ordering.
	 *
	 * @since 1.2.0
	 */
	public function ajax_track_click(): void {
		check_ajax_referer( 'jpwbc_front_nonce', 'nonce' );

		$brand_id = isset( $_POST['brand_id'] ) ? absint( wp_unslash( $_POST['brand_id'] ) ) : 0;
		if ( $brand_id <= 0 || ! jpwbc_woocommerce_ready() ) {
			wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
		}

		$term = get_term( $brand_id, JPWBC_BRAND_TAXONOMY );
		if ( ! $term instanceof \WP_Term ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}

		$clicks = (int) get_term_meta( $brand_id, self::CLICKS_META, true );
		update_term_meta( $brand_id, self::CLICKS_META, $clicks + 1 );

		wp_send_json_success( array( 'clicks' => $clicks + 1 ) );
	}
}
