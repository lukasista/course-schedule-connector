<?php
/**
 * One scheduled date of a course.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api\Dto;

/**
 * A single term of a course, as listed in the course record's terms array.
 */
final class CourseTerm {

	/**
	 * Constructor.
	 *
	 * @param int    $stamp         Unix timestamp of the term.
	 * @param string $date_text     Date as printed by the API, in D.M.Y form.
	 * @param string $datetime_text Date and time as printed by the API.
	 */
	public function __construct(
		public readonly int $stamp,
		public readonly string $date_text = '',
		public readonly string $datetime_text = ''
	) {}
}
