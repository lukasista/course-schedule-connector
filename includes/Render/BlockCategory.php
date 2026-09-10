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
 * Gives the plugin's blocks sections of their own in the inserter.
 *
 * Thirty-two blocks scattered through "Widgets" are not a set anybody can find,
 * and half of them are called things like "Price" and "Name", which is a name
 * that means nothing next to the fifty other blocks WordPress and a theme
 * already offer.
 *
 * One section was the first step and not enough of one. Thirty-two entries
 * under one heading is a shorter list and still the wrong question to ask of
 * it: somebody in the editor is building a trainer's page, or a kind of course,
 * or a course, and wants the fields of that one thing. WordPress's categories
 * are a flat list — a block belongs to exactly one and none of them nest — so
 * three of them it is, named so that they sort together and read as one set.
 * The same arrangement WooCommerce uses for the same reason.
 */
final class BlockCategory {

	/**
	 * The category a course's fields stand in.
	 */
	public const COURSES = 'cscs-courses';

	/**
	 * The category a kind of course's fields stand in.
	 */
	public const KINDS = 'cscs-kinds';

	/**
	 * The category a trainer's fields stand in.
	 */
	public const TRAINERS = 'cscs-trainers';

	/**
	 * Returns the category a field of a given context belongs to.
	 *
	 * @param string $context Field context — `course`, `trainer`, `kind`.
	 * @return string
	 */
	public static function of( string $context ): string {
		$sections = array(
			'course'  => self::COURSES,
			'kind'    => self::KINDS,
			'trainer' => self::TRAINERS,
		);

		return $sections[ $context ] ?? self::COURSES;
	}

	/**
	 * Hooks the categories.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'block_categories_all', array( $this, 'add' ) );
	}

	/**
	 * Returns the sections, in the order they should read.
	 *
	 * Named "iSport: something" rather than something on its own, because an
	 * inserter is a long list of other people's headings and "Courses" in it is
	 * anybody's. The order is the order somebody works in: the courses first,
	 * because that is most of the site, then the pages that group them, then
	 * the people who run them.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function sections(): array {
		return array(
			array(
				'slug'  => self::COURSES,
				/* translators: a section of blocks in the editor's inserter */
				'title' => __( 'iSport: courses', 'course-schedule-connector' ),
				'icon'  => null,
			),
			array(
				'slug'  => self::KINDS,
				/* translators: a section of blocks in the editor's inserter */
				'title' => __( 'iSport: kinds of course', 'course-schedule-connector' ),
				'icon'  => null,
			),
			array(
				'slug'  => self::TRAINERS,
				/* translators: a section of blocks in the editor's inserter */
				'title' => __( 'iSport: trainers', 'course-schedule-connector' ),
				'icon'  => null,
			),
		);
	}

	/**
	 * Adds the sections to the inserter's list.
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

		$present = array();

		foreach ( $categories as $category ) {
			if ( is_array( $category ) ) {
				$present[] = (string) ( $category['slug'] ?? '' );
			}
		}

		$wanted = array();

		foreach ( self::sections() as $section ) {
			if ( ! in_array( $section['slug'], $present, true ) ) {
				$wanted[] = $section;
			}
		}

		if ( array() === $wanted ) {
			return $categories;
		}

		$core = array( 'text', 'media', 'design', 'widgets', 'theme', 'embed', 'reusable' );
		$at   = 0;

		foreach ( $categories as $index => $category ) {
			if ( is_array( $category ) && in_array( (string) ( $category['slug'] ?? '' ), $core, true ) ) {
				$at = $index + 1;
			}
		}

		array_splice( $categories, $at, 0, $wanted );

		return $categories;
	}
}
