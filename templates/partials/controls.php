<?php
/**
 * The controls above a listing: which week, which room.
 *
 * Real links, with the choice in the address. A visitor without JavaScript gets
 * a page that works; one with it gets the same thing without the reload. The
 * order matters — the links are the feature, the script is the polish.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\Listing $listing
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! $listing->has_weeks() && array() === $listing->rooms ) {
	return;
}

?>
<div class="cscs-controls">
	<?php if ( $listing->has_weeks() ) : ?>
		<nav class="cscs-weeks" aria-label="<?php esc_attr_e( 'Which week', 'course-schedule-connector' ); ?>">
			<a class="cscs-weeks__step" data-cscs-nav="week" data-cscs-value="<?php echo esc_attr( (string) ( $listing->args->week - 1 ) ); ?>" href="<?php echo esc_url( $listing->url( 'week', $listing->args->week - 1 ) ); ?>" rel="prev">
				<?php esc_html_e( '← Previous week', 'course-schedule-connector' ); ?>
			</a>

			<span class="cscs-weeks__current"><?php echo esc_html( $listing->week_label() ); ?></span>

			<?php if ( 0 !== $listing->args->week ) : ?>
				<a class="cscs-weeks__today" data-cscs-nav="week" data-cscs-value="0" href="<?php echo esc_url( $listing->url( 'week', 0 ) ); ?>">
					<?php esc_html_e( 'This week', 'course-schedule-connector' ); ?>
				</a>
			<?php endif; ?>

			<a class="cscs-weeks__step" data-cscs-nav="week" data-cscs-value="<?php echo esc_attr( (string) ( $listing->args->week + 1 ) ); ?>" href="<?php echo esc_url( $listing->url( 'week', $listing->args->week + 1 ) ); ?>" rel="next">
				<?php esc_html_e( 'Next week →', 'course-schedule-connector' ); ?>
			</a>
		</nav>
	<?php endif; ?>

	<?php if ( array() !== $listing->rooms ) : ?>
		<nav class="cscs-rooms" aria-label="<?php esc_attr_e( 'Which room', 'course-schedule-connector' ); ?>">
			<a class="cscs-rooms__room<?php echo 0 === $listing->args->room ? ' is-current' : ''; ?>" data-cscs-nav="room" data-cscs-value="0" href="<?php echo esc_url( $listing->url( 'room', 0 ) ); ?>">
				<?php esc_html_e( 'All rooms', 'course-schedule-connector' ); ?>
			</a>

			<?php foreach ( $listing->rooms as $cscs_room_id => $cscs_room_name ) : ?>
				<a class="cscs-rooms__room<?php echo $listing->args->room === $cscs_room_id ? ' is-current' : ''; ?>" data-cscs-nav="room" data-cscs-value="<?php echo esc_attr( (string) $cscs_room_id ); ?>" href="<?php echo esc_url( $listing->url( 'room', (int) $cscs_room_id ) ); ?>">
					<?php echo esc_html( $cscs_room_name ); ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</div>
