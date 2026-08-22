# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Repository scaffolding: licence, documentation, coding standards, CI and release automation.
- Release checklist: the acceptance gate that must be fully satisfied before any submission to WordPress.org.
- Plugin bootstrap with its own PSR-4 autoloader, so the plugin ships without a Composer runtime dependency.
- API client for the iSport System endpoints, with bounded retries, a hard hourly request ceiling, a failure circuit breaker, and a cache that serves stale data rather than an empty page.
- Base URL validation that refuses plain http, credentials, non-standard ports, and any address in loopback, private, link-local or other reserved space.
- Payload mapper and type normalisation for both endpoints, including the course-to-class match key.
- WP-CLI commands `wp cscs api courses`, `wp cscs api lessons` and `wp cscs api doctor`.
- Unit tests with fixtures for normalisation, URL validation, mapping, every client guard, matching and the storage round trip.
- Data model: the `cscs_course` post type with room, trainer, activity and tag taxonomies, plus tables for class occurrences and the synchronisation log.
- Matching of class occurrences to courses on the normalised name and the term timestamp, with an accent-stripped fallback, permanent manual assignments, and a reported success rate that excludes hall rentals but not orphaned course lessons.
- Rooms of a course derived from the occurrences it actually runs in, rather than from the single room name the course record carries.
- Scheduled synchronisation: the course list, a near window of occurrences, a far window to the end of the term, and daily retention. Every job queries forward in time only.
- Retention: occurrences are kept for a configurable window behind today and then removed; courses are never deleted, only closed.
- WP-CLI: `wp cscs sync courses|lessons|rematch|unmatched|assign|retention`.
- `uninstall.php`, which removes data only when the administrator opted into that in the settings.
- The non-bookable list is actually declared among the defaults. It was added to the validation branch but not to `defaults()`, so the matcher was handed an empty list for a whole run while its own tests passed — they built the list by hand rather than reading the setting. Two tests now cover the wiring: one asserts the list has real content, and one asserts that every declared default is readable.
- Activities that occupy a slot in the timetable but take no bookings — courses run by outside lecturers, make-up lessons, individual training — are reported as their own category rather than as failures. They are tagged as courses, because that is what they are, and no course record will ever exist for them, so nothing in the data distinguishes them; the list is a setting, seeded with the activities Jojo Gym publishes as taking no registrations, and compared loosely so that "Zdravé cvičení" also covers "Zdravé cvičení s overbaly". A real course still wins over the list.
- A course is now indexed under both of its names. The API documents that `activity_name` "may be an alternative name" and the timetable uses either, so indexing only one lost every course that goes by two — six of them on the first live run.
- Whether an unmatched occurrence is a problem is decided by the tag the API already carries rather than inferred from the absence of a trainer and a price. Course lessons are tagged "Kurz" and rentals "Pronájem haly", so the record says outright what it is; the old heuristic misfiled drop-in classes and make-up lessons, which have both a trainer and a price and belong to no course by design. The tag identifier differs between installations, so the label is compared, and the list is filterable through `cscs_course_lesson_tags`.
- `wp cscs sync unmatched` shows each occurrence's tags, which is the first thing worth knowing about one.
- Unmatched occurrences were stored as `id_course = 0` while every query looked for `NULL`, so the synchronisation reported unresolved occurrences and the listing command found none — both reading the same rows. `$wpdb->prepare()` turns a null bound to `%d` into zero, which made the intended NULL unreachable. The column is now `NOT NULL DEFAULT 0` and zero means "no course", asserted in a test rather than assumed.
- A schema change now applies on the next request instead of waiting for the plugin to be deactivated and reactivated.
- The matching report groups unresolved occurrences by activity name. A weekly course that matched nothing produces one name rather than a dozen apparent problems, which is the difference between a number to worry about and a list to act on.
- Settings are written through a validating setter and seeded with their defaults on activation, so there is always something to read and edit. WP-CLI: `wp cscs settings list|get|set`.

### Fixed
- Continuous integration: the first run failed every job. `npm ci` had no lock file to install from and there was no JavaScript to build, `phpunit/phpunit` and `phpstan/phpstan` were missing from the development requirements, and PHPStan was pointed at a `templates` directory that does not exist yet.
- Coding standards: every violation reported by PHP_CodeSniffer against the `WordPress`, `WordPress-Docs` and `PHPCompatibilityWP` rulesets, verified locally against the same tool versions the pipeline resolves.
- Static analysis: WP-CLI stubs and the WordPress time constants are now declared, so PHPStan resolves every symbol at level 6.
- The failure circuit no longer punishes a repair. Changing a setting that affects requests — the base URL, a timeout, the retry or ceiling values — closes the circuit and drops the cached responses, and `wp cscs api reset` does the same on demand. Previously, correcting a bad base URL meant being told for the next half hour that requests were paused.
- The client no longer refuses a response because of its `Content-Type`. The iSport endpoints are hand-written PHP that announces JSON as `text/html`, so the check rejected the live system while accepting nothing extra: the real protection was always that the body decodes to an array. A body opening with a tag is now reported as an error page rather than as a JSON syntax error, and a leading byte order mark no longer defeats decoding.
- `readme.txt` no longer promises screenshots that are not in the repository, and declares the current WordPress release.
- `yoast/phpunit-polyfills` removed: it caps PHPUnit at 9 and is only needed by the WordPress core test suite, which this plugin does not use.
- Plugin Check now runs against a build of the WordPress.org artefact rather than the repository, with the slug supplied explicitly, so it sees what would actually be submitted instead of the development tree.
- The redundant PHP version guard is gone. WordPress enforces `Requires PHP` itself and refuses to activate a plugin the server cannot run, so the check was an unreachable branch that static analysis rightly flagged.

### Changed
- The repository is renamed to match the plugin slug, so a checkout directory is never mistaken for the plugin name.
- Every quality gate now runs even when an earlier one fails, and each writes its output to the run summary. One push therefore reports every problem at once, and the results are readable from the run page without downloading an artifact.

[Unreleased]: https://github.com/lukasista/course-schedule-connector/commits/main
