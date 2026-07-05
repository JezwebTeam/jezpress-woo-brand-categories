<?php
/**
 * Elementor widget: Brand Filter Bar (Category + Price + Sort).
 *
 * Output is delegated to JPWBC_Filter::render_filter_bar() so the widget and the
 * [jpwbc_brand_filter] shortcode produce identical markup, and the filtering is
 * applied to the brand archive's main query by JPWBC_Filter.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.13.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Brand Filter Bar Elementor widget.
 *
 * @since 1.13.0
 */
class JPWBC_Elementor_Brand_Filter extends \Elementor\Widget_Base {

	/**
	 * Widget machine name.
	 *
	 * @since 1.13.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'jpwbc_brand_filter';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.13.0
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Brand Filter Bar', 'jezpress-woo-brand-categories' );
	}

	/**
	 * Widget icon.
	 *
	 * @since 1.13.0
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-filter';
	}

	/**
	 * Widget categories.
	 *
	 * @since 1.13.0
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'woocommerce-elements', 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @since 1.13.0
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'brand', 'filter', 'price', 'sort', 'category', 'woocommerce', 'jezpress' );
	}

	/**
	 * Register controls.
	 *
	 * @since 1.13.0
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'jpwbc_section',
			array( 'label' => __( 'Brand Filter Bar', 'jezpress-woo-brand-categories' ) )
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

		$this->add_control(
			'show_category',
			array(
				'label'        => __( 'Show Category filter', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_price',
			array(
				'label'        => __( 'Show Price filter', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'show_sort',
			array(
				'label'        => __( 'Show Sort by', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_responsive_control(
			'align',
			array(
				'label'     => __( 'Alignment', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::CHOOSE,
				'options'   => array(
					'flex-start' => array(
						'title' => __( 'Left', 'jezpress-woo-brand-categories' ),
						'icon'  => 'eicon-text-align-left',
					),
					'center'     => array(
						'title' => __( 'Center', 'jezpress-woo-brand-categories' ),
						'icon'  => 'eicon-text-align-center',
					),
					'flex-end'   => array(
						'title' => __( 'Right', 'jezpress-woo-brand-categories' ),
						'icon'  => 'eicon-text-align-right',
					),
				),
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-filterbar' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->register_style_controls();
	}

	/**
	 * Register the Style-tab controls, scoped to this widget.
	 *
	 * @since 1.13.0
	 */
	protected function register_style_controls(): void {
		$this->start_controls_section(
			'jpwbc_style',
			array(
				'label' => __( 'Filter bar', 'jezpress-woo-brand-categories' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'control_typography',
				'label'    => __( 'Typography', 'jezpress-woo-brand-categories' ),
				'selector' => '{{WRAPPER}} .jpwbc-filter__toggle, {{WRAPPER}} .jpwbc-sort',
			)
		);

		$this->add_responsive_control(
			'control_gap',
			array(
				'label'      => __( 'Gap between controls', 'jezpress-woo-brand-categories' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array( 'px' => array( 'min' => 0, 'max' => 40 ) ),
				'selectors'  => array( '{{WRAPPER}} .jpwbc-filterbar' => 'gap: {{SIZE}}{{UNIT}};' ),
			)
		);

		$this->add_control(
			'control_color',
			array(
				'label'     => __( 'Text colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-filter__toggle' => 'color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-sort'           => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'control_border_color',
			array(
				'label'     => __( 'Border colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-filter__toggle' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-filter--sort'   => 'border-color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'active_color',
			array(
				'label'     => __( 'Active option colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-filter__opt.is-active' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'apply_bg',
			array(
				'label'     => __( 'Apply button background', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-filter__apply' => 'background-color: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Render on the front end.
	 *
	 * @since 1.13.0
	 */
	protected function render(): void {
		$filter = JPWBC_Filter::instance();
		if ( ! $filter instanceof JPWBC_Filter ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$brand    = isset( $settings['jpwbc_brand'] ) ? sanitize_title( (string) $settings['jpwbc_brand'] ) : '';

		echo $filter->render_filter_bar( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_filter_bar() returns escaped template output.
			array(
				'brand'         => $brand,
				'show_category' => isset( $settings['show_category'] ) && 'yes' === $settings['show_category'],
				'show_price'    => isset( $settings['show_price'] ) && 'yes' === $settings['show_price'],
				'show_sort'     => isset( $settings['show_sort'] ) && 'yes' === $settings['show_sort'],
			)
		);
	}
}
