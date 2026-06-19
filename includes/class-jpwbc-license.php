<?php
/**
 * License Handler
 *
 * Handles license validation, activation, and deactivation.
 * Integrates with JezPress licensing system with anti-nulling protections.
 *
 * Based on the JezPress Secure License Handler pattern.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * License class
 *
 * @since 1.0.0
 */
class JPWBC_License {

	/**
	 * JezPress License API URL.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $api_url = 'https://updates.jezpress.com';

	/**
	 * Plugin slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $slug;

	/**
	 * Plugin name for display.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $plugin_name;

	/**
	 * Plugin file path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $plugin_file;

	/**
	 * Option name for storing license data.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $option_name;

	/**
	 * Menu slug for the license settings page.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $menu_slug;

	/**
	 * Whether license is required for plugin to work.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private bool $required = true;

	/**
	 * Cache duration in seconds (12 hours).
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private int $cache_duration = 43200;

	/**
	 * Cached license data.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>|null
	 */
	private ?array $license_data = null;

	/**
	 * Security salt for data encryption.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $security_salt;

	/**
	 * Singleton instance.
	 *
	 * @since 1.0.0
	 * @var JPWBC_License|null
	 */
	private static ?JPWBC_License $instance = null;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file Plugin main file path.
	 * @param string $slug        Plugin slug on JezPress.
	 * @param string $plugin_name Plugin display name.
	 */
	public function __construct( string $plugin_file, string $slug, string $plugin_name ) {
		$this->plugin_file   = $plugin_file;
		$this->slug          = sanitize_title( $slug );
		$this->plugin_name   = sanitize_text_field( $plugin_name );
		$this->option_name   = 'jzwb_lic_' . substr( md5( $this->slug ), 0, 8 );
		$this->menu_slug     = 'jpwbc-settings';
		$this->security_salt = $this->generate_site_salt();
	}

	/**
	 * Get singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @param string $plugin_file Plugin main file path.
	 * @param string $slug        Plugin slug on JezPress.
	 * @param string $plugin_name Plugin display name.
	 * @return JPWBC_License|null License instance or null if not initialized.
	 */
	public static function get_instance( string $plugin_file = '', string $slug = '', string $plugin_name = '' ): ?JPWBC_License {
		if ( null === self::$instance && '' !== $plugin_file ) {
			self::$instance = new self( $plugin_file, $slug, $plugin_name );
		}
		return self::$instance;
	}

	/**
	 * Generate site-specific salt for encryption.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function generate_site_salt(): string {
		$components = array(
			defined( 'AUTH_KEY' ) ? AUTH_KEY : 'jzwb',
			$this->slug,
			wp_parse_url( home_url(), PHP_URL_HOST ),
		);
		return hash( 'sha256', implode( '|', $components ) );
	}

	/**
	 * Initialize license handler — registers admin hooks.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'admin_init', array( $this, 'handle_license_actions' ) );
		add_action( 'admin_init', array( $this, 'verify_integrity' ), 1 );
		// Note: admin_notices intentionally NOT registered here — the main plugin file
		// renders nuanced status-aware notices via jpwbc_license_admin_notice().

		add_filter( 'plugin_action_links_' . plugin_basename( $this->plugin_file ), array( $this, 'plugin_action_links' ) );

		add_action( 'jpwbc_license_check', array( $this, 'scheduled_license_check' ) );

		if ( ! wp_next_scheduled( 'jpwbc_license_check' ) ) {
			wp_schedule_event( time(), 'daily', 'jpwbc_license_check' );
		}
	}

	/**
	 * Verify plugin integrity on admin init.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function verify_integrity(): void {
		if ( ! $this->verify_source() ) {
			$this->invalidate_license();
		}

		if ( ! $this->verify_stored_data() ) {
			$this->invalidate_license();
		}
	}

	/**
	 * Verify plugin source is from JezPress.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function verify_source(): bool {
		$marker_file = dirname( $this->plugin_file ) . '/.jezpress';

		if ( ! file_exists( $marker_file ) ) {
			$update_check = get_site_transient( 'update_plugins' );
			if ( $update_check && isset( $update_check->response[ plugin_basename( $this->plugin_file ) ] ) ) {
				@file_put_contents( $marker_file, $this->generate_source_signature() ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			}
		}

		return true; // Allow for initial installation.
	}

	/**
	 * Generate source signature.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	private function generate_source_signature(): string {
		return hash( 'sha256', $this->slug . '|' . ( defined( 'JPWBC_VERSION' ) ? JPWBC_VERSION : '1.0.0' ) . '|jezpress' );
	}

	/**
	 * Verify stored license data integrity.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function verify_stored_data(): bool {
		$data = get_option( $this->option_name );

		if ( empty( $data ) ) {
			return true;
		}

		if ( ! is_array( $data ) ) {
			return false;
		}

		if ( ! isset( $data['_sig'] ) ) {
			return false;
		}

		if ( isset( $data['_enc'] ) && $data['_enc'] ) {
			$data = $this->decrypt_license_data( $data );
		}

		$expected_sig = $this->generate_data_signature( $data );
		return hash_equals( $expected_sig, $data['_sig'] );
	}

	/**
	 * Generate data signature for integrity check.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data License data.
	 * @return string
	 */
	private function generate_data_signature( array $data ): string {
		$to_sign = array(
			isset( $data['key'] ) ? $data['key'] : '',
			isset( $data['status'] ) ? $data['status'] : '',
			isset( $data['domain'] ) ? $data['domain'] : '',
			$this->security_salt,
		);
		return hash( 'sha256', implode( '|', $to_sign ) );
	}

	/**
	 * Invalidate and clear stored license data.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function invalidate_license(): void {
		delete_option( $this->option_name );
		$this->license_data = null;
	}

	/**
	 * Check if license is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_check Force remote check.
	 * @return bool True if license is valid.
	 */
	public function is_valid( bool $force_check = false ): bool {
		$license_data = $this->get_license_data( $force_check );

		if ( empty( $license_data ) || empty( $license_data['key'] ) ) {
			return false;
		}

		if ( ! isset( $license_data['status'] ) || 'active' !== $license_data['status'] ) {
			return false;
		}

		if ( isset( $license_data['domain'] ) ) {
			$current_domain = wp_parse_url( home_url(), PHP_URL_HOST );
			if ( $license_data['domain'] !== $current_domain ) {
				return false;
			}
		}

		if ( ! $this->verify_stored_data() ) {
			return false;
		}

		if ( isset( $license_data['expires'] ) && 'lifetime' !== $license_data['expires'] ) {
			$expires = strtotime( $license_data['expires'] );
			if ( $expires && $expires < time() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if license is active (alias for is_valid).
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if license is active.
	 */
	public function is_active(): bool {
		return $this->is_valid();
	}

	/**
	 * Get stored license key.
	 *
	 * @since 1.0.0
	 *
	 * @return string License key or empty string.
	 */
	public function get_license_key(): string {
		$license_data = $this->get_license_data();
		return isset( $license_data['key'] ) ? $license_data['key'] : '';
	}

	/**
	 * Get license data from database.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $force_check Force remote validation.
	 * @return array<string, mixed> License data.
	 */
	public function get_license_data( bool $force_check = false ): array {
		if ( null !== $this->license_data && ! $force_check ) {
			return $this->license_data;
		}

		$stored_data = get_option( $this->option_name, array() );
		$this->license_data = is_array( $stored_data ) ? $stored_data : array();

		if ( isset( $this->license_data['_enc'] ) && $this->license_data['_enc'] ) {
			$this->license_data = $this->decrypt_license_data( $this->license_data );
		}

		if ( $force_check || $this->needs_revalidation() ) {
			$this->validate_license_remote();
		}

		return $this->license_data;
	}

	/**
	 * Check if license needs revalidation.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	private function needs_revalidation(): bool {
		if ( empty( $this->license_data['key'] ) ) {
			return false;
		}

		$last_check = isset( $this->license_data['last_check'] ) ? (int) $this->license_data['last_check'] : 0;
		return ( time() - $last_check ) > $this->cache_duration;
	}

	/**
	 * Encrypt license data for storage.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data License data.
	 * @return array<string, mixed> Encrypted data.
	 */
	private function encrypt_license_data( array $data ): array {
		$key_to_encrypt = isset( $data['key'] ) ? $data['key'] : '';

		if ( $key_to_encrypt ) {
			$data['key']  = base64_encode( $this->xor_encrypt( $key_to_encrypt ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			$data['_enc'] = true;
		}

		return $data;
	}

	/**
	 * Decrypt license data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $data Encrypted license data.
	 * @return array<string, mixed> Decrypted data.
	 */
	private function decrypt_license_data( array $data ): array {
		if ( isset( $data['key'] ) && isset( $data['_enc'] ) && $data['_enc'] ) {
			$decoded = base64_decode( $data['key'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
			if ( false !== $decoded ) {
				$data['key'] = $this->xor_encrypt( $decoded );
			}
			unset( $data['_enc'] );
		}
		return $data;
	}

	/**
	 * Simple XOR encryption/decryption.
	 *
	 * @since 1.0.0
	 *
	 * @param string $data Data to encrypt/decrypt.
	 * @return string Result.
	 */
	private function xor_encrypt( string $data ): string {
		$key    = $this->security_salt;
		$result = '';

		$data_length = strlen( $data );
		$key_length  = strlen( $key );

		for ( $i = 0; $i < $data_length; $i++ ) {
			$result .= $data[ $i ] ^ $key[ $i % $key_length ];
		}

		return $result;
	}

	/**
	 * Activate license.
	 *
	 * @since 1.0.0
	 *
	 * @param string $license_key License key.
	 * @return array{success: bool, message: string} Result with 'success' and 'message'.
	 */
	public function activate( string $license_key ): array {
		$license_key = $this->sanitize_license_key( $license_key );

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'Please enter a valid license key.', 'jezpress-woo-brand-categories' ),
			);
		}

		$response = $this->api_request( 'activate', $license_key );

		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}

		if ( isset( $response->success ) && $response->success ) {
			$current_domain = wp_parse_url( home_url(), PHP_URL_HOST );

			$this->license_data = array(
				'key'         => $license_key,
				'status'      => 'active',
				'domain'      => $current_domain ? $current_domain : '',
				'customer'    => isset( $response->customer ) ? sanitize_text_field( $response->customer ) : '',
				'email'       => isset( $response->email ) ? sanitize_email( $response->email ) : '',
				'expires'     => isset( $response->expires ) ? sanitize_text_field( $response->expires ) : 'lifetime',
				'activations' => isset( $response->activations ) ? absint( $response->activations ) : 0,
				'limit'       => isset( $response->limit ) ? ( 'unlimited' === $response->limit ? 'unlimited' : absint( $response->limit ) ) : 0,
				'last_check'  => time(),
				'activated'   => time(),
			);

			$this->license_data['_sig'] = $this->generate_data_signature( $this->license_data );
			$data_to_save               = $this->encrypt_license_data( $this->license_data );
			update_option( $this->option_name, $data_to_save );

			return array(
				'success' => true,
				'message' => __( 'License activated successfully!', 'jezpress-woo-brand-categories' ),
			);
		}

		$error_message = isset( $response->message ) ? $response->message : __( 'License activation failed.', 'jezpress-woo-brand-categories' );

		return array(
			'success' => false,
			'message' => $error_message,
		);
	}

	/**
	 * Deactivate license.
	 *
	 * @since 1.0.0
	 *
	 * @return array{success: bool, message: string} Result with 'success' and 'message'.
	 */
	public function deactivate(): array {
		$license_key = $this->get_license_key();

		if ( empty( $license_key ) ) {
			return array(
				'success' => false,
				'message' => __( 'No license key to deactivate.', 'jezpress-woo-brand-categories' ),
			);
		}

		$this->api_request( 'deactivate', $license_key );

		// Clear local data regardless of API response.
		$this->license_data = array();
		delete_option( $this->option_name );

		return array(
			'success' => true,
			'message' => __( 'License deactivated successfully.', 'jezpress-woo-brand-categories' ),
		);
	}

	/**
	 * Validate license remotely.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if valid.
	 */
	private function validate_license_remote(): bool {
		$license_key = isset( $this->license_data['key'] ) ? $this->license_data['key'] : '';

		if ( empty( $license_key ) ) {
			return false;
		}

		$response = $this->api_request( 'validate', $license_key );

		if ( is_wp_error( $response ) ) {
			// Keep existing status on network error, but update last check time.
			$this->license_data['last_check'] = time();
			$this->license_data['_sig']       = $this->generate_data_signature( $this->license_data );
			update_option( $this->option_name, $this->encrypt_license_data( $this->license_data ) );
			return isset( $this->license_data['status'] ) && 'active' === $this->license_data['status'];
		}

		if ( isset( $response->valid ) && $response->valid ) {
			$this->license_data['status']      = 'active';
			$this->license_data['last_check']  = time();
			$this->license_data['activations'] = isset( $response->activations ) ? absint( $response->activations ) : 0;
			$this->license_data['expires']     = isset( $response->expires ) ? sanitize_text_field( $response->expires ) : 'lifetime';
		} else {
			$this->license_data['status']     = 'inactive';
			$this->license_data['last_check'] = time();
		}

		$this->license_data['_sig'] = $this->generate_data_signature( $this->license_data );
		update_option( $this->option_name, $this->encrypt_license_data( $this->license_data ) );

		return 'active' === $this->license_data['status'];
	}

	/**
	 * Make API request to the JezPress license endpoint.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action      API action (activate, deactivate, validate).
	 * @param string $license_key License key.
	 * @return \stdClass|\WP_Error API response or error.
	 */
	private function api_request( string $action, string $license_key ): \stdClass|\WP_Error {
		$url = sprintf(
			'%s/api/v1/license/%s',
			$this->api_url,
			sanitize_key( $action )
		);

		$plugin_ver = defined( 'JPWBC_VERSION' ) ? JPWBC_VERSION : '1.0.0';

		$body = array(
			'license_key' => $license_key,
			'plugin'      => $this->slug,
			'site_url'    => home_url(),
			'site_name'   => get_bloginfo( 'name' ),
			'wp_version'  => get_bloginfo( 'version' ),
			'php_version' => PHP_VERSION,
			'plugin_ver'  => $plugin_ver,
		);

		$args = array(
			'timeout'   => 15,
			'sslverify' => true,
			'headers'   => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'User-Agent'   => 'JezPress/' . $plugin_ver . '; ' . home_url(),
			),
			'body'      => wp_json_encode( $body ),
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'api_error',
				/* translators: %d: HTTP status code */
				sprintf( __( 'License server returned error code: %d', 'jezpress-woo-brand-categories' ), $code )
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'json_error', __( 'Invalid response from license server.', 'jezpress-woo-brand-categories' ) );
		}

		return $data;
	}

	/**
	 * Sanitize license key input.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key License key.
	 * @return string Sanitized key.
	 */
	private function sanitize_license_key( string $key ): string {
		$key = strtoupper( trim( $key ) );
		$key = preg_replace( '/[^A-Z0-9\-]/', '', $key );
		return $key ?? '';
	}

	/**
	 * Handle license activate/deactivate form submissions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function handle_license_actions(): void {
		$this->check_transient_message();

		// Check if this is a license form submission (early exit if not)
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified below after confirming POST data exists
		if ( ! isset( $_POST['jezweb_license_action'] ) || ! isset( $_POST['jezweb_license_nonce'] ) ) {
			return;
		}

		// Verify capability first
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Sanitize action for nonce verification
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified immediately below
		$action = isset( $_POST['jezweb_license_action'] ) ? sanitize_key( wp_unslash( $_POST['jezweb_license_action'] ) ) : '';

		// Verify nonce before processing any other data
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jezweb_license_nonce'] ) ), 'jezweb_license_' . $action ) ) {
			add_settings_error( $this->menu_slug, 'nonce_error', __( 'Security check failed.', 'jezpress-woo-brand-categories' ), 'error' );
			return;
		}

		// Verify slug matches this plugin instance
		if ( ! isset( $_POST['jezweb_license_slug'] ) || sanitize_text_field( wp_unslash( $_POST['jezweb_license_slug'] ) ) !== $this->slug ) {
			return;
		}

		if ( 'activate' === $action ) {
			$license_key = isset( $_POST['jezweb_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['jezweb_license_key'] ) ) : '';
			$result      = $this->activate( $license_key );

			if ( $result['success'] ) {
				set_transient( 'jezweb_license_message_' . $this->slug, array(
					'type'    => 'success',
					'message' => $result['message'],
				), 30 );
				wp_safe_redirect( admin_url( 'admin.php?page=jpwbc-settings&tab=license&activated=1' ) );
				exit;
			} else {
				add_settings_error( $this->menu_slug, 'activation_failed', $result['message'], 'error' );
			}
		} elseif ( 'deactivate' === $action ) {
			$result = $this->deactivate();

			if ( $result['success'] ) {
				set_transient( 'jezweb_license_message_' . $this->slug, array(
					'type'    => 'success',
					'message' => $result['message'],
				), 30 );
				wp_safe_redirect( admin_url( 'admin.php?page=jpwbc-settings&tab=license&deactivated=1' ) );
				exit;
			} else {
				add_settings_error( $this->menu_slug, 'deactivation_failed', $result['message'], 'error' );
			}
		}
	}

	/**
	 * Check for transient message after redirect.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	private function check_transient_message(): void {
		$transient_message = get_transient( 'jezweb_license_message_' . $this->slug );
		if ( $transient_message && is_array( $transient_message ) ) {
			delete_transient( 'jezweb_license_message_' . $this->slug );
			add_settings_error(
				$this->menu_slug,
				'success' === $transient_message['type'] ? 'license_success' : 'license_error',
				$transient_message['message'],
				$transient_message['type']
			);
		}
	}

	/**
	 * Display admin notice when license is missing.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function admin_notices(): void {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Suppress on the plugin settings page — the License tab shows status inline.
		if ( false !== strpos( $screen->id, 'jpwbc-settings' ) ) {
			return;
		}

		if ( $this->required && ! $this->is_valid() ) {
			$license_url = admin_url( 'admin.php?page=jpwbc-settings&tab=license' );
			?>
			<div class="notice notice-error">
				<p>
					<strong><?php echo esc_html( $this->plugin_name ); ?>:</strong>
					<?php esc_html_e( 'License activation required to enable plugin features.', 'jezpress-woo-brand-categories' ); ?>
					<a href="<?php echo esc_url( $license_url ); ?>">
						<?php esc_html_e( 'Activate License', 'jezpress-woo-brand-categories' ); ?>
					</a>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add Settings and License links to plugin action links.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, string> $links Plugin action links.
	 * @return array<int, string> Modified links.
	 */
	public function plugin_action_links( array $links ): array {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=jpwbc-settings' ),
			__( 'Settings', 'jezpress-woo-brand-categories' )
		);
		$license_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=jpwbc-settings&tab=license' ),
			__( 'License', 'jezpress-woo-brand-categories' )
		);

		array_unshift( $links, $license_link );
		array_unshift( $links, $settings_link );

		return $links;
	}

	/**
	 * Render the license tab content (no page wrap — embedded in the admin tab).
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function render_tab_content(): void {
		$license_data = $this->get_license_data();
		$is_active    = $this->is_valid();
		$license_key  = isset( $license_data['key'] ) ? $license_data['key'] : '';
		?>
		<div style="max-width:800px; margin-top:20px;">

			<?php settings_errors( $this->menu_slug ); ?>

			<div class="jpwbc-card" style="background:#fff;padding:20px;border:1px solid #c3c4c7;box-shadow:0 1px 1px rgba(0,0,0,.04);">
				<h2 style="margin-top:0;">
					<?php esc_html_e( 'License Status', 'jezpress-woo-brand-categories' ); ?>
					<?php if ( $is_active ) : ?>
						<span style="background:#00a32a;color:#fff;padding:3px 8px;border-radius:3px;font-size:12px;margin-left:10px;"><?php esc_html_e( 'Active', 'jezpress-woo-brand-categories' ); ?></span>
					<?php else : ?>
						<span style="background:#d63638;color:#fff;padding:3px 8px;border-radius:3px;font-size:12px;margin-left:10px;"><?php esc_html_e( 'Inactive', 'jezpress-woo-brand-categories' ); ?></span>
					<?php endif; ?>
				</h2>

				<?php if ( $is_active ) : ?>
					<div class="jpwbc-license-info">
						<p><strong><?php esc_html_e( 'License Key:', 'jezpress-woo-brand-categories' ); ?></strong> <?php echo esc_html( $this->mask_license_key( $license_key ) ); ?></p>
						<?php if ( ! empty( $license_data['customer'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Licensed to:', 'jezpress-woo-brand-categories' ); ?></strong> <?php echo esc_html( $license_data['customer'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $license_data['email'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Email:', 'jezpress-woo-brand-categories' ); ?></strong> <?php echo esc_html( $license_data['email'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $license_data['domain'] ) ) : ?>
							<p><strong><?php esc_html_e( 'Domain:', 'jezpress-woo-brand-categories' ); ?></strong> <?php echo esc_html( $license_data['domain'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $license_data['expires'] ) && 'lifetime' !== $license_data['expires'] ) : ?>
							<p><strong><?php esc_html_e( 'Expires:', 'jezpress-woo-brand-categories' ); ?></strong> <?php echo esc_html( $license_data['expires'] ); ?></p>
						<?php elseif ( 'lifetime' === ( $license_data['expires'] ?? '' ) ) : ?>
							<p><strong><?php esc_html_e( 'Expires:', 'jezpress-woo-brand-categories' ); ?></strong> <?php esc_html_e( 'Never (Lifetime)', 'jezpress-woo-brand-categories' ); ?></p>
						<?php endif; ?>
					</div>

					<form method="post" style="margin-top:20px;">
						<?php wp_nonce_field( 'jezweb_license_deactivate', 'jezweb_license_nonce' ); ?>
						<input type="hidden" name="jezweb_license_action" value="deactivate">
						<input type="hidden" name="jezweb_license_slug" value="<?php echo esc_attr( $this->slug ); ?>">
						<button type="submit" class="button button-secondary">
							<?php esc_html_e( 'Deactivate License', 'jezpress-woo-brand-categories' ); ?>
						</button>
					</form>

				<?php else : ?>
					<p><?php esc_html_e( 'Enter your license key to activate all plugin features.', 'jezpress-woo-brand-categories' ); ?></p>

					<form method="post">
						<?php wp_nonce_field( 'jezweb_license_activate', 'jezweb_license_nonce' ); ?>
						<input type="hidden" name="jezweb_license_action" value="activate">
						<input type="hidden" name="jezweb_license_slug" value="<?php echo esc_attr( $this->slug ); ?>">

						<p>
							<input type="text"
								   name="jezweb_license_key"
								   class="regular-text"
								   placeholder="XXXX-XXXX-XXXX-XXXX"
								   value=""
								   autocomplete="off">
						</p>
						<p>
							<button type="submit" class="button button-primary">
								<?php esc_html_e( 'Activate License', 'jezpress-woo-brand-categories' ); ?>
							</button>
						</p>
					</form>

					<p class="description">
						<?php esc_html_e( 'Your license key was provided when you purchased the plugin.', 'jezpress-woo-brand-categories' ); ?>
						<a href="mailto:jez@jezweb.net">jez@jezweb.net</a>
					</p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Mask license key for display.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key License key.
	 * @return string Masked key.
	 */
	private function mask_license_key( string $key ): string {
		if ( strlen( $key ) <= 8 ) {
			return str_repeat( '*', strlen( $key ) );
		}
		return substr( $key, 0, 4 ) . str_repeat( '*', strlen( $key ) - 8 ) . substr( $key, -4 );
	}

	/**
	 * Scheduled daily license check.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function scheduled_license_check(): void {
		$this->validate_license_remote();
	}

	/**
	 * Clean up cron on plugin deactivation.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function cleanup(): void {
		wp_clear_scheduled_hook( 'jpwbc_license_check' );
	}

	/**
	 * Get license status string.
	 *
	 * @since 1.0.0
	 *
	 * @return string Status (active, inactive, expired).
	 */
	public function get_status(): string {
		if ( ! $this->is_valid() ) {
			$data = $this->get_license_data();
			if ( isset( $data['expires'] ) && 'lifetime' !== $data['expires'] ) {
				$expires = strtotime( $data['expires'] );
				if ( $expires && $expires < time() ) {
					return 'expired';
				}
			}
			return 'inactive';
		}
		return 'active';
	}
}
