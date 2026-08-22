<?php
/**
 * Admin menu.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

use CSCS\Admin\Screen\Overview;
use CSCS\Admin\Screen\SettingsPage;
use CSCS\Data\PostType;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the plugin's own menu.
 *
 * Everything the plugin owns lives under one menu, including the course post
 * type, so that a site manager has one place to go rather than hunting between
 * a custom menu and a post type that appeared somewhere else in the sidebar.
 */
final class Menu {

	/**
	 * Top-level menu slug.
	 */
	public const SLUG = 'cscs';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks the menu.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_pages' ) );
	}

	/**
	 * Registers the menu and its pages.
	 *
	 * @return void
	 */
	public function add_pages(): void {
		$overview = new Overview( $this->plugin );
		$settings = new SettingsPage( $this->plugin );

		add_menu_page(
			__( 'iSport', 'course-schedule-connector' ),
			__( 'iSport', 'course-schedule-connector' ),
			Capabilities::MANAGE_CONTENT,
			self::SLUG,
			array( $overview, 'render' ),
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			self::SLUG,
			__( 'Overview', 'course-schedule-connector' ),
			__( 'Overview', 'course-schedule-connector' ),
			Capabilities::MANAGE_CONTENT,
			self::SLUG,
			array( $overview, 'render' )
		);

		add_submenu_page(
			self::SLUG,
			__( 'Courses', 'course-schedule-connector' ),
			__( 'Courses', 'course-schedule-connector' ),
			Capabilities::MANAGE_CONTENT,
			'edit.php?post_type=' . PostType::COURSE
		);

		// Settings decide where the plugin sends requests, so they are an
		// administrator's business rather than a site manager's.
		add_submenu_page(
			self::SLUG,
			__( 'Settings', 'course-schedule-connector' ),
			__( 'Settings', 'course-schedule-connector' ),
			Capabilities::MANAGE_DESIGN,
			self::SLUG . '-settings',
			array( $settings, 'render' )
		);
	}
}
