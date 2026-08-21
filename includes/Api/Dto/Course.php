<?php
/**
 * A course as returned by the courses endpoint.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api\Dto;

/**
 * Normalised course record.
 *
 * Every property has already been coerced to a predictable type. A null price
 * is preserved rather than defaulted, because for a course it carries meaning.
 */
final class Course {

	/**
	 * Constructor.
	 *
	 * @param int                $id                 Internal course id.
	 * @param string             $name               Course name.
	 * @param string             $description        Course description as supplied by the API.
	 * @param string             $activity_name      Activity name, which may differ from the course name.
	 * @param string             $match_key          Key used to match class occurrences to this course.
	 * @param string|null        $url                Direct link into the iSport schedule.
	 * @param int|null           $stamp_from         Start timestamp.
	 * @param int|null           $stamp_to           End timestamp.
	 * @param string|null        $date_from          Start date, Y-m-d.
	 * @param string|null        $date_to            End date, Y-m-d.
	 * @param string|null        $price              Price as a decimal string, or null when the course is free.
	 * @param int                $number_lessons     Number of terms.
	 * @param string             $trainer_name       Trainer name.
	 * @param string             $room_name          Room name as reported by the API. Only a fallback: the authoritative
	 *                                               room list is derived from the course's matched classes.
	 * @param string|null        $colour             Text colour, bare hex.
	 * @param string|null        $background         Background colour, bare hex.
	 * @param int                $capacity           Total capacity.
	 * @param int                $capacity_waiting   Waiting-list capacity.
	 * @param int                $occupied           Occupied places.
	 * @param int                $available          Free places.
	 * @param int                $available_waiting  Free waiting-list places.
	 * @param string|null        $image              Course image URL.
	 * @param string|null        $trainer_image      Trainer image URL.
	 * @param array<int, string> $tags               Tag id to tag name.
	 * @param string             $rating             Rating as supplied by the API.
	 * @param array<int, CourseTerm> $terms          Scheduled terms.
	 */
	public function __construct(
		public readonly int $id,
		public readonly string $name,
		public readonly string $description,
		public readonly string $activity_name,
		public readonly string $match_key,
		public readonly ?string $url,
		public readonly ?int $stamp_from,
		public readonly ?int $stamp_to,
		public readonly ?string $date_from,
		public readonly ?string $date_to,
		public readonly ?string $price,
		public readonly int $number_lessons,
		public readonly string $trainer_name,
		public readonly string $room_name,
		public readonly ?string $colour,
		public readonly ?string $background,
		public readonly int $capacity,
		public readonly int $capacity_waiting,
		public readonly int $occupied,
		public readonly int $available,
		public readonly int $available_waiting,
		public readonly ?string $image,
		public readonly ?string $trainer_image,
		public readonly array $tags,
		public readonly string $rating,
		public readonly array $terms
	) {}

	/**
	 * Returns the timestamps of every term, for matching against class occurrences.
	 *
	 * @return array<int, int>
	 */
	public function term_stamps(): array {
		return array_map( static fn( CourseTerm $term ): int => $term->stamp, $this->terms );
	}
}
