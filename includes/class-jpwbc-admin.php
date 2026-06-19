<?php
/**
 * Admin settings class
 *
 * Handles all admin functionality including menu registration,
 * settings registration, and admin page rendering.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for plugin settings
 *
 * @since 1.0.0
 */
class JPWBC_Admin {

	/**
	 * Menu page slug
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $menu_slug = 'jpwbc-settings';

	/**
	 * Option group for settings
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $option_group = 'jpwbc_settings';

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Constructor logic if needed
	}

	/**
	 * Register admin menu
	 *
	 * If JezPress Manager is active, register as a submenu under it.
	 * Otherwise, register as a top-level menu.
	 *
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		// Check if JezPress Manager is active
		if ( defined( 'JEZPRESS_MANAGER_ACTIVE' ) && JEZPRESS_MANAGER_ACTIVE ) {
			// Register as submenu under JezPress Manager
			add_submenu_page(
				'jezpress-manager',
				__( 'JezPress Woo Brand Categories Settings', 'jezpress-woo-brand-categories' ),
				__( 'JezPress Woo Brand Categories', 'jezpress-woo-brand-categories' ),
				'manage_options',
				$this->menu_slug,
				array( $this, 'render_settings_page' )
			);
		} else {
			// Register as standalone menu
			add_menu_page(
				__( 'JezPress Woo Brand Categories Settings', 'jezpress-woo-brand-categories' ),
				__( 'JezPress Woo Brand Categories', 'jezpress-woo-brand-categories' ),
				'manage_options',
				$this->menu_slug,
				array( $this, 'render_settings_page' ),
				'dashicons-admin-generic',
				58
			);
		}
	}

	/**
	 * Register settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		// Register settings group
		register_setting(
			$this->option_group,
			'jpwbc_example_setting',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			)
		);

		// Add settings sections
		add_settings_section(
			'jpwbc_general_section',
			__( 'General Settings', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_general_section' ),
			$this->menu_slug
		);

		// Add settings fields
		add_settings_field(
			'jpwbc_example_setting',
			__( 'Example Setting', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_example_field' ),
			$this->menu_slug,
			'jpwbc_general_section'
		);
	}

	/**
	 * Enqueue admin assets
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Only load on our settings page
		if ( false === strpos( $hook_suffix, $this->menu_slug ) ) {
			return;
		}

		// Enqueue admin styles
		wp_enqueue_style(
			'jpwbc-admin',
			JPWBC_PLUGIN_URL . 'assets/css/jpwbc-admin.css',
			array(),
			JPWBC_VERSION
		);

		// Enqueue admin scripts
		wp_enqueue_script(
			'jpwbc-admin',
			JPWBC_PLUGIN_URL . 'assets/js/jpwbc-admin.js',
			array( 'jquery' ),
			JPWBC_VERSION,
			true
		);

		// Localize script
		wp_localize_script(
			'jpwbc-admin',
			'jpwbcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'jpwbc_admin_nonce' ),
				'i18n'    => array(
					'saving' => __( 'Saving...', 'jezpress-woo-brand-categories' ),
					'saved'  => __( 'Saved!', 'jezpress-woo-brand-categories' ),
					'error'  => __( 'Error saving settings.', 'jezpress-woo-brand-categories' ),
				),
			)
		);
	}

	/**
	 * Render the settings page
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Get current tab
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';

		// Define tabs
		$tabs = array(
			'general' => __( 'General', 'jezpress-woo-brand-categories' ),
			'license' => __( 'License', 'jezpress-woo-brand-categories' ),
		);

		/**
		 * Filter the settings page tabs
		 *
		 * @since 1.0.0
		 *
		 * @param array $tabs Array of tab slugs and labels.
		 */
		$tabs = apply_filters( 'jpwbc_settings_tabs', $tabs );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '&tab=' . $tab_slug ) ); ?>"
					   class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="tab-content" style="margin-top: 20px;">
				<?php
				switch ( $current_tab ) {
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_general_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the general settings tab
	 *
	 * @since 1.0.0
	 */
	private function render_general_tab(): void {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( $this->option_group );
			do_settings_sections( $this->menu_slug );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render the license tab
	 *
	 * @since 1.0.0
	 */
	private function render_license_tab(): void {
		$license = JPWBC_License::get_instance();
		if ( $license ) {
			$license->render_tab_content();
		}
	}

	/**
	 * Render general section description
	 *
	 * @since 1.0.0
	 */
	public function render_general_section(): void {
		echo '<p>' . esc_html__( 'Configure the general settings for the plugin.', 'jezpress-woo-brand-categories' ) . '</p>';
	}

	/**
	 * Render example field
	 *
	 * @since 1.0.0
	 */
	public function render_example_field(): void {
		$value = get_option( 'jpwbc_example_setting', '' );
		?>
		<input type="text"
			   id="jpwbc_example_setting"
			   name="jpwbc_example_setting"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text">
		<p class="description">
			<?php esc_html_e( 'Enter an example value.', 'jezpress-woo-brand-categories' ); ?>
		</p>
		<?php
	}
}
