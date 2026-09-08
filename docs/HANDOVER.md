# Předání práce — deník po dnech

> **Začínáte-li od nuly, čtěte `ALPHA.md`.** Ten shrnuje celý projekt na jednom
> místě. Tenhle soubor je to, co se dělo den po dni, a je užitečný, když je
> potřeba vědět *proč* něco vypadá, jak vypadá. Do vydaného pluginu se
> nedistribuuje.

## Stav k 8. 9. 2026

Tenhle soubor je most mezi pracovními dny. Je psaný tak, aby se do něj dalo
vstoupit bez znalosti předchozího rozhovoru: co je hotové, jak se to spouští,
co je otevřené. Až se projekt uzavře, zmizí — do vydaného pluginu se nedistribuuje.

---

## 1. Kde projekt stojí

Fáze **F0 – F11 jsou hotové**, F12 (akceptační brána) je odložená. Podrobné
zadání každé fáze je v `PLAN.md`, kapitola 9. Běží **testovací a vylepšovací
fáze**: verze `1.0.0-alpha.2` je její první kolo.

**Lukášova prohlídka pluginu běží dál a má přednost před F8.** Všechno, co z ní
zatím vzešlo, je hotové a ověřené proti živým datům. Poslední kolo přineslo:
druh kurzu (taxonomie + vlastní stránka), věk, pohlaví a úroveň, popis
z iSportu, srovnané odrážky a rozdělení panelů bloku na *Nastavení* a *Styly*.

Poslední commity:

| commit | co přinesl |
|---|---|
| `b90e32e` | popis druhu z kurzů, ceny podle délky lekce, sloupec Délka |
| `bcbb77f` | druh kurzu má vlastní stránku a modul s rozvrhem svých kurzů |
| `41249c4` | jedna úroveň odrážek a jejich nastavení; panely bloku do karty Styly |
| `eaaaaa6` | Popis z iSportu jako vlastní blok i modul; stránka kurzu ho doplní |
| `e90d138` | druh se čte z názvu aktivity, kde se liší od názvu kurzu |
| `39cc66d` | druh kurzu jako taxonomie — karta se postaví zaškrtnutím druhu |
| `8407a63` | skupiny kurzů pod jedním jménem (ruční výběr + filtry), věk kurzu |
| `29fadad` | pohlaví a úroveň kurzu — čtení z názvu, ruční přepis, překlady s kontextem |
| `36ad88f` | sloupce Den a Čas od–do; stylopis se dostane i do Theme Builderu |
| `a7b9d1d` | sekce iSport, ikony modulů, nastavení vzhledu tabulky |
| `1b74a31` | builder ukazuje design už při návrhu (`elementType` + order class) |
| `e1a9df2` | modul Fotografie se chová jako obrázek |
| `838aeca` | prázdné pole mizí ze stránky i s obalem |
| `bd8ced9` | české slugy `/kurz/` a `/trener/` |
| `1c44582` | Divi 5 modul pro každé pole, nadpis stylovaný zvlášť od hodnoty |
| `7672436` | pojmenovaný Gutenberg blok pro každé pole |
| `ba734c1` | Den a Čas místo „Kdy“; trenéři jako typ příspěvku |
| `49cde06` | předchozí předání práce |

Testy: **225 prochází**. Spouští se `php tools/phpunit-shim/run.php` z kořene
repozitáře.

**Nepushnuté commity** na Macu — viz problém s portem 443 níže.

---

## 2. Co přibylo naposledy

### Alfa 2 (8. 9.) — devět hlášení z provozu, tři z nich jedna chyba

První kolo testování ostré alfy. Devět bodů od Lukáše; nejzajímavější je, že
tři z nich — nespárované lekce, mizející kurzy a nefungující načítání popisů —
měly **jednu příčinu**: `Client::get_courses()` se ptal iSportu bez data
a dostal jen kurzy, jejichž nejbližší lekce je teprve před námi (73 ze 113).
Ten neúplný seznam brala archivace, párování i popisy. Výchozí datum se teď
bere z nastavení **Term starts** minus měsíc.

Podobně dopadly fotky trenérů: pojistka proti opakovanému stahování tam byla
a ptala se špatně (poslední adresa místo „někdy viděná"), protože iSport vede
pod jedním jménem víc záznamů trenéra. V knihovně médií bylo 2 116 obrázků
dvaadvaceti lidí. Po opravě a `wp cscs trainers tidy` jich je 115.

Dál v tomhle kole: stav **Zrušený kurz** s přesměrováním na druh kurzu,
pozastavení synchronizace, hromadné načtení popisů, export/import nastavení,
sloupce Popis a Kurzy v seznamu druhů, hláška u tlačítka Načíst popis
a zúžení rozbalovacího seznamu v postranním meta boxu. Podrobně
v `../CHANGELOG.md` pod `1.0.0-alpha.2`.

Tři věci, které se v tomhle kole ukázaly a platí dál:

1. **`post_status => 'any'` neznamená any.** WordPress to čte jako „každý stav
   nevyloučený z vyhledávání". Nový stav `cscs_cancelled` vyloučený je, takže
   `find()`, `all_ids()` i `names()` by zrušený kurz přehlédly a synchronizace
   by z něj udělala druhou kopii. Na to je `CourseRepository::every_status()`.
2. **`width: 100%` neudrží select v úzkém sloupci.** Select je široký jako jeho
   nejdelší položka; ve sloupci, který se přizpůsobuje obsahu, se procento
   počítá ze šířky, kterou select sám roztáhl. Řeší se zkrácením textu položek.
3. **iSport nemá stabilní identifikátory tam, kde je čekáme.** Jedno jméno
   trenéra = několik záznamů s různými fotkami. Osm kurzů má lekce v rozvrhu,
   ale ve výpisu kurzů nejsou vůbec. Cokoli, co se páruje podle jména nebo se
   spoléhá na „poslední viděnou hodnotu", je proto potenciálně tahle chyba znovu.

Odshora nejnovější. Každá kapitola je psaná tak, aby stačila sama o sobě:
proč to tak je, kde to v kódu leží a co se tím na živých datech ověřilo.


### Popis druhu a ceny podle délky
Stránka druhu se při založení plní `KindRepository::fill_description()` —
nejčastějším `_cscs_api_description` mezi kurzy druhu, a jen když je prázdná.
Na živých datech to vyplnilo **23 z 25** stránek. Panel *Popis z kurzu*
(`Admin\KindEditor`) načte popis konkrétního kurzu na kliknutí; je to odkaz
s nonce přes `admin-post.php`, ne formulář — formulář ve formuláři blokového
editoru je nevalidní.

`KindDetail::price_listing()` páruje cenu s délkou první lekce
(`Formatter::minutes_between()`), klíčuje dvojicí a nechává z každé dvojice
první kurz — dvacet dva kurzů *Gymnastiky* dá dva řádky, ne dvacet dva — a vrací
běžný `Listing` o dvou sloupcích, **Délka** a **Cena**. Na živých datech: 25
druhů, žádný neopakuje řádek, šest jich má dva. Sloupec `duration` je
k dispozici všem výpisům, nejen druhu; `course_listing()` ho ani `price`
nepoužívá — z rozvrhu druhu odešly, když ceny dostaly vlastní tabulku.

Pole `kind-text` vykresluje `post_content` stránky druhu přes
`Fields::written_text()`. *Popis kurzu* i *Popis z iSportu* se ptají kurzu,
takže na stránce druhu nemají co ukázat, a v šabloně Theme Builderu není kam
text napsat ručně.

Seznam příspěvků, na které lze modul namířit, se staví procházkou
`Fields::all()` a `Fields::post_type()` — v `FieldModules` i `FieldBlocks`.
Dřív to byla ruční mapa dvou kontextů v každém; druhy nebyly ani v jedné,
`$sources[$context]` na chybějícím klíči zapsalo `null` a Divi na to v panelu
nastavení odpoví „tento obsah nelze zobrazit", zatímco frontend byl v pořádku.
Hlídá to `FieldModulesTest::test_every_context_has_a_post_type_to_be_pointed_at()`.

### Plánovač (opraveno 29. 8.)
`Scheduler::schedule()` běží nově i na `init` a registruje `cron_schedules`
dřív, než cokoli plánuje. `wp_schedule_event()` ověřuje recurrence proti
`wp_get_schedules()`; při aktivaci ještě plugin není v seznamu aktivních, takže
`Plugin::register()` filtr nepřidal a obě úlohy na vlastních intervalech
(`cscs_sync_courses`, `cscs_sync_lessons_near`) se **nenaplánovaly**, zatímco
obě `daily` ano. Frontend o tom nic neřekne — stránky se vykreslují dál a čísla
stojí. Na živých datech bylo 44 ze 113 kurzů zastaralých. Neznámý interval teď
padá na `hourly`, `reschedule()` přeplánuje úlohu po změně intervalu
(`Plugin::on_setting_changed()`) a *iSport → Přehled* má tabulku **Naplánované
úlohy** s `wp_next_scheduled()` u všech čtyř.

### Publikum se ztrácelo při uložení kurzu
Editor nabízí „jak říká název", což smaže uloženou hodnotu — správně — ale nic
pak název nepřečetlo až do další synchronizace. Dva kurzy ze 113 tak byly bez
věku, pohlaví a úrovně. `CourseRepository::refresh_audience()` se volá na konci
`CourseEditor::save()`.

### F11 (29. 8.) — dokumentace a dodání
Verze **1.0.0** (hlavička, `CSCS_VERSION`, `Stable tag`, CHANGELOG).

Dokumentace srovnána se skutečností, ne jen doplněna. Co bylo v DEVELOPERu
špatně: seznam šablon jmenoval pět souborů, které nikdy neexistovaly
(`courses-grid.php`…); druhá kapitola „Divi 5 modules" vypisovala čtyři vymyšlené
moduly; tabulka hooků měla sedm „planned" filtrů, které nevznikly, a chyběly
čtyři skutečné; úvod tvrdil, že vše od rendereru dál je teprve specifikace.
Seznam WP-CLI příkazů neznal `sets`, `caps`, `trainers`, `divi` ani `sync kinds`.
Hooky jsem porovnal grepem proti zdroji, ne po paměti.

`readme.txt`: opraveny nepravdivé body (karty a kalendářní mřížka neexistují,
e-mailové upozornění taky ne), doplněny druhy, filtr na stránce druhu a sekce
`== Screenshots ==`. `.wordpress-org/README.md` popisuje osm snímků a jak je
pořídit — samotné PNG musí vzniknout z prohlížeče, na to kontejner nedosáhne.

**Dvě vady našel až snímek obrazovky**, ne kód: složená tabulka na telefonu
nechávala popisky u prázdných buněk (`td:empty` nezabírá, protože šablona dovnitř
píše konec řádku — teď třída `cscs-col--empty`), a osmisloupcová tabulka
přetékala mimo stránku (`.cscs-table-scroll`, `role="region" tabindex="0"`).

### F10 (29. 8.) — Plugin Check a čistá instalace
**Plugin Check 2.1.0: 0 chyb, 0 varování**, všechny kategorie včetně
`plugin_repo`, `--include-experimental`, proti buildu pro WordPress.org na
čistém WP 7.1 (Studio site „CSCS Clean Check", localhost:8893 — nechal jsem ji
stát pro F12).

Opraveno 29 nálezů. Podstatné: ABSPATH guard byl v 61 souborech až pod blokem
`use`, kam se nedívá; všechny proměnné, které čtou šablony, mají prefix
(`$cscs_listing`, `$cscs_detail`, …), protože šablona se includuje na úrovni
souboru; `load_plugin_textdomain()` zrušeno, WP načítá překlady pluginu sám od
6.7 (= nové *Requires at least*). Zbytek byly sniffy, na které plugin
odpovídal pro sadu WordPressu a ne pro sadu Plugin Checku — ignore teď jmenují
obě a říkají proč.

Buildy staví `/tmp/mkbuilds.sh` (v kontejneru) podle `.distignore`; wporg
varianta navíc maže `includes/Updater` a hlavičku `Update URI`.

**Pozor:** přejmenování proměnných rozbilo `Fields::table()`, které nastavovalo
starý název — našel to až běh s Divi. Opraveno; je to důvod, proč se musí
zkoušet obojí.

### F9 (29. 8.) — výkon, i18n, přístupnost
Výpis 113 kurzů: 119 → **7 dotazů**, 191 → 51 ms. Viník byl
`wp_get_object_terms()` v `course_row()` (obchází cache, běžel na řádek);
teď `get_the_terms()` + `Query::prime()` před stavěním řádků. `prime()` má dvě
větve: dostane-li `WP_Post`y, plní term a meta cache; dostane-li id (druh,
trenér), pustí jeden `WP_Query`, jehož výsledek zahodí — tím se naplní i post
cache. Druh: 37 → 17 dotazů. Frontend nedělá žádný odchozí požadavek (ověřeno
přes `pre_http_request`).

i18n: 575 řetězců, 0 nepřeložených, kontrola překladových volání parserem
(`token_get_all`), ne grepem — žádné skládání, chybějící doména ani placeholder
bez komentáře. Cena se formátuje přes `number_format_i18n()` a měna je
překladový řetězec; `CourseDetail::short_date()` bere `get_option('date_format')`.

Přístupnost: `Listing::spoken()` + živá oblast `#cscs-status` v `cscs.js`
(WCAG 4.1.3 — výměna tabulky bez pohybu focusu se jinak neohlásí), `<caption>`
z nadpisu sady, `:focus-visible` v `currentColor`. Doplněna i chybějící tabulka
**Naplánované úlohy** v Přehledu, která z F8 zůstala jen jako pomocná metoda.

### Oddíl bloků
`Render\BlockCategory` (slug `cscs`, titulek „iSport") na `block_categories_all`,
vsazený za poslední kategorii, kterou dodává WordPress. Jmenuje ho `FieldBlocks`
přes `BlockCategory::SLUG` a `blocks/display/block.json` literálem — hlídá
`BlockMetadataTest`. Divi moduly zůstávají na `category: module` +
`folder: cscs-modules`, to je jiný seznam. Živě: 31 gutenbergovských bloků
v oddílu, 0 mimo.

### Filtr patří na stránku druhu, ne do šablony
Klíčové zjištění: v globální šabloně (Divi Theme Builder / Šablony) nelze filtr
nastavit v modulu — je to jeden design pro všechny stránky druhu. Proto
`KindType::META_FILTER` (klíče v `KindRepository::FILTER_KEYS`) a
`KindType::META_TERM` (druh zvolený ručně; `term_for()` ho upřednostní před
názvem) na stránce druhu, panel *Které kurzy tato stránka ukazuje*
(`KindEditor::render_courses_box()`), a řádková akce **Kopírovat**
(`KindEditor::duplicate()` — přenáší text, výpisek, obrázek a filtr, **ne**
`META_KEY_NAME`).

`KindDetail::asked()` odděluje „nevyplněno" od „vyplněno na výchozí": modul
posílá všechny hodnoty vždycky, takže `filterOrder=asc` bez `filterSort` se
nesmí počítat jako rozhodnutí. Bez toho by šablona přebila všech 26 stránek.
Filtrují se i ceny (`price_listing()`), aby stránka pro kluky ukázala jen jejich
cenu. Ověřeno živě: 22 / 18 / 2 řádků, ceny 2 / 2 / 1.

### Filtry v modulu Kurzy tohoto druhu
`'filters' => true` v katalogu → atributy `filterGenders`, `filterLevels`,
`filterAgeMin`, `filterAgeMax`, `filterSort`, `filterOrder`, `filterLimit`
v `FieldRenderer::attributes()`, čtou je `FieldModuleRenderer::settings()`
i `blocks/fields/editor.js` (panel *Které kurzy*, v Divi vlastní content
skupina `contentCourses`). Filtruje se v paměti v `KindDetail::filtered()`
a `sorted()` — nad klíči vykreslené řádky, ne nad sloupci databáze. Testy
`KindFilterTest`. Živě: Gymnastika 22 řádků, dívky 18, kluci 2.

### Stránka druhu kurzu
Typ příspěvku `cscs_kind_page` (**ne** `cscs_kind` — to je taxonomie, kolidovalo
by to o query var), párovaný `Normalise::match_key_loose()` jako u trenérů.
`KindRepository::ensure()` volá `derive_kind()`, takže stránka vznikne, jakmile
pod druh spadne první kurz; `wp cscs sync kinds` je založí všechny. Adresa
`/druh/…` — po změně slugu je nutné zvednout `Plugin::REWRITE_VERSION` (teď 5).

`KindDetail::course_listing()` staví tabulku přes stejný `Listing` jako všechno
ostatní. Pole `kind-courses` = blok i Divi modul *Kurzy tohoto druhu*;
`templates/single-kind.php` ji přidá pod text stránky.

### Odrážky
`Markup::flatten_lists()` (DOM) srovná seznamy na jednu úroveň — iSport posílá
`<ul><li><ul><li>` u 24 kurzů. Pole s `'bullets' => true` v katalogu dostane
v bloku nastavení odrážek a v Divi skupiny *Bullet item* / *Bullet mark*
(`::marker`). Panely bloku se přestěhovaly: **Nastavení** = co ukazuje,
**Styly** = jak vypadá.

### Popis z iSportu
`course-text` (*Popis kurzu*) ukazuje **text z editoru** a ten je na webu
prázdný u všech 113 kurzů; `_cscs_api_description` má naopak 111 kurzů a nikde
se nezobrazoval. Přibyl proto samostatný blok i modul **Popis z iSportu**
(`course-api`) a výchozí stránka kurzu vypíše popis z iSportu, když v editoru
nic není. Text z iSportu je HTML a začíná zbytečným `<br>` — ten se ořezává.

### Druh kurzu
`CSCS\Data\CourseKind` čte z názvu **druh kurzu** a synchronizace pod něj kurz
zařadí do nové taxonomie `cscs_kind` (*Druhy kurzů*). Karta se pak postaví
zaškrtnutím druhu v sadě — ruční seznamy zůstávají jen jako záchranná brzda.

Proč ne `cscs_activity`: iSport posílá jako `activity_name` **celý název kurzu**,
takže by to byl jeden term na kurz. (V téhle instalaci ji stejně nikdo neplní.)

Kurz má **dvě jména** a rozhoduje `activity_name`, když se liší od názvu:
kurz 25 se jmenuje *Gymnastika 4-6 let dívky pokročilé*, ale aktivitu má
*Jojo přípravka…* a na starém webu je pod Jojo přípravkou. Takových je 14, ale
13 z nich se liší jen mezerou. Bez toho pravidla měla karta Gymnastika dívky 19
řádků místo 18 — a ten devatenáctý tam nepatřil.

Druh končí tam, kde začíná věk, pohlaví, úroveň nebo pololetí — tedy **ne u
první mezery**; to byla chyba prvního pokusu, kvůli které se „Gymnastika pro
radost“ dostala do „Gymnastiky“. Slovníky jsou `Audience`ovy, filtr
`cscs_course_kind_patterns`. Na živých datech: **113 kurzů → 25 druhů**,
Gymnastika 23, Jojo přípravka 15, Lezení 12, Parkour 12, Gymnastika pro radost 7.

`cscs_kind` je v zamykatelných polích, takže ručně opravený druh synchronizace
nepřepíše. Backfill: `wp cscs sync kinds [--dry-run]`.

### Skupiny kurzů pod jedním jménem a věk
Dosavadní web publikuje kartu *Gymnastika dívky*: jeden nadpis, jedna tabulka,
osmnáct kurzů, které v datech nespojuje nic než rozhodnutí člověka. Zobrazovací
sada uměla jen filtry, takže takovou kartu postavit nešlo. Teď nese navíc
`courses` (kurzy zaškrtnuté ručně, zobrazí se bez ohledu na filtry), `exclude`
(nikdy se nezobrazí), `genders`, `levels`, `age_min` a `age_max`.

`Query::courses()` pustí filtry zvlášť (`fields => ids`), přidá ručně vybrané,
odečte vyloučené a výsledek předá skutečnému dotazu jako `post__in` — řazení
i stránkování tak zůstávají v databázi. **Sada, která má zaškrtnuté kurzy a
žádný filtr, znamená přesně ty kurzy**; bez toho pravidla by znamenala ty kurzy
plus celý katalog. Sada bez ručního výběru se dotazuje přesně jako dřív.

Věk je třetí údaj, který iSport nemá, a čte se z názvu ve třech tvarech:
`9-11 let` rozsah, `od 10 let` spodní hranice, `4 roky` jeden věk. Jednotka
odlišuje věk od čísla kurzu. Půlroky zůstávají (`2,5-3 roky` → `2.5`–`3`),
ukládají se s tečkou, vypisují s čárkou. Na živém webu má věk 106 ze 113 kurzů,
zbytek jsou kurzy pro dospělé. Filtr věku je **překryv**, ne obsažení: sada 7–9
vezme i kurz 6–8.

Ukázková sada `gymnastika-divky` je na webu založená (dívky, 7–9 let, sloupce
Den / Čas od–do / Věk / Pohlaví / Úroveň / Volná místa / tlačítko) — vypadá jako
přiložený obrázek z dosavadního webu.

### Pohlaví a úroveň
Kurz nese, **pro koho je** a jakou má **úroveň**. V iSportu ani jedno pole není
— ověřeno na obou endpointech — a dosavadní web to čte z názvu kurzu, takže to
tak dělá i plugin: `CSCS\Data\Audience` porovnává pevný slovník proti názvu
zbavenému diakritiky a velkých písmen, na celá slova (`mix` se tedy nenajde
v `mixáž`) a v pořadí, ve kterém `mírně pokročilí` předchází `pokročilí`.

Živý běh `wp cscs sync audience`: **113 kurzů, 104 přečteno z názvu, 122 polí
zůstalo prázdných, 0 přepsáno.** Řádky byly projité ručně.

Ruční volba v editaci kurzu zapíše meta a přidá klíč do `_cscs_locked_fields`,
takže ji synchronizace už nikdy nepřepíše; *— podle názvu —* meta smaže.

Úroveň se skloňuje podle pohlaví (*začátečnice* × *začátečníci*). Anglicky je to
jedno slovo, takže se obě čtení rozlišují **gettext kontextem** — kvůli tomu se
musel naučit kontexty i `tools/extract-strings.php` a `tools/i18n/build.py`.
Builder navíc přeskočí ručně vypsaný řetězec, který extraktor našel i v PHP;
katalog se stejným řetězcem dvakrát gettext nepřečte.

### Den a Čas
Detail kurzu psal „Kdy: Po 16:00, St 17:00“ — jedno pole se dvěma fakty
v jediné podobě, ve které se rozvrh číst nedá. Teď jsou to dvě pole a jejich
řádky běží v jednom kroku, takže první den patří k prvnímu času a čtenář
nemusí hádat. Tabulka lekcí uměla nahlásit i konec termínu (`time_to`), jen se
jí nikdo neptal.

### Trenéři
Vlastní typ příspěvku `cscs_trainer_profile` — dvacet znaků, což je přesně
maximum, které WordPress dovolí, a záměrně **ne** `cscs_trainer`: to jméno patří
taxonomii a typ příspěvku by s ní kolidoval o query var. Taxonomie zůstává beze
změny, protože podle ní filtrují Zobrazovací sady.

Páruje se **normalizovaným jménem** (`Normalise::match_key`) — jiný společný
identifikátor obě strany nemají. Klíč se na kurz zapisuje **až po meta smyčce**
v `CourseRepository::save()`, aby web se zamčeným jménem trenéra měl klíč toho
jména, které opravdu zobrazuje.

Fotka se stahuje na serveru do knihovny médií, takže prohlížeč návštěvníka se
iSportu nikdy na nic neptá. Náhledový obrázek nastavený ručně má vždycky
přednost. `wp cscs trainers backfill [--dry-run] [--photographs]` postaví
stránky z už uložených kurzů.

### Bloky a moduly pro pole
Katalog `CSCS\Render\Fields` je jediný seznam toho, z čeho se kurz a trenér
skládají. Generují se z něj Gutenberg bloky, Divi moduly i testy, takže pole
přidané tam se objeví ve třech editorech naráz a nemůže v každém říkat něco
jiného.

Bloky se registrují programově, bez `block.json` a bez adresáře na blok —
neexistuje k nim kód, který by tam patřil.

Nadpis a hodnota mají **vlastní typografii**. To je celý důvod, proč to není
jeden blok s rozbalovacím seznamem: block supports stylují blok jako celek
a „Cena“ a „4 160 Kč“ jsou dvě věci. Každá hodnota se před vypuštěním do
prohlížeče ověří proti vzoru nebo seznamu — atribut přichází z uloženého
příspěvku a „napsal to náš vlastní editor“ není tvrzení o bezpečnosti.

Divi moduly generuje `php tools/build-divi-modules.php` do `divi/fields/`.
`FieldModulesTest` selže, když se katalog a vygenerované adresáře rozejdou.

---

## 3. Jak se s projektem pracuje

### Dvě kopie téhož
Repozitář žije **na Lukášově Macu**: `/Volumes/PRO/www/Plugins/Jojo Gym iSport
System Integration`, přes `device_bash` je pod `$HOME/mnt/Jojo Gym iSport System
Integration`. Adresář pluginu ve WordPressu je na něj symlink, takže zapsaný
soubor je okamžitě živý — nic se nikam „nenahrává“.

V cloudovém kontejneru (kde běží PHP a testy) je **pracovní kopie**. Na Macu
naopak PHP není. Postup je tedy: upravit a otestovat v kontejneru, pak přenést.

Přenos tam a zpět se dělá jedním archivem, ne po souborech:

```
# z Macu do kontejneru (nová session začíná tímhle)
cd "$HOME/mnt/Jojo Gym iSport System Integration"
tar czf _to_delete/repo-snapshot.tgz --exclude='.git' --exclude='_to_delete' .
# device_stage_files → rozbalit v kontejneru

# z kontejneru na Mac
tar czf /tmp/sync.tgz <soubory>          # v kontejneru
# SendUserFile → device_commit_files do _to_delete/sync.tgz
cd "$HOME/mnt/Jojo Gym iSport System Integration"
mkdir -p _to_delete/x && tar xzf _to_delete/sync.tgz -C _to_delete/x
(cd _to_delete/x && find . -type f -print0 | while IFS= read -r -d '' f; do mkdir -p "../../$(dirname "$f")"; cp "$f" "../../$f"; done)
```

Kopíruje se přes `cp`, ne rozbalením rovnou na místo: připojený svazek nedovolí
`unlink`, takže `tar` nad existujícím souborem selže. Ze stejného důvodu se na
Macu nedá mazat — soubory k odstranění se přesouvají do `_to_delete/`
(je v `.gitignore`).

### Commit
Ve VM není nastavená identita, takže:

```
git -c user.name="Lukas Pivonka" -c user.email="info@pivonka.co.uk" commit -F - <<'MSG'
…
MSG
```

Po **každém** git příkazu uklidit zámky, jinak zůstane `index.lock` a další
příkaz odmítne běžet:

```
find .git \( -name '*.lock' -o -name 'tmp_obj_*' \) -print0 | while IFS= read -r -d '' f; do
  mv "$f" "_to_delete/gitlocks/$(date +%s%N)-$(basename "$f")"
done
```

Varování `unable to unlink '.git/objects/…tmp_obj_…'` je průvodní jev
připojeného svazku, ne chyba — commit proběhne. Pozor: `git add -A ':!cesta'`
nefunguje, pathspec magic není v této verzi implementovaná.

Na `git push` z Macu je otevřený problém: `Failed to connect to github.com
port 443`. Ping projde, proxy ani záznam v `/etc/hosts` nejsou. Nesouvisí to
s pluginem. **Nežádat o personal access token** — cloudová git proxy ho ignoruje.

### Testy, překlady
```
php tools/phpunit-shim/run.php                                # testy
php tools/extract-strings.php && python3 tools/i18n/build.py  # .pot, .po, .mo, JED
php tools/build-divi-modules.php                              # Divi metadata
```
**PHP na Macu je přes WP-CLI.** Ve VM, ve které běží `device_bash`, žádné PHP
není, ale Studio ho má — a WP-CLI umí spustit skript i bez WordPressu, takže
nic z toho se nemusí kopírovat tam a zpět:

```
wp eval 'require ".../tools/build-divi-modules.php";' --skip-wordpress
```

Bez `--skip-wordpress` by se načetla česká lokalizace a do generovaných souborů
by se zapsaly české titulky místo anglických. `wp eval-file` nefunguje —
soubory začínají `declare( strict_types=1 )` a ten musí být prvním příkazem
skriptu.

Nové české překlady se dopisují do `tools/i18n/cs.py`, ne do `.po` — ta se
generuje. Slovníky: `CS` (PHP), `JS` (blok výpisu), `JS_DIVI` (modul výpisu),
`JS_FIELDS` (bloky polí), `JS_DIVI_FIELDS` (moduly polí). Každý JS soubor má
svůj JED soubor pojmenovaný `md5(<cesta k souboru relativně ke kořeni>)` —
při přidání nového skriptu je potřeba dopsat i jeho `write_jed(...)`.

### Prohlížeč
Web běží na `http://localhost:8888`, na Lukášově Macu je rozšíření Claude in
Chrome. Chování na frontendu se ověřuje přímo v něm — u Divi je to jediný
způsob, jak najít pasti popsané níže. `read_console_messages` začíná
zaznamenávat až od prvního zavolání, takže stránku je potřeba po zavolání
načíst znovu.

Vizuální builder Divi se otevírá jako `?et_fb=1&PageSpeed=off`. **Na typu
příspěvku `cscs_course` se builder neotevře** — Divi má seznam typů, pro které
je zapnutý, a kurzy v něm nejsou. Testovat se dá na stránce
*Isport system integration test*.

Modul se do stránky přidává přes vrstvy (druhá ikona vlevo) → ⋮ u existujícího
modulu → *Přidat Prvek* → *Modul*. Přes ⋮ u sloupce se otevře vkládání řádku,
ne modulu.

---

## 4. Divi 5 — pasti, které stály čas

1. **Atribut se nesmí jmenovat `set`.** Divi drží atributy v seamless-immutable,
   kde `set`/`setIn` jsou metody; atribut toho jména je přepíše a builder
   selže na `getIn(...).setIn is not a function` — tiše, výběr se prostě
   neuloží. Moduly proto používají `listing.advanced.id` a `field.advanced.*`.
2. **Modul musí mít `module-default-render-attributes.json`.** Bez něj neexistuje
   struktura, do které by se dalo zapsat, a platí totéž — výběr se neuloží.
3. **V builderu se nesmí sáhnout na `window.React`**; Divi vykresluje vlastní
   instancí v `window.vendor.React` a hooky z cizí kopie Reactu vyhodí výjimku.
4. **Skupiny typografie je nutné pojmenovat.** Dvě podřízené skupiny `font`
   ponechané na Divi se obě jmenují „Text Modulu“ a panel je nepoužitelný.
   Řešení je `settings.groups.designHeadingText` / `designValueText` a u položek
   `decoration.font` a `decoration.spacing` `groupSlug` na ně, s komponentou
   `{type:"group", name:"divi/font"|"divi/spacing", props:{attrName, grouped,
   fieldLabel}}`. Vzor je v `divi/blurb/module.json` v tématu Divi — ten soubor
   je čitelný přes prohlížeč na
   `/wp-content/themes/Divi/includes/builder-5/visual-builder/packages/module-library/src/components/<modul>/module.json`
   a je to nejrychlejší způsob, jak se dozvědět, co Divi opravdu čeká.
5. **V šabloně Theme Builderu je globální `$post` ta šablona**, ne příspěvek,
   který návštěvník otevřel. `get_post()` tam vrátí layout; ptát se je potřeba
   přes `get_queried_object()`. Projevilo se to tak, že každé pole na stránce
   trenéra hlásilo „tahle stránka není ani kurz, ani trenér“.
6. **Překlady skriptu ve Visual Builderu nefungují přes `wp_set_script_translations`** —
   Divi si balíček registruje vlastním správcem a WordPress ten handle nezná.
   Řetězce se předávají v `wp_localize_script` spolu s metadaty modulu, což
   prokazatelně funguje (tak chodí i názvy modulů).
7. **Deklarovat nastavení nestačí — bez `styleProps` Divi nemá kam zapsat
   jeho CSS.** Pole hodnotu přijme a nic se nestane. K tomu musí render
   callback modulu vypsat `$elements->style( array( 'attrName' => … ) )` pro
   každý podřízený prvek, jinak se styly nedostanou ani na frontend.
8. **Každý atribut, který nese styly, musí mít `elementType`.** Podle něj si
   Divi vybírá style komponenty; atribut bez něj nedostane žádné a
   `elements.style( { attrName } )` pro něj nevykreslí nic. PHP se na to
   neptá — proto byl frontend celou dobu správně a mýlil se jen builder.
   Používají se Diviho vlastní názvy: nadpis `heading`, text `content`,
   obrázek `image` (`imageLink`, když je obrázek zároveň odkaz), obal
   `wrapper`.
9. **Než se modul zeptá na styly, musí mít nastavenou svou order class.**
   Divi ji nastaví, když vykresluje styly modulu samostatně; uvnitř edit
   stromu ještě ne, a pravidla pak vyjdou jako ` .cscs-field__label` — začínají
   mezerou a nepatří ničemu. `visual-builder/cscs-divi-fields.js` proto volá
   `setBaseOrderClass`, `setOrderClass` a `setModuleNameClass` a teprve pak
   vykresluje `elements.style()` uvnitř `StyleContainer` jako potomka
   `ModuleContainer`. Tam přistane style tag i u Diviho vlastních modulů.
10. **`renderers.styles` Divi u modulu z pluginu nezavolá.** Obalí ho, ale
   nikdy se ho nezeptá — ověřeno sondou. Registruje se dál, protože je to
   správné místo, ale na plátno se dostane až volání v `edit`.
11. **`$elements->style()` v PHP nevrací řetězec.** Vrací to, co daný prvek
   potřebuje — někdy řetězec, jindy pole deklarací — a `Style::add()` vezme
   obojí. Slíbit v callbacku návratový typ `string` znamená fatální chybu na
   každé stránce, kde takový modul je.
12. **Vlastní sekce modulů se dělá dvěma věcmi**: klíčem `folder` v
   `module.json` a zavoláním `divi.moduleLibrary.registerFolder( {name, path,
   title, icon, category} )`. Přesně tak to má WooCommerce se svou sekcí
   *Woo Modules*.
13. **Vlastní ikonu modulu přidat nejde.** `divi.iconLibrary` žádnou registraci
   nevystavuje. Použitelné názvy (`divi/module-*`) se dají vypsat z builderu:
   `divi.data.select('divi/module-library').getModules()` a z každého modulu
   `moduleIcon`.
14. Odkazy s kotvou na téže stránce Divi polyká kvůli plynulému rolování;
   posluchače je třeba věšet v **capture** fázi.

---

## 5. Otevřené body

- **Testovací data na webu.** Na trenérovi *Pavlína Mládková* jsou vyplněné
  zkušební kvalifikace, zajímavost a motto; na stránce *Isport system
  integration test* je pod výpisem modul **Cena** namířený na kurz
  113-Deskové hry. Obojí je tam kvůli ověření a dá se smazat.
- **Přepínač týdnů se u sady `lekce` nezobrazuje**, protože má rozsah
  *pololetí*, ne *týden*. Je to záměr — přepínal by něco, co výpis nezohledňuje.
- **Push.** Lukáš pushnul alfu 2 včetně (`ce50461`). Push dělá vždycky ručně
  sám — commituje se lokálně, na push se čeká.
- **Přepisovací pravidla pro `/druh/…`.** `Plugin::REWRITE_VERSION` je 5
  a uložená hodnota byla vynulována, takže se pravidla přestaví při prvním
  načtení administrace. Kdyby přesto adresa druhu vrátila 404, stačí uložit
  *Nastavení → Trvalé odkazy*.
- **Ukázková sada `gymnastika-divky`** je na webu založená (druh *Gymnastika*
  + pohlaví *dívky*, 18 kurzů) a odpovídá tabulce ze starého webu řádek po
  řádku. Je to ukázka, ne produkční nastavení — klidně smazat nebo přenastavit.
- **Osm kurzů má lekce, ale ve výpisu kurzů iSportu nejsou** (03-Rodiče a děti,
  05-Rodiče a děti, 25/27-Gymnastika 4-6, 33-Gymnastika 7-9, 113-Deskové hry,
  722-Free running, 841-Bouldrování). Jejich lekce proto zůstávají nespárované
  (57 z ~2 000) a druh *Deskové hry* nemá z čeho vzít popis. Na naší straně
  není co opravit — jde o data v iSportu.
- **Dva kurzy mají popis prázdný přímo v iSportu** (`108-Gymnastika pro
  dospělé`, `83-Vzdušná akrobacie … s hlídáním dětí`), takže jejich druhy
  zůstanou bez popisu, dokud se nedoplní tam.
- **Taxonomie `cscs_activity` zůstává prázdná.** iSport posílá jako
  `activity_name` celý název kurzu, takže by to byl jeden term na kurz.
  Filtr *Aktivity* v zobrazovacích sadách proto zatím nemá co nabídnout;
  seskupuje se podle **Druhu kurzu**.
- **Kurz 25** se jmenuje *Gymnastika 4-6 let dívky pokročilé*, ale iSport u něj
  posílá aktivitu *Jojo přípravka…*. Druh se čte z aktivity, takže je pod Jojo
  přípravkou — stejně jako na starém webu. Kdyby to mělo být jinak, přepíše se
  druh u kurzu a zaškrtne zámek.
- **Taxonomie „Lektoři“ se v češtině přejmenovala na „Trenéři“**, aby
  v administraci nestála dvě jména pro tutéž věc. Kdyby to vadilo, mění se to
  v `tools/i18n/cs.py`.
- **Šablona stránky trenéra v Theme Builderu už existuje** („Všechny Trenéři“)
  a funguje. Šablona stránky kurzu zatím ne.
- **Adresy jsou česky:** kurz `/kurz/…`, trenér `/trener/…`. Mění se filtry
  `cscs_course_rewrite_slug` a `cscs_trainer_rewrite_slug`; po každé takové
  změně je nutné zvednout `Plugin::REWRITE_VERSION`, jinak se přepisovací
  pravidla nepřestaví a adresy vrátí 404.

## 6. Trvalá omezení, která platí bez ohledu na fázi

- Na server iSportu se **nikdy nezapisuje**. Jen dva dokumentované endpointy,
  jen GET, jen ze serveru webu.
- **Tagy kurzů se nemění** — pocházejí z API a zůstávají, jak jsou.
- Design zůstává výsadou administrátora, vynucenou na serveru.
- Synchronizace se dotýká jen toho, co vlastní API. Cokoli člověk napíše —
  u kurzu i u trenéra — zůstává nedotčené.
