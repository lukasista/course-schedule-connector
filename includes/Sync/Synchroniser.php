<?php
/**
 * Synchronisation of remote data into local storage.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Sync;

use CSCS\Api\ApiException;
use CSCS\Api\Client;
use CSCS\Data\CourseRepository;
use CSCS\Data\LessonRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Brings the local copy up to date.
 *
 * Every job here queries forward in time only. A date that has passed is never
 * requested again: its records are frozen, which costs the remote system
 * nothing and keeps the history stable no matter what the remote system decides
 * to do with it later.
 */
final class Synchroniser {

	/**
	 * Transient key guarding against overlapping runs.
	 */
	private const LOCK = 'cscs_sync_lock';

	/**
	 * API client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Course storage.
	 *
	 * @var CourseRepository
	 */
	private CourseRepository $courses;

	/**
	 * Occurrence storage.
	 *
	 * @var LessonRepository
	 */
	private LessonRepository $lessons;

	/**
	 * Matcher.
	 *
	 * @var Matcher
	 */
	private Matcher $matcher;

	/**
	 * Log.
	 *
	 * @var Logger
	 */
	private Logger $logger;

	/**
	 * Constructor.
	 *
	 * @param Client           $client   API client.
	 * @param CourseRepository $courses  Course storage.
	 * @param LessonRepository $lessons  Occurrence storage.
	 * @param Matcher          $matcher  Matcher.
	 * @param Logger           $logger   Log.
	 */
	public function __construct(
		Client $client,
		CourseRepository $courses,
		LessonRepository $lessons,
		Matcher $matcher,
		Logger $logger
	) {
		$this->client  = $client;
		$this->courses = $courses;
		$this->lessons = $lessons;
		$this->matcher = $matcher;
		$this->logger  = $logger;
	}

	/**
	 * Refreshes the course list.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array{records: int, archived: int}
	 * @throws ApiException When the remote system cannot be reached.
	 */
	public function sync_courses( bool $force = false ): array {
		$started = time();

		if ( ! $this->acquire() ) {
			$this->logger->record( 'courses', $started, 0, 'skipped', 'Another synchronisation was already running.' );

			return array(
				'records'  => 0,
				'archived' => 0,
			);
		}

		try {
			$courses = $this->client->get_courses( null, $force );
			$written = 0;

			foreach ( $courses as $course ) {
				if ( 0 !== $this->courses->save( $course ) ) {
					++$written;
				}
			}

			$archived = $this->courses->archive_missing( array_keys( $courses ) );

			$this->logger->record( 'courses', $started, $written, 'success' );

			return array(
				'records'  => $written,
				'archived' => $archived,
			);
		} catch ( ApiException $e ) {
			$this->logger->record( 'courses', $started, 0, 'failure', $e->get_reason() );

			throw $e;
		} finally {
			$this->release();
		}
	}

	/**
	 * Refreshes a window of class occurrences and re-runs matching over it.
	 *
	 * @param string      $job       Job name for the log.
	 * @param string|null $date_from Ymd start date. Defaults to today.
	 * @param string|null $date_to   Ymd end date.
	 * @param bool        $force     Bypass the cache.
	 * @return array{records: int, match: MatchResult|null}
	 * @throws ApiException When the remote system cannot be reached.
	 */
	public function sync_lessons( string $job = 'lessons', ?string $date_from = null, ?string $date_to = null, bool $force = false ): array {
		$started = time();

		if ( ! $this->acquire() ) {
			$this->logger->record( $job, $started, 0, 'skipped', 'Another synchronisation was already running.' );

			return array(
				'records' => 0,
				'match'   => null,
			);
		}

		try {
			$lessons = $this->client->get_lessons(
				$date_from ?? $this->today(),
				$date_to,
				null,
				null,
				$force
			);

			// A cached course list costs no request, and matching without it
			// would file every occurrence as an orphan.
			$courses = $this->client->get_courses();
			$match   = $this->matcher->match( $courses, $lessons, $this->lessons->manual_assignments() );
			$written = $this->lessons->save( $lessons, $match );

			$this->apply_rooms( $match );

			$this->logger->record(
				$job,
				$started,
				$written,
				'success',
				sprintf( 'match rate %.1f%%, %d external, %d unresolved', $match->rate(), $match->external(), $match->problematic() )
			);

			return array(
				'records' => $written,
				'match'   => $match,
			);
		} catch ( ApiException $e ) {
			$this->logger->record( $job, $started, 0, 'failure', $e->get_reason() );

			throw $e;
		} finally {
			$this->release();
		}
	}

	/**
	 * Re-runs matching over everything already stored, without a network request.
	 *
	 * @return MatchResult
	 * @throws ApiException When the course list cannot be read.
	 */
	public function rematch(): MatchResult {
		$started = time();

		$lessons = $this->lessons->all();
		$courses = $this->client->get_courses();
		$match   = $this->matcher->match( $courses, $lessons, $this->lessons->manual_assignments() );

		$this->lessons->save( $lessons, $match );
		$this->apply_rooms( $match );

		$this->logger->record(
			'rematch',
			$started,
			$match->matched(),
			'success',
			sprintf( 'match rate %.1f%%', $match->rate() )
		);

		return $match;
	}

	/**
	 * Writes the rooms each course actually uses onto its post.
	 *
	 * @param MatchResult $outcome Matching outcome.
	 * @return void
	 */
	private function apply_rooms( MatchResult $outcome ): void {
		$ids = $this->courses->all_ids();

		foreach ( $outcome->rooms as $course_id => $rooms ) {
			if ( isset( $ids[ $course_id ] ) ) {
				$this->courses->set_rooms( $ids[ $course_id ], $rooms );
			}
		}
	}

	/**
	 * Today's date in the site's timezone, as the API expects it.
	 *
	 * @return string
	 */
	private function today(): string {
		$today = wp_date( 'Ymd' );

		return is_string( $today ) && '' !== $today ? $today : gmdate( 'Ymd' );
	}

	/**
	 * Takes the run lock.
	 *
	 * @return bool False when another run holds it.
	 */
	private function acquire(): bool {
		if ( false !== get_transient( self::LOCK ) ) {
			return false;
		}

		set_transient( self::LOCK, time(), 10 * MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Releases the run lock.
	 *
	 * @return void
	 */
	private function release(): void {
		delete_transient( self::LOCK );
	}
}
