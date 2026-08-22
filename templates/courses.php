<?php
/**
 * Listing of courses.
 *
 * A theme may replace this file by copying it to
 * `course-schedule-connector/courses.php` (or `jojo-isport/courses.php`) in the
 * theme or child theme. Everything has already been decided by the time this
 * runs: `$listing` holds the columns, the rows and the words.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\Listing $listing
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

?>
<div class="cscs cscs--courses" id="cscs-<?php echo esc_attr( $listing->set->id ); ?>" data-cscs-set="<?php echo esc_attr( $listing->set->id ); ?>" data-cscs-page="<?php echo esc_attr( (string) $listing->args->page ); ?>" data-cscs-week="<?php echo esc_attr( (string) $listing->args->week ); ?>" data-cscs-room="<?php echo esc_attr( (string) $listing->args->room ); ?>">
	<?php if ( '' !== $listing->heading() ) : ?>
		<h2 class="cscs-heading"><?php echo esc_html( $listing->heading() ); ?></h2>
	<?php endif; ?>

	<?php require \CSCS\Render\Renderer::locate( 'partials/controls' ); ?>

	<?php if ( $listing->is_empty() ) : ?>
		<p class="cscs-empty"><?php echo esc_html( $listing->empty_text() ); ?></p>
	<?php else : ?>
		<?php require \CSCS\Render\Renderer::locate( 'partials/table' ); ?>
		<?php require \CSCS\Render\Renderer::locate( 'partials/pager' ); ?>
	<?php endif; ?>
</div>
