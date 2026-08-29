<?php
/**
 * Listing of courses.
 *
 * A theme may replace this file by copying it to
 * `course-schedule-connector/courses.php` (or `jojo-isport/courses.php`) in the
 * theme or child theme. Everything has already been decided by the time this
 * runs: `$cscs_listing` holds the columns, the rows and the words.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\Listing $cscs_listing
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

?>
<div class="cscs cscs--courses" id="cscs-<?php echo esc_attr( $cscs_listing->set->id ); ?>" data-cscs-set="<?php echo esc_attr( $cscs_listing->set->id ); ?>" data-cscs-page="<?php echo esc_attr( (string) $cscs_listing->args->page ); ?>" data-cscs-week="<?php echo esc_attr( (string) $cscs_listing->args->week ); ?>" data-cscs-room="<?php echo esc_attr( (string) $cscs_listing->args->room ); ?>" data-cscs-said="<?php echo esc_attr( $cscs_listing->spoken() ); ?>">
	<?php if ( '' !== $cscs_listing->heading() ) : ?>
		<h2 class="cscs-heading"><?php echo esc_html( $cscs_listing->heading() ); ?></h2>
	<?php endif; ?>

	<?php require \CSCS\Render\Renderer::locate( 'partials/controls' ); ?>

	<?php if ( $cscs_listing->is_empty() ) : ?>
		<p class="cscs-empty"><?php echo esc_html( $cscs_listing->empty_text() ); ?></p>
	<?php else : ?>
		<?php require \CSCS\Render\Renderer::locate( 'partials/table' ); ?>
		<?php require \CSCS\Render\Renderer::locate( 'partials/pager' ); ?>
	<?php endif; ?>
</div>
