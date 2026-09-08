<?php
/**
 * Export and import of the plugin's settings.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Admin;

defined( 'ABSPATH' ) || exit;

use CSCS\Plugin;
use CSCS\Settings;

/**
 * Moves a configuration between sites as a file.
 *
 * The settings are twenty-odd values, several of them long lists of activity
 * names that took an afternoon to get right. Retyping them on the staging copy,
 * or after a rebuild, is how they end up subtly different from the ones that
 * were tested.
 *
 * Import is not a restore: every value goes through the same validating setter
 * the screen and the command line use, and a value the plugin would refuse to
 * store is reported rather than written. A file from a stranger can therefore
 * do nothing that could not be typed into the form.
 */
final class SettingsTransfer {

	/**
	 * The action the export button posts to.
	 */
	public const EXPORT = 'cscs_settings_export';

	/**
	 * The action the import form posts to.
	 */
	public const IMPORT = 'cscs_settings_import';

	/**
	 * Marker written into the file, so an unrelated JSON file is refused.
	 */
	private const MARKER = 'course-schedule-connector/settings';

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
	 * Hooks the two actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'export' ) );
		add_action( 'admin_post_' . self::IMPORT, array( $this, 'import' ) );
	}

	/**
	 * Sends the current settings as a file.
	 *
	 * @return void
	 */
	public function export(): void {
		check_admin_referer( self::EXPORT );

		if ( ! Capabilities::can_manage_design() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'course-schedule-connector' ) );
		}

		$payload = array(
			'format'   => self::MARKER,
			'version'  => CSCS_VERSION,
			'exported' => gmdate( 'c' ),
			'site'     => home_url(),
			'settings' => $this->plugin->settings()->all(),
		);

		$body = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		if ( ! is_string( $body ) ) {
			wp_die( esc_html__( 'The settings could not be written out.', 'course-schedule-connector' ) );
		}

		$name = sprintf( 'cscs-settings-%s.json', gmdate( 'Y-m-d' ) );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		header( 'Content-Length: ' . strlen( $body ) );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A JSON file body, sent as a download rather than as markup.

		exit;
	}

	/**
	 * Reads an uploaded file back into the settings.
	 *
	 * @return void
	 */
	public function import(): void {
		check_admin_referer( self::IMPORT );

		if ( ! Capabilities::can_manage_design() ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'course-schedule-connector' ) );
		}

		$outcome = $this->read();

		wp_safe_redirect(
			add_query_arg(
				$outcome,
				admin_url( 'admin.php?page=' . Menu::SLUG . '-settings' )
			)
		);

		exit;
	}

	/**
	 * Renders the panel on the settings screen.
	 *
	 * @return void
	 */
	public function panel(): void {
		$export = wp_nonce_url(
			add_query_arg( array( 'action' => self::EXPORT ), admin_url( 'admin-post.php' ) ),
			self::EXPORT
		);

		?>
		<h2><?php esc_html_e( 'Export and import', 'course-schedule-connector' ); ?></h2>
		<p class="description" style="max-width:45em">
			<?php esc_html_e( 'The file holds every setting on this screen, including the activity lists and the tag rules. Importing replaces the settings it names and leaves the rest alone; anything the plugin would refuse to store is reported and not written. Courses, pages and timetable data are not touched either way.', 'course-schedule-connector' ); ?>
		</p>
		<p>
			<a class="button" href="<?php echo esc_url( $export ); ?>"><?php esc_html_e( 'Download the settings', 'course-schedule-connector' ); ?></a>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="cscs-actions">
			<?php wp_nonce_field( self::IMPORT ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT ); ?>">
			<p>
				<label class="screen-reader-text" for="cscs-settings-file"><?php esc_html_e( 'Settings file', 'course-schedule-connector' ); ?></label>
				<input type="file" id="cscs-settings-file" name="cscs_settings_file" accept="application/json,.json" required>
				<button type="submit" name="cscs_action" value="import" class="button"><?php esc_html_e( 'Import the settings', 'course-schedule-connector' ); ?></button>
				<span class="cscs-progress" hidden>
					<span class="spinner is-active" style="float:none;margin:0 .3em 0 0"></span>
					<span class="cscs-progress__text" role="status"></span>
				</span>
			</p>
		</form>
		<?php
	}

	/**
	 * Shows what the last import did.
	 *
	 * @return void
	 */
	public static function notice(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reading redirect markers to phrase a message; no action is taken here.
		$imported = isset( $_GET['cscs-imported'] ) ? absint( wp_unslash( $_GET['cscs-imported'] ) ) : -1;
		$refused  = isset( $_GET['cscs-refused'] ) ? absint( wp_unslash( $_GET['cscs-refused'] ) ) : 0;
		$failed   = isset( $_GET['cscs-import-error'] ) ? sanitize_key( wp_unslash( $_GET['cscs-import-error'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' !== $failed ) {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html( self::reason( $failed ) )
			);

			return;
		}

		if ( $imported < 0 ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			0 === $refused ? 'success' : 'warning',
			esc_html(
				sprintf(
					/* translators: 1: number of settings written, 2: number refused */
					__( '%1$d settings imported, %2$d refused.', 'course-schedule-connector' ),
					$imported,
					$refused
				)
			)
		);
	}

	/**
	 * Turns a failure marker into something a person can read.
	 *
	 * @param string $reason Marker.
	 * @return string
	 */
	private static function reason( string $reason ): string {
		$reasons = array(
			'no_file'    => __( 'No file arrived. Choose a file and try again.', 'course-schedule-connector' ),
			'too_big'    => __( 'That file is larger than a settings file could be.', 'course-schedule-connector' ),
			'unreadable' => __( 'The file could not be read.', 'course-schedule-connector' ),
			'bad_json'   => __( 'The file is not valid JSON.', 'course-schedule-connector' ),
			'bad_shape'  => __( 'That is not a settings file exported from this plugin.', 'course-schedule-connector' ),
		);

		return $reasons[ $reason ] ?? __( 'The file could not be imported.', 'course-schedule-connector' );
	}

	/**
	 * Validates the upload and writes what it can.
	 *
	 * @return array<string, int|string> Query arguments describing the outcome.
	 */
	private function read(): array {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- The upload is validated field by field immediately below.
		$file = isset( $_FILES['cscs_settings_file'] ) ? $_FILES['cscs_settings_file'] : null;

		if ( ! is_array( $file ) || ! isset( $file['tmp_name'], $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return array( 'cscs-import-error' => 'no_file' );
		}

		$path = (string) $file['tmp_name'];

		if ( '' === $path || ! is_uploaded_file( $path ) ) {
			return array( 'cscs-import-error' => 'no_file' );
		}

		if ( filesize( $path ) > 256 * KB_IN_BYTES ) {
			return array( 'cscs-import-error' => 'too_big' );
		}

		$body = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local temporary upload, not a remote resource.

		if ( ! is_string( $body ) || '' === trim( $body ) ) {
			return array( 'cscs-import-error' => 'unreadable' );
		}

		$decoded = json_decode( $body, true );

		if ( ! is_array( $decoded ) ) {
			return array( 'cscs-import-error' => 'bad_json' );
		}

		if ( ( $decoded['format'] ?? '' ) !== self::MARKER || ! isset( $decoded['settings'] ) || ! is_array( $decoded['settings'] ) ) {
			return array( 'cscs-import-error' => 'bad_shape' );
		}

		return $this->apply( $decoded['settings'] );
	}

	/**
	 * Stores the values the plugin accepts and counts the rest.
	 *
	 * @param array<string, mixed> $values Values from the file.
	 * @return array<string, int>
	 */
	private function apply( array $values ): array {
		$settings = $this->plugin->settings();
		$written  = 0;
		$refused  = 0;

		foreach ( Settings::defaults() as $key => $unused ) {
			unset( $unused );

			if ( ! array_key_exists( $key, $values ) ) {
				continue;
			}

			try {
				$settings->set( $key, $values[ $key ] );

				++$written;
			} catch ( \InvalidArgumentException $e ) {
				unset( $e );

				++$refused;
			}
		}

		return array(
			'cscs-imported' => $written,
			'cscs-refused'  => $refused,
		);
	}
}
