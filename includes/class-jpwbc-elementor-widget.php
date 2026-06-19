<?php
/**
 * Elementor widget
 *
 * Drag-in "JezPress > Brand Categories" widget. Output is delegated to the
 * shared JPWBC_Frontend renderer, so the widget, the shortcode and the
 * auto-hook all produce identical markup.
 *
 * Loaded only when Elementor is present (see the bootstrap registration).
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Brand Categories Elementor widget.
 *
 * @since 1.0.0
 */
class JPWBC_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Widget machine name.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'jpwbc_brand_categories';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Brand Categories', 'jezpress-woo-brand-categories' );
	}

	/**
	 * Widget icon.
	 *
	 * @since 1.0.0
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-product-categories';
	}

	/**
	 * Widget categories.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'woocommerce-elements', 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'brand', 'category', 'categories', 'woocommerce', 'jezpress' );
	}

	/**
	 * Register controls.
	 *
	 * @since 1.0.0
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'jpwbc_section',
			array(
				'label' => __( 'Brand Categories', 'jezpress-woo-brand-categories' ),
			)
		);

		$this->add_control(
			'jpwbc_brand',
			array(
				'label'       => __( 'Brand slug (optional)', 'jezpress-woo-brand-categories' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'description' => __( 'Leave blank to use the current brand archive. Set a slug to force a specific brand.', 'jezpress-woo-brand-categories' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render the widget on the front end.
	 *
	 * @since 1.0.0
	 */
	protected function render(): void {
		$frontend = JPWBC_Frontend::instance();
		if ( ! $frontend instanceof JPWBC_Frontend ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$brand    = isset( $settings['jpwbc_brand'] ) ? sanitize_title( (string) $settings['jpwbc_brand'] ) : '';

		echo $frontend->render( $brand ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render() returns escaped template output.
	}

	/**
	 * Editor placeholder (Elementor preview can't run the full query reliably).
	 *
	 * @since 1.0.0
	 */
	protected function content_template(): void {
		// No live JS template; the widget renders server-side.
	}
}
