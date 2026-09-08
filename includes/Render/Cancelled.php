<?php
/**
 * What becomes of the address of a course that is no longer offered.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\PostType;
use CSCS\Plugin;

/**
 * Sends an old course link on to the kind of course it belonged to.
 *
 * A course iSport has stopped offering is taken off the site, and its page then
 * answers the way any address with nothing behind it answers: not found. Which
 * is correct and unhelpful. Somebody following a link from last term, or from a
 * search result, was looking for gymnastics for eight-year-olds, and the page
 * that lists the gymnastics still running answers that question — so that is
 * where they are sent, permanently, which also passes on whatever the old
 * address had earned.
 *
 * A course whose kind has no page of its own is left at the not-found page:
 * there is nowhere better to send anybody, and inventing a destination is worse
 * than admitting the page has gone.
 */
final class Cancelled {

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
	 * Hooks the redirect.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'redirect' ) );
	}

	/**
	 * Redirects the address of a withdrawn course, where there is somewhere to send it.
	 *
	 * @return void
	 */
	public function redirect(): void {
		if ( is_admin() || ! is_404() ) {
			return;
		}

		$post = $this->requested();

		if ( ! $post instanceof \WP_Post ) {
			return;
		}

		$target = $this->target( $post );

		/**
		 * Filters where the address of a withdrawn course sends a visitor.
		 *
		 * An empty string leaves the not-found page as it is.
		 *
		 * @since 1.0.0
		 *
		 * @param string   $target Absolute URL, or an empty string.
		 * @param \WP_Post $post   The withdrawn course.
		 */
		$target = (string) apply_filters( 'cscs_cancelled_course_redirect', $target, $post );

		if ( '' === $target ) {
			return;
		}

		wp_safe_redirect( $target, 301 );

		exit;
	}

	/**
	 * Returns the withdrawn course the address was asking for, if it was.
	 *
	 * @return \WP_Post|null
	 */
	private function requested(): ?\WP_Post {
		$slug = (string) get_query_var( PostType::COURSE );

		if ( '' === $slug ) {
			$slug = (string) get_query_var( 'name' );
		}

		if ( '' === $slug ) {
			return null;
		}

		$found = get_posts(
			array(
				'post_type'        => PostType::COURSE,
				'name'             => $slug,
				'post_status'      => PostType::CANCELLED,
				'posts_per_page'   => 1,
				'no_found_rows'    => true,
				'suppress_filters' => false,
			)
		);

		return array() === $found ? null : $found[0];
	}

	/**
	 * Returns the page of the kind a course belonged to.
	 *
	 * @param \WP_Post $post Course.
	 * @return string Absolute URL, or an empty string.
	 */
	private function target( \WP_Post $post ): string {
		$terms = get_the_terms( $post, PostType::KIND );

		if ( ! is_array( $terms ) || array() === $terms ) {
			return '';
		}

		$page = $this->plugin->kinds()->find( (string) $terms[0]->name );

		if ( 0 === $page || 'publish' !== get_post_status( $page ) ) {
			return '';
		}

		$link = get_permalink( $page );

		return is_string( $link ) ? $link : '';
	}
}
