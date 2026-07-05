<?php
/**
 * Template: current-brand category filter as a horizontal chip/pill row.
 *
 * Myer-style row of pills: "All {Brand}" + one chip per product category the
 * brand has products in, each linking to the clean /brands/{brand}/{category}/
 * combo URL. The active category (or "All" when unfiltered) is highlighted.
 *
 * Override by copying to {theme}/jezpress-woo-brand-categories/brand-category-chips.php
 *
 * @package JezPress\WooBrandCategories
 * @since   1.12.0
 *
 * @var array $data {
 *     @type array       $settings        Plugin settings.
 *     @type array|null  $current_brand   { term_id, name, slug, url } or null.
 *     @type array       $categories      Rows: name, slug, count, url, is_active.
 *     @type string      $active_cat_slug Active category slug ('' = All).
 *     @type string      $all_text        Label for the "all" chip; "{brand}" is replaced with the brand name.
 *     @type bool        $show_all        Show the leading "All {brand}" chip.
 *     @type bool        $show_counts     Show a "(N)" product count in each chip.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jpwbc_current = isset( $data['current_brand'] ) && is_array( $data['current_brand'] ) ? $data['current_brand'] : null;
$jpwbc_cats    = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
$jpwbc_active  = isset( $data['active_cat_slug'] ) ? (string) $data['active_cat_slug'] : '';
$jpwbc_all_on  = ! isset( $data['show_all'] ) || ! empty( $data['show_all'] );
$jpwbc_counts  = ! empty( $data['show_counts'] );

if ( null === $jpwbc_current || empty( $jpwbc_cats ) ) {
	return;
}

$jpwbc_brand_name = isset( $jpwbc_current['name'] ) ? (string) $jpwbc_current['name'] : '';
$jpwbc_brand_url  = isset( $jpwbc_current['url'] ) ? (string) $jpwbc_current['url'] : '';
$jpwbc_all_text   = isset( $data['all_text'] ) && '' !== (string) $data['all_text']
	? (string) $data['all_text']
	: __( 'All {brand}', 'jezpress-woo-brand-categories' );
$jpwbc_all_label  = str_replace( '{brand}', $jpwbc_brand_name, $jpwbc_all_text );

// "All" is active when no category filter is applied.
$jpwbc_all_active = ( '' === $jpwbc_active );
?>
<nav class="jpwbc-brand-cats jpwbc-chips" aria-label="<?php esc_attr_e( 'Filter by category', 'jezpress-woo-brand-categories' ); ?>">
	<?php if ( $jpwbc_all_on && '' !== $jpwbc_brand_url ) : ?>
		<a class="jpwbc-chip jpwbc-chip--all<?php echo $jpwbc_all_active ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( $jpwbc_brand_url ); ?>"
			<?php echo $jpwbc_all_active ? 'aria-current="page"' : ''; ?>>
			<span class="jpwbc-chip__label"><?php echo esc_html( $jpwbc_all_label ); ?></span>
		</a>
	<?php endif; ?>

	<?php foreach ( $jpwbc_cats as $jpwbc_cat ) : ?>
		<?php
		$jpwbc_cat_url = isset( $jpwbc_cat['url'] ) ? (string) $jpwbc_cat['url'] : '';
		if ( '' === $jpwbc_cat_url ) {
			continue;
		}
		$jpwbc_is_active = ! empty( $jpwbc_cat['is_active'] );
		?>
		<a class="jpwbc-chip<?php echo $jpwbc_is_active ? ' is-active' : ''; ?>"
			href="<?php echo esc_url( $jpwbc_cat_url ); ?>"
			<?php echo $jpwbc_is_active ? 'aria-current="page"' : ''; ?>>
			<span class="jpwbc-chip__label"><?php echo esc_html( (string) $jpwbc_cat['name'] ); ?></span>
			<?php if ( $jpwbc_counts ) : ?>
				<span class="jpwbc-chip__count"><?php echo esc_html( (string) (int) $jpwbc_cat['count'] ); ?></span>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</nav>
