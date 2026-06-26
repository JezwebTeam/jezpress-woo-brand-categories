<?php
/**
 * Plugin Name: JezPress Woo Brand Categories
 * Plugin URI: https://jezpress.com/plugins/jezpress-woo-brand-categories
 * Description: In-brand product-category navigation and clean brand+category URLs for WooCommerce brand archives.
 * Version: 1.0.6
 * Author: Jezweb
 * Author URI: https://jezpress.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: jezpress-woo-brand-categories
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 9.6
 * WC tested up to: 10.8
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Check PHP version before doing anything else
 *
 * @since 1.0.0
 */
function jpwbc_check_php_version(): void {
	if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
		add_action( 'admin_notices', 'jpwbc_php_version_notice' );
		return;
	}
}
add_action( 'admin_init', 'jpwbc_check_php_version' );

/**
 * Display admin notice for PHP version requirement
 *
 * @since 1.0.0
 */
function jpwbc_php_version_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	$message = sprintf(
		/* translators: 1: Required PHP version, 2: Current PHP version */
		__( 'JezPress Woo Brand Categories requires PHP version %1$s or higher. You are running version %2$s. Please upgrade PHP to activate this plugin.', 'jezpress-woo-brand-categories' ),
		'8.1.0',
		PHP_VERSION
	);

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		esc_html( $message )
	);

	deactivate_plugins( plugin_basename( __FILE__ ) );
}

/**
 * Stop execution if PHP version is too low
 */
if ( version_compare( PHP_VERSION, '8.1.0', '<' ) ) {
	return;
}

/**
 * Plugin constants
 *
 * @since 1.0.0
 */
define( 'JPWBC_VERSION', '1.0.6' );
define( 'JPWBC_PLUGIN_FILE', __FILE__ );
define( 'JPWBC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'JPWBC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'JPWBC_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Brand archive taxonomy + clean-URL query var.
 *
 * @since 1.0.0
 */
define( 'JPWBC_BRAND_TAXONOMY', 'product_brand' );
define( 'JPWBC_CAT_TAXONOMY', 'product_cat' );
define( 'JPWBC_QUERY_VAR', 'jpwbc_cat' );

/**
 * Declare HPOS + Cart/Checkout block compatibility.
 *
 * This plugin never touches order tables, but declaring compatibility keeps
 * WooCommerce's feature screen from flagging it as "incompatible".
 *
 * @since 1.0.0
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * Whether WooCommerce and the native product_brand taxonomy are available.
 *
 * WooCommerce 9.6+ ships a native `product_brand` taxonomy. The taxonomy is
 * registered on `init`, so callers that need it must run on `init` or later.
 *
 * @since 1.0.0
 *
 * @return bool
 */
function jpwbc_woocommerce_ready(): bool {
	return class_exists( 'WooCommerce' ) && taxonomy_exists( JPWBC_BRAND_TAXONOMY );
}

/**
 * Admin notice when WooCommerce or the product_brand taxonomy is missing.
 *
 * @since 1.0.0
 */
function jpwbc_dependency_notice(): void {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}

	if ( ! class_exists( 'WooCommerce' ) ) {
		$message = __( '<strong>JezPress Woo Brand Categories</strong> requires WooCommerce to be installed and active.', 'jezpress-woo-brand-categories' );
	} elseif ( ! taxonomy_exists( JPWBC_BRAND_TAXONOMY ) ) {
		$message = __( '<strong>JezPress Woo Brand Categories</strong> requires the WooCommerce <code>product_brand</code> taxonomy (WooCommerce 9.6 or higher). Please update WooCommerce.', 'jezpress-woo-brand-categories' );
	} else {
		return;
	}

	printf(
		'<div class="notice notice-error"><p>%s</p></div>',
		wp_kses_post( $message )
	);
}

/**
 * Activation hook - Create default options
 *
 * @since 1.0.0
 */
function jpwbc_activate(): void {
	// Check WordPress version
	global $wp_version;
	if ( version_compare( $wp_version, '6.4', '<' ) ) {
		deactivate_plugins( JPWBC_PLUGIN_BASENAME );
		wp_die(
			esc_html__( 'JezPress Woo Brand Categories requires WordPress 6.4 or higher.', 'jezpress-woo-brand-categories' ),
			esc_html__( 'Plugin Activation Error', 'jezpress-woo-brand-categories' ),
			array( 'back_link' => true )
		);
	}

	// Seed the single settings option with defaults if it does not exist yet.
	// JPWBC_Admin owns the canonical defaults; load it so we never duplicate them here.
	if ( false === get_option( 'jpwbc_settings' ) ) {
		require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-admin.php';
		add_option( 'jpwbc_settings', JPWBC_Admin::get_defaults() );
	}

	// Rewrite rules are registered on `init` by JPWBC_Rewrites; flush so the
	// clean brand+category URLs resolve immediately after activation.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'jpwbc_activate' );

/**
 * Deactivation hook - Cleanup transients
 *
 * @since 1.0.0
 */
function jpwbc_deactivate(): void {
	// Delete all plugin transients
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_jpwbc_' ) . '%'
		)
	);

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_timeout_jpwbc_' ) . '%'
		)
	);

	// Clear scheduled cron events
	$timestamp = wp_next_scheduled( 'jpwbc_license_check' );
	if ( false !== $timestamp ) {
		wp_unschedule_event( $timestamp, 'jpwbc_license_check' );
	}

	// Flush rewrite rules
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'jpwbc_deactivate' );

/**
 * Load plugin text domain for translations
 *
 * @since 1.0.0
 */
function jpwbc_load_textdomain(): void {
	load_plugin_textdomain(
		'jezpress-woo-brand-categories',
		false,
		dirname( JPWBC_PLUGIN_BASENAME ) . '/languages'
	);
}
add_action( 'init', 'jpwbc_load_textdomain' );

/**
 * Locate and load a plugin template, allowing theme overrides.
 *
 * Lookup order: child theme → parent theme → plugin. Themes override by placing
 * a file at `{theme}/jezpress-woo-brand-categories/{name}`.
 *
 * @since 1.0.0
 *
 * @param string               $name Template file name (e.g. 'dropdown.php').
 * @param array<string, mixed> $args Variables exposed to the template as $data.
 * @return string Rendered template HTML.
 */
function jpwbc_get_template( string $name, array $args = array() ): string {
	$name     = ltrim( str_replace( array( '..', "\0" ), '', $name ), '/' );
	$located  = '';
	$rel_path = 'jezpress-woo-brand-categories/' . $name;

	$theme_file = locate_template( array( $rel_path ) );
	if ( $theme_file ) {
		$located = $theme_file;
	} elseif ( file_exists( JPWBC_PLUGIN_DIR . 'templates/' . $name ) ) {
		$located = JPWBC_PLUGIN_DIR . 'templates/' . $name;
	}

	if ( '' === $located ) {
		return '';
	}

	$data = $args; // Exposed to the template.
	ob_start();
	include $located;
	return (string) ob_get_clean();
}

/**
 * Include required class files
 *
 * @since 1.0.0
 */
function jpwbc_include_files(): void {
	// Core classes
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-loader.php';

	// Admin settings
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-admin.php';

	// License integration
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-license.php';

	// JezPress updater
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-updater.php';

	// Feature classes (cache, query, rewrites, frontend, SEO).
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-cache.php';
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-query.php';
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-rewrites.php';
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-frontend.php';
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-seo-rankmath.php';
}

/**
 * Initialize the plugin
 *
 * @since 1.0.0
 */
function jpwbc_init(): void {
	// Include class files
	jpwbc_include_files();

	// Initialize the loader
	$loader = new JPWBC_Loader();

	// Initialize admin
	$admin = new JPWBC_Admin();

	// Register admin hooks
	$loader->add_action( 'admin_menu', $admin, 'register_menu' );
	$loader->add_action( 'admin_init', $admin, 'register_settings' );
	$loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_admin_assets', 10, 1 );
	$loader->add_action( 'admin_post_jpwbc_save_settings', $admin, 'handle_save_settings' );

	// Initialize license (singleton pattern with required parameters)
	$license = JPWBC_License::get_instance(
		__FILE__,
		'jezpress-woo-brand-categories',
		'JezPress Woo Brand Categories'
	);

	// Initialize license hooks (License::init() also registers the jpwbc_license_check cron hook).
	$license->init();

	// Register with JezPress Manager dashboard
	if ( class_exists( 'JezPress_Manager' ) && method_exists( 'JezPress_Manager', 'register' ) ) {
		JezPress_Manager::register(
			array(
				'slug'           => 'jezpress-woo-brand-categories',
				'name'           => 'JezPress Woo Brand Categories',
				'version'        => JPWBC_VERSION,
				'description'    => 'In-brand product-category navigation and clean brand+category URLs for WooCommerce brand archives.',
				'icon'           => 'dashicons-admin-generic',
				'license_status' => $license->get_status(),
				'settings_url'   => admin_url( 'admin.php?page=jpwbc-settings' ),
			)
		);
	}

	// Initialize updater
	$updater = new JPWBC_Updater( __FILE__ );
	$updater->set_slug( 'jezpress-woo-brand-categories' )
		->set_license( $license->get_license_key() )
		->initialize();

	// --- Feature layer (requires WooCommerce; gated by licence + master toggle) ---
	if ( class_exists( 'WooCommerce' ) ) {
		$settings = JPWBC_Admin::get_settings();

		// Cache busting always runs so data stays fresh even while unlicensed.
		$cache = new JPWBC_Cache();
		$cache->register_hooks();

		$query    = new JPWBC_Query( $cache );
		$feature  = $license->is_valid() && ! empty( $settings['enabled'] );
		$rewrites = new JPWBC_Rewrites( $query, $feature && ! empty( $settings['clean_urls'] ) );

		if ( $feature ) {
			$rewrites->register_hooks();

			$frontend = new JPWBC_Frontend( $query, $rewrites, $settings );
			$frontend->register_hooks();

			$seo = new JPWBC_SEO_RankMath( $query, $rewrites, $settings );
			$seo->register_hooks();

			// Register the Elementor widget when Elementor is active.
			add_action( 'elementor/widgets/register', 'jpwbc_register_elementor_widget' );
		}

		// Admin Combo Preview + Cache tabs (available to admins regardless of licence).
		if ( is_admin() ) {
			require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-admin-feature.php';
			$admin_feature = new JPWBC_Admin_Feature( $query, $cache, $rewrites, $settings );
			$admin_feature->register_hooks();
		}
	}

	// Run all hooks
	$loader->run();
}
add_action( 'plugins_loaded', 'jpwbc_init', 20 );

/**
 * Register the Elementor widget.
 *
 * @since 1.0.0
 *
 * @param mixed $widgets_manager Elementor widgets manager.
 */
function jpwbc_register_elementor_widget( $widgets_manager ): void {
	require_once JPWBC_PLUGIN_DIR . 'includes/class-jpwbc-elementor-widget.php';
	if ( class_exists( 'JPWBC_Elementor_Widget' ) && is_object( $widgets_manager ) && method_exists( $widgets_manager, 'register' ) ) {
		$widgets_manager->register( new JPWBC_Elementor_Widget() );
	}
}

/**
 * Surface the WooCommerce / product_brand dependency notice in admin.
 *
 * Runs on `admin_init` so the taxonomy (registered on `init`) is present.
 */
add_action(
	'admin_init',
	static function (): void {
		if ( ! jpwbc_woocommerce_ready() ) {
			add_action( 'admin_notices', 'jpwbc_dependency_notice' );
		}
	}
);

/**
 * Display admin notice for invalid or inactive license
 *
 * @since 1.0.0
 */
function jpwbc_license_admin_notice(): void {
	// Only show to users who can manage options
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Don't show on the plugin settings page
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['page'] ) && 'jpwbc-settings' === $_GET['page'] ) {
		return;
	}

	// Check license status (use singleton)
	$license = JPWBC_License::get_instance();
	if ( null === $license ) {
		return;
	}
	$status = $license->get_status();

	// Only show notice if license is not active
	if ( 'active' === $status ) {
		return;
	}

	$message = '';
	$class   = 'notice-warning';

	$license_url = admin_url( 'admin.php?page=jpwbc-settings&tab=license' );

	match ( $status ) {
		'inactive' => $message = sprintf(
			/* translators: %s: URL to settings page */
			__( '<strong>JezPress Woo Brand Categories:</strong> Please activate your license to receive updates and support. <a href="%s">Enter License Key</a>', 'jezpress-woo-brand-categories' ),
			$license_url
		),
		'expired'  => $message = sprintf(
			/* translators: 1: URL to renewal page, 2: URL to settings page */
			__( '<strong>JezPress Woo Brand Categories:</strong> Your license has expired. <a href="%1$s" target="_blank">Renew your license</a> to continue receiving updates and support. <a href="%2$s">Manage License</a>', 'jezpress-woo-brand-categories' ),
			'https://updates.jezpress.com/renew/',
			$license_url
		),
		'invalid'  => $message = sprintf(
			/* translators: %s: URL to settings page */
			__( '<strong>JezPress Woo Brand Categories:</strong> Your license key is invalid. <a href="%s">Update License Key</a>', 'jezpress-woo-brand-categories' ),
			$license_url
		),
		'disabled' => $message = sprintf(
			/* translators: 1: URL to support page, 2: URL to settings page */
			__( '<strong>JezPress Woo Brand Categories:</strong> Your license has been disabled. Please <a href="%1$s" target="_blank">contact support</a>. <a href="%2$s">Manage License</a>', 'jezpress-woo-brand-categories' ),
			'https://updates.jezpress.com/support/',
			$license_url
		),
		default    => $message = '',
	};

	// Set error class for invalid and disabled statuses
	if ( in_array( $status, array( 'invalid', 'disabled' ), true ) ) {
		$class = 'notice-error';
	}

	if ( ! empty( $message ) ) {
		printf(
			'<div class="notice %s"><p>%s</p></div>',
			esc_attr( $class ),
			wp_kses_post( $message )
		);
	}
}
add_action( 'admin_notices', 'jpwbc_license_admin_notice' );
