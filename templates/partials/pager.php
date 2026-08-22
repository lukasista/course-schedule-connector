<?php
/**
 * Paging under a listing.
 *
 * @package CourseScheduleConnector
 * @var \CSCS\Render\Listing $listing
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

$cscs_pages = $listing->pages();

if ( 2 > $cscs_pages ) {
	return;
}

$cscs_page = $listing->args->page;

?>
<nav class="cscs-pager" aria-label="<?php esc_attr_e( 'Pages', 'course-schedule-connector' ); ?>">
	<?php if ( 1 < $cscs_page ) : ?>
		<a class="cscs-pager__step" data-cscs-nav="page" data-cscs-value="<?php echo esc_attr( (string) ( $cscs_page - 1 ) ); ?>" href="<?php echo esc_url( $listing->url( 'page', $cscs_page - 1 ) ); ?>" rel="prev">
			<?php esc_html_e( '← Back', 'course-schedule-connector' ); ?>
		</a>
	<?php endif; ?>

	<span class="cscs-pager__count">
		<?php
		printf(
			/* translators: 1: current page, 2: number of pages */
			esc_html__( 'Page %1$d of %2$d', 'course-schedule-connector' ),
			(int) $cscs_page,
			(int) $cscs_pages
		);
		?>
	</span>

	<?php if ( $cscs_page < $cscs_pages ) : ?>
		<a class="cscs-pager__step" data-cscs-nav="page" data-cscs-value="<?php echo esc_attr( (string) ( $cscs_page + 1 ) ); ?>" href="<?php echo esc_url( $listing->url( 'page', $cscs_page + 1 ) ); ?>" rel="next">
			<?php esc_html_e( 'Next →', 'course-schedule-connector' ); ?>
		</a>
	<?php endif; ?>
</nav>
