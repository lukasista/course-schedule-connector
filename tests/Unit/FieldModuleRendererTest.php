<?php
/**
 * Tests for reassembling Divi's discrete layout settings into one object.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Divi\FieldModuleRenderer;
use PHPUnit\Framework\TestCase;

/**
 * `cardsLayout` and `cardLayout` are not settings Divi's builder writes
 * directly any more — the panel offers eight plain `divi/select` fields
 * instead (`cardsDirection`/`cardsJustify`/`cardsAlign`/`cardsWrap`, and the
 * same four again prefixed `card`), and `FieldModuleRenderer::settings()`
 * reassembles each set of four back into the one `display`/`flexDirection`/
 * `justifyContent`/`alignItems`/`flexWrap` shape
 * `FieldRenderer::layout_value()` already parses — the same shape Divi's own
 * native Layout widget would have written, so that half of the pipeline did
 * not have to change. These tests hold that reassembly to account on its
 * own, apart from the parsing on the other side of it
 * {@see FieldRendererTest}.
 *
 * @covers \CSCS\Divi\FieldModuleRenderer
 */
final class FieldModuleRendererTest extends TestCase {

	/**
	 * Builds the `field.advanced` attrs shape Divi actually stores, from a
	 * flat map of key to desktop value.
	 *
	 * @param array<string, string> $values Setting name to stored value.
	 * @return array<string, mixed>
	 */
	private function attrs( array $values ): array {
		$advanced = array();

		foreach ( $values as $key => $value ) {
			$advanced[ $key ] = array( 'desktop' => array( 'value' => $value ) );
		}

		return array( 'field' => array( 'advanced' => $advanced ) );
	}

	/**
	 * Calls the private `settings()` the same way Divi's own preview
	 * endpoint does, for a field of cards.
	 *
	 * @param array<string, string> $values Setting name to stored value.
	 * @return array<string, mixed>
	 */
	private function settings_for( array $values ): array {
		$callable = new \ReflectionMethod( FieldModuleRenderer::class, 'settings' );
		$callable->setAccessible( true );

		return (array) $callable->invoke( null, $this->attrs( $values ), 'kind-trainers', array() );
	}

	/**
	 * Nothing chosen for the grid is nothing to reassemble — the same
	 * "nothing configured" `card_rules()` already treats as no override.
	 *
	 * @return void
	 */
	public function test_nothing_set_assembles_to_an_empty_grid_layout(): void {
		$settings = $this->settings_for( array() );

		self::assertSame( array(), $settings['cardsLayout'] );
		self::assertSame( array(), $settings['cardLayout'] );
	}

	/**
	 * All four grid settings chosen become one object, with `display` added
	 * — the same object Divi's own native Layout widget would have written.
	 *
	 * @return void
	 */
	public function test_all_four_grid_settings_assemble_into_one_object(): void {
		$settings = $this->settings_for(
			array(
				'cardsDirection' => 'row',
				'cardsJustify'   => 'space-between',
				'cardsAlign'     => 'center',
				'cardsWrap'      => 'wrap',
			)
		);

		self::assertSame(
			array(
				'display'        => 'flex',
				'flexDirection'  => 'row',
				'justifyContent' => 'space-between',
				'alignItems'     => 'center',
				'flexWrap'       => 'wrap',
			),
			$settings['cardsLayout']
		);
	}

	/**
	 * Choosing only one of the four still assembles a valid object — a
	 * designer setting only direction must not be forced to also pick
	 * alignment and wrap before anything takes effect.
	 *
	 * @return void
	 */
	public function test_choosing_only_direction_still_assembles(): void {
		$settings = $this->settings_for( array( 'cardsDirection' => 'column' ) );

		self::assertSame(
			array(
				'display'       => 'flex',
				'flexDirection' => 'column',
			),
			$settings['cardsLayout']
		);
	}

	/**
	 * The card's own four settings assemble into `cardLayout`, independently
	 * of the grid's — the exact independence a duplicated native Layout
	 * widget failed to keep, which is why these are eight separate settings
	 * rather than two copies of one widget.
	 *
	 * @return void
	 */
	public function test_the_grid_and_the_card_assemble_independently(): void {
		$settings = $this->settings_for(
			array(
				'cardsDirection' => 'row',
				'cardsAlign'     => 'flex-start',
				'cardJustify'    => 'center',
				'cardAlign'      => 'center',
			)
		);

		self::assertSame(
			array(
				'display'       => 'flex',
				'flexDirection' => 'row',
				'alignItems'    => 'flex-start',
			),
			$settings['cardsLayout']
		);
		self::assertSame(
			array(
				'display'        => 'flex',
				'justifyContent' => 'center',
				'alignItems'     => 'center',
			),
			$settings['cardLayout']
		);
	}

	/**
	 * The eight discrete settings are consumed here and must not also leak
	 * into the result under their own flat names — that would be a second,
	 * disagreeing copy of what `cardsLayout`/`cardLayout` already say.
	 *
	 * @return void
	 */
	public function test_the_discrete_settings_do_not_also_appear_under_their_own_names(): void {
		$settings = $this->settings_for( array( 'cardsDirection' => 'row' ) );

		foreach ( array( 'cardsDirection', 'cardsJustify', 'cardsAlign', 'cardsWrap', 'cardDirection', 'cardJustify', 'cardAlign', 'cardWrap' ) as $key ) {
			self::assertArrayNotHasKey( $key, $settings, $key );
		}
	}

	/**
	 * The photograph's own margin is a plain passthrough, read the same way
	 * as any other scalar setting.
	 *
	 * @return void
	 */
	public function test_the_photographs_margin_passes_through(): void {
		$settings = $this->settings_for( array( 'cardImageMargin' => '0 0 8px 0' ) );

		self::assertSame( '0 0 8px 0', $settings['cardImageMargin'] );
	}
}
