<?php
/**
 * Uninstall handler for JezPress Woo Brand Categories
 *
 * Deletes all plugin options, transients, and scheduled cron events
 * when the plugin is uninstalled.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

/**
 * Delete all plugin options
 *
 * Add your plugin's option keys to this array
 */
$options = array(
	'jpwbc_settings',
	'jpwbc_cache_version',
	'jpwbc_cache_rebuilt',
	// Attribute-facet indexer (1.14.0+).
	'jpwbc_af_attributes',
	'jpwbc_af_index_state',
	// Legacy keys from older builds (harmless if absent).
	'jpwbc_license_key',
	'jpwbc_license_data',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

/**
 * Delete all plugin transients
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_jpwbc_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_jpwbc_' ) . '%'
	)
);

/**
 * Clear scheduled cron events
 */
$timestamp = wp_next_scheduled( 'jpwbc_license_check' );
if ( false !== $timestamp ) {
	wp_unschedule_event( $timestamp, 'jpwbc_license_check' );
}
wp_clear_scheduled_hook( 'jpwbc_af_reindex' );

/**
 * Delete the derived attribute-facet taxonomies (jpwbc_af_*): their terms, term
 * meta and object relationships. The taxonomies aren't registered at uninstall,
 * so this is a direct table cleanup.
 */
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$jpwbc_af_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT term_taxonomy_id, term_id FROM {$wpdb->term_taxonomy} WHERE taxonomy LIKE %s",
		$wpdb->esc_like( 'jpwbc_af_' ) . '%'
	)
);
if ( ! empty( $jpwbc_af_rows ) ) {
	$jpwbc_af_tt = array_map( 'intval', wp_list_pluck( $jpwbc_af_rows, 'term_taxonomy_id' ) );
	$jpwbc_af_t  = array_map( 'intval', wp_list_pluck( $jpwbc_af_rows, 'term_id' ) );
	$jpwbc_tt_in = implode( ',', $jpwbc_af_tt );
	$jpwbc_t_in  = implode( ',', $jpwbc_af_t );

	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- IN lists are intval-cast integers.
	if ( '' !== $jpwbc_tt_in ) {
		$wpdb->query( "DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ($jpwbc_tt_in)" );
		$wpdb->query( "DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ($jpwbc_tt_in)" );
	}
	if ( '' !== $jpwbc_t_in ) {
		$wpdb->query( "DELETE FROM {$wpdb->terms} WHERE term_id IN ($jpwbc_t_in)" );
		$wpdb->query( "DELETE FROM {$wpdb->termmeta} WHERE term_id IN ($jpwbc_t_in)" );
	}
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

/**
 * Delete brand click-count term meta (used by Trending Brands).
 */
delete_metadata( 'term', 0, '_jpwbc_clicks', '', true );

/**
 * Delete license data option (uses hashed name)
 */
$license_option = 'jzwb_lic_' . substr( md5( 'jezpress-woo-brand-categories' ), 0, 8 );
delete_option( $license_option );

/**
 * Delete the license-action feedback transient.
 *
 * (The updater's own cache transient, jpwbc_update_*, is already removed by the
 * _transient_jpwbc_ LIKE cleanup above.)
 */
delete_transient( 'jezweb_license_message_jezpress-woo-brand-categories' );
