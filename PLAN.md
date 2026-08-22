# Jojo Gym – iSport System ↔ WordPress / Divi 5
## Projektový plán v1.9

**Datum:** 19. 8. 2026
**Název pluginu:** **Course & Schedule Connector for iSport** (slug `course-schedule-connector`, prefix kódu `cscs`)
**Cíl:** Zobrazovat kurzy a rozvrh lekcí z iSport systému na WordPress webu pomocí modulů pro Divi 5. Obsah spravuje správce z WP administrace, design řídí výhradně administrátor v Divi.

---

## 1. Cílové prostředí (potvrzeno)

| | |
|---|---|
| WordPress | 7.0.4 |
| PHP | 8.3 |
| Divi | 5.11.0 |
| Aktivní šablona | child theme **JoJo Gym 2.0** |
| Vývoj | WordPress Studio, web **jojogym 2**, `http://localhost:8888` |
| Repozitář | `https://github.com/lukasista/course-schedule-connector` (veřejný) |
| Účet na WordPress.org | `1uka5i5ta`, propojený s GitHubem |
| Jazyk | web česky, kód plně připravený na překlad (`__()`, `.pot`, text domain `course-schedule-connector`) |

---

## 1b. Soulad s pravidly WordPress.org (nové v1.3)

Cílem je plugin, který obstojí v oficiálním adresáři WordPress.org. Prověřil jsem všech 18 pravidel adresáře; tři z nich si vynutily změnu návrhu.

### Konflikt 1 – aktualizace z GitHubu vs. adresář
Pravidlo 8 zakazuje hostovanému pluginu stahovat spustitelný kód z externího zdroje a pravidlo 3 vyžaduje, aby stabilní verze byla vždy na WordPress.org. **Řešení: dva buildy z jednoho zdroje.** Updater je izolovaný modul `includes/Updater/`; CI vyrobí `course-schedule-connector.zip` (s updaterem, pro GitHub) a `course-schedule-connector-wporg.zip` (bez něj). Žádná jiná část na updateru nezávisí, takže jeho odstranění nemůže ovlivnit chování.

### Konflikt 2 – ochranné známky ve slugu
Pravidlo 17 zakazuje známku jako první nebo jediný výraz slugu. **Slug je `course-schedule-connector`**, zobrazovaný název *„Course & Schedule Connector for iSport“* používá povolený tvar „*Funkce* for *Značka*“. V `README.md` je výslovné prohlášení o neafiliaci s iSport System i Elegant Themes.

### Konflikt 3 – závislost na Divi
Divi je komerční plugin mimo adresář, takže hlavička `Requires Plugins` na něj nefunguje – ta přijímá jen slugy z WordPress.org. **Divi je proto měkká závislost:** plugin musí být plnohodnotný i bez něj. Do v1 se proto přesouvá **shortcode** a **Gutenberg blok** (oba používají tentýž PHP renderer, takže přírůstek je malý) a **konfigurovatelná adresa API**, aby plugin nebyl vázaný na jedinou tělocvičnu.

### Co z pravidel dál plyne pro kód
- **Pravidlo 6 a 7:** v `readme.txt` je povinná sekce `== External services ==`, která pojmenovává oba endpointy, popisuje jaká data se stahují, kdy a kým. Klíčové a snadno doložitelné: **o návštěvnících se neodesílá nic** – žádné osobní údaje, cookies ani identifikátory, a požadavek nikdy nevzniká v prohlížeči návštěvníka.
- **Pravidlo 11:** jediné admin upozornění je zavíratelné hlášení po třech neúspěšných synchronizacích za sebou. Žádné upsell bannery ani žádosti o recenzi.
- **Pravidlo 12:** právě 5 tagů, žádné názvy konkurenčních pluginů.
- **Pravidlo 13:** HTTP přes WordPress HTTP API, plánování přes WP-Cron, žádná přibalená knihovna za běhu.
- **Pravidlo 15:** release workflow selže, pokud se neshoduje git tag, hlavička `Version:` a `Stable tag:`.

Kompletní mapování všech 18 pravidel je v repozitáři v `docs/COMPLIANCE.md`, bezpečnostní kontroly v `docs/SECURITY-CHECKLIST.md`.

---

## 2. Zjištění z živých dat API (ověřeno 19. 8. 2026)

### 2.1 Pole navíc oproti dokumentaci
**`courses.php`** – `image`, `trainer_image`, `tags`, `rating`; `terms[]` = `{ date_txt, date_time_txt, stamp }`
**`activities.php`** – `id_activity_term`, `id_activity`, `activity_description`, `id_tab`, `tab_name`, `id_lane`, `lane_name`, `id_trainer`, `image`, `trainer_image`, `booking_allowed`, `waiting_allowed`, `booking_not_allowed_reason`, `tags`, `rating`

### 2.2 Klíčové zjištění: jak spojit kurz s jeho lekcemi

**API neposkytuje žádný společný identifikátor.** V `activities.php` není `id_course`, v `courses.php` není `id_activity`. Navíc `id_activity` **není identita kurzu** – ověřeno na 11. 9. 2026, kde `id_activity = 368` vystupuje jednou jako *„33-Gymnastika 7-9 let dívky I. pololetí“* (14:00) a podruhé jako *„37-Gymnastika 9-11 let dívky I. pololetí“* (15:00). Je to spíš slot v rozvrhu, ne kurz.

**Funkční spojovací klíč je `activity_name` + `stamp`.** Ověřeno:
kurz 1085 *„37-Gymnastika 9-11 let dívky I. pololetí“*, `terms[0].stamp = 1789131600`
→ lekce 11. 9. 2026 15:00, `stamp_from = 1789131600`, `tab_name = "Gymnastická hala 2"` ✔

Názvy ale nejsou vždy znak po znaku shodné – kurz 1070 má `activity_name = "113- Deskové hry…"` (mezera za pomlčkou), lekce `"113-Deskové hry…"` (bez mezery). **Párování proto poběží přes normalizovaný klíč** (trim, sjednocení vícenásobných mezer, sjednocení velikosti písmen, odstranění diakritiky pro fallback) + shoda `stamp`. Shoda stampu je velmi silná – kolize je prakticky vyloučená.

**Pro nespárované případy bude v administraci obrazovka „Nespárované lekce“** s možností ručně přiřadit lekci ke kurzu. Přiřazení se uloží natrvalo a synchronizace ho nepřepíše.

### 2.3 Důsledek pro filtrování kurzů podle sálu (vaše otázka č. 2)

Kurz má v API `room_name` jako **jediný text** (např. `"Gymnastická hala 2"`, `"TÁBORY"`). To by pro váš požadavek nestačilo, protože aktivita může probíhat ve dvou sálech.

**Řešení: sály kurzu se odvodí z jeho lekcí, ne z `room_name`.** Po spárování se ke kurzu přiřadí *všechny* `id_tab` / `tab_name`, ve kterých jeho termíny reálně probíhají → taxonomie `isport_room` může mít u kurzu více hodnot. `room_name` z API zůstane jako záložní hodnota, když se kurz nepodaří spárovat. Data to potvrzují: pronájem *„Gym Dobřichovice“* běží pod `id_activity` 527/528/529/530 ve čtyřech různých sálech současně.

Stejně vznikne taxonomie `isport_activity` (typ aktivity) – z názvu kurzu očištěného o číselný prefix a označení pololetí, s možností ručního přepsání v adminu.

### 2.4 Další poznatky
- Čísla chodí jako **řetězce** (`"capacity": "1"`, `"price": "1960.00"`), ale `available` a `available_waiting` jako **integer** → nutná normalizace typů.
- `date_from` / `date_to` v `activities.php` fungují přesně dle dokumentace (ověřeno na 1. a 11. 9. 2026).
- **Jeden request vrací kompletní data včetně obsazenosti.** Neexistuje samostatný „capacity“ endpoint. To zásadně zjednodušuje návrh (viz kapitola 4).
- Kurzy aktuálně běží 11. 9. 2026 – 22. 1. 2027, tedy pololetí ≈ 4,5 měsíce.
- Ukázka v dokumentaci API používá `CURLOPT_SSL_VERIFYHOST = FALSE`. Plugin použije `wp_remote_get()` **s ověřením certifikátu** – vypínání kontroly je bezpečnostní riziko.

---

## 2b. Kategorie aktivit v rozvrhu (ověřeno na ostrých datech 21. 8. 2026)

Běh nad oknem 11. 9. – 2. 10. 2026 ukázal, že termíny v rozvrhu spadají do **tří** kategorií, ne dvou:

| Kategorie | Počet | Jak se pozná | Počítá se do úspěšnosti? |
|---|---|---|---|
| **Lekce kurzů** | 334 | štítek „Kurz“ a spárováno s kurzem přes název + stamp | ano |
| **Pronájmy a veřejné vstupy** | 249 | jiný štítek než „Kurz“ (např. „Pronájem haly“) | ne |
| **Aktivity bez přihlášek** | 78 | štítek „Kurz“, ale název je na seznamu v Nastavení | ne |

Třetí kategorie má **tři různé důvody**, které se liší tím, co má návštěvník udělat:

| Podtyp | Příklad | Co zobrazit místo tlačítka |
|---|---|---|
| Kurz externího lektora | Zdravé cvičení, Barre, Karate, Capoeira, Balet, Judo pro děti, Pohyb dětem, Street dance, Tango base, Fyzio cvičení, Zdravá záda, Intenzivní kruhový trénink | kontakt na lektora |
| **Individuální trénink** | Individuální trénink | **cena za lekci + „termín i platba po domluvě, mimo iSport“**; nemá pevné termíny, takže u něj nedává smysl vypisovat rozvrh kurzu |
| Náhradní lekce | Náhradní lekce 4-6 let I. pololetí | náhrada za zameškanou hodinu, řeší se s tělocvičnou |

V API je od běžných kurzů **nic neodlišuje** — nesou stejný štítek „Kurz“, protože to kurzy jsou. Seznam je proto nastavení (`non_bookable_activities`), porovnává se volně jako podřetězec bez diakritiky (rozvrh píše „Zdravé cvičení s overbaly“, ceník „Zdravé cvičení (overbaly)“) a **skutečný kurz má vždy přednost před seznamem**.

**Důsledek pro frontend:** u těchto aktivit nesmí být tlačítko na přihlášení do iSportu. Patří k nim kontakt na lektora.

---

## 3. Architektura

```
iSport API ──► API klient (wp_remote_get, retry, timeout, circuit breaker)
                    │
                    ├─► Synchronizace (WP-Cron, jen dopředu v čase)
                    │      ├─ CPT  isport_course      ← kurzy (editovatelné ve WP)
                    │      ├─ tabulka wp_cscs_lessons ← termíny lekcí
                    │      └─ párování name+stamp → taxonomie sálů u kurzu
                    │
                    └─► Krátká cache (obsazenost = součást téhož payloadu)

Data ──► Renderer (PHP, šablony přepsatelné v child theme JoJo Gym 2.0)
              ├─► Divi 5 moduly (server-side render callback)
              ├─► REST /wp-json/cscs/v1/render → náhled ve Visual Builderu
              ├─► Šablona detailu kurzu (single-isport_course.php)
              └─► Shortcode (záloha mimo Divi)
```

Divi 5 modul = PHP část (`ModuleRegistration::register_module()`, render callback, `Style::add()`) + React část pro Visual Builder. Aby markup neexistoval dvakrát, **React komponenta si vyžádá HTML z REST endpointu** – jediným zdrojem pravdy zůstává PHP.

---

## 4. Zátěž serveru iSportu – návrh (vaše otázka č. 1 a 10)

Toto je nejdůležitější změna oproti v1.0. Protože **jeden request vrací všechny kurzy včetně obsazenosti**, není potřeba žádné samostatné „live“ dotazování. Stačí rozumně nastavené intervaly.

### 4.1 Rozpočet dotazů

| Úloha | Rozsah | Interval | Dotazů / den |
|---|---|---|---|
| Kurzy | `courses.php` (celý výpis, 23 kurzů) | 10 min | 144 |
| Lekce – blízké okno | `activities.php?date_from=dnes&date_to=+21 dní` | 15 min | 96 |
| Lekce – vzdálené okno | `date_from=+21 dní&date_to=konec pololetí` | 1× denně v noci | 1 |
| **Celkem** | | | **≈ 240** |

Pro srovnání: kdyby data volal každý návštěvník, jedna průměrná návštěvnost by znamenala tisíce dotazů denně. Takto je zátěž **konstantní, předvídatelná a nezávislá na návštěvnosti** – to je přesně to, co iSportu při případném dotazu doložíme.

### 4.2 Ochranné mechanismy
- **Nikdy se nedotazujeme na minulost.** `date_from` je vždy „dnes“. Proběhlé termíny se už nikdy nestahují znovu → historie stojí nula dotazů.
- **Hash odpovědi** – když se payload nezměnil, přeskočí se zápis do DB.
- **Circuit breaker** – po 3 chybách za sebou se synchronizace uspí na 30 minut a odejde e-mail administrátorovi.
- **Tvrdý strop** – nastavitelné „max. dotazů za hodinu“, které cron nikdy nepřekročí.
- **Adaptivní zpomalení** – pokud na stránky s kurzy nikdo nepřijde déle než X hodin (např. v noci), intervaly se automaticky prodlouží.
- **Mutex** (transient lock), aby dva crony neběžely současně.
- **Návštěvník nikdy nevolá API.** Čte se výhradně z databáze; při expiraci cache se vrátí poslední známá data a obnova proběhne na pozadí (stale-while-revalidate).
- **Fallback při výpadku** – zobrazí se poslední známá data, ne prázdná stránka.

### 4.3 Retence dat – doporučení k otázce č. 10

Vše řídí jediné nastavení **„Aktuální pololetí: od – do“**, které se zadává **ručně v Nastavení a platí společně pro všechny kurzy** (ne per kurz). Z něj se odvodí rozsah synchronizace i to, které kurzy jsou „probíhající“. Při přechodu na další pololetí stačí přepsat dvě data na jednom místě.

| Data | Pravidlo | Proč |
|---|---|---|
| **Termíny lekcí** | uchovat od **−30 dnů** (nastavitelné 0–365) do konce pololetí; starší noční úlohou smazat | rozvrh je pohledový nástroj, historii nikdo nelistuje; −30 dnů stačí na „co bylo minulý týden“ |
| **Proběhlé termíny** | jsou **zmrazené** – po uplynutí data se už nikdy neaktualizují ani nestahují | nulová zátěž API i DB |
| **Kurzy (CPT)** | **nikdy nemazat**; stav `probíhající` → `ukončený` (po `date_to`) → `archivovaný` (ručně) | mají vlastní stránku, obsah, obrázky a hodnotu pro SEO; smazáním byste přišel o odkazy a o práci |
| **Ukončené kurzy** | ve výpisech skryté, stránka zůstává dostupná, volitelně s poznámkou „Tento kurz již proběhl“ a odkazem na aktuální nabídku | zachová SEO a zároveň nemate návštěvníky |
| **Log synchronizací** | 90 dnů | diagnostika |

Objem je zanedbatelný: ≈ 38 lekcí denně × ~150 dnů pololetí ≈ **6 000 řádků** ve vlastní tabulce. To je pro MySQL nic.

---

## 5. Datový model

**CPT `isport_course`** (veřejný, vlastní stránka detailu)
- `post_title` ← `course_name`, přepis správcem respektován
- `post_content` ← vlastní rozšířený popis (popis z API drženo zvlášť v meta)
- meta: `_cscs_id_course`, `_cscs_price`, `_cscs_number_lessons`, `_cscs_capacity`, `_cscs_capacity_waiting`, `_cscs_occupied`, `_cscs_available`, `_cscs_available_waiting`, `_cscs_date_from`, `_cscs_date_to`, `_cscs_stamp_from`, `_cscs_stamp_to`, `_cscs_course_url`, `_cscs_color`, `_cscs_background`, `_cscs_image`, `_cscs_trainer_image`, `_cscs_rating`, `_cscs_terms` (JSON), `_cscs_api_description`, `_cscs_status`, `_cscs_synced_at`
- **`_cscs_show_isport_button`** – `dědit / vždy zobrazit / vždy skrýt` (viz 6.3)
- `_cscs_locked_fields` – seznam polí ručně upravených, která synchronizace nepřepíše
- taxonomie: `isport_room` (**více hodnot**, odvozeno z lekcí), `isport_trainer`, `isport_activity`, `isport_tag`

**Tabulka `wp_cscs_lessons`**
`id_activity_term` (PK), `id_activity`, `id_course` (výsledek párování, nullable), `activity_name`, `match_key` (normalizovaný název), `stamp_from`, `stamp_to`, `date`, `time_from`, `time_to`, `id_tab`, `tab_name`, `id_trainer`, `trainer_name`, `id_lane`, `lane_name`, `price`, `color`, `background`, `capacity`, `capacity_waiting`, `occupied`, `available`, `available_waiting`, `canceled`, `booking_allowed`, `waiting_allowed`, `activity_url`, `tab_url`, `tags` (JSON), `payload` (JSON), `synced_at`
Indexy: `stamp_from`, `id_tab`, `date`, `canceled`, `id_course`, `match_key`

**Tabulka `wp_cscs_sync_log`** – čas, typ, počet záznamů, doba trvání, výsledek, chyba.

---

## 6. Administrace

### 6.1 Menu
```
iSport
├── Přehled              – stav synchronizace, poslední běh, chyby, „Synchronizovat nyní“, počítadlo dotazů
├── Kurzy                – CPT: obsah, obrázky, zámky polí, přepínač tlačítka iSport, hromadné akce
├── Nespárované lekce    – ruční přiřazení lekce ke kurzu
├── Zobrazovací sady     – konfigurace obsahu pro moduly (viz 6.2)
├── Sály a aktivity      – mapování id_tab → název, pořadí, barva, viditelnost
├── Nastavení            – URL API, pololetí od–do, intervaly, retence, formát ceny, zrušené lekce, breakpoint
└── Log                  – historie synchronizací a chyb
```

### 6.2 Zobrazovací sady
Pojmenovaná konfigurace obsahu, např. *„Kurzy pro děti – domovská stránka“*: které sloupce a v jakém pořadí, vlastní popisky, filtry (sál, trenér, aktivita, rozsah dat, jen s volnými místy), **zahrnout / vyloučit pronájmy a cizí oddíly**, zobrazit / skrýt zrušené lekce, řazení, počet položek, stránkování, texty (nadpis, CTA, prázdný stav, vyprodáno), prahy pro barevné stavy.

Divi modul obsahuje **jediné pole viditelné správci: výběr sady.** Změna sady se projeví všude, kde je použitá, bez otevření Divi.

### 6.3 Tlačítko „Přihlásit v iSport systému“ (vaše otázka č. 3)
Trojúrovňové řízení:
1. **Globální výchozí** v Nastavení – zobrazovat / nezobrazovat.
2. **Na úrovni kurzu** – přepínač `dědit / vždy zobrazit / vždy skrýt` přímo v editaci kurzu.
3. **Hromadně** – v seznamu kurzů zaškrtnout libovolné kurzy a použít hromadnou akci *„Zobrazit tlačítko iSport“* / *„Skrýt tlačítko iSport“*. Doplněno rychlým filtrem, aby šlo hromadně označit např. všechny kurzy jednoho sálu.

Tlačítko se navíc **automaticky skryje**, pokud je kurz plný nebo API hlásí `booking_allowed = 0` (s využitím `booking_not_allowed_reason` jako popisku).

### 6.4 Role a oprávnění
- Nová role **„Správce iSport“** (`cscs_manager`) – capability `cscs_manage_content` (Zobrazovací sady, obsah kurzů, tlačítka, texty, nespárované lekce) + základní `read`, `upload_files`.
- `cscs_manage_design` – designové skupiny v Divi modulech a Nastavení pluginu. Pouze role *Administrator*.
- Zamčení není kosmetické: designové atributy se při ukládání **validují na serveru** proti oprávnění uživatele → nelze obejít úpravou DOM ani přímým voláním REST.

---

## 7. Zobrazení a formátování

### 7.1 Cena (vaše otázka č. 8)
- Formát `1 960 Kč` – mezera jako oddělovač tisíců (pevná mezera, aby se nelámalo), bez zaokrouhlování; `.00` se skryje, nenulové desetiny se zobrazí.
- `price = null` u **kurzu** → **„Zdarma“**.
- `price = null` u **lekce** (pronájmy a veřejné vstupy typu *„Veřejnost“*, *„Gym Dobřichovice“*, *„Sokol Radotín“*) → v Nastavení **přepínač „Zdarma“ / „Na dotaz“**, výchozí *„Na dotaz“*. Oba texty jsou zároveň editovatelné, aby šlo použít i vlastní formulaci.
- Chování je tedy oddělené: kurzy mají pevně „Zdarma“, lekce řídí nastavení.

### 7.2 Zrušené lekce (vaše otázka č. 9)
Výchozí stav: přeškrtnutý řádek se štítkem **„Zrušeno“** a sníženou sytostí barev. V Nastavení přepínač **„Zrušené lekce: zobrazit / skrýt“**, přenositelný i do jednotlivé Zobrazovací sady (aby šlo mít na hlavní stránce skryté a v úplném rozvrhu viditelné).

### 7.2b Pronájmy a cizí oddíly v rozvrhu
Lekce, které nejsou vlastní kurz Jojo Gymu (*„Veřejnost“*, *„Gym Dobřichovice“*, *„Sokol Radotín“*, *„Hradčany“*…), se rozpoznají podle toho, že se nepodařilo spárovat je s žádným kurzem, a podle štítků z pole `tags` (např. *„Pronájem haly“*). V každé **Zobrazovací sadě** bude přepínač **„Zahrnout pronájmy a cizí oddíly“** – na hlavní stránce je tak lze skrýt, zatímco v úplném rozvrhu obsazenosti hal zůstanou viditelné. Seznam toho, co se za pronájem považuje, půjde v administraci ručně upravit.

### 7.3 Responzivní tabulky
```
Desktop                          Mobil (< 768 px, nastavitelné)
┌────────┬─────┬──────┐          ┌──────────────────────┐
│ Kurz   │Cena │Místa │          │ Kurz    Aerobic mini │
├────────┼─────┼──────┤    →     │ Cena         1 200 Kč│
│Aerobic │1200 │  3   │          │ Místa               3│
└────────┴─────┴──────┘          └──────────────────────┘
```
Sémantická `<table>`, každé `<td>` nese `data-label`; v media query `display: block` + `td::before { content: attr(data-label) }`. HTML zůstává validní tabulkou (dobré pro čtečky i tisk). Alternativní režimy volitelné v sadě: karty, nebo vodorovný scroll s ukotveným prvním sloupcem (u širokého rozvrhu čitelnější).

**Přístupnost:** `<caption>`, `scope` na hlavičkách, ARIA role v blokovém režimu, kontrola kontrastu barev z API (`color` na `background`) s bezpečným fallbackem, ovládání filtrů z klávesnice.

### 7.4 Lokalizace (vaše otázka č. 4)
Všechny řetězce přes `__()` / `esc_html__()` s text domain `course-schedule-connector`, generovaný `.pot`, česká `.mo` v balíčku. Data z API (názvy kurzů, sálů) se nepřekládají, ale texty rozhraní ano. Datum a čas přes `wp_date()` a `wp_timezone()` – nikdy ne systémový čas serveru. Cena přes formátovací vrstvu, aby šlo v budoucnu změnit měnu.

---

## 8. Výstupy na frontendu (verze 1)

| Modul | Slug | Zdroj dat | Zobrazení |
|---|---|---|---|
| Kurzy – karty | `cscs/courses-grid` | CPT | dlaždice: obrázek, název, trenér, sály, cena, termín, volná místa, CTA |
| Kurzy – tabulka | `cscs/courses-table` | CPT | volitelné sloupce, řazení, na mobilu překlopená |
| Rozvrh – seznam po dnech | `cscs/schedule-list` | `wp_cscs_lessons` | seskupení po dnech, filtr sálu, rozsah dat |
| Rozvrh – týdenní kalendář | `cscs/schedule-calendar` | `wp_cscs_lessons` | mřížka sál × čas, barvy z API, na mobilu přepnutí na seznam |

**Kromě Divi modulů (nově v v1):**

| Výstup | Zápis | Určeno pro |
|---|---|---|
| Shortcode | `[cscs_courses set="…"]`, `[cscs_schedule set="…"]` | klasický editor, widget, šablona, jakýkoli motiv |
| Gutenberg blok | `cscs/display` s výběrem sady v postranním panelu | editor bloků |

Všechny tři cesty volají **tentýž PHP renderer**, takže výstup je identický a údržba jednoho místa.

Nastavení viditelné správci: **jen výběr Zobrazovací sady.**
Nastavení jen pro administrátora: typografie, barvy, mezery, rámečky, hover stavy, breakpoint překlopení, poměr stran obrázků.

Šablony budou přepsatelné v child theme **JoJo Gym 2.0** (`/jojo-isport/*.php`), aby šlo dělat úpravy mimo plugin.

---

## 9. Fáze projektu

| # | Fáze | Obsah | Odhad |
|---|---|---|---|
| **F0** | Repozitář a standardy | ✅ *hotovo* – licence GPL-2.0+, `README.md`, `readme.txt`, `CHANGELOG`, `CONTRIBUTING`, `SECURITY`, Code of Conduct, PHPCS/PHPStan konfigurace, CI a release workflow, šablony issues, Dependabot, `docs/` | 0,5 dne |
| **F1** | Jádro + API klient | ✅ *hotovo* – bootstrap, vlastní PSR-4 autoloader, klient s retry/timeout/circuit breakerem a hodinovým stropem, **validace base URL proti SSRF**, mapper, normalizace typů, cache se stale-while-revalidate, WP-CLI `wp cscs api`, 47 unit testů | 2 dny |
| **F2** | Datový model + synchronizace | ✅ *hotovo a ověřeno na ostrých datech* – CPT `cscs_course` a 4 taxonomie, tabulky lekcí a logu, cron se čtyřmi úlohami, **párování name+stamp**, odvození sálů z lekcí, zámky polí, retence, `uninstall.php`, WP-CLI `wp cscs sync` a `wp cscs settings`, 93 unit testů. **Úspěšnost párování 100 %** (113 kurzů, 661 termínů, 334 spárovaných, 0 nevyřešených) | 2,5 dne |
| **F3a** | Administrace – základ | ✅ *hotovo* – menu iSport, obrazovka Přehled (co je uloženo, stav připojení, poslední běhy, ruční synchronizace), obrazovka Nastavení přes validující setter, role **Správce iSport** a oprávnění vynucená při uložení | 1 den |
| **F3b** | Administrace – obsah | Zobrazovací sady, editace kurzu (obohacený obsah, kontakt na lektora, příznak přihlášek, zámky polí), **ruční kurzy** zakládané ručně i jedním kliknutím z nespárovaného termínu, obrazovka nespárovaných lekcí, mapování sálů, hromadné akce | 1,5 dne |
| **F4** | Renderer + styly | Šablonový systém, responzivní tabulky, stavy (vyprodáno, zrušeno, poslední místa), formátování ceny, přístupnost | 1,5 dne |
| **F5** | Shortcode + Gutenberg blok | Nezávislost na Divi: `[cscs_courses]`, `[cscs_schedule]`, blok `cscs/display`, REST náhled | 1,5 dne |
| **F6** | Divi 5 moduly | 4 moduly: `module.json`, PHP render, React edit přes REST náhled, webpack build, oddělení skupin dle role | 3 dny |
| **F7** | Detail kurzu + frontend | Šablona `single-cscs_course.php`, tlačítko iSport dle nastavení, JSON-LD, filtrování a stránkování bez reloadu, přepínač týdnů | 2 dny |
| **F8** | Bezpečnostní audit | Průchod celého `docs/SECURITY-CHECKLIST.md` bod po bodu, escapování, nonce, capability, `$wpdb->prepare()`, `uninstall.php`, test s `WP_DEBUG` | 1,5 dne |
| **F9** | Výkon, i18n, přístupnost | Profilování dotazů, cache-warming, `.pot` + čeština, audit WCAG 2.1 AA | 1,5 dne |
| **F10** | Testy a Plugin Check | PHPUnit s fixturami odpovědí API, průchod oficiálním **Plugin Check** bez chyb a varování, test bez Divi i s Divi | 1,5 dne |
| **F11** | Dokumentace a dodání | Dopsání `USER-GUIDE.md` a `DEVELOPER.md` podle skutečné implementace, snímky obrazovek, verzování, dva buildy, případné podání na WordPress.org | 2 dny |
| **F12** | Akceptační brána | Průchod celým `docs/RELEASE-CHECKLIST.md` na **čisté** instalaci WordPressu, doložení každé položky, oboustranný podpis; teprve pak případné podání do adresáře | 1,5 dne |

**Celkem ≈ 23,5 pracovního dne** (nárůst proti v1.2 o shortcode a blok, samostatný bezpečnostní audit, Plugin Check a plnou dokumentaci).

Fáze F1–F5 jsou nezávislé na Divi a plně otestovatelné přes shortcode. Kdyby se Divi 5 API změnilo, přijdeme maximálně o práci z F6.

**Kontrolní body k odsouhlasení:** po F2 (synchronizovaná data + úspěšnost párování), po F5 (hotový vzhled přes shortcode, bez Divi), po F6 (moduly v Divi) a po F10 (čistý Plugin Check).


## 10. Distribuce, licence a dokumentace

### Licence
**GPL-2.0-or-later** pro celý plugin – podmínka pravidla 1. Plný text licence je v repozitáři v `LICENSE`. Za běhu se nepřibaluje žádná knihovna třetí strany; vývojové nástroje (PHPCS, PHPStan, webpack, Babel) jsou pouze `require-dev` a do distribuce se nedostanou. V `README.md` je prohlášení, že plugin není spojen s iSport System ani Elegant Themes a že obě označení jsou ochranné známky svých vlastníků.

### Dva buildy
| Balíček | `includes/Updater/` | Distribuce |
|---|---|---|
| `course-schedule-connector.zip` | ano | GitHub Releases, aktualizace přímo ve WordPressu |
| `course-schedule-connector-wporg.zip` | odstraněn při buildu | WordPress.org SVN |

Release workflow se spustí na tag `v*.*.*`, ověří shodu tagu s hlavičkou `Version:` a se `Stable tag:`, sestaví oba balíčky a připojí je k releasu.

### Kontrola kvality při každém pull requestu
PHP_CodeSniffer (WordPress + WordPress-Docs), PHPStan level 6, PHPUnit a oficiální **Plugin Check** – tentýž nástroj, který používá recenzní tým WordPress.org. Pull request, který kteroukoli kontrolu neprojde, nelze sloučit.

### Dokumentace v repozitáři
| Soubor | Pro koho |
|---|---|
| `docs/USER-GUIDE.md` | správce webu, česky |
| `docs/DEVELOPER.md` | vývojáři – datový model, hooky, šablony, REST, WP-CLI |
| `docs/COMPLIANCE.md` | mapování všech 18 pravidel WordPress.org |
| `docs/SECURITY-CHECKLIST.md` | bezpečnostní kontroly, každá jako podmínka pro merge |
| `docs/RELEASE-CHECKLIST.md` | akceptační brána před vydáním, s podpisy |
| `README.md`, `readme.txt` | GitHub / WordPress.org |
| `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md` | přispěvatelé |

## 11. Akceptační brána před vydáním

Podání do adresáře WordPress.org proběhne **teprve po naprosté jistotě**, že plugin splňuje bezpečnostní i obsahové požadavky a spolehlivě funguje. Aby to nebyl jen dobrý pocit, je ta jistota rozepsaná do měřitelného seznamu v `docs/RELEASE-CHECKLIST.md`. Deset oddílů, u každé položky musí existovat důkaz – běh CI, snímek obrazovky nebo log, ne názor.

| Oddíl | Co se ověřuje | Tvrdé kritérium |
|---|---|---|
| 1. Automatické brány | PHPCS, PHPStan, PHPUnit, build, Plugin Check | **nula chyb i varování**, včetně kategorie *Plugin Repository* |
| 2. Bezpečnost | každý řádek `SECURITY-CHECKLIST.md` ověřený čtením kódu | mimo jiné: pokus uložit design bez oprávnění musí selhat na serveru |
| 3. Pravidla adresáře | znovu projité mapování všech 18 pravidel | build pro WordPress.org neobsahuje updater — ověřeno rozbalením balíčku |
| 4. Funkční ověření | čistá instalace WordPressu, ne vývojový web | **funguje s vypnutým Divi**; úspěšnost párování nad 95 %; při nedostupném API se web nerozbije |
| 5. Oprávnění | role Správce iSport | nevidí design v Divi a **neuloží ho ani podvrženým požadavkem** |
| 6. Přístupnost | Axe, čtečka, klávesnice, 200% zoom, 320 px | nula porušení |
| 7. Výkon | Query Monitor | **na frontendu nesmí vzniknout žádný odchozí HTTP požadavek** |
| 8. Lokalizace | `.pot`, čeština, formátování data a ceny | žádné skládání řetězců uvnitř překladové funkce |
| 9. Dokumentace | příručka i vývojářská dokumentace odpovídají skutečnosti | včetně snímků obrazovek |
| 10. Čistý debug | `WP_DEBUG`, `WP_DEBUG_LOG`, `SCRIPT_DEBUG`, `SAVEQUERIES` | **prázdný log**, žádná chyba v konzoli |

Na konci jsou dva podpisy – můj a váš. Bez obou se nic nikam neodesílá.

*Vyřešeno:* účet na WordPress.org je `1uka5i5ta`, repozitář `course-schedule-connector` je veřejný (což zároveň splňuje pravidlo 4 a odpadá potřeba tokenu pro aktualizace z GitHubu), podání do adresáře je podmíněné touto branou.

## 12. Rizika

| Riziko | Dopad | Opatření |
|---|---|---|
| Párování kurz ↔ lekce podle názvu selže | Kurz nebude mít sály, kalendář nespojí data | Normalizovaný klíč + shoda `stamp` + obrazovka „Nespárované lekce“ s ručním přiřazením + hlášení míry úspěšnosti v Přehledu |
| Divi 5 API se vyvíjí | Moduly přestanou fungovat po aktualizaci | Moduly tenké, logika v PHP vrstvě nezávislé na Divi; test po každé aktualizaci |
| API bez verzování a SLA | Změna formátu rozbije web | Jeden mapper, defenzivní čtení klíčů, soft-fail místo fatální chyby |
| Vnímaná zátěž iSportu | Zablokování přístupu | Konstantních ≈ 240 dotazů denně, tvrdý strop, circuit breaker, doložitelné počítadlo v Přehledu |
| Ruční úpravy přepsané synchronizací | Ztráta práce správce | Zámky polí + soft-delete |
| Obejití zámku designu | Rozbitý vzhled | Serverová validace atributů podle capability |
| Diakritika a kódování | Rozsypaná čeština | Kontrola `Content-Type`, vynucení UTF-8, ošetřený `json_decode` |

---

## 13. Co udělám hned po odsouhlasení

1. Ověřím Divi 5.11.0 a child theme JoJo Gym 2.0 na `localhost:8888` a založím kostru pluginu podle `docs/DEVELOPER.md`
2. Implementuji F1 – API klient s validací base URL a normalizací typů – a ukážu funkční výpis dat přes `wp cscs sync courses`, ještě než vznikne jakékoli UI
3. Hned v F2 doložím **míru úspěšnosti párování** kurzů a lekcí; to je jediné místo, kde by návrh mohl narazit
