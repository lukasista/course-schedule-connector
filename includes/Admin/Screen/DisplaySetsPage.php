<?php
/**
 * Display sets screen.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin\Screen;

use CSCS\Admin\Capabilities;
use CSCS\Data\DisplaySet;
use CSCS\Data\PostType;
use CSCS\Plugin;
use CSCS\Render\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Where a site manager decides what a listing shows.
 *
 * This screen is the whole point of the arrangement the site owner asked for.
 * A page builder module offers one field — which set — so changing what a page
 * lists never means opening the builder, and the design stays where the design
 * belongs. Everything that decides content lives here, and nothing that decides
 * appearance does.
 *
 * A set's id never changes once it exists, because a shortcode somewhere on the
 * site is written in terms of it and renaming a set must not silently empty a
 * page.
 */
final class DisplaySetsPage {

	/**
	 * Menu slug.
	 */
	public const SLUG = 'cscs-sets';

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

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Choosing which set to look at changes nothing.
		$editing = isset( $_GET['set'] ) ? sanitize_key( wp_unslash( $_GET['set'] ) ) : '';
		$sets    = $this->plugin->sets()->all();

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Display sets', 'course-schedule-connector' ); ?></h1>

			<p class="description" style="max-width:52em">
				<?php esc_html_e( 'A display set is a named answer to "what should this listing show": which columns, in what order, which rooms and trainers, how far ahead, what to say when there is nothing. Pages refer to the set by name, so changing the set changes every page that uses it — without opening the page builder, and without touching the design.', 'course-schedule-connector' ); ?>
			</p>

			<?php if ( '' !== $notice ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
			<?php endif; ?>

			<?php $this->render_list( $sets, $editing ); ?>

			<?php if ( isset( $sets[ $editing ] ) ) : ?>
				<?php $this->render_form( $sets[ $editing ] ); ?>
			<?php else : ?>
				<?php $this->render_form( null ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Renders the list of sets.
	 *
	 * @param array<string, DisplaySet> $sets    Existing sets.
	 * @param string                    $editing Id being edited.
	 * @return void
	 */
	private function render_list( array $sets, string $editing ): void {
		?>
		<h2><?php esc_html_e( 'Sets', 'course-schedule-connector' ); ?></h2>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Name', 'course-schedule-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shows', 'course-schedule-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Shortcode', 'course-schedule-connector' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Columns', 'course-schedule-connector' ); ?></th>
					<th scope="col"></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( array() === $sets ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'No sets yet. The form below makes the first one.', 'course-schedule-connector' ); ?></td></tr>
				<?php endif; ?>

				<?php foreach ( $sets as $set ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $set->name ); ?></strong>
							<?php if ( $set->id === $editing ) : ?>
								<span class="description"><?php esc_html_e( '— being edited', 'course-schedule-connector' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							echo esc_html(
								DisplaySet::TYPE_SCHEDULE === $set->type
									? __( 'Classes', 'course-schedule-connector' )
									: __( 'Courses', 'course-schedule-connector' )
							);
							?>
						</td>
						<td>
							<code>[<?php echo esc_html( DisplaySet::TYPE_SCHEDULE === $set->type ? 'cscs_schedule' : 'cscs_courses' ); ?> set="<?php echo esc_html( $set->id ); ?>"]</code>
						</td>
						<td><?php echo esc_html( (string) count( $set->columns ) ); ?></td>
						<td>
							<a href="<?php echo esc_url( $this->url( $set->id ) ); ?>"><?php esc_html_e( 'Edit', 'course-schedule-connector' ); ?></a>
							|
							<a href="<?php echo esc_url( wp_nonce_url( $this->url( '', array( 'delete' => $set->id ) ), 'cscs_delete_set_' . $set->id ) ); ?>"><?php esc_html_e( 'Delete', 'course-schedule-connector' ); ?></a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Renders the form for one set, or for a new one.
	 *
	 * @param DisplaySet|null $set Set being edited.
	 * @return void
	 */
	private function render_form( ?DisplaySet $set ): void {
		$set  = $set ?? DisplaySet::from_array( array() );
		$new  = '' === $set->id;
		$type = $set->type;

		?>
		<h2><?php echo esc_html( $new ? __( 'New set', 'course-schedule-connector' ) : __( 'Edit set', 'course-schedule-connector' ) ); ?></h2>

		<form method="post">
			<?php wp_nonce_field( 'cscs_sets' ); ?>
			<input type="hidden" name="cscs_set[id]" value="<?php echo esc_attr( $set->id ); ?>" />

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cscs-set-name"><?php esc_html_e( 'Name', 'course-schedule-connector' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="cscs-set-name" name="cscs_set[name]" value="<?php echo esc_attr( $set->name ); ?>" required="required" />
						<p class="description">
							<?php
							echo esc_html(
								$new
									? __( 'For example "Children\'s courses — home page". The shortcode name is made from this and never changes afterwards.', 'course-schedule-connector' )
									: sprintf(
										/* translators: %s: set id */
										__( 'Referred to as "%s", which stays as it is so that pages using it keep working.', 'course-schedule-connector' ),
										$set->id
									)
							);
							?>
						</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cscs-set-type"><?php esc_html_e( 'Shows', 'course-schedule-connector' ); ?></label></th>
					<td>
						<select id="cscs-set-type" name="cscs_set[type]">
							<option value="<?php echo esc_attr( DisplaySet::TYPE_COURSES ); ?>" <?php selected( $type, DisplaySet::TYPE_COURSES ); ?>><?php esc_html_e( 'Courses', 'course-schedule-connector' ); ?></option>
							<option value="<?php echo esc_attr( DisplaySet::TYPE_SCHEDULE ); ?>" <?php selected( $type, DisplaySet::TYPE_SCHEDULE ); ?>><?php esc_html_e( 'Classes', 'course-schedule-connector' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Courses are what people sign up to; classes are the individual times in the timetable. Changing this resets the columns, since the two have different ones.', 'course-schedule-connector' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Columns', 'course-schedule-connector' ); ?></h3>
			<p class="description" style="max-width:52em">
				<?php esc_html_e( 'Tick what to show and number the order. A column left unticked is not rendered at all; a label left empty uses the built-in one. On a narrow screen every table folds so that these headings run down the left and the values down the right, which is why a short label is worth the trouble.', 'course-schedule-connector' ); ?>
			</p>

			<table class="widefat striped" style="max-width:52em">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Column', 'course-schedule-connector' ); ?></th>
						<th scope="col" style="width:6em"><?php esc_html_e( 'Order', 'course-schedule-connector' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Label', 'course-schedule-connector' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( self::column_labels( $type ) as $column => $label ) : ?>
						<?php $position = array_search( $column, $set->columns, true ); ?>
						<tr>
							<td>
								<label>
									<input type="checkbox" name="cscs_set[show][<?php echo esc_attr( $column ); ?>]" value="1" <?php checked( false !== $position ); ?> />
									<?php echo esc_html( $label ); ?>
								</label>
							</td>
							<td>
								<input type="number" min="1" max="99" step="1" class="small-text" name="cscs_set[position][<?php echo esc_attr( $column ); ?>]" value="<?php echo esc_attr( (string) ( false === $position ? 99 : $position + 1 ) ); ?>" />
							</td>
							<td>
								<input type="text" class="regular-text" name="cscs_set[labels][<?php echo esc_attr( $column ); ?>]" value="<?php echo esc_attr( $set->labels[ $column ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $label ); ?>" />
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h3><?php esc_html_e( 'What to include', 'course-schedule-connector' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				$this->rooms_row( $set->rooms );
				$this->term_row( 'trainers', __( 'Trainers', 'course-schedule-connector' ), PostType::TRAINER, $set->trainers, __( 'Nothing ticked means every trainer.', 'course-schedule-connector' ) );
				$this->term_row( 'activities', __( 'Activities', 'course-schedule-connector' ), PostType::ACTIVITY, $set->activities, __( 'Nothing ticked means every activity.', 'course-schedule-connector' ) );
				?>
				<tr>
					<th scope="row"><label for="cscs-set-range"><?php esc_html_e( 'How far ahead', 'course-schedule-connector' ); ?></label></th>
					<td>
						<select id="cscs-set-range" name="cscs_set[range]">
							<option value="term" <?php selected( $set->range, 'term' ); ?>><?php esc_html_e( 'The whole term', 'course-schedule-connector' ); ?></option>
							<option value="week" <?php selected( $set->range, 'week' ); ?>><?php esc_html_e( 'This week', 'course-schedule-connector' ); ?></option>
							<option value="days" <?php selected( $set->range, 'days' ); ?>><?php esc_html_e( 'The next few days', 'course-schedule-connector' ); ?></option>
							<option value="custom" <?php selected( $set->range, 'custom' ); ?>><?php esc_html_e( 'Between two dates', 'course-schedule-connector' ); ?></option>
						</select>
						<label>
							<?php esc_html_e( 'Days:', 'course-schedule-connector' ); ?>
							<input type="number" min="1" max="366" step="1" class="small-text" name="cscs_set[range_days]" value="<?php echo esc_attr( (string) $set->range_days ); ?>" />
						</label>
						<label>
							<?php esc_html_e( 'From:', 'course-schedule-connector' ); ?>
							<input type="date" name="cscs_set[date_from]" value="<?php echo esc_attr( $set->date_from ); ?>" />
						</label>
						<label>
							<?php esc_html_e( 'To:', 'course-schedule-connector' ); ?>
							<input type="date" name="cscs_set[date_to]" value="<?php echo esc_attr( $set->date_to ); ?>" />
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rentals and outside clubs', 'course-schedule-connector' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cscs_set[include_rentals]" value="1" <?php checked( $set->include_rentals ); ?> />
							<?php esc_html_e( 'Include them', 'course-schedule-connector' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'A hall rented to somebody else occupies the room but is not a course of yours. On a full timetable of hall occupancy that matters; on the home page it usually does not.', 'course-schedule-connector' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cscs-set-cancelled"><?php esc_html_e( 'Cancelled classes', 'course-schedule-connector' ); ?></label></th>
					<td>
						<select id="cscs-set-cancelled" name="cscs_set[cancelled]">
							<option value="inherit" <?php selected( $set->cancelled, 'inherit' ); ?>><?php esc_html_e( 'As the settings say', 'course-schedule-connector' ); ?></option>
							<option value="show" <?php selected( $set->cancelled, 'show' ); ?>><?php esc_html_e( 'Show them, struck through', 'course-schedule-connector' ); ?></option>
							<option value="hide" <?php selected( $set->cancelled, 'hide' ); ?>><?php esc_html_e( 'Hide them', 'course-schedule-connector' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Courses with no places left', 'course-schedule-connector' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="cscs_set[only_available]" value="1" <?php checked( $set->only_available ); ?> />
							<?php esc_html_e( 'Leave them out', 'course-schedule-connector' ); ?>
						</label>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Order and size', 'course-schedule-connector' ); ?></h3>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="cscs-set-sort"><?php esc_html_e( 'Sort by', 'course-schedule-connector' ); ?></label></th>
					<td>
						<select id="cscs-set-sort" name="cscs_set[sort]">
							<?php foreach ( self::sort_labels( $type ) as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $set->sort, $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<select name="cscs_set[order]">
							<option value="asc" <?php selected( $set->order, 'asc' ); ?>><?php esc_html_e( 'Ascending', 'course-schedule-connector' ); ?></option>
							<option value="desc" <?php selected( $set->order, 'desc' ); ?>><?php esc_html_e( 'Descending', 'course-schedule-connector' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cscs-set-per-page"><?php esc_html_e( 'Items per page', 'course-schedule-connector' ); ?></label></th>
					<td>
						<input type="number" min="0" max="500" step="1" class="small-text" id="cscs-set-per-page" name="cscs_set[per_page]" value="<?php echo esc_attr( (string) $set->per_page ); ?>" />
						<p class="description"><?php esc_html_e( 'Zero shows everything on one page.', 'course-schedule-connector' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cscs-set-few"><?php esc_html_e( 'Few places left at', 'course-schedule-connector' ); ?></label></th>
					<td>
						<input type="number" min="0" max="99" step="1" class="small-text" id="cscs-set-few" name="cscs_set[few_places]" value="<?php echo esc_attr( (string) $set->few_places ); ?>" />
						<p class="description"><?php esc_html_e( 'At or below this many free places the listing says so. Zero switches the warning off.', 'course-schedule-connector' ); ?></p>
					</td>
				</tr>
			</table>

			<h3><?php esc_html_e( 'Wording', 'course-schedule-connector' ); ?></h3>
			<table class="form-table" role="presentation">
				<?php
				$this->text_row( 'heading', __( 'Heading', 'course-schedule-connector' ), $set->heading, __( 'Left empty, no heading is printed.', 'course-schedule-connector' ) );
				$this->text_row( 'cta', __( 'Button text', 'course-schedule-connector' ), $set->cta, __( 'For example "Sign up in iSport".', 'course-schedule-connector' ) );
				$this->text_row( 'empty_text', __( 'When there is nothing to show', 'course-schedule-connector' ), $set->empty_text );
				$this->text_row( 'full_text', __( 'When a course is full', 'course-schedule-connector' ), $set->full_text );
				?>
			</table>

			<p>
				<button type="submit" name="cscs_action" value="save" class="button button-primary">
					<?php echo esc_html( $new ? __( 'Create set', 'course-schedule-connector' ) : __( 'Save set', 'course-schedule-connector' ) ); ?>
				</button>
				<?php if ( ! $new ) : ?>
					<a class="button" href="<?php echo esc_url( $this->url( '' ) ); ?>"><?php esc_html_e( 'Start a new one', 'course-schedule-connector' ); ?></a>
				<?php endif; ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Renders the rooms, under the names the site chose for them.
	 *
	 * Rooms are picked by the id iSport gives them rather than by a taxonomy
	 * term, because that id is what a class carries and what the course record
	 * stores. A room renamed on the Rooms screen, or in iSport, is still the
	 * same room to a set that was configured before the rename.
	 *
	 * @param array<int, int> $selected Selected room ids.
	 * @return void
	 */
	private function rooms_row( array $selected ): void {
		$rooms = $this->plugin->rooms()->decorate( $this->plugin->lessons()->rooms() );

		?>
		<tr>
			<th scope="row"><?php esc_html_e( 'Rooms', 'course-schedule-connector' ); ?></th>
			<td>
				<?php if ( array() === $rooms ) : ?>
					<p class="description"><?php esc_html_e( 'Nothing to choose from yet. Synchronise first.', 'course-schedule-connector' ); ?></p>
				<?php else : ?>
					<fieldset style="max-height:14em;overflow:auto;border:1px solid #dcdcde;padding:.5em">
						<?php foreach ( $rooms as $room ) : ?>
							<label style="display:block">
								<input type="checkbox" name="cscs_set[rooms][]" value="<?php echo esc_attr( (string) $room['id'] ); ?>" <?php checked( in_array( $room['id'], $selected, true ) ); ?> />
								<?php echo esc_html( $room['name'] ); ?>
								<?php if ( $room['hidden'] ) : ?>
									<span class="description"><?php esc_html_e( '— hidden everywhere', 'course-schedule-connector' ); ?></span>
								<?php endif; ?>
							</label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'Nothing ticked means every room.', 'course-schedule-connector' ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders a row of taxonomy checkboxes.
	 *
	 * @param string          $field       Field name.
	 * @param string          $label       Row label.
	 * @param string          $taxonomy    Taxonomy name.
	 * @param array<int, int> $selected    Selected term ids.
	 * @param string          $description Help text.
	 * @return void
	 */
	private function term_row( string $field, string $label, string $taxonomy, array $selected, string $description ): void {
		$terms = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			)
		);

		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<?php if ( ! is_array( $terms ) || array() === $terms ) : ?>
					<p class="description"><?php esc_html_e( 'Nothing to choose from yet. Synchronise first.', 'course-schedule-connector' ); ?></p>
				<?php else : ?>
					<fieldset style="max-height:14em;overflow:auto;border:1px solid #dcdcde;padding:.5em">
						<?php foreach ( $terms as $term ) : ?>
							<?php if ( $term instanceof \WP_Term ) : ?>
								<label style="display:block">
									<input type="checkbox" name="cscs_set[<?php echo esc_attr( $field ); ?>][]" value="<?php echo esc_attr( (string) $term->term_id ); ?>" <?php checked( in_array( $term->term_id, $selected, true ) ); ?> />
									<?php echo esc_html( $term->name ); ?>
								</label>
							<?php endif; ?>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Renders a text field row.
	 *
	 * @param string $field       Field name.
	 * @param string $label       Row label.
	 * @param string $value       Current value.
	 * @param string $description Help text.
	 * @return void
	 */
	private function text_row( string $field, string $label, string $value, string $description = '' ): void {
		?>
		<tr>
			<th scope="row"><label for="cscs-set-<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" class="regular-text" id="cscs-set-<?php echo esc_attr( $field ); ?>" name="cscs_set[<?php echo esc_attr( $field ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
				<?php if ( '' !== $description ) : ?>
					<p class="description"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns what to call each column.
	 *
	 * @param string $type Listing type.
	 * @return array<string, string>
	 */
	public static function column_labels( string $type ): array {
		$labels = Fields::column_labels();

		$ordered = array();

		foreach ( DisplaySet::catalogue( $type ) as $column ) {
			$ordered[ $column ] = $labels[ $column ] ?? $column;
		}

		return $ordered;
	}

	/**
	 * Returns what to call each sort key.
	 *
	 * @param string $type Listing type.
	 * @return array<string, string>
	 */
	private static function sort_labels( string $type ): array {
		$labels = array(
			'name'   => __( 'Name', 'course-schedule-connector' ),
			'start'  => __( 'When it starts', 'course-schedule-connector' ),
			'price'  => __( 'Price', 'course-schedule-connector' ),
			'places' => __( 'Places left', 'course-schedule-connector' ),
			'room'   => __( 'Room', 'course-schedule-connector' ),
		);

		$ordered = array();

		foreach ( array_keys( DisplaySet::sorts( $type ) ) as $key ) {
			$ordered[ $key ] = $labels[ $key ] ?? $key;
		}

		return $ordered;
	}

	/**
	 * Builds a link to this screen.
	 *
	 * @param string               $set   Set to edit, or empty for a new one.
	 * @param array<string, string> $extra Extra query arguments.
	 * @return string
	 */
	private function url( string $set, array $extra = array() ): string {
		$args = array( 'page' => self::SLUG );

		if ( '' !== $set ) {
			$args['set'] = $set;
		}

		return add_query_arg( array_merge( $args, $extra ), admin_url( 'admin.php' ) );
	}

	/**
	 * Saves or deletes whatever was submitted.
	 *
	 * @return string Message to show.
	 */
	private function handle_actions(): string {
		$deleted = $this->handle_delete();

		if ( '' !== $deleted ) {
			return $deleted;
		}

		if ( ! isset( $_POST['cscs_action'] ) || ! isset( $_POST['cscs_set'] ) || ! is_array( $_POST['cscs_set'] ) ) {
			return '';
		}

		check_admin_referer( 'cscs_sets' );

		if ( ! Capabilities::can_manage_content() ) {
			return '';
		}

		$submitted = wp_unslash( $_POST['cscs_set'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Every value is normalised by DisplaySet, which is where the rules for one live.
		$new       = '' === (string) ( $submitted['id'] ?? '' );

		$submitted['columns'] = $this->columns_from_form( $submitted );

		$set = DisplaySet::from_array( $submitted );
		$id  = $this->plugin->sets()->save( $set );

		if ( '' === $id ) {
			return __( 'A set needs a name.', 'course-schedule-connector' );
		}

		return $new
			? sprintf(
				/* translators: %s: set id */
				__( 'Set created. Refer to it as "%s".', 'course-schedule-connector' ),
				$id
			)
			: __( 'Set saved.', 'course-schedule-connector' );
	}

	/**
	 * Removes a set when one was asked to be removed.
	 *
	 * @return string Message to show, empty when nothing was deleted.
	 */
	private function handle_delete(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The nonce is checked immediately below, once there is something to check it for.
		$id = isset( $_GET['delete'] ) ? sanitize_key( wp_unslash( $_GET['delete'] ) ) : '';

		if ( '' === $id ) {
			return '';
		}

		check_admin_referer( 'cscs_delete_set_' . $id );

		if ( ! Capabilities::can_manage_content() || ! $this->plugin->sets()->delete( $id ) ) {
			return '';
		}

		return __( 'Set deleted. Any page still referring to it now shows nothing.', 'course-schedule-connector' );
	}

	/**
	 * Turns the ticks and the order numbers into a list of columns.
	 *
	 * The form asks for a position on every row, including the unticked ones,
	 * so that ticking a column later keeps the place somebody meant it to have.
	 *
	 * @param array<string, mixed> $submitted Submitted values.
	 * @return array<int, string>
	 */
	private function columns_from_form( array $submitted ): array {
		$shown     = is_array( $submitted['show'] ?? null ) ? $submitted['show'] : array();
		$positions = is_array( $submitted['position'] ?? null ) ? $submitted['position'] : array();
		$columns   = array();

		foreach ( array_keys( $shown ) as $column ) {
			$column             = (string) $column;
			$columns[ $column ] = (int) ( $positions[ $column ] ?? 99 );
		}

		asort( $columns );

		return array_keys( $columns );
	}
}
