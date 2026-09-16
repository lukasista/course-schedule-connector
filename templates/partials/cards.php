<?php
/**
 * A grid of trainer cards: a photograph, a name, each linked to that
 * trainer's own page.
 *
 * A theme may replace this file by copying it to
 * `course-schedule-connector/partials/cards.php`.
 *
 * @package CourseScheduleConnector
 * @var array<int, array{name: string, permalink: string, image: string}> $cscs_cards
 * @var string $cscs_layout 'row' or 'column'.
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

?>
<div class="cscs-cards cscs-cards--<?php echo esc_attr( $cscs_layout ); ?>">
	<?php foreach ( $cscs_cards as $cscs_card ) : ?>
		<div class="cscs-card">
			<?php if ( '' !== $cscs_card['image'] ) : ?>
				<?php if ( '' !== $cscs_card['permalink'] ) : ?>
					<a class="cscs-card__image-link" href="<?php echo esc_url( $cscs_card['permalink'] ); ?>"><?php echo wp_kses_post( $cscs_card['image'] ); ?></a>
				<?php else : ?>
					<?php echo wp_kses_post( $cscs_card['image'] ); ?>
				<?php endif; ?>
			<?php endif; ?>
			<?php if ( '' !== $cscs_card['permalink'] ) : ?>
				<a class="cscs-card__name" href="<?php echo esc_url( $cscs_card['permalink'] ); ?>"><?php echo esc_html( $cscs_card['name'] ); ?></a>
			<?php else : ?>
				<span class="cscs-card__name"><?php echo esc_html( $cscs_card['name'] ); ?></span>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
