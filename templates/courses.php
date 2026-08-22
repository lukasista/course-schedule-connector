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
<div class="cscs cscs--courses">
	<?php if ( '' !== $listing->heading() ) : ?>
		<h2 class="cscs-heading"><?php echo esc_html( $listing->heading() ); ?></h2>
	<?php endif; ?>

	<?php if ( $listing->is_empty() ) : ?>
		<p class="cscs-empty"><?php echo esc_html( $listing->empty_text() ); ?></p>
	<?php else : ?>
		<table class="cscs-table">
			<thead>
				<tr>
					<?php foreach ( $listing->columns as $column => $label ) : ?>
						<th scope="col" class="cscs-col-<?php echo esc_attr( $column ); ?>"><?php echo esc_html( $label ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $listing->rows as $row ) : ?>
					<tr class="<?php echo esc_attr( $listing->row_class( $row ) ); ?>">
						<?php foreach ( $listing->columns as $column => $label ) : ?>
							<?php $cell = $listing->cell( $row, $column ); ?>
							<td class="cscs-col-<?php echo esc_attr( $column ); ?>" data-label="<?php echo esc_attr( $label ); ?>">
								<?php echo wp_kses_post( $cell['html'] ); ?>
							</td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
