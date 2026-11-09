# Předání práce — deník po dnech

> **Začínáte-li od nuly, čtěte `ALPHA.md`.** Ten shrnuje celý projekt na jednom
> místě. Tenhle soubor je to, co se dělo den po dni, a je užitečný, když je
> potřeba vědět *proč* něco vypadá, jak vypadá. Do vydaného pluginu se
> nedistribuuje.

## Stav k 9. 11. 2026

Tenhle soubor je most mezi pracovními dny. Je psaný tak, aby se do něj dalo
vstoupit bez znalosti předchozího rozhovoru: co je hotové, jak se to spouští,
co je otevřené. Až se projekt uzavře, zmizí — do vydaného pluginu se nedistribuuje.

---

## 1. Kde projekt stojí

Fáze **F0 – F11 jsou hotové**, F12 (akceptační brána) je odložená. Podrobné
zadání každé fáze je v `PLAN.md`, kapitola 9. Běží **testovací a vylepšovací
fáze**: verze `1.0.0-alpha.2` je její první kolo.

**Lukášova prohlídka pluginu běží dál a má přednost před F8.** Všechno, co z ní
zatím vzešlo, je hotové a ověřené proti živým datům. Poslední kola: karty
trenérů na stránce druhu kurzu jako nativní Diviho Loop místo vlastního
modulu (16.–19. 9.), v administraci kurzů kategorie (nová ruční taxonomie),
kombinovatelné filtry nad tabulkou, oprava skutečné chyby ve výpočtu stavu
kurzu s jednorázovým přepočtem dat, a filtr i sloupec pro pohlaví (22.–24. 9.),
a oprava prázdné smyčky trenérů mimo stránku druhu kurzu — karusel na úvodní
stránce i náhled ve Visual Builderu (28. 10.).

**Ani jedno z toho ještě není zacommitované** — ani 22.–24. 9., ani 28. 10. —
viz konec příslušných podsekcí v části 2 a `Otevřené body`.
příslušné podsekce v části 2 a `Otevřené body`.

Poslední commity:

| commit | co přinesl |
|---|---|
| `0bb2008` | Karty trenérů: vzorová data ve Visual Builderu i pro sdílenou šablonu |
| `8b014e2` | Karty trenérů: nativní Diviho Loop místo vlastního modulu, s vazbou na druh kurzu |
| `8cca2ae` | Synchronizovaná fotka trenéra se zrcadlí i do skutečného featured image |
| `e328cb3` | Karty trenérů: zastaralé komentáře ještě popisovaly starý mechanismus |
| `4583a57` | Karty trenérů: Diviho Rozvržení bylo nespolehlivé, nahrazeno osmi poli |
| `d356bce` | Karty trenérů: Rozvržení místo Řádek/Sloupec, a Jméno trenéra místo Název |
| `614708f` | Náhled v builderu: `cardsLayout`/`cardLayout` se tam vůbec neposílaly |
| `d14fcab` | Řádek/sloupec u karet a u fotky se jménem: teď to v Divi funguje doopravdy |
| `8ee9043` | Karta trenéra: vlastní rozložení fotky a jména, barva jména jde nastavit |
| `ded0091` | Karty trenérů: fotografie a jméno dostaly Diviho vlastní Návrh a Pokročilé |
| `61d2575` | Trenéři na stránce druhu kurzu: fotografie, jméno a odkaz na jejich stránku |
| `07969bb` | Trenéři nesou vztah ke stránkám druhu kurzu |
| `fe392b7` | Sloupce tabulky jako child moduly — pilot na `course-schedule` |
| `d366100` | Deník předání dopsaný o 10. a 11. září |
| `28e0e69` | barva pozadí tlačítka se propíše i v plátně builderu (`important`) |
| `9dd4aa3` | tlačítko v plátně: chybějící třída v náhledu + selektor bez předpony |
| `ff590f5` | náhled `/cscs/v1/field` dostává tytéž třídy jako frontend |
| `6b7a51c` | pořadí sloupců v tabulce, nula sloupec vynechá |
| `ec43921` | sloupec **Detaily** — odkaz z tabulky na stránku kurzu |
| `2912588` | název kurzu je nadpis (H1, Text nadpisu); tlačítko bez Textu hodnoty |
| `ced62c1` | šest polí bez nadpisu; Button panel má kam zapisovat |
| `d6c5910` | Rozvržení pole je Divi skupina **Rozvržení** v záložce Návrh |
| `2a8b0dd` | pole si na šabloně kurzu dojdou pro trenéra a pro druh kurzu |
| `86d5b09` | složky modulů vedle sebe — vnořená se z Diviho seznamu ztratí |
| `37fadfb` | bloky a moduly rozdělené na Kurzy / Druhy kurzů / Trenéři |
| `1cf5bad` | tlačítko a odkaz jsou dva moduly, tlačítko je skutečné Divi tlačítko |
| `8cef1c2` | editovatelný text odkazu; `isport_button_text` v nastavení |
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

Testy: **260 prochází** (stav k 11. 9., tahle session je neopakovala). Spouští
se `php tools/phpunit-shim/run.php` z kořene repozitáře. **Nové kolo
(kategorie, filtry, oprava stavu, pohlaví) zatím nemá vlastní PHPUnit
pokrytí** — ověřeno jen ručně přes `wp_cli eval-file` proti přímému SQL (viz
sekce 2); stálo by za to tam dopsat testy, hlavně na
`CourseRepository::is_semester_over()` a na kombinaci víc `meta_filters()`
najednou.

**`origin/main` je aktuální po `0bb2008`, ale pracovní strom teď čistý
NENÍ.** Poslední kolo (kategorie, filtry, oprava stavu, pohlaví — sekce 2) je
hotové a ověřené, ale ještě nezacommitované. Přesný seznam souborů na konci
příslušné podsekce v sekci 2.

---

## 2. Co přibylo naposledy

### Fotka trenéra se na stránce kurzu roztahovala do obdélníku — `height: auto` bilo se s Diviho vlastním poměrem stran (9. 11.)

Lukáš nahlásil v jiné relaci šišatou fotku trenéra na stránce kurzu a dal
k tomu diagnózu, kterou si tam nechal ověřit přímo v prohlížeči: Divi
poměr stran 1:1 se korektně nastaví na box, který modul fotce dává, ale
CSS pluginu má na obrázku `height: auto` — takže si prohlížeč výšku
dopočítává ze zdrojové fotky (portrét, např. 56 × 75 px), ne z boxu, a box
se nakonec roztáhne na obrázek, místo aby to bylo naopak. Mechanismus sedí
s tím, co dělá `Fields::style_elements()` — `elementType: image` (sekce
4, bod 8) dává Divimu vlastní skupinu Sizing přímo na selektor obrázku
(`.cscs-field__image` / `.cscs-card__image`), ne na nic kolem něj, takže
`height: auto` na témže selektoru soupeří přímo s tím, co se Divi snaží
nastavit.

Oprava v `assets/css/cscs.css`: `.cscs-field__image` a `.cscs-card__image`
mají místo `max-width: 100%; height: auto;` teď `display: block; width:
100%; height: 100%; object-fit: cover;` — obrázek vyplní box, který
dostane, ořízne se, ale nerozteče. Při čtení `templates/partials/cards.php`
navíc vyšlo najevo, že karta trenéra s vyplněným permalinkem (běžný
případ) obaluje fotku do `<a class="cscs-card__image-link">` — a tenhle
odkaz (stejně jako `.cscs-field__image-link` u jednotlivého pole) měl
`display: inline-block;` bez jakékoli výšky, což by novému `height: 100%`
na obrázku nedalo od čeho se odvíjet, kdyby box dával Divi právě odkazu,
ne obrázku samotnému. Obě třídy mají teď `display: block; height: 100%;`
vedle svého `max-width: 100%;` — beze změny chování tam, kde žádný box
nikdo nenastavuje (procentuální výška proti předkovi s `auto` výškou se
spočítá jako `auto`, stejně jako dřív).

Neprovedeno v téhle relaci: živé ověření v builderu. Tu práci už udělala
ta druhá relace (potvrzeno jako funkční — holý preset s poměrem 1:1 dá
kulatou fotku 56 × 56 px na desktopu i telefonu) a Lukáš podle svých slov
běží svou vlastní smyčku na klonu/Preview s dočasným CSS v presetu, které
odstraní, až se tahle oprava pluginu potvrdí tam. Všimnuto, ale
neopraveno: `tools/build-divi-modules.php` nastavuje pro pole s obrázkem
vždy `elementType: 'image'`, i pro karty, kde markup podmínečně přidává
právě ten odkazový obal — Divi pro přesně tenhle případ nabízí vlastní
`elementType: 'imageLink'` (sekce 4, bod 8), ale nikde v kódu se
nepoužívá; viz otevřené body. `.cscs-course__image img` (vlastní stránka
kurzu) a `.cscs-trainer__photo-image` (vlastní stránka trenéra, omezená
`.cscs-trainer__photo`) zůstaly beze změny záměrně — ani jedno nesedí v
Diviho boxu, `height: auto` je tam správně.

### Nastavitelné role pro správu obsahu — administrátorské stránky i pro jiné role než iSport manager (5. 11.)

Lukáš zjistil, že administrátorské stránky pluginu nevidí nikdo kromě
administrátora a role *iSport manager* — potřeboval by k nim pustit i další
role, například *Správce obchodu* (`shop_manager`). Namísto napevno
zadrátovaného `shop_manager` do `Capabilities.php` (rychlá oprava, nabízená
jako první varianta) si Lukáš vybral nastavitelné řešení v administraci,
aby příští podobná role nepotřebovala zásah do kódu.

Nový mechanismus: Nastavení → *Kdo může spravovat obsah* — zaškrtávací
seznam všech rolí na webu kromě pevné trojice Administrator/Editor/iSport
manager (ty `cscs_manage_content` mají vždycky a tady se ani nenabízejí).
Výběr se ukládá jako `extra_content_roles` (nový klíč v
`Settings::defaults()`), validovaný proti skutečnému seznamu rolí na webu —
neplatná nebo duplicitní hodnota se tiše zahodí. Uložení nastavení spustí
akci `cscs_setting_changed`, kterou `Plugin::on_setting_changed()`
přesměruje na novou `Capabilities::sync_extra_roles( $role_slugs )` — ta
projde všechny role na webu mimo pevnou trojici a `cscs_manage_content`
přesně podle seznamu buď přidá, nebo odebere (prázdný seznam = odebere
úplně všem). Platí tedy hned při uložení, ne až při příští aktivaci nebo
zvýšení `Capabilities::VERSION` — to číslo dál hlídá jen tu pevnou trojici
v `Capabilities::ensure()`, ze stejného důvodu, jaký popisují komentáře
přímo v `Capabilities.php` a `CapsCommand.php`: neviditelné menu na webu,
kde se plugin aktivoval dřív, než capability vznikla. `wp cscs caps install`
nově čte
`extra_content_roles` z nastavení a sesynchronizuje stejně.

Ověřeno přes `wp_cli eval` i `wp cscs caps list` proti Studiu (`jojogym 2`):
uložení `["shop_manager"]` přes skutečnou `Settings::set()` přidá
`cscs_manage_content` roli *Shop manager* (a nikomu jinému), prázdný
seznam ho zase odebere; administrátor, editor a iSport manager se při tom
ani jednou nezměnili. Sanitizér v `Settings.php` správně zahodil
`administrator`, `editor`, `cscs_manager` a neexistující roli, když byly
poslané ve stejném požadavku. Vykreslení stránky Nastavení přes
`SettingsPage::render()` (jako administrátor, lokalizace `cs_CZ`) proběhlo
beze chyby, obsahuje nový oddíl i zaškrtávátko pro `shop_manager`, a
správně žádné zaškrtávátko pro pevnou trojici.

Vedlejší nález: `tools/i18n/strings.json` byl dlouho zastaralý vůči
skutečnému zdroji — chybělo v něm 63 řetězců, které
`course-schedule-connector.pot` už obsahuje (pole Divi modulů, popis
pluginu, `Photo & name arrangement` a další), protože `php` v tomhle
prostředí není k dispozici a `tools/extract-strings.php` se tak nedalo
spustit. Oba nové řetězce z téhle práce byly proto doplněné ručně přímo do
`strings.json`, `cs.py` a do `.pot`/`.po`/`.mo` — bez spuštění celého
`build.py`, aby se těch 63 chybějících řetězců nesmazalo přepsáním. Viz
`Otevřené body`.

Nezacommitováno — viz `Otevřené body`.

### Prázdná smyčka trenérů mimo stránku druhu kurzu — karusel na úvodní stránce (28. 10.)

`LoopKindPageBinding` (16.–19. 9., podsekce níže) uměl sentinel
`%current_kind_page%` dosadit jen na stránce druhu kurzu — `resolve()` se
mimo ni rovnou vracel beze změny, takže podmínka v meta dotazu zůstala
doslova `_cscs_trainer_kind_page = %current_kind_page%`. Kdekoli jinde ta
samá sdílená smyčka trenérů běžela — karusel na úvodní stránce byl zadaný
případ — to neodpovídalo ničemu a smyčka ukázala „Nebyly nalezeny žádné
výsledky“. Náhled ve Visual Builderu se přitom nikdy nechoval stejně:
`resolve_preview_query_args()` mimo stránku druhu vždycky dosadil nějakou
skutečnou, publikovanou ukázkovou stránku
(`KindRepository::example_page_with_trainers()`), takže ten, kdo šablonu
navrhuje, viděl trenéry, ale web ne — přesně ten rozjezd, který předchozí
zápis sám předjímal jako riziko k prověření.

Oprava: mimo stránku druhu se podmínka se sentinelem teď nepřeskakuje ani
nedostává cizí stránku, ale rozšiřuje na „trenér má jakoukoli stránku
druhu“ — `compare => 'EXISTS'`, bez `value`. Past: samotné přepsání
`compare` na `EXISTS` nestačí, pokud `value` (nebo `metaValue` u
nepřevedeného tvaru podmínky) zůstane na podmínce ležet — WordPress ji
přečte i tak a tiše se zachová, jako by `compare` bylo `=`. Ověřeno přímo
na stejných datech: s `EXISTS` a ponechanou sentinelovou hodnotou vrátil
dotaz 0 trenérů, s `EXISTS` a hodnotou odstraněnou 21 — přesně počet
trenérů, kteří mají aspoň jednu stránku druhu. Na stránce druhu kurzu
(živé i v náhledu Builderu) se nic neměnilo — tam se sentinel dál dosazuje
na skutečné id té stránky. Oprava je v
`includes/Divi/LoopKindPageBinding.php`, podrobně v `DEVELOPER.md`
(„Trainers on a kind's page“) a jako nová položka 15 v sekci 4 níže.

Ověřeno přes `wp_cli eval` proti Studiu (`jojogym 2`): přímým zavoláním
`resolve()` a `resolve_preview_query_args()` se skutečným tvarem dat, jaký
Divi doopravdy posílá (zachyceno na živé stránce druhu přes dočasný
mu-plugin sondu, pak smazaný), a skutečným `WP_Query` nad výsledkem — 21
trenérů mimo stránku druhu, a beze změny 1 trenér (Lukáš Moutelík) na
stránce druhu *Ninja Movement*, shodně s `KindRepository::trainers()` i se
skutečným vykreslením té stránky. Karusel na úvodní stránce samotný se ale
v obsahu webu `jojogym 2` nenašel — žádná stránka ani šablona kromě stránky
druhu v tuhle chvíli neobsahuje podmínku na `_cscs_trainer_kind_page` —
takže ověřené je mechanismus, ne kliknutí skutečným karuselem. Až bude na
webu existovat, stál by za to jeden skutečný průchod.

Vedlejší dopad: `resolve_preview_query_args()` už nikdy nevolá
`KindRepository::example_page_with_trainers()`, takže import
`KindRepository` z `LoopKindPageBinding.php` zmizel a ta metoda repository
nemá volajícího — viz `Otevřené body`.

Nezacommitováno — viz `Otevřené body`.

### Administrace: kategorie, kombinovatelné filtry a oprava stavu kurzu (22.–24. 9.)

Celé tohle kolo vzešlo z toho, že Lukáš procházel administraci kurzů a chtěl
se v ní líp orientovat. **Zatím nezacommitované** — viz konec téhle podsekce.

**Kategorie kurzů, `cscs_category` — čistě ruční, žádné odvozování z názvu.**
Nová hierarchická taxonomie (na rozdíl od ostatních taxonomií pluginu, které
jsou ploché), připojená k `cscs_course` i `cscs_kind_page` přes
`register_taxonomy_for_object_type()`. Hierarchická záměrně: UI má nabízet
krátký pevný seznam k zaškrtnutí jako u příspěvkových rubrik, ne pole na psaní
vlastního štítku. První pokus kategorie odvozoval z názvu kurzu regexem —
stejný vzor, jaký `Audience` dělá s pohlavím/úrovní/věkem. Lukáš to vzápětí
opravil, jeho slova: „Chci, abys mi prostě vytvořil taxonomii pro kurzy a
druhy kurzů... Pokud se kategorie nedají načíst pomocí API, chci je všechny
zadávat ručně.“ Protože `Course` DTO žádné pole pro kategorii nemá (ověřeno),
odhad z názvu se pro tohle konkrétní pole nepočítá jako „z dat“. **Rozdíl od
`Audience`/`CourseKind` je záměrný a specifický pro tuhle taxonomii — nebrat
jako precedens pro škrtání odvozování jinde v pluginu.** Prvních 111 ze 115
kurzů má kategorie už přiřazené z toho prvního (staženého) pokusu — zůstaly
jako výchozí obsah k ručním úpravám, smazané je jen to, co je dřív dopočítávalo
(`wp cscs sync categories` je pryč).

Stejná past jako u Trenérů/Druhů kurzů: taxonomie sama nezaloží položku v levém
menu, protože `cscs_course` má `show_in_menu => false` (vlastní menu pluginu).
Přidána položka „Kategorie“ do `Menu.php` stejným vzorem —
`edit-tags.php?taxonomy=cscs_category&post_type=cscs_course`, žádná nová
třída. Ověřeno přes `wp_cli eval-file`, který zavolá `Menu::add_pages()` jako
administrátor a vypíše `$submenu['cscs']`. Cestou doplněn i chybějící český
překlad „Categories“/„Category“ → „Kategorie“ (chybělo od zavedení taxonomie).

**Sedm rozbalovacích filtrů nad tabulkou kurzů** (`edit.php?post_type=cscs_course`):
sál, trenér, druh kurzu, štítek, kategorie (všech pět = taxonomie), stav a
pohlaví (obojí meta). Taxonomie se filtrují úplně samy — `register_taxonomy()`
bez vlastního `query_var` dá taxonomii veřejnou proměnnou pojmenovanou stejně
jako ona, a `WP_Query::parse_tax_query()` ji čte automaticky i na `edit.php`
(jádro tam volá `wp_edit_posts_query()` → `wp()` → hlavní dotaz) — stačí
`<select name="cscs_room">`, přesně jako u kategorií/štítků příspěvků. Stav je
jediná výjimka, protože `_cscs_status` je meta, ne term: vlastní
`pre_get_posts`, podmíněné na `is_main_query()`.

Past při testování přes `wp_cli eval-file`: ručně vytvořený `new WP_Query()`
nikdy nesplní `is_main_query()` (porovnává se identita s `$wp_the_query`), takže
filtr v takovém testu tiše nedělá nic — vypadá to jako chyba v kódu, není.
Věrná simulace nastaví `$GLOBALS['wp_the_query']` před `->query()`.

**Lukáš nahlásil skutečnou chybu**: „Druh kurzu = Gymnastika“ + „Stav =
Probíhá“ nenajde nic. Měl pravdu, jen vinu neměl filtr — ten je ověřený nezávisle
dvěma metodami (přímý `tax_query`+`meta_query` dotaz a plná simulace
`wp_edit_posts_query()`), obě se vždy shodují. Skutečná chyba:
`CourseRepository::status_for()` a `Retention::close_finished_courses()`
počítaly „Ukončený“ z `stamp_to` — což je konec ČASU JEDNÉ lekce, ne konec
pololetí — takže kurz spadl na finished, jakmile skončila jeho úplně první
hodina, i kdyby podle `date_to` běžel ještě měsíce. Lukášovo zadání: nezajímá
ho jednotlivá hodina, jen jestli kurz v pololetí ještě běží — na
Ukončený/Archivováno má přejít, teprve až proběhne poslední hodina pololetí
(`date_to`), nebo když ho iSport přestane nabízet úplně (nezávislá, už správná
cesta — `archive_missing()`, beze změny).

**Oprava:** nová sdílená `CourseRepository::is_semester_over( ?string $date_to
): bool` — jen `date_to` (+23:59:59); `stamp_to`/`stamp_from` se pro tohle už
vůbec nečtou (dál je legitimně používá `Matcher.php` k párování lekcí, beze
změny). `status_for()` i `Retention::close_finished_courses()` teď volají
tutéž metodu místo dvou nezávislých kopií téže (chybné) logiky.

**Jednorázová oprava dat:** přepočítáno všech 105 nearchivovaných kurzů (10
archivovaných záměrně vynecháno). **102 kurzů se přehodilo z `finished` na
`running`**, 2 zůstaly running (obě „Ninja Movement“), 1 opravdu finished. Po
opravě: Gymnastika + Probíhá = 17 (dřív 0), + Ukončený = 1, + Archivováno = 3
(17+1+3 = 21 = celý druh, sedí).

**Filtr pro pohlaví**, hned po opravě stavu, na Lukášovo přání. Zdroj možností
`Audience::genders()` — stejný zdroj, co už dřív používal ruční výběr pohlaví
v `CourseEditor.php`, takže popisky (Dívky/Kluci/Mix/Ženy/Muži) byly v `.po` už
přeložené, doplnilo se jen nové „All genders“ → „Všechna pohlaví“. Meta klíč
`CourseRepository::META_GENDER` (`_cscs_gender`), stejná mechanika jako stav.

**Refaktor, protože dva meta filtry vedle sebe by se navzájem přepsaly:** dva
nezávislé `pre_get_posts` callbacky, každý volající `$query->set('meta_query',
...)`, by druhým voláním smazaly výsledek prvního — všimnuto při návrhu, dřív
než se to stihlo stát chybou v provozu. Bývalé `apply_status_filter()` a
hardcoded blok stavu ve `filters()` nahrazeny obecnými `meta_filters()` (mapa
filtr → `meta_key`/`options`/popisek „vše“) a `apply_meta_filters()`, který
posbírá všechny aktivní meta filtry do jednoho pole a `meta_query` nastaví jen
jednou, s `relation => AND` při dvou a víc aktivních. Taxonomie beze změny.
Guard navíc: neplatná/cizí hodnota v URL (např. `cscs_gender=neco`) se
ignoruje.

Ověřeno `wp_cli eval-file` proti přímému SQL — pohlaví samotné, stav samotné,
oboje dohromady i trojkombinace stav+pohlaví+druh, přesně: dívky 26, kluci 2,
mix 41, ženy 0, muži 0; probíhá 104/ukončený 1/archivováno 10 (shoda s
přepočtem dat výše).

**Sloupec Pohlaví**, hned po filtru, stejnou logikou jako u Stavu: vedle
filtru k dohledání se hodí i sloupec k rychlému přehlédnutí bez klikání do
detailu. `columns()`/`column()` v `CourseList.php`, vedle sloupce Stav. Čte
přímo `Audience::genders()[$gender]` — žádná druhá kopie popisků vedle té, co
používá filtr. Nový překlad nebyl potřeba: „Gender“ byl jako msgid už dřív
přeložený na „Pohlaví“ (sdílené s popiskem pole v `Render/Fields.php`).

**Stav k 25. 9.: hotovo a důkladně ověřené, ale nezacommitované.** V pracovním
stromu: `includes/Admin/CourseList.php`, `includes/Admin/Menu.php`,
`includes/Data/CourseRepository.php`, `includes/Data/KindType.php`,
`includes/Data/PostType.php`, `includes/Sync/Retention.php`, plus tři
přegenerované jazykové soubory (`.po`/`.mo`/`.pot`). Než se s tím dál pracuje:
projít diff a rozhodnout, jestli je to jeden commit nebo dva (kategorie+menu
je věcně jiná změna než oprava stavu+pohlaví) — commituje se s Lukášovou
identitou (sekce 3), push dělá jako vždy on sám. Navíc dva neverzované
soubory, `languages/course-schedule-connector-cs_CZ-ab413d42…json` a
`…-f379702637…json`: vypadají jako zbytky sestavení JED katalogu spuštěného
uvnitř `_to_delete/x/...` (cesty přenosu Mac↔kontejner) místo z kořene
repozitáře — mají v sobě špatnou cestu ke zdrojovému JS (`source`), takže i
jejich vlastní název souboru (hash té cesty) je špatně a WordPress by si je
stejně nenašel. Bezpečně smazat; kdyby ten JED katalog potřeboval přegenerovat,
udělat to ze skutečné cesty, ne z rozbalené kopie v `_to_delete/`.

### Karty trenérů jako nativní Diviho Loop, ne vlastní modul (16.–19. 9.)

Shrnutí z commit logu a čtení `includes/Divi/LoopKindPageBinding.php` — na
rozdíl od ostatních zápisů v tomhle deníku nebylo z první ruky v době psaní.
Riziko, které si to psaní tehdy přiznalo, se potvrdilo: karusel na úvodní
stránce kvůli tomu doopravdy ukazoval nulu trenérů. Oprava je v nové
podsekci na začátku této sekce (28. 10.).

Sdílená šablona stránky druhu kurzu teď kreslí trenéry přes Diviho **vlastní**
Group/Loop modul (smyčka přes `cscs_trainer_profile`, filtrovaná meta dotazem
na vztah trenéra ke stránce druhu) místo dřívějšího vlastního modulu — poslední
krok série, která před tím narovnala Řádek/Sloupec u karet, fotku se jménem,
napojila Diviho vlastní panely Návrh/Pokročilé na fotku i jméno, a přidala
trenérovi skutečný vztah ke stránkám druhu kurzu.

Jedna věc, kterou nativní Diviho Loop sám neumí: „filtruj na stránku druhu,
na které se tahle smyčka zrovna zobrazuje“ — Diviho editor meta dotazu nemá
u hodnoty podmínky žádnou možnost dynamického obsahu (ověřeno čtením
zdrojového kódu Visual Builderu, ne odhadem). Řešení: uložená podmínka ve
sdílené šabloně nikdy nenese skutečné id stránky, jen pevný sentinel
(`%current_kind_page%`, nemůže se srazit se skutečným id příspěvku) a
`LoopKindPageBinding` je jediné místo, které ho dosazuje — a musí to dělat
dvakrát, protože Divi staví tentýž dotaz dvěma různými cestami. Skutečná
návštěva stránky druhu: hook `divi_loop_data_before_execution`, sentinel se
nahradí přes `get_queried_object_id()`. Úprava sdílené šablony ve Visual
Builderu vůbec touhle cestou neprojde (ověřeno trasováním živé VB session) —
plátno se místo toho ptá REST endpointu `/divi/v1/loop/query-results`, který
střílí jiný filtr (`divi_module_options_loop_post_type_results_query_args`);
protože editace šablony není žádná konkrétní stránka, sentinel se tam dosadí
na skutečnou, publikovanou ukázkovou stránku
(`KindRepository::example_page_with_trainers()`), jen aby ten, kdo šablonu
navrhuje, viděl opravdovou kartu trenéra, ne prázdnou smyčku.

V tomhle kole navíc: synchronizovaná fotka trenéra se teď zrcadlí i do
skutečného featured image příspěvku (`8cca2ae` — anglický commit message,
stálo by za sjednocení příště), a kolo končí vzorovými daty ve Visual
Builderu i na sdílené šabloně, aby smyčka měla co ukázat při navrhování.

### Sloupce jako child moduly — pilot na `course-schedule` (13. 9.)

Otevřený bod z minula: sloupce tabulky jako child prvky místo číslování.
Hotovo jako pilot na jednom poli, `course-schedule` (vlastní rozvrh kurzu) —
zbylé čtyři tabulky (`course-makeup`, `trainer-courses`, `kind-courses`,
`kind-prices`) zůstávají zatím jen na číslech.

Mechanismus ověřený ve zdrojáku Divi (`elegantthemes/d5-example-core-modules`
— Accordion, Contact Form): `childrenName` na rodiči, `category:
"child-module"` na potomkovi, rodič si dítě čte přes `BlockParserStore::get()`
/ `$block->parsed_block['innerBlocks']`. Nový modul
`cscs/divi-course-schedule-column` (`Divi\TableColumnModule`) leží mimo
`divi/fields/`, stejně jako `divi/cscs-display` — nese jedno nastavení (který
sloupec) a nekreslí nic, čte ho rodič. Design sloupce nepotřeboval nic nového:
každý kandidát má už dnes svůj pevný selektor podle jména, bez ohledu na
pořadí či viditelnost.

Obě strany (Divi i Gutenberg) píšou do těch samých číslovaných nastavení,
která `Fields::ordered_columns()` už uměl číst — `Fields::columns_from_children()`
a `Fields::apply_children_columns()` jsou nové čisté funkce v `Fields.php`,
beze změny čehokoliv po proudu. Prázdný seznam dětí nastavení nezmění vůbec,
takže stránka bez potomků (úplně každá dnes) jede beze změny.

Gutenberg stranou vznikla i jedna vynucená architektonická odbočka:
`ServerSideRender`, kterým se dnes kreslí náhled všech 31 bloků, neumí
zobrazit živé potomky — `WP_REST_Block_Renderer_Controller::get_item()`
vždycky skládá blok s `innerBlocks => []`, bez výjimky, natvrdo v jádru
WordPressu. `course-schedule` proto v editoru místo toho volá `/cscs/v1/field`
(stejná cesta, kterou už používá Divi canvas), teď navíc s parametrem
`children`. Ostatních 31 bloků se to netýká, žádný z nich neztratil
`ServerSideRender`.

**Neověřeno naživo.** Studio (`jojogym 2`, port 8888) bylo celou session
nedostupné (`Connection refused`, `site_start` spadl na „Cannot read
properties of undefined"), takže nic z tohohle nikdo neviděl v opravdovém
Visual Builderu ani v Gutenbergu. Testy (267, samé PHP) pokrývají jen čistou
logiku — `FieldModuleRenderer`, `FieldBlocks` a REST vrstva jsou mimo dosah
shimu stejně jako všechno ostatní, co se dotýká živých WP/Divi tříd. Než se
tomu bude věřit, chce to ruční průchod: přidat/přetáhnout sloupec v obou
builderech, smazat ho, otevřít starou stránku bez potomků, a v Gutenbergu
zkontrolovat, že existující `course-schedule` blok nehlásí „neplatný obsah"
— `save()` mu teď místo `null` píše `InnerBlocks.Content`, což je jiná
serializace i pro blok bez jediného dítěte, a validátor na to může, ale
nemusí být citlivý.

Nové soubory: `includes/Divi/TableColumnModule.php`,
`includes/Divi/TableColumnModuleDependency.php`, `divi/course-schedule-column/`,
`visual-builder/cscs-divi-course-schedule-column.js`. Podrobný technický popis
je teď v `DEVELOPER.md` pod „Columns as children, piloted on one table"; tenhle
zápis je jen shrnutí pro deník.

### Prohlídka v builderu (10.–11. 9.) — moduly z pohledu člověka, který navrhuje

Celé tohle kolo vzešlo z toho, že si Lukáš sedl do Divi builderu a zkoušel
s moduly opravdu pracovat. Nic z toho nebyla chyba v datech; všechno to byla
chyba v tom, co panel nabízí a co z nabízeného skutečně funguje.

**Bloky a moduly jsou rozdělené podle toho, co se zrovna navrhuje** — Kurzy,
Druhy kurzů, Trenéři. Dvaatřicet položek pod jednou hlavičkou „iSport“ byl
kratší seznam, ale pořád špatná otázka: člověk v builderu dělá stránku trenéra,
nebo druhu, nebo kurzu. Past, která to stála jeden pokus: **Divi zahodí složku,
která přímo nedrží žádný modul** — `getFolders` nechá jen ty, pod kterými
`getChildModules` něco najde. Složka „iSport“ se třemi podsložkami tedy zmizela
i se vším pod sebou. Tři složky vedle sebe, ne vnořené. Test to hlídá.

**Přihlašovací odkaz jsou dva moduly, tlačítko a odkaz.** První pokus byl jeden
modul s přepínačem, který měnil název třídy a nic víc — panel Návrhu zůstal ten,
jaký dostane textové pole. Tlačítko teď deklaruje `elementType: button`
s Divi vlastním `decoration.button`, což přivolá celý Button panel; odkaz je
kotva v textu s vlastní skupinou písma. Text obojího se dá psát, a `Nastavení →
Co říká odkaz na přihlášení` je to, na co se všechno ostatní odvolává.

**Pole si na šabloně kurzu dojdou pro trenéra a pro druh.** Jedenáct z dvaatřiceti
modulů hlásilo na stránce kurzu „tahle stránka není ani jedno“. Pole teď dosáhne
o krok dál, jen z kurzu a jen tam, kde je odpověď jedna věc. Pravidlo, kterým se
zužují kandidátní stránky druhu, má jednu implementaci — `KindRepository::matches()`.

**Rozvržení pole je Divi vlastní skupina Rozvržení v záložce Návrh**, ne rozbalovák
pluginu v Obsahu. Deklarované tak, jak to má Divi Icon List. Tři věci se zjistily
vyrenderováním a přečtením stylopisu, ne úvahou: Divi **nikdy nevypisuje `display`**
(režim říká třídou `et_flex_module` / `et_grid_module` na modulu), mezeru píše jako
`--horizontal-gap` / `--vertical-gap`, a všechno to má váhu dvou tříd bez id.
Proto je náš stylopis v `:where()` a reset okrajů záměrně přesně na dvě třídy.

**Šest polí nemá nadpis**, protože nikdy nebyla věc, nad kterou nadpis sedí: název
kurzu, obrázek kurzu, tlačítko a odkaz přihlášení, jméno trenéra a fotografie.
Nese to jeden klíč — popisek `null` znamená „tenhle druh věci to není“ (prázdný
popisek pořád znamená „nemá vlastní jméno, ale přijme napsané“). A **pole, které
samo je nadpisem** (název kurzu, jméno trenéra), to říká: hodnota je deklarovaná
jako nadpis, panel jí říká *Text nadpisu*, prvek *Prvek nadpisu* a seznam začíná
na **H1**.

**Sloupec Detaily** je cesta z tabulky do kurzu. Tabulka kurzů byla tabulka údajů
*o* kurzech a nikdy cesta k jednomu z nich — na stránce druhu chybí i název kurzu.
Hodnota sloupce je v každém řádku stejná, protože je to cesta dovnitř, ne údaj, a
co říká, je to, jak se sloupec jmenuje.

**Pořadí sloupců** se nastavuje číslem u každého sloupce, nula ho vynechá.
`Fields::ordered_columns()` je jediná implementace a ptají se jí čtyři tabulky.
Je to **mezikrok**: cílový tvar jsou sloupce jako child prvky, tažené myší
v panelu vrstev — viz Otevřené body.

### Tlačítko ve visual builderu — čtyři kola a co je stálo

Stojí za zapsání celé, protože každé kolo vypadalo jako táž chyba a pokaždé to
byla jiná.

1. **Panel neukládal.** V `module-default-render-attributes.json` chyběla větev
   `button`, takže nebylo kam zapsat. Táž past jako u výběru zdroje. Každý modul
   v knihovně Divi s `elementType: button` ten výchozí atribut má.
2. **Ukládal, ale nezobrazoval se v Theme Builderu.** Uvnitř šablony Divi
   nepoužívá selektor atributu, ale `customPostTypeSelector`; kde ho modul
   nepojmenuje, vyrobí si ho vložením vlastních obalů do našeho selektoru —
   a vložilo `.et-db` dovnitř `#page-containeru`, kde `.et-db` není.
3. **V plátně builderu chyběla kotvě třída `et_pb_button`.** Plátno tyhle moduly
   nevykresluje přes `FieldModuleRenderer`, ale ptá se routy `/cscs/v1/field`,
   která renderovala pluginem, jenž o Divi neví nic.
4. **Barva pozadí se v plátně pořád neprojevila, přechod ano.** Ten rozdíl
   pojmenoval příčinu: o `background-image` se nikdo nepere, kdežto
   `background-color` přebíjelo `body.et-db #et-boc .et-l .et_pb_button` — jedno
   id, tři třídy a prvek. Na stránce Divi týž řetěz doplní i před náš selektor;
   v plátně nedoplní nic. Skupiny, se kterými se to pravidlo pere, jsou proto
   označené `important`, což je idiom, jaký Divi používá na vlastním tlačítku.

> **Varování, které stálo dvě kola: `getComputedStyle` se v plátně builderu
> nedá věřit.** Inline `background-color` s `!important`, zapsaný na živou kotvu,
> viditelný v jejím atributu `style`, na připojeném a vykresleném prvku, se
> přečetl jako stará hodnota. Spolehlivé jsou **shoda selektorů (`matches()`),
> pravidla ve `styleSheets` a screenshot**. Jednou kvůli tomu byla vrácena
> správná oprava.

Plátno builderu se otevírá na `?post_type=et_body_layout&p=<id>&et_fb=1` a jeho
obsah je v iframu `#et-vb-app-frame` — stejný původ, takže se do něj dá číst
i zapisovat.

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
15. **`compare => 'EXISTS'` v meta dotazu nestačí, zůstane-li u podmínky
   `value`.** WordPress ji přečte i tak a tiše se zachová, jako by `compare`
   bylo `=` — ověřeno přímo na stejné podmínce: s `EXISTS` a ponechanou
   hodnotou `%current_kind_page%` vrátil dotaz 0 trenérů, se stejným
   `EXISTS` a hodnotou odstraněnou 21. Klíč `value` (a `metaValue` u
   nepřevedeného tvaru podmínky) je při přechodu na `EXISTS` nutné odstranit
   úplně — `unset()`, nestačí přepsat jen `compare`. Viz
   `LoopKindPageBinding::replace_sentinel()`.
16. **Diviho vlastní skupina Sizing/poměr stran pro `elementType: image` (bod
   8) píše CSS přímo na `<img>`, ne na nic kolem něj — selektor je tu obrázek
   samotný (`image`/`cardImage` z `Fields::style_elements()`).** `height:
   auto` na témže selektoru ve vlastním CSS pluginu soupeří s tím, co se
   Divi snaží nastavit: prohlížeč si výšku dál počítá ze zdrojového poměru
   obrázku, což vyhraje nad jakýmkoli boxem, který Divi dává — portrétová
   fotka zůstane portrétem bez ohledu na vybraný poměr stran. Oprava:
   vlastní CSS má obrázek nechat vyplnit box, který dostane (`width: 100%;
   height: 100%; object-fit: cover;`), ne počítat si výšku samo. Zjištěno
   9. 11. na fotce trenéra, která na stránce kurzu vyšla šišatá místo
   kulaté; viz sekce 2.

---

## 5. Otevřené body

- **Pracovní strom není čistý.** Zacommitované, ale ještě ne pushnuté
  (Lukáš pushuje sám): `d971c22` a `1b435b5` (22.–24. 9. a 28. 10., sekce
  2), `b96a7a9` (nastavitelné role, 5. 11., sekce 2) a `578ef2a`
  (`.distignore` ve vydávaném balíčku). Nově nezacommitovaná je dnešní
  oprava fotky trenéra (9. 11., sekce 2): `assets/css/cscs.css`, a tento
  soubor i `DEVELOPER.md`. Dva neverzované vadné JED soubory
  (`course-schedule-connector-cs_CZ-ab413d42…json` a
  `…-f379702637…json`, oba staré z 16. 9., nepoužívané žádným kódem) čekají
  na smazání stejně jako předtím.
- **`tools/i18n/strings.json` je zastaralý vůči `.pot`.** Chybělo v něm 63
  řetězců, co `course-schedule-connector.pot` má (pole Divi modulů, popis
  pluginu a další) — zjištěno 5. 11. při zkoušce `python3
  tools/i18n/build.py`, který by jinak tyhle existující překlady přepsal
  kratší, neúplnou verzí. Důvod: `php` není v tomto prostředí k dispozici,
  takže `tools/extract-strings.php` nejde spustit a `strings.json` se
  dlouho neobnovoval. Stálo by za to nechat `php tools/extract-strings.php`
  proběhnout na stroji, kde `php` je, a `strings.json` i `cs.py` jednou
  provždy doplnit.
- **Nová admin práce nemá PHPUnit pokrytí**, jen ruční ověření přes
  `wp_cli eval-file` proti SQL (sekce 2). Stálo by za to dopsat testy hlavně
  na `CourseRepository::is_semester_over()` a na
  `CourseList::apply_meta_filters()` (kombinace dvou a víc filtrů najednou,
  guard na neplatnou hodnotu v URL).
- **`KindRepository::example_page_with_trainers()` už nemá volajícího.**
  Oprava z 28. 10. (sekce 2) ji z `LoopKindPageBinding` odstranila — mimo
  stránku druhu se teď náhled chová jako web (`EXISTS`), ne jako vzorová
  stránka. Metoda samotná zůstala, je to rozumná obecná schopnost
  repository, ale dokud si ji nevyžádá něco jiného, je to mrtvý kód —
  klidně smazat, nebo nechat.
- **Karusel na úvodní stránce se v obsahu `jojogym 2` nenašel živý** —
  oprava z 28. 10. (sekce 2) je ověřená přímo na datech a dotazech, ne
  kliknutím skutečným karuselem. Stálo by za to, až na webu bude skutečně
  existovat.
- **`LoopKindPageBinding` nemá automatizovaný test**, jen ruční ověření přes
  `wp_cli eval` — stejná mezera jako u admin práce z 22.–24. 9. výš.
- **Sloupce jako child prvky** — pilot hotový na `course-schedule` (13. 9.,
  viz sekce 2 a `DEVELOPER.md`), zbylé čtyři tabulky (`course-makeup`,
  `trainer-courses`, `kind-courses`, `kind-prices`) čekají na totéž — je to teď
  jen přidání `columns_as_children` do katalogu plus vygenerovaný/napsaný
  potomek na každou, ne nový mechanismus. Hlavní zbytek: ruční ověření v obou
  builderech (Studio bylo celou session nedostupné) a rozhodnutí, jestli
  číslovaný panel na `course-schedule` má časem zmizet, nebo zůstat napořád
  vedle potomků jako záloha.
- **Tlačítko má skupiny označené `important`.** Přebít barvu tlačítka z vlastního
  stylopisu proto chce `!important`. Dá se to zúžit jen na vlastnosti, o které se
  Divi opravdu pere, kdyby to vadilo.
- **Zbytky po testování na webu.** Na šabloně *Rozvržení Všechny Druhy Kurzů
  Hlavní Text* je tlačítko obarvené namodro s rádiusem 28 px a stínem — bylo to
  kvůli ověření a dá se to smazat nebo přenastavit. Na stránce *Isport system
  integration test* jsou dva zkušební moduly. `isport_button_text` je na webu
  nastavené na „Registrovat se“.
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
- **Obrázek, který je zároveň odkaz, nese `elementType: 'image'`, ne Diviho
  vlastní `elementType: 'imageLink'`.** `tools/build-divi-modules.php` ho
  natvrdo nastavuje na `image` pro pole s obrázkem i pro karty — i když
  karta trenéra s vyplněným permalinkem (běžný případ) obrázek v
  `templates/partials/cards.php` obalí do `<a
  class="cscs-card__image-link">`. Divi pro přesně tuhle situaci nabízí
  `imageLink` (sekce 4, bod 8), ale tahle hodnota se v kódu nikde
  nepoužívá. Nezměněno 9. 11. — je to hlubší zásah do toho, jak Divi
  tenhle typ prvku styluje, a chtělo by to ověření živě v builderu, ne jen
  čtení kódu.

## 6. Trvalá omezení, která platí bez ohledu na fázi

- Na server iSportu se **nikdy nezapisuje**. Jen dva dokumentované endpointy,
  jen GET, jen ze serveru webu.
- **Tagy kurzů se nemění** — pocházejí z API a zůstávají, jak jsou.
- Design zůstává výsadou administrátora, vynucenou na serveru.
- Synchronizace se dotýká jen toho, co vlastní API. Cokoli člověk napíše —
  u kurzu i u trenéra — zůstává nedotčené.
