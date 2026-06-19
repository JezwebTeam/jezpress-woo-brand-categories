<?php
/**
 * Admin settings class
 *
 * Handles the admin menu, the single `jpwbc_settings` option (registration,
 * rendering, sanitisation) and the tabbed settings UI.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class for plugin settings.
 *
 * @since 1.0.0
 */
class JPWBC_Admin {

	/**
	 * Single option name holding all plugin settings.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public const OPTION_KEY = 'jpwbc_settings';

	/**
	 * Settings API option group.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private const OPTION_GROUP = 'jpwbc_settings_group';

	/**
	 * Allowed placement values for the dropdown output.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	public const PLACEMENTS = array( 'shortcode', 'auto_hook', 'elementor' );

	/**
	 * Keys belonging to each settings tab (drives the merge-on-save logic).
	 *
	 * @since 1.0.0
	 * @var array<string, array<int, string>>
	 */
	private const TAB_KEYS = array(
		'general' => array( 'enabled', 'placement', 'expand_active', 'other_brands_clickable', 'show_counts', 'brand_search' ),
		'seo'     => array( 'clean_urls', 'index_min_products', 'title_template', 'intro_template' ),
	);

	/**
	 * Default settings. Single source of truth for option shape.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private const DEFAULT_SETTINGS = array(
		// General.
		'enabled'                => true,
		'placement'              => 'shortcode',
		'expand_active'          => true,
		'other_brands_clickable' => false,
		'show_counts'            => true,
		'brand_search'           => true,
		// Indexing & SEO.
		'clean_urls'             => true,
		'index_min_products'     => 4,
		'title_template'         => '{brand} – {category}',
		'intro_template'         => 'Browse our range of {category} from {brand}. {count} products available.',
	);

	/**
	 * Menu page slug.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $menu_slug = 'jpwbc-settings';

	/**
	 * Get merged, defaulted plugin settings.
	 *
	 * Always returns every key with a sane type, so callers never have to
	 * defend against partial options.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		$saved = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$merged = array_merge( self::DEFAULT_SETTINGS, $saved );

		// Coerce types defensively.
		$merged['enabled']                = (bool) $merged['enabled'];
		$merged['expand_active']          = (bool) $merged['expand_active'];
		$merged['other_brands_clickable'] = (bool) $merged['other_brands_clickable'];
		$merged['show_counts']            = (bool) $merged['show_counts'];
		$merged['brand_search']           = (bool) $merged['brand_search'];
		$merged['clean_urls']             = (bool) $merged['clean_urls'];
		$merged['index_min_products']     = max( 1, (int) $merged['index_min_products'] );
		$merged['placement']              = in_array( $merged['placement'], self::PLACEMENTS, true ) ? $merged['placement'] : 'shortcode';
		$merged['title_template']         = (string) $merged['title_template'];
		$merged['intro_template']         = (string) $merged['intro_template'];

		return $merged;
	}

	/**
	 * Get the default settings array.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed>
	 */
	public static function get_defaults(): array {
		return self::DEFAULT_SETTINGS;
	}

	/**
	 * Register admin menu.
	 *
	 * Submenu under JezPress Manager when present, otherwise a top-level menu.
	 *
	 * @since 1.0.0
	 */
	public function register_menu(): void {
		if ( defined( 'JEZPRESS_MANAGER_ACTIVE' ) && JEZPRESS_MANAGER_ACTIVE ) {
			add_submenu_page(
				'jezpress-manager',
				__( 'Brand Categories Settings', 'jezpress-woo-brand-categories' ),
				__( 'Brand Categories', 'jezpress-woo-brand-categories' ),
				'manage_options',
				$this->menu_slug,
				array( $this, 'render_settings_page' )
			);
		} else {
			add_menu_page(
				__( 'Brand Categories Settings', 'jezpress-woo-brand-categories' ),
				__( 'Brand Categories', 'jezpress-woo-brand-categories' ),
				'manage_options',
				$this->menu_slug,
				array( $this, 'render_settings_page' ),
				'dashicons-category',
				58
			);
		}
	}

	/**
	 * Register the single settings option and its sections/fields.
	 *
	 * @since 1.0.0
	 */
	public function register_settings(): void {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::DEFAULT_SETTINGS,
			)
		);

		// --- General tab ---------------------------------------------------
		add_settings_section(
			'jpwbc_section_general',
			__( 'Dropdown', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_general_section_intro' ),
			'jpwbc-settings-general'
		);

		$this->add_checkbox_field( 'enabled', __( 'Enable plugin', 'jezpress-woo-brand-categories' ), 'jpwbc-settings-general', 'jpwbc_section_general', __( 'Master switch for the in-brand category dropdown and clean URLs.', 'jezpress-woo-brand-categories' ) );

		add_settings_field(
			'placement',
			__( 'Placement', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_placement_field' ),
			'jpwbc-settings-general',
			'jpwbc_section_general'
		);

		$this->add_checkbox_field( 'expand_active', __( 'Expand current brand by default', 'jezpress-woo-brand-categories' ), 'jpwbc-settings-general', 'jpwbc_section_general', __( 'On a brand archive, open the active brand and show its categories.', 'jezpress-woo-brand-categories' ) );
		$this->add_checkbox_field( 'other_brands_clickable', __( 'Other brands clickable', 'jezpress-woo-brand-categories' ), 'jpwbc-settings-general', 'jpwbc_section_general', __( 'Allow expanding any brand in the list to reveal its categories inline.', 'jezpress-woo-brand-categories' ) );
		$this->add_checkbox_field( 'show_counts', __( 'Show product counts', 'jezpress-woo-brand-categories' ), 'jpwbc-settings-general', 'jpwbc_section_general', __( 'Display the product count next to each category.', 'jezpress-woo-brand-categories' ) );
		$this->add_checkbox_field( 'brand_search', __( 'Show brand search box', 'jezpress-woo-brand-categories' ), 'jpwbc-settings-general', 'jpwbc_section_general', __( 'Add a small filter box above the brand list.', 'jezpress-woo-brand-categories' ) );

		// --- Indexing & SEO tab -------------------------------------------
		add_settings_section(
			'jpwbc_section_seo',
			__( 'Clean URLs & indexing', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_seo_section_intro' ),
			'jpwbc-settings-seo'
		);

		$this->add_checkbox_field( 'clean_urls', __( 'Enable clean combo URLs', 'jezpress-woo-brand-categories' ), 'jpwbc-settings-seo', 'jpwbc_section_seo', __( 'Serve <code>/brands/{brand}/{category}/</code> URLs. Flush permalinks after changing.', 'jezpress-woo-brand-categories' ) );

		add_settings_field(
			'index_min_products',
			__( 'Index threshold', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_index_min_field' ),
			'jpwbc-settings-seo',
			'jpwbc_section_seo'
		);

		add_settings_field(
			'title_template',
			__( 'SEO title template', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_title_template_field' ),
			'jpwbc-settings-seo',
			'jpwbc_section_seo'
		);

		add_settings_field(
			'intro_template',
			__( 'Intro text template', 'jezpress-woo-brand-categories' ),
			array( $this, 'render_intro_template_field' ),
			'jpwbc-settings-seo',
			'jpwbc_section_seo'
		);
	}

	/**
	 * Sanitise the settings option.
	 *
	 * Only the keys belonging to the submitted tab (`_active_tab`) are taken
	 * from the input; every other key is preserved from the stored option so a
	 * single-tab save never wipes the other tab's values. Unchecked checkboxes
	 * (absent from POST) are correctly stored as false for the active tab only.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $input Raw submitted value.
	 * @return array<string, mixed> Sanitised, complete settings array.
	 */
	public function sanitize_settings( mixed $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$output = self::get_settings(); // Start from current, fully-defaulted values.

		$active_tab = isset( $input['_active_tab'] ) ? sanitize_key( (string) $input['_active_tab'] ) : '';
		$keys       = self::TAB_KEYS[ $active_tab ] ?? array();

		if ( empty( $keys ) ) {
			// Unknown tab — change nothing.
			return $this->strip_internal_keys( $output );
		}

		foreach ( $keys as $key ) {
			switch ( $key ) {
				case 'enabled':
				case 'expand_active':
				case 'other_brands_clickable':
				case 'show_counts':
				case 'brand_search':
				case 'clean_urls':
					$output[ $key ] = ! empty( $input[ $key ] );
					break;

				case 'placement':
					$value          = isset( $input[ $key ] ) ? sanitize_key( (string) $input[ $key ] ) : 'shortcode';
					$output[ $key ] = in_array( $value, self::PLACEMENTS, true ) ? $value : 'shortcode';
					break;

				case 'index_min_products':
					$output[ $key ] = max( 1, min( 999, absint( $input[ $key ] ?? 4 ) ) );
					break;

				case 'title_template':
					$output[ $key ] = sanitize_text_field( (string) ( $input[ $key ] ?? '' ) );
					break;

				case 'intro_template':
					$output[ $key ] = sanitize_textarea_field( (string) ( $input[ $key ] ?? '' ) );
					break;
			}
		}

		$output = $this->strip_internal_keys( $output );

		/**
		 * Fires after settings are sanitised, before they are stored.
		 *
		 * Feature classes hook this to flush rewrite rules / bust caches.
		 * The payload is the final settings array (without internal keys), but
		 * the option is not written yet — consumers should use this argument,
		 * not JPWBC_Admin::get_settings(), which still returns the old value.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, mixed> $output Sanitised settings to be stored.
		 */
		do_action( 'jpwbc_settings_saved', $output );

		return $output;
	}

	/**
	 * Remove transient internal keys before persisting.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $settings Settings array.
	 * @return array<string, mixed>
	 */
	private function strip_internal_keys( array $settings ): array {
		unset( $settings['_active_tab'] );
		return $settings;
	}

	/**
	 * Enqueue admin assets on our settings page only.
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		if ( false === strpos( $hook_suffix, $this->menu_slug ) ) {
			return;
		}

		wp_enqueue_style(
			'jpwbc-admin',
			JPWBC_PLUGIN_URL . 'assets/css/jpwbc-admin.css',
			array(),
			JPWBC_VERSION
		);

		wp_enqueue_script(
			'jpwbc-admin',
			JPWBC_PLUGIN_URL . 'assets/js/jpwbc-admin.js',
			array( 'jquery' ),
			JPWBC_VERSION,
			true
		);

		wp_localize_script(
			'jpwbc-admin',
			'jpwbcAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'jpwbc_admin_nonce' ),
			)
		);
	}

	/**
	 * Render the settings page shell with tabs.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		$tabs = array(
			'general' => __( 'Settings', 'jezpress-woo-brand-categories' ),
			'seo'     => __( 'Indexing & SEO', 'jezpress-woo-brand-categories' ),
			'preview' => __( 'Combo Preview', 'jezpress-woo-brand-categories' ),
			'cache'   => __( 'Cache', 'jezpress-woo-brand-categories' ),
			'license' => __( 'License', 'jezpress-woo-brand-categories' ),
		);

		/**
		 * Filter the settings page tabs.
		 *
		 * @since 1.0.0
		 *
		 * @param array<string, string> $tabs Tab slug => label.
		 */
		$tabs = apply_filters( 'jpwbc_settings_tabs', $tabs );

		if ( ! isset( $tabs[ $current_tab ] ) ) {
			$current_tab = 'general';
		}
		?>
		<div class="wrap jpwbc-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only flag after our nonce-protected save redirect.
			if ( isset( $_GET['jpwbc_saved'] ) ) :
				?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'jezpress-woo-brand-categories' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_slug => $tab_label ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . $this->menu_slug . '&tab=' . $tab_slug ) ); ?>"
					   class="nav-tab <?php echo $current_tab === $tab_slug ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="tab-content" style="margin-top:20px;">
				<?php
				switch ( $current_tab ) {
					case 'seo':
						$this->render_settings_form( 'seo', 'jpwbc-settings-seo' );
						break;
					case 'preview':
						$this->render_tab_via_action( 'jpwbc_render_preview_tab' );
						break;
					case 'cache':
						$this->render_tab_via_action( 'jpwbc_render_cache_tab' );
						break;
					case 'license':
						$this->render_license_tab();
						break;
					default:
						$this->render_settings_form( 'general', 'jpwbc-settings-general' );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a Settings-API form for one tab.
	 *
	 * The hidden `_active_tab` field tells the sanitiser which keys this submit
	 * is allowed to change.
	 *
	 * @since 1.0.0
	 *
	 * @param string $tab       Tab key (matches TAB_KEYS).
	 * @param string $page_slug Settings page slug holding that tab's sections.
	 */
	private function render_settings_form( string $tab, string $page_slug ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php
			wp_nonce_field( 'jpwbc_save_settings', 'jpwbc_settings_nonce' );
			echo '<input type="hidden" name="action" value="jpwbc_save_settings">';
			printf(
				'<input type="hidden" name="%s[_active_tab]" value="%s">',
				esc_attr( self::OPTION_KEY ),
				esc_attr( $tab )
			);
			do_settings_sections( $page_slug );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Handle the settings form submission.
	 *
	 * Posts to admin-post.php (not options.php) so the save never depends on the
	 * WordPress `allowed_options` whitelist — which role/security plugins on
	 * client sites can filter, silently dropping the save. The registered
	 * `sanitize_settings()` callback still runs (via the `sanitize_option_*`
	 * filter on update_option), so only allowlisted, sanitised keys are stored.
	 *
	 * @since 1.0.2
	 */
	public function handle_save_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'jezpress-woo-brand-categories' ), 403 );
		}

		check_admin_referer( 'jpwbc_save_settings', 'jpwbc_settings_nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_settings() (the registered sanitize_option_jpwbc_settings filter) rebuilds the option from an allowlist on update_option below.
		$raw = isset( $_POST[ self::OPTION_KEY ] ) && is_array( $_POST[ self::OPTION_KEY ] )
			? wp_unslash( $_POST[ self::OPTION_KEY ] )
			: array();

		$tab = isset( $raw['_active_tab'] ) ? sanitize_key( (string) $raw['_active_tab'] ) : 'general';

		// Only persist for a known tab; otherwise it's a no-op (avoids a
		// misleading "Settings saved" on a crafted/unknown tab).
		if ( isset( self::TAB_KEYS[ $tab ] ) ) {
			update_option( self::OPTION_KEY, $raw );
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => $this->menu_slug,
					'tab'         => $tab,
					'jpwbc_saved' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render a tab whose content is provided by a feature class via action.
	 *
	 * Phase 3 wires the Combo Preview and Cache tabs onto these actions; until
	 * then a neutral placeholder is shown.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action Action hook name.
	 */
	private function render_tab_via_action( string $action ): void {
		if ( has_action( $action ) ) {
			/**
			 * Render a feature-provided settings tab.
			 *
			 * @since 1.0.0
			 */
			do_action( $action );
			return;
		}
		echo '<p>' . esc_html__( 'This section becomes available once the plugin is fully active (WooCommerce with the product_brand taxonomy).', 'jezpress-woo-brand-categories' ) . '</p>';
	}

	/**
	 * Render the license tab (delegated to the license handler).
	 *
	 * @since 1.0.0
	 */
	private function render_license_tab(): void {
		$license = JPWBC_License::get_instance();
		if ( $license ) {
			$license->render_tab_content();
		}
	}

	/* ---------------------------------------------------------------------
	 * Section intros + field renderers
	 * ------------------------------------------------------------------- */

	/**
	 * General section intro.
	 *
	 * @since 1.0.0
	 */
	public function render_general_section_intro(): void {
		echo '<p>' . esc_html__( 'Control how the in-brand category dropdown is shown on brand archives.', 'jezpress-woo-brand-categories' ) . '</p>';
	}

	/**
	 * SEO section intro.
	 *
	 * @since 1.0.0
	 */
	public function render_seo_section_intro(): void {
		echo '<p>' . esc_html__( 'Tune the clean combo URLs and which brand+category pages are allowed to be indexed.', 'jezpress-woo-brand-categories' ) . '</p>';
	}

	/**
	 * Register a boolean checkbox field bound to a settings key.
	 *
	 * @since 1.0.0
	 *
	 * @param string $key         Settings key.
	 * @param string $label       Field label.
	 * @param string $page_slug   Settings page slug.
	 * @param string $section     Section id.
	 * @param string $description Help text (may contain a limited <code> tag).
	 */
	private function add_checkbox_field( string $key, string $label, string $page_slug, string $section, string $description = '' ): void {
		add_settings_field(
			$key,
			$label,
			function () use ( $key, $description ): void {
				$settings = self::get_settings();
				$checked  = ! empty( $settings[ $key ] );
				printf(
					'<input type="checkbox" name="%1$s[%2$s]" value="1" %3$s>',
					esc_attr( self::OPTION_KEY ),
					esc_attr( $key ),
					checked( $checked, true, false )
				);
				if ( '' !== $description ) {
					echo '<span class="description" style="display:block;margin-top:4px;">'
						. wp_kses( $description, array( 'code' => array() ) )
						. '</span>';
				}
			},
			$page_slug,
			$section
		);
	}

	/**
	 * Render the placement select.
	 *
	 * @since 1.0.0
	 */
	public function render_placement_field(): void {
		$settings = self::get_settings();
		$current  = $settings['placement'];
		$labels   = array(
			'shortcode' => __( 'Shortcode — [jpwbc_brand_categories]', 'jezpress-woo-brand-categories' ),
			'auto_hook' => __( 'Automatic — before the brand archive product loop', 'jezpress-woo-brand-categories' ),
			'elementor' => __( 'Elementor widget only', 'jezpress-woo-brand-categories' ),
		);
		echo '<select name="' . esc_attr( self::OPTION_KEY ) . '[placement]">';
		foreach ( self::PLACEMENTS as $value ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $labels[ $value ] ?? $value )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'How the dropdown is output. The Elementor widget and shortcode are always available regardless of this setting.', 'jezpress-woo-brand-categories' ) . '</p>';
	}

	/**
	 * Render the index-threshold number field.
	 *
	 * @since 1.0.0
	 */
	public function render_index_min_field(): void {
		$settings = self::get_settings();
		printf(
			'<input type="number" min="1" max="999" step="1" name="%1$s[index_min_products]" value="%2$d" class="small-text">',
			esc_attr( self::OPTION_KEY ),
			(int) $settings['index_min_products']
		);
		echo '<p class="description">' . esc_html__( 'A brand+category page is allowed to be indexed only if it has at least this many products. Thinner pages render but get noindex,follow.', 'jezpress-woo-brand-categories' ) . '</p>';
	}

	/**
	 * Render the SEO title-template text field.
	 *
	 * @since 1.0.0
	 */
	public function render_title_template_field(): void {
		$settings = self::get_settings();
		printf(
			'<input type="text" name="%1$s[title_template]" value="%2$s" class="large-text">',
			esc_attr( self::OPTION_KEY ),
			esc_attr( (string) $settings['title_template'] )
		);
		echo '<p class="description">' . esc_html__( 'Tokens: {brand}, {category}, {count}.', 'jezpress-woo-brand-categories' ) . '</p>';
	}

	/**
	 * Render the intro-template textarea.
	 *
	 * @since 1.0.0
	 */
	public function render_intro_template_field(): void {
		$settings = self::get_settings();
		printf(
			'<textarea name="%1$s[intro_template]" rows="3" class="large-text">%2$s</textarea>',
			esc_attr( self::OPTION_KEY ),
			esc_textarea( (string) $settings['intro_template'] )
		);
		echo '<p class="description">' . esc_html__( 'Short intro shown on indexable combo pages. Tokens: {brand}, {category}, {count}. Leave blank to omit.', 'jezpress-woo-brand-categories' ) . '</p>';
	}
}
