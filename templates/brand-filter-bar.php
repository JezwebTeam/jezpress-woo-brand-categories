<?php
/**
 * Template: brand archive filter bar (Category + Price + Sort).
 *
 * Query-param based, no-JS friendly: category is a set of links to clean combo
 * URLs; price and sort are GET forms that submit back to the current view path
 * (so the active category is preserved). JavaScript, when present, auto-submits
 * the sort control on change (see assets/js/jpwbc.js).
 *
 * Override by copying to {theme}/jezpress-woo-brand-categories/brand-filter-bar.php
 *
 * @package JezPress\WooBrandCategories
 * @since   1.13.0
 *
 * @var array $data {
 *     @type array  $settings      Plugin settings.
 *     @type array  $brand         { term_id, name, slug, url }.
 *     @type array  $categories    Rows: name, slug, count, url, is_active.
 *     @type string $active_cat    Active category slug ('' = All).
 *     @type string $base_url      Current view path (brand or combo URL).
 *     @type array  $preserve      Query args to carry across category links.
 *     @type array  $price_range   { min:int, max:int }.
 *     @type float|null $current_min  Current min price filter.
 *     @type float|null $current_max  Current max price filter.
 *     @type string $current_sort  Current orderby key ('' = default).
 *     @type array  $sort_options  orderby key => label.
 *     @type bool   $show_category Show the category control.
 *     @type bool   $show_price    Show the price control.
 *     @type bool   $show_sort     Show the sort control.
 *     @type bool   $is_filtered   Whether a price/sort filter is active.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jpwbc_brand    = isset( $data['brand'] ) && is_array( $data['brand'] ) ? $data['brand'] : null;
$jpwbc_cats     = isset( $data['categories'] ) && is_array( $data['categories'] ) ? $data['categories'] : array();
$jpwbc_active   = isset( $data['active_cat'] ) ? (string) $data['active_cat'] : '';
$jpwbc_base     = isset( $data['base_url'] ) ? (string) $data['base_url'] : '';
$jpwbc_preserve = isset( $data['preserve'] ) && is_array( $data['preserve'] ) ? $data['preserve'] : array();
$jpwbc_range    = isset( $data['price_range'] ) && is_array( $data['price_range'] ) ? $data['price_range'] : array( 'min' => 0, 'max' => 0 );
$jpwbc_min      = isset( $data['current_min'] ) && null !== $data['current_min'] ? (float) $data['current_min'] : null;
$jpwbc_max      = isset( $data['current_max'] ) && null !== $data['current_max'] ? (float) $data['current_max'] : null;
$jpwbc_sort     = isset( $data['current_sort'] ) ? (string) $data['current_sort'] : '';
$jpwbc_sorts    = isset( $data['sort_options'] ) && is_array( $data['sort_options'] ) ? $data['sort_options'] : array();
$jpwbc_show_cat = ! isset( $data['show_category'] ) || ! empty( $data['show_category'] );
$jpwbc_show_pr  = ! isset( $data['show_price'] ) || ! empty( $data['show_price'] );
$jpwbc_show_srt = ! isset( $data['show_sort'] ) || ! empty( $data['show_sort'] );

if ( null === $jpwbc_brand ) {
	return;
}

$jpwbc_brand_url  = isset( $jpwbc_brand['url'] ) ? (string) $jpwbc_brand['url'] : '';
$jpwbc_range_min  = isset( $jpwbc_range['min'] ) ? (int) $jpwbc_range['min'] : 0;
$jpwbc_range_max  = isset( $jpwbc_range['max'] ) ? (int) $jpwbc_range['max'] : 0;

// Active category label for the dropdown summary.
$jpwbc_cat_label = __( 'All categories', 'jezpress-woo-brand-categories' );
foreach ( $jpwbc_cats as $jpwbc_c ) {
	if ( ! empty( $jpwbc_c['is_active'] ) ) {
		$jpwbc_cat_label = (string) $jpwbc_c['name'];
		break;
	}
}

// Order-preserving hidden fields for the price/sort forms.
$jpwbc_hidden = static function ( array $pairs ): void {
	foreach ( $pairs as $k => $v ) {
		if ( '' === (string) $v ) {
			continue;
		}
		printf(
			'<input type="hidden" name="%s" value="%s">',
			esc_attr( (string) $k ),
			esc_attr( (string) $v )
		);
	}
};

if ( ! $jpwbc_show_cat && ! $jpwbc_show_pr && ! $jpwbc_show_srt ) {
	return;
}
?>
<div class="jpwbc-brand-cats jpwbc-filterbar">

	<?php if ( $jpwbc_show_cat && ! empty( $jpwbc_cats ) ) : ?>
		<details class="jpwbc-filter jpwbc-filter--category">
			<summary class="jpwbc-filter__toggle">
				<span class="jpwbc-filter__label"><?php esc_html_e( 'Category', 'jezpress-woo-brand-categories' ); ?></span>
				<span class="jpwbc-filter__value"><?php echo esc_html( $jpwbc_cat_label ); ?></span>
			</summary>
			<div class="jpwbc-filter__panel">
				<ul class="jpwbc-filter__list">
					<li>
						<a class="jpwbc-filter__opt<?php echo '' === $jpwbc_active ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( add_query_arg( $jpwbc_preserve, $jpwbc_brand_url ) ); ?>"
							<?php echo '' === $jpwbc_active ? 'aria-current="true"' : ''; ?>>
							<?php esc_html_e( 'All categories', 'jezpress-woo-brand-categories' ); ?>
						</a>
					</li>
					<?php foreach ( $jpwbc_cats as $jpwbc_c ) : ?>
						<?php
						$jpwbc_c_url = isset( $jpwbc_c['url'] ) ? (string) $jpwbc_c['url'] : '';
						if ( '' === $jpwbc_c_url ) {
							continue;
						}
						$jpwbc_c_active = ! empty( $jpwbc_c['is_active'] );
						?>
						<li>
							<a class="jpwbc-filter__opt<?php echo $jpwbc_c_active ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( add_query_arg( $jpwbc_preserve, $jpwbc_c_url ) ); ?>"
								<?php echo $jpwbc_c_active ? 'aria-current="true"' : ''; ?>>
								<span class="jpwbc-filter__opt-name"><?php echo esc_html( (string) $jpwbc_c['name'] ); ?></span>
								<span class="jpwbc-filter__opt-count"><?php echo esc_html( (string) (int) $jpwbc_c['count'] ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</details>
	<?php endif; ?>

	<?php if ( $jpwbc_show_pr ) : ?>
		<details class="jpwbc-filter jpwbc-filter--price">
			<summary class="jpwbc-filter__toggle">
				<span class="jpwbc-filter__label"><?php esc_html_e( 'Price', 'jezpress-woo-brand-categories' ); ?></span>
				<?php if ( null !== $jpwbc_min || null !== $jpwbc_max ) : ?>
					<span class="jpwbc-filter__value">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: min price, 2: max price */
								__( '%1$s – %2$s', 'jezpress-woo-brand-categories' ),
								null !== $jpwbc_min ? (string) (int) $jpwbc_min : (string) $jpwbc_range_min,
								null !== $jpwbc_max ? (string) (int) $jpwbc_max : (string) $jpwbc_range_max
							)
						);
						?>
					</span>
				<?php endif; ?>
			</summary>
			<div class="jpwbc-filter__panel">
				<form class="jpwbc-price-form" method="get" action="<?php echo esc_url( $jpwbc_base ); ?>">
					<div class="jpwbc-price-fields">
						<label class="jpwbc-price-field">
							<span class="screen-reader-text"><?php esc_html_e( 'Minimum price', 'jezpress-woo-brand-categories' ); ?></span>
							<input type="number" name="jpwbc_min_price" inputmode="numeric" min="0"
								placeholder="<?php echo esc_attr( (string) $jpwbc_range_min ); ?>"
								value="<?php echo null !== $jpwbc_min ? esc_attr( (string) (int) $jpwbc_min ) : ''; ?>">
						</label>
						<span class="jpwbc-price-sep" aria-hidden="true">–</span>
						<label class="jpwbc-price-field">
							<span class="screen-reader-text"><?php esc_html_e( 'Maximum price', 'jezpress-woo-brand-categories' ); ?></span>
							<input type="number" name="jpwbc_max_price" inputmode="numeric" min="0"
								placeholder="<?php echo esc_attr( (string) $jpwbc_range_max ); ?>"
								value="<?php echo null !== $jpwbc_max ? esc_attr( (string) (int) $jpwbc_max ) : ''; ?>">
						</label>
					</div>
					<?php $jpwbc_hidden( array( 'orderby' => $jpwbc_sort ) ); ?>
					<button type="submit" class="jpwbc-filter__apply"><?php esc_html_e( 'Apply', 'jezpress-woo-brand-categories' ); ?></button>
				</form>
			</div>
		</details>
	<?php endif; ?>

	<?php if ( $jpwbc_show_srt && ! empty( $jpwbc_sorts ) ) : ?>
		<form class="jpwbc-filter jpwbc-filter--sort jpwbc-autosubmit" method="get" action="<?php echo esc_url( $jpwbc_base ); ?>">
			<label class="jpwbc-sort">
				<span class="jpwbc-filter__label"><?php esc_html_e( 'Sort by', 'jezpress-woo-brand-categories' ); ?></span>
				<select name="orderby" class="jpwbc-sort__select">
					<?php foreach ( $jpwbc_sorts as $jpwbc_key => $jpwbc_lbl ) : ?>
						<?php $jpwbc_sel = ( (string) $jpwbc_key === $jpwbc_sort ) || ( '' === $jpwbc_sort && 'menu_order' === $jpwbc_key ); ?>
						<option value="<?php echo esc_attr( (string) $jpwbc_key ); ?>" <?php selected( $jpwbc_sel ); ?>>
							<?php echo esc_html( (string) $jpwbc_lbl ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</label>
			<?php
			$jpwbc_hidden(
				array(
					'jpwbc_min_price' => null !== $jpwbc_min ? (string) (int) $jpwbc_min : '',
					'jpwbc_max_price' => null !== $jpwbc_max ? (string) (int) $jpwbc_max : '',
				)
			);
			?>
			<button type="submit" class="jpwbc-filter__apply jpwbc-filter__apply--sort"><?php esc_html_e( 'Go', 'jezpress-woo-brand-categories' ); ?></button>
		</form>
	<?php endif; ?>

	<?php if ( ! empty( $data['is_filtered'] ) ) : ?>
		<a class="jpwbc-filter__clear" href="<?php echo esc_url( $jpwbc_base ); ?>"><?php esc_html_e( 'Clear filters', 'jezpress-woo-brand-categories' ); ?></a>
	<?php endif; ?>

</div>
