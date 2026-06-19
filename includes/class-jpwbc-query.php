<?php
/**
 * Query layer
 *
 * Resolves, for a given brand, the product categories that brand actually has
 * published catalog-visible products in, together with per-category counts.
 * Uses one grouped term-relationship join — no product objects are loaded —
 * and caches the result via JPWBC_Cache.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Query handler.
 *
 * @since 1.0.0
 */
class JPWBC_Query {

	/**
	 * Cache handler.
	 *
	 * @since 1.0.0
	 * @var JPWBC_Cache
	 */
	private JPWBC_Cache $cache;

	/**
	 * Per-request memo of brand category lists.
	 *
	 * @since 1.0.0
	 * @var array<int, array<int, array<string, mixed>>>
	 */
	private array $memo = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param JPWBC_Cache $cache Cache handler.
	 */
	public function __construct( JPWBC_Cache $cache ) {
		$this->cache = $cache;
	}

	/**
	 * Get the categories (with counts) a brand has products in.
	 *
	 * @since 1.0.0
	 *
	 * @param int $brand_id Brand (product_brand) term id.
	 * @return array<int, array{term_id:int, name:string, slug:string, count:int}>
	 */
	public function get_brand_categories( int $brand_id ): array {
		if ( $brand_id <= 0 ) {
			return array();
		}

		if ( isset( $this->memo[ $brand_id ] ) ) {
			return $this->memo[ $brand_id ];
		}

		$cached = $this->cache->get_brand_categories( $brand_id );
		if ( null !== $cached ) {
			$this->memo[ $brand_id ] = $cached;
			return $cached;
		}

		$rows = $this->query_brand_categories( $brand_id );
		$this->cache->set_brand_categories( $brand_id, $rows );
		$this->memo[ $brand_id ] = $rows;

		return $rows;
	}

	/**
	 * Run the grouped join for a brand's categories.
	 *
	 * @since 1.0.0
	 *
	 * @param int $brand_id Brand term id.
	 * @return array<int, array{term_id:int, name:string, slug:string, count:int}>
	 */
	private function query_brand_categories( int $brand_id ): array {
		global $wpdb;

		$exclude_tt = $this->excluded_visibility_tt_ids();

		$exclude_sql = '';
		if ( ! empty( $exclude_tt ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $exclude_tt ), '%d' ) );
			$exclude_sql  = $wpdb->prepare(
				" AND p.ID NOT IN (
					SELECT tr_vis.object_id FROM {$wpdb->term_relationships} tr_vis
					WHERE tr_vis.term_taxonomy_id IN ($placeholders)
				)",
				$exclude_tt
			);
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $exclude_sql is built with $wpdb->prepare above; table names are $wpdb props.
		$sql = $wpdb->prepare(
			"SELECT t_cat.term_id AS term_id, t_cat.name AS name, t_cat.slug AS slug, COUNT( DISTINCT p.ID ) AS cnt
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->term_relationships} tr_brand ON tr_brand.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt_brand ON tt_brand.term_taxonomy_id = tr_brand.term_taxonomy_id
				AND tt_brand.taxonomy = %s AND tt_brand.term_id = %d
			INNER JOIN {$wpdb->term_relationships} tr_cat ON tr_cat.object_id = p.ID
			INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tt_cat.term_taxonomy_id = tr_cat.term_taxonomy_id
				AND tt_cat.taxonomy = %s
			INNER JOIN {$wpdb->terms} t_cat ON t_cat.term_id = tt_cat.term_id
			WHERE p.post_type = 'product' AND p.post_status = 'publish'" . $exclude_sql . "
			GROUP BY t_cat.term_id, t_cat.name, t_cat.slug
			HAVING cnt > 0
			ORDER BY t_cat.name ASC",
			JPWBC_BRAND_TAXONOMY,
			$brand_id,
			JPWBC_CAT_TAXONOMY
		);

		$results = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! is_array( $results ) ) {
			return array();
		}

		$rows = array();
		foreach ( $results as $row ) {
			$rows[] = array(
				'term_id' => (int) $row['term_id'],
				'name'    => (string) $row['name'],
				'slug'    => (string) $row['slug'],
				'count'   => (int) $row['cnt'],
			);
		}

		return $rows;
	}

	/**
	 * Find a single category row within a brand by category slug.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $brand_id Brand term id.
	 * @param string $cat_slug Category slug.
	 * @return array{term_id:int, name:string, slug:string, count:int}|null
	 */
	public function get_category_in_brand( int $brand_id, string $cat_slug ): ?array {
		$cat_slug = sanitize_title( $cat_slug );
		foreach ( $this->get_brand_categories( $brand_id ) as $row ) {
			if ( $row['slug'] === $cat_slug ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Product count for a brand+category combo (0 if the combo is empty).
	 *
	 * @since 1.0.0
	 *
	 * @param int    $brand_id Brand term id.
	 * @param string $cat_slug Category slug.
	 * @return int
	 */
	public function get_combo_count( int $brand_id, string $cat_slug ): int {
		$row = $this->get_category_in_brand( $brand_id, $cat_slug );
		return $row ? (int) $row['count'] : 0;
	}

	/**
	 * Term-taxonomy ids of product_visibility terms that hide a product from
	 * the catalog ('exclude-from-catalog'). Returns empty array if unavailable.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, int>
	 */
	private function excluded_visibility_tt_ids(): array {
		if ( ! taxonomy_exists( 'product_visibility' ) ) {
			return array();
		}

		$ids  = array();
		$term = get_term_by( 'slug', 'exclude-from-catalog', 'product_visibility' );
		if ( $term instanceof \WP_Term ) {
			$ids[] = (int) $term->term_taxonomy_id;
		}

		return $ids;
	}
}
