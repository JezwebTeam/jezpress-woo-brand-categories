<?php
/**
 * Template: the "Our Brands" sidebar with the in-brand category dropdown.
 *
 * Renders the full A-Z brand list; the current brand is expanded inline with
 * its categories, and every other brand gets a toggle that lazy-loads its
 * categories over AJAX (built-in as of 1.0.5; see assets/js/jpwbc.js).
 *
 * Override by copying to {theme}/jezpress-woo-brand-categories/brand-categories-dropdown.php
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 *
 * @var array $data {
 *     @type array       $settings        Plugin settings.
 *     @type array       $brands          Rows: term_id, name, slug, url, is_current.
 *     @type array|null  $current_brand   { term_id, name, slug, url } or null.
 *     @type array       $categories      Current brand's category rows.
 *     @type string      $active_cat_slug Active category slug.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jpwbc_settings  = isset( $data['settings'] ) && is_array( $data['settings'] ) ? $data['settings'] : array();
$jpwbc_brands    = isset( $data['brands'] ) && is_array( $data['brands'] ) ? $data['brands'] : array();
$jpwbc_current   = isset( $data['current_brand'] ) && is_array( $data['current_brand'] ) ? $data['current_brand'] : null;
$jpwbc_cats      = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
$jpwbc_active    = isset( $data['active_cat_slug'] ) ? (string) $data['active_cat_slug'] : '';
$jpwbc_clickable = true; // All brands are expandable (built-in behaviour as of 1.0.5).
$jpwbc_expand    = ! empty( $jpwbc_settings['expand_active'] );
$jpwbc_search    = ! empty( $jpwbc_settings['brand_search'] );

if ( empty( $jpwbc_brands ) ) {
	return;
}
?>
<div class="jpwbc-brand-cats" data-clickable="<?php echo $jpwbc_clickable ? '1' : '0'; ?>">
	<?php if ( $jpwbc_search ) : ?>
		<div class="jpwbc-search">
			<label class="screen-reader-text" for="jpwbc-search-input"><?php esc_html_e( 'Search brands', 'jezpress-woo-brand-categories' ); ?></label>
			<input type="search" id="jpwbc-search-input" class="jpwbc-search__input" autocomplete="off"
				placeholder="<?php esc_attr_e( 'Search brands…', 'jezpress-woo-brand-categories' ); ?>">
		</div>
	<?php endif; ?>

	<ul class="jpwbc-brands">
		<?php foreach ( $jpwbc_brands as $jpwbc_brand ) : ?>
			<?php
			$jpwbc_is_current  = ! empty( $jpwbc_brand['is_current'] );
			$jpwbc_expandable  = $jpwbc_is_current || $jpwbc_clickable;
			$jpwbc_is_open     = $jpwbc_is_current && $jpwbc_expand;
			$jpwbc_li_classes  = 'jpwbc-brand';
			$jpwbc_li_classes .= $jpwbc_is_current ? ' is-current' : '';
			$jpwbc_li_classes .= $jpwbc_is_open ? ' is-open' : '';
			$jpwbc_li_classes .= $jpwbc_expandable ? ' is-expandable' : '';
			?>
			<li class="<?php echo esc_attr( $jpwbc_li_classes ); ?>" data-brand-id="<?php echo esc_attr( (string) (int) $jpwbc_brand['term_id'] ); ?>">
				<div class="jpwbc-brand__row">
					<a class="jpwbc-brand__link" href="<?php echo esc_url( (string) $jpwbc_brand['url'] ); ?>">
						<?php echo esc_html( (string) $jpwbc_brand['name'] ); ?>
					</a>
					<?php if ( $jpwbc_expandable ) : ?>
						<button type="button" class="jpwbc-brand__toggle"
							aria-expanded="<?php echo $jpwbc_is_open ? 'true' : 'false'; ?>"
							aria-label="<?php
								/* translators: %s: brand name */
								echo esc_attr( sprintf( __( 'Toggle %s categories', 'jezpress-woo-brand-categories' ), (string) $jpwbc_brand['name'] ) );
							?>">
							<span class="jpwbc-chevron" aria-hidden="true"></span>
						</button>
					<?php endif; ?>
				</div>

				<?php if ( $jpwbc_is_current ) : ?>
					<div class="jpwbc-brand__panel" <?php echo $jpwbc_is_open ? '' : 'hidden'; ?>>
						<?php
						echo jpwbc_get_template( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- partial returns escaped output.
							'brand-categories-list.php',
							array(
								'settings'        => $jpwbc_settings,
								'categories'      => $jpwbc_cats,
								'brand'           => array( 'name' => $jpwbc_current['name'] ?? '', 'slug' => $jpwbc_current['slug'] ?? '' ),
								'reset_url'       => $jpwbc_current['url'] ?? '',
								'active_cat_slug' => $jpwbc_active,
							)
						);
						?>
					</div>
				<?php elseif ( $jpwbc_clickable ) : ?>
					<div class="jpwbc-brand__panel" data-lazy="1" hidden></div>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
