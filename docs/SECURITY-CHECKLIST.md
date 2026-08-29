# Security checklist

Every control below is a merge requirement, verified in code review and, where marked, enforced automatically in CI.

## Audit of 29 August 2026 (phase F8)

Every control below was walked line by line against the code as it then stood.
Six controls were failing, all of them because the plugin had grown past the day
the control was written; each is fixed and each fix is named beside its control.
The rest were satisfied as written.

| # | Control that failed | What was wrong | Fixed by |
|---|---|---|---|
| 1 | Server-side design lock | `DesignGuard` matched one block name, `cscs/divi-display`. The thirty field modules and thirty-one field blocks that arrived after it were unguarded, so a site manager without `cscs_manage_design` could recolour every table, restyle every heading and write custom CSS on any of them — on the server, not merely in a panel. | The guard now visits every block whose name begins `cscs/`, with the content keys each kind actually offers, and counts them per name so inserting one kind does not shift another's design. Three new tests. |
| 2 | Direct-access guard | Fourteen files under `includes/` had no `defined( 'ABSPATH' ) \|\| exit;` — the DTOs, the mapper, the matcher, the normaliser and the URL validator. | Added to all fourteen. Nothing is left without it. |
| 3 | Single host | `redirection => 2` let WordPress follow a redirect to **any** host, so an open redirect on the booking system would have been an open redirect out of this site's server. | `redirection => 0`, and one redirect is followed by hand only when the scheme, host and port match what was asked for. |
| 4 | Escape late, escape always (public REST) | The public `/listing` route took a `url` parameter and wrote it into the links of the HTML it returned, so a public route would put anybody's address inside a page carrying this site's name. | The base is held to this site's own host; anything else is dropped and the links fall back to the request. |
| 5 | Capability on every action | `/field` renders a preview for anyone with `edit_posts` — an author of one post — including a course nobody has published. | A post that is not published is refused unless the person asking could read it anyway. |
| 6 | Clean removal | `uninstall.php` deleted courses and trainers but not the kind pages, and left every term of the plugin's three taxonomies behind. | Kind pages are deleted with the rest and the taxonomies are emptied. The cron hooks are named from `Scheduler`'s constants rather than retyped. |

Checked and found sound, rather than assumed: the scoped inline CSS the blocks
write cannot be broken out of — every value passes `colour()`, `length()`,
`spacing()`, `one_of()`, `variable()` or `sanitize_html_class()`, and the one
free-form value, `font-family`, is matched against a pattern with no `<`, `/`,
`;`, `{` or `}` in it, so neither a declaration nor a closing tag can be
smuggled through by somebody who can edit a post. Every custom query is
prepared. There is no `eval`, no dynamic include, no debug output. Colours and
URLs from iSport are normalised on ingest, not at the point of output.

## Input

| Control | Detail | Enforced |
|---|---|---|
| Sanitise every input | `sanitize_text_field()`, `absint()`, `sanitize_key()`, `esc_url_raw()` as appropriate, at the boundary | PHPCS |
| Treat API data as untrusted | The remote response is third-party input. Every field is type-normalised on ingest (`"1"` → `int`), unknown fields are dropped, and nothing is stored raw except a validated JSON payload column | Review |
| Validate the base URL | Must be `https://`, must parse to a valid host, is checked against a deny-list of private and loopback ranges to prevent server-side request forgery, and is confirmed by a test request before being saved | Review |
| Bound every numeric parameter | Limits, offsets, date ranges and per-page values are clamped to sane ranges before reaching a query | Review |
| Reject oversized responses | Responses above a configurable size are discarded and logged rather than parsed | Review |

## Output

| Control | Detail | Enforced |
|---|---|---|
| Escape late, escape always | `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()` at the point of output | PHPCS |
| Colours from the API | `color` and `background` arrive as untrusted hex strings. They are validated against `/^[0-9a-fA-F]{3,8}$/` before being used in a style attribute, never interpolated raw | Review |
| URLs from the API | `course_url`, `activity_url` and `tab_url` are passed through `esc_url()` and restricted to `http`/`https` | Review |
| No inline JavaScript from data | Data reaches JavaScript through `wp_localize_script()` or `wp_add_inline_script()` with `wp_json_encode()`, never by string concatenation into a script tag | Review |

## Authorisation

| Control | Detail | Enforced |
|---|---|---|
| Capability on every action | `cscs_manage_content` for content, `cscs_manage_design` for design and settings. Never `is_admin()` as an authorisation check — it only tests the screen, not the user | Review |
| Nonce on every state change | `wp_nonce_field()` plus `check_admin_referer()`, or `X-WP-Nonce` for REST | PHPCS + review |
| REST permission callbacks | Every route defines an explicit `permission_callback`. Public read routes state `__return_true` deliberately and expose only data already visible on the front end | Review |
| Server-side design lock | Design attributes submitted by a user without `cscs_manage_design` are discarded on save, not merely hidden in the UI. Hiding a field is a convenience; the server decides | Review |

## Database

| Control | Detail | Enforced |
|---|---|---|
| Always prepare | Every custom query uses `$wpdb->prepare()`; table names are interpolated from `$wpdb->prefix` and a constant, never from input | PHPCS |
| Schema changes through `dbDelta()` | Versioned migrations, with the schema version stored in an option | Review |
| No secrets in the database | The plugin requires no API key. If one is added later it goes in a constant in `wp-config.php`, not in an option | Review |

## Files and code execution

| Control | Detail | Enforced |
|---|---|---|
| Direct-access guard | `defined( 'ABSPATH' ) \|\| exit;` at the top of every PHP file | PHPCS |
| No `eval`, no dynamic includes | No `eval()`, no `create_function()`, no variable `include` paths | PHPCS |
| No remote code | Nothing downloaded is ever executed or written as PHP | Review |
| No file uploads | The plugin accepts no upload; images are referenced by URL from the media library or the remote system | Review |

## Outbound requests

| Control | Detail | Enforced |
|---|---|---|
| Certificate verification on | `sslverify` stays at its default `true`. The example in the iSport API documentation disables host verification — that is deliberately **not** followed | Review |
| Timeouts and retries | Ten-second timeout, at most two retries with backoff, then a circuit breaker after three consecutive failures | Review |
| Request cap | A hard configurable ceiling on requests per hour that scheduled jobs cannot exceed | Review |
| Single host | Requests go only to the configured host. Redirects to a different host are refused | Review |
| Never from a visitor | Front-end rendering reads local data only. A visitor request can never trigger an outbound call | Review |

## Errors and logging

| Control | Detail | Enforced |
|---|---|---|
| No sensitive data in logs | The synchronisation log records counts, durations and error codes — never full response bodies or URLs with parameters | Review |
| Fail soft | A failed synchronisation leaves the previous data in place and never emits a fatal error on the front end | Review |
| No debug output | No `error_log()`, `var_dump()`, `print_r()` or `console.log()` in committed code | PHPCS + review |

## Uninstall

| Control | Detail | Enforced |
|---|---|---|
| Clean removal | `uninstall.php` removes options, custom tables, scheduled events and the custom role — but only when the administrator has opted in to data removal in settings | Review |
| No orphaned cron | All scheduled events are cleared on deactivation | Review |

## Dependencies

| Control | Detail | Enforced |
|---|---|---|
| No runtime dependencies | Nothing in `require` beyond PHP itself; everything else is `require-dev` and excluded from the build | CI |
| Automated updates | Dependabot monitors Composer, npm and GitHub Actions monthly | CI |
| Pinned actions | Workflow actions are referenced by major version from trusted publishers | Review |
