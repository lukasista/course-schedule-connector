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

## Shortcode

```
[cscs_courses set="homepage-kids"]
[cscs_schedule set="week-hall-2"]
```

`set` is the slug of a display set. Individual attributes can override a set's values for one-off use, but the set is the intended interface.

## Block

`cscs/display` — a single block with a display-set picker in the sidebar, server-rendered through the same renderer.

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
| `/display-sets` | GET | `cscs_manage_content` |
| `/sync` | POST | `cscs_manage_design` |
| `/match/unmatched` | GET | `cscs_manage_content` |
| `/match/assign` | POST | `cscs_manage_content` |

Every route declares an explicit `permission_callback`.

## Capabilities

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

wp cscs makeup list
wp cscs makeup link "<activity name>" <course-id>   # 0 or omitted clears it
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

## Options

All options are prefixed `cscs_`. Settings are stored as a single serialised array under `cscs_settings`; display sets under `cscs_display_sets`; schema version under `cscs_schema_version`.
