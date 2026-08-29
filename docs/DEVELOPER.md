# Developer documentation

> Status: written ahead of the implementation and used as the specification the code is built against. Phases F1 and F2 have landed, so the API layer, the data model and synchronisation below are descriptive; everything from the renderer onwards is still specification.

## Implemented so far (phases F1 and F2)

| Component | Class | Notes |
|---|---|---|
| Autoloader | `CSCS\Autoloader` | PSR-4 over `includes/`. No Composer runtime dependency. |
| Bootstrap | `CSCS\Plugin` | Lazy service container. The minimum PHP version is declared in the plugin header and enforced by WordPress, which refuses to activate a plugin the server cannot run; the plugin deliberately does not duplicate that check. |
| Settings | `CSCS\Settings` | One option, typed access, base URL validated on read. |
| Base URL validation | `CSCS\Support\Url` | SSRF guard. WordPress-free, unit-tested. |
| Type normalisation | `CSCS\Support\Normalise` | WordPress-free, unit-tested. |
| Transport | `CSCS\Api\Http`, `CSCS\Api\WpHttp` | Interface plus a `wp_remote_get()` implementation with certificate verification on. |
| Client | `CSCS\Api\Client` | Retries, ceiling, breaker, cache, decoding. |
| Guards | `CSCS\Api\RateLimiter`, `CSCS\Api\CircuitBreaker` | |
| Mapping | `CSCS\Api\Mapper`, `CSCS\Api\Dto\*` | Typed records. |
| Cache | `CSCS\Cache\Store` | Transients with stale-while-revalidate. |
| CLI | `CSCS\Cli\ApiCommand`, `SyncCommand`, `SettingsCommand` | `wp cscs api`, `wp cscs sync`, `wp cscs settings`. |
| Roles | `CSCS\Admin\Capabilities` | Role `cscs_manager`; capabilities checked on save, not by hiding fields. |
| Admin menu | `CSCS\Admin\Menu` | One menu holding everything the plugin owns. |
| Overview | `CSCS\Admin\Screen\Overview` | What is stored, connection state, recent runs, manual actions. |
| Settings | `CSCS\Admin\Screen\SettingsPage` | Writes through the validating setter. |
| Schema | `CSCS\Data\Schema` | Versioned tables through `dbDelta()`, upgraded on the next request after a version bump. `id_course = 0` means no course: `$wpdb->prepare()` cannot bind a real NULL to `%d`. |
| Post type | `CSCS\Data\PostType` | `cscs_course` plus four taxonomies. |
| Course storage | `CSCS\Data\CourseRepository` | Never deletes, never overwrites a locked field. |
| Occurrence storage | `CSCS\Data\LessonRepository` | Idempotent bulk upsert keyed on the remote occurrence id; also holds the manual course assignments and the make-up links. |
| Serialisation | `CSCS\Data\LessonPayload` | Store and restore, unit-tested round trip. |
| Matching | `CSCS\Sync\Matcher`, `MatchResult`, `Assignment` | WordPress-free, unit-tested. |
| Synchronisation | `CSCS\Sync\Synchroniser` | Course list, occurrence windows, re-matching. |
| Schedule | `CSCS\Sync\Scheduler` | Four jobs, all querying forward in time. |
| Retention | `CSCS\Sync\Retention` | Purges occurrences, closes finished courses. |
| Log | `CSCS\Sync\Logger` | Counts, durations and error codes only. |

### Guarantees the data layer makes

- A course is never deleted. It moves from `running` to `finished` to `archived`.
- A field recorded in `_cscs_locked_fields` is never overwritten by a synchronisation.
- An occurrence write is idempotent: the same window can be synchronised repeatedly without duplicates, because the remote occurrence id is the primary key.
- A manual assignment recorded through `wp cscs sync assign` survives every later run.
- No job ever asks the remote system about a date that has passed.
- Two runs never overlap; the second is skipped and logged.
- A setting is validated wherever it is written. The base URL passes the same guard from the command line as it will from the admin screen, so there is no back door that stores an address the client would then refuse.

### Guarantees the client makes

- No request is attempted without a validated base URL.
- No request is attempted while the circuit is open or the hourly ceiling is spent.
- A transport error is retried; a malformed payload is not, because it will be malformed again.
- When a request fails and stale data exists, the stale data is returned instead of an exception.
- A response is only decoded if the status is 200 and the body decodes to an array. The content type is deliberately not part of that judgement: these endpoints label JSON as `text/html`, so the label proves nothing. A body opening with a tag is reported as an error page.

## Contents

- [Naming and prefixes](#naming-and-prefixes)
- [Directory layout](#directory-layout)
- [Data model](#data-model)
- [Synchronisation](#synchronisation)
- [Course ↔ lesson matching](#course--lesson-matching)
- [Rendering and template overrides](#rendering-and-template-overrides)
- [Shortcode](#shortcode)
- [Block](#block)
- [The trainer page](#the-trainer-page)
- [Fields, blocks and field modules](#fields-blocks-and-field-modules)
- [Divi 5 modules](#divi-5-modules)
- [REST API](#rest-api)
- [Capabilities](#capabilities)
- [Hooks](#hooks)
- [WP-CLI](#wp-cli)
- [Options](#options)

## Naming and prefixes

| Kind | Prefix | Example |
|---|---|---|
| PHP namespace | `CSCS\` | `CSCS\Api\Client` |
| Functions, options, transients, hooks | `cscs_` | `cscs_sync_courses` |
| Constants | `CSCS_` | `CSCS_VERSION` |
| Post type | `cscs_` | `cscs_course` |
| Taxonomies | `cscs_` | `cscs_room` |
| Tables | `{$wpdb->prefix}cscs_` | `wp_cscs_lessons` |
| CSS classes | `cscs-` | `cscs-table__cell` |
| Text domain | — | `course-schedule-connector` |

## Directory layout

```
course-schedule-connector/
├── course-schedule-connector.php   Bootstrap: headers, constants, autoload, activation
├── uninstall.php                   Optional data removal
├── includes/
│   ├── Autoloader.php              PSR-4 autoloader
│   ├── Plugin.php                  Bootstrap and service container
│   ├── Settings.php                Typed settings access
│   ├── Api/                        Client, transport, mapper, guards, DTOs
│   ├── Sync/                       Synchroniser, Matcher, Scheduler, Retention
│   ├── Data/                       CourseRepository, LessonRepository, Schema
│   ├── Admin/                      Screens, DisplaySets, Settings, Permissions
│   ├── Render/                     Renderer, Formatter, Table, Calendar
│   ├── Integration/Divi/           Module registration (loaded only when Divi 5 is present)
│   ├── Integration/Block/          Block registration
│   ├── Rest/                       Routes
│   ├── Cli/                        WP-CLI commands
│   └── Updater/                    GitHub updater — removed from the WordPress.org build
├── templates/                      Overridable output templates
├── assets/                         Source and compiled CSS/JS
├── visual-builder/                 React sources for the Divi modules
├── languages/                      .pot and compiled translations
├── tests/
└── docs/
```

## Data model

### Post type `cscs_course`

Public, with an archive and single template. Enrichment fields live alongside the synchronised values so that a manual edit is never silently overwritten.

| Meta key | Type | Source |
|---|---|---|
| `_cscs_id_course` | int | API `id_course` |
| `_cscs_price` | string decimal | API `price` |
| `_cscs_number_lessons` | int | API `number_lessons` |
| `_cscs_capacity`, `_cscs_capacity_waiting` | int | API |
| `_cscs_occupied`, `_cscs_available`, `_cscs_available_waiting` | int | API |
| `_cscs_date_from`, `_cscs_date_to` | `Y-m-d` | API |
| `_cscs_stamp_from`, `_cscs_stamp_to` | int | API |
| `_cscs_course_url` | url | API |
| `_cscs_color`, `_cscs_background` | hex | API, validated |
| `_cscs_image`, `_cscs_trainer_image` | url | API |
| `_cscs_rating` | string | API |
| `_cscs_terms` | JSON | API `terms[]` |
| `_cscs_api_description` | string | API `course_description` |
| `_cscs_status` | enum | `running` / `finished` / `archived` |
| `_cscs_show_isport_button` | enum | `inherit` / `always` / `never` |
| `_cscs_gender` | enum | `girls` / `boys` / `mixed` / `women` / `men`, read from the course's name or set by hand |
| `_cscs_age_from`, `_cscs_age_to` | decimal string | The ages the course is for, likewise. No `_to` means no upper age |
| `_cscs_level` | enum | `beginner` / `improver` / `advanced` / `competitive`, likewise |
| `_cscs_locked_fields` | JSON array | Fields edited by hand, protected from synchronisation |
| `_cscs_synced_at` | int | Timestamp |

Taxonomies: `cscs_room` (**multi-value**, derived from the course's lessons), `cscs_trainer`, `cscs_activity`, `cscs_tag`.

### Table `{$wpdb->prefix}cscs_lessons`

Primary key `id_activity_term`. Indexes on `stamp_from`, `id_tab`, `date`, `canceled`, `id_course`, `match_key`.

Class occurrences are machine data with a short useful life and are deliberately kept out of `wp_posts`: a semester is roughly six thousand rows, which would bloat the post table and slow every query on the site.

### Table `{$wpdb->prefix}cscs_sync_log`

Timestamp, job type, record count, duration, outcome, error message. Retained ninety days.

## Synchronisation

Three scheduled jobs, all of which query **forward in time only**:

| Job | Range | Default interval |
|---|---|---|
| `cscs_sync_courses` | full course list | 10 minutes |
| `cscs_sync_lessons_near` | today → +21 days | 15 minutes |
| `cscs_sync_lessons_far` | +21 days → end of semester | daily |

A single request returns capacity alongside everything else, so there is no separate availability call.

Protections: a transient mutex, a response hash that skips writes when nothing changed, a circuit breaker after three consecutive failures, a hard hourly request cap, and adaptive backoff when the relevant pages have had no traffic.

Records that disappear from the API are **soft-deleted** — marked, never removed — so that manual enrichment survives.

## Course ↔ lesson matching

The API provides no shared identifier. `activities.php` carries no `id_course`, and `id_activity` is **not** the identity of a course: on 11 September 2026, `id_activity = 368` appears once as *"33-Gymnastika 7-9 let dívky I. pololetí"* at 14:00 and again as *"37-Gymnastika 9-11 let dívky I. pololetí"* at 15:00. It identifies a schedule slot.

The working join key is `activity_name` + `stamp`:

```
match_key = normalise( activity_name )
normalise: trim → collapse whitespace → casefold → (fallback: strip diacritics)
index:  every course under match_key( activity_name ) and match_key( course_name )
match:  lesson.match_key ∈ index AND lesson.stamp_from ∈ course.terms[].stamp
```

Names are not byte-identical between the two endpoints — course 1070 has `"113- Deskové hry…"` while its lessons have `"113-Deskové hry…"` — hence the normalisation. The timestamp equality makes a false positive essentially impossible.

Classification of an unmatched occurrence asks three sources in order: the curated list of activity names that take no bookings (`non_bookable_activities`), then the tag map (`tag_categories`), then a last-resort guess from whether the record carries a trainer and a price.

The list comes first deliberately. A tag maintained in the remote system would be the better authority in principle, but in this installation the tags do not separate the cases: an outside lecturer's course is labelled "Pronájem haly" in one place and "Open lekce" in another, and the gym also rents halls to the public under the same label. A tag that means two things cannot overrule a list that means one. If the remote system is ever tagged consistently, moving the tag map ahead of the list is a one-line change.

Unmatched occurrences fall into five buckets: `no_candidate` (a rental or open session, recognised by its tag), `not_bookable` (an activity on the non-bookable list — an outside lecturer's course, a make-up lesson, individual training), `ambiguous` or `stamp_mismatch` (a real problem), `makeup` (a replacement for a missed class), and `orphan` (tagged as a course but nothing matched — a real problem). Only `ambiguous`, `stamp_mismatch` and `orphan` count against the success rate.

Unmatched occurrences surface on an admin screen for manual assignment; a manual assignment is permanent and is never overwritten by a later synchronisation. The match rate is reported on the Overview screen.

**A course's rooms are derived from its matched lessons**, not from the API's `room_name`, because a course can run in several rooms at once. `room_name` is the fallback when a course has no matched lessons.

## Rendering and template overrides

`CSCS\Render\Renderer` is the only component that produces markup. The shortcode, the block, the Divi modules and the REST preview all call it.

Templates are resolved in this order:

1. `your-child-theme/course-schedule-connector/{template}.php`
2. `your-theme/course-schedule-connector/{template}.php`
3. `course-schedule-connector/templates/{template}.php`

Available templates: `courses-grid.php`, `courses-table.php`, `schedule-list.php`, `schedule-calendar.php`, `course-single.php`, plus partials under `templates/partials/`.

## Rendering

One path, three entrances. `Renderer::render()` is what the shortcode, the block and the builder module all call, so a listing cannot look one way in an editor preview and another on the page.

```
DisplaySet ─▶ Query ─▶ rows ─▶ Listing ─▶ template ─▶ HTML
                                  ▲
                             Formatter
```

- **`Query`** reads what the set asks for: courses through `get_posts()` (they are posts), classes through one prepared statement over the plugin's table (a term of several hundred classes filtered by room and date is what indexes are for). Both are capped at 1000 rows whatever the set says. `Query::course_times()` answers "which weekday and hour does this course meet" for the whole listing in one query, and ignores a slot that occurred once — a substitution is not the day a course runs on.
- **`Formatter`** holds the decisions a visitor reads and nothing else: the price format, whether a course counts as full, and whether the booking button appears. It touches neither WordPress nor the database, so the rules are tested as rules.
- **`Listing`** is what a template receives, with every decision already made — including which columns survived: `Listing::used_columns()` drops any column empty in every row (a pure rule, tested as one), and keeps them all when there are no rows to judge by — cell text, cell markup, row classes, the wording for empty and full. A template that only arranges things cannot break a rule by being rewritten.
- **Templates** live in `templates/` and are looked up in the theme first, under `course-schedule-connector/` or `jojo-isport/` (filter `cscs_template_directories`). `Renderer::locate()` falls back to the plugin's own copy, and accepts one directory level (`partials/table`) with each part reduced to the characters a template name may hold.

Two filters are worth knowing: `cscs_listing_rows` sees the rows before they are rendered, and `cscs_template_directories` decides where a theme's overrides are looked for.

### Browsing a listing

What a set decides and what a visitor chooses are two objects. `DisplaySet` says what a listing is; `ListingArgs` says where in it somebody currently is — page, week offset, room. Every value in the second arrives from a URL, so all three are clamped (`page` and `week` to ±60, `room` to a positive id), and changing week or room returns to the first page, because page four of last week means nothing on page four of this one. A visitor may narrow to a room the set already covers and cannot widen past it: a query string does not get to change what a listing is about.

The controls are ordinary links carrying the choice in the address — `?cscs_week=1&cscs_room=12` — so a listing works with no JavaScript at all. `assets/js/cscs.js` then intercepts them, fetches `GET /cscs/v1/listing` (public, since a listing is public) and swaps the markup in place, pushing the same address into history. Anything unexpected — a failed request, a reply with no listing in it — falls back to following the link. A control that only works once a script has loaded is a control that sometimes does not work, and a timetable is exactly what somebody opens on a bad connection.

### The fold

Below a configurable width every table stops being a table: each row becomes a card, each cell a two-column grid with its own heading down the left. The heading comes from `data-label` on the cell, printed by the template, and the rule that reveals it is generated by `Renderer::responsive_css()` — a media query takes a number, so a configurable breakpoint cannot live in a static stylesheet. The `<thead>` is hidden from sight but left for screen readers.

## Shortcode

```
[cscs_courses set="homepage-kids"]
[cscs_schedule set="week-hall-2"]
```

`set` is the slug of a display set. Individual attributes can override a set's values for one-off use, but the set is the intended interface.

## Block

`cscs/display`, one attribute: `set`. Registered from `blocks/display/block.json` with a `render_callback`, so the editor previews it through the block-renderer endpoint WordPress already has — no route of the plugin's own to secure, and the preview is the page rather than a drawing of it.

The editor script is plain browser JavaScript against the packages WordPress loads (`wp-blocks`, `wp-element`, `wp-components`, `wp-block-editor`, `wp-i18n`, `wp-server-side-render`). There is no build step, which is deliberate for a block that is one select box: a bundler here would mean a compiled file in the repository or a toolchain between a change and a working plugin. Its strings are translated through `wp_set_script_translations()`, which reads `languages/course-schedule-connector-{locale}-{md5}.json` where the hash is of the script's path relative to the plugin — `blocks/display/editor.js`.

`save()` returns null: nothing is written into post content. A listing saved as markup would be a snapshot of a Tuesday, wrong by Wednesday, and invisible to everyone until somebody reopened the page.

`Assets` registers the stylesheet once on `init` and adds the generated media query after it; the shortcode, the block and the builder module then only enqueue the handle. Registering is not enqueueing — the file reaches a page only where something renders a listing.


`cscs/display` — a single block with a display-set picker in the sidebar, server-rendered through the same renderer.

## The course page

`SingleCourse` filters `the_content` on a single `cscs_course` rather than replacing the template. A course is a post, so the theme already draws everything around it; taking the whole template over would mean fighting the theme for a layout it has. A theme that wants the page itself can still drop `course-schedule-connector/single-course.php` in, or a `single-cscs_course.php` of its own.

`CourseDetail` decides everything the page shows — the facts, already worded and with the empty ones dropped, the lecturer's contact, the button, and two listings: the course's own upcoming classes and the make-up classes tied to it. Those two are ordinary `Listing` objects built from sets made on the spot, so the timetable of one course folds on a telephone exactly like the timetable of all of them, because it is the same code and the same `partials/table.php`.

`structured_data()` prints a `Course` JSON-LD in `wp_head`: name, description, provider, the term as a `CourseInstance`, the room as a `Place`, and an `Offer` only when there is a real price. Nothing is claimed there that the page does not also say in words.

## The trainer page

`TrainerType` registers `cscs_trainer_profile` — twenty characters, which is exactly what WordPress allows, and deliberately not `cscs_trainer`: that name belongs to the taxonomy, and a post type sharing it would collide over the query variable. The taxonomy stays exactly as it was, because it is what display sets filter by.

Pairing is by `TrainerRepository::key()`, which is `Normalise::match_key()` — the same normalised name the class matcher uses, since it is the only identifier both sides have. The key is written onto the course in `CourseRepository::save()` **after** the meta loop, so a site that has locked the trainer name keeps the key of the name it actually shows; a key disagreeing with the name beside it would point the course at somebody else's page. `cscs_create_trainer_pages` switches off page creation while keeping the pairing.

The photograph is fetched with `download_url()` and `media_handle_sideload()` at synchronisation time and remembered by source URL, so it is fetched once. A featured image always wins over it. `wp cscs trainers backfill [--dry-run] [--photographs]` builds the pages from courses already stored.

## Fields, blocks and field modules

`Render\Fields` is the catalogue: one list of what a course and a trainer are made of, each entry naming its context, its kind (`text`, `list`, `html`), its title, its default heading and its icon. `Fields::value()` works out what a field says about a given post. Everything else is generated from it, so a field added there appears in the block inserter, in Divi's module list and in the tests at once.

`Render\FieldRenderer` turns one field and its settings into markup: a heading and a value, each with typography of its own. WordPress's block supports style a block as a whole and this block is two things, which is the whole reason for the second set. Every value is checked against a pattern or a list before it reaches a browser — `length()`, `colour()`, `variable()`, `one_of()` — because an attribute arrives from a saved post and "our own editor wrote it" is not a claim about safety. `var(--wp--preset--…)` is accepted by shape so the theme's own palette and type scale can be used.

`Render\FieldBlocks` registers one block per field with `register_block_type( $name, $args )`. There is no `block.json` and no directory per block because there is no per-block code; the editor script is one file that reads the localised catalogue and registers them all with a shared `edit`.

`Divi\FieldModules` does the same for Divi, which cannot be told about a module in code — it reads a directory holding `module.json`. Those are generated:

```
php tools/build-divi-modules.php
```

The generator loads `Fields::all()` directly, standing up the two functions and the constant it needs, so there is one list rather than two that drift. `FieldModulesTest` fails if the generated directories and the catalogue disagree.

Two things about the module metadata that are not obvious. The heading and the value are declared as attributes with selectors of their own (`{{selector}} .cscs-field__label` and `…__value`) and each font and spacing group is assigned to a **named** group — `designHeadingText`, `designValueText`. Left to Divi's own naming both typography groups come out called "Module Text", and a design panel with two identically named groups in it is a panel nobody can use. And the source field is called `field.advanced.source`, never `set`, for the reason recorded below.

### One folder, and an icon each

Every generated module carries `folder: "cscs-modules"`, the same key WooCommerce's modules carry to get their own shelf, and the builder is told what that folder is by `divi.moduleLibrary.registerFolder`. The definition is `Divi\ModuleFolder`; both builder scripts register it, because either may load first and registering the same folder twice registers the same folder.

The icon of each module is `moduleIcon` in the catalogue, and the names are Divi's own — `divi/module-pricing-table`, `divi/module-countdown-timer`, and so on. There is no public way to add an icon to Divi's set: `divi.iconLibrary` exposes no registration, so a drawing of our own would mean reaching into the theme's internals for a picture. The registered modules' `moduleIcon` values are the list of what is available, readable from the builder with `divi.data.select( 'divi/module-library' ).getModules()`.

### The parts of a table

A field that draws a table declares its columns in the catalogue, and everything follows from that list. `Fields::style_elements()` answers what parts the field has — the heading and the value always, the picture where there is one, and for a table its heading row, its cells, its links, its banding and one attribute per column. That one answer is used by the generator to declare the attributes, by `FieldModuleRenderer::module_styles()` to emit their CSS, and by the builder script, which reads it back off the metadata as "every attribute that declares an `elementType`". A test asserts the three agree.

The blocks cannot do it the same way. The heading and the value are one element each and take an inline style; a table is drawn by a shared template that knows nothing about this block's settings. So the block writes rules, scoped to a class named after the settings themselves — two tables designed alike share one class, and a table nobody has designed writes nothing.

Custom properties with defaults in the stylesheet would have been tidier and are wrong: a rule like `.cscs-table a { color: var(--…) }` exists whether or not anybody set the property, and an unset custom property does not fall back to the theme's own rule. It falls back to nothing, and every link in every table loses the colour the theme gave it.

### Making the builder show the design

A module can register, open, offer every design setting, store every value and render every one of them correctly on the page while the builder's canvas never changes. Nothing errors; there is simply no CSS. Two things cause it, and both are silent.

**Every attribute that carries styles must declare an `elementType`.** Divi decides from it which style components an attribute gets — an attribute without one gets none, and `elements.style( { attrName } )` renders nothing for it. PHP does not ask, which is why the page is right and only the builder is wrong. Divi's own names are the ones to use: a heading is `heading`, a body of text is `content`, a picture is `image` (`imageLink` when the picture is itself the link), a wrapper is `wrapper`. Divi's module definitions are readable on any site running it, at `/wp-content/themes/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<module>/module.json`, and are the fastest way to check what a version actually expects.

**The order class has to be on the elements before the styles are asked for.** Divi sets it when it renders a module's styles on its own; inside the edit tree it has not done it yet, and the rules come out as ` .cscs-field__label` — beginning with a space, belonging to nothing. `visual-builder/cscs-divi-fields.js` calls `setBaseOrderClass`, `setOrderClass` and `setModuleNameClass` from `metadata.moduleOrderClassName` and the module's id first, then renders `elements.style()` for the module, the heading, the value and the picture inside a `StyleContainer`, as a child of `ModuleContainer`. That is where the style tag lands for Divi's own modules too.

The module also registers the same renderer as `renderers.styles`, which is the documented place for it. Divi does not call that for a module registered from a plugin — it wraps it and never asks — so the call in `edit` is the one that reaches the canvas; the registration is there so the module stops being wrong the day Divi does ask.

`FieldModulesTest::test_every_styled_element_declares_its_kind` is the guard for the first half. There is no test for the second: it is one call site, and the only honest test is opening the builder.

## Divi 5 modules

One module, `cscs/divi-display`, with one content field: the display set. Registered the way Divi registers its own — `divi_module_library_modules_dependency_tree` hands over a `DependencyInterface` object whose `load()` calls `ModuleRegistration::register_module()` with `divi/cscs-display/module.json` and a render callback. The callback wraps the plugin's own renderer in `Module::render()`, so Divi contributes the classnames, the design styles and the custom CSS while nothing about the listing's content is decided there.

Every hook used fires only under Divi 5, and the two classes that name Divi's own (`ModuleDependency`, `ModuleRenderer`) are autoloaded only from inside those hooks. Without Divi the plugin is untouched — which is both a WordPress.org requirement and the reason the shortcode and the block exist.

The Visual Builder component is plain browser JavaScript against the globals Divi exposes, registered on the `divi.moduleLibrary.registerModuleLibraryStore.after` action.

**Use `window.vendor.React`, not `window.React`.** Divi renders the builder with its own copy of React 18.2, exposed at `window.vendor.React`; WordPress's copy is a different one, and a component built from it may not call a hook — React keeps hook state per copy, and the mismatch surfaces as the builder's "something went wrong" panel with nothing in it to say why. The same applies to `window.vendor.wp.hooks` and `window.vendor.wp.i18n`. This component uses no hooks at all regardless: the preview is filled through a ref, which is plain React and cannot be got wrong. The element remembers which set it asked about in a data attribute, so a reply arriving late cannot overwrite a newer one.

**Never name an attribute `set`.** Divi keeps a module's attributes in seamless-immutable objects, whose own API includes `set`, `setIn`, `get`, `getIn`, `merge`, `without` and `asMutable`. An attribute named after one of those collides with it: Divi drops the attribute from the module (the store's metadata simply loses it), and every choice made in its field is refused with `getIn(...).setIn is not a function`. The field renders perfectly throughout, so nothing looks wrong. The attribute here is `listing`.

**Ship `module-default-render-attributes.json` beside `module.json`.** Divi writes a chosen value into the structure the defaults describe; with no default for a field there is nothing to write into, and the field refuses every choice in the same silent way. The server reads the file itself, and the same file is handed to the builder as `metadata.defaults` and passed to `registerModule` as `defaultAttrs`, so both sides start from one definition.

**A setting is not an element's content.** Divi reserves `innerContent` for what an element itself contains — the words in a heading, the code in a code module — and such an attribute carries `elementType`, `tagName` and an inline editor. Anything else is a setting and belongs under `settings.advanced` with a three-part `attrName` (`set.advanced.id`), the way `content.advanced.dateTime` does in the countdown timer. Declared under `innerContent`, a setting renders its field perfectly and then refuses every choice made in it, because the builder has nowhere to write the value.

A select whose options include one with an empty value cannot be committed — picking it has nothing to save, and the field then refuses every later choice too. The set options therefore start at the first real set. Divi's own tutorial reads the same globals; the only thing its example needs a build for is JSX. The metadata is read from `module.json` in PHP, has this site's display sets put into the select options, and is handed over with `wp_localize_script()` after Divi has enqueued the package — the handle is the package name and does not exist before that.

The preview inside the builder is fetched from `GET /cscs/v1/preview?set=…` (`edit_posts`) and inserted as HTML. A module that draws itself in React holds its markup twice, once in PHP for the page and once in JavaScript for the builder, and the two drift; a listing has one definition.

### The design guard

`DesignGuard` filters `wp_insert_post_data`. When the person saving lacks `cscs_manage_design`, every `cscs/divi-display` block in the content keeps the attributes it was stored with, except the ones a site manager owns — today just `set`. `css` counts as design, being a stylesheet by another name.

A module with no stored counterpart is saved with no design at all rather than with whatever the builder put in it. Modules are matched between the old and new content by document order, since Divi writes no identifier into the saved markup: reordering therefore moves design with the position rather than with the module, which is the safe way round — a site manager can shuffle design an administrator approved, and cannot invent any.

Hiding the design panels would be a courtesy. A builder is a browser application, and anything a browser decides can be undone in the browser, so the rule is applied where it cannot be got around.

## Divi 5 modules

| Module | Slug |
|---|---|
| Courses – cards | `cscs/courses-grid` |
| Courses – table | `cscs/courses-table` |
| Schedule – list by day | `cscs/schedule-list` |
| Schedule – weekly calendar | `cscs/schedule-calendar` |

Each module is a `module.json` describing attributes, a PHP render callback registered through `ModuleRegistration::register_module()`, and a React edit component. **The edit component fetches HTML from the REST preview route** rather than reimplementing the markup, so the Visual Builder and the front end cannot diverge.

Registration is guarded: if the Divi 5 module API is not present, nothing is registered and no error is raised.

## REST API

Namespace `cscs/v1`.

| Route | Method | Permission |
|---|---|---|
| `/courses` | GET | public — same data as the front end |
| `/lessons` | GET | public |
| `/render` | GET | `edit_posts` — Visual Builder and block preview |
| `/field` | GET | `edit_posts` — one field, for the Visual Builder |
| `/display-sets` | GET | `cscs_manage_content` |
| `/sync` | POST | `cscs_manage_design` |
| `/match/unmatched` | GET | `cscs_manage_content` |
| `/match/assign` | POST | `cscs_manage_content` |

Every route declares an explicit `permission_callback`.

## Admin screens

| Screen | Slug | Capability | What it is for |
| --- | --- | --- | --- |
| Overview | `cscs` | `cscs_manage_content` | What is stored, how the last runs went, requests this hour. Synchronising and clearing failure state need `cscs_manage_design`. |
| Courses | `edit.php?post_type=cscs_course` | post capabilities | Editorial content, contact, booking button, field locks; bulk button changes. |
| Display sets | `cscs-sets` | `cscs_manage_content` | What a listing shows: columns, filters, range, sorting, wording. |
| Rooms | `cscs-rooms` | `cscs_manage_content` | The name, order, colour and visibility a room has on the site. |
| Unmatched lessons | `cscs-unmatched` | `cscs_manage_content` | Permanent manual assignments, and a look at what was classified as belonging to nobody. |
| Make-up lessons | `cscs-makeup` | `cscs_manage_content` | Which course each make-up occurrence stands in for. |
| Settings | `cscs-settings` | `cscs_manage_design` | Connection, term, intervals, retention, display defaults, classification lists. |

Both make-up and unmatched carry a count in the menu label. Each is one indexed query on every admin page load, and both return zero before the schema exists, which is the state right after activation.

Course meta a person edits — `_cscs_show_button`, `_cscs_contact_name`, `_cscs_contact_email`, `_cscs_contact_phone`, `_cscs_contact_note` — is registered through `register_post_meta()` with a sanitiser and an `auth_callback` of `cscs_manage_content`, so the block and the REST API read it later from one definition rather than two. `_cscs_locked_fields` holds the field names `CourseRepository::save()` refuses to overwrite.

## Capabilities

Granted by `Capabilities::ensure()` on every boot, which returns immediately unless `cscs_capabilities_version` is behind `Capabilities::VERSION`. Raise that constant whenever a capability is added, or sites already running the plugin will never be granted it — activation happens once, and the admin menu is invisible without `cscs_manage_content`, with nothing to say why. `wp cscs caps list` prints the current state; `wp cscs caps install` grants them again.


| Capability | Granted to | Controls |
|---|---|---|
| `cscs_manage_content` | *iSport Manager*, Editor, Administrator | Display sets, course content, button visibility, manual matching |
| `cscs_manage_design` | Administrator | Design controls in Divi modules, plugin settings, manual synchronisation |

The design restriction is enforced when settings are saved, not only in the interface.

## Hooks

*Planned — the list below is the intended surface and is finalised in phase F4.*

**Filters**

| Filter | Purpose | Status |
|---|---|---|
| `cscs_api_request_args` | Modify `wp_remote_get()` arguments | implemented |
| `cscs_normalise_course` | Adjust a course record after normalisation | planned |
| `cscs_normalise_lesson` | Adjust a lesson record after normalisation | planned |
| `cscs_match_key` | Replace the matching key algorithm | planned |
| `cscs_template_path` | Override template resolution | planned |
| `cscs_price_format` | Change price formatting | planned |
| `cscs_availability_state` | Change the thresholds behind availability states | planned |
| `cscs_course_lesson_tags` | Tag labels that mark an occurrence as part of a course | implemented |
| `cscs_course_gender_terms` | Words in a course name that say who it is for | implemented |
| `cscs_course_level_terms` | Words in a course name that say what level it is | implemented |
| `cscs_course_rewrite_slug` | Change the URL slug of a course | implemented |

**Actions**

| Action | Fires | Status |
|---|---|---|
| `cscs_booted` | Once the plugin has booted and its services exist | implemented |
| `cscs_circuit_opened` | When repeated failures pause outbound requests | implemented |
| `cscs_before_sync` / `cscs_after_sync` | Around each synchronisation job | planned |
| `cscs_sync_failed` | On failure, with the error | planned |
| `cscs_course_saved` | After a course record is written | implemented |
| `cscs_setting_changed` | After one setting is written and validated | implemented |

## WP-CLI

Implemented:

```bash
wp cscs sync courses [--force]
wp cscs sync lessons [--from=<Ymd>] [--to=<Ymd>] [--force]
wp cscs sync rematch                                  # re-match stored data, no network request
wp cscs sync unmatched [--limit=<n>] [--format=<format>]
wp cscs sync list --status=matched|external|not_bookable|makeup|unresolved

wp cscs makeup list [--unlinked]                       # one row per make-up occurrence
wp cscs makeup link <term-id> <course-id>             # 0 or omitted clears it
                                                      # same job as iSport → Make-up lessons
wp cscs sync audience [--dry-run]                     # re-read gender and level from every course name
wp cscs sync assign <term> <course>                   # permanent manual assignment
wp cscs sync retention

wp cscs settings list                                 # every setting and its value
wp cscs settings set api_base_url https://example.com # validated the way the admin screens will
wp cscs settings get <key>

wp cscs api doctor                                    # configuration and connectivity
wp cscs api reset                                     # close the circuit, drop cached responses
wp cscs api courses [--date=<Ymd>] [--force] [--format=<format>]
wp cscs api lessons [--from=<Ymd>] [--to=<Ymd>] [--tab=<id>] [--limit=<n>] [--force] [--format=<format>]
```

Still planned:

```bash
wp cscs cache flush
```

## Measuring the plugin's memory cost

Divi 5 parses a generated metadata file of some forty thousand lines on every request, which can leave a site sitting close to its PHP ceiling before this plugin loads anything at all. Do not guess at the plugin's footprint — measure it:

```bash
WP=/path/to/wordpress
php -d memory_limit=512M "$(command -v wp)" --path="$WP" plugin deactivate course-schedule-connector
php -d memory_limit=512M "$(command -v wp)" --path="$WP" eval 'echo size_format( memory_get_peak_usage( true ) ), PHP_EOL;'
php -d memory_limit=512M "$(command -v wp)" --path="$WP" plugin activate course-schedule-connector
php -d memory_limit=512M "$(command -v wp)" --path="$WP" eval 'echo size_format( memory_get_peak_usage( true ) ), PHP_EOL;'
```

The difference is the plugin's cost. On a request that renders nothing, the plugin loads one file and registers one autoloader, so the difference should be negligible; anything else is a bug worth chasing.

## Translations

Every user-facing string goes through the `course-schedule-connector` text domain, and the plugin ships `languages/course-schedule-connector.pot` alongside a Czech translation (`-cs_CZ.po` and the compiled `.mo`). The domain is loaded on `init` at priority 5 — before the post type registers its labels at priority 10, and not earlier than `init`, which is what WordPress 6.7 onwards complains about.

Regenerating after changing or adding a string:

```bash
wp i18n make-pot . languages/course-schedule-connector.pot
wp i18n update-po languages/course-schedule-connector.pot languages/
wp i18n make-mo languages/ languages/
```

A string whose English is ambiguous carries a context: `_x( 'Beginners', 'level
of a course for girls or women', … )` and its mixed-group twin are two
translations of one word, and without the context they collapse into one. The
extractor records the context, the builder emits `msgctxt`, and the compiled
`.mo` keys the entry as `context \x04 msgid`, which is what gettext looks for.
A string listed by hand in `tools/i18n/cs.py` for a JavaScript file is skipped
when the extractor already found it in PHP; a catalogue that defines one string
twice is one gettext refuses to read.

Czech takes three plural forms, `nplurals=3; plural=(n==1) ? 0 : ((n>=2 && n<=4) ? 1 : 2);`, so every `_n()` call needs three. A string that reads well in English and awkwardly in Czech is a string worth rewording in both: the source text is not sacred.

Once the plugin is listed on WordPress.org, translations come from translate.wordpress.org and land in `WP_LANG_DIR/plugins`, which wins over anything shipped here. The bundled Czech file is what makes the admin readable before that happens.

## Courses made by hand

Not every course exists in iSport. `CourseRepository::create_manual()` writes one as an ordinary `cscs_course` post and gives it a course id of `MANUAL_ID_BASE + post_id` — a billion and up, where the remote system's low-thousands ids will never reach. Everything downstream is written in terms of course ids, so this is one code path rather than two.

Three consequences, each deliberate:

- `archive_missing()` skips manual ids. Absence from the remote list is not evidence about a course that was never in it.
- `Matcher::match()` takes the manual ids as a fourth argument and honours a manual assignment to one, but never indexes them by name: a manual course has no term list, so a name match would fail the timestamp check and report classes as failures that are correctly classified today.
- `CourseEditor::ensure_id()` runs on `save_post` at priority 5, so a course created through the WordPress editor gets its id before anything else looks for one.

## Who a course is for, and at what level

`CSCS\Data\Audience` reads a gender and a level out of a course's name and
nothing else, because there is nothing else: neither endpoint carries a field
for either, and the website this plugin replaces reads both off the name too.

- The vocabulary is a fixed, ordered list per key, compared against
  `Normalise::plain()` — the name lowercased with its diacritics removed, spaces
  and punctuation kept — with a whole-word pattern
  (`(?<![a-z0-9])word(?![a-z0-9])`). Keeping the spaces is the point: "mix" must
  not be found inside "mixáž", and "mírně pokročilí" is two words that belong
  together. Order matters, so "mírně pokročilí" is looked for before
  "pokročilí".
- What is stored is a key, never a word. `Audience::level_label( $level,
  $gender )` decides the word, because Czech agrees it with the group:
  "začátečnice" for girls and women, "začátečníci" otherwise. The two are one
  word in English, told apart by a gettext context — see *Translations*.
  `Audience::levels()` returns the masculine set, which is the form Czech uses
  where there is no group to agree with, such as a settings screen.
- `CourseRepository::derive_audience()` runs on every save, right after the
  trainer is paired, and writes nothing over a value a person chose: the course
  editor's two selects append their key to `_cscs_locked_fields`, and a locked
  key is left alone for good. Choosing *— podle názvu —* deletes the meta and
  the reading takes over again.
- The age is read the same way, in three shapes asked in order: a range
  ("9-11 let"), a floor with no ceiling ("od 10 let") and a single age
  ("4 roky"). The unit is what separates an age from the course's own number —
  "101-Lezení od 10 let" has two numbers in it — so nothing without `let` or
  `rok` after it counts. Halves are kept, because the gym runs courses for
  two-and-a-half-year-olds; they are stored with a full stop and rendered with
  whatever the language writes, which in Czech is a comma. The two ages lock
  together on the course editor: half a fact set by hand and half read from the
  name is a state with no screen to explain it on.
- `Fields` carries `course-age`, `course-gender` and `course-level` like any
  other field, so the block, the Divi module, the course page's facts and the
  listing column all come from the one catalogue.

## Rooms

`RoomMap` keeps label, order, colour and visibility per remote room id in the autoloaded option `cscs_rooms`, and stores only the rows somebody actually configured. `RoomMap::apply()` merges that with the rooms the stored timetable mentions — `LessonRepository::rooms()`, since no endpoint lists them — and sorts by order then by the name the site shows. Hidden rooms stay in the list; filtering them out belongs to whoever renders, or the screen that edits them could never show one again.

## The kind of a course

`CSCS\Data\CourseKind` reads what a page would call the card — "Gymnastika",
"Jojo přípravka", "Lezení" — out of the course's name, and the synchronisation
files the course under a `cscs_kind` term of that name.

It is deliberately not the `cscs_activity` taxonomy, which belongs to the remote
system: iSport sends the whole course name as `activity_name`, so that taxonomy
would hold one term per course and group nothing. (It is also, in this
installation, never assigned at all.)

The names have a shape, kept by whoever types them:

```
113-Deskové hry 9-13 let I. pololetí
^^^ ^^^^^^^^^^^ ^^^^^^^^ ^^^^^^^^^^^
nr. kind        age      term
```

with an audience and a level allowed between the age and the term. So the kind
is everything before the earliest of: an age in any of its three shapes, a word
from the audience vocabulary, a word from the level vocabulary, or the term. The
course's own number is cut from the front, and what is left is trimmed of any
dash or comma the cut left behind. Cutting at the first space instead — the
obvious shortcut — puts *Gymnastika pro radost* on the *Gymnastika* card, which
is two different courses under one heading.

A course has two names and the activity is asked first: iSport sends
`activity_name` beside the course's own, documented as "may be an alternative
name", and where the two differ it is the activity that says which group the
course belongs to. Course 25 is called *Gymnastika 4-6 let dívky pokročilé* and
its activity is *Jojo přípravka 4-6 let dívky pokročilé*; the old website
publishes it under Jojo přípravka. Fourteen of the 113 live courses have names
that differ at all, and all but that one differ by a stray space.

The vocabularies are `Audience`'s own, so a gym that words "girls" differently
teaches both readers at once; `cscs_course_kind_patterns` filters the whole list
of cut markers. Nothing is guessed beyond the cut: *Gymnastika pro dospělé* is
its own kind rather than *Gymnastika* for adults, because the name says so and
because merging two kinds by hand is possible where un-merging one is not. The
113 live courses fall into 25 kinds.

`cscs_kind` is on the course editor's lockable list, so a reading corrected by
hand survives every synchronisation. `wp cscs sync kinds [--dry-run]` re-files
the catalogue and prints one line per kind.

## What a kind costs, and what it says

`KindRepository::prevailing_description()` counts the identical
`_cscs_api_description` values among a kind's courses and answers with the most
common one; `fill_description()` writes it only onto a page whose content is
empty, and `wp cscs sync kinds` calls it for every page after filing. `Admin\KindEditor`
adds the panel that fetches one course's description on demand — a link with a
nonce through `admin-post.php`, not a form, because a form inside the block
editor's own form is invalid markup.

`KindDetail::price_listing()` pairs each course's price with the length of its
first class (`Formatter::minutes_between()`), keys the pair on both numbers and
keeps the first course of each — so twenty-two courses of *Gymnastika* answer
with two rows, not twenty-two — then sorts by length and price and hands the
chosen courses to an ordinary `Listing` of two columns, `duration` and `price`.
It is a table like every other one the plugin draws, so it words, folds and
designs itself the same way; the `duration` column is offered to every course
listing, not only a kind's. `course_listing()` does not use it, nor `price`:
they moved out when the prices got a table.

The narrowing is asked of the page first and the module second. `KindType::META_FILTER`
holds the page's answer (the keys in `KindRepository::FILTER_KEYS`, named as the
blocks name them) and `KindType::META_TERM` the term a page was pointed at by
hand, which `KindRepository::term_for()` prefers over the name — a page called
"Gymnastika dívky" pairs with no term by its title and is not meant to.
`KindDetail::asked()` then keeps only the settings a person actually filled in,
because a module sends every setting whether it was touched or not: an empty
string is not an answer, a limit of zero is "all of them", and a direction with
nothing to sort by is Divi's default rather than a decision. Without that rule a
theme builder template — one design for every page of a type — would shout its
own defaults over all twenty-six pages, which is the whole reason the filter is
on the page and not in the design. `Admin\KindEditor` renders both panels and the
**Copy** row action, which carries the words, the excerpt, the picture and the
filter across but never `META_KEY_NAME`: that key says "the page the
synchronisation made for this kind", and two pages claiming it is one too many.

`KindDetail::course_listing()` takes the block's or module's settings and
narrows the rows in memory — `filtered()` on gender, level and an age floor and
ceiling, `sorted()` on the keys a rendered row actually carries (`name`,
`date_from`, `price`, `available`, which are not the database columns a display
set sorts on), then a slice. A course that holds nothing for a thing being asked
about is left out rather than kept, as in `Query`. The controls come from
`'filters' => true` on the field, which `FieldRenderer::attributes()`,
`FieldModuleRenderer::settings()`, the Divi generator and `blocks/fields/editor.js`
each read; `KindFilterTest` covers the two helpers through reflection.

`kind-text` renders the page's own `post_content` through `Fields::written_text()`,
which is what `course-text` and `trainer-text` do — the field exists because the
two description fields are both about a course, so a kind's page had no way to
show its own words inside a theme builder template.

The list of posts a field module may be pointed at is built by walking
`Fields::all()` and asking `Fields::post_type()` per context, in `FieldModules`
and in `FieldBlocks` alike. It used to be a literal map of two contexts in each,
and the kinds were added to neither: `$sources[ $field['context'] ]` on a
missing key wrote `null` into the select's options, which Divi renders as "this
content cannot be displayed" in the settings panel — the front end was fine, so
nothing else showed it. `FieldModulesTest::test_every_context_has_a_post_type_to_be_pointed_at()`
is the guard.

## Where the blocks and the modules live

`Render\BlockCategory` registers one inserter category, `cscs`, on
`block_categories_all`, and both registrations name it: `FieldBlocks` through
`BlockCategory::SLUG` and `blocks/display/block.json` as a literal, which
`BlockMetadataTest` keeps in step. It is spliced in after the last category
WordPress ships rather than appended, because the end of that list is wherever
the last plugin to be activated left it. The Divi modules are a separate
arrangement and stay on `category: module` with `folder: cscs-modules` — they
are the builder's blocks, not the inserter's.

## Scheduling

`Scheduler::schedule()` runs on `init` as well as on activation, and registers
the `cron_schedules` filter before it schedules anything. Both matter, and the
second is the bug: `wp_schedule_event()` validates the recurrence against
`wp_get_schedules()`, a plugin being activated is not yet in the active list
when `plugins_loaded` fires in that request, so `Plugin::register()` had not
added the filter and the two jobs on plugin-declared intervals — `cscs_courses`
and `cscs_lessons` — were refused while the two `daily` ones were accepted. The
front end shows nothing when this happens; the pages render and the numbers stop
moving. An unknown recurrence now falls back to `hourly`, `reschedule()` puts a
job back after its interval setting changes (`Plugin::on_setting_changed()`), and
the overview screen lists all four with `wp_next_scheduled()`.

## A kind's own page

`CSCS\Data\KindType` registers `cscs_kind_page` — not `cscs_kind`, which is the
taxonomy and would collide over the query variable — and `KindRepository` pairs
the two on `Normalise::match_key_loose()`, the same key the trainers use. The
pairing survives a retitle, which somebody will do the first time a page wants
to read differently from the term.

`derive_kind()` calls `KindRepository::ensure()` as it files a course, so a page
exists as soon as its kind does; `wp cscs sync kinds` does the same for the
catalogue at once. Only the name is ever written, and only when the page is
made.

`KindDetail::course_listing()` builds the timetable from the term's courses
through the same `Listing` every other list of courses uses, which is what makes
it fold identically on a telephone. The `kind-courses` field renders it as a
block and a Divi module; `templates/single-kind.php` appends it to the page's
own words rather than replacing them.

## Markup from somewhere else

`CSCS\Support\Markup::flatten_lists()` puts every list one level deep: a list
inside a list item is lifted into the list above it and an item left empty is
dropped. iSport's rich-text box produces `<ul><li><ul><li>…` in 24 of the 113
live descriptions, which reads as two levels of bullets, and no stylesheet can
undo it — the second level is in the markup.

It parses with `DOMDocument`, and where that is missing (or the fragment has no
nesting at all) it hands the markup straight back, so the cheap case stays
cheap and the impossible case stays safe. Only structure moves; no text is
rewritten.

Fields whose value can hold a list carry `'bullets' => true` in the catalogue,
which adds `bulletStyle`, `bulletColour`, `bulletIndent` and `bulletGap` to a
block and declares `bulletItem` / `bulletMarker` as styled elements for Divi.
The mark is styled through `::marker`, which is what it is: colouring the item
would colour the words with it.

## Two descriptions

A course has two texts and neither is a fallback for the other inside one
field: `course-text` shows what was written in the WordPress editor,
`course-api` shows what iSport holds (`_cscs_api_description`). Each is a block
and a Divi module, so a design can place either, both or neither.

The remote text is HTML — lists, line breaks — and usually opens with a stray
`<br>`; `CourseDetail::api_description()` trims that and runs `wp_kses_post()`
over the rest. On the plugin's own course page the editor's content wins and the
iSport description fills in where there is none, which on this installation is
every course: none of the 113 has a word written in WordPress and 111 have a
description in iSport.

## Grouping courses under one name

A display set answers "what should this listing show", and until now it answered
it only with filters — rooms, trainers, activities, of which the last was always
empty. Ticking a kind is what builds a card. Beyond that a set carries:

| Field | Meaning |
|---|---|
| `kinds` | Kind term ids to keep, empty for all |
| `courses` | Post ids shown whatever the filters say |
| `exclude` | Post ids never shown, whatever else says |
| `genders`, `levels` | Audience keys to keep, empty for all |
| `age_min`, `age_max` | Ages to overlap, empty for no bound |

`Query::courses()` runs the filters on their own first (`fields => ids`), adds
the named courses, subtracts the excluded ones and hands the result to the real
query as `post__in` — so ordering and paging still happen in the database rather
than in PHP. A set that names courses and filters by nothing means those courses
and no others; without that rule it would mean those courses *plus the whole
catalogue*, which is the opposite of what anybody asked for. The filters-only
path is untouched, so a set written before any of this exists queries exactly as
it did.

Age matching is overlap, not containment: a set for 7 to 9 keeps the course for
6 to 8, because a seven-year-old could join it. A course with no age at all is
left out of an age-filtered set — it cannot be shown to match, and a wrong
course on a card is worse than a missing one.

## Display sets

A set is a `CSCS\Data\DisplaySet` value object: `from_array()` normalises anything shaped roughly like one, `to_array()` gives what is stored, and `catalogue()`/`sorts()` declare what a listing of each type may show and sort by. It never throws — an unrecognised column is dropped, a number out of range is clamped, an empty column list falls back to the full catalogue — because a set that half-loads renders a page that looks broken.

`DisplaySetRepository` keeps every set in one autoloaded option, `cscs_display_sets`, keyed by id. Ids are slugs, made from the name on creation and immutable afterwards: `[cscs_courses set="kurzy-pro-deti"]` is written by hand on a page, and a renamed id would empty it silently.

Adding a column means adding it to `DisplaySet::catalogue()`, to `Fields::column_labels()` — the one map the sets screen, the block sidebar and the Divi panel all read — and to `Listing::value()`. The first is what decides whether a stored value survives, so a column missing from it is dropped no matter what the other two say.

## Options

All options are prefixed `cscs_`. Settings are stored as a single serialised array under `cscs_settings`; display sets under `cscs_display_sets`; schema version under `cscs_schema_version`.
