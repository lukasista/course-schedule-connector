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
 * Returns the folder a field of a given context belongs in.
 *
 * The same map `CSCS\Divi\ModuleFolder::path()` holds, restated here because
 * this script runs on its own with nothing of the plugin loaded. Two copies of
 * three strings, and the alternative is bootstrapping WordPress to build a
 * JSON file.
 *
 * @param string $context Field context.
 * @return string
 */
function folder_for( string $context ): string {
	$folders = array(
		'course'  => 'cscs-courses',
		'kind'    => 'cscs-kinds',
		'trainer' => 'cscs-trainers',
	);

	return $folders[ $context ] ?? 'cscs-courses';
}

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
		'moduleIcon'           => $field['moduleIcon'],
		'category'             => 'module',
		// Divi's module list is alphabetical and long; thirty-two modules
		// spread through it are not a set anybody can find. This is the same
		// key WooCommerce's modules carry to get their own shelf — three of
		// them, because somebody in the builder is designing a trainer's page,
		// or a kind of course, or a course, and wants the fields of that one
		// thing rather than all of them. Three side by side and not three
		// inside one: Divi drops a folder that holds only subfolders, and
		// everything under it goes with it.
		'folder'               => folder_for( (string) $field['context'] ),
		'attributes'           => array_filter(
			array_merge(
				array(
					'module' => module_attribute( ! empty( $field['image'] ) ),
					// No heading, no attribute for one. The order matters as
					// well as the presence: a test holds the styled attributes
					// against `Fields::style_elements()`, which answers the
					// same question in the same order.
					'title'  => \CSCS\Render\Fields::heads( $field )
						? element_attribute( '{{selector}} .cscs-field__label', 'title', 'designHeadingText', 'Heading', 'heading' )
						: array(),
					// Three ways for the value to be a thing a designer styles.
					// On most fields it is the value, and it is called that. On
					// a field that *is* a heading — the name of a course, the
					// name of a trainer — it is the heading, and calling it the
					// value in the panel is asking somebody to translate. And
					// on a button there is no such thing: the button is styled
					// as a button, and the box it sits in is not a second set
					// of text settings to go looking through.
					'value'  => value_attribute( $field ),
					'image'  => empty( $field['image'] ) ? array() : image_attribute(),
				),
				bullet_attributes( $field ),
				signup_attributes( $field ),
				table_attributes( $field ),
				array(
					'field' => field_attribute( $field ),
					'css'   => array( 'type' => 'object' ),
				)
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
				// Where the columns of a table sit. Its own group for the same
				// reason the filters have one: the order of the columns and
				// what the module is pointed at are two questions.
				'contentColumns'    => array() === (array) ( $field['columns'] ?? array() ) ? null : array(
					'panel'     => 'content',
					'priority'  => 25,
					'groupName' => 'columns',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'Columns' ),
					),
				),
				// Which of the courses under this heading to show. Its own
				// group, because "what this module is pointed at" and "which of
				// what it found to print" are two questions, and running them
				// together makes a panel of nine controls nobody reads.
				'contentCourses'    => empty( $field['filters'] ) ? null : array(
					'panel'     => 'content',
					'priority'  => 20,
					'groupName' => 'courses',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'Which courses' ),
					),
				),
				// What the way into iSport says. The address is the course's
				// and is not offered: a field somebody can type into and
				// nothing reads is worse than no field.
				'contentLink'       => empty( $field['signup'] ) ? null : array(
					'panel'     => 'content',
					'priority'  => 20,
					'groupName' => 'link',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'Link' ),
					),
				),
				// A plain link is styled as text; a button is styled by Divi's
				// own Button group, which the attribute below brings with it
				// and which needs no group declared here.
				'designSignupLink'  => 'link' === ( $field['signup'] ?? '' ) ? array(
					'panel'         => 'design',
					'priority'      => 15,
					'groupName'     => 'signupLink',
					'multiElements' => true,
					'component'     => array(
						'name'  => 'divi/composite',
						'props' => array(
							'groupLabel'        => 'Link',
							'clipboardCategory' => 'style',
						),
					),
				) : null,
				'contentPictureLink' => empty( $field['image'] ) ? null : array(
					'panel'     => 'content',
					'priority'  => 30,
					'groupName' => 'pictureLink',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array( 'groupLabel' => 'Link' ),
					),
				),
				// Where the heading and the value stand relative to each
				// other. First in the panel, as it is in Divi's own Icon List,
				// because it is the question somebody opens the Design tab
				// with.
				'designLayout'      => array(
					'panel'     => 'design',
					'priority'  => 5,
					'groupName' => 'designLayout',
					'component' => array(
						'name'  => 'divi/composite',
						'props' => array(
							'groupLabel'        => 'Layout',
							'clipboardCategory' => 'style',
							'presetGroup'       => 'divi/layout',
						),
					),
				),
				// Two design groups, named apart. Left to Divi's own naming
				// both typography groups come out called "Module Text", and a
				// panel with two identically named groups in it is a panel
				// nobody can use.
				'designHeadingText' => ( ! \CSCS\Render\Fields::heads( $field ) && empty( $field['headline'] ) ) ? null : array(
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
				'designValueText'   => 'content' !== ( value_attribute( $field )['elementType'] ?? '' ) ? null : array(
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
			) + bullet_groups( $field ) + table_groups( $field ),
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
		// What the two halves of a field do relative to each other, in the
		// place a Divi user already looks for it: Divi's own Layout group —
		// flex, grid, direction, alignment, wrapping, gaps, per breakpoint —
		// rather than the smaller version the plugin used to draw in the
		// content panel, which is where this lived and where nobody designing
		// a page thinks to look.
		//
		// Declared the long way rather than as an empty object, which would
		// also work. The long way is Divi's own Icon List, and it buys three
		// things the short way does not: the group is called Layout rather
		// than whatever Divi would have named it, it sits at the top of the
		// Design panel where that module puts it, and `presetGroup` is what
		// lets a layout preset saved elsewhere apply here.
		'layout'     => array(
			'groupType' => 'group-item',
			'item'      => array(
				'groupSlug' => 'designLayout',
				'priority'  => 10,
				'render'    => true,
				'component' => array(
					'type'  => 'group',
					'name'  => 'divi/layout',
					'props' => array( 'grouped' => false ),
				),
			),
		),
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
		'type'       => 'object',
		'selector'   => '{{selector}}',
		'settings'   => array(
			'meta'       => array( 'meta' => array() ),
			'advanced'   => $advanced,
			'decoration' => $decoration,
		),
		// Everything else on the module belongs on the module: a background, a
		// border and a margin are drawn around the whole of it. The layout is
		// the exception, because a flex container arranges its own children and
		// the module's only child is the field. Pointed at the module the
		// controls would all work and none of them would show. Pointed here
		// they arrange the heading and the value, which is what somebody
		// opening a group called Layout is trying to do.
		'styleProps' => array(
			'layout' => array( 'selector' => '{{selector}} .cscs-field' ),
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
function element_attribute( string $selector, string $attr, string $group, string $label, string $type, array $groups = array() ): array {
	$groups = array() === $groups ? array( 'font' => 'divi/font', 'spacing' => 'divi/spacing' ) : $groups;

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

	$decoration = array();
	$priority   = 10;

	foreach ( $groups as $property => $component ) {
		$decoration[ $property ] = $item( $component, $property, $priority );
		$priority               += 10;
	}

	return array(
		'type'        => 'object',
		'elementType' => $type,
		'selector'    => $selector,
		'settings'    => array( 'decoration' => $decoration ),
	);
}

/**
 * The value, as whatever kind of thing this field's value is.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function value_attribute( array $field ): array {
	$selector = '{{selector}} .cscs-field__value';

	if ( 'button' === (string) ( $field['signup'] ?? '' ) ) {
		return array();
	}

	if ( ! empty( $field['headline'] ) ) {
		return element_attribute( $selector, 'value', 'designHeadingText', 'Heading', 'heading' );
	}

	return element_attribute( $selector, 'value', 'designValueText', 'Value', 'content' );
}

/**
 * The way into iSport, as the thing a page builder already knows how to style.
 *
 * This is the second attempt and the first one was wrong. It was one module
 * with a switch on it reading "button or plain link", and the switch changed a
 * class name and nothing else: the design panel went on offering the groups a
 * text module offers, there was no Button group to reach, and Divi's button
 * presets had nothing to attach to. A switch that appears to do nothing is a
 * switch that does nothing, whatever the markup says.
 *
 * So there are two modules, and each is the shape it claims to be. The button
 * declares `elementType: button` with Divi's own `decoration.button`, exactly
 * as Divi's Call To Action declares the button inside itself — which is what
 * summons the whole Button panel: text, background, border, icon, hover, and
 * the presets that go with it. It also carries `et_pb_button`, so the button
 * styling set for the site as a whole reaches it without anybody restating it.
 *
 * The link declares nothing of the sort, and that is the point: it is an
 * anchor in the text, styled by the theme's link styling, with a font group of
 * its own for a designer who wants to say otherwise.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function signup_attributes( array $field ): array {
	$shape = (string) ( $field['signup'] ?? '' );

	if ( 'button' === $shape ) {
		// Named the way every other part of these modules is named, and it took
		// three wrong turns to get back here.
		//
		// `body #page-container` in front of it was bought to outrank the button
		// styling Divi writes for the site. It is not needed: Divi splices its
		// own wrapper chain in front of a selector that begins with
		// `{{selector}}`, which comes out as `.et-db #et-boc .et-l .cscs_…
		// .cscs-button.et_pb_button` — one id and four classes — against the
		// `body.et-db #et-boc .et-l .et_pb_button` it has to beat, which is one
		// id, three classes and an element. Four classes beat three.
		//
		// And the prefix cost the two places whose markup it does not describe:
		// a theme builder template, where the splice put `.et-db` inside
		// `#page-container` and matched nothing, and the builder's canvas.
		// Naming `customPostTypeSelector` to repair the first only moved the
		// problem — there it is `{{baseSelector}}` that gets substituted, and
		// in the canvas that resolves to a base order class Divi never puts on
		// the element. Asking the canvas `a.matches()` against the rule Divi
		// emitted answered false for every declaration in it.
		$selector = '{{selector}} .cscs-button.et_pb_button';

		return array(
			'button' => array(
				'type'        => 'object',
				'selector'    => $selector,
				'elementType' => 'button',
				'styleProps'  => array(
					'selector'   => $selector,
					// Marked important, and the reason is a specificity our
					// selector cannot reach. Divi writes the site's button
					// styling at `body.et-db #et-boc .et-l .et_pb_button` — one
					// id, three classes and an element — and on the page it
					// splices the same wrapper chain onto ours, which then wins
					// on class count. In the builder's canvas it splices nothing,
					// so ours stands at three classes and no id and loses every
					// property that rule also sets: the background, the radius,
					// the weight, the size.
					//
					// That is why a gradient or an image showed in the canvas
					// while a plain colour did not — nothing competes for
					// `background-image`, and `background-color` was being
					// beaten. An id of our own is not the way out: the one place
					// to put it is in front, and a prefix there is what breaks
					// the splice on the page.
					//
					// Divi's own Button marks its spacing important and four of
					// its font properties, for the same kind of reason.
					'background' => array( 'important' => true ),
					'border'     => array( 'important' => true ),
					'font'       => array( 'important' => true ),
					'spacing'    => array( 'important' => true ),
					// The Button group's own alignment, which belongs on the box
					// around the button rather than on the button, the way Divi
					// routes its own to the button's wrapper.
					'button'     => array(
						'propertySelectors' => array(
							'desktop' => array(
								'value' => array( 'text-align' => '{{selector}} .cscs-field__value' ),
							),
						),
					),
				),
				'settings'    => array(
					'decoration' => array(
						'background' => array(),
						'border'     => array(),
						'boxShadow'  => array(),
						'button'     => array(
							'component' => array(
								'props' => array( 'dynamicSubgroupHost' => true ),
							),
						),
						'font'       => array(),
						'sizing'     => array(),
						'spacing'    => array(),
					),
				),
			),
		);
	}

	if ( 'link' === $shape ) {
		return array(
			'signupLink' => element_attribute(
				'{{selector}} .cscs-signup-link',
				'signupLink',
				'designSignupLink',
				'Link',
				'content',
				array(
					'font'      => 'divi/font',
					'spacing'   => 'divi/spacing',
					'border'    => 'divi/border',
					'boxShadow' => 'divi/box-shadow',
				)
			),
		);
	}

	return array();
}

/**
 * The parts of a table, as things a designer can style.
 *
 * A field that draws a table has more in it than a heading and a value, and
 * until now none of it could be reached from the builder: the heading row, the
 * cells, the links inside them, the banding behind them and the width of each
 * column. They are declared the way Divi declares its own sub-elements, so the
 * panel that appears is Divi's, in the language the rest of the builder speaks.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function bullet_attributes( array $field ): array {
	if ( empty( $field['bullets'] ) ) {
		return array();
	}

	// The words of an item and the mark in front of them are two decisions.
	// The mark is styled through `::marker`, which is what it is — colouring
	// the item would colour the words with it.
	return array(
		'bulletItem'   => element_attribute( '{{selector}} .cscs-field__value li', 'bulletItem', 'designBulletItem', 'Bullet item', 'content', array( 'font' => 'divi/font', 'spacing' => 'divi/spacing' ) ),
		'bulletMarker' => element_attribute( '{{selector}} .cscs-field__value li::marker', 'bulletMarker', 'designBulletMarker', 'Bullet mark', 'content', array( 'font' => 'divi/font' ) ),
	);
}

/**
 * The design groups a list's parts appear in.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function bullet_groups( array $field ): array {
	if ( empty( $field['bullets'] ) ) {
		return array();
	}

	$groups   = array();
	$priority = 29;

	foreach ( array( 'designBulletItem' => 'Bullet item', 'designBulletMarker' => 'Bullet mark' ) as $key => $label ) {
		$groups[ $key ] = array(
			'panel'         => 'design',
			'priority'      => $priority,
			'groupName'     => lcfirst( substr( $key, strlen( 'design' ) ) ),
			'multiElements' => true,
			'component'     => array(
				'name'  => 'divi/composite',
				'props' => array(
					'groupLabel'        => $label,
					'clipboardCategory' => 'style',
				),
			),
		);

		++$priority;
	}

	return $groups;
}

/**
 * The parts of a table, as things a designer can style.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function table_attributes( array $field ): array {
	$columns = (array) ( $field['columns'] ?? array() );

	if ( array() === $columns ) {
		return array();
	}

	$text = array(
		'font'       => 'divi/font',
		'spacing'    => 'divi/spacing',
		'background' => 'divi/background',
		'border'     => 'divi/border',
	);

	$attributes = array(
		'tableHead'   => element_attribute( '{{selector}} .cscs-table thead th', 'tableHead', 'designTableHead', 'Table heading', 'heading', $text ),
		'tableCell'   => element_attribute( '{{selector}} .cscs-table tbody td', 'tableCell', 'designTableCell', 'Table cell', 'content', $text ),
		'tableLink'   => element_attribute( '{{selector}} .cscs-table tbody a', 'tableLink', 'designTableLink', 'Table link', 'content', array( 'font' => 'divi/font' ) ),
		'tableStripe' => element_attribute( '{{selector}} .cscs-table tbody tr:nth-child(even)', 'tableStripe', 'designTableRow', 'Banded row', 'wrapper', array( 'background' => 'divi/background' ) ),
	);

	foreach ( $columns as $column ) {
		$name = column_attribute( (string) $column );

		$attributes[ $name ] = element_attribute(
			'{{selector}} .cscs-table .cscs-col-' . $column,
			$name,
			'design' . ucfirst( $name ),
			column_label( (string) $column ) . ' column',
			'content',
			array(
				'font'   => 'divi/font',
				'sizing' => 'divi/sizing',
			)
		);
	}

	return $attributes;
}

/**
 * The design groups a table's parts live in.
 *
 * One group per part, and one per column. Left in a single group they would be
 * fifty controls under one heading, each distinguishable only by the words "of
 * the Price column" trailing after it.
 *
 * @param array<string, mixed> $field Field definition.
 * @return array<string, mixed>
 */
function table_groups( array $field ): array {
	$columns = (array) ( $field['columns'] ?? array() );

	if ( array() === $columns ) {
		return array();
	}

	// Divi's own design groups sit at multiples of ten and the first of them
	// is not far above thirty, so these go in the gap between the value's
	// typography and Divi's — one apart, in a block, rather than every tenth
	// and interleaved with Border, Box Shadow and Filters.
	$priority = 31;
	$groups   = array();

	$add = static function ( string $key, string $label ) use ( &$groups, &$priority ): void {
		$groups[ $key ] = array(
			'panel'         => 'design',
			'priority'      => $priority,
			'groupName'     => lcfirst( substr( $key, strlen( 'design' ) ) ),
			'multiElements' => true,
			'component'     => array(
				'name'  => 'divi/composite',
				'props' => array(
					'groupLabel'        => $label,
					'clipboardCategory' => 'style',
				),
			),
		);

		++$priority;
	};

	$add( 'designTableHead', 'Table heading' );
	$add( 'designTableCell', 'Table cell' );
	$add( 'designTableLink', 'Table link' );
	$add( 'designTableRow', 'Banded row' );

	foreach ( $columns as $column ) {
		$add( 'design' . ucfirst( column_attribute( (string) $column ) ), column_label( (string) $column ) . ' column' );
	}

	return $groups;
}

/**
 * Returns the attribute name a column's settings live under.
 *
 * The catalogue's own answer, so that the generator and the renderer cannot
 * disagree about what a column is called.
 *
 * @param string $column Column key.
 * @return string
 */
function column_attribute( string $column ): string {
	return \CSCS\Render\Fields::column_attribute( $column );
}

/**
 * Returns what a column is called.
 *
 * @param string $column Column key.
 * @return string
 */
function column_label( string $column ): string {
	return \CSCS\Render\Fields::column_label( $column );
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

	// Every setting here describes a heading — whether to show one, what it
	// says, what element it is, what follows it, and how far it sits from the
	// value. A field with no heading gets none of them: a control that cannot
	// change anything is read, tried and disbelieved, and the panel it sits in
	// is one somebody is trying to work in.
	$heads = \CSCS\Render\Fields::heads( $field );

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

	if ( $heads ) {
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
	}

	// A field that is itself a heading is the one place H1 belongs, and the one
	// place it was missing: the name of a course is the title of the page it is
	// on, and a page's title is an H1. The rest keep the shorter list, because
	// an H1 over a price is not a thing anybody meant to ask for.
	$tags = ! empty( $field['headline'] )
		? array(
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
		)
		: array(
			'div'    => array( 'label' => 'DIV' ),
			'p'      => array( 'label' => 'P' ),
			'span'   => array( 'label' => 'SPAN' ),
			'strong' => array( 'label' => 'STRONG' ),
			'h2'     => array( 'label' => 'H2' ),
			'h3'     => array( 'label' => 'H3' ),
			'h4'     => array( 'label' => 'H4' ),
		);

	$add(
		'valueTag',
		array(
			'label'       => ! empty( $field['headline'] ) ? 'Heading element' : 'Value element',
			'description' => 'Which HTML element the value is.',
			'component'   => array(
				'name'  => 'divi/select',
				'type'  => 'field',
				'props' => array( 'options' => $tags ),
			),
		)
	);

	if ( $heads ) {
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
	}

	if ( ! empty( $field['bullets'] ) ) {
		$add(
			'bulletStyle',
			array(
				'label'       => 'Bullets',
				'description' => 'What a list inside this text is marked with. Left alone, whatever the theme says.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array(
						'options' => array(
							''            => array( 'label' => 'As the theme says' ),
							'disc'        => array( 'label' => 'Round' ),
							'circle'      => array( 'label' => 'Hollow' ),
							'square'      => array( 'label' => 'Square' ),
							'decimal'     => array( 'label' => 'Numbered' ),
							'lower-alpha' => array( 'label' => 'Lettered' ),
							'none'        => array( 'label' => 'None' ),
						),
					),
				),
			)
		);
	}

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

	if ( ! empty( $field['signup'] ) ) {
		$add(
			'linkText',
			array(
				'label'       => 'Link text',
				'description' => 'What the link says. Left empty, the wording set in iSport → Settings is used.',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentLink'
		);
	}

	// Where each column sits. The catalogue's order is what the places start
	// as, so a table nobody has rearranged comes out as it always did; nought
	// leaves a column out, which is the same answer as "do not show it" and one
	// control rather than two.
	foreach ( (array) ( $field['columns'] ?? array() ) as $index => $column ) {
		$add(
			\CSCS\Render\Fields::column_order_attribute( (string) $column ),
			array(
				'label'       => \CSCS\Render\Fields::column_label( (string) $column ),
				'description' => 'Where this column sits, counting from one. Nought leaves it out.',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentColumns'
		);
	}

	// The wording of the way into a course, for a table that offers one. It
	// names the column and the link alike, because a column headed "Details"
	// whose cells said something else would be two names for one thing.
	if ( in_array( 'detail', (array) ( $field['columns'] ?? array() ), true ) ) {
		$add(
			'detailText',
			array(
				'label'       => 'Wording of the details link',
				'description' => 'What the column is called and what each link says. Left empty, "Details".',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentCourses'
		);
	}

	if ( ! empty( $field['filters'] ) ) {
		$add(
			'filterGenders',
			array(
				'label'       => 'Who the course is for',
				'description' => 'Left alone, everybody. A course whose name says nothing about this is never shown by a setting that asks.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array( 'options' => new stdClass() ),
				),
			),
			'contentCourses'
		);

		$add(
			'filterLevels',
			array(
				'label'       => 'At what level',
				'description' => 'Left alone, every level.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array( 'options' => new stdClass() ),
				),
			),
			'contentCourses'
		);

		$add(
			'filterAgeMin',
			array(
				'label'       => 'Age from',
				'description' => 'Leaves out courses that finish below this age. Empty means no floor.',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentCourses'
		);

		$add(
			'filterAgeMax',
			array(
				'label'       => 'Age to',
				'description' => 'Leaves out courses that start above this age. Empty means no ceiling.',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentCourses'
		);

		$add(
			'filterSort',
			array(
				'label'       => 'Order by',
				'description' => 'Left alone, the order the courses are filed in.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array(
						'options' => array(
							''       => array( 'label' => 'As they are filed' ),
							'name'   => array( 'label' => 'Name' ),
							'start'  => array( 'label' => 'When it starts' ),
							'price'  => array( 'label' => 'Price' ),
							'places' => array( 'label' => 'Places free' ),
						),
					),
				),
			),
			'contentCourses'
		);

		$add(
			'filterOrder',
			array(
				'label'       => 'Which way',
				'description' => 'Up or down.',
				'component'   => array(
					'name'  => 'divi/select',
					'type'  => 'field',
					'props' => array(
						'options' => array(
							'asc'  => array( 'label' => 'Ascending' ),
							'desc' => array( 'label' => 'Descending' ),
						),
					),
				),
			),
			'contentCourses'
		);

		$add(
			'filterLimit',
			array(
				'label'       => 'At most',
				'description' => 'How many rows to print. Empty or zero means all of them.',
				'component'   => array(
					'name' => 'divi/text',
					'type' => 'field',
				),
			),
			'contentCourses'
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
		'valueTag'  => 'div',
		'emptyText' => '',
	);

	if ( \CSCS\Render\Fields::heads( $field ) ) {
		$advanced = array(
			'source'    => '',
			'showLabel' => $field['heading'] ? 'on' : 'off',
			'label'     => '',
			'labelTag'  => 'h3',
			'valueTag'  => 'div',
			'separator' => '',
			'gap'       => '',
			'emptyText' => '',
		);
	}

	if ( 'list' === $field['kind'] ) {
		$advanced['listStyle'] = 'disc';
	}

	if ( ! empty( $field['bullets'] ) ) {
		$advanced['bulletStyle'] = '';
	}

	if ( ! empty( $field['signup'] ) ) {
		$advanced['linkText'] = '';
	}

	if ( in_array( 'detail', (array) ( $field['columns'] ?? array() ), true ) ) {
		$advanced['detailText'] = '';
	}

	foreach ( array_values( (array) ( $field['columns'] ?? array() ) ) as $index => $column ) {
		$advanced[ \CSCS\Render\Fields::column_order_attribute( (string) $column ) ] = (string) ( $index + 1 );
	}

	if ( ! empty( $field['filters'] ) ) {
		$advanced['filterGenders'] = '';
		$advanced['filterLevels']  = '';
		$advanced['filterAgeMin']  = '';
		$advanced['filterAgeMax']  = '';
		$advanced['filterSort']    = '';
		$advanced['filterOrder']   = 'asc';
		$advanced['filterLimit']   = '';
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

	// The Button panel needs somewhere to write. Every module in Divi's own
	// library that declares `elementType: button` ships this default and not
	// one of them goes without it — which is the whole of the evidence, and
	// enough of it: the panel appeared, took settings, and saved none of them,
	// and the page this was tested on had no `button` key in any of the modules
	// it had been tried on. It is the same trap the source field fell into and
	// the same fix. The value is Divi's, character for character, so the icon
	// behaves the way it does on a Divi button.
	if ( 'button' === (string) ( $field['signup'] ?? '' ) ) {
		$defaults['button'] = array(
			'decoration' => array(
				'button' => array(
					'desktop' => array(
						'value' => array( 'icon' => array( 'enable' => 'on' ) ),
					),
				),
			),
		);
	}

	foreach ( $advanced as $key => $value ) {
		$defaults['field']['advanced'][ $key ] = array(
			'desktop' => array( 'value' => $value ),
		);
	}

	return $defaults;
}
