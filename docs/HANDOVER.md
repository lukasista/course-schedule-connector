# Předání práce — stav k 22. 8. 2026

Tenhle soubor je most mezi pracovními dny. Je psaný tak, aby se do něj dalo
vstoupit bez znalosti předchozího rozhovoru: co je hotové, jak se to spouští,
co je otevřené. Až se projekt uzavře, zmizí — do vydaného pluginu se nedistribuuje.

---

## 1. Kde projekt stojí

Fáze **F0 – F7 jsou hotové**. Zbývá F8 (bezpečnostní audit), F9 (výkon, i18n,
přístupnost), F10 (testy + Plugin Check), F11 (dokumentace) a F12 (akceptační
brána). Podrobné zadání každé fáze je v `PLAN.md`, kapitola 9.

Poslední commity:

| commit | co přinesl |
|---|---|
| `cc98196` | filtr sálů jako rozbalovací seznam, oprava kliknutí polykaného šablonou |
| `3bc6580` | procházení výpisu: týdny, sál, stránkování |
| `4140b7b` | vracení ručních rozhodnutí zpět (náhradní lekce i nespárované lekce) |
| `8bc59bc` | stránka detailu kurzu |

Testy: **183 prochází**. Spouští se `php tools/phpunit-shim/run.php` z kořene
repozitáře.

---

## 2. Co je hotové, po částech

### Data a synchronizace (F1 – F3)
Kurzy jsou vlastní typ obsahu `cscs_course`, lekce vlastní tabulka. Stahuje se
ze dvou endpointů iSportu, jen čtení, jen ze serveru — v prohlížeči návštěvníka
nevzniká žádný požadavek na iSport a neodesílá se o něm nic.

Kurz a jeho lekce spojuje dvojice `activity_name` + `stamp`, protože API žádný
společný identifikátor nemá. Co se spárovat nepodaří, se neztrácí: leží to
v administraci ve výpisu **Nespárované lekce**, kde se to dá přiřadit ručně.
**Náhradní lekce** mají vlastní obrazovku, včetně tlačítka „přiřadit stejně
jako…“ pro celou řadu termínů najednou.

Každé ruční rozhodnutí jde vzít zpět. U náhradních lekcí tlačítkem přímo
u řádku, u nespárovaných lekcí přes filtr **Přiřazené ručně**, kde se volbou
„nepatří k žádnému kurzu“ lekce vrátí zpátky párování.

### Zobrazení (F4 – F7)
Zobrazovací sada je pojmenované nastavení výpisu: typ (kurzy / rozvrh), sloupce,
rozsah, sály, počet na stránku, texty. Vykresluje ji jeden PHP renderer, který
používají všechny tři cesty — shortcode, Gutenberg blok i modul pro Divi 5.
Šablony jsou přepsatelné v tématu.

Sloupec, ve kterém není ani jedna hodnota, se z výpisu vynechá úplně — prázdná
hlavička vypadá jako rozbitá tabulka, ne jako odpověď „žádné“.

Na mobilu se tabulka rozpadá na dvojice: název sloupce vlevo, hodnota vpravo.
Zlom se nastavuje v administraci.

Stránka kurzu ukazuje fakta, kontakt na lektora, rozvrh kurzu a jeho náhradní
lekce, a nese strukturovaná data `Course` (JSON-LD).

Návštěvník si výpis může zúžit: **týden** (dopředu, dozadu, zpět na tento),
**sál** (rozbalovací seznam) a **stránkování**. Všechno to jsou obyčejné odkazy
a formulář se stavem v adrese — bez JavaScriptu to funguje, s ním se výpis jen
vymění na místě přes REST `/wp-json/cscs/v1/listing`.

### Divi 5
Modul `cscs-display` je registrovaný přes `ModuleRegistration::register_module()`
se `render_callback`, závislost přes `divi_module_library_modules_dependency_tree`.
Design je výsada administrátora — `DesignGuard` to vynucuje na serveru, ne jen
schováním v rozhraní.

Dvě věci o Divi 5, které stály nejvíc času a nikde v dokumentaci nejsou:

1. **Atribut se nesmí jmenovat `set`.** Divi drží atributy v seamless-immutable,
   kde `set`/`setIn` jsou metody; atribut toho jména je přepíše a builder
   selže na `getIn(...).setIn is not a function` — tiše, výběr se prostě
   neuloží. Modul proto používá `listing.advanced.id`.
2. **Modul musí mít `module-default-render-attributes.json`.** Bez něj neexistuje
   struktura, do které by se dalo zapsat, a platí totéž — výběr se neuloží.

V builderu se nesmí sáhnout na `window.React`; Divi vykresluje vlastní instancí
v `window.vendor.React` a hooky z cizí kopie Reactu vyhodí výjimku.

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
# z kontejneru na Mac
tar czf /tmp/sync.tgz <soubory>          # v kontejneru
# SendUserFile → device_commit_files do _to_delete/sync.tgz
cd "$HOME/mnt/Jojo Gym iSport System Integration"
mkdir -p _to_delete/x && tar xzf _to_delete/sync.tgz -C _to_delete/x
(cd _to_delete/x && find . -type f -print0 | while IFS= read -r -d '' f; do cp "$f" "../../$f"; done)
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

Varování `unable to unlink '.git/objects/…tmp_obj_…'` je průvodní jev
připojeného svazku, ne chyba — commit proběhne.

Na `git push` z Macu je otevřený problém: `Failed to connect to github.com
port 443`. Ping projde, proxy ani záznam v `/etc/hosts` nejsou. Nesouvisí to
s pluginem.

### Testy, překlady
```
php tools/phpunit-shim/run.php                                # testy
php tools/extract-strings.php && python3 tools/i18n/build.py  # .pot, .po, .mo, JED
```
Podrobnosti k nástrojům jsou v `tools/README.md`. Nové české překlady se
dopisují do `tools/i18n/cs.py`, ne do `.po` — ta se generuje.

### Prohlížeč
Web běží na `http://localhost:8888`, na Lukášově Macu je rozšíření Claude in
Chrome. Chování na frontendu se ověřuje přímo v něm — u Divi to byl jediný
způsob, jak najít obě výše popsané pasti. `read_console_messages` začíná
zaznamenávat až od prvního zavolání, takže stránku je potřeba po zavolání
načíst znovu.

---

## 4. Čím se právě skončilo

Filtr sálů se zobrazoval, ale klik na něj nedělal nic — ani výměnu, ani přechod
na odkaz. Server přitom filtroval správně na všech úrovních.

Příčina: odkazy filtru mířily na `?cscs_room=12#cscs-lekce`, tedy na kotvu na
téže stránce. Divi (a většina šablon) takové odkazy odchytává kvůli plynulému
rolování a klik zastaví dřív, než ho uvidí kdokoli další. Posluchač pluginu se
proto nespustil.

Opraveno dvěma věcmi:
- posluchač naslouchá **při cestě události dolů** (capture), takže se k němu
  žádná šablona nedostane první, ať už web nosí jakoukoli;
- sály jsou nově **formulář s rozbalovacím seznamem** a tlačítkem „Zobrazit“.
  Bez JavaScriptu se formulář odešle, s ním stačí vybrat a tlačítko se skryje
  (`.cscs-js` na `<html>`). Přepínač týdnů zůstal jako Předchozí/Další.

Ověřeno v prohlížeči: výběr sálu 12 → 230 řádků, všechny „Gymnastická hala 2“,
adresa `?cscs_room=12`. Zpětně ověřeno i na odkazu s kotvou.

---

## 5. Otevřené body

- **Přepínač týdnů se u sady `lekce` nezobrazuje**, protože má rozsah
  *pololetí*, ne *týden*. Je to záměr — přepínal by něco, co výpis nezohledňuje.
  Zobrazí se po přepnutí rozsahu v nastavení sady.
- **Nepushnuté commity** na Macu, viz problém s portem 443 výše.
- **Lukášova prohlídka pluginu** proběhne před F8; z ní vzejdou úpravy, které
  mají přednost před dalšími fázemi.

## 6. Trvalá omezení, která platí bez ohledu na fázi

- Na server iSportu se **nikdy nezapisuje**. Jen dva dokumentované endpointy,
  jen GET, jen ze serveru webu.
- **Tagy kurzů se nemění** — pocházejí z API a zůstávají, jak jsou.
- Design zůstává výsadou administrátora, vynucenou na serveru.
