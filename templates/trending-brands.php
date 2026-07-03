<?php
/**
 * Template: Trending Brands.
 *
 * Override by copying to {theme}/jezpress-woo-brand-categories/trending-brands.php
 *
 * @package JezPress\WooBrandCategories
 * @since   1.2.0
 *
 * @var array $data {
 *     @type string $title      Heading.
 *     @type bool   $show_arrow Whether to show the arrow after the heading.
 *     @type array  $brands     Rows: term_id, name, slug, url, clicks.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jpwbc_title  = isset( $data['title'] ) ? (string) $data['title'] : '';
$jpwbc_arrow  = ! empty( $data['show_arrow'] );
$jpwbc_brands = isset( $data['brands'] ) && is_array( $data['brands'] ) ? $data['brands'] : array();

if ( empty( $jpwbc_brands ) ) {
	return;
}
?>
<div class="jpwbc-brand-cats jpwbc-trending">
	<?php if ( '' !== $jpwbc_title ) : ?>
		<h3 class="jpwbc-trending__title">
			<?php echo esc_html( $jpwbc_title ); ?>
			<?php if ( $jpwbc_arrow ) : ?>
				<span class="jpwbc-arrow" aria-hidden="true"></span>
			<?php endif; ?>
		</h3>
	<?php endif; ?>

	<ul class="jpwbc-trending__list">
		<?php foreach ( $jpwbc_brands as $jpwbc_brand ) : ?>
			<?php if ( '' === (string) $jpwbc_brand['url'] ) { continue; } ?>
			<li class="jpwbc-trending__item">
				<a href="<?php echo esc_url( (string) $jpwbc_brand['url'] ); ?>"
					data-jpwbc-brand="<?php echo esc_attr( (string) (int) $jpwbc_brand['term_id'] ); ?>">
					<?php echo esc_html( (string) $jpwbc_brand['name'] ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
