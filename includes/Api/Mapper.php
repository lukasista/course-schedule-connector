<?php
/**
 * Translation of raw API payloads into typed records.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api;

use CSCS\Api\Dto\Course;
use CSCS\Api\Dto\CourseTerm;
use CSCS\Api\Dto\Lesson;
use CSCS\Support\Normalise;

/**
 * Converts decoded API payloads into course and lesson objects.
 *
 * This is the single place that knows the shape of the remote payload. The API
 * carries no version number and offers no compatibility promise, so confining
 * that knowledge to one class means a change upstream is a change in one file.
 *
 * Reading is deliberately defensive: unknown keys are ignored, missing keys fall
 * back to a neutral value, and a record that lacks an identifier is skipped
 * rather than allowed to poison the result. Nothing here touches WordPress.
 */
final class Mapper {

	/**
	 * Maps a decoded courses payload.
	 *
	 * @param mixed $payload Decoded JSON.
	 * @return array<int, Course> Courses keyed by course id.
	 */
	public function map_courses( $payload ): array {
		$courses = array();

		foreach ( $this->records( $payload ) as $record ) {
			$course = $this->map_course( $record );

			if ( null !== $course ) {
				$courses[ $course->id ] = $course;
			}
		}

		return $courses;
	}

	/**
	 * Maps a decoded activities payload.
	 *
	 * @param mixed $payload Decoded JSON.
	 * @return array<int, Lesson> Lessons keyed by term id.
	 */
	public function map_lessons( $payload ): array {
		$lessons = array();

		foreach ( $this->records( $payload ) as $record ) {
			$lesson = $this->map_lesson( $record );

			if ( null !== $lesson ) {
				$lessons[ $lesson->id_term ] = $lesson;
			}
		}

		return $lessons;
	}

	/**
	 * Maps one course record.
	 *
	 * @param array<string, mixed> $record Raw record.
	 * @return Course|null Null when the record carries no usable identifier.
	 */
	private function map_course( array $record ): ?Course {
		$id = Normalise::to_int( $record['id_course'] ?? null );

		if ( 0 === $id ) {
			return null;
		}

		$activity_name = Normalise::to_string( $record['activity_name'] ?? '' );
		$name          = Normalise::to_string( $record['course_name'] ?? '' );

		if ( '' === $activity_name ) {
			$activity_name = $name;
		}

		return new Course(
			$id,
			$name,
			Normalise::to_string( $record['course_description'] ?? '' ),
			$activity_name,
			Normalise::match_key( $activity_name ),
			Normalise::to_url( $record['course_url'] ?? null ),
			Normalise::to_timestamp( $record['stamp_from'] ?? null ),
			Normalise::to_timestamp( $record['stamp_to'] ?? null ),
			Normalise::to_date( $record['date_from'] ?? null ),
			Normalise::to_date( $record['date_to'] ?? null ),
			Normalise::to_price( $record['price'] ?? null ),
			Normalise::to_int( $record['number_lessons'] ?? null ),
			Normalise::to_string( $record['trainer_name'] ?? '' ),
			Normalise::to_string( $record['room_name'] ?? '' ),
			Normalise::to_hex_colour( $record['color'] ?? null ),
			Normalise::to_hex_colour( $record['background'] ?? null ),
			Normalise::to_int( $record['capacity'] ?? null ),
			Normalise::to_int( $record['capacity_waiting'] ?? null ),
			Normalise::to_int( $record['occupied'] ?? null ),
			Normalise::to_int( $record['available'] ?? null ),
			Normalise::to_int( $record['available_waiting'] ?? null ),
			Normalise::to_url( $record['image'] ?? null ),
			Normalise::to_url( $record['trainer_image'] ?? null ),
			Normalise::to_tags( $record['tags'] ?? null ),
			Normalise::to_string( $record['rating'] ?? '' ),
			$this->map_terms( $record['terms'] ?? null )
		);
	}

	/**
	 * Maps one class occurrence.
	 *
	 * @param array<string, mixed> $record Raw record.
	 * @return Lesson|null Null when the record carries no usable identifier.
	 */
	private function map_lesson( array $record ): ?Lesson {
		$id_term = Normalise::to_int( $record['id_activity_term'] ?? null );

		if ( 0 === $id_term ) {
			return null;
		}

		$activity_name = Normalise::to_string( $record['activity_name'] ?? '' );

		return new Lesson(
			$id_term,
			Normalise::to_int( $record['id_activity'] ?? null ),
			$activity_name,
			Normalise::match_key( $activity_name ),
			Normalise::to_string( $record['activity_description'] ?? '' ),
			Normalise::to_timestamp( $record['stamp_from'] ?? null ),
			Normalise::to_timestamp( $record['stamp_to'] ?? null ),
			Normalise::to_date( $record['date'] ?? null ),
			Normalise::to_time( $record['time_from'] ?? null ),
			Normalise::to_time( $record['time_to'] ?? null ),
			Normalise::to_url( $record['activity_url'] ?? null ),
			Normalise::to_int( $record['id_tab'] ?? null ),
			Normalise::to_string( $record['tab_name'] ?? '' ),
			Normalise::to_url( $record['tab_url'] ?? null ),
			Normalise::to_int( $record['id_lane'] ?? null ),
			Normalise::to_string( $record['lane_name'] ?? '' ),
			Normalise::to_int( $record['id_trainer'] ?? null ),
			Normalise::to_string( $record['trainer_name'] ?? '' ),
			Normalise::to_url( $record['image'] ?? null ),
			Normalise::to_url( $record['trainer_image'] ?? null ),
			Normalise::to_price( $record['price'] ?? null ),
			Normalise::to_hex_colour( $record['color'] ?? null ),
			Normalise::to_hex_colour( $record['background'] ?? null ),
			Normalise::to_int( $record['capacity'] ?? null ),
			Normalise::to_int( $record['capacity_waiting'] ?? null ),
			Normalise::to_int( $record['occupied'] ?? null ),
			Normalise::to_int( $record['available'] ?? null ),
			Normalise::to_int( $record['available_waiting'] ?? null ),
			Normalise::to_bool( $record['canceled'] ?? null ),
			Normalise::to_bool( $record['booking_allowed'] ?? null ),
			Normalise::to_bool( $record['waiting_allowed'] ?? null ),
			Normalise::to_string( $record['booking_not_allowed_reason'] ?? '' ),
			Normalise::to_tags( $record['tags'] ?? null ),
			Normalise::to_string( $record['rating'] ?? '' )
		);
	}

	/**
	 * Maps the terms array of a course.
	 *
	 * @param mixed $terms Raw terms value.
	 * @return array<int, CourseTerm>
	 */
	private function map_terms( $terms ): array {
		if ( ! is_array( $terms ) ) {
			return array();
		}

		$mapped = array();

		foreach ( $terms as $term ) {
			if ( ! is_array( $term ) ) {
				continue;
			}

			$stamp = Normalise::to_timestamp( $term['stamp'] ?? null );

			if ( null === $stamp ) {
				continue;
			}

			$mapped[] = new CourseTerm(
				$stamp,
				Normalise::to_string( $term['date_txt'] ?? '' ),
				Normalise::to_string( $term['date_time_txt'] ?? '' )
			);
		}

		usort( $mapped, static fn( CourseTerm $a, CourseTerm $b ): int => $a->stamp <=> $b->stamp );

		return $mapped;
	}

	/**
	 * Extracts the list of records from a decoded payload.
	 *
	 * The endpoints return a JSON array, but a keyed object would decode to an
	 * associative array, so both shapes are accepted.
	 *
	 * @param mixed $payload Decoded JSON.
	 * @return array<int, array<string, mixed>>
	 */
	private function records( $payload ): array {
		if ( ! is_array( $payload ) ) {
			return array();
		}

		$records = array();

		foreach ( $payload as $record ) {
			if ( is_array( $record ) ) {
				$records[] = $record;
			}
		}

		return $records;
	}
}
