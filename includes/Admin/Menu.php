<?php
/**
 * Admin menu.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

use CSCS\Admin\Screen\DisplaySetsPage;
use CSCS\Admin\Screen\MakeupPage;
use CSCS\Admin\Screen\Overview;
use CSCS\Admin\Screen\UnmatchedPage;
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

		$sets = new DisplaySetsPage( $this->plugin );

		add_submenu_page(
			self::SLUG,
			__( 'Display sets', 'course-schedule-connector' ),
			__( 'Display sets', 'course-schedule-connector' ),
			Capabilities::MANAGE_CONTENT,
			DisplaySetsPage::SLUG,
			array( $sets, 'render' )
		);

		$unmatched = new UnmatchedPage( $this->plugin );

		add_submenu_page(
			self::SLUG,
			__( 'Unmatched lessons', 'course-schedule-connector' ),
			$this->bubble( __( 'Unmatched lessons', 'course-schedule-connector' ), UnmatchedPage::pending( $this->plugin ) ),
			Capabilities::MANAGE_CONTENT,
			UnmatchedPage::SLUG,
			array( $unmatched, 'render' )
		);

		$makeup = new MakeupPage( $this->plugin );

		add_submenu_page(
			self::SLUG,
			__( 'Make-up lessons', 'course-schedule-connector' ),
			$this->bubble( __( 'Make-up lessons', 'course-schedule-connector' ), MakeupPage::pending( $this->plugin ) ),
			Capabilities::MANAGE_CONTENT,
			MakeupPage::SLUG,
			array( $makeup, 'render' )
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

	/**
	 * Adds a count to a menu label, the way core marks pending updates.
	 *
	 * Make-up lessons and unplaceable classes both turn up a few at a time, and
	 * nobody opens a screen on the off chance that something new is waiting on
	 * it. The bubble is the only thing that tells them.
	 *
	 * @param string $label   Menu label.
	 * @param int    $pending Number of things waiting.
	 * @return string
	 */
	private function bubble( string $label, int $pending ): string {
		if ( 0 === $pending ) {
			return $label;
		}

		return sprintf(
			'%s <span class="update-plugins count-%d"><span class="update-count">%s</span></span>',
			$label,
			$pending,
			number_format_i18n( $pending )
		);
	}
}
