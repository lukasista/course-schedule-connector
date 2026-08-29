<?php
/**
 * Synchronisation log.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\Schema;

/**
 * Records what each synchronisation did.
 *
 * Counts, durations and error codes only. Response bodies and full URLs stay
 * out: a log that quietly accumulates other people's data is a liability, and
 * nothing in a body helps answer the question the log exists for, which is
 * whether the last run worked.
 */
final class Logger {

	/**
	 * Records a run.
	 *
	 * @param string $job        Job name.
	 * @param int    $started_at Start timestamp.
	 * @param int    $records    Records processed.
	 * @param string $outcome    One of "success", "skipped" or "failure".
	 * @param string $message    Short message, already free of sensitive detail.
	 * @return void
	 */
	public function record( string $job, int $started_at, int $records, string $outcome, string $message = '' ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- The plugin's own table; there is no API for it.
		$wpdb->insert(
			Schema::log_table(),
			array(
				'job'         => substr( $job, 0, 40 ),
				'started_at'  => $started_at,
				'duration_ms' => (int) round( ( microtime( true ) - $started_at ) * 1000 ),
				'records'     => $records,
				'outcome'     => substr( $outcome, 0, 20 ),
				'message'     => substr( $message, 0, 500 ),
			),
			array( '%s', '%d', '%d', '%d', '%s', '%s' )
		);
	}

	/**
	 * Returns the most recent entries.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function recent( int $limit = 50 ): array {
		global $wpdb;

		$table = Schema::log_table();

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: there is no core API for it, the table name comes from $wpdb->prefix and cannot be a placeholder, and the results are already served from the object cache one layer up.
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", max( 1, $limit ) ),
			ARRAY_A
		);

		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Removes entries older than a cut-off.
	 *
	 * @param int $before Timestamp.
	 * @return int Rows removed.
	 */
	public function purge_before( int $before ): int {
		global $wpdb;

		$table = Schema::log_table();

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE started_at < %d", $before ) );
	}

	/**
	 * Returns the last run of a job, or null.
	 *
	 * @param string $job Job name.
	 * @return array<string, mixed>|null
	 */
	public function last( string $job ): ?array {
		global $wpdb;

		$table = Schema::log_table();

		// phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Table name from $wpdb->prefix; the bound value is prepared.
		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- The plugin's own table: there is no core API for it, the table name comes from $wpdb->prefix and cannot be a placeholder, and the results are already served from the object cache one layer up.
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE job = %s ORDER BY id DESC LIMIT 1", $job ),
			ARRAY_A
		);

		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery

		return is_array( $row ) ? $row : null;
	}
}
