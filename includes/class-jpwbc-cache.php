<?php
/**
 * Cache layer
 *
 * Caches the per-brand category lists in transients (Redis-backed on the
 * target site). Busting uses a monotonic version counter stored in an option,
 * so invalidation is an O(1) option bump rather than an enumerate-and-delete
 * sweep — stale transients simply fall out of use and expire on their TTL.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cache handler.
 *
 * @since 1.0.0
 */
class JPWBC_Cache {

	/**
	 * Transient TTL (12 hours) — a backstop; version bumps do the real work.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Option storing the cache version counter.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const VERSION_OPTION = 'jpwbc_cache_version';

	/**
	 * Option storing the last manual-rebuild timestamp.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const REBUILT_OPTION = 'jpwbc_cache_rebuilt';

	/**
	 * Register cache-busting hooks.
	 *
	 * @since 1.0.0
	 */
	public function register_hooks(): void {
		add_action( 'save_post_product', array( $this, 'bust' ) );
		add_action( 'deleted_post', array( $this, 'on_deleted_post' ), 10, 2 );
		add_action( 'trashed_post', array( $this, 'on_trashed_post' ) );
		add_action( 'untrashed_post', array( $this, 'on_trashed_post' ) );
		add_action( 'set_object_terms', array( $this, 'on_set_object_terms' ), 10, 6 );
	}

	/**
	 * Current cache version.
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function version(): int {
		return max( 1, (int) get_option( self::VERSION_OPTION, 1 ) );
	}

	/**
	 * Bump the cache version, invalidating every cached entry at once.
	 *
	 * @since 1.0.0
	 */
	public function bust(): void {
		update_option( self::VERSION_OPTION, $this->version() + 1, false );
	}

	/**
	 * Manual rebuild: bust the version and record the timestamp.
	 *
	 * @since 1.0.0
	 */
	public function rebuild(): void {
		$this->bust();
		update_option( self::REBUILT_OPTION, time(), false );
	}

	/**
	 * Last rebuild timestamp (0 if never).
	 *
	 * @since 1.0.0
	 *
	 * @return int
	 */
	public function last_rebuilt(): int {
		return (int) get_option( self::REBUILT_OPTION, 0 );
	}

	/**
	 * Build the transient key for a brand's category list.
	 *
	 * @since 1.0.0
	 *
	 * @param int $brand_id Brand term id.
	 * @return string
	 */
	private function brand_cats_key( int $brand_id ): string {
		return 'jpwbc_brand_cats_' . $brand_id . '_v' . $this->version();
	}

	/**
	 * Read a brand's cached category list.
	 *
	 * @since 1.0.0
	 *
	 * @param int $brand_id Brand term id.
	 * @return array<int, array<string, mixed>>|null Null on cache miss.
	 */
	public function get_brand_categories( int $brand_id ): ?array {
		$value = get_transient( $this->brand_cats_key( $brand_id ) );
		return is_array( $value ) ? $value : null;
	}

	/**
	 * Store a brand's category list.
	 *
	 * @since 1.0.0
	 *
	 * @param int                              $brand_id   Brand term id.
	 * @param array<int, array<string, mixed>> $categories Category rows.
	 */
	public function set_brand_categories( int $brand_id, array $categories ): void {
		set_transient( $this->brand_cats_key( $brand_id ), $categories, self::TTL );
	}

	/**
	 * Whether an external persistent object cache (e.g. Redis) is in use.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function has_object_cache(): bool {
		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}

	/* ---------------------------------------------------------------------
	 * Bust triggers
	 * ------------------------------------------------------------------- */

	/**
	 * Bust when a product is permanently deleted.
	 *
	 * @since 1.0.0
	 *
	 * @param int          $post_id Deleted post id.
	 * @param \WP_Post|null $post    Post object (WP 6.4+ passes it).
	 */
	public function on_deleted_post( int $post_id, $post = null ): void {
		$type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );
		if ( 'product' === $type ) {
			$this->bust();
		}
	}

	/**
	 * Bust when a product is trashed/untrashed.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post id.
	 */
	public function on_trashed_post( int $post_id ): void {
		if ( 'product' === get_post_type( $post_id ) ) {
			$this->bust();
		}
	}

	/**
	 * Bust when a product's brand/category term relationships change.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $object_id  Object id.
	 * @param array  $terms      Terms (unused).
	 * @param array  $tt_ids     Term taxonomy ids (unused).
	 * @param string $taxonomy   Taxonomy being set.
	 * @param bool   $append     Append flag (unused).
	 * @param array  $old_tt_ids Previous term taxonomy ids (unused).
	 */
	public function on_set_object_terms( int $object_id, $terms, $tt_ids, string $taxonomy, $append, $old_tt_ids ): void {
		if ( in_array( $taxonomy, array( JPWBC_BRAND_TAXONOMY, JPWBC_CAT_TAXONOMY ), true ) ) {
			$this->bust();
		}
	}
}
