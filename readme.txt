=== Course & Schedule Connector for iSport ===
Contributors: 1uka5i5ta
Tags: courses, schedule, timetable, booking, sports
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display courses and class schedules from an iSport System gym management installation on your WordPress site.

== Description ==

Course & Schedule Connector for iSport reads the public JSON feeds of an iSport System installation and displays courses and class schedules on your WordPress site.

Data is synchronised in the background on a schedule, stored locally, and served to visitors from your own database. Visitors never trigger a request to the remote system, so page speed does not depend on the availability of the external service.

**Features**

* Course listings as cards or as a fully responsive table
* Class schedule as a day-by-day list or as a weekly calendar grid
* Tables collapse on small screens so the column label sits next to its value
* Named display sets: a site manager configures what is shown once, and reuses it everywhere
* Content and design permissions are separated, so a site manager cannot change the design
* Individual course pages with your own images, descriptions and structured data
* Output through a shortcode, a block, or Divi modules — the plugin does not require a page builder
* Live availability, capacity and waiting-list counts
* Full translation support

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

The last successfully retrieved data continues to be displayed. Repeated failures pause synchronisation temporarily and notify the site administrator by e-mail.

= Can a site manager break the design? =

No. Design controls are restricted to users with the `cscs_manage_design` capability, which is granted to administrators only, and the restriction is enforced server-side when settings are saved.

== Changelog ==

= 0.1.0 =
* Initial development release.

== Upgrade Notice ==

= 0.1.0 =
Initial development release.
