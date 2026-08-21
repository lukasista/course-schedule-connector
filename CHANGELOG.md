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

[Unreleased]: https://github.com/lukasista/wpdissi/commits/main
