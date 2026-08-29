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

// Courses and trainers are posts with pages and text somebody wrote; they go
// only on request.
$cscs_posts = get_posts(
	array(
		'post_type'        => array(
			\CSCS\Data\PostType::COURSE,
			\CSCS\Data\TrainerType::TRAINER,
			\CSCS\Data\KindType::KIND,
		),
		'post_status'      => 'any',
		'numberposts'      => -1,
		'fields'           => 'ids',
		'suppress_filters' => false,
	)
);

foreach ( $cscs_posts as $cscs_post_id ) {
	wp_delete_post( (int) $cscs_post_id, true );
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

// The groupings the courses were filed under. Deleting a post does not delete
// the terms it was in, and a site that removed this plugin should not be left
// with two hundred empty ones in its database.
foreach ( array(
	\CSCS\Data\PostType::ROOM,
	\CSCS\Data\PostType::KIND,
	\CSCS\Data\PostType::TAG,
) as $cscs_taxonomy ) {
	$cscs_terms = get_terms(
		array(
			'taxonomy'   => $cscs_taxonomy,
			'hide_empty' => false,
			'fields'     => 'ids',
		)
	);

	if ( ! is_array( $cscs_terms ) ) {
		continue;
	}

	foreach ( $cscs_terms as $cscs_term_id ) {
		wp_delete_term( (int) $cscs_term_id, $cscs_taxonomy );
	}
}

foreach ( array(
	\CSCS\Sync\Scheduler::HOOK_COURSES,
	\CSCS\Sync\Scheduler::HOOK_NEAR,
	\CSCS\Sync\Scheduler::HOOK_FAR,
	\CSCS\Sync\Scheduler::HOOK_RETENTION,
) as $cscs_hook ) {
	wp_clear_scheduled_hook( $cscs_hook );
}
