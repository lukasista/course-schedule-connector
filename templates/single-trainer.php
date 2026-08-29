<?php
/**
 * A trainer's own page.
 *
 * A theme may replace this by copying it to
 * `course-schedule-connector/single-trainer.php`. Everything is decided before
 * this runs: `$cscs_detail` holds the photograph, the lists, the words and the
 * courses this trainer runs.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\TrainerDetail $cscs_detail
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$cscs_trainer        = $cscs_detail->post();
$cscs_photo          = $cscs_detail->photograph_html();
$cscs_motto          = $cscs_detail->motto();
$cscs_fact           = $cscs_detail->fact();
$cscs_qualifications = $cscs_detail->qualifications();
$cscs_hobbies        = $cscs_detail->hobbies();
$cscs_courses        = $cscs_detail->course_listing();

?>
<article class="cscs cscs-trainer" id="trainer-<?php echo esc_attr( (string) $cscs_trainer->ID ); ?>">
	<header class="cscs-trainer__header">
		<h1 class="cscs-trainer__title"><?php echo esc_html( $cscs_detail->name() ); ?></h1>

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
			<?php // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, applied rather than invented: the words on this page should go through shortcodes, embeds and wpautop exactly as they would in a theme. ?>
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
			$cscs_listing = $cscs_courses;
			require \CSCS\Render\Renderer::locate( 'partials/table' );
			?>
		</section>
	<?php endif; ?>
</article>
