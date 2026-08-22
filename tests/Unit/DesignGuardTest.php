<?php
/**
 * Tests for the design guard.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

namespace CSCS\Tests\Unit;

use CSCS\Divi\DesignGuard;
use PHPUnit\Framework\TestCase;

/**
 * Guards the site owner's first requirement: content is the site manager's,
 * design is the administrator's, and the split is enforced on the server.
 *
 * @covers \CSCS\Divi\DesignGuard
 */
final class DesignGuardTest extends TestCase {

	/**
	 * Attributes as an administrator left them.
	 *
	 * @return array<string, mixed>
	 */
	private function stored(): array {
		return array(
			'set'            => array( 'innerContent' => array( 'desktop' => array( 'value' => 'kurzy-pro-deti' ) ) ),
			'module'         => array(
				'decoration' => array( 'background' => array( 'desktop' => array( 'value' => array( 'color' => '#ffffff' ) ) ) ),
			),
			'css'            => array( 'freeForm' => 'body { display: none }' ),
			'builderVersion' => '5.11.1',
		);
	}

	/**
	 * An administrator's save is passed through untouched.
	 *
	 * @return void
	 */
	public function test_an_administrator_may_change_anything(): void {
		$submitted = $this->stored();

		$submitted['module']['decoration']['background']['desktop']['value']['color'] = '#000000';

		$this->assertSame( $submitted, DesignGuard::merge( $this->stored(), $submitted, true ) );
	}

	/**
	 * A site manager's design changes are put back the way they were, and the
	 * change they were entitled to make survives.
	 *
	 * @return void
	 */
	public function test_a_site_manager_changes_the_set_and_nothing_else(): void {
		$submitted = $this->stored();

		$submitted['set']['innerContent']['desktop']['value']                          = 'tydenni-rozvrh';
		$submitted['module']['decoration']['background']['desktop']['value']['color'] = '#000000';
		$submitted['css']['freeForm']                                                  = 'body { color: red }';

		$guarded = DesignGuard::merge( $this->stored(), $submitted, false );

		$this->assertSame( 'tydenni-rozvrh', $guarded['set']['innerContent']['desktop']['value'] );
		$this->assertSame( '#ffffff', $guarded['module']['decoration']['background']['desktop']['value']['color'] );
		$this->assertSame( 'body { display: none }', $guarded['css']['freeForm'] );
	}

	/**
	 * Custom CSS is design. It is a stylesheet with another name, and letting
	 * it through would make the rest of the rule decorative.
	 *
	 * @return void
	 */
	public function test_custom_css_counts_as_design(): void {
		$guarded = DesignGuard::merge(
			array(),
			array( 'css' => array( 'freeForm' => '.et_pb_module { position: fixed }' ) ),
			false
		);

		$this->assertArrayNotHasKey( 'css', $guarded );
	}

	/**
	 * A module a site manager adds arrives with no design at all rather than
	 * with whatever the builder put in it.
	 *
	 * @return void
	 */
	public function test_a_newly_added_module_carries_no_design(): void {
		$submitted = $this->stored();

		$guarded = DesignGuard::merge( array(), $submitted, false );

		$this->assertSame( array( 'set', 'builderVersion' ), array_keys( $guarded ) );
		$this->assertSame( 'kurzy-pro-deti', $guarded['set']['innerContent']['desktop']['value'] );
	}

	/**
	 * Removing the set removes it. The rule is about design, not about forcing
	 * a listing to stay on a page.
	 *
	 * @return void
	 */
	public function test_the_set_can_be_cleared(): void {
		$submitted = $this->stored();

		unset( $submitted['set'] );

		$guarded = DesignGuard::merge( $this->stored(), $submitted, false );

		$this->assertArrayNotHasKey( 'set', $guarded );
		$this->assertArrayHasKey( 'module', $guarded );
	}

	/**
	 * Modules are found wherever they sit, including inside a row inside a
	 * section, which is where every one of them actually sits.
	 *
	 * @return void
	 */
	public function test_modules_are_found_at_any_depth_in_document_order(): void {
		$blocks = array(
			array(
				'blockName'   => 'divi/section',
				'attrs'       => array(),
				'innerBlocks' => array(
					array(
						'blockName'   => 'divi/row',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'cscs/divi-display',
								'attrs'       => array( 'set' => 'first' ),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'divi/text',
								'attrs'       => array(),
								'innerBlocks' => array(),
							),
							array(
								'blockName'   => 'cscs/divi-display',
								'attrs'       => array( 'set' => 'second' ),
								'innerBlocks' => array(),
							),
						),
					),
				),
			),
		);

		$this->assertSame(
			array( array( 'set' => 'first' ), array( 'set' => 'second' ) ),
			DesignGuard::collect( $blocks )
		);
	}

	/**
	 * A page with none of our modules yields nothing to guard, so a save of one
	 * is never rewritten.
	 *
	 * @return void
	 */
	public function test_a_page_without_the_module_yields_nothing(): void {
		$this->assertSame(
			array(),
			DesignGuard::collect(
				array(
					array(
						'blockName'   => 'divi/section',
						'attrs'       => array(),
						'innerBlocks' => array(
							array(
								'blockName'   => 'divi/text',
								'attrs'       => array( 'module' => array() ),
								'innerBlocks' => array(),
							),
						),
					),
				)
			)
		);
	}
}
