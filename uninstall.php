<?php
/**
 * Uninstall routine.
 *
 * Runs only when the administrator deletes the plugin, and only removes data
 * when they asked for that in the settings. Deleting a plugin is not the same
 * as wanting the course pages and everything written on them to disappear, so
 * the destructive path is opt-in rather than assumed.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once __DIR__ . '/includes/Autoloader.php';

\CSCS\Autoloader::register( __DIR__ . '/includes/' );

$cscs_settings = get_option( \CSCS\Settings::OPTION, array() );

if ( ! is_array( $cscs_settings ) || empty( $cscs_settings['delete_data_on_uninstall'] ) ) {
	return;
}

// Courses are posts with pages and text somebody wrote; they go only on request.
$cscs_courses = get_posts(
	array(
		'post_type'        => \CSCS\Data\PostType::COURSE,
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => false,
	)
);

foreach ( $cscs_courses as $cscs_course_id ) {
	wp_delete_post( (int) $cscs_course_id, true );
}

\CSCS\Data\Schema::drop();
\CSCS\Admin\Capabilities::remove();

$cscs_options = array(
	\CSCS\Settings::OPTION,
	\CSCS\Data\LessonRepository::MANUAL_OPTION,
	\CSCS\Data\LessonRepository::MAKEUP_OPTION,
	\CSCS\Data\DisplaySetRepository::OPTION,
	\CSCS\Data\RoomMap::OPTION,
);

foreach ( $cscs_options as $cscs_option ) {
	delete_option( $cscs_option );
}

foreach ( array( 'cscs_sync_courses', 'cscs_sync_lessons_near', 'cscs_sync_lessons_far', 'cscs_retention' ) as $cscs_hook ) {
	wp_clear_scheduled_hook( $cscs_hook );
}
