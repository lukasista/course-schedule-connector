# Directory assets

Everything WordPress.org shows beside the plugin lives here. The build ignores
this folder; it is copied to the SVN `assets/` directory at release.

| File | What it is | Size |
|---|---|---|
| `screenshot-1.png` … | One per entry under `== Screenshots ==` in `readme.txt`, in that order | 1200 × 900, PNG |
| `banner-772x250.png` | The header on the plugin page | 772 × 250 |
| `banner-1544x500.png` | The same at twice the size | 1544 × 500 |
| `icon-128x128.png` | The icon in search results | 128 × 128 |
| `icon-256x256.png` | The same at twice the size | 256 × 256 |

## Where the shots are taken

A clean WordPress with this plugin, real data synchronised and nothing else
installed — not a development site with other plugins in the admin bar. The one
used for version 1.0.0 is the Studio site **CSCS Clean Check** at
`http://localhost:8893`, whose addresses are:

| # | Address |
|---|---|
| 1, 2 | `/kurzy/` — a course listing |
| 3 | `/rozvrh/` — a class timetable, week by week |
| 4 | `/kurz/28-gymnastika-6-9-let-divky-zacatecnici-i-pololeti/` |
| 5 | `/druh/gymnastika/` |
| 6 | `/wp-admin/admin.php?page=cscs-sets` |
| 7 | `/wp-admin/admin.php?page=cscs` |
| 8 | `/wp-admin/post-new.php?post_type=page` |

Before shooting:

- **Settings → General → Site language: English (United States).** The plugin's
  own words follow the site's language; the course names are the gym's and stay
  Czech.
- Collapse the admin menu and hide the admin bar on the front end, or the
  screenshots are half WordPress chrome.
- Browser window 1200 px wide for everything except number 2, which is 390 px.
- No browser chrome in the image: capture the page, not the window.

## Taking the screenshots

They are taken on a clean WordPress with the plugin installed and real data
synchronised — not on a development site with other plugins in the admin bar.
The site language decides the language of the plugin's own words, so set it to
English for the directory.

The eight shots, in the order `readme.txt` lists them:

1. **A course listing on a page** — a display set with `day, hours, age, gender,
   level, price, places, button`, ten rows, on a page of its own. This is the
   one thing most people want to see.
2. **The same listing on a telephone** — 390 px wide, showing the fold: one card
   per course, the column name beside its value, and nothing where a course has
   nothing to say.
3. **A class timetable** — a set of type *Schedule*, with the week and room
   controls above it.
4. **A course's own page** — the facts table, the contact, the sign-up button
   and the course's own upcoming classes.
5. **A kind of course's page** — the words, the two-column prices table, and the
   timetable of everything filed under it.
6. **iSport → Display sets**, with a set open: the columns, the filters and the
   hand-picked courses.
7. **iSport → Overview**, showing what is stored, the connection, and the
   scheduled jobs with when each runs next.
8. **The block editor**, inserter open on the **iSport** category, with a field
   block selected and its Styles tab showing.

An admin screenshot needs a logged-in browser, so those three are taken by hand
rather than by a headless capture.
