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
		'attributes'           => array_filter(
			array(
				'module' => module_attribute( ! empty( $field['image'] ) ),
				'title'  => element_attribute( '{{selector}} .cscs-field__label', 'title', 'designHeadingText', 'Heading', 'heading' ),
				'value'  => element_attribute( '{{selector}} .cscs-field__value', 'value', 'designValueText', 'Value', 'content' ),
				'image'  => empty( $field['image'] ) ? array() : image_attribute(),
				'field'  => field_attribute( $field ),
				'css'    => array( 'type' => 'object' ),
			)
		),
		'settings'             => array(
			'content'  => 'auto',
			'design'   => 'auto',
			'advanced' => 'auto',
			'groups'   => array_filter(
				array(
				'content'           => array(
					'panel'     => 'content',
					'priority'  => 10,
					'groupName' => 'content',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'iSport' ),
					),
				),
				// A picture gets the two content groups Divi's own Image module
				// has, in the same order, so that somebody who knows that module
				// finds what they are looking for where they expect it.
				'contentPicture'    => empty( $field['image'] ) ? null : array(
					'panel'     => 'content',
					'priority'  => 20,
					'groupName' => 'picture',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'Picture' ),
					),
				),
				'contentPictureLink' => empty( $field['image'] ) ? null : array(
					'panel'     => 'content',
					'priority'  => 30,
					'groupName' => 'pictureLink',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'Link' ),
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
				)
			),
		),
	);
}

/**
 * The decoration groups every module gets.
 *
 * @return array<string, mixed>
 */
function module_attribute( bool $picture = false ): array {
	$decoration = array(
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
	);

	// A border and a shadow belong to the picture, not to the invisible box
	// around it — which is how Divi's own Image module declares them, and why
	// rounding the corners of a photograph on the module did nothing to the
	// photograph. For a picture field they move to the `image` element, and
	// leaving them here as well would put two identically named groups in the
	// panel, which is the trap the heading and the value already fell into.
	if ( $picture ) {
		unset( $decoration['border'], $decoration['boxShadow'] );
	}

	$advanced = array(
		'text' => array(),
		'link' => array(),
		'html' => array(),
	);

	// A picture carries its own link, so the module's would be a second group
	// called "Link" in the same panel — two identically named groups, and no
	// way to tell from the panel which one the picture obeys. Divi's own Image
	// module declares no module link for the same reason.
	if ( $picture ) {
		unset( $advanced['link'] );
	}

	return array(
		'type'     => 'object',
		'selector' => '{{selector}}',
		'settings' => array(
			'meta'       => array( 'meta' => array() ),
			'advanced'   => $advanced,
			'decoration' => $decoration,
		),
	);
}

/**
 * The picture itself, as a thing a designer can style.
 *
 * Copied in shape from Divi's own Image module: `fit`, `border` and `boxShadow`
 * on a selector that reaches the `img`, declared as empty objects so that Divi
 * generates the groups exactly as it does for its own. None of these names
 * collide with anything left on the module, so they need no naming of their own.
 *
 * @return array<string, mixed>
 */
function image_attribute(): array {
	$image = '{{selector}} .cscs-field__image';

	return array(
		'type'        => 'object',
		'elementType' => 'image',
		'selector'    => $image,
		'settings'    => array(
			'decoration' => array(
				'fit'       => array(),
				'border'    => array(),
				'boxShadow' => array(),
			),
		),
		// Declaring the settings is only half of it: without `styleProps` Divi
		// knows the fields belong to the picture and still has nowhere to write
		// their CSS, so every one of them accepts a value and does nothing. The
		// selectors are named rather than left to the default for the same
		// reason Divi names its own — a border on a picture belongs on the
		// picture, not on whatever happens to wrap it.
		'styleProps'  => array(
			'selector'  => $image,
			'fit'       => array( 'selector' => $image ),
			'border'    => array( 'selector' => $image ),
			'boxShadow' => array( 'selector' => $image ),
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
 * Every element that carries styles has to say what kind of element it is.
 * Divi's builder decides from `elementType` which style components an attribute
 * gets; an attribute without one is styled correctly by PHP on the page and
 * silently by nothing at all in the builder, which is how a design can look
 * saved and applied and still not show until the page is reloaded. Divi's own
 * modules name a heading `heading` and a body of text `content`, and so do
 * these.
 *
 * @param string $selector Where the styles land.
 * @param string $attr     Attribute name.
 * @param string $group    Which design group the settings belong to.
 * @param string $label    What the settings call this element.
 * @param string $type     Divi's name for this kind of element.
 * @return array<string, mixed>
 */
function element_attribute( string $selector, string $attr, string $group, string $label, string $type ): array {
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
		'type'        => 'object',
		'elementType' => $type,
		'selector'    => $selector,
		'settings'    => array(
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

	$add = static function ( string $key, array $item, string $group = 'content' ) use ( &$items, &$priority ): void {
		$items[ $key ] = array(
			'groupType' => 'group-item',
			'item'      => array_merge(
				array(
					'groupSlug' => $group,
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

	if ( ! empty( $field['image'] ) ) {
		$add(
			'imageSize',
			array(
				'label'       => 'Size',
				'description' => 'Which of the sizes WordPress made of this picture to serve. Larger is not better: a portrait shown at 300 pixels costs the visitor nothing extra if 300 pixels is what is sent.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array( 'options' => new stdClass() ),
				),
			),
			'contentPicture'
		);

		$add(
			'imageAlt',
			array(
				'label'       => 'Alternative text',
				'description' => 'What the picture says to somebody who cannot see it. Empty means the name of the course or trainer, which is usually right.',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentPicture'
		);

		$add(
			'imageLink',
			array(
				'label'       => 'Links to',
				'description' => 'Where the picture takes a visitor who clicks it.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array(
						'options' => array(
							'none'   => array( 'label' => 'Nowhere' ),
							'post'   => array( 'label' => 'Its own page' ),
							'file'   => array( 'label' => 'The picture at full size' ),
							'custom' => array( 'label' => 'An address of your own' ),
						),
					),
				),
			),
			'contentPictureLink'
		);

		$add(
			'imageLinkUrl',
			array(
				'label'       => 'Address',
				'description' => 'Used when the picture links to an address of your own.',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentPictureLink'
		);

		$add(
			'imageLinkTarget',
			array(
				'label'       => 'Open in a new window',
				'description' => 'A new window is a surprise, so it is off unless somebody asks for it.',
				'component'   => array(
					'name' => 'divi/toggle',
					'type' => 'field',
				),
			),
			'contentPictureLink'
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

	if ( ! empty( $field['image'] ) ) {
		$advanced['imageSize']       = 'large';
		$advanced['imageAlt']        = '';
		$advanced['imageLink']       = 'none';
		$advanced['imageLinkUrl']    = '';
		$advanced['imageLinkTarget'] = 'off';
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
