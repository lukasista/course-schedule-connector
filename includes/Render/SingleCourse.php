<?php
/**
 * The course page.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\PostType;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Gives a course a page of its own without taking the theme's over.
 *
 * A course is a post, so the theme already draws the header, the footer and
 * whatever it puts around a page. Replacing the whole template would mean
 * fighting the theme for a layout it already has; instead the course's own
 * content is rendered into the place the theme prints post content, and
 * everything around it stays the theme's business.
 *
 * A theme that would rather do it properly can still put
 * `course-schedule-connector/single-course.php` in its own directory, or drop
 * a `single-cscs_course.php` beside its other templates and take over entirely.
 */
final class SingleCourse {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Whether the page has already been rendered on this request.
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
		add_action( 'wp_head', array( $this, 'structured_data' ) );
	}

	/**
	 * Replaces a course's content with the course's page.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function content( string $content ): string {
		if ( ! $this->is_course_page() || $this->rendered ) {
			return $content;
		}

		// The template prints the post's own text as part of the page, and it
		// asks for it directly rather than through this filter, which would
		// otherwise call itself for ever.
		$this->rendered = true;

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return $content;
		}

		$file = Renderer::locate( 'single-course' );

		if ( '' === $file ) {
			return $content;
		}

		wp_enqueue_style( Assets::HANDLE );

		$detail = new CourseDetail( $this->plugin, $post );

		ob_start();

		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Prints the machine-readable description of a course.
	 *
	 * @return void
	 */
	public function structured_data(): void {
		if ( ! $this->is_course_page() ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$json = ( new CourseDetail( $this->plugin, $post ) )->structured_data();

		if ( '' === $json ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by wp_json_encode from values escaped for JSON; escaping again would corrupt it.
		);
	}

	/**
	 * Whether this request is a single course being shown to a visitor.
	 *
	 * @return bool
	 */
	private function is_course_page(): bool {
		return is_singular( PostType::COURSE ) && is_main_query() && in_the_loop();
	}
}
