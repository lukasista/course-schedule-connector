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
- Unit tests with fixtures for normalisation, URL validation, mapping and every client guard.

### Fixed
- Continuous integration: the first run failed every job. `npm ci` had no lock file to install from and there was no JavaScript to build, `phpunit/phpunit` and `phpstan/phpstan` were missing from the development requirements, and PHPStan was pointed at a `templates` directory that does not exist yet.
- Coding standards: every violation reported by PHP_CodeSniffer against the `WordPress`, `WordPress-Docs` and `PHPCompatibilityWP` rulesets, verified locally against the same tool versions the pipeline resolves.
- Static analysis: WP-CLI stubs and the WordPress time constants are now declared, so PHPStan resolves every symbol at level 6.
- `readme.txt` no longer promises screenshots that are not in the repository, and declares the current WordPress release.
- `yoast/phpunit-polyfills` removed: it caps PHPUnit at 9 and is only needed by the WordPress core test suite, which this plugin does not use.
- Plugin Check now runs against a build of the WordPress.org artefact rather than the repository, with the slug supplied explicitly, so it sees what would actually be submitted instead of the development tree.
- The redundant PHP version guard is gone. WordPress enforces `Requires PHP` itself and refuses to activate a plugin the server cannot run, so the check was an unreachable branch that static analysis rightly flagged.

### Changed
- The repository is renamed to match the plugin slug, so a checkout directory is never mistaken for the plugin name.
- Every quality gate now runs even when an earlier one fails, and each writes its output to the run summary. One push therefore reports every problem at once, and the results are readable from the run page without downloading an artifact.

[Unreleased]: https://github.com/lukasista/course-schedule-connector/commits/main
