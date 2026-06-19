<?php
/**
 * Partial: the category list for a single brand.
 *
 * Reused by the dropdown template (current brand) and the AJAX lazy-expand
 * endpoint (other brands).
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 *
 * @var array $data {
 *     @type array  $settings        Plugin settings (uses show_counts).
 *     @type array  $categories      Rows: name, slug, count, url, is_active.
 *     @type array  $brand           { name, slug }.
 *     @type string $reset_url        URL of the unfiltered brand archive.
 *     @type string $active_cat_slug  Active category slug.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jpwbc_settings    = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
$jpwbc_categories  = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
$jpwbc_brand       = isset( $data['brand'] ) && is_array( $data['brand'] ) ? $data['brand'] : array( 'name' => '' );
$jpwbc_reset_url   = isset( $data['reset_url'] ) ? (string) $data['reset_url'] : '';
$jpwbc_show_counts = ! empty( $jpwbc_settings['show_counts'] );
$jpwbc_active      = isset( $data['active_cat_slug'] ) ? (string) $data['active_cat_slug'] : '';

if ( empty( $jpwbc_categories ) ) {
	echo '<p class="jpwbc-empty">' . esc_html__( 'No categories found for this brand.', 'jezpress-woo-brand-categories' ) . '</p>';
	return;
}
?>
<ul class="jpwbc-cats">
	<?php if ( '' !== $jpwbc_reset_url ) : ?>
		<li class="jpwbc-cat jpwbc-cat--all <?php echo '' === $jpwbc_active ? 'is-active' : ''; ?>">
			<a href="<?php echo esc_url( $jpwbc_reset_url ); ?>" <?php echo '' === $jpwbc_active ? 'aria-current="page"' : ''; ?>>
				<?php
				/* translators: %s: brand name */
				printf( esc_html__( 'All %s products', 'jezpress-woo-brand-categories' ), esc_html( (string) ( $jpwbc_brand['name'] ?? '' ) ) );
				?>
			</a>
		</li>
	<?php endif; ?>

	<?php foreach ( $jpwbc_categories as $jpwbc_cat ) : ?>
		<?php $jpwbc_is_active = ! empty( $jpwbc_cat['is_active'] ); ?>
		<li class="jpwbc-cat <?php echo $jpwbc_is_active ? 'is-active' : ''; ?>">
			<a href="<?php echo esc_url( (string) $jpwbc_cat['url'] ); ?>" <?php echo $jpwbc_is_active ? 'aria-current="page"' : ''; ?>>
				<span class="jpwbc-cat__name"><?php echo esc_html( (string) $jpwbc_cat['name'] ); ?></span>
				<?php if ( $jpwbc_show_counts ) : ?>
					<span class="jpwbc-cat__count"><?php echo esc_html( (string) (int) $jpwbc_cat['count'] ); ?></span>
				<?php endif; ?>
			</a>
		</li>
	<?php endforeach; ?>
</ul>
