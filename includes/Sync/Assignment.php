<?php
/**
 * One class occurrence tied to a course.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

/**
 * The outcome of matching a single class occurrence.
 */
final class Assignment {

	/**
	 * Matched on the normalised name and an exact term timestamp.
	 */
	public const STRICT = 'strict';

	/**
	 * Matched on the normalised name, with the occurrence inside the course's dates.
	 */
	public const RANGE = 'range';

	/**
	 * Matched on the accent-stripped name and an exact term timestamp.
	 */
	public const LOOSE = 'loose';

	/**
	 * Assigned by hand in the admin and never overwritten.
	 */
	public const MANUAL = 'manual';

	/**
	 * Constructor.
	 *
	 * @param int    $term_id   Class occurrence id.
	 * @param int    $course_id Course id.
	 * @param string $method    One of the class constants.
	 */
	public function __construct(
		public readonly int $term_id,
		public readonly int $course_id,
		public readonly string $method
	) {}
}
