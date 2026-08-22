<?php
/**
 * Timetable of classes.
 *
 * A theme may replace this file by copying it to
 * `course-schedule-connector/schedule.php` (or `jojo-isport/schedule.php`) in the
 * theme or child theme. Everything has already been decided by the time this
 * runs: `$listing` holds the columns, the rows and the words.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\Listing $listing
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

?>
<div class="cscs cscs--schedule">
	<?php if ( '' !== $listing->heading() ) : ?>
		<h2 class="cscs-heading"><?php echo esc_html( $listing->heading() ); ?></h2>
	<?php endif; ?>

	<?php if ( $listing->is_empty() ) : ?>
		<p class="cscs-empty"><?php echo esc_html( $listing->empty_text() ); ?></p>
	<?php else : ?>
		<?php require \CSCS\Render\Renderer::locate( 'partials/table' ); ?>
	<?php endif; ?>
</div>
