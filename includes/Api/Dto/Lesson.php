<?php
/**
 * A single class occurrence as returned by the activities endpoint.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Api\Dto;

defined( 'ABSPATH' ) || exit;

/**
 * Normalised class occurrence.
 *
 * A null price here does not mean "free": entries such as hall rentals and open
 * public sessions carry no price at all. How that is presented is a display
 * decision made in settings, not a data decision made here.
 */
final class Lesson {

	/**
	 * Constructor.
	 *
	 * @param int                $id_term                    Unique term id, stable across requests.
	 * @param int                $id_activity                Activity id. Identifies a schedule slot, not a course.
	 * @param string             $activity_name              Activity name.
	 * @param string             $match_key                  Key used to match this occurrence to a course.
	 * @param string             $description                Activity description.
	 * @param int|null           $stamp_from                 Start timestamp.
	 * @param int|null           $stamp_to                   End timestamp.
	 * @param string|null        $date                       Date, Y-m-d.
	 * @param string|null        $time_from                  Start time, H:i.
	 * @param string|null        $time_to                    End time, H:i.
	 * @param string|null        $url                        Direct link to the class.
	 * @param int                $id_tab                     Tab id, which is how the API expresses a room.
	 * @param string             $tab_name                   Tab name.
	 * @param string|null        $tab_url                    Direct link to the tab.
	 * @param int                $id_lane                    Lane id.
	 * @param string             $lane_name                  Lane name.
	 * @param int                $id_trainer                 Trainer id.
	 * @param string             $trainer_name               Trainer name.
	 * @param string|null        $image                      Activity image URL.
	 * @param string|null        $trainer_image              Trainer image URL.
	 * @param string|null        $price                      Price as a decimal string, or null when there is none.
	 * @param string|null        $colour                     Text colour, bare hex.
	 * @param string|null        $background                 Background colour, bare hex.
	 * @param int                $capacity                   Total capacity.
	 * @param int                $capacity_waiting           Waiting-list capacity.
	 * @param int                $occupied                   Occupied places.
	 * @param int                $available                  Free places.
	 * @param int                $available_waiting          Free waiting-list places.
	 * @param bool               $canceled                   Whether the class was cancelled.
	 * @param bool               $booking_allowed            Whether booking is currently possible.
	 * @param bool               $waiting_allowed            Whether joining the waiting list is possible.
	 * @param string             $booking_not_allowed_reason Reason booking is unavailable.
	 * @param array<int, string> $tags                       Tag id to tag name.
	 * @param string             $rating                     Rating as supplied by the API.
	 */
	public function __construct(
		public readonly int $id_term,
		public readonly int $id_activity,
		public readonly string $activity_name,
		public readonly string $match_key,
		public readonly string $description,
		public readonly ?int $stamp_from,
		public readonly ?int $stamp_to,
		public readonly ?string $date,
		public readonly ?string $time_from,
		public readonly ?string $time_to,
		public readonly ?string $url,
		public readonly int $id_tab,
		public readonly string $tab_name,
		public readonly ?string $tab_url,
		public readonly int $id_lane,
		public readonly string $lane_name,
		public readonly int $id_trainer,
		public readonly string $trainer_name,
		public readonly ?string $image,
		public readonly ?string $trainer_image,
		public readonly ?string $price,
		public readonly ?string $colour,
		public readonly ?string $background,
		public readonly int $capacity,
		public readonly int $capacity_waiting,
		public readonly int $occupied,
		public readonly int $available,
		public readonly int $available_waiting,
		public readonly bool $canceled,
		public readonly bool $booking_allowed,
		public readonly bool $waiting_allowed,
		public readonly string $booking_not_allowed_reason,
		public readonly array $tags,
		public readonly string $rating
	) {}
}
