<?php
/**
 * Elementor widget: Brand Category Filter (chips).
 *
 * Renders the current brand's product categories as a horizontal row of
 * pills/chips (Myer-style). Output is delegated to JPWBC_Frontend::render_chips()
 * so it shares the dropdown's brand resolution, category query and combo URLs.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.12.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

/**
 * Brand Category Filter (chips) Elementor widget.
 *
 * @since 1.12.0
 */
class JPWBC_Elementor_Brand_Chips extends \Elementor\Widget_Base {

	/**
	 * Widget machine name.
	 *
	 * @since 1.12.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'jpwbc_brand_chips';
	}

	/**
	 * Widget title.
	 *
	 * @since 1.12.0
	 *
	 * @return string
	 */
	public function get_title(): string {
		return __( 'Brand Category Filter (chips)', 'jezpress-woo-brand-categories' );
	}

	/**
	 * Widget icon.
	 *
	 * @since 1.12.0
	 *
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-filter';
	}

	/**
	 * Widget categories.
	 *
	 * @since 1.12.0
	 *
	 * @return array<int, string>
	 */
	public function get_categories(): array {
		return array( 'woocommerce-elements', 'general' );
	}

	/**
	 * Search keywords.
	 *
	 * @since 1.12.0
	 *
	 * @return array<int, string>
	 */
	public function get_keywords(): array {
		return array( 'brand', 'category', 'filter', 'chips', 'pills', 'woocommerce', 'jezpress' );
	}

	/**
	 * Register controls.
	 *
	 * @since 1.12.0
	 */
	protected function register_controls(): void {
		$this->start_controls_section(
			'jpwbc_section',
			array( 'label' => __( 'Brand Category Filter', 'jezpress-woo-brand-categories' ) )
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
			'show_all',
			array(
				'label'        => __( 'Show "All" chip', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'all_text',
			array(
				'label'       => __( '"All" chip label', 'jezpress-woo-brand-categories' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => __( 'All {brand}', 'jezpress-woo-brand-categories' ),
				'description' => __( 'Use {brand} as a placeholder for the brand name, e.g. "All {brand}".', 'jezpress-woo-brand-categories' ),
				'condition'   => array( 'show_all' => 'yes' ),
			)
		);

		$this->add_control(
			'show_counts',
			array(
				'label'        => __( 'Show product count per chip', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
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
					'{{WRAPPER}} .jpwbc-chips' => 'justify-content: {{VALUE}};',
				),
			)
		);

		$this->end_controls_section();

		$this->register_style_controls();
	}

	/**
	 * Register the Style-tab controls, scoped to this widget.
	 *
	 * @since 1.12.0
	 */
	protected function register_style_controls(): void {
		$this->start_controls_section(
			'jpwbc_style',
			array(
				'label' => __( 'Chips', 'jezpress-woo-brand-categories' ),
				'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'chip_typography',
				'label'    => __( 'Typography', 'jezpress-woo-brand-categories' ),
				'selector' => '{{WRAPPER}} .jpwbc-chip',
			)
		);

		$this->add_responsive_control(
			'chip_gap',
			array(
				'label'      => __( 'Gap between chips', 'jezpress-woo-brand-categories' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .jpwbc-chips' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'chip_padding',
			array(
				'label'      => __( 'Chip padding', 'jezpress-woo-brand-categories' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em' ),
				'selectors'  => array(
					'{{WRAPPER}} .jpwbc-chip' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'chip_radius',
			array(
				'label'      => __( 'Border radius', 'jezpress-woo-brand-categories' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', '%' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 50,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .jpwbc-chip' => 'border-radius: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_control(
			'chip_border_width',
			array(
				'label'      => __( 'Border width', 'jezpress-woo-brand-categories' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 6,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .jpwbc-chip' => 'border-style: solid; border-width: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->start_controls_tabs( 'chip_state_tabs' );

		// Normal state.
		$this->start_controls_tab(
			'chip_tab_normal',
			array( 'label' => __( 'Normal', 'jezpress-woo-brand-categories' ) )
		);
		$this->add_control(
			'chip_color',
			array(
				'label'     => __( 'Text colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jpwbc-chip' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'chip_bg',
			array(
				'label'     => __( 'Background', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jpwbc-chip' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'chip_border',
			array(
				'label'     => __( 'Border colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jpwbc-chip' => 'border-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		// Hover state.
		$this->start_controls_tab(
			'chip_tab_hover',
			array( 'label' => __( 'Hover', 'jezpress-woo-brand-categories' ) )
		);
		$this->add_control(
			'chip_color_hover',
			array(
				'label'     => __( 'Text colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-chip:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-chip:focus' => 'color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'chip_bg_hover',
			array(
				'label'     => __( 'Background', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-chip:hover' => 'background-color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-chip:focus' => 'background-color: {{VALUE}};',
				),
			)
		);
		$this->add_control(
			'chip_border_hover',
			array(
				'label'     => __( 'Border colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-chip:hover' => 'border-color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-chip:focus' => 'border-color: {{VALUE}};',
				),
			)
		);
		$this->end_controls_tab();

		// Active (selected) state.
		$this->start_controls_tab(
			'chip_tab_active',
			array( 'label' => __( 'Active', 'jezpress-woo-brand-categories' ) )
		);
		$this->add_control(
			'chip_color_active',
			array(
				'label'     => __( 'Text colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jpwbc-chip.is-active' => 'color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'chip_bg_active',
			array(
				'label'     => __( 'Background', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jpwbc-chip.is-active' => 'background-color: {{VALUE}};' ),
			)
		);
		$this->add_control(
			'chip_border_active',
			array(
				'label'     => __( 'Border colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array( '{{WRAPPER}} .jpwbc-chip.is-active' => 'border-color: {{VALUE}};' ),
			)
		);
		$this->end_controls_tab();

		$this->end_controls_tabs();

		$this->end_controls_section();
	}

	/**
	 * Render on the front end.
	 *
	 * @since 1.12.0
	 */
	protected function render(): void {
		$frontend = JPWBC_Frontend::instance();
		if ( ! $frontend instanceof JPWBC_Frontend ) {
			return;
		}

		$settings = $this->get_settings_for_display();
		$brand    = isset( $settings['jpwbc_brand'] ) ? sanitize_title( (string) $settings['jpwbc_brand'] ) : '';

		echo $frontend->render_chips( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_chips() returns escaped template output.
			$brand,
			array(
				'all_text'    => isset( $settings['all_text'] ) ? (string) $settings['all_text'] : '',
				'show_all'    => isset( $settings['show_all'] ) && 'yes' === $settings['show_all'],
				'show_counts' => isset( $settings['show_counts'] ) && 'yes' === $settings['show_counts'],
			)
		);
	}
}
