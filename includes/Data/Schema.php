<?php
/**
 * Database schema.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the plugin's own tables.
 *
 * Class occurrences are machine data with a short useful life: a semester is
 * several thousand rows that are rewritten constantly and discarded when the
 * term ends. Keeping them in wp_posts would inflate the table every site query
 * has to walk, so they get a table of their own with indexes chosen for the two
 * questions the schedule actually asks: what happens between these timestamps,
 * and what happens in this room.
 */
final class Schema {

	/**
	 * Option holding the installed schema version.
	 */
	public const VERSION_OPTION = 'cscs_schema_version';

	/**
	 * Schema version. Bump when a table definition changes.
	 */
	public const VERSION = 1;

	/**
	 * Returns the lessons table name.
	 *
	 * @return string
	 */
	public static function lessons_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'cscs_lessons';
	}

	/**
	 * Returns the synchronisation log table name.
	 *
	 * @return string
	 */
	public static function log_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'cscs_sync_log';
	}

	/**
	 * Creates or upgrades the tables when the stored version is behind.
	 *
	 * @param bool $force Run even when the stored version is current.
	 * @return void
	 */
	public static function install( bool $force = false ): void {
		$installed = (int) get_option( self::VERSION_OPTION, 0 );

		if ( ! $force && $installed >= self::VERSION ) {
			return;
		}

		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$lessons = self::lessons_table();
		$log     = self::log_table();

		dbDelta(
			"CREATE TABLE {$lessons} (
				id_activity_term bigint(20) unsigned NOT NULL,
				id_activity bigint(20) unsigned NOT NULL DEFAULT 0,
				id_course bigint(20) unsigned NULL DEFAULT NULL,
				match_method varchar(20) NOT NULL DEFAULT '',
				activity_name varchar(255) NOT NULL DEFAULT '',
				match_key varchar(191) NOT NULL DEFAULT '',
				stamp_from bigint(20) unsigned NOT NULL DEFAULT 0,
				stamp_to bigint(20) unsigned NOT NULL DEFAULT 0,
				lesson_date date NULL DEFAULT NULL,
				time_from varchar(5) NOT NULL DEFAULT '',
				time_to varchar(5) NOT NULL DEFAULT '',
				id_tab bigint(20) unsigned NOT NULL DEFAULT 0,
				tab_name varchar(191) NOT NULL DEFAULT '',
				id_lane bigint(20) unsigned NOT NULL DEFAULT 0,
				lane_name varchar(191) NOT NULL DEFAULT '',
				id_trainer bigint(20) unsigned NOT NULL DEFAULT 0,
				trainer_name varchar(191) NOT NULL DEFAULT '',
				price varchar(20) NULL DEFAULT NULL,
				capacity int(11) NOT NULL DEFAULT 0,
				capacity_waiting int(11) NOT NULL DEFAULT 0,
				occupied int(11) NOT NULL DEFAULT 0,
				available int(11) NOT NULL DEFAULT 0,
				available_waiting int(11) NOT NULL DEFAULT 0,
				canceled tinyint(1) NOT NULL DEFAULT 0,
				booking_allowed tinyint(1) NOT NULL DEFAULT 0,
				is_external tinyint(1) NOT NULL DEFAULT 0,
				payload longtext NULL,
				synced_at bigint(20) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (id_activity_term),
				KEY stamp_from (stamp_from),
				KEY lesson_date (lesson_date),
				KEY id_tab (id_tab),
				KEY id_course (id_course),
				KEY match_key (match_key),
				KEY canceled (canceled)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$log} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				job varchar(40) NOT NULL DEFAULT '',
				started_at bigint(20) unsigned NOT NULL DEFAULT 0,
				duration_ms int(11) NOT NULL DEFAULT 0,
				records int(11) NOT NULL DEFAULT 0,
				outcome varchar(20) NOT NULL DEFAULT '',
				message text NULL,
				PRIMARY KEY  (id),
				KEY started_at (started_at),
				KEY job (job)
			) {$charset};"
		);

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Drops the plugin's tables. Only ever called from uninstall.
	 *
	 * @return void
	 */
	public static function drop(): void {
		global $wpdb;

		foreach ( array( self::lessons_table(), self::log_table() ) as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table names cannot be parameterised and are built from $wpdb->prefix.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
		}

		delete_option( self::VERSION_OPTION );
	}
}
