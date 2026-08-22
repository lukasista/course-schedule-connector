<?php
/**
 * Tests for settings validation.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Settings;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the boundary every setting passes through, including the one that
 * decides where the plugin is allowed to send a request.
 *
 * @covers \CSCS\Settings
 */
final class SettingsTest extends TestCase {

	/**
	 * Settings under test.
	 *
	 * @var Settings
	 */
	private Settings $settings;

	/**
	 * Resets stored state.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		cscs_reset_test_state();

		$this->settings = new Settings();
	}

	/**
	 * A public https URL is accepted and stored without its trailing slash.
	 *
	 * @return void
	 */
	public function test_a_valid_base_url_is_normalised_and_stored(): void {
		$stored = $this->settings->set( 'api_base_url', 'https://jojogym.isportsystem.cz/' );

		$this->assertSame( 'https://jojogym.isportsystem.cz', $stored );
		$this->assertSame( 'https://jojogym.isportsystem.cz', $this->settings->api_base_url() );
		$this->assertTrue( $this->settings->is_configured() );
	}

	/**
	 * The server-side request forgery guard applies at the settings boundary,
	 * not only when a request is about to be made.
	 *
	 * @dataProvider provide_refused_urls
	 *
	 * @param string $url Candidate URL.
	 * @return void
	 */
	public function test_an_unsafe_base_url_is_refused( string $url ): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->settings->set( 'api_base_url', $url );
	}

	/**
	 * URLs that must never be stored.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function provide_refused_urls(): array {
		return array(
			'plain http'  => array( 'http://jojogym.isportsystem.cz' ),
			'loopback'    => array( 'https://127.0.0.1' ),
			'link local'  => array( 'https://169.254.169.254' ),
			'credentials' => array( 'https://user:pass@example.com' ),
			'not a url'   => array( 'jojogym.isportsystem.cz' ),
		);
	}

	/**
	 * An unknown key is a mistake worth reporting, not a new setting.
	 *
	 * @return void
	 */
	public function test_an_unknown_setting_is_refused(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->settings->set( 'colour_of_the_sky', 'blue' );
	}

	/**
	 * Types are inferred from the defaults and enforced.
	 *
	 * @return void
	 */
	public function test_values_are_coerced_to_the_type_the_key_expects(): void {
		$this->assertTrue( $this->settings->set( 'show_canceled_lessons', 'yes' ) );
		$this->assertFalse( $this->settings->set( 'show_canceled_lessons', 'no' ) );
		$this->assertSame( 900, $this->settings->set( 'interval_courses', '900' ) );
	}

	/**
	 * A number setting refuses text rather than silently storing zero.
	 *
	 * @return void
	 */
	public function test_a_number_setting_refuses_text(): void {
		$this->expectException( \InvalidArgumentException::class );

		$this->settings->set( 'interval_courses', 'often' );
	}

	/**
	 * Term dates are checked for shape, because the schedule depends on them.
	 *
	 * @return void
	 */
	public function test_term_dates_must_be_iso_formatted(): void {
		$this->assertSame( '2026-09-11', $this->settings->set( 'semester_from', '2026-09-11' ) );

		$this->expectException( \InvalidArgumentException::class );

		$this->settings->set( 'semester_to', '22.01.2027' );
	}

	/**
	 * The price fallback only accepts the two readings that exist.
	 *
	 * @return void
	 */
	public function test_the_price_fallback_is_restricted_to_known_values(): void {
		$this->assertSame( 'free', $this->settings->set( 'lesson_price_when_empty', 'FREE' ) );

		$this->expectException( \InvalidArgumentException::class );

		$this->settings->set( 'lesson_price_when_empty', 'ask' );
	}

	/**
	 * The non-bookable list ships with real content and reaches a caller that
	 * asks for it.
	 *
	 * A default added to the wrong place is invisible: the matcher was handed an
	 * empty list for a whole run while its own tests passed, because they built
	 * the list by hand instead of reading the setting.
	 *
	 * @return void
	 */
	public function test_the_non_bookable_list_has_a_usable_default(): void {
		$list = $this->settings->get( 'non_bookable_activities' );

		$this->assertIsArray( $list );
		$this->assertNotEmpty( $list );
		$this->assertContains( 'Náhradní lekce', $list );
		$this->assertContains( 'Judo', $list );
	}

	/**
	 * Every default is readable through get(), so a key defined in one place and
	 * consumed in another cannot silently return null.
	 *
	 * @return void
	 */
	public function test_every_default_is_readable(): void {
		foreach ( array_keys( Settings::defaults() ) as $key ) {
			$this->assertNotNull( $this->settings->get( $key ), $key . ' returned null.' );
		}
	}

	/**
	 * The list accepts a textarea's worth of names, one per line.
	 *
	 * @return void
	 */
	public function test_the_non_bookable_list_accepts_lines_of_text(): void {
		$stored = $this->settings->set( 'non_bookable_activities', "Balet\n  Judo  \n\nKarate\n" );

		$this->assertSame( array( 'Balet', 'Judo', 'Karate' ), $stored );
	}

	/**
	 * Seeding gives an administrator something to edit, and never overwrites
	 * what they have already chosen.
	 *
	 * @return void
	 */
	public function test_seeding_creates_defaults_but_never_overwrites(): void {
		Settings::seed_defaults();

		$this->assertSame( Settings::defaults(), get_option( Settings::OPTION ) );

		$this->settings->set( 'interval_courses', 1200 );
		Settings::seed_defaults();

		$this->settings->flush();
		$this->assertSame( 1200, $this->settings->get( 'interval_courses' ) );
	}
}
