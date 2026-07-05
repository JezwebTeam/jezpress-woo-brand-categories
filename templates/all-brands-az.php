<?php
/**
 * Template: All Brands (A-Z).
 *
 * Optional alphabet jump-index, then brands grouped under a heading per letter
 * (each letter on its own line). Empty letters are never shown as lonely rows.
 *
 * Override by copying to {theme}/jezpress-woo-brand-categories/all-brands-az.php
 *
 * @package JezPress\WooBrandCategories
 * @since   1.2.0
 *
 * @var array $data {
 *     @type string $title              Heading.
 *     @type bool   $show_index         Show the A-Z letter grid.
 *     @type int    $columns            A-Z letter-grid columns (3-6).
 *     @type bool   $show_groups        Show the per-letter brand lists.
 *     @type int    $list_columns       Brand-list columns under each letter (1-4).
 *     @type bool   $show_letter_counts Show a "(N)" count per letter.
 *     @type bool   $show_search        Show the brand search box.
 *     @type bool   $show_arrow         Show arrow after the heading.
 *     @type bool   $sticky_index       Pin the heading + A-Z bar to the top while the list scrolls.
 *     @type int    $sticky_offset      Sticky top offset in px (to clear a sticky site header).
 *     @type string $index_url          Optional Brands-page URL; makes each A-Z letter link to "{url}#jpwbc-az-{letter}".
 *     @type string $view_all_url       Optional "View all" URL.
 *     @type string $view_all_text      "View all" label.
 *     @type array  $groups             Letter => [ {term_id,name,slug,url}, ... ].
 *     @type int    $instance           Unique instance id for anchor ids.
 * }
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$jpwbc_title    = isset( $data['title'] ) ? (string) $data['title'] : '';
$jpwbc_arrow    = ! empty( $data['show_arrow'] );
$jpwbc_index    = ! empty( $data['show_index'] );
$jpwbc_ix_url   = isset( $data['index_url'] ) ? (string) $data['index_url'] : '';
$jpwbc_view_url = isset( $data['view_all_url'] ) ? (string) $data['view_all_url'] : '';
$jpwbc_view_txt = isset( $data['view_all_text'] ) ? (string) $data['view_all_text'] : '';
$jpwbc_groups   = isset( $data['groups'] ) && is_array( $data['groups'] ) ? $data['groups'] : array();
$jpwbc_inst     = isset( $data['instance'] ) ? (int) $data['instance'] : 1;
$jpwbc_groups_on = ! isset( $data['show_groups'] ) || ! empty( $data['show_groups'] );
$jpwbc_cols      = isset( $data['columns'] ) ? (int) $data['columns'] : 5;
$jpwbc_cols      = ( $jpwbc_cols >= 3 && $jpwbc_cols <= 6 ) ? $jpwbc_cols : 5;
$jpwbc_list_cols = isset( $data['list_columns'] ) ? (int) $data['list_columns'] : 1;
$jpwbc_list_cols = ( $jpwbc_list_cols >= 1 && $jpwbc_list_cols <= 4 ) ? $jpwbc_list_cols : 1;
$jpwbc_counts    = ! empty( $data['show_letter_counts'] );
$jpwbc_search    = ! empty( $data['show_search'] );
$jpwbc_ix_layout = ( isset( $data['index_layout'] ) && 'inline' === $data['index_layout'] ) ? 'inline' : 'grid';
$jpwbc_sticky    = ! empty( $data['sticky_index'] );
$jpwbc_sticky_off = isset( $data['sticky_offset'] ) ? max( 0, (int) $data['sticky_offset'] ) : 0;

if ( empty( $jpwbc_groups ) ) {
	return;
}

// Root wrapper: optional Myer-style sticky heading + alphabet bar. The sticky
// offset (to clear a sticky site header) is passed as a CSS custom property.
$jpwbc_root_class = 'jpwbc-brand-cats jpwbc-allbrands';
$jpwbc_root_attr  = '';
if ( $jpwbc_sticky ) {
	$jpwbc_root_class .= ' jpwbc-allbrands--sticky';
	$jpwbc_root_attr   = ' style="--jpwbc-sticky-top:' . esc_attr( (string) $jpwbc_sticky_off ) . 'px"';
}

// The head (heading + search + index) is wrapped so it can be pinned as one block.
$jpwbc_head_class = 'jpwbc-allbrands__head' . ( $jpwbc_sticky ? ' jpwbc-allbrands__head--sticky' : '' );

// Index wrapper class: a fixed N-column grid, or an inline (Myer-style) row.
$jpwbc_index_class = 'jpwbc-az-index';
$jpwbc_index_class .= ( 'inline' === $jpwbc_ix_layout )
	? ' jpwbc-az-index--inline'
	: ' jpwbc-az-index--cols-' . $jpwbc_cols;

// The index letters become links when either (a) a cross-page Brands URL is set
// (menu use case — link to the Brands page at that letter), or (b) the brand
// lists are shown on this same widget (in-page jump). With neither, there is
// nothing to point at, so they render as plain text.
$jpwbc_index_links = ( '' !== $jpwbc_ix_url ) || $jpwbc_groups_on;

/**
 * Build the stable anchor id for a letter group.
 *
 * Deliberately instance-independent so cross-page links from another widget
 * (e.g. the menu) can target "{brands-url}#jpwbc-az-b" reliably.
 *
 * @param string $letter Group key.
 * @return string
 */
$jpwbc_anchor = static function ( string $letter ): string {
	$slug = ( '#' === $letter ) ? 'num' : strtolower( $letter );
	return 'jpwbc-az-' . $slug;
};

$jpwbc_present  = array_keys( $jpwbc_groups );
$jpwbc_alphabet = array_merge( range( 'A', 'Z' ), array( '#' ) );
?>
<div class="<?php echo esc_attr( $jpwbc_root_class ); ?>"<?php echo $jpwbc_root_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- value built from esc_attr()'d integer above. ?>>
	<div class="<?php echo esc_attr( $jpwbc_head_class ); ?>">
	<?php if ( '' !== $jpwbc_title ) : ?>
		<h3 class="jpwbc-allbrands__title">
			<?php echo esc_html( $jpwbc_title ); ?>
			<?php if ( $jpwbc_arrow ) : ?>
				<span class="jpwbc-arrow" aria-hidden="true"></span>
			<?php endif; ?>
		</h3>
	<?php endif; ?>

	<?php if ( $jpwbc_search ) : ?>
		<div class="jpwbc-allbrands__search">
			<label class="screen-reader-text" for="jpwbc-brandfilter-<?php echo esc_attr( (string) $jpwbc_inst ); ?>"><?php esc_html_e( 'Search for a brand', 'jezpress-woo-brand-categories' ); ?></label>
			<input type="search" id="jpwbc-brandfilter-<?php echo esc_attr( (string) $jpwbc_inst ); ?>" class="jpwbc-brandfilter" autocomplete="off"
				placeholder="<?php esc_attr_e( 'Search for a brand…', 'jezpress-woo-brand-categories' ); ?>">
		</div>
	<?php endif; ?>

	<?php if ( $jpwbc_index ) : ?>
		<nav class="<?php echo esc_attr( $jpwbc_index_class ); ?>" aria-label="<?php esc_attr_e( 'Brands by letter', 'jezpress-woo-brand-categories' ); ?>">
			<?php
			foreach ( $jpwbc_alphabet as $jpwbc_letter ) :
				$jpwbc_has = in_array( $jpwbc_letter, $jpwbc_present, true );
				if ( $jpwbc_has && $jpwbc_index_links ) :
					$jpwbc_href = ( '' !== $jpwbc_ix_url )
						? esc_url( $jpwbc_ix_url . '#' . $jpwbc_anchor( $jpwbc_letter ) )
						: '#' . esc_attr( $jpwbc_anchor( $jpwbc_letter ) );
					?>
					<a class="jpwbc-az-index__letter" href="<?php echo $jpwbc_href; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped above via esc_url / esc_attr. ?>"><?php echo esc_html( $jpwbc_letter ); ?></a>
				<?php elseif ( $jpwbc_has ) : ?>
					<span class="jpwbc-az-index__letter"><?php echo esc_html( $jpwbc_letter ); ?></span>
				<?php else : ?>
					<span class="jpwbc-az-index__letter is-empty" aria-hidden="true"><?php echo esc_html( $jpwbc_letter ); ?></span>
				<?php endif; ?>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
	</div><?php // .jpwbc-allbrands__head ?>

	<?php if ( $jpwbc_groups_on ) : ?>
		<div class="jpwbc-az-groups">
			<?php foreach ( $jpwbc_groups as $jpwbc_letter => $jpwbc_brands ) : ?>
				<section class="jpwbc-az-group" id="<?php echo esc_attr( $jpwbc_anchor( (string) $jpwbc_letter ) ); ?>">
					<h4 class="jpwbc-az-group__letter">
						<?php echo esc_html( (string) $jpwbc_letter ); ?>
						<?php if ( $jpwbc_counts ) : ?>
							<span class="jpwbc-az-group__count">(<?php echo esc_html( (string) count( $jpwbc_brands ) ); ?>)</span>
						<?php endif; ?>
					</h4>
					<ul class="jpwbc-az-group__list jpwbc-az-group__list--cols-<?php echo esc_attr( (string) $jpwbc_list_cols ); ?>">
						<?php foreach ( $jpwbc_brands as $jpwbc_brand ) : ?>
							<?php if ( '' === (string) $jpwbc_brand['url'] ) { continue; } ?>
							<li class="jpwbc-az-group__item">
								<a href="<?php echo esc_url( (string) $jpwbc_brand['url'] ); ?>"
									data-jpwbc-brand="<?php echo esc_attr( (string) (int) $jpwbc_brand['term_id'] ); ?>">
									<?php echo esc_html( (string) $jpwbc_brand['name'] ); ?>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $jpwbc_view_url && '' !== $jpwbc_view_txt ) : ?>
		<p class="jpwbc-allbrands__viewall">
			<a href="<?php echo esc_url( $jpwbc_view_url ); ?>"><?php echo esc_html( $jpwbc_view_txt ); ?></a>
		</p>
	<?php endif; ?>
</div>
