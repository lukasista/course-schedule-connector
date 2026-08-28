<?php
/**
 * A course's own page.
 *
 * A theme may replace this by copying it to
 * `course-schedule-connector/single-course.php`. Everything is decided before
 * this runs: `$detail` holds the facts already worded, the contact, the button
 * and the two listings.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\CourseDetail $detail
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$cscs_course   = $detail->course();
$cscs_contact  = $detail->contact();
$cscs_schedule = $detail->schedule();
$cscs_makeup   = $detail->makeup();
$cscs_button   = $detail->button();

?>
<article class="cscs cscs-course" id="course-<?php echo esc_attr( (string) $cscs_course['post_id'] ); ?>">
	<header class="cscs-course__header">
		<h1 class="cscs-course__title"><?php echo esc_html( (string) $cscs_course['name'] ); ?></h1>

		<?php if ( has_post_thumbnail( (int) $cscs_course['post_id'] ) ) : ?>
			<div class="cscs-course__image">
				<?php echo get_the_post_thumbnail( (int) $cscs_course['post_id'], 'large' ); ?>
			</div>
		<?php endif; ?>
	</header>

	<?php if ( '' !== trim( (string) get_post_field( 'post_content', (int) $cscs_course['post_id'] ) ) ) : ?>
		<div class="cscs-course__text">
			<?php echo wp_kses_post( apply_filters( 'the_content', get_post_field( 'post_content', (int) $cscs_course['post_id'] ) ) ); ?>
		</div>
	<?php elseif ( '' !== $detail->api_description() ) : ?>
		<?php // Nobody has written anything here, and the booking system has: a page with the description it holds beats a page with nothing on it. Anything written in WordPress wins the moment it exists. ?>
		<div class="cscs-course__text cscs-course__text--api">
			<?php echo wp_kses_post( $detail->api_description() ); ?>
		</div>
	<?php endif; ?>

	<?php $cscs_facts = $detail->facts_html(); ?>
	<?php if ( array() !== $cscs_facts ) : ?>
		<table class="cscs-table cscs-course__facts">
			<tbody>
				<?php foreach ( $cscs_facts as $cscs_label => $cscs_value ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $cscs_label ); ?></th>
						<td data-label="<?php echo esc_attr( (string) $cscs_label ); ?>"><?php echo wp_kses( $cscs_value, array( 'a' => array( 'href' => array(), 'class' => array() ), 'br' => array() ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( '' !== $cscs_button ) : ?>
		<p class="cscs-course__booking"><?php echo wp_kses_post( $cscs_button ); ?></p>
	<?php endif; ?>

	<?php if ( null !== $cscs_contact ) : ?>
		<section class="cscs-course__contact">
			<h2><?php esc_html_e( 'Who to ask', 'course-schedule-connector' ); ?></h2>

			<?php if ( '' !== $cscs_contact['name'] ) : ?>
				<p class="cscs-course__contact-name"><?php echo esc_html( $cscs_contact['name'] ); ?></p>
			<?php endif; ?>

			<ul class="cscs-course__contact-list">
				<?php if ( '' !== $cscs_contact['email'] ) : ?>
					<li><a href="<?php echo esc_url( 'mailto:' . $cscs_contact['email'] ); ?>"><?php echo esc_html( $cscs_contact['email'] ); ?></a></li>
				<?php endif; ?>
				<?php if ( '' !== $cscs_contact['phone'] ) : ?>
					<li><a href="<?php echo esc_url( 'tel:' . preg_replace( '/\s+/', '', $cscs_contact['phone'] ) ); ?>"><?php echo esc_html( $cscs_contact['phone'] ); ?></a></li>
				<?php endif; ?>
			</ul>

			<?php if ( '' !== $cscs_contact['note'] ) : ?>
				<p class="cscs-course__contact-note"><?php echo esc_html( $cscs_contact['note'] ); ?></p>
			<?php endif; ?>
		</section>
	<?php endif; ?>

	<?php if ( null !== $cscs_schedule ) : ?>
		<section class="cscs-course__schedule">
			<h2><?php esc_html_e( 'Upcoming classes', 'course-schedule-connector' ); ?></h2>
			<?php
			$listing = $cscs_schedule;
			require \CSCS\Render\Renderer::locate( 'partials/table' );
			?>
		</section>
	<?php endif; ?>

	<?php if ( null !== $cscs_makeup ) : ?>
		<section class="cscs-course__makeup">
			<h2><?php esc_html_e( 'Make-up classes for this course', 'course-schedule-connector' ); ?></h2>
			<p class="description"><?php esc_html_e( 'A class you can attend instead of one you missed.', 'course-schedule-connector' ); ?></p>
			<?php
			$listing = $cscs_makeup;
			require \CSCS\Render\Renderer::locate( 'partials/table' );
			?>
		</section>
	<?php endif; ?>
</article>
