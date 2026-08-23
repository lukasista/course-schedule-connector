<?php
/**
 * A trainer's own page.
 *
 * A theme may replace this by copying it to
 * `course-schedule-connector/single-trainer.php`. Everything is decided before
 * this runs: `$detail` holds the photograph, the lists, the words and the
 * courses this trainer runs.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\TrainerDetail $detail
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$cscs_trainer        = $detail->post();
$cscs_photo          = $detail->photograph_html();
$cscs_motto          = $detail->motto();
$cscs_fact           = $detail->fact();
$cscs_qualifications = $detail->qualifications();
$cscs_hobbies        = $detail->hobbies();
$cscs_courses        = $detail->course_listing();

?>
<article class="cscs cscs-trainer" id="trainer-<?php echo esc_attr( (string) $cscs_trainer->ID ); ?>">
	<header class="cscs-trainer__header">
		<h1 class="cscs-trainer__title"><?php echo esc_html( $detail->name() ); ?></h1>

		<?php if ( '' !== $cscs_photo ) : ?>
			<div class="cscs-trainer__photo">
				<?php echo wp_kses_post( $cscs_photo ); ?>
			</div>
		<?php endif; ?>

		<?php if ( '' !== $cscs_motto ) : ?>
			<p class="cscs-trainer__motto"><?php echo esc_html( $cscs_motto ); ?></p>
		<?php endif; ?>
	</header>

	<?php if ( '' !== trim( (string) get_post_field( 'post_content', $cscs_trainer->ID ) ) ) : ?>
		<div class="cscs-trainer__text">
			<?php echo wp_kses_post( apply_filters( 'the_content', get_post_field( 'post_content', $cscs_trainer->ID ) ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $cscs_fact ) : ?>
		<section class="cscs-trainer__fact">
			<h2><?php esc_html_e( 'Something worth knowing', 'course-schedule-connector' ); ?></h2>
			<p><?php echo nl2br( esc_html( $cscs_fact ) ); ?></p>
		</section>
	<?php endif; ?>

	<?php if ( array() !== $cscs_qualifications ) : ?>
		<section class="cscs-trainer__qualifications">
			<h2><?php esc_html_e( 'Qualifications', 'course-schedule-connector' ); ?></h2>
			<ul class="cscs-list">
				<?php foreach ( $cscs_qualifications as $cscs_line ) : ?>
					<li><?php echo esc_html( $cscs_line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( array() !== $cscs_hobbies ) : ?>
		<section class="cscs-trainer__hobbies">
			<h2><?php esc_html_e( 'Interests', 'course-schedule-connector' ); ?></h2>
			<ul class="cscs-list">
				<?php foreach ( $cscs_hobbies as $cscs_line ) : ?>
					<li><?php echo esc_html( $cscs_line ); ?></li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<?php if ( null !== $cscs_courses ) : ?>
		<section class="cscs-trainer__courses">
			<h2><?php esc_html_e( 'Courses this trainer runs', 'course-schedule-connector' ); ?></h2>
			<?php
			$listing = $cscs_courses;
			require \CSCS\Render\Renderer::locate( 'partials/table' );
			?>
		</section>
	<?php endif; ?>
</article>
