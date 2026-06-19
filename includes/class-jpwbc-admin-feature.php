<?php
/**
 * Admin feature glue
 *
 * Renders the Combo Preview and Cache settings tabs and handles the cache
 * rebuild action. Kept separate from JPWBC_Admin (which owns the settings
 * option) because this side depends on the WooCommerce-only query/cache layer.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin feature handler.
 *
 * @since 1.0.0
 */
class JPWBC_Admin_Feature {

	/**
	 * Query handler.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Query
	 */
	private JPWBC_Query $query;

	/**
	 * Cache handler.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Cache
	 */
	private JPWBC_Cache $cache;

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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param JPWBC_Query    $query    Query handler.
	 * @param JPWBC_Cache    $cache    Cache handler.
	 * @param JPWBC_Rewrites $rewrites Rewrite handler.
	 * @param array<string, mixed> $settings Plugin settings.
	 */
	public function __construct( JPWBC_Query $query, JPWBC_Cache $cache, JPWBC_Rewrites $rewrites, array $settings ) {
		$this->query    = $query;
		$this->cache    = $cache;
		$this->rewrites = $rewrites;
		$this->settings = $settings;
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_action( 'jpwbc_render_preview_tab', array( $this, 'render_preview_tab' ) );
		add_action( 'jpwbc_render_cache_tab', array( $this, 'render_cache_tab' ) );
		add_action( 'admin_post_jpwbc_rebuild_cache', array( $this, 'handle_rebuild' ) );
	}

	/**
	 * Handle the "Rebuild caches" POST.
	 *
	 * @since 1.0.0
	 */
	public function handle_rebuild(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jezpress-woo-brand-categories' ), 403 );
		}

		check_admin_referer( 'jpwbc_rebuild_cache' );

		$this->cache->rebuild();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'jpwbc-settings',
					'tab'       => 'cache',
					'jpwbc_msg' => 'rebuilt',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render the Combo Preview tab.
	 *
	 * @since 1.0.0
	 */
	public function render_preview_tab(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! jpwbc_woocommerce_ready() ) {
			echo '<p>' . esc_html__( 'WooCommerce and the product_brand taxonomy must be active.', 'jezpress-woo-brand-categories' ) . '</p>';
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only brand selector for an admin preview.
		$selected = isset( $_GET['jpwbc_brand'] ) ? absint( wp_unslash( $_GET['jpwbc_brand'] ) ) : 0;

		$brands = get_terms(
			array(
				'taxonomy'   => JPWBC_BRAND_TAXONOMY,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		if ( is_wp_error( $brands ) ) {
			$brands = array();
		}
		?>
		<p><?php esc_html_e( 'Preview the categories, counts, generated URLs and indexing status for a brand.', 'jezpress-woo-brand-categories' ); ?></p>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="page" value="jpwbc-settings">
			<input type="hidden" name="tab" value="preview">
			<label for="jpwbc_brand"><?php esc_html_e( 'Brand:', 'jezpress-woo-brand-categories' ); ?></label>
			<select name="jpwbc_brand" id="jpwbc_brand" onchange="this.form.submit()">
				<option value="0"><?php esc_html_e( '— Select a brand —', 'jezpress-woo-brand-categories' ); ?></option>
				<?php foreach ( $brands as $brand ) : ?>
					<?php if ( $brand instanceof \WP_Term ) : ?>
						<option value="<?php echo esc_attr( (string) $brand->term_id ); ?>" <?php selected( $selected, $brand->term_id ); ?>>
							<?php echo esc_html( $brand->name ); ?>
						</option>
					<?php endif; ?>
				<?php endforeach; ?>
			</select>
		</form>

		<?php
		if ( $selected > 0 ) {
			$this->render_preview_table( $selected );
		}
	}

	/**
	 * Render the preview table for a selected brand.
	 *
	 * @since 1.0.0
	 *
	 * @param int $brand_id Brand term id.
	 */
	private function render_preview_table( int $brand_id ): void {
		$brand = get_term( $brand_id, JPWBC_BRAND_TAXONOMY );
		if ( ! $brand instanceof \WP_Term ) {
			echo '<p>' . esc_html__( 'Brand not found.', 'jezpress-woo-brand-categories' ) . '</p>';
			return;
		}

		$rows      = $this->query->get_brand_categories( $brand_id );
		$threshold = max( 1, (int) ( $this->settings['index_min_products'] ?? 4 ) );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'This brand has no categories with published products.', 'jezpress-woo-brand-categories' ) . '</p>';
			return;
		}
		?>
		<table class="widefat striped" style="margin-top:1em;max-width:900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Category', 'jezpress-woo-brand-categories' ); ?></th>
					<th><?php esc_html_e( 'Products', 'jezpress-woo-brand-categories' ); ?></th>
					<th><?php esc_html_e( 'URL', 'jezpress-woo-brand-categories' ); ?></th>
					<th><?php esc_html_e( 'SEO status', 'jezpress-woo-brand-categories' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach ( $rows as $row ) :
					$url       = $this->rewrites->combo_url( $brand->slug, (string) $row['slug'] );
					$indexable = (int) $row['count'] >= $threshold;
					?>
					<tr>
						<td><?php echo esc_html( (string) $row['name'] ); ?></td>
						<td><?php echo esc_html( (string) (int) $row['count'] ); ?></td>
						<td><a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $url ); ?></a></td>
						<td>
							<?php if ( $indexable ) : ?>
								<span style="color:#00a32a;font-weight:600;"><?php esc_html_e( 'Indexed', 'jezpress-woo-brand-categories' ); ?></span>
							<?php else : ?>
								<span style="color:#dba617;"><?php esc_html_e( 'No-index', 'jezpress-woo-brand-categories' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php
			/* translators: %d: index threshold */
			printf( esc_html__( 'Categories with fewer than %d products render but are set to noindex,follow.', 'jezpress-woo-brand-categories' ), (int) $threshold );
			?>
		</p>
		<?php
	}

	/**
	 * Render the Cache tab.
	 *
	 * @since 1.0.0
	 */
	public function render_cache_tab(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only success flag after a nonce-protected redirect.
		if ( isset( $_GET['jpwbc_msg'] ) && 'rebuilt' === sanitize_key( wp_unslash( $_GET['jpwbc_msg'] ) ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Caches rebuilt.', 'jezpress-woo-brand-categories' ) . '</p></div>';
		}

		$has_object_cache = $this->cache->has_object_cache();
		$last             = $this->cache->last_rebuilt();
		?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Persistent object cache', 'jezpress-woo-brand-categories' ); ?></th>
				<td>
					<?php if ( $has_object_cache ) : ?>
						<span style="color:#00a32a;font-weight:600;"><?php esc_html_e( 'Active', 'jezpress-woo-brand-categories' ); ?></span>
						<p class="description"><?php esc_html_e( 'Brand-category lookups are served from the object cache (e.g. Redis).', 'jezpress-woo-brand-categories' ); ?></p>
					<?php else : ?>
						<span style="color:#dba617;"><?php esc_html_e( 'Not detected', 'jezpress-woo-brand-categories' ); ?></span>
						<p class="description"><?php esc_html_e( 'Lookups are cached in the options table via transients.', 'jezpress-woo-brand-categories' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Last rebuilt', 'jezpress-woo-brand-categories' ); ?></th>
				<td>
					<?php
					if ( $last > 0 ) {
						echo esc_html(
							sprintf(
								/* translators: %s: human time diff */
								__( '%s ago', 'jezpress-woo-brand-categories' ),
								human_time_diff( $last, time() )
							)
						);
					} else {
						esc_html_e( 'Never', 'jezpress-woo-brand-categories' );
					}
					?>
				</td>
			</tr>
		</table>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jpwbc_rebuild_cache' ); ?>
			<input type="hidden" name="action" value="jpwbc_rebuild_cache">
			<?php submit_button( __( 'Rebuild caches', 'jezpress-woo-brand-categories' ), 'secondary' ); ?>
		</form>
		<?php
	}
}
