# Course & Schedule Connector for iSport — alfa 1.0.0-alpha.1

**Tenhle soubor je jediné, co je potřeba přečíst, než se začne pracovat.** Je
psaný tak, aby se do něj dalo vstoupit bez znalosti předchozích rozhovorů: co
plugin je, jak je postavený, jak se s ním pracuje, co je hotové, co se ví, že je
špatně, a co bude dál. Podrobnosti jsou v `DEVELOPER.md` (jak to funguje uvnitř),
`USER-GUIDE.md` (jak se to používá, česky), `HANDOVER.md` (deník po dnech)
a `../CHANGELOG.md` (co přibylo a proč).

Stav k 29. 8. 2026. Fáze F0–F11 hotové, F12 (akceptační brána) odložená.
Následuje **testovací a vylepšovací fáze**.

---

## 1. Co to je

Plugin pro Jojo Gym. Čte veřejné JSON kanály jejich instalace **iSport System**,
ukládá kurzy a lekce do WordPressu a zobrazuje je na webu — jako shortcode, jako
Gutenberg bloky a jako moduly pro **Divi 5**. Návštěvník nikdy nespustí požadavek
na iSport; všechno se kreslí z lokální databáze.

| | |
|---|---|
| Slug | `course-schedule-connector` |
| Prefix / namespace | `cscs` / `CSCS\` |
| Text domain | `course-schedule-connector` |
| Verze | `1.0.0-alpha.1` |
| Požaduje | WordPress 6.7, PHP 8.1 |
| Licence | GPL-2.0-or-later |
| Repozitář | `github.com/lukasista/course-schedule-connector` (veřejný) |
| Účet na WordPress.org | `1uka5i5ta` (podání zatím neproběhlo) |

---

## 2. Kde se pracuje

Tři místa a je důležité je neplést:

| Kde | Co to je | Jak se tam dosáhne |
|---|---|---|
| **Kontejner** | `/root/work/plugin` — pracovní kopie, tady se edituje, staví a testuje | `Bash` |
| **Mac (repozitář)** | `/Volumes/PRO/www/Plugins/Jojo Gym iSport System Integration` — git, odtud se pushuje | `device_bash`, mount pod `$HOME/mnt/…` |
| **Mac (weby)** | Studio: `jojogym 2` (ostrá data + Divi 5.11) a `CSCS Clean Check` (čistá instalace, WP 7.1, bez Divi) | `wordpress-studio__wp_cli` |

Plugin na obou webech je **symlink na repozitář na Macu** u `jojogym 2`;
`CSCS Clean Check` má vlastní kopii, tam se instaluje ze ZIPu.

### Postup přenosu kontejner → Mac

```bash
# v kontejneru
tar czf /tmp/syncNN.tgz includes templates assets blocks divi languages tools tests
```
pak `SendUserFile` → `device_commit_files` do `_to_delete/syncNN.tgz` → na Macu
`tar --overwrite -xzf _to_delete/syncNN.tgz` → **a vždy porovnat otisk obou
kopií**:
```bash
find includes templates … -type f ! -name ".DS_Store" ! -path "*__pycache__*" | sort | xargs md5sum | md5sum
```

### Sestavení balíčků

`bash tools/build-packages.sh` sestaví podle `.distignore`:

- `/tmp/cscs-dist.zip` — build pro GitHub (s `includes/Updater`, s hlavičkou `Update URI`)
- `/tmp/cscs-wporg.zip` — build pro adresář (bez obojího)

Totéž dělá v CI `.github/workflows/release.yml` na tag `v*.*.*`; ten navíc ověří,
že se tag shoduje s hlavičkou `Version:` i se `Stable tag:`.

### Testy

```bash
php tools/phpunit-shim/run.php        # 249 testů, 1053 assertions
```
Vlastní běhoun, ne PHPUnit — kontejner nemá síť na Packagist. Testy jsou
v `tests/Unit/`, stuby WordPressu v `tests/bootstrap.php`, fixtury odpovědí API
v `tests/fixtures/`.

### Překlady a generované soubory

```bash
php tools/extract-strings.php    # vytáhne řetězce ze zdroje
python3 tools/i18n/build.py      # .pot, .po, .mo a JED soubory pro JS
php tools/build-divi-modules.php # 30 modulů z katalogu polí
```
Nový anglický řetězec ⇒ `build.py` spadne s `KeyError`, dokud se překlad nedopíše
do `tools/i18n/cs.py`. Je to schválně.

### Git na Macu

Po **každém** git příkazu je nutné uklidit zámky, jinak další příkaz selže:
```bash
mkdir -p _to_delete/gitlocks
for f in .git/index.lock .git/HEAD.lock .git/objects/maintenance.lock; do [ -e "$f" ] && mv "$f" "_to_delete/gitlocks/lock.$RANDOM"; done
find .git/objects -name "tmp_obj_*" -exec mv {} _to_delete/gitlocks/ \; 2>/dev/null
```
Commity se dělají s `GIT_AUTHOR_NAME="Lukas Pivonka" GIT_AUTHOR_EMAIL="info@pivonka.co.uk"`
(a totéž pro `GIT_COMMITTER_*`). **Push dělá Lukáš ručně.**

---

## 3. Jak je to postavené

### Vrstvy

```
Api\        Client, WpHttp, Mapper, CircuitBreaker, RateLimiter   — síť a normalizace
Data\       CourseRepository, LessonRepository, KindRepository,   — co je uloženo
            PostType, TrainerType, KindType, DisplaySet, Audience,
            CourseKind, Schema, RoomMap
Sync\       Synchroniser, Matcher, MakeupResolver, Scheduler,     — jak se to plní
            Retention, Logger
Render\     Renderer, Query, Listing, Fields, FieldRenderer,      — jak se to kreslí
            FieldBlocks, Block, BlockCategory, CourseDetail,
            TrainerDetail, KindDetail, Single*, RestPreview
Divi\       FieldModules, DisplayModule, DesignGuard, …           — jen když je Divi
Admin\      Menu, Screen\*, CourseEditor, TrainerEditor,          — administrace
            KindEditor, CourseList, Capabilities
Cli\        api, sync, settings, sets, caps, trainers, makeup, divi
```

### Šest věcí, které je dobré vědět předem

1. **Jeden katalog polí.** `Render\Fields::all()` je jediná definice toho, co
   plugin umí zobrazit. Z něj se generují **bloky i Divi moduly** — 31 a 31.
   Přidat pole = přidat položku do katalogu, spustit `build-divi-modules.php`,
   dopsat překlad. `FieldModulesTest` spadne, když se generátor zapomene pustit.

2. **Jedna tabulka.** `templates/partials/table.php` kreslí každý výpis v celém
   pluginu — rozvrh, kurzy trenéra, kurzy druhu, ceny druhu. Proto se všechno
   skládá na telefonu stejně a proto se změna projeví všude.

3. **Zobrazovací sady** (`DisplaySet`) říkají, co má výpis ukázat: sloupce,
   filtry, ručně vybrané kurzy, řazení, stránkování. Uloženy v jedné option.
   Stránka odkazuje na sadu jménem.

4. **Druh kurzu** je taxonomie **i** typ příspěvku. Taxonomie `cscs_kind` seskupuje
   (čte se z názvu kurzu, `Data\CourseKind`), příspěvek `cscs_kind_page` nese text,
   obrázek a **filtr**, kterým se dá jeden druh ukázat jako dvě stránky. Filtr je
   na stránce, ne v modulu, protože globální šablona je jeden design pro všechny.

5. **Zámek designu** (`Divi\DesignGuard`) je na `wp_insert_post_data`: uložení
   uživatelem bez `cscs_manage_design` vrátí design, který tam byl. Platí pro
   **každý** blok začínající `cscs/`.

6. **Věk, pohlaví a úroveň** se čtou z názvu kurzu (`Data\Audience`), protože
   iSport je nemá. Ruční oprava se zamkne a synchronizace na ni nesahá.

### Data z iSportu

Dva endpointy: `/api/courses.php` a `/api/activities.php`. Kurz se páruje
s lekcí přes **název + časové razítko**. Na ostrých datech: 113 kurzů, 511 lekcí,
**úspěšnost párování 98,7 %**, 3 nevyřešené. Sály se odvozují z lekcí.

---

## 4. Co je hotové

| Fáze | Co | Stav |
|---|---|---|
| F0–F2 | Repozitář, API klient, datový model, synchronizace | ✅ |
| F3a/F3b | Administrace (chybí jen hromadné akce v seznamu kurzů) | ✅ / ⚠️ |
| F4–F5 | Renderer, šablony, shortcode, blok | ✅ |
| F6 | Divi 5 moduly | ✅ |
| F7 | Stránka kurzu, trenéra, druhu; frontend | ✅ |
| F8 | Bezpečnostní audit — 7 nálezů, 2 vážné | ✅ |
| F9 | Výkon (119 → 7 dotazů), i18n, přístupnost | ✅ |
| F10 | Plugin Check: **0 chyb, 0 varování**; čistá instalace i Divi | ✅ |
| F11 | Dokumentace, verze, buildy, snímky | ✅ |
| F12 | Akceptační brána `RELEASE-CHECKLIST.md` | ⏸ odloženo |

Čísla, která stojí za zapamatování: **249 testů**, **31 bloků**, **31 Divi
modulů**, **575 přeložených řetězců**, **25 druhů kurzů**, výpis 113 kurzů za
**7 dotazů / 51 ms**, frontend dělá **0 odchozích požadavků**.

### Dvě chyby, které stojí za připomenutí, protože se budou opakovat

- **Zámek designu hlídal jeden blok z jedenašedesáti** a navíc požíral obsah,
  protože filtr dostává data zaescapovaná a `parse_blocks()` na nich vrátí blok
  bez atributů. Vypadalo to jako fungující zámek.
- **Plánovač se nikdy nenaplánoval.** `wp_schedule_event()` odmítne interval,
  který WordPress v tu chvíli nezná, a při aktivaci ho nezná. Frontend o tom
  neřekne nic — stránky se kreslí dál a čísla stojí.

Poučení, které platí dál: **co se neověří na živých datech nebo na obrázku, to se
neví.** Obě chyby v tabulce níž našel snímek obrazovky, ne čtení kódu.

---

## 5. Co se ví, že je špatně

Nic z toho neblokuje provoz. Seřazeno podle toho, jak moc to bije do očí.

| # | Kde | Co | Důkaz |
|---|---|---|---|
| A1 | Složená tabulka na telefonu | Dlouhý popisek („Time from and to") přeteče do hodnoty a **překryje ji**. Mřížka `minmax(6rem, 40%)` v `Renderer::responsive_css()` popisek nezalomí. | `.wordpress-org/screenshot-2.png` |
| A2 | Široká tabulka | Posuv funguje, ale useknutý sloupec vypadá jako rozbitá stránka — chybí náznak, že se dá posouvat (stín, přechod). | `screenshot-5.png` |
| A3 | Široká tabulka | `:focus-visible` kreslí kolem celé oblasti silný černý rám. Funkčně správně, opticky těžké. | `screenshot-5.png` |
| A4 | Přehled → Poslední běhy | Datum se píše `29.8. 15:23` napevno česky i na anglickém webu. `wp_date('j.n. H:i')` v `Admin\Screen\Overview.php:275`. | `screenshot-7.png` |
| A5 | Seznam kurzů v administraci | Hromadné akce z F3b nikdy nevznikly. | — |
| A6 | `.wordpress-org` | Snímky 1 a 3 jsou tentýž obrázek; chybí snímek výpisu kurzů na desktopu. | — |
| A7 | Adresář | Bannery a ikony (`banner-*.png`, `icon-*.png`) neexistují. | — |

---

## 6. Co bude dál

Lukáš už ví, že bude chtít **hodně úprav v Gutenberg blocích a v šablonách
stránek a kurzů**. Tomu odpovídají místa, kterých se to dotkne:

- **Bloky:** `blocks/fields/editor.js` (panely, ovládání), `Render\FieldBlocks`
  (registrace), `Render\Fields` (katalog), `Render\FieldRenderer` (atributy,
  scoped CSS). Změna v katalogu se propíše i do Divi — což je většinou to, co se
  chce, ale je dobré si toho být vědom.
- **Šablony:** `templates/` — `single-course.php`, `single-kind.php`,
  `single-trainer.php`, `courses.php`, `schedule.php` a partials. Přepisují se
  v tématu pod `course-schedule-connector/`. **Proměnné mají prefix**
  (`$cscs_listing`, `$cscs_detail`, …) — kdo přepisuje šablonu, musí je znát;
  tabulka je v `DEVELOPER.md`.
- **Vzhled tabulky:** `assets/css/cscs.css` (statické) a
  `Render\Renderer::responsive_css()` (skládání podle nastavené šířky).

Až se bude chtít vydávat: **F12** je průchod `docs/RELEASE-CHECKLIST.md` na
čisté instalaci, u každé položky důkaz, a dva podpisy. Teprve pak případné
podání do adresáře.

---

## 7. Rychlá orientace v souborech

```
course-schedule-connector.php   hlavička, konstanty, aktivace
includes/                       všechen PHP kód (viz vrstvy výše)
templates/                      šablony frontendu, přepsatelné v tématu
blocks/display/                 blok Kurzy a rozvrh (block.json + editor.js)
blocks/fields/editor.js         editor všech 30 polí jako bloků
divi/cscs-display/              modul Kurzy a rozvrh
divi/fields/<pole>/             30 generovaných modulů
assets/css, assets/js           styly a skripty frontendu i administrace
languages/                      .pot, .po, .mo, JED soubory pro JS
tools/                          generátory a testovací běhoun
tests/                          249 unit testů, stuby, fixtury
docs/                           tenhle soubor, DEVELOPER, USER-GUIDE, HANDOVER,
                                COMPLIANCE, SECURITY-CHECKLIST, RELEASE-CHECKLIST
.wordpress-org/                 snímky a pokyny pro adresář
```
