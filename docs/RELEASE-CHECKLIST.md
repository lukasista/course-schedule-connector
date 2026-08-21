# Release checklist

The acceptance gate for v1.0.0. **Nothing is submitted to WordPress.org until every box below is ticked and evidenced.** "Evidenced" means a CI run, a screenshot, or a log — not an opinion.

Each release candidate gets a copy of this list in its pull request.

---

## 1. Automated gates

All of these run in CI and must be green on the release commit.

- [ ] `composer run lint` — PHP_CodeSniffer, `WordPress` + `WordPress-Docs`, **zero errors, zero warnings**
- [ ] `composer run analyse` — PHPStan level 6, zero errors
- [ ] `composer run test` — PHPUnit, all tests pass
- [ ] `npm run lint:js` and `npm run lint:css` — zero errors
- [ ] `npm run build` — completes with no warnings
- [ ] **Plugin Check** against the WordPress.org build — zero errors **and zero warnings**, with every check category enabled including *Plugin Repository*
- [ ] Release workflow version guard passes: git tag = `Version:` header = `Stable tag:`

## 2. Security

Every row of [`SECURITY-CHECKLIST.md`](SECURITY-CHECKLIST.md) verified by reading the code, not by assuming.

- [ ] Every `echo`, `print` and template output is escaped with the correct function for its context
- [ ] Every `$_GET`, `$_POST`, `$_REQUEST` and REST parameter is sanitised and bounded
- [ ] Every state-changing endpoint checks a capability **and** verifies a nonce
- [ ] Every REST route has an explicit `permission_callback`; none is missing or `null`
- [ ] Every custom query uses `$wpdb->prepare()`
- [ ] Base URL validation rejects `http://`, private ranges, loopback and link-local addresses
- [ ] Hex colours from the API are validated before reaching a style attribute
- [ ] URLs from the API pass `esc_url()` and are limited to `http`/`https`
- [ ] Design attributes submitted without `cscs_manage_design` are discarded server-side — verified by an authenticated request that attempts it
- [ ] `defined( 'ABSPATH' ) || exit;` present in every PHP file
- [ ] No `eval`, no dynamic `include`, no remote code, no file writes outside the uploads API
- [ ] `uninstall.php` removes everything it created — verified on a scratch install
- [ ] Composer `require` contains nothing but PHP; the built ZIP contains no `vendor/bin`, no tests, no dev tooling

## 3. WordPress.org guidelines

- [ ] Every row of [`COMPLIANCE.md`](COMPLIANCE.md) re-read and still accurate
- [ ] `readme.txt` under 10 KB, short description under 150 characters, exactly five tags
- [ ] `== External services ==` section names both endpoints, what is retrieved, when, and by whom
- [ ] `Contributors` lists a real WordPress.org username
- [ ] `Tested up to` matches the current WordPress release
- [ ] The WordPress.org build contains **no** `includes/Updater/` and **no** `Update URI` header — verified by unzipping the artefact
- [ ] No "Powered by" link, no upsell, no telemetry, no review nag anywhere in the code
- [ ] Trademark notice present in `README.md`

## 4. Functional verification

Tested on a **clean** WordPress install, not the development site.

- [ ] Activates without notice or error on WP 6.5 and on the current release
- [ ] Runs on PHP 8.1, 8.2 and 8.3
- [ ] **Works with Divi deactivated** — shortcode and block render correctly
- [ ] Works with Divi 5 active — all four modules render, and the Visual Builder preview matches the front end pixel for pixel
- [ ] Works with a default theme (Twenty Twenty-Five) and with the JoJo Gym 2.0 child theme
- [ ] Initial synchronisation completes and reports sensible counts
- [ ] Course ↔ lesson match rate is reported and is **above 95 %**; every unmatched lesson is assignable by hand
- [ ] Manual assignment survives a subsequent synchronisation
- [ ] A locked field survives a subsequent synchronisation
- [ ] Retention job deletes only what it should — verified against a seeded dataset
- [ ] Request counter never exceeds the configured hourly cap under a forced-failure loop
- [ ] Circuit breaker opens after three failures and the failure e-mail is sent once, not repeatedly
- [ ] With the remote host unreachable, the front end still renders the last known data and logs the failure
- [ ] `price = null` renders "Zdarma" for a course and the configured text for a lesson
- [ ] `canceled = 1` renders struck through with the label, and disappears entirely when hidden
- [ ] `booking_allowed = 0` hides the iSport button and the reason is available
- [ ] Timezone handling correct across a DST boundary — verified with a seeded lesson on the changeover date

## 5. Permissions

- [ ] A user with the *iSport Manager* role can edit display sets and course content
- [ ] The same user sees **no** design controls in Divi and **cannot** save them via a crafted request
- [ ] The same user cannot open plugin settings or trigger a synchronisation
- [ ] A subscriber can reach none of the admin screens and no privileged REST route

## 6. Accessibility

- [ ] Axe or equivalent reports no violations on each of the four outputs
- [ ] Tables keep `<caption>` and `scope`, and remain announced correctly by a screen reader in the collapsed mobile layout
- [ ] All interactive elements reachable and operable by keyboard, with a visible focus style
- [ ] Colour combinations from the API are contrast-checked, with the fallback applied when they fail
- [ ] Tested at 200 % browser zoom and at 320 px width

## 7. Performance

- [ ] Query Monitor shows no query on a course listing page above 50 ms, and no N+1 pattern
- [ ] A page with a full weekly calendar adds fewer than 15 queries
- [ ] No outbound HTTP request occurs during a front-end page load — verified with Query Monitor's HTTP panel
- [ ] Front-end CSS and JS are enqueued only on pages that actually render plugin output
- [ ] **Peak memory contribution measured** with the plugin off and on, on an admin page and a front-end page, and recorded in the pull request. A page builder can leave a site sitting near its ceiling, so the plugin's own cost must be a known number rather than an assumption.

## 8. Internationalisation

- [ ] `.pot` regenerated and complete
- [ ] Czech translation complete and reviewed
- [ ] No string concatenation inside a translation function; every placeholder is numbered where order may change
- [ ] Dates, times and prices formatted through `wp_date()` and the plugin formatter, never `date()`

## 9. Documentation

- [ ] `USER-GUIDE.md` matches the shipped interface, with screenshots
- [ ] `DEVELOPER.md` matches the shipped hooks, templates, REST routes and CLI commands
- [ ] `CHANGELOG.md` complete for the release
- [ ] `readme.txt` changelog mirrors it
- [ ] Screenshots present in `.wordpress-org/` and referenced in `readme.txt`

## 10. Debug clean

- [ ] Full pass over every admin screen and every front-end output with `WP_DEBUG`, `WP_DEBUG_LOG`, `SCRIPT_DEBUG` and `SAVEQUERIES` enabled — **log empty**
- [ ] No PHP deprecation notice on PHP 8.3
- [ ] No JavaScript console error or warning
- [ ] No 404 for a plugin asset

---

## Sign-off

| | Name | Date |
|---|---|---|
| Developer sign-off | | |
| Site owner sign-off | | |

Submission to WordPress.org happens only after both signatures.
