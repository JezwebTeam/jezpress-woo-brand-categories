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
				'label'        => __( 'Show A-Z letter grid', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'index_layout',
			array(
				'label'     => __( 'Alphabet layout', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'grid',
				'options'   => array(
					'grid'   => __( 'Column grid', 'jezpress-woo-brand-categories' ),
					'inline' => __( 'Inline row (boxed)', 'jezpress-woo-brand-categories' ),
				),
				'condition' => array( 'show_index' => 'yes' ),
			)
		);

		$this->add_control(
			'columns',
			array(
				'label'     => __( 'Letter grid columns', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => '5',
				'options'   => array(
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				),
				'condition' => array(
					'show_index'   => 'yes',
					'index_layout' => 'grid',
				),
			)
		);

		$this->add_control(
			'show_groups',
			array(
				'label'        => __( 'Show brand list under each letter', 'jezpress-woo-brand-categories' ),
				'description'  => __( 'Off = show only the A-Z letter grid.', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => 'yes',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'list_columns',
			array(
				'label'       => __( 'Brand list columns', 'jezpress-woo-brand-categories' ),
				'description' => __( 'Columns of brand names under each letter. Use 3-4 for a full-width brands page.', 'jezpress-woo-brand-categories' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '1',
				'options'     => array(
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
				),
				'condition'   => array( 'show_groups' => 'yes' ),
			)
		);

		$this->add_control(
			'show_letter_counts',
			array(
				'label'        => __( 'Show brand count per letter', 'jezpress-woo-brand-categories' ),
				'description'  => __( 'e.g. "C (104)".', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
				'condition'    => array( 'show_groups' => 'yes' ),
			)
		);

		$this->add_control(
			'show_search',
			array(
				'label'        => __( 'Show brand search box', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
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
			'sticky_index',
			array(
				'label'        => __( 'Sticky heading + alphabet bar', 'jezpress-woo-brand-categories' ),
				'description'  => __( 'Pin the heading and A-Z bar to the top while the brand list scrolls (like the Myer brands page).', 'jezpress-woo-brand-categories' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'default'      => '',
				'return_value' => 'yes',
			)
		);

		$this->add_control(
			'sticky_offset',
			array(
				'label'       => __( 'Sticky top offset (px)', 'jezpress-woo-brand-categories' ),
				'description' => __( 'Distance from the top of the viewport — increase this to clear a sticky site header.', 'jezpress-woo-brand-categories' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'default'     => 0,
				'min'         => 0,
				'max'         => 400,
				'step'        => 1,
				'condition'   => array( 'sticky_index' => 'yes' ),
			)
		);

		$this->add_control(
			'index_url',
			array(
				'label'       => __( 'Letters link to (Brands page URL)', 'jezpress-woo-brand-categories' ),
				'description' => __( 'Make each A-Z letter link to your Brands page and jump to that letter (e.g. /all-brands/). Ideal for a menu — leave empty to use in-page jumps to the lists below.', 'jezpress-woo-brand-categories' ),
				'type'        => \Elementor\Controls_Manager::URL,
				'placeholder' => '/all-brands/',
				'options'     => array( 'url' ),
				'condition'   => array( 'show_index' => 'yes' ),
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

		$this->register_style_controls();
	}

	/**
	 * Register the Style-tab controls (font sizes + colours), scoped to this widget.
	 *
	 * @since 1.3.0
	 */
	protected function register_style_controls(): void {
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
				'selector' => '{{WRAPPER}} .jpwbc-allbrands__title',
			)
		);

		$this->add_control(
			'heading_color',
			array(
				'label'     => __( 'Heading colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-allbrands__title' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'letter_typography',
				'label'    => __( 'Letter typography (grid + section headings)', 'jezpress-woo-brand-categories' ),
				'selector' => '{{WRAPPER}} .jpwbc-az-index__letter, {{WRAPPER}} .jpwbc-az-group__letter',
			)
		);

		$this->add_control(
			'letter_color',
			array(
				'label'     => __( 'Letter colour (grid + section headings)', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-az-index__letter:not(.is-empty)' => 'color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-az-group__letter'                => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'letter_gap',
			array(
				'label'      => __( 'Gap below letter heading', 'jezpress-woo-brand-categories' ),
				'description' => __( 'Space between the letter heading (and its underline) and the brand list below it.', 'jezpress-woo-brand-categories' ),
				'type'       => \Elementor\Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'em' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 60,
					),
					'em' => array(
						'min'  => 0,
						'max'  => 5,
						'step' => 0.1,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .jpwbc-az-group__letter' => 'margin-bottom: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'list_typography',
				'label'    => __( 'Brand list typography', 'jezpress-woo-brand-categories' ),
				'selector' => '{{WRAPPER}} .jpwbc-az-group__list',
			)
		);

		$this->add_control(
			'link_color',
			array(
				'label'     => __( 'Brand link colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-az-group__item a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'link_hover_color',
			array(
				'label'     => __( 'Brand link hover colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-az-group__item a:hover'   => 'color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-az-group__item a:focus'   => 'color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-az-index__letter:hover'   => 'color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-az-index__letter:focus'   => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			array(
				'name'     => 'view_all_typography',
				'label'    => __( '"View all" typography', 'jezpress-woo-brand-categories' ),
				'selector' => '{{WRAPPER}} .jpwbc-allbrands__viewall > a',
			)
		);

		$this->add_control(
			'view_all_color',
			array(
				'label'     => __( '"View all" link colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-allbrands__viewall > a' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_control(
			'view_all_hover_color',
			array(
				'label'     => __( '"View all" link hover colour', 'jezpress-woo-brand-categories' ),
				'type'      => \Elementor\Controls_Manager::COLOR,
				'selectors' => array(
					'{{WRAPPER}} .jpwbc-allbrands__viewall > a:hover' => 'color: {{VALUE}};',
					'{{WRAPPER}} .jpwbc-allbrands__viewall > a:focus' => 'color: {{VALUE}};',
				),
			)
		);

		$this->add_responsive_control(
			'groups_padding',
			array(
				'label'      => __( 'Brand list area padding', 'jezpress-woo-brand-categories' ),
				'description' => __( 'Padding around the grouped brand list (below the alphabet bar).', 'jezpress-woo-brand-categories' ),
				'type'       => \Elementor\Controls_Manager::DIMENSIONS,
				'size_units' => array( 'px', 'em', '%' ),
				'selectors'  => array(
					'{{WRAPPER}} .jpwbc-az-groups' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
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

		$url = '';
		if ( isset( $settings['view_all_url']['url'] ) ) {
			$url = (string) $settings['view_all_url']['url'];
		}

		$index_url = '';
		if ( isset( $settings['index_url']['url'] ) ) {
			$index_url = (string) $settings['index_url']['url'];
		}

		echo JPWBC_Brands::instance()->render_all_brands( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_all_brands() returns escaped template output.
			array(
				'title'              => isset( $settings['title'] ) ? (string) $settings['title'] : '',
				'show_index'         => isset( $settings['show_index'] ) && 'yes' === $settings['show_index'],
				'index_layout'       => isset( $settings['index_layout'] ) ? (string) $settings['index_layout'] : 'grid',
				'show_groups'        => isset( $settings['show_groups'] ) && 'yes' === $settings['show_groups'],
				'columns'            => isset( $settings['columns'] ) ? (int) $settings['columns'] : 5,
				'list_columns'       => isset( $settings['list_columns'] ) ? (int) $settings['list_columns'] : 1,
				'show_letter_counts' => isset( $settings['show_letter_counts'] ) && 'yes' === $settings['show_letter_counts'],
				'show_search'        => isset( $settings['show_search'] ) && 'yes' === $settings['show_search'],
				'show_arrow'         => isset( $settings['show_arrow'] ) && 'yes' === $settings['show_arrow'],
				'index_url'          => $index_url,
				'sticky_index'       => isset( $settings['sticky_index'] ) && 'yes' === $settings['sticky_index'],
				'sticky_offset'      => isset( $settings['sticky_offset'] ) ? (int) $settings['sticky_offset'] : 0,
				'view_all_url'       => $url,
				'view_all_text'      => isset( $settings['view_all_text'] ) ? (string) $settings['view_all_text'] : '',
			)
		);
	}
}
