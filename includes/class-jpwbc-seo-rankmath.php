<?php
/**
 * SEO integration (Rank Math)
 *
 * On a valid clean combo view ("/brands/{brand}/{category}/") this sets the
 * title, meta description, self-canonical, H1, breadcrumb trail and intro copy,
 * and forces noindex,follow on combos below the index threshold. It also points
 * the canonical of legacy "?product_cat=" hits at the matching clean URL.
 *
 * All Rank Math filters degrade to no-ops when Rank Math is not installed; the
 * archive-title / breadcrumb hooks are WordPress/WooCommerce core.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rank Math SEO handler.
 *
 * @since 1.0.0
 */
class JPWBC_SEO_RankMath {

	/**
	 * Query handler.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Query
	 */
	private JPWBC_Query $query;

	/**
	 * Rewrite handler.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Rewrites
	 */
	private JPWBC_Rewrites $rewrites;

	/**
	 * Plugin settings.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * Memoised combo context: false = not computed, null = not a combo, array = context.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>|null|false
	 */
	private array|null|false $combo = false;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param JPWBC_Query    $query    Query handler.
	 * @param JPWBC_Rewrites $rewrites Rewrite handler.
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	public function __construct( JPWBC_Query $query, JPWBC_Rewrites $rewrites, array $settings ) {
		$this->query    = $query;
		$this->rewrites = $rewrites;
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		// Rank Math (no-ops when RM absent).
		add_filter( 'rank_math/frontend/title', array( $this, 'filter_title' ) );
		add_filter( 'rank_math/frontend/description', array( $this, 'filter_description' ) );
		add_filter( 'rank_math/frontend/canonical', array( $this, 'filter_canonical' ) );
		add_filter( 'rank_math/frontend/robots', array( $this, 'filter_robots' ) );
		add_filter( 'rank_math/frontend/breadcrumb/items', array( $this, 'filter_breadcrumb' ), 10, 2 );

		// Core fallbacks for the H1 / archive title and intro copy.
		add_filter( 'get_the_archive_title', array( $this, 'filter_archive_title' ), 20 );
		add_action( 'woocommerce_archive_description', array( $this, 'render_intro' ), 25 );
	}

	/**
	 * Resolve and memoise the current combo context.
	 *
	 * @since 1.0.0
	 *
	 * @return array{brand:\WP_Term, cat:array<string,mixed>, count:int, indexable:bool}|null
	 */
	private function combo(): ?array {
		if ( false !== $this->combo ) {
			return $this->combo;
		}

		$this->combo = null;

		if ( ! jpwbc_woocommerce_ready() || ! $this->rewrites->is_combo_request() ) {
			return null;
		}

		$cat_slug = sanitize_title( (string) get_query_var( JPWBC_QUERY_VAR ) );
		$obj      = get_queried_object();
		if ( '' === $cat_slug || ! $obj instanceof \WP_Term || JPWBC_BRAND_TAXONOMY !== $obj->taxonomy ) {
			return null;
		}

		$cat = $this->query->get_category_in_brand( (int) $obj->term_id, $cat_slug );
		if ( null === $cat ) {
			return null;
		}

		$threshold   = max( 1, (int) ( $this->settings['index_min_products'] ?? 4 ) );
		$this->combo = array(
			'brand'     => $obj,
			'cat'       => $cat,
			'count'     => (int) $cat['count'],
			'indexable' => (int) $cat['count'] >= $threshold,
		);

		return $this->combo;
	}

	/**
	 * Replace {brand} {category} {count} tokens.
	 *
	 * @since 1.0.0
	 *
	 * @param string                $template Template string.
	 * @param array<string, mixed>  $combo    Combo context.
	 * @return string
	 */
	private function tokens( string $template, array $combo ): string {
		return strtr(
			$template,
			array(
				'{brand}'    => $combo['brand']->name,
				'{category}' => (string) $combo['cat']['name'],
				'{count}'    => (string) (int) $combo['count'],
			)
		);
	}

	/**
	 * Filter the SEO title on combo views.
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Current title.
	 * @return string
	 */
	public function filter_title( $title ) {
		$combo = $this->combo();
		if ( null === $combo ) {
			return $title;
		}
		$tpl = (string) ( $this->settings['title_template'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			return $title;
		}
		return wp_strip_all_tags( $this->tokens( $tpl, $combo ) );
	}

	/**
	 * Filter the meta description on combo views.
	 *
	 * @since 1.0.0
	 *
	 * @param string $description Current description.
	 * @return string
	 */
	public function filter_description( $description ) {
		$combo = $this->combo();
		if ( null === $combo ) {
			return $description;
		}
		$tpl = (string) ( $this->settings['intro_template'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			return $description;
		}
		// Return plain text: Rank Math is the sole consumer of this filter and
		// escapes the <meta> attribute itself, so escaping here would double-encode.
		return wp_strip_all_tags( $this->tokens( $tpl, $combo ) );
	}

	/**
	 * Self-canonical on combo views; clean-URL canonical for ?product_cat= hits.
	 *
	 * @since 1.0.0
	 *
	 * @param string $canonical Current canonical URL.
	 * @return string
	 */
	public function filter_canonical( $canonical ) {
		$combo = $this->combo();
		if ( null !== $combo ) {
			return $this->rewrites->combo_url( $combo['brand']->slug, (string) $combo['cat']['slug'] );
		}

		return $this->legacy_param_canonical( $canonical );
	}

	/**
	 * Canonicalise legacy "?product_cat=" hits on a brand archive.
	 *
	 * @since 1.0.0
	 *
	 * @param string $canonical Current canonical URL.
	 * @return string
	 */
	private function legacy_param_canonical( string $canonical ): string {
		if ( empty( $this->settings['clean_urls'] ) || ! jpwbc_woocommerce_ready() || ! is_tax( JPWBC_BRAND_TAXONOMY ) ) {
			return $canonical;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only canonicalisation of a public GET param.
		$param = isset( $_GET['product_cat'] ) ? sanitize_title( wp_unslash( (string) $_GET['product_cat'] ) ) : '';
		if ( '' === $param ) {
			return $canonical;
		}

		$obj = get_queried_object();
		if ( ! $obj instanceof \WP_Term || JPWBC_BRAND_TAXONOMY !== $obj->taxonomy ) {
			return $canonical;
		}

		if ( $this->query->get_combo_count( (int) $obj->term_id, $param ) < 1 ) {
			return $canonical;
		}

		return $this->rewrites->combo_url( $obj->slug, $param );
	}

	/**
	 * Force noindex,follow on thin combos.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, string> $robots Robots directives.
	 * @return array<string, string>
	 */
	public function filter_robots( $robots ) {
		$combo = $this->combo();
		if ( null === $combo || $combo['indexable'] ) {
			return is_array( $robots ) ? $robots : array();
		}

		$robots           = is_array( $robots ) ? $robots : array();
		$robots['index']  = 'noindex';
		$robots['follow'] = 'follow';
		return $robots;
	}

	/**
	 * Append the category crumb to the Rank Math breadcrumb trail.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<int, string>> $crumbs Breadcrumb items.
	 * @param mixed                           $class  Breadcrumb class instance (unused).
	 * @return array<int, array<int, string>>
	 */
	public function filter_breadcrumb( $crumbs, $class = null ) {
		$combo = $this->combo();
		if ( null === $combo || ! is_array( $crumbs ) ) {
			return $crumbs;
		}

		$crumbs[] = array(
			(string) $combo['cat']['name'],
			$this->rewrites->combo_url( $combo['brand']->slug, (string) $combo['cat']['slug'] ),
		);

		return $crumbs;
	}

	/**
	 * Filter the archive H1 on combo views to "{Brand} – {Category}".
	 *
	 * @since 1.0.0
	 *
	 * @param string $title Archive title (may contain a "Brand:" prefix span).
	 * @return string
	 */
	public function filter_archive_title( $title ) {
		$combo = $this->combo();
		if ( null === $combo ) {
			return $title;
		}
		return esc_html( $combo['brand']->name . ' – ' . (string) $combo['cat']['name'] );
	}

	/**
	 * Render the templated intro paragraph on indexable combo views.
	 *
	 * @since 1.0.0
	 */
	public function render_intro(): void {
		$combo = $this->combo();
		if ( null === $combo || empty( $combo['indexable'] ) ) {
			return;
		}
		$tpl = (string) ( $this->settings['intro_template'] ?? '' );
		if ( '' === trim( $tpl ) ) {
			return;
		}
		echo '<div class="jpwbc-combo-intro">' . esc_html( $this->tokens( $tpl, $combo ) ) . '</div>';
	}
}
