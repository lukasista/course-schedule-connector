<?php
/**
 * A kind of course's own page.
 *
 * A theme may replace this by copying it to
 * `course-schedule-connector/single-kind.php`. Everything is decided before
 * this runs: `$detail` holds the name and the courses filed under it.
 *
 * The words and the picture are the page's own, so they are printed by
 * WordPress before this and not repeated here — what this adds is the one thing
 * a page cannot write for itself, which is the timetable of what actually runs.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\KindDetail $detail
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$cscs_kind    = $detail->post();
$cscs_courses = $detail->course_listing();

?>
<div class="cscs cscs-kind" id="kind-<?php echo esc_attr( (string) $cscs_kind->ID ); ?>">
	<?php if ( null !== $cscs_courses ) : ?>
		<section class="cscs-kind__courses">
			<h2><?php esc_html_e( 'Schedule', 'course-schedule-connector' ); ?></h2>
			<?php
			$listing = $cscs_courses;
			require \CSCS\Render\Renderer::locate( 'partials/table' );
			?>
		</section>
	<?php else : ?>
		<p class="cscs-kind__empty"><?php esc_html_e( 'No course of this kind is running at the moment.', 'course-schedule-connector' ); ?></p>
	<?php endif; ?>
</div>
