<?php
/**
 * Template: product category archive filter bar (Category + Price + Sort).
 *
 * The Category control lists the whole product_cat tree: top-level categories
 * as rows, each expandable in place to reveal its children. Selecting one
 * navigates to WooCommerce's own category archive URL, so these are ordinary
 * canonical archives — price/sort/facets ride along as query params.
 *
 * Override by copying to {theme}/jezpress-woo-brand-categories/category-filter-bar.php
 *
 * @package JezPress\WooBrandCategories
 * @since   1.20.0
 *
 * @var array $data {
 *     @type array  $settings       Plugin settings.
 *     @type array  $current        { term_id, name, slug, url } of the viewed category.
 *     @type string $shop_url       The unfiltered shop archive URL.
 *     @type array  $category_tree  Rows: name, slug, count, url, is_active, has_active_child, children[].
 *     @type array  $facets         Attribute facets.
 *     @type string $active_cat     Active category slug ('' = All).
 *     @type string $base_url       Current view path.
 *     @type array  $preserve       Query args to carry across category links.
 *     @type array  $price_range    { min:int, max:int }.
 *     @type float|null $current_min Current min price filter.
 *     @type float|null $current_max Current max price filter.
 *     @type string $current_sort   Current orderby key ('' = default).
 *     @type array  $sort_options   orderby key => label.
 *     @type bool   $show_category  Show the category control.
 *     @type bool   $show_price     Show the price control.
 *     @type bool   $show_sort      Show the sort control.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jpwbc_current  = isset( $data['current'] ) && is_array( $data['current'] ) ? $data['current'] : array();
$jpwbc_tree     = isset( $data['category_tree'] ) && is_array( $data['category_tree'] ) ? $data['category_tree'] : array();
$jpwbc_shop_url = isset( $data['shop_url'] ) ? (string) $data['shop_url'] : '';
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

$jpwbc_range_min = isset( $jpwbc_range['min'] ) ? (int) $jpwbc_range['min'] : 0;
$jpwbc_range_max = isset( $jpwbc_range['max'] ) ? (int) $jpwbc_range['max'] : 0;

// Summary label: the category being viewed, else "All categories".
$jpwbc_cat_label = ( '' !== (string) ( $jpwbc_current['name'] ?? '' ) )
	? (string) $jpwbc_current['name']
	: __( 'All categories', 'jezpress-woo-brand-categories' );

// Emit the preserved filter params ($preserve) as hidden inputs so each GET form
// keeps the other active filters. $exclude lists param keys the form owns itself.
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

// One renderer for a category row, parent or child.
$jpwbc_opt = static function ( array $row, bool $is_child ) use ( $jpwbc_preserve ): void {
	$url = isset( $row['url'] ) ? (string) $row['url'] : '';
	if ( '' === $url ) {
		return;
	}
	$active = ! empty( $row['is_active'] );

	// Descendants are flattened into one list, so the indent comes from depth
	// (clamped: a very deep tree shouldn't push labels off the panel).
	$depth  = isset( $row['depth'] ) ? max( 1, min( 4, (int) $row['depth'] ) ) : 1;
	$indent = $is_child ? esc_attr( ' jpwbc-filter__opt--child jpwbc-filter__opt--d' . $depth ) : '';

	printf(
		'<a class="jpwbc-filter__opt%1$s%2$s" href="%3$s"%4$s><span class="jpwbc-filter__opt-name">%5$s</span><span class="jpwbc-filter__opt-count">%6$s</span></a>',
		$indent,
		$active ? ' is-active' : '',
		esc_url( add_query_arg( $jpwbc_preserve, $url ) ),
		$active ? ' aria-current="true"' : '',
		esc_html( (string) ( $row['name'] ?? '' ) ),
		esc_html( (string) (int) ( $row['count'] ?? 0 ) )
	);
};

if ( ! $jpwbc_show_cat && ! $jpwbc_show_pr && ! $jpwbc_show_srt && empty( $jpwbc_facets ) ) {
	return;
}
?>
<div class="jpwbc-mobilebar" aria-hidden="true">
	<button type="button" class="jpwbc-mobilebar__btn jpwbc-mobilebar__filter" data-jpwbc-open="filter">
		<span class="jpwbc-mobilebar__icon" aria-hidden="true"></span><?php esc_html_e( 'Filter', 'jezpress-woo-brand-categories' ); ?>
	</button>
	<button type="button" class="jpwbc-mobilebar__btn jpwbc-mobilebar__sort" data-jpwbc-open="sort">
		<?php esc_html_e( 'Sort', 'jezpress-woo-brand-categories' ); ?>
	</button>
</div>

<div class="jpwbc-brand-cats jpwbc-filterbar jpwbc-filterbar--category" data-jpwbc-ajax="<?php echo $jpwbc_ajax ? '1' : '0'; ?>" data-jpwbc-results="<?php echo esc_attr( $jpwbc_res_sel ); ?>">

	<div class="jpwbc-filterbar__head">
		<span class="jpwbc-filterbar__title"><?php esc_html_e( 'Filters', 'jezpress-woo-brand-categories' ); ?></span>
		<button type="button" class="jpwbc-filterbar__close" data-jpwbc-close aria-label="<?php esc_attr_e( 'Close filters', 'jezpress-woo-brand-categories' ); ?>">&times;</button>
	</div>

	<?php if ( $jpwbc_show_cat && ! empty( $jpwbc_tree ) ) : ?>
		<details class="jpwbc-filter jpwbc-filter--category" data-jpwbc-facet="category">
			<summary class="jpwbc-filter__toggle">
				<span class="jpwbc-filter__label"><?php esc_html_e( 'Category', 'jezpress-woo-brand-categories' ); ?></span>
				<span class="jpwbc-filter__value"><?php echo esc_html( $jpwbc_cat_label ); ?></span>
			</summary>
			<div class="jpwbc-filter__panel">
				<ul class="jpwbc-filter__list jpwbc-filter__tree">
					<?php if ( '' !== $jpwbc_shop_url ) : ?>
						<li>
							<a class="jpwbc-filter__opt<?php echo '' === $jpwbc_active ? ' is-active' : ''; ?>"
								href="<?php echo esc_url( add_query_arg( $jpwbc_preserve, $jpwbc_shop_url ) ); ?>"
								<?php echo '' === $jpwbc_active ? 'aria-current="true"' : ''; ?>>
								<?php esc_html_e( 'All categories', 'jezpress-woo-brand-categories' ); ?>
							</a>
						</li>
					<?php endif; ?>

					<?php foreach ( $jpwbc_tree as $jpwbc_parent ) : ?>
						<?php
						$jpwbc_kids = isset( $jpwbc_parent['children'] ) && is_array( $jpwbc_parent['children'] ) ? $jpwbc_parent['children'] : array();
						// The branch you're on starts expanded — whether you're on the
						// parent itself or on one of its subcategories.
						$jpwbc_open = ! empty( $jpwbc_parent['has_active_child'] ) || ! empty( $jpwbc_parent['is_active'] );
						$jpwbc_pid  = 'jpwbc-sub-' . sanitize_html_class( (string) ( $jpwbc_parent['slug'] ?? '' ) );
						?>
						<li class="jpwbc-filter__branch<?php echo $jpwbc_open ? ' is-open' : ''; ?>">
							<div class="jpwbc-filter__row">
								<?php $jpwbc_opt( $jpwbc_parent, false ); ?>
								<?php if ( ! empty( $jpwbc_kids ) ) : ?>
									<button type="button" class="jpwbc-filter__expand"
										data-jpwbc-expand
										aria-expanded="<?php echo $jpwbc_open ? 'true' : 'false'; ?>"
										aria-controls="<?php echo esc_attr( $jpwbc_pid ); ?>"
										aria-label="<?php echo esc_attr( sprintf( /* translators: %s: category name */ __( 'Show subcategories of %s', 'jezpress-woo-brand-categories' ), (string) ( $jpwbc_parent['name'] ?? '' ) ) ); ?>">
										<span class="jpwbc-filter__chev" aria-hidden="true"></span>
									</button>
								<?php endif; ?>
							</div>

							<?php if ( ! empty( $jpwbc_kids ) ) : ?>
								<ul class="jpwbc-filter__children" id="<?php echo esc_attr( $jpwbc_pid ); ?>">
									<?php foreach ( $jpwbc_kids as $jpwbc_kid ) : ?>
										<li><?php $jpwbc_opt( $jpwbc_kid, true ); ?></li>
									<?php endforeach; ?>
								</ul>
							<?php endif; ?>
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

	<div class="jpwbc-filterbar__foot">
		<a class="jpwbc-filterbar__clearall" href="<?php echo esc_url( $jpwbc_base ); ?>"><?php esc_html_e( 'Clear all', 'jezpress-woo-brand-categories' ); ?></a>
		<button type="button" class="jpwbc-filterbar__done" data-jpwbc-close><?php esc_html_e( 'Show results', 'jezpress-woo-brand-categories' ); ?></button>
	</div>

</div>

<div class="jpwbc-filter-backdrop" data-jpwbc-close hidden></div>

<button type="button" class="jpwbc-filter-jump" aria-label="<?php esc_attr_e( 'Jump to filters', 'jezpress-woo-brand-categories' ); ?>" hidden>
	<span class="jpwbc-filter-jump__icon" aria-hidden="true"></span><?php esc_html_e( 'Filter', 'jezpress-woo-brand-categories' ); ?>
</button>
