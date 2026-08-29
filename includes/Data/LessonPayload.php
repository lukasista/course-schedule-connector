<?php
/**
 * Serialisation of class occurrences for storage.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

use CSCS\Api\Dto\Lesson;

/**
 * Turns an occurrence into a stored record and back again.
 *
 * The original record is kept alongside the indexed columns so that matching
 * can be re-run without asking the remote system again. That only works if the
 * round trip is exact, which is why it lives here as pure functions with a test
 * rather than inline in a repository where a renamed property would break it
 * silently.
 */
final class LessonPayload {

	/**
	 * Encodes an occurrence for storage.
	 *
	 * @param Lesson $lesson Occurrence.
	 * @return string JSON.
	 */
	public static function encode( Lesson $lesson ): string {
		return (string) wp_json_encode( get_object_vars( $lesson ) );
	}

	/**
	 * Restores the API field names a stored record was serialised from.
	 *
	 * @param array<string, mixed> $stored Decoded stored record.
	 * @return array<string, mixed>
	 */
	public static function to_api_shape( array $stored ): array {
		return array(
			'id_activity_term'           => $stored['id_term'] ?? 0,
			'id_activity'                => $stored['id_activity'] ?? 0,
			'activity_name'              => $stored['activity_name'] ?? '',
			'activity_description'       => $stored['description'] ?? '',
			'stamp_from'                 => $stored['stamp_from'] ?? null,
			'stamp_to'                   => $stored['stamp_to'] ?? null,
			'date'                       => $stored['date'] ?? null,
			'time_from'                  => $stored['time_from'] ?? null,
			'time_to'                    => $stored['time_to'] ?? null,
			'activity_url'               => $stored['url'] ?? null,
			'id_tab'                     => $stored['id_tab'] ?? 0,
			'tab_name'                   => $stored['tab_name'] ?? '',
			'tab_url'                    => $stored['tab_url'] ?? null,
			'id_lane'                    => $stored['id_lane'] ?? 0,
			'lane_name'                  => $stored['lane_name'] ?? '',
			'id_trainer'                 => $stored['id_trainer'] ?? 0,
			'trainer_name'               => $stored['trainer_name'] ?? '',
			'image'                      => $stored['image'] ?? null,
			'trainer_image'              => $stored['trainer_image'] ?? null,
			'price'                      => $stored['price'] ?? null,
			'color'                      => $stored['colour'] ?? null,
			'background'                 => $stored['background'] ?? null,
			'capacity'                   => $stored['capacity'] ?? 0,
			'capacity_waiting'           => $stored['capacity_waiting'] ?? 0,
			'occupied'                   => $stored['occupied'] ?? 0,
			'available'                  => $stored['available'] ?? 0,
			'available_waiting'          => $stored['available_waiting'] ?? 0,
			'canceled'                   => ! empty( $stored['canceled'] ) ? 1 : 0,
			'booking_allowed'            => ! empty( $stored['booking_allowed'] ) ? 1 : 0,
			'waiting_allowed'            => ! empty( $stored['waiting_allowed'] ) ? 1 : 0,
			'booking_not_allowed_reason' => $stored['booking_not_allowed_reason'] ?? '',
			'tags'                       => $stored['tags'] ?? array(),
			'rating'                     => $stored['rating'] ?? '',
		);
	}
}
