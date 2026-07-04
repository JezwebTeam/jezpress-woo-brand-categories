<?php
/**
 * Elementor widget: Trending Brands.
 *
 * Output is delegated to JPWBC_Brands so the widget and the
 * [jpwbc_trending_brands] shortcode produce identical markup.
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
 * Trending Brands Elementor widget.
 *
 * @since 1.2.0
 */
class JPWBC_Elementor_Trending extends \Elementor\Widget_Base {

	/**
	 * Widget machine name.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'jpwbc_trending_brands';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Trending Brands', 'jezpress-woo-brand-categories' );
	}

	/**
	 * Widget icon.
	 *
	 * @since 1.2.0
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-tags';
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
		return array( 'brand', 'trending', 'popular', 'woocommerce', 'jezpress' );
	}

	/**
	 * Register controls.
	 *
	 * @since 1.2.0
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'jpwbc_section',
			array( 'label' => __( 'Trending Brands', 'jezpress-woo-brand-categories' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'   => __( 'Heading', 'jezpress-woo-brand-categories' ),
				'type'    => \Elementor\Controls_Manager::TEXT,
				'default' => __( 'Trending Brands', 'jezpress-woo-brand-categories' ),
			)
		);

		$this->add_control(
			'count',
			array(
				'label'   => __( 'How many to show', 'jezpress-woo-brand-categories' ),
				'type'    => \Elementor\Controls_Manager::NUMBER,
				'min'     => 1,
				'max'     => 50,
				'default' => 8,
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

		$this->end_controls_section();

		$this->start_controls_section(
			'jpwbc_style',
			array(
				'label' => __( 'Style', 'jezpress-woo-brand-categories' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'heading_typography',
				'label'    => __( 'Heading typography', 'jezpress-woo-brand-categories' ),
				'selector' => '{{WRAPPER}} .jpwbc-trending__title',
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Heading colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-trending__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'list_typography',
				'label'    => __( 'List typography', 'jezpress-woo-brand-categories' ),
				'selector' => '{{WRAPPER}} .jpwbc-trending__list',
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => __( 'Brand link colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-trending__item a' => 'color: {{VALUE}};',
				),
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

		echo JPWBC_Brands::instance()->render_trending( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_trending() returns escaped template output.
			array(
				'title'      => isset( $settings['title'] ) ? (string) $settings['title'] : '',
				'count'      => isset( $settings['count'] ) ? (int) $settings['count'] : 8,
				'show_arrow' => isset( $settings['show_arrow'] ) && 'yes' === $settings['show_arrow'],
			)
		);
	}
}
