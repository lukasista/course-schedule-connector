<?php
/**
 * Rooms screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin\Screen;

use CSCS\Admin\Capabilities;
use CSCS\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Where the rooms get the names the website should use.
 *
 * iSport names a room for the people running it — "Gymnastická hala 1 a
 * veřejnost" — which is exactly right there and unreadable in a table column
 * on a phone. Renaming it in iSport would disturb the gym's own work, so the
 * site keeps its own label beside the remote one, along with an order, a colour
 * and the option of not showing a room at all.
 *
 * The rooms listed here are the ones the stored timetable mentions. There is no
 * endpoint that lists rooms, and inventing one from a stale list would show
 * rooms that no longer exist.
 */
final class RoomsPage {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'cscs-rooms';

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin instance.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Renders the screen.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! Capabilities::can_manage_content() ) {
			wp_die( esc_html__( 'You are not allowed to view this page.', 'course-schedule-connector' ) );
		}

		$notice = $this->handle_actions();
		$rooms  = $this->plugin->rooms()->decorate( $this->plugin->lessons()->rooms() );

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Rooms', 'course-schedule-connector' ); ?></h1>

			<p class="description" style="max-width:52em">
				<?php esc_html_e( 'iSport names a room for the people who run the timetable. The website can call it something shorter without anything changing in iSport. The rooms listed are the ones the stored timetable actually uses, so a room appears here once something has been booked in it.', 'course-schedule-connector' ); ?>
			</p>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 'cscs_rooms' ); ?>

				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'In iSport', 'course-schedule-connector' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Name on the website', 'course-schedule-connector' ); ?></th>
							<th scope="col" style="width:7em"><?php esc_html_e( 'Order', 'course-schedule-connector' ); ?></th>
							<th scope="col" style="width:9em"><?php esc_html_e( 'Colour', 'course-schedule-connector' ); ?></th>
							<th scope="col" style="width:8em"><?php esc_html_e( 'Hidden', 'course-schedule-connector' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( array() === $rooms ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No rooms yet, because no timetable is stored. Synchronise first.', 'course-schedule-connector' ); ?></td></tr>
						<?php endif; ?>

						<?php foreach ( $rooms as $room ) : ?>
							<tr>
								<td>
									<?php echo esc_html( $room['remote'] ); ?>
									<br /><span class="description">#<?php echo esc_html( (string) $room['id'] ); ?></span>
								</td>
								<td>
									<label class="screen-reader-text" for="cscs-room-<?php echo esc_attr( (string) $room['id'] ); ?>"><?php esc_html_e( 'Name on the website', 'course-schedule-connector' ); ?></label>
									<input type="text" class="regular-text" id="cscs-room-<?php echo esc_attr( (string) $room['id'] ); ?>" name="cscs_rooms[<?php echo esc_attr( (string) $room['id'] ); ?>][label]" value="<?php echo esc_attr( $room['name'] === $room['remote'] ? '' : $room['name'] ); ?>" placeholder="<?php echo esc_attr( $room['remote'] ); ?>" />
								</td>
								<td>
									<input type="number" min="0" max="999" step="1" class="small-text" name="cscs_rooms[<?php echo esc_attr( (string) $room['id'] ); ?>][order]" value="<?php echo esc_attr( (string) $room['order'] ); ?>" />
								</td>
								<td>
									<input type="color" name="cscs_rooms[<?php echo esc_attr( (string) $room['id'] ); ?>][colour]" value="<?php echo esc_attr( self::picker_value( $room['colour'] ) ); ?>" />
									<label>
										<input type="checkbox" name="cscs_rooms[<?php echo esc_attr( (string) $room['id'] ); ?>][use_colour]" value="1" <?php checked( '' !== $room['colour'] ); ?> />
										<?php esc_html_e( 'Use', 'course-schedule-connector' ); ?>
									</label>
								</td>
								<td>
									<label>
										<input type="checkbox" name="cscs_rooms[<?php echo esc_attr( (string) $room['id'] ); ?>][hidden]" value="1" <?php checked( $room['hidden'] ); ?> />
										<?php esc_html_e( 'Hide', 'course-schedule-connector' ); ?>
									</label>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p>
					<button type="submit" name="cscs_action" value="save" class="button button-primary">
						<?php esc_html_e( 'Save rooms', 'course-schedule-connector' ); ?>
					</button>
				</p>
				<p class="description" style="max-width:52em">
					<?php esc_html_e( 'An empty name uses the one from iSport. Rooms with the same order are listed alphabetically, so leaving every order at nought is a perfectly good answer. A colour is used only when "Use" is ticked; a hidden room is left out of every listing but keeps its classes counted.', 'course-schedule-connector' ); ?>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Renders a stored colour the way a colour input needs it.
	 *
	 * Colours are stored as the API writes them — six characters, no hash — so
	 * that nothing further down has to tell the two apart. The picker insists
	 * on `#rrggbb`, and refuses anything shorter by falling back to black,
	 * which would look like a decision somebody made.
	 *
	 * @param string $colour Stored colour.
	 * @return string
	 */
	private static function picker_value( string $colour ): string {
		if ( 3 === strlen( $colour ) ) {
			$colour = $colour[0] . $colour[0] . $colour[1] . $colour[1] . $colour[2] . $colour[2];
		}

		return 6 === strlen( $colour ) ? '#' . $colour : '#ffffff';
	}

	/**
	 * Saves whatever was submitted.
	 *
	 * @return string Message to show.
	 */
	private function handle_actions(): string {
		if ( ! isset( $_POST['cscs_action'] ) ) {
			return '';
		}

		check_admin_referer( 'cscs_rooms' );

		if ( ! Capabilities::can_manage_content() ) {
			return '';
		}

		$submitted = isset( $_POST['cscs_rooms'] ) && is_array( $_POST['cscs_rooms'] )
			? wp_unslash( $_POST['cscs_rooms'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is normalised by RoomMap, which is where the rules for one live.
			: array();

		$rows = array();

		foreach ( $submitted as $id => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			// The colour picker always submits something, so an unticked "Use"
			// is the only way to say "no colour of our own".
			if ( empty( $row['use_colour'] ) ) {
				$row['colour'] = '';
			}

			$rows[ (int) $id ] = $row;
		}

		$configured = $this->plugin->rooms()->save( $rows );

		return sprintf(
			/* translators: %d: number of rooms */
			_n( '%d room configured.', '%d rooms configured.', $configured, 'course-schedule-connector' ),
			$configured
		);
	}
}
