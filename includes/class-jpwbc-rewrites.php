<?php
/**
 * Rewrites + query filtering
 *
 * Registers the clean `/{brand-base}/{brand}/{category}/` combo URLs, exposes
 * the `jpwbc_cat` query var, and on a matched combo adds an AND product_cat
 * clause to the brand archive's main query. Invalid or empty combos 404.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrite + query handler.
 *
 * @since 1.0.0
 */
class JPWBC_Rewrites {

	/**
	 * Query handler.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Query
	 */
	private JPWBC_Query $query;

	/**
	 * Whether clean combo URLs are enabled.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $clean_urls;

	/**
	 * Marks the current request as an invalid/empty combo to be 404'd.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $invalid_combo = false;

	/**
	 * The validated combo category slug for this request ('' = not a combo).
	 *
	 * Set once, on the main query, after the brand + category pairing has been
	 * validated. Secondary product queries (the Elementor Products widget's
	 * loop and its pagination query) reuse it instead of re-validating — they
	 * never carry the jpwbc_cat query var, since query vars come from the URL
	 * for the main query only.
	 *
	 * @since 1.19.3
	 * @var string
	 */
	private string $active_cat = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param JPWBC_Query $query      Query handler.
	 * @param bool        $clean_urls Whether clean combo URLs are enabled.
	 */
	public function __construct( JPWBC_Query $query, bool $clean_urls ) {
		$this->query      = $query;
		$this->clean_urls = $clean_urls;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );

		if ( $this->clean_urls ) {
			add_action( 'init', array( $this, 'add_rewrite_rules' ), 20 );
		}

		add_action( 'pre_get_posts', array( $this, 'filter_archive_query' ) );
		add_action( 'template_redirect', array( $this, 'maybe_404' ) );

		// Flush rules when settings (e.g. the clean-URL toggle) are saved.
		add_action( 'jpwbc_settings_saved', array( $this, 'on_settings_saved' ) );
	}

	/**
	 * Expose the jpwbc_cat query var.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $vars Query vars.
	 * @return array<int, string>
	 */
	public function add_query_var( array $vars ): array {
		$vars[] = JPWBC_QUERY_VAR;
		return $vars;
	}

	/**
	 * The brand archive base segment (e.g. "brands"), from the live taxonomy.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function brand_base(): string {
		$tax = get_taxonomy( JPWBC_BRAND_TAXONOMY );
		if ( $tax && is_array( $tax->rewrite ) && ! empty( $tax->rewrite['slug'] ) ) {
			return trim( (string) $tax->rewrite['slug'], '/' );
		}
		return 'brands';
	}

	/**
	 * Register the combo rewrite rules (paged variant first).
	 *
	 * @since 1.0.0
	 */
	public function add_rewrite_rules(): void {
		$base = preg_quote( $this->brand_base(), '#' );

		add_rewrite_rule(
			'^' . $base . '/([^/]+)/([^/]+)/page/?([0-9]{1,})/?$',
			'index.php?' . JPWBC_BRAND_TAXONOMY . '=$matches[1]&' . JPWBC_QUERY_VAR . '=$matches[2]&paged=$matches[3]',
			'top'
		);

		add_rewrite_rule(
			'^' . $base . '/([^/]+)/([^/]+)/?$',
			'index.php?' . JPWBC_BRAND_TAXONOMY . '=$matches[1]&' . JPWBC_QUERY_VAR . '=$matches[2]',
			'top'
		);
	}

	/**
	 * Add the AND product_cat clause on a matched brand archive.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Query $wp_query The query being prepared.
	 */
	public function filter_archive_query( \WP_Query $wp_query ): void {
		if ( is_admin() ) {
			return;
		}

		// Secondary product queries on the combo archive — the Elementor Products
		// widget runs its own loop query AND a separate pagination query, neither
		// of which carries jpwbc_cat. Without this the grid shows the filtered
		// products while the pagination still counts every product in the brand.
		if ( ! $wp_query->is_main_query() ) {
			if ( '' === $this->active_cat
				|| ! in_array( 'product', (array) $wp_query->get( 'post_type' ), true )
				|| ! is_tax( JPWBC_BRAND_TAXONOMY ) ) {
				return;
			}
			// Hand-picked product queries (related products, up-sells, cross-sells,
			// a manual Elementor selection) name their posts outright — they are
			// not the archive loop and must not be narrowed to the combo category.
			// (no_found_rows is deliberately not used as a second signal: it would
			// also exclude an archive loop that has pagination switched off.)
			if ( ! empty( $wp_query->get( 'post__in' ) ) ) {
				return;
			}
			$this->append_cat_clause( $wp_query, $this->active_cat );
			return;
		}

		$cat_slug = $wp_query->get( JPWBC_QUERY_VAR );
		if ( '' === $cat_slug || null === $cat_slug ) {
			return;
		}

		$cat_slug   = sanitize_title( (string) $cat_slug );
		$brand_slug = sanitize_title( (string) $wp_query->get( JPWBC_BRAND_TAXONOMY ) );

		if ( '' === $brand_slug || ! jpwbc_woocommerce_ready() ) {
			$this->invalid_combo = true;
			return;
		}

		$brand = get_term_by( 'slug', $brand_slug, JPWBC_BRAND_TAXONOMY );
		if ( ! $brand instanceof \WP_Term ) {
			$this->invalid_combo = true;
			return;
		}

		// The combo must be a real, non-empty brand+category pairing.
		if ( $this->query->get_combo_count( (int) $brand->term_id, $cat_slug ) < 1 ) {
			$this->invalid_combo = true;
			return;
		}

		$this->active_cat = $cat_slug;
		$this->append_cat_clause( $wp_query, $cat_slug );
	}

	/**
	 * Append the AND product_cat clause for a combo category to a query.
	 *
	 * @since 1.19.3
	 *
	 * @param \WP_Query $wp_query The query being prepared.
	 * @param string    $cat_slug Validated category slug.
	 */
	private function append_cat_clause( \WP_Query $wp_query, string $cat_slug ): void {
		$tax_query = (array) $wp_query->get( 'tax_query' );

		// The Elementor Products widget's "Current Query" mode inherits the main
		// query's vars, so its loop query already carries this clause. Adding it
		// again would only cost a duplicate term-relationship JOIN.
		if ( $this->has_cat_clause( $tax_query, $cat_slug ) ) {
			return;
		}

		$wp_query->set(
			'tax_query',
			jpwbc_tax_query_and(
				$tax_query,
				array(
					array(
						'taxonomy' => JPWBC_CAT_TAXONOMY,
						'field'    => 'slug',
						'terms'    => $cat_slug,
					),
				)
			)
		);
	}

	/**
	 * Whether a tax_query already restricts results to a category slug.
	 *
	 * Walks nested clause groups, and only counts an including clause — a
	 * NOT IN / NOT EXISTS clause naming the same slug excludes the category
	 * rather than selecting it, so our clause is still needed.
	 *
	 * @since 1.19.3
	 *
	 * @param array<int|string, mixed> $tax_query A tax_query or clause group.
	 * @param string                   $cat_slug  Category slug to look for.
	 * @return bool
	 */
	private function has_cat_clause( array $tax_query, string $cat_slug ): bool {
		foreach ( $tax_query as $key => $clause ) {
			if ( 'relation' === $key || ! is_array( $clause ) ) {
				continue;
			}

			// A nested clause group — recurse.
			if ( ! isset( $clause['taxonomy'] ) ) {
				if ( $this->has_cat_clause( $clause, $cat_slug ) ) {
					return true;
				}
				continue;
			}

			$operator = strtoupper( (string) ( $clause['operator'] ?? 'IN' ) );
			if ( 'IN' !== $operator ) {
				continue;
			}

			$terms = isset( $clause['terms'] ) ? (array) $clause['terms'] : array();
			if ( JPWBC_CAT_TAXONOMY === ( $clause['taxonomy'] ?? '' )
				&& 'slug' === ( $clause['field'] ?? '' )
				&& in_array( $cat_slug, array_map( 'strval', $terms ), true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Force a 404 for invalid/empty combos.
	 *
	 * @since 1.0.0
	 */
	public function maybe_404(): void {
		if ( ! $this->invalid_combo ) {
			return;
		}

		global $wp_query;
		if ( $wp_query instanceof \WP_Query ) {
			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
		}
	}

	/**
	 * Whether the current request is a valid clean combo view.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function is_combo_request(): bool {
		$cat = get_query_var( JPWBC_QUERY_VAR );
		return ! $this->invalid_combo && '' !== $cat && null !== $cat;
	}

	/**
	 * Build a clean combo URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $brand_slug Brand slug.
	 * @param string $cat_slug   Category slug.
	 * @return string
	 */
	public function combo_url( string $brand_slug, string $cat_slug ): string {
		return user_trailingslashit(
			home_url( '/' . $this->brand_base() . '/' . rawurlencode( $brand_slug ) . '/' . rawurlencode( $cat_slug ) . '/' )
		);
	}

	/**
	 * Build a brand archive URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $brand_slug Brand slug.
	 * @return string
	 */
	public function brand_url( string $brand_slug ): string {
		$link = get_term_link( $brand_slug, JPWBC_BRAND_TAXONOMY );
		if ( ! is_wp_error( $link ) ) {
			return $link;
		}
		return user_trailingslashit( home_url( '/' . $this->brand_base() . '/' . rawurlencode( $brand_slug ) . '/' ) );
	}

	/**
	 * Flush rewrite rules after a relevant settings change.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings The new settings.
	 */
	public function on_settings_saved( array $settings ): void {
		// Force a full rebuild of the rewrite rules on the next request. The new
		// clean-URL value is read at construction next request, so this cleanly
		// adds OR removes the combo rules whichever way the toggle moved.
		delete_option( 'rewrite_rules' );
	}
}
