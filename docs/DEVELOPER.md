# Developer documentation

> Status: this document is written ahead of the implementation and is the specification the code is built against. Sections marked *planned* describe intent; they become descriptive as each phase lands.

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
│   ├── Api/                        Client, Mapper, Normaliser
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
match: lesson.match_key === course.match_key AND lesson.stamp_from ∈ course.terms[].stamp
```

Names are not byte-identical between the two endpoints — course 1070 has `"113- Deskové hry…"` while its lessons have `"113-Deskové hry…"` — hence the normalisation. The timestamp equality makes a false positive essentially impossible.

Unmatched lessons surface on an admin screen for manual assignment; a manual assignment is permanent and is never overwritten by a later synchronisation. The match rate is reported on the Overview screen.

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

| Filter | Purpose |
|---|---|
| `cscs_api_request_args` | Modify `wp_remote_get()` arguments |
| `cscs_normalise_course` | Adjust a course record after normalisation |
| `cscs_normalise_lesson` | Adjust a lesson record after normalisation |
| `cscs_match_key` | Replace the matching key algorithm |
| `cscs_template_path` | Override template resolution |
| `cscs_price_format` | Change price formatting |
| `cscs_availability_state` | Change the thresholds behind availability states |
| `cscs_is_rental` | Decide whether a lesson counts as an external rental |

**Actions**

| Action | Fires |
|---|---|
| `cscs_before_sync` / `cscs_after_sync` | Around each synchronisation job |
| `cscs_sync_failed` | On failure, with the error |
| `cscs_course_updated` | After a course record changes |

## WP-CLI

```bash
wp cscs sync courses
wp cscs sync lessons [--from=<Ymd>] [--to=<Ymd>]
wp cscs match --report
wp cscs match --rebuild
wp cscs cache flush
wp cscs retention run
wp cscs doctor            # environment and configuration check
```

## Options

All options are prefixed `cscs_`. Settings are stored as a single serialised array under `cscs_settings`; display sets under `cscs_display_sets`; schema version under `cscs_schema_version`.
