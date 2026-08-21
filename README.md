# Course & Schedule Connector for iSport

[![License: GPL v2+](https://img.shields.io/badge/License-GPLv2%2B-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-21759b.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb4.svg)](https://www.php.net/)

A WordPress plugin that displays courses and class schedules from an **iSport System** gym management installation. Output is available as a shortcode, a block, and — when [Divi](https://www.elegantthemes.com/gallery/divi/) 5 is active — as four native Divi 5 modules.

Built for [Jojo Gym](https://jojogym.isportsystem.cz/), designed to work with any iSport System installation.

---

## Table of contents

- [Why this plugin](#why-this-plugin)
- [Requirements](#requirements)
- [Installation](#installation)
- [Architecture](#architecture)
- [Development](#development)
- [Quality gates](#quality-gates)
- [Distribution](#distribution)
- [Documentation](#documentation)
- [Security](#security)
- [License](#license)

---

## Why this plugin

The iSport System exposes two public JSON endpoints. Calling them directly from a page template would mean one or more HTTP requests per page view, an unpredictable load on the remote server, and a white page whenever that server is slow.

This plugin instead **synchronises in the background** and serves visitors from local data:

- Courses are stored as a `cscs_course` custom post type, so they can be enriched with your own images, descriptions and SEO metadata.
- Class occurrences are stored in a dedicated table, indexed for schedule queries.
- Remote requests are made only by scheduled jobs and by explicit administrator action — **never by a visitor**.
- A hard hourly request cap and a circuit breaker protect the remote service.

## Requirements

| | Minimum | Developed against |
|---|---|---|
| WordPress | 6.5 | 7.0.4 |
| PHP | 8.1 | 8.3 |
| Divi *(optional)* | 5.0 | 5.11.0 |

Divi is an optional integration. Without it the shortcode and block provide the same output.

## Installation

### From a release

Download `course-schedule-connector.zip` from the [Releases](../../releases) page and install it through **Plugins → Add New → Upload Plugin**.

### From source

```bash
git clone https://github.com/lukasista/wpdissi.git course-schedule-connector
cd course-schedule-connector
composer install
npm ci
npm run build
```

Then symlink or copy the directory into `wp-content/plugins/`.

## Architecture

```
iSport API ──► Api\Client ──► Api\Mapper ──► Sync\Synchroniser
                                                   │
                                   ┌───────────────┼────────────────┐
                                   ▼               ▼                ▼
                            cscs_course CPT   cscs_lessons     cscs_sync_log
                                   │
                                   ▼
                            Render\Renderer (templates, overridable in a theme)
                                   │
                 ┌─────────────────┼──────────────────┬──────────────────┐
                 ▼                 ▼                  ▼                  ▼
            Shortcode          Block            Divi 5 modules      REST render
                                                                (Visual Builder preview)
```

The renderer is the single source of truth for markup. The Divi Visual Builder preview requests HTML from the REST endpoint rather than duplicating the markup in JavaScript, so the editor and the front end can never diverge.

Templates live in `templates/` and can be overridden from a theme by placing a file of the same name in `your-theme/course-schedule-connector/`.

See [`docs/DEVELOPER.md`](docs/DEVELOPER.md) for the full reference: hooks, filters, template tags, data model and REST routes.

## Development

```bash
composer install       # PHP tooling (PHPCS, PHPStan, PHPUnit)
npm ci                 # JS tooling (webpack, Babel)

npm run start          # watch mode for the Divi modules and block
npm run build          # production build

composer run lint      # PHP_CodeSniffer against WordPress Coding Standards
composer run lint:fix  # auto-fix what can be fixed
composer run analyse   # PHPStan, level 6 with WordPress stubs
composer run test      # PHPUnit
```

WP-CLI commands are provided for local work:

```bash
wp cscs api doctor                              # configuration and connectivity
wp cscs api courses                             # courses straight from the API
wp cscs api lessons --from=20260911 --to=20260911
wp cscs api courses --format=json --force       # bypass the cache
```

Synchronisation commands (`wp cscs sync`, `wp cscs match`) arrive with the data layer in the next phase.

## Quality gates

Every pull request runs, on PHP 8.1, 8.2 and 8.3:

- **PHP_CodeSniffer** with the `WordPress`, `WordPress-Docs` and `PHPCompatibilityWP` rulesets
- **PHPStan** at level 6 with WordPress and WP-CLI stubs
- **PHPUnit**
- **[Plugin Check](https://wordpress.org/plugins/plugin-check/)**, the official WordPress.org pre-submission scanner, in the same configuration the review team uses

A pull request that fails any of these cannot be merged. The JavaScript job joins them in the phase that introduces the block and the Divi modules; until the plugin ships JavaScript, a build step with nothing to build only produces noise.

## Distribution

The same source produces two builds:

| Build | Contains updater | Distributed via |
|---|---|---|
| `course-schedule-connector.zip` | yes | GitHub Releases, self-updating |
| `course-schedule-connector-wporg.zip` | no | WordPress.org SVN |

Guideline 8 of the WordPress.org plugin directory forbids a hosted plugin from updating itself from an external source, so the updater is an isolated module that the WordPress.org build omits. See [`docs/COMPLIANCE.md`](docs/COMPLIANCE.md).

## Documentation

| Document | Audience |
|---|---|
| [`docs/USER-GUIDE.md`](docs/USER-GUIDE.md) | Site managers *(Czech)* |
| [`docs/DEVELOPER.md`](docs/DEVELOPER.md) | Developers |
| [`docs/COMPLIANCE.md`](docs/COMPLIANCE.md) | WordPress.org guideline mapping |
| [`docs/SECURITY-CHECKLIST.md`](docs/SECURITY-CHECKLIST.md) | Reviewers |
| [`docs/RELEASE-CHECKLIST.md`](docs/RELEASE-CHECKLIST.md) | Release sign-off |
| [`PLAN.md`](PLAN.md) | Project plan *(Czech)* |

## Security

Please report vulnerabilities privately — see [`SECURITY.md`](SECURITY.md). Do not open a public issue for a security problem.

## License

GPL-2.0-or-later. See [`LICENSE`](LICENSE).

This plugin is not affiliated with, endorsed by, or sponsored by iSport System or Elegant Themes. "iSport System" and "Divi" are the trademarks of their respective owners and are used here only to describe compatibility.
