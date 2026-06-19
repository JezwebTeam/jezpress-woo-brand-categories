<?php
/**
 * Hook registration and loader class
 *
 * Manages all WordPress hooks (actions and filters) for the plugin.
 * This class provides a centralized way to register all hooks and ensures
 * they are properly organized and executed.
 *
 * @package JezPress\WooBrandCategories
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loader class to register all hooks with WordPress
 *
 * @since 1.0.0
 */
class JPWBC_Loader {
	/**
	 * Array of actions to register with WordPress
	 *
	 * @since 1.0.0
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $actions = array();

	/**
	 * Array of filters to register with WordPress
	 *
	 * @since 1.0.0
	 * @var array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	protected array $filters = array();

	/**
	 * Add a new action to be registered with WordPress
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook          The name of the WordPress action that is being registered.
	 * @param object $component     A reference to the instance of the object on which the action is defined.
	 * @param string $callback      The name of the method to be called.
	 * @param int    $priority      Optional. The priority at which the function should be fired. Default 10.
	 * @param int    $accepted_args Optional. The number of arguments that should be passed to the callback. Default 1.
	 */
	public function add_action( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->actions[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Add a new filter to be registered with WordPress
	 *
	 * @since 1.0.0
	 *
	 * @param string $hook          The name of the WordPress filter that is being registered.
	 * @param object $component     A reference to the instance of the object on which the filter is defined.
	 * @param string $callback      The name of the method to be called.
	 * @param int    $priority      Optional. The priority at which the function should be fired. Default 10.
	 * @param int    $accepted_args Optional. The number of arguments that should be passed to the callback. Default 1.
	 */
	public function add_filter( string $hook, object $component, string $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$this->filters[] = array(
			'hook'          => $hook,
			'component'     => $component,
			'callback'      => $callback,
			'priority'      => $priority,
			'accepted_args' => $accepted_args,
		);
	}

	/**
	 * Register all hooks with WordPress
	 *
	 * Iterates through all stored actions and filters and registers them
	 * with WordPress using add_action() and add_filter().
	 *
	 * @since 1.0.0
	 */
	public function run(): void {
		// Register all actions
		foreach ( $this->actions as $hook ) {
			add_action(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}

		// Register all filters
		foreach ( $this->filters as $hook ) {
			add_filter(
				$hook['hook'],
				array( $hook['component'], $hook['callback'] ),
				$hook['priority'],
				$hook['accepted_args']
			);
		}
	}

	/**
	 * Get all registered actions
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	public function get_actions(): array {
		return $this->actions;
	}

	/**
	 * Get all registered filters
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array{hook: string, component: object, callback: string, priority: int, accepted_args: int}>
	 */
	public function get_filters(): array {
		return $this->filters;
	}

	/**
	 * Clear all registered actions
	 *
	 * Useful for testing or resetting the loader state.
	 *
	 * @since 1.0.0
	 */
	public function clear_actions(): void {
		$this->actions = array();
	}

	/**
	 * Clear all registered filters
	 *
	 * Useful for testing or resetting the loader state.
	 *
	 * @since 1.0.0
	 */
	public function clear_filters(): void {
		$this->filters = array();
	}

	/**
	 * Clear all registered hooks (actions and filters)
	 *
	 * @since 1.0.0
	 */
	public function clear_all(): void {
		$this->clear_actions();
		$this->clear_filters();
	}
}
