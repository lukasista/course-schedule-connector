<?php
/**
 * Writes the Divi 5 module metadata for every field.
 *
 * Divi reads a module from a directory holding `module.json`, so a module
 * cannot be declared in code the way a Gutenberg block can. Twenty-odd
 * directories of near-identical JSON is not something to maintain by hand, so
 * they are generated from the same catalogue the blocks come from and committed
 * — reviewable in a diff, and regenerated with one command when a field is
 * added:
 *
 *     php tools/build-divi-modules.php
 *
 * The titles written here are English on purpose. What the builder shows is
 * handed over at runtime, translated, by CSCS\Divi\FieldModules.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$root = dirname( __DIR__ );

/**
 * The catalogue, read from the plugin rather than copied beside it.
 *
 * `Fields::all()` needs two functions and a constant to be loadable, and
 * nothing else — no database, no site, no WordPress. Standing those up here is
 * a few lines and buys the thing that matters: there is one list of fields, so
 * the modules cannot quietly stop matching the blocks.
 */
define( 'ABSPATH', $root . '/' );

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Filter stub: nothing is hooked in a build script.
	 *
	 * @param string $hook  Hook name.
	 * @param mixed  $value Value.
	 * @return mixed
	 */
	function apply_filters( $hook, $value ) {
		unset( $hook );

		return $value;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translation stub: the builder is handed translated titles at runtime.
	 *
	 * @param string $text   Text.
	 * @param string $domain Text domain.
	 * @return string
	 */
	function __( $text, $domain = 'default' ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- A stub for a build script that runs without WordPress.
		unset( $domain );

		return $text;
	}
}

require_once $root . '/includes/Render/Fields.php';

$fields = \CSCS\Render\Fields::all();

$written = 0;

foreach ( $fields as $name => $field ) {
	$directory = $root . '/divi/fields/' . $name;

	if ( ! is_dir( $directory ) && ! mkdir( $directory, 0755, true ) && ! is_dir( $directory ) ) {
		fwrite( STDERR, "Could not make {$directory}\n" );

		exit( 1 );
	}

	file_put_contents(
		$directory . '/module.json',
		json_encode( module_metadata( $name, $field ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
	);

	file_put_contents(
		$directory . '/module-default-render-attributes.json',
		json_encode( module_defaults( $field ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
	);

	++$written;
}

echo "Wrote {$written} module(s).\n";

/**
 * Builds one module's metadata.
 *
 * @param string               $name  Field name.
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function module_metadata( string $name, array $field ): array {
	$slug = str_replace( '-', '_', $name );

	return array(
		'name'                 => 'cscs/divi-' . $name,
		'd4Shortcode'          => 'cscs_divi_' . $slug,
		'title'                => $field['title'],
		'titles'               => $field['title'],
		'moduleClassName'      => 'cscs_divi_field',
		'moduleOrderClassName' => 'cscs_divi_field_' . $slug,
		'moduleIcon'           => 'divi/module-text',
		'category'             => 'module',
		'attributes'           => array(
			'module' => module_attribute(),
			'title'  => element_attribute( '{{selector}} .cscs-field__label', 'title', 'designHeadingText', 'Heading' ),
			'value'  => element_attribute( '{{selector}} .cscs-field__value', 'value', 'designValueText', 'Value' ),
			'field'  => field_attribute( $field ),
			'css'    => array( 'type' => 'object' ),
		),
		'settings'             => array(
			'content'  => 'auto',
			'design'   => 'auto',
			'advanced' => 'auto',
			'groups'   => array(
				'content'           => array(
					'panel'     => 'content',
					'priority'  => 10,
					'groupName' => 'content',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'iSport' ),
					),
				),
				// Two design groups, named apart. Left to Divi's own naming
				// both typography groups come out called "Module Text", and a
				// panel with two identically named groups in it is a panel
				// nobody can use.
				'designHeadingText' => array(
					'panel'         => 'design',
					'priority'      => 20,
					'groupName'     => 'headingText',
					'multiElements' => true,
					'component'     => array(
						'name'  => 'divi/composite',
						'props' => array(
							'groupLabel'        => 'Heading text',
							'clipboardCategory' => 'style',
						),
					),
				),
				'designValueText'   => array(
					'panel'         => 'design',
					'priority'      => 30,
					'groupName'     => 'valueText',
					'multiElements' => true,
					'component'     => array(
						'name'  => 'divi/composite',
						'props' => array(
							'groupLabel'        => 'Value text',
							'clipboardCategory' => 'style',
						),
					),
				),
			),
		),
	);
}

/**
 * The decoration groups every module gets.
 *
 * @return array<string, mixed>
 */
function module_attribute(): array {
	return array(
		'type'     => 'object',
		'selector' => '{{selector}}',
		'settings' => array(
			'meta'       => array( 'meta' => array() ),
			'advanced'   => array(
				'text' => array(),
				'link' => array(),
				'html' => array(),
			),
			'decoration' => array(
				'background' => array(),
				'border'     => array(),
				'boxShadow'  => array(),
				'filters'    => array(),
				'spacing'    => array(),
				'sizing'     => array(),
				'transform'  => array(),
				'animation'  => array(),
				'disabledOn' => array(),
				'overflow'   => array(),
				'position'   => array(),
				'scroll'     => array(),
				'sticky'     => array(),
				'transition' => array(),
				'zIndex'     => array(),
			),
		),
	);
}

/**
 * A sub-element with typography of its own.
 *
 * This is the whole reason these modules exist rather than one module with a
 * dropdown: the heading and the value are two things a designer treats
 * differently, and Divi's own font group applied to the module as a whole
 * cannot tell them apart.
 *
 * @param string $selector Where the styles land.
 * @return array<string, mixed>
 */
function element_attribute( string $selector, string $attr, string $group, string $label ): array {
	$item = static function ( string $component, string $property, int $priority ) use ( $attr, $group, $label ): array {
		return array(
			'groupType' => 'group-item',
			'item'      => array(
				'groupSlug' => $group,
				'priority'  => $priority,
				'render'    => true,
				'component' => array(
					'type'  => 'group',
					'name'  => $component,
					'props' => array(
						'attrName'   => $attr . '.decoration.' . $property,
						'grouped'    => true,
						'fieldLabel' => $label,
					),
				),
			),
		);
	};

	return array(
		'type'     => 'object',
		'selector' => $selector,
		'settings' => array(
			'decoration' => array(
				'font'    => $item( 'divi/font', 'font', 10 ),
				'spacing' => $item( 'divi/spacing', 'spacing', 20 ),
			),
		),
	);
}

/**
 * The module's own content settings.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function field_attribute( array $field ): array {
	$items    = array();
	$priority = 10;

	$add = static function ( string $key, array $item ) use ( &$items, &$priority ): void {
		$items[ $key ] = array(
			'groupType' => 'group-item',
			'item'      => array_merge(
				array(
					'groupSlug' => 'content',
					'attrName'  => 'field.advanced.' . $key,
					'category'  => 'configuration',
					'priority'  => $priority,
					'render'    => true,
					'features'  => array(
						'sticky'     => false,
						'responsive' => false,
						'hover'      => false,
						'preset'     => 'content',
					),
				),
				$item
			),
		);

		$priority += 10;
	};

	$add(
		'source',
		array(
			'label'       => 'Source',
			'description' => 'Which course or trainer this shows. Left empty it follows the page, which is what a theme builder template wants.',
			'component'   => array(
				'name'  => 'divi/select',
				'type'  => 'field',
				'props' => array( 'options' => new stdClass() ),
			),
		)
	);

	$add(
		'showLabel',
		array(
			'label'       => 'Show a heading',
			'description' => 'Whether the field prints its name above or beside the value.',
			'component'   => array(
				'name' => 'divi/toggle',
				'type' => 'field',
			),
		)
	);

	$add(
		'label',
		array(
			'label'       => 'Heading',
			'description' => 'What to call this field. Empty means the name it comes with.',
			'component'   => array(
				'name' => 'divi/text',
				'type' => 'field',
			),
		)
	);

	$add(
		'labelTag',
		array(
			'label'       => 'Heading element',
			'description' => 'Which HTML element the heading is.',
			'component'   => array(
				'name'  => 'divi/select',
				'type'  => 'field',
				'props' => array(
					'options' => array(
						'h1'     => array( 'label' => 'H1' ),
						'h2'     => array( 'label' => 'H2' ),
						'h3'     => array( 'label' => 'H3' ),
						'h4'     => array( 'label' => 'H4' ),
						'h5'     => array( 'label' => 'H5' ),
						'h6'     => array( 'label' => 'H6' ),
						'p'      => array( 'label' => 'P' ),
						'div'    => array( 'label' => 'DIV' ),
						'span'   => array( 'label' => 'SPAN' ),
						'strong' => array( 'label' => 'STRONG' ),
					),
				),
			),
		)
	);

	$add(
		'valueTag',
		array(
			'label'       => 'Value element',
			'description' => 'Which HTML element the value is.',
			'component'   => array(
				'name'  => 'divi/select',
				'type'  => 'field',
				'props' => array(
					'options' => array(
						'div'    => array( 'label' => 'DIV' ),
						'p'      => array( 'label' => 'P' ),
						'span'   => array( 'label' => 'SPAN' ),
						'strong' => array( 'label' => 'STRONG' ),
						'h2'     => array( 'label' => 'H2' ),
						'h3'     => array( 'label' => 'H3' ),
						'h4'     => array( 'label' => 'H4' ),
					),
				),
			),
		)
	);

	$add(
		'layout',
		array(
			'label'       => 'Arrangement',
			'description' => 'Heading above the value, or beside it.',
			'component'   => array(
				'name'  => 'divi/select',
				'type'  => 'field',
				'props' => array(
					'options' => array(
						'stack'  => array( 'label' => 'Heading above' ),
						'inline' => array( 'label' => 'Side by side' ),
					),
				),
			),
		)
	);

	$add(
		'separator',
		array(
			'label'       => 'After the heading',
			'description' => 'A colon, a dash — printed right after the heading.',
			'component'   => array(
				'name' => 'divi/text',
				'type' => 'field',
			),
		)
	);

	$add(
		'gap',
		array(
			'label'       => 'Gap',
			'description' => 'Between the heading and the value.',
			'component'   => array(
				'name' => 'divi/text',
				'type' => 'field',
			),
		)
	);

	if ( 'list' === $field['kind'] ) {
		$add(
			'listStyle',
			array(
				'label'       => 'Bullets',
				'description' => 'What the list is marked with.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array(
						'options' => array(
							'disc'    => array( 'label' => 'Round' ),
							'circle'  => array( 'label' => 'Hollow' ),
							'square'  => array( 'label' => 'Square' ),
							'ordered' => array( 'label' => 'Numbered' ),
							'none'    => array( 'label' => 'None' ),
						),
					),
				),
			)
		);
	}

	$add(
		'emptyText',
		array(
			'label'       => 'When there is nothing to show',
			'description' => 'Left empty the module disappears rather than printing a heading over a blank space.',
			'component'   => array(
				'name' => 'divi/text',
				'type' => 'field',
			),
		)
	);

	return array(
		'type'     => 'object',
		'selector' => '{{selector}}',
		'settings' => array( 'advanced' => $items ),
	);
}

/**
 * The defaults Divi writes a chosen value into.
 *
 * Without this file there is no structure to write into, and every choice made
 * in every field is refused without a word. That cost an evening once already;
 * it is not going to cost another.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function module_defaults( array $field ): array {
	$advanced = array(
		'source'    => '',
		'showLabel' => $field['heading'] ? 'on' : 'off',
		'label'     => '',
		'labelTag'  => 'h3',
		'valueTag'  => 'div',
		'layout'    => 'stack',
		'separator' => '',
		'gap'       => '',
		'emptyText' => '',
	);

	if ( 'list' === $field['kind'] ) {
		$advanced['listStyle'] = 'disc';
	}

	$defaults = array(
		'module' => array(
			'meta' => array(
				'adminLabel' => array(
					'desktop' => array( 'value' => $field['title'] ),
				),
			),
		),
		'field'  => array( 'advanced' => array() ),
	);

	foreach ( $advanced as $key => $value ) {
		$defaults['field']['advanced'][ $key ] = array(
			'desktop' => array( 'value' => $value ),
		);
	}

	return $defaults;
}
