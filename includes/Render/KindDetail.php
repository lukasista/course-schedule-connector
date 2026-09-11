<?php
/**
 * Everything a kind of course's page needs, worked out once.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Render;

defined( 'ABSPATH' ) || exit;

use CSCS\Data\DisplaySet;
use CSCS\Data\KindRepository;
use CSCS\Data\KindType;
use CSCS\Plugin;

/**
 * The page of one kind of course: what it is called and what runs under it.
 *
 * Deliberately thin next to {@see CourseDetail} and {@see TrainerDetail}. A
 * kind holds no facts of its own — no price, no room, no capacity — because it
 * is not a thing anybody signs up for. What it has is a name, whatever somebody
 * wrote about it, and the courses filed under it, and only the last of those
 * needs working out.
 */
final class KindDetail {

	/**
	 * Plugin instance.
	 *
	 * @var Plugin
	 */
	private Plugin $plugin;

	/**
	 * The page.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $post;

	/**
	 * Constructor.
	 *
	 * @param Plugin   $plugin Plugin instance.
	 * @param \WP_Post $post   Kind page.
	 */
	public function __construct( Plugin $plugin, \WP_Post $post ) {
		$this->plugin = $plugin;
		$this->post   = $post;
	}

	/**
	 * Returns what this kind is called.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) get_the_title( $this->post );
	}

	/**
	 * Returns the courses of this kind, as a listing.
	 *
	 * The same listing code every other list of courses uses, so this table
	 * folds on a telephone exactly as the rest do, and the columns are the ones
	 * a trainer's table already answers with — the timetable a visitor is
	 * reading, not the catalogue an administrator is.
	 *
	 * Length and price are not among them. They were, until they got a table of
	 * their own: what a kind costs is two numbers for twenty-two courses, so
	 * repeating both down every row of the timetable said the same thing
	 * twenty-two times and made the columns a visitor came for narrower.
	 *
	 * What it shows may be narrowed — girls only, beginners only, seven to nine
	 * — which is how one kind shows as two tables without twenty-two courses
	 * having to be refiled under a kind invented to hold them. The question is
	 * asked in two places and that is the point: the page carries it, and the
	 * module may override it. A theme builder template is one design for every
	 * page of a type, so a filter set on the module inside it is set for all of
	 * them — "girls" on the page for boys. The page is the only place an answer
	 * can differ per page, so the page is where it belongs, and the module's
	 * own setting is for the other case: a module dropped on one particular
	 * page that wants something else.
	 *
	 * What is asked for and comes back empty is an empty table, not the whole
	 * kind: a filter that silently stops applying is worse than one that shows
	 * nothing.
	 *
	 * @param array<string, mixed> $settings Block or module settings.
	 * @return Listing|null
	 */
	public function course_listing( array $settings = array() ): ?Listing {
		// Read before the merge below, which keeps only the keys that narrow
		// the table and throws the rest away.
		$wording = trim( (string) ( $settings['detailText'] ?? '' ) );

		$settings = array_merge( $this->plugin->kinds()->filter( $this->post->ID ), self::asked( $settings ) );

		$ids = $this->plugin->kinds()->courses( $this->post->ID );

		if ( array() === $ids ) {
			return null;
		}

		Query::prime( $ids );

		$query = new Query( $this->plugin );
		$rows  = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post ) {
				$rows[] = $query->course_row( $post );
			}
		}

		// The wording of the way in is one field and it names the column and the
		// link alike: a column headed *Details* whose cells said something else
		// would be two names for one thing. Left empty it is whatever the
		// column is called in the site's language.
		$set = DisplaySet::from_array(
			array(
				'type'    => DisplaySet::TYPE_COURSES,
				'columns' => array( 'day', 'hours', 'age', 'gender', 'level', 'places', 'detail', 'button' ),
				'labels'  => '' === $wording ? array() : array( 'detail' => $wording ),
			)
		);

		$rows = self::filtered( $rows, $settings );
		$rows = self::sorted( $rows, $settings );

		$limit = max( 0, (int) ( $settings['filterLimit'] ?? 0 ) );

		if ( 0 !== $limit ) {
			$rows = array_slice( $rows, 0, $limit );
		}

		if ( array() === $rows ) {
			return null;
		}

		$times = $query->course_times(
			array_map(
				static function ( array $row ): int {
					return (int) ( $row['course_id'] ?? 0 );
				},
				$rows
			)
		);

		return new Listing( $set, $rows, Renderer::labels_for( $set ), $this->plugin->settings(), $times );
	}

	/**
	 * Returns what this kind costs, as a table of two columns.
	 *
	 * A kind has no price of its own, and often not one price at all: an hour
	 * of gymnastics and an hour and a half of it are different courses at
	 * different money, and on this site nearly every kind has two. So the
	 * answer is a row per pair actually on offer — 60 minutes at 4 160, 90 at
	 * 5 160 — and no more rows than there are distinct pairs: twenty-two
	 * courses of Gymnastika make two lines, not twenty-two.
	 *
	 * It is a `Listing` like every other table the plugin draws, built from one
	 * representative course per pair, so the columns are worded, formatted,
	 * folded on a telephone and designed exactly as the rest are.
	 *
	 * Courses with no price are left out rather than shown as free, and
	 * whatever narrows the page's timetable narrows this too.
	 *
	 * @param array<string, mixed> $settings Block or module settings.
	 * @return Listing|null
	 */
	public function price_listing( array $settings = array() ): ?Listing {
		$settings = array_merge( $this->plugin->kinds()->filter( $this->post->ID ), self::asked( $settings ) );

		$ids = $this->plugin->kinds()->courses( $this->post->ID );

		if ( array() === $ids ) {
			return null;
		}

		Query::prime( $ids );

		$query = new Query( $this->plugin );
		$rows  = array();

		foreach ( $ids as $id ) {
			$post = get_post( $id );

			if ( $post instanceof \WP_Post ) {
				$rows[] = $query->course_row( $post );
			}
		}

		// The same courses the timetable beside it lists. A page for the girls'
		// hours that priced the boys' as well would be answering a question
		// nobody on that page asked.
		$rows = self::filtered( $rows, $settings );

		$times = $query->course_times(
			array_map(
				static function ( array $row ): int {
					return (int) ( $row['course_id'] ?? 0 );
				},
				$rows
			)
		);

		$pairs = array();

		foreach ( $rows as $row ) {
			$price = $row['price'] ?? null;

			if ( null === $price || '' === (string) $price ) {
				continue;
			}

			$minutes = 0;

			foreach ( $times[ (int) ( $row['course_id'] ?? 0 ) ] ?? array() as $slot ) {
				$minutes = Formatter::minutes_between(
					(string) ( $slot['from'] ?? '' ),
					(string) ( $slot['to'] ?? '' )
				);

				if ( 0 !== $minutes ) {
					break;
				}
			}

			// Keyed by both, so two courses of one length and one price are one
			// row, and the same length at two prices is two. The first course of
			// a pair stands for it; every course of a pair would render the same
			// two cells.
			$key = $minutes . '|' . (string) $price;

			if ( isset( $pairs[ $key ] ) ) {
				continue;
			}

			$pairs[ $key ] = array(
				'minutes' => $minutes,
				'row'     => $row,
				'price'   => (string) $price,
			);
		}

		if ( array() === $pairs ) {
			return null;
		}

		uasort(
			$pairs,
			static function ( array $left, array $right ): int {
				return array( $left['minutes'], (float) $left['price'] ) <=> array( $right['minutes'], (float) $right['price'] );
			}
		);

		$chosen = array();
		$kept   = array();

		foreach ( $pairs as $pair ) {
			$chosen[] = $pair['row'];
			$course   = (int) ( $pair['row']['course_id'] ?? 0 );

			if ( isset( $times[ $course ] ) ) {
				$kept[ $course ] = $times[ $course ];
			}
		}

		$set = DisplaySet::from_array(
			array(
				'type'    => DisplaySet::TYPE_COURSES,
				'columns' => array( 'duration', 'price' ),
			)
		);

		return new Listing( $set, $chosen, Renderer::labels_for( $set ), $this->plugin->settings(), $kept );
	}

	/**
	 * Keeps the settings that were actually asked for.
	 *
	 * Every module sends every setting, filled in or not, so "not asked" has to
	 * be told from "asked for the default". An empty string is not asked; a
	 * limit of zero is "all of them", which is what not asking means; and a
	 * direction without something to sort by is Divi's own default rather than
	 * anybody's decision, so it is not allowed to overrule the page.
	 *
	 * @param array<string, mixed> $settings Block or module settings.
	 * @return array<string, mixed>
	 */
	private static function asked( array $settings ): array {
		$asked = array();

		foreach ( KindRepository::FILTER_KEYS as $key ) {
			$value = $settings[ $key ] ?? '';

			if ( '' !== $value && null !== $value && 0 !== $value && '0' !== $value ) {
				$asked[ $key ] = $value;
			}
		}

		if ( ! isset( $asked['filterSort'] ) ) {
			unset( $asked['filterOrder'] );
		}

		return $asked;
	}

	/**
	 * Keeps the rows the settings ask for.
	 *
	 * A course that says nothing about its group cannot be shown to match a
	 * question about groups — the same rule a display set follows, and the only
	 * one that does not put a mixed class on a card for girls.
	 *
	 * @param array<int, array<string, mixed>> $rows     Course rows.
	 * @param array<string, mixed>             $settings Block or module settings.
	 * @return array<int, array<string, mixed>>
	 */
	private static function filtered( array $rows, array $settings ): array {
		$asked = array(
			'filterGenders' => $settings['filterGenders'] ?? '',
			'filterLevels'  => $settings['filterLevels'] ?? '',
			'filterAgeMin'  => $settings['filterAgeMin'] ?? '',
			'filterAgeMax'  => $settings['filterAgeMax'] ?? '',
		);

		if ( '' === trim( implode( '', array_map( 'strval', $asked ) ) ) ) {
			return $rows;
		}

		// The rule itself lives with the pages, because it is asked in two
		// directions: of every course a page is about to list, and of one
		// course against every page, when a design made for all courses has to
		// work out which kind the course on the screen belongs to. Two copies
		// would be two answers to one question.
		return array_values(
			array_filter(
				$rows,
				static fn( array $row ): bool => KindRepository::matches( $asked, $row )
			)
		);
	}

	/**
	 * Puts the rows in the order the settings ask for.
	 *
	 * @param array<int, array<string, mixed>> $rows     Course rows.
	 * @param array<string, mixed>             $settings Block or module settings.
	 * @return array<int, array<string, mixed>>
	 */
	private static function sorted( array $rows, array $settings ): array {
		// The keys a rendered row actually carries, which are not the database
		// columns a display set sorts on — this list is already in memory and
		// there is nothing to ask the database for.
		$sorts = array(
			'name'   => array( 'name', false ),
			'start'  => array( 'date_from', false ),
			'price'  => array( 'price', true ),
			'places' => array( 'available', true ),
		);

		$sort = (string) ( $settings['filterSort'] ?? '' );

		if ( ! isset( $sorts[ $sort ] ) ) {
			return $rows;
		}

		list( $field, $numeric ) = $sorts[ $sort ];

		usort(
			$rows,
			static function ( array $left, array $right ) use ( $field, $numeric ): int {
				$a = $left[ $field ] ?? '';
				$b = $right[ $field ] ?? '';

				return $numeric
					? (float) $a <=> (float) $b
					: strnatcasecmp( (string) $a, (string) $b );
			}
		);

		return 'desc' === (string) ( $settings['filterOrder'] ?? 'asc' ) ? array_reverse( $rows ) : $rows;
	}

	/**
	 * Returns the term this page is paired with, if any.
	 *
	 * @return \WP_Term|null
	 */
	public function term(): ?\WP_Term {
		return $this->plugin->kinds()->term_for( $this->post->ID );
	}

	/**
	 * Returns the page itself.
	 *
	 * @return \WP_Post
	 */
	public function post(): \WP_Post {
		return $this->post;
	}

	/**
	 * Returns the key this page is paired by, for anything that has to match.
	 *
	 * @return string
	 */
	public function key(): string {
		return (string) get_post_meta( $this->post->ID, KindType::META_KEY_NAME, true );
	}
}
