<?php
/**
 * One listing table.
 *
 * Shared by every listing the plugin renders, which is what keeps the fold —
 * the point below which a table becomes one card per row, headings down the
 * left — the same everywhere. A theme may replace this file by copying it to
 * `course-schedule-connector/partials/table.php`.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\Listing $listing
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

?>
<table class="cscs-table">
	<?php
	// A table with no name is, to somebody listening to the page, "table, five
	// columns" and nothing else — and a page may carry three of them. The
	// heading above it is its name where there is one; where there is none, and
	// on a kind's page or a trainer's there is none, saying how many rows it
	// has at least tells the two tables apart.
	$cscs_caption = trim( $listing->heading() );
	$cscs_caption = '' === $cscs_caption ? $listing->spoken() : $cscs_caption;
	?>
	<caption class="cscs-visually-hidden"><?php echo esc_html( $cscs_caption ); ?></caption>
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
