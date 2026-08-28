# Předání práce — stav k 28. 8. 2026

Tenhle soubor je most mezi pracovními dny. Je psaný tak, aby se do něj dalo
vstoupit bez znalosti předchozího rozhovoru: co je hotové, jak se to spouští,
co je otevřené. Až se projekt uzavře, zmizí — do vydaného pluginu se nedistribuuje.

---

## 1. Kde projekt stojí

Fáze **F0 – F7 jsou hotové**. Zbývá F8 (bezpečnostní audit), F9 (výkon, i18n,
přístupnost), F10 (testy + Plugin Check), F11 (dokumentace) a F12 (akceptační
brána). Podrobné zadání každé fáze je v `PLAN.md`, kapitola 9.

**Lukášova prohlídka pluginu, která měla přednost před F8, proběhla** a vzešly
z ní tři úpravy. Všechny jsou hotové a ověřené v prohlížeči.

Poslední commity:

| commit | co přinesl |
|---|---|
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

Testy: **203 prochází**. Spouští se `php tools/phpunit-shim/run.php` z kořene
repozitáře.

**Nepushnuté commity** na Macu — viz problém s portem 443 níže.

---

## 2. Co přibylo naposledy

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
- **Nepushnuté commity** na Macu, viz problém s portem 443 výše.
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
