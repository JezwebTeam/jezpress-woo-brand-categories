<?php
/**
 * Elementor widget: All Brands (A-Z).
 *
 * Output is delegated to JPWBC_Brands so the widget and the
 * [jpwbc_all_brands] shortcode produce identical markup.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * All Brands (A-Z) Elementor widget.
 *
 * @since 1.2.0
 */
class JPWBC_Elementor_Brands_AZ extends \Elementor\Widget_Base {

	/**
	 * Widget machine name.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'jpwbc_all_brands';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'All Brands (A-Z)', 'jezpress-woo-brand-categories' );
	}

	/**
	 * Widget icon.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-editor-list-ul';
	}

	/**
	 * Widget categories.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'woocommerce-elements', 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @since 1.2.0
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'brand', 'brands', 'a-z', 'index', 'directory', 'woocommerce', 'jezpress' );
	}

	/**
	 * Register controls.
	 *
	 * @since 1.2.0
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'jpwbc_section',
			array( 'label' => __( 'All Brands (A-Z)', 'jezpress-woo-brand-categories' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Heading', 'jezpress-woo-brand-categories' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'All Brands (A-Z)', 'jezpress-woo-brand-categories' ),
			)
		);

		$this->add_control(
			'show_index',
			array(
				'label'        => __( 'Show A-Z jump bar', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_arrow',
			array(
				'label'        => __( 'Show heading arrow', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'view_all_url',
			array(
				'label'       => __( '"View all" link (optional)', 'jezpress-woo-brand-categories' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => 'https://…',
				'options'     => array( 'url' ),
			)
		);

		$this->add_control(
			'view_all_text',
			array(
				'label'   => __( '"View all" label', 'jezpress-woo-brand-categories' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'View all', 'jezpress-woo-brand-categories' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render on the front end.
	 *
	 * @since 1.2.0
	 */
	protected function render(): void {
		$settings = $this->get_settings_for_display();

		$url = '';
		if ( isset( $settings['view_all_url']['url'] ) ) {
			$url = (string) $settings['view_all_url']['url'];
		}

		echo JPWBC_Brands::instance()->render_all_brands( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_all_brands() returns escaped template output.
			array(
				'title'         => isset( $settings['title'] ) ? (string) $settings['title'] : '',
				'show_index'    => isset( $settings['show_index'] ) && 'yes' === $settings['show_index'],
				'show_arrow'    => isset( $settings['show_arrow'] ) && 'yes' === $settings['show_arrow'],
				'view_all_url'  => $url,
				'view_all_text' => isset( $settings['view_all_text'] ) ? (string) $settings['view_all_text'] : '',
			)
		);
	}
}
