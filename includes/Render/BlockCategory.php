<?php
/**
 * The shelf the plugin's blocks stand on.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

/**
 * Gives the plugin's blocks a section of their own in the inserter.
 *
 * Thirty-one blocks scattered through "Widgets" are not a set anybody can find,
 * and half of them are called things like "Price" and "Name", which is a name
 * that means nothing next to the fifty other blocks WordPress and a theme
 * already offer. Under one heading they read as what they are: the fields of a
 * course, of a trainer, and of a kind of course.
 *
 * The same arrangement WooCommerce uses, and the Divi modules already have.
 */
final class BlockCategory {

	/**
	 * The category's slug.
	 */
	public const SLUG = 'cscs';

	/**
	 * Hooks the category.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'block_categories_all', array( $this, 'add' ) );
	}

	/**
	 * Adds the category to the inserter's list.
	 *
	 * Placed after the categories WordPress ships and before whatever other
	 * plugins have added, rather than at the end: the end is wherever the last
	 * plugin to be activated happens to leave it, which is not a place.
	 *
	 * @param mixed $categories Categories, as passed by the filter.
	 * @return array<int, array<string, mixed>>
	 */
	public function add( $categories ): array {
		if ( ! is_array( $categories ) ) {
			$categories = array();
		}

		foreach ( $categories as $category ) {
			if ( is_array( $category ) && self::SLUG === ( $category['slug'] ?? '' ) ) {
				return $categories;
			}
		}

		$core = array( 'text', 'media', 'design', 'widgets', 'theme', 'embed', 'reusable' );
		$at   = 0;

		foreach ( $categories as $index => $category ) {
			if ( is_array( $category ) && in_array( (string) ( $category['slug'] ?? '' ), $core, true ) ) {
				$at = $index + 1;
			}
		}

		array_splice(
			$categories,
			$at,
			0,
			array(
				array(
					'slug'  => self::SLUG,
					'title' => __( 'iSport', 'course-schedule-connector' ),
					'icon'  => null,
				),
			)
		);

		return $categories;
	}
}
