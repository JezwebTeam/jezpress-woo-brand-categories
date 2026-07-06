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
$jpwbc_facets   = isset( $data['facets'] ) && is_array( $data['facets'] ) ? $data['facets'] : array();
$jpwbc_ajax     = ! empty( $data['ajax'] );
$jpwbc_res_sel  = isset( $data['results_selector'] ) && '' !== (string) $data['results_selector'] ? (string) $data['results_selector'] : 'ul.products';
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

// Emit the preserved filter params ($preserve) as hidden inputs so each GET form
// keeps the other active filters. $exclude lists param keys the form owns itself
// (its inputs already carry those). Array values (facets) emit name[] fields.
$jpwbc_hidden = static function ( array $exclude = array() ) use ( $jpwbc_preserve ): void {
	foreach ( $jpwbc_preserve as $k => $v ) {
		if ( in_array( (string) $k, $exclude, true ) ) {
			continue;
		}
		if ( is_array( $v ) ) {
			foreach ( $v as $item ) {
				if ( '' === (string) $item ) {
					continue;
				}
				printf(
					'<input type="hidden" name="%s" value="%s">',
					esc_attr( (string) $k . '[]' ),
					esc_attr( (string) $item )
				);
			}
			continue;
		}
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

if ( ! $jpwbc_show_cat && ! $jpwbc_show_pr && ! $jpwbc_show_srt && empty( $jpwbc_facets ) ) {
	return;
}
?>
<div class="jpwbc-brand-cats jpwbc-filterbar" data-jpwbc-ajax="<?php echo $jpwbc_ajax ? '1' : '0'; ?>" data-jpwbc-results="<?php echo esc_attr( $jpwbc_res_sel ); ?>">

	<?php if ( $jpwbc_show_cat && ! empty( $jpwbc_cats ) ) : ?>
		<details class="jpwbc-filter jpwbc-filter--category" data-jpwbc-facet="category">
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

	<?php foreach ( $jpwbc_facets as $jpwbc_facet ) : ?>
		<?php
		$jpwbc_f_key    = isset( $jpwbc_facet['key'] ) ? (string) $jpwbc_facet['key'] : '';
		$jpwbc_f_label  = isset( $jpwbc_facet['label'] ) ? (string) $jpwbc_facet['label'] : '';
		$jpwbc_f_values = isset( $jpwbc_facet['values'] ) && is_array( $jpwbc_facet['values'] ) ? $jpwbc_facet['values'] : array();
		if ( '' === $jpwbc_f_key || empty( $jpwbc_f_values ) ) {
			continue;
		}
		$jpwbc_f_param    = 'jpwbc_af_' . $jpwbc_f_key;
		$jpwbc_f_selcount = 0;
		foreach ( $jpwbc_f_values as $jpwbc_v ) {
			if ( ! empty( $jpwbc_v['selected'] ) ) {
				++$jpwbc_f_selcount;
			}
		}
		?>
		<details class="jpwbc-filter jpwbc-filter--attr" data-jpwbc-facet="<?php echo esc_attr( 'attr-' . $jpwbc_f_key ); ?>">
			<summary class="jpwbc-filter__toggle">
				<span class="jpwbc-filter__label"><?php echo esc_html( $jpwbc_f_label ); ?></span>
				<?php if ( $jpwbc_f_selcount > 0 ) : ?>
					<span class="jpwbc-filter__value"><?php echo esc_html( (string) (int) $jpwbc_f_selcount ); ?></span>
				<?php endif; ?>
			</summary>
			<div class="jpwbc-filter__panel">
				<form class="jpwbc-attr-form" method="get" action="<?php echo esc_url( $jpwbc_base ); ?>">
					<ul class="jpwbc-filter__list">
						<?php foreach ( $jpwbc_f_values as $jpwbc_v ) : ?>
							<?php
							$jpwbc_v_slug = isset( $jpwbc_v['slug'] ) ? (string) $jpwbc_v['slug'] : '';
							if ( '' === $jpwbc_v_slug ) {
								continue;
							}
							?>
							<li>
								<label class="jpwbc-filter__check">
									<input type="checkbox" name="<?php echo esc_attr( $jpwbc_f_param ); ?>[]"
										value="<?php echo esc_attr( $jpwbc_v_slug ); ?>"
										<?php checked( ! empty( $jpwbc_v['selected'] ) ); ?>>
									<span class="jpwbc-filter__opt-name"><?php echo esc_html( (string) $jpwbc_v['name'] ); ?></span>
									<span class="jpwbc-filter__opt-count"><?php echo esc_html( (string) (int) $jpwbc_v['count'] ); ?></span>
								</label>
							</li>
						<?php endforeach; ?>
					</ul>
					<?php $jpwbc_hidden( array( $jpwbc_f_param ) ); ?>
					<button type="submit" class="jpwbc-filter__apply"><?php esc_html_e( 'Apply', 'jezpress-woo-brand-categories' ); ?></button>
				</form>
			</div>
		</details>
	<?php endforeach; ?>

	<?php if ( $jpwbc_show_pr ) : ?>
		<details class="jpwbc-filter jpwbc-filter--price" data-jpwbc-facet="price">
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

					<?php if ( $jpwbc_range_max > $jpwbc_range_min ) : ?>
						<div class="jpwbc-price-slider"
							data-min="<?php echo esc_attr( (string) $jpwbc_range_min ); ?>"
							data-max="<?php echo esc_attr( (string) $jpwbc_range_max ); ?>">
							<div class="jpwbc-price-slider__track"><span class="jpwbc-price-slider__fill"></span></div>
							<input type="range" class="jpwbc-price-slider__lower" aria-label="<?php esc_attr_e( 'Minimum price', 'jezpress-woo-brand-categories' ); ?>"
								min="<?php echo esc_attr( (string) $jpwbc_range_min ); ?>" max="<?php echo esc_attr( (string) $jpwbc_range_max ); ?>" step="1"
								value="<?php echo esc_attr( (string) ( null !== $jpwbc_min ? (int) $jpwbc_min : $jpwbc_range_min ) ); ?>">
							<input type="range" class="jpwbc-price-slider__upper" aria-label="<?php esc_attr_e( 'Maximum price', 'jezpress-woo-brand-categories' ); ?>"
								min="<?php echo esc_attr( (string) $jpwbc_range_min ); ?>" max="<?php echo esc_attr( (string) $jpwbc_range_max ); ?>" step="1"
								value="<?php echo esc_attr( (string) ( null !== $jpwbc_max ? (int) $jpwbc_max : $jpwbc_range_max ) ); ?>">
						</div>
						<div class="jpwbc-price-slider__bounds" aria-hidden="true">
							<span>$<?php echo esc_html( (string) $jpwbc_range_min ); ?></span>
							<span>$<?php echo esc_html( (string) $jpwbc_range_max ); ?></span>
						</div>
					<?php endif; ?>

					<?php $jpwbc_hidden( array( 'jpwbc_min_price', 'jpwbc_max_price' ) ); ?>
					<button type="submit" class="jpwbc-filter__apply"><?php esc_html_e( 'Apply', 'jezpress-woo-brand-categories' ); ?></button>
				</form>
			</div>
		</details>
	<?php endif; ?>

	<?php if ( $jpwbc_show_srt && ! empty( $jpwbc_sorts ) ) : ?>
		<?php
		$jpwbc_cur_sort  = '' !== $jpwbc_sort ? $jpwbc_sort : 'menu_order';
		$jpwbc_sort_lbl  = isset( $jpwbc_sorts[ $jpwbc_cur_sort ] ) ? (string) $jpwbc_sorts[ $jpwbc_cur_sort ] : (string) reset( $jpwbc_sorts );
		?>
		<details class="jpwbc-filter jpwbc-filter--sort" data-jpwbc-facet="sort">
			<summary class="jpwbc-filter__toggle">
				<span class="jpwbc-filter__label"><?php esc_html_e( 'Sort by', 'jezpress-woo-brand-categories' ); ?></span>
				<span class="jpwbc-filter__value"><?php echo esc_html( $jpwbc_sort_lbl ); ?></span>
			</summary>
			<div class="jpwbc-filter__panel">
				<ul class="jpwbc-filter__list">
					<?php foreach ( $jpwbc_sorts as $jpwbc_key => $jpwbc_lbl ) : ?>
						<?php
						// Sort links preserve price + facets; orderby is dropped for the
						// default (menu_order = Recommended) so it stays a clean URL.
						$jpwbc_sort_args = $jpwbc_preserve;
						unset( $jpwbc_sort_args['orderby'] );
						if ( 'menu_order' !== $jpwbc_key ) {
							$jpwbc_sort_args['orderby'] = $jpwbc_key;
						}
						$jpwbc_sort_url    = add_query_arg( $jpwbc_sort_args, $jpwbc_base );
						$jpwbc_sort_active = ( (string) $jpwbc_key === $jpwbc_cur_sort );
						?>
						<li>
							<a class="jpwbc-filter__opt<?php echo $jpwbc_sort_active ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( $jpwbc_sort_url ); ?>"
								<?php echo $jpwbc_sort_active ? 'aria-current="true"' : ''; ?>>
								<span class="jpwbc-filter__opt-name"><?php echo esc_html( (string) $jpwbc_lbl ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</details>
	<?php endif; ?>

	<?php if ( ! empty( $jpwbc_preserve ) ) : ?>
		<a class="jpwbc-filter__clear" href="<?php echo esc_url( $jpwbc_base ); ?>"><?php esc_html_e( 'Clear filters', 'jezpress-woo-brand-categories' ); ?></a>
	<?php endif; ?>

</div>
