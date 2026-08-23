<?php
/**
 * The trainer page.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\TrainerType;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Gives a trainer a page of its own without taking the theme's over.
 *
 * The same arrangement as a course page, and for the same reason: the theme
 * already draws everything around the content, and a plugin that replaced the
 * whole template would spend the rest of its life fighting for a layout the
 * theme has already worked out.
 *
 * A theme that would rather do it properly puts
 * `course-schedule-connector/single-trainer.php` in its own directory, or a
 * `single-cscs_trainer_profile.php` beside its other templates.
 */
final class SingleTrainer {

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
	 * Replaces a trainer's content with the trainer's page.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function content( string $content ): string {
		if ( ! $this->is_trainer_page() || $this->rendered ) {
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

		$file = Renderer::locate( 'single-trainer' );

		if ( '' === $file ) {
			return $content;
		}

		wp_enqueue_style( Assets::HANDLE );

		$detail = new TrainerDetail( $this->plugin, $post );

		ob_start();

		include $file;

		return (string) ob_get_clean();
	}

	/**
	 * Prints the machine-readable description of a trainer.
	 *
	 * @return void
	 */
	public function structured_data(): void {
		if ( ! $this->is_trainer() ) {
			return;
		}

		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$json = ( new TrainerDetail( $this->plugin, $post ) )->structured_data();

		if ( '' === $json ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built by wp_json_encode from values escaped for JSON; escaping again would corrupt it.
		);
	}

	/**
	 * Whether this request is a single trainer being shown to a visitor.
	 *
	 * @return bool
	 */
	private function is_trainer_page(): bool {
		return $this->is_trainer() && in_the_loop();
	}

	/**
	 * Whether this request is a trainer being shown to a visitor.
	 *
	 * Separate from {@see self::is_trainer_page()} because `wp_head` runs
	 * before the loop, where `in_the_loop()` is always false.
	 *
	 * @return bool
	 */
	private function is_trainer(): bool {
		return is_singular( TrainerType::TRAINER ) && is_main_query();
	}
}
