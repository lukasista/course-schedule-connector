=== Course & Schedule Connector for iSport ===
Contributors: 1uka5i5ta
Tags: courses, schedule, timetable, booking, sports
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0-alpha.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display courses and class schedules from an iSport System gym management installation on your WordPress site.

== Description ==

Course & Schedule Connector for iSport reads the public JSON feeds of an iSport System installation and displays courses and class schedules on your WordPress site.

Data is synchronised in the background on a schedule, stored locally, and served to visitors from your own database. Visitors never trigger a request to the remote system, so page speed does not depend on the availability of the external service.

**Features**

* Courses and class schedules as responsive tables that fold on a telephone into one card per row, the column label beside its value
* Named display sets: what a listing shows is decided once and reused everywhere — by filter, by hand-picked course, or both
* Thirty-one blocks and thirty-one Divi 5 modules, one per field: Price, Day, Time, Age, Who it is for, Level, Places left, the sign-up button, the timetable, a trainer's photograph and qualifications, a kind of course's prices — each designable on its own
* Courses are grouped into kinds read from their names, and each kind has a page of its own that can be narrowed to part of it, so one design serves them all
* Individual course and trainer pages, with your own words and pictures and structured data
* Content and design permissions are separated, and the separation is enforced when the page is saved rather than by hiding a panel
* Live availability, capacity and waiting-list counts
* Works with no page builder and with no JavaScript; the Divi modules are an optional extra
* Fully translatable, and shipped with a Czech translation

**How it works**

The plugin queries two endpoints of your iSport System installation, which you configure in the plugin settings. It stores courses as a custom post type and class occurrences in a dedicated database table, then renders them from local data.

== External services ==

This plugin connects to an iSport System installation to retrieve course and class schedule data. The base URL of that installation is configured by the site administrator; no connection is made until a URL is entered.

Two endpoints are requested from that host:

* `/api/courses.php` — returns the list of courses, their dates, prices, trainers, rooms and capacity.
* `/api/activities.php` — returns individual class occurrences within a requested date range.

Requests are sent from your web server on a schedule (by default several times per hour) and when an administrator triggers a manual synchronisation. **No data about your site visitors is sent** — the requests contain no personal data, no cookies and no identifiers. Requests are never made from the visitor's browser.

The service is operated by the provider of your iSport System installation. Please consult that provider for their terms of service and privacy policy.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install the ZIP through **Plugins → Add New → Upload Plugin**.
2. Activate the plugin.
3. Go to **iSport → Settings** and enter the base URL of your iSport System installation.
4. Run **Synchronise now** on the **iSport → Overview** screen.
5. Create a display set under **iSport → Display sets**, then place a shortcode, block or Divi module on a page.

== Frequently Asked Questions ==

= Do I need Divi? =

No. The plugin ships a shortcode and a block that work in any theme. The Divi modules are an optional extra that are registered only when Divi 5 is active.

= How often is data refreshed? =

Courses are refreshed every ten minutes and the upcoming three weeks of classes every fifteen minutes by default. Both intervals are configurable, and a hard hourly request cap protects the remote server.

= What happens if the remote system is unavailable? =

The last successfully retrieved data continues to be displayed, and the pages carry on working. Repeated failures pause outbound requests for a while rather than retrying into a wall; the **iSport → Overview** screen says so, along with when each scheduled job runs next.

= Can a site manager break the design? =

No. Design settings belong to the `cscs_manage_design` capability, which administrators hold and the Course manager role does not — and a save by somebody without it puts back the design that was there, on the server. Hiding the panels would be a courtesy: a builder is a browser application, and anything a browser decides can be undone in a browser.

= Does the plugin send anything to the remote system about my visitors? =

No. Every page is rendered from data already in your own database, and a visitor's browser never contacts iSport. The scheduled requests your server makes carry no personal data, no cookies and no identifiers.

= Can I show only part of a kind of course? =

Yes. A kind's page carries its own answer to "which of these courses" — who they are for, at what level, an age range — so *Gymnastics* can appear as one page for the girls' hours and another for the boys' without refiling a single course. One theme-builder template then serves every page of the type, because the question is asked on the page and not in the design.

== Screenshots ==

1. A course listing on a page: day, time, age, who it is for, level, price, places left and the sign-up button.
2. The same listing on a telephone. Below a width you choose, every row becomes a card with the column name beside its value — and nothing where a course has nothing to say.
3. A class timetable, with the week and room controls above it. They are ordinary links, so they work with no JavaScript.
4. A course's own page: the facts, the lecturer's contact, the sign-up button and the course's own upcoming classes.
5. A kind of course's page: your words, what its courses cost as a two-column table, and the timetable of everything filed under it.
6. Display sets. What a listing shows is decided once here — by filter, by hand-picked course, or both.
7. The Overview screen: what is stored, the state of the connection, and when each scheduled job runs next.
8. The block editor, with the plugin's own section in the inserter and a field block's Styles tab open.

== Changelog ==

= 1.0.0-alpha.2 =
* Courses that began earlier in the term were missing: the remote system was asked for its listing without a date, which it answers as "courses starting from this moment". Forty of a hundred and thirteen courses had been archived as though iSport had stopped offering them, two hundred and nineteen class occurrences could not be tied to a course, and six kinds of course had nothing to take a description from. The listing now starts a month before the configured term start.
* Trainer photographs are fetched once instead of on every synchronisation. iSport keeps several records under one trainer's name, each with its own picture, and the old guard mistook that for the photograph having changed — one site's media library held 2 116 pictures of twenty-two people. `wp cscs trainers tidy` clears out the duplicates.
* A course iSport has stopped offering now goes into a **Cancelled** state of its own: off the site, kept in full, with its own tab in the list of courses, and published again by itself if iSport offers it back. Its old address redirects to the kind of course it belonged to.
* Overview: **Pause synchronisation**, which stops the scheduled jobs until you resume them and leaves synchronising by hand working; and **Fetch every missing description**, which fills in the kind pages that have nothing written on them.
* Both long-running buttons now say what they are doing while they do it, instead of leaving the page sitting there.
* The button that fetches a kind's description says what happened. A course with no description in iSport now says so, rather than looking like a button that does nothing.
* Settings can be exported to a file and imported back. Every value goes through the same validation the form uses, so a file cannot store anything that could not be typed in.
* The list of kinds of course has a **Description** column and a **Courses** count, both switchable under Screen Options.
* The course dropdown in a kind's editor no longer hangs over the edge of the screen.
* A trainer's photograph is fetched only from the configured iSport address, rather than from whatever address the response happens to carry.
* `wp cscs sync descriptions`, `wp cscs sync pause on|off` and `wp cscs trainers tidy`.

= 1.0.0-alpha.1 =
* First alpha. Feature-complete and in testing on the site it was built for; not yet submitted to the directory.
* Courses and class timetables from an iSport System installation, synchronised on a schedule and served from your own database.
* Display sets; a shortcode; thirty-one blocks and thirty-one Divi 5 modules; course, trainer and kind-of-course pages.
* Content and design permissions separated and enforced on the server.
* Czech translation included.

== Upgrade Notice ==

= 1.0.0-alpha.2 =
Fixes courses and class occurrences going missing when the term had already begun. Run a synchronisation after updating.

= 1.0.0-alpha.1 =
First alpha.
