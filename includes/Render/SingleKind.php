<?php
/**
 * The page of one kind of course.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\KindType;
use CSCS\Plugin;

/**
 * Adds the timetable of a kind to the page written about that kind.
 *
 * Added to the content rather than replacing the theme's template, and appended
 * rather than substituted: what somebody wrote in the editor is the page, and
 * this is the one part of it nobody can write by hand — the courses of this
 * kind that are actually running, which change every term.
 */
final class SingleKind {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Whether the timetable has already been added on this request.
	 *
	 * @var bool
	 */
	private bool $rendered = false;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks the page.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'the_content', array( $this, 'content' ), 20 );
	}

	/**
	 * Appends the timetable to a kind's page.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function content( string $content ): string {
		if ( ! $this->is_kind_page() || $this->rendered ) {
			return $content;
		}

		$this->rendered = true;

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return $content;
		}

		$file = Renderer::locate( 'single-kind' );

		if ( '' === $file ) {
			return $content;
		}

		wp_enqueue_style( Assets::HANDLE );

		$cscs_detail = new KindDetail( $this->plugin, $post );

		ob_start();

		include $file;

		return $content . (string) ob_get_clean();
	}

	/**
	 * Returns whether this request is a kind's own page, in the main query.
	 *
	 * @return bool
	 */
	private function is_kind_page(): bool {
		return is_singular( KindType::KIND ) && in_the_loop() && is_main_query();
	}
}
