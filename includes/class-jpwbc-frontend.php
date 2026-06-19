<?php
/**
 * Front-end output
 *
 * Renders the in-brand category dropdown via shortcode, an optional auto-hook,
 * and (for the Elementor widget) a public render entry point. Assets load only
 * on product_brand archives. An AJAX endpoint lazily returns another brand's
 * categories when "other brands clickable" is enabled.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end handler.
 *
 * @since 1.0.0
 */
class JPWBC_Frontend {

	/**
	 * Query handler.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Query
	 */
	private JPWBC_Query $query;

	/**
	 * Rewrite handler (URL builders).
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
	 * Guards against rendering the dropdown twice on one request.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $rendered = false;

	/**
	 * Most-recent instance, for the Elementor widget to reach the renderer.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Frontend|null
	 */
	private static ?JPWBC_Frontend $instance = null;

	/**
	 * Get the active front-end instance (set on construction).
	 *
	 * @since 1.0.0
	 *
	 * @return JPWBC_Frontend|null
	 */
	public static function instance(): ?JPWBC_Frontend {
		return self::$instance;
	}

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
		self::$instance = $this;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_shortcode( 'jpwbc_brand_categories', array( $this, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		if ( 'auto_hook' === ( $this->settings['placement'] ?? 'shortcode' ) ) {
			add_action( 'woocommerce_before_shop_loop', array( $this, 'auto_render' ), 5 );
		}

		add_action( 'wp_ajax_jpwbc_brand_categories', array( $this, 'ajax_brand_categories' ) );
		add_action( 'wp_ajax_nopriv_jpwbc_brand_categories', array( $this, 'ajax_brand_categories' ) );
	}

	/**
	 * Whether the current request is a product_brand archive.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function is_brand_archive(): bool {
		return jpwbc_woocommerce_ready() && is_tax( JPWBC_BRAND_TAXONOMY );
	}

	/**
	 * Enqueue CSS/JS on brand archives only.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets(): void {
		if ( ! $this->is_brand_archive() ) {
			return;
		}

		wp_enqueue_style(
			'jpwbc-frontend',
			JPWBC_PLUGIN_URL . 'assets/css/jpwbc.css',
			array(),
			JPWBC_VERSION
		);

		wp_enqueue_script(
			'jpwbc-frontend',
			JPWBC_PLUGIN_URL . 'assets/js/jpwbc.js',
			array(),
			JPWBC_VERSION,
			true
		);

		wp_localize_script(
			'jpwbc-frontend',
			'jpwbcFront',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'jpwbc_front_nonce' ),
				'reducedMotion'  => false,
				'i18n'           => array(
					'loading' => __( 'Loading…', 'jezpress-woo-brand-categories' ),
					'error'   => __( 'Could not load categories.', 'jezpress-woo-brand-categories' ),
				),
			)
		);
	}

	/**
	 * Shortcode callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>|string $atts Shortcode attributes.
	 * @return string
	 */
	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array( 'brand' => '' ),
			is_array( $atts ) ? $atts : array(),
			'jpwbc_brand_categories'
		);

		return $this->render( is_string( $atts['brand'] ) ? $atts['brand'] : '' );
	}

	/**
	 * Auto-hook callback (echoes on brand archives).
	 *
	 * @since 1.0.0
	 */
	public function auto_render(): void {
		if ( ! $this->is_brand_archive() ) {
			return;
		}
		echo $this->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() returns escaped template output.
	}

	/**
	 * Render the dropdown.
	 *
	 * @since 1.0.0
	 *
	 * @param string $brand_override Optional brand slug to treat as current.
	 * @return string
	 */
	public function render( string $brand_override = '' ): string {
		if ( $this->rendered || empty( $this->settings['enabled'] ) || ! jpwbc_woocommerce_ready() ) {
			return '';
		}
		$this->rendered = true;

		$current_brand = $this->resolve_current_brand( $brand_override );

		$brands = get_terms(
			array(
				'taxonomy'   => JPWBC_BRAND_TAXONOMY,
				'hide_empty' => true,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		);
		if ( is_wp_error( $brands ) ) {
			$brands = array();
		}

		$brand_rows = array();
		foreach ( $brands as $brand ) {
			if ( ! $brand instanceof \WP_Term ) {
				continue;
			}
			$brand_rows[] = array(
				'term_id'    => (int) $brand->term_id,
				'name'       => $brand->name,
				'slug'       => $brand->slug,
				'url'        => $this->rewrites->brand_url( $brand->slug ),
				'is_current' => $current_brand && $current_brand->term_id === $brand->term_id,
			);
		}

		$active_cat = sanitize_title( (string) get_query_var( JPWBC_QUERY_VAR ) );

		$categories = array();
		if ( $current_brand instanceof \WP_Term ) {
			$categories = $this->category_rows( $current_brand, $active_cat );
		}

		$data = array(
			'settings'        => $this->settings,
			'brands'          => $brand_rows,
			'current_brand'   => $current_brand instanceof \WP_Term
				? array(
					'term_id' => (int) $current_brand->term_id,
					'name'    => $current_brand->name,
					'slug'    => $current_brand->slug,
					'url'     => $this->rewrites->brand_url( $current_brand->slug ),
				)
				: null,
			'categories'      => $categories,
			'active_cat_slug' => $active_cat,
		);

		return jpwbc_get_template( 'brand-categories-dropdown.php', $data );
	}

	/**
	 * Build category rows (with URLs + active flag) for a brand.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Term $brand      Brand term.
	 * @param string   $active_cat Active category slug.
	 * @return array<int, array<string, mixed>>
	 */
	private function category_rows( \WP_Term $brand, string $active_cat ): array {
		$rows = array();
		foreach ( $this->query->get_brand_categories( (int) $brand->term_id ) as $cat ) {
			$rows[] = array(
				'name'      => $cat['name'],
				'slug'      => $cat['slug'],
				'count'     => (int) $cat['count'],
				'url'       => $this->rewrites->combo_url( $brand->slug, $cat['slug'] ),
				'is_active' => $cat['slug'] === $active_cat,
			);
		}
		return $rows;
	}

	/**
	 * Resolve the "current" brand from an override slug or the queried object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $brand_override Optional brand slug.
	 * @return \WP_Term|null
	 */
	private function resolve_current_brand( string $brand_override ): ?\WP_Term {
		if ( '' !== $brand_override ) {
			$term = get_term_by( 'slug', sanitize_title( $brand_override ), JPWBC_BRAND_TAXONOMY );
			return $term instanceof \WP_Term ? $term : null;
		}

		if ( $this->is_brand_archive() ) {
			$obj = get_queried_object();
			if ( $obj instanceof \WP_Term && JPWBC_BRAND_TAXONOMY === $obj->taxonomy ) {
				return $obj;
			}
		}

		return null;
	}

	/**
	 * AJAX: return a brand's category list as HTML (lazy other-brand expansion).
	 *
	 * Public, read-only endpoint — no capability required, but nonce-checked.
	 *
	 * @since 1.0.0
	 */
	public function ajax_brand_categories(): void {
		check_ajax_referer( 'jpwbc_front_nonce', 'nonce' );

		if ( ! jpwbc_woocommerce_ready() ) {
			wp_send_json_error( array( 'message' => 'unavailable' ), 400 );
		}

		$brand_id = isset( $_POST['brand_id'] ) ? absint( wp_unslash( $_POST['brand_id'] ) ) : 0;
		if ( $brand_id <= 0 ) {
			wp_send_json_error( array( 'message' => 'bad_request' ), 400 );
		}

		$brand = get_term( $brand_id, JPWBC_BRAND_TAXONOMY );
		if ( ! $brand instanceof \WP_Term ) {
			wp_send_json_error( array( 'message' => 'not_found' ), 404 );
		}

		$html = jpwbc_get_template(
			'brand-categories-list.php',
			array(
				'settings'        => $this->settings,
				'categories'      => $this->category_rows( $brand, '' ),
				'brand'           => array( 'name' => $brand->name, 'slug' => $brand->slug ),
				'reset_url'       => $this->rewrites->brand_url( $brand->slug ),
				'active_cat_slug' => '',
			)
		);

		wp_send_json_success( array( 'html' => $html ) );
	}
}
