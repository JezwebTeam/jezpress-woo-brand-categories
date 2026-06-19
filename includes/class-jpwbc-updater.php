<?php
/**
 * JezPress Updater
 *
 * Automatic plugin updates from the JezPress update server.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Updater class
 *
 * @since 1.0.0
 */
class JPWBC_Updater {

	/**
	 * JezPress base URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $api_url = 'https://updates.jezpress.com';

	/**
	 * Absolute path to the main plugin file.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $file;

	/**
	 * Plugin data from the plugin header.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private array $plugin = array();

	/**
	 * Plugin basename (e.g. jezpress-woo-brand-categories/jezpress-woo-brand-categories.php).
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $basename = '';

	/**
	 * Plugin slug on JezPress.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $slug = '';

	/**
	 * Cached API response (in-memory).
	 *
	 * @since 1.0.0
	 * @var object|null
	 */
	private ?object $api_response = null;

	/**
	 * License key for authenticated requests.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $license_key = '';

	/**
	 * Transient cache duration in seconds (12 hours).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private int $cache_duration = 43200;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file Absolute path to the main plugin file (__FILE__ from main plugin).
	 */
	public function __construct( string $file ) {
		if ( empty( $file ) || ! file_exists( $file ) ) {
			return;
		}
		$this->file = $file;
	}

	/**
	 * Lazy-load plugin header data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function ensure_plugin_properties(): void {
		if ( ! empty( $this->plugin ) && ! empty( $this->basename ) ) {
			return;
		}

		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$this->plugin   = get_plugin_data( $this->file );
		$this->basename = plugin_basename( $this->file );
	}

	/**
	 * Set JezPress plugin slug.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Plugin slug on JezPress.
	 * @return $this
	 */
	public function set_slug( string $slug ): self {
		$this->slug = sanitize_title( $slug );
		return $this;
	}

	/**
	 * Set license key for authenticated downloads.
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key License key.
	 * @return $this
	 */
	public function set_license( string $license_key ): self {
		$this->license_key = sanitize_text_field( $license_key );
		return $this;
	}

	/**
	 * Set API base URL (must be HTTPS).
	 *
	 * @since 1.0.0
	 *
	 * @param string $url API base URL.
	 * @return $this
	 */
	public function set_api_url( string $url ): self {
		$url = esc_url_raw( $url );

		if ( 0 !== strpos( $url, 'https://' ) ) {
			$url = str_replace( 'http://', 'https://', $url );
		}

		$this->api_url = rtrim( $url, '/' );
		return $this;
	}

	/**
	 * Register WordPress update hooks.
	 *
	 * IMPORTANT: Do NOT wrap in is_admin(). WP cron auto-updates need these
	 * hooks to fire outside admin context.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function initialize(): void {
		if ( empty( $this->slug ) ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_update' ) );
		add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'handle_manual_check' ) );
		add_action( 'upgrader_process_complete', array( $this, 'clear_cache_on_update' ), 10, 2 );
	}

	/**
	 * Fetch update info from JezPress /api/v1/update endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_refresh Bypass transient cache.
	 * @return object|false Remote info object or false on failure.
	 */
	private function get_remote_info( bool $force_refresh = false ): object|false {
		if ( ! $force_refresh && ! empty( $this->api_response ) ) {
			return $this->api_response;
		}

		$cache_key = 'jpwbc_update_' . md5( $this->slug . $this->license_key );

		if ( ! $force_refresh ) {
			$cached = get_transient( $cache_key );
			if ( false !== $cached && is_object( $cached ) && ! empty( $cached->version ) ) {
				$this->api_response = $cached;
				return $cached;
			}
		}

		$this->ensure_plugin_properties();

		$params = array(
			'plugin'  => $this->slug,
			'version' => ! empty( $this->plugin['Version'] ) ? $this->plugin['Version'] : '0.0.0',
		);

		if ( ! empty( $this->license_key ) ) {
			$params['license_key'] = $this->license_key;
		}

		if ( function_exists( 'home_url' ) ) {
			$params['site_url'] = home_url();
		}

		$url = add_query_arg( $params, $this->api_url . '/api/v1/update' );

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 15,
				'sslverify' => true,
				'headers'   => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log( 'API request failed: ' . $response->get_error_message() );
			return false;
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$this->log( 'API returned HTTP ' . wp_remote_retrieve_response_code( $response ) );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		if ( ! is_object( $body ) || empty( $body->version ) ) {
			$this->log( 'Invalid API response: ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		$info               = new stdClass();
		$info->version      = sanitize_text_field( $body->version );
		$info->download_url = isset( $body->download_url ) ? esc_url_raw( $body->download_url ) : '';
		$info->changelog    = isset( $body->changelog ) ? wp_kses_post( $body->changelog ) : '';
		$info->requires_wp  = isset( $body->requires_wp ) ? sanitize_text_field( $body->requires_wp ) : '';
		$info->requires_php = isset( $body->requires_php ) ? sanitize_text_field( $body->requires_php ) : '';
		$info->tested_wp    = isset( $body->tested_wp ) ? sanitize_text_field( $body->tested_wp ) : '';
		$info->name         = isset( $body->name ) ? sanitize_text_field( $body->name ) : '';
		$info->author       = isset( $body->author ) ? sanitize_text_field( $body->author ) : '';
		$info->last_updated = isset( $body->last_updated ) ? sanitize_text_field( $body->last_updated ) : '';
		$info->description  = isset( $body->description ) ? wp_kses_post( $body->description ) : '';

		$this->api_response = $info;
		set_transient( $cache_key, $info, $this->cache_duration );

		return $info;
	}

	/**
	 * Inject our plugin into WordPress update checks.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $transient The update_plugins transient.
	 * @return mixed
	 */
	public function check_update( mixed $transient ): mixed {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$this->ensure_plugin_properties();

		if ( empty( $this->plugin['Version'] ) || empty( $this->basename ) ) {
			return $transient;
		}

		$remote = $this->get_remote_info();

		if ( false === $remote || empty( $remote->version ) ) {
			return $transient;
		}

		if ( version_compare( $remote->version, $this->plugin['Version'], '>' ) ) {
			$transient->response[ $this->basename ] = (object) array(
				'id'           => $this->basename,
				'slug'         => dirname( $this->basename ),
				'plugin'       => $this->basename,
				'new_version'  => $remote->version,
				'url'          => '',
				'package'      => $remote->download_url,
				'icons'        => array(),
				'banners'      => array(),
				'tested'       => $remote->tested_wp,
				'requires_php' => $remote->requires_php,
				'requires'     => $remote->requires_wp,
			);
		} else {
			$transient->no_update[ $this->basename ] = (object) array(
				'id'          => $this->basename,
				'slug'        => dirname( $this->basename ),
				'plugin'      => $this->basename,
				'new_version' => $this->plugin['Version'],
				'url'         => '',
				'package'     => '',
			);
		}

		return $transient;
	}

	/**
	 * Provide plugin info for the "View details" popup.
	 *
	 * @since 1.0.0
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object|array
	 */
	public function plugin_info( false|object|array $result, string $action, object $args ): false|object|array {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		$this->ensure_plugin_properties();

		if ( ! isset( $args->slug ) || dirname( $this->basename ) !== $args->slug ) {
			return $result;
		}

		$remote = $this->get_remote_info();

		if ( false === $remote ) {
			return $result;
		}

		return (object) array(
			'name'              => ! empty( $remote->name ) ? $remote->name : $this->plugin['Name'],
			'slug'              => dirname( $this->basename ),
			'version'           => $remote->version,
			'author'            => ! empty( $remote->author ) ? $remote->author : $this->plugin['AuthorName'],
			'author_profile'    => $this->plugin['AuthorURI'],
			'last_updated'      => $remote->last_updated,
			'homepage'          => $this->plugin['PluginURI'],
			'short_description' => $this->plugin['Description'],
			'sections'          => array(
				'description' => ! empty( $remote->description ) ? $remote->description : $this->plugin['Description'],
				'changelog'   => $remote->changelog,
			),
			'download_link'     => $remote->download_url,
			'requires'          => $remote->requires_wp,
			'requires_php'      => $remote->requires_php,
			'tested'            => $remote->tested_wp,
		);
	}

	/**
	 * Add "Check for updates" link to plugin row meta.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $links Plugin row meta links.
	 * @param string             $file  Plugin basename.
	 * @return array<int, string>
	 */
	public function plugin_row_meta( array $links, string $file ): array {
		$this->ensure_plugin_properties();

		if ( $file !== $this->basename ) {
			return $links;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}

		$url = wp_nonce_url(
			admin_url( 'plugins.php?jpwbc_check=' . rawurlencode( $this->slug ) ),
			'jpwbc_check_' . $this->slug,
			'jpwbc_nonce'
		);

		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'jezpress-woo-brand-categories' ) . '</a>';

		return $links;
	}

	/**
	 * Handle the manual "Check for updates" click.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_manual_check(): void {
		if ( ! isset( $_GET['jpwbc_check'] ) || $_GET['jpwbc_check'] !== $this->slug ) {
			return;
		}

		if ( ! current_user_can( 'update_plugins' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jezpress-woo-brand-categories' ), 403 );
		}

		if ( ! isset( $_GET['jpwbc_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['jpwbc_nonce'] ) ), 'jpwbc_check_' . $this->slug ) ) {
			wp_die( esc_html__( 'Security check failed.', 'jezpress-woo-brand-categories' ), 403 );
		}

		$this->clear_cache();
		delete_site_transient( 'update_plugins' );

		wp_safe_redirect( admin_url( 'plugins.php?jpwbc_checked=1' ) );
		exit;
	}

	/**
	 * Clear our cache after this plugin has been updated.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP_Upgrader $upgrader Upgrader instance.
	 * @param array        $options  Upgrade options.
	 * @return void
	 */
	public function clear_cache_on_update( \WP_Upgrader $upgrader, array $options ): void {
		if ( 'update' !== $options['action'] || 'plugin' !== $options['type'] ) {
			return;
		}

		$this->ensure_plugin_properties();

		if ( isset( $options['plugins'] ) && in_array( $this->basename, (array) $options['plugins'], true ) ) {
			$this->clear_cache();
		}
	}

	/**
	 * Clear cached API response.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		delete_transient( 'jpwbc_update_' . md5( $this->slug . $this->license_key ) );
		$this->api_response = null;
	}

	/**
	 * Log a message when WP_DEBUG is on.
	 *
	 * @since 1.0.0
	 *
	 * @param string $message Message to log.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[JPWBC Updater] ' . $this->slug . ': ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
