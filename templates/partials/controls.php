<?php
/**
 * The controls above a listing: which week, which room.
 *
 * A form and real links, with the choice in the address. A visitor without
 * JavaScript gets a page that works; one with it gets the same thing without
 * the reload. The order matters — the form is the feature, the script is the
 * polish, and the "Show" button is hidden only once the script has said it will
 * do the work itself.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\Listing $listing
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

if ( ! $listing->has_weeks() && array() === $listing->rooms ) {
	return;
}

$cscs_rooms_id = 'cscs-rooms-' . $listing->set->id;

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
		<form class="cscs-rooms" method="get" action="<?php echo esc_url( $listing->form_action() ); ?>">
			<?php foreach ( $listing->hidden_fields() as $cscs_field => $cscs_value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( (string) $cscs_field ); ?>" value="<?php echo esc_attr( (string) $cscs_value ); ?>" />
			<?php endforeach; ?>

			<label class="cscs-rooms__label" for="<?php echo esc_attr( $cscs_rooms_id ); ?>">
				<?php esc_html_e( 'Room', 'course-schedule-connector' ); ?>
			</label>

			<select class="cscs-rooms__select" id="<?php echo esc_attr( $cscs_rooms_id ); ?>" name="cscs_room" data-cscs-nav="room">
				<option value="0"><?php esc_html_e( 'All rooms', 'course-schedule-connector' ); ?></option>

				<?php foreach ( $listing->rooms as $cscs_room_id => $cscs_room_name ) : ?>
					<option value="<?php echo esc_attr( (string) $cscs_room_id ); ?>" <?php selected( $listing->args->room, (int) $cscs_room_id ); ?>>
						<?php echo esc_html( $cscs_room_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<button class="cscs-rooms__go" type="submit"><?php esc_html_e( 'Show', 'course-schedule-connector' ); ?></button>
		</form>
	<?php endif; ?>
</div>
