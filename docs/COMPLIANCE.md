# WordPress.org compliance

How this plugin satisfies each of the eighteen [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/). This document is maintained alongside the code; a change that affects a row below must update the row.

| # | Guideline | Status | How it is satisfied |
|---|---|---|---|
| 1 | GPL-compatible licence | ✅ | Plugin is GPL-2.0-or-later. Every bundled asset is GPL-compatible; no third-party PHP library is bundled at runtime. Build tooling is dev-only and never shipped. |
| 2 | Developer responsibility | ✅ | A single maintainer is accountable; no guideline is circumvented by design. |
| 3 | Stable version on WordPress.org | ⚠️ | Resolved by shipping two builds — see [Distribution](#distribution) below. |
| 4 | Human-readable code | ✅ | No obfuscation, no minified-only PHP. Source is public at [`lukasista/course-schedule-connector`](https://github.com/lukasista/course-schedule-connector) and linked from the readme, which also means the GitHub build needs no access token. The repository is named for the plugin slug, so a checkout is never mistaken for a differently named plugin. |
| 5 | No trialware | ✅ | No feature is paywalled, time-limited or restricted to a sandbox. |
| 6 | Third-party services permitted when disclosed | ✅ | The iSport System dependency is disclosed in a dedicated `== External services ==` section of `readme.txt`, naming the exact endpoints, the data retrieved, when requests occur, and who operates the service. |
| 7 | No unauthorised tracking | ✅ | No analytics, no telemetry, no phone-home. The only outbound host is the one the administrator enters, and nothing is sent until they enter it. **No visitor data leaves the site** — requests carry no personal data, cookies or identifiers, and are never made from a visitor's browser. |
| 8 | No executable code from external sources | ⚠️ | Resolved by shipping two builds — see [Distribution](#distribution) below. The plugin evaluates no remote code in either build; only the update mechanism differs. |
| 9 | Legal and ethical conduct | ✅ | No illegal or dishonest functionality. |
| 10 | No unauthorised credits | ✅ | No "Powered by" link, no credit anywhere in front-end output. |
| 11 | Respect the admin dashboard | ✅ | The only admin notice is a dismissible failure warning after three consecutive synchronisation errors, shown on the plugin's own screens. No upsells, no review nags. |
| 12 | No readme spam | ✅ | Five tags (`courses`, `schedule`, `timetable`, `booking`, `sports`), no competitor names, no affiliate links, no keyword stuffing. |
| 13 | Use WordPress libraries | ✅ | HTTP through the WordPress HTTP API, scheduling through WP-Cron, escaping and sanitising through core functions. No bundled HTTP client, no bundled jQuery. |
| 14 | Limit SVN commits | ✅ | Development happens on GitHub; SVN receives tagged releases only. |
| 15 | Increment version numbers | ✅ | Enforced in CI: the release workflow fails unless the git tag, the `Version:` header and `Stable tag:` all match. |
| 16 | Complete at submission | ✅ | Submission happens only after every item in [`RELEASE-CHECKLIST.md`](RELEASE-CHECKLIST.md) is ticked and both sign-offs are given. |
| 17 | Respect trademarks | ✅ | Slug is `course-schedule-connector` — no trademark as the first or sole term. Display name uses the permitted "*Feature* for *Brand*" form. `README.md` carries an explicit non-affiliation notice for both iSport System and Divi. |
| 18 | Directory maintenance rights | ✅ | Acknowledged. |

## Distribution

Guidelines 3 and 8 make a self-updating GitHub distribution incompatible with a WordPress.org listing. Rather than choosing one, the release workflow produces two artefacts from the same source:

| Artefact | `includes/Updater/` | `Update URI` header | Distributed via |
|---|---|---|---|
| `course-schedule-connector.zip` | present | `https://github.com/lukasista/course-schedule-connector` | GitHub Releases, self-updating |
| `course-schedule-connector-wporg.zip` | **removed at build time** | absent | WordPress.org SVN |

The updater is an isolated namespace with no other component depending on it, so its removal cannot affect plugin behaviour. This is verified by the Plugin Check job in CI, which runs against the WordPress.org build.

## Divi as an optional dependency

Divi is a commercial plugin and is not in the WordPress.org directory, so the `Requires Plugins` header cannot reference it — that header only accepts WordPress.org slugs.

Divi is therefore a **soft dependency**:

- Core functionality is delivered through a shortcode and a block, which work in any theme.
- Divi module registration is wrapped in a capability check for the Divi 5 module API and never runs when Divi is absent.
- No Divi code, asset or trademark is bundled.
- Deactivating Divi does not break the site: the shortcode and block continue to render.

## Generic usefulness

The plugin is not hardcoded to a single installation. The base URL is an administrator setting, the plugin makes no request before that setting has a value, and the room and activity mapping screens let any iSport System customer adapt it to their own tabs and naming.

## Privacy

The plugin stores no personal data about site visitors, sets no cookie, and writes no personal data to the database. Trainer names retrieved from the remote system are business contact information published by the gym itself and are displayed only as the gym publishes them.

A suggested privacy-policy paragraph is registered through `wp_add_privacy_policy_content()` so that it appears in the site's privacy policy guide.

## Pre-submission routine

The full acceptance gate is [`RELEASE-CHECKLIST.md`](RELEASE-CHECKLIST.md) — ten sections covering automated gates, security, guidelines, functional verification, permissions, accessibility, performance, internationalisation, documentation and a clean debug pass, ending in a developer and a site-owner signature.

Nothing is submitted until every item is ticked and evidenced by a CI run, a screenshot or a log.
