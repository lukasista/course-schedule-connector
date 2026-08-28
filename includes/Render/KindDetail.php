<?php
/**
 * Everything a kind of course's page needs, worked out once.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

use CSCS\Data\DisplaySet;
use CSCS\Data\KindType;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * The page of one kind of course: what it is called and what runs under it.
 *
 * Deliberately thin next to {@see CourseDetail} and {@see TrainerDetail}. A
 * kind holds no facts of its own — no price, no room, no capacity — because it
 * is not a thing anybody signs up for. What it has is a name, whatever somebody
 * wrote about it, and the courses filed under it, and only the last of those
 * needs working out.
 */
final class KindDetail {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * The page.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $post;

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param \WP_Post $post   Kind page.
	 */
	public function __construct( Plugin $plugin, \WP_Post $post ) {
		$this->plugin = $plugin;
		$this->post   = $post;
	}

	/**
	 * Returns what this kind is called.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) get_the_title( $this->post );
	}

	/**
	 * Returns the courses of this kind, as a listing.
	 *
	 * The same listing code every other list of courses uses, so this table
	 * folds on a telephone exactly as the rest do, and the columns are the ones
	 * a trainer's table already answers with — the timetable a visitor is
	 * reading, not the catalogue an administrator is.
	 *
	 * @return Listing|null
	 */
	public function course_listing(): ?Listing {
		$ids = $this->plugin->kinds()->courses( $this->post->ID );

		if ( array() === $ids ) {
			return null;
		}

		$query = new Query( $this->plugin );
		$rows  = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post ) {
				$rows[] = $query->course_row( $post );
			}
		}

		if ( array() === $rows ) {
			return null;
		}

		$set = DisplaySet::from_array(
			array(
				'type'    => DisplaySet::TYPE_COURSES,
				'columns' => array( 'day', 'hours', 'duration', 'age', 'gender', 'level', 'price', 'places', 'button' ),
			)
		);

		$times = $query->course_times(
			array_map(
				static function ( array $row ): int {
					return (int) ( $row['course_id'] ?? 0 );
				},
				$rows
			)
		);

		return new Listing( $set, $rows, Renderer::labels_for( $set ), $this->plugin->settings(), $times );
	}

	/**
	 * Returns what this kind costs, one line per length of lesson.
	 *
	 * A kind has no price of its own, and often not one price at all: an hour
	 * of gymnastics and an hour and a half of it are different courses at
	 * different money, and on this site every kind but a few has two. So the
	 * answer is the set of pairs actually on offer — "90 minut — 5 160 Kč" —
	 * rather than a number that would have to be wrong for somebody.
	 *
	 * Courses with no price are left out rather than shown as free.
	 *
	 * @return array<int, string>
	 */
	public function prices(): array {
		$ids = $this->plugin->kinds()->courses( $this->post->ID );

		if ( array() === $ids ) {
			return array();
		}

		$query = new Query( $this->plugin );
		$rows  = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post ) {
				$rows[] = $query->course_row( $post );
			}
		}

		$times = $query->course_times(
			array_map(
				static function ( array $row ): int {
					return (int) ( $row['course_id'] ?? 0 );
				},
				$rows
			)
		);

		$pairs = array();

		foreach ( $rows as $row ) {
			$price = $row['price'] ?? null;

			if ( null === $price || '' === (string) $price ) {
				continue;
			}

			$minutes = 0;

			foreach ( $times[ (int) ( $row['course_id'] ?? 0 ) ] ?? array() as $slot ) {
				$minutes = Formatter::minutes_between(
					(string) ( $slot['from'] ?? '' ),
					(string) ( $slot['to'] ?? '' )
				);

				if ( 0 !== $minutes ) {
					break;
				}
			}

			// Keyed by both, so two courses of one length and one price are one
			// line, and the same length at two prices is two.
			$pairs[ $minutes . '|' . (string) $price ] = array(
				'minutes' => $minutes,
				'price'   => (string) $price,
			);
		}

		uasort(
			$pairs,
			static function ( array $left, array $right ): int {
				return array( $left['minutes'], (float) $left['price'] ) <=> array( $right['minutes'], (float) $right['price'] );
			}
		);

		$lines = array();

		foreach ( $pairs as $pair ) {
			// A course with a price of zero really is free — that is what a
			// course record with 0.00 in it says, and this list is of courses.
			$price  = Formatter::price( $pair['price'], __( 'Free', 'course-schedule-connector' ) );
			$length = Formatter::duration( (int) $pair['minutes'] );

			if ( '' === $price ) {
				continue;
			}

			$lines[] = '' === $length
				? $price
				: sprintf(
					/* translators: 1: how long a lesson lasts, 2: what the course costs. */
					_x( '%1$s — %2$s', 'a length of lesson and what it costs', 'course-schedule-connector' ),
					$length,
					$price
				);
		}

		return array_values( array_unique( $lines ) );
	}

	/**
	 * Returns the term this page is paired with, if any.
	 *
	 * @return \WP_Term|null
	 */
	public function term(): ?\WP_Term {
		return $this->plugin->kinds()->term_for( $this->post->ID );
	}

	/**
	 * Returns the page itself.
	 *
	 * @return \WP_Post
	 */
	public function post(): \WP_Post {
		return $this->post;
	}

	/**
	 * Returns the key this page is paired by, for anything that has to match.
	 *
	 * @return string
	 */
	public function key(): string {
		return (string) get_post_meta( $this->post->ID, KindType::META_KEY_NAME, true );
	}
}
