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
