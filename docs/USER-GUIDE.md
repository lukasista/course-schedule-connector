# Uživatelská příručka

> Stav: příručka vzniká souběžně s pluginem. Kapitoly popisují cílový stav a doplňují se, jak jednotlivé části vznikají. Obrázky se doplní po dokončení administrace.

Příručka je pro **správce webu** – člověka, který spravuje nabídku kurzů a rozvrh, ale nemá na starost vzhled stránek.

## Obsah

- [Co plugin dělá](#co-plugin-dělá)
- [První nastavení](#první-nastavení)
- [Zobrazovací sady](#zobrazovací-sady)
- [Vložení na stránku](#vložení-na-stránku)
- [Správa kurzů](#správa-kurzů)
- [Tlačítko „Přihlásit v iSport systému“](#tlačítko-přihlásit-v-isport-systému)
- [Nespárované lekce](#nespárované-lekce)
- [Náhradní lekce](#náhradní-lekce)
- [Sály](#sály)
- [Stránka kurzu](#stránka-kurzu)
- [Trenéři](#trenéři)
- [Bloky a moduly pro vlastní design](#bloky-a-moduly-pro-vlastní-design)
- [Kurzy, které v iSportu nejsou](#kurzy-které-v-isportu-nejsou)
- [Přehled a synchronizace](#přehled-a-synchronizace)
- [Co plugin dělá na mobilu](#co-plugin-dělá-na-mobilu)
- [Kdy volat administrátora](#kdy-volat-administrátora)
- [Řešení potíží](#řešení-potíží)

## Co plugin dělá

Plugin si v pravidelných intervalech sám stahuje kurzy a rozvrh lekcí z iSport systému a ukládá je do WordPressu. Návštěvníci webu pak vidí data přímo z vašeho webu – rychle a spolehlivě, i kdyby byl iSport systém zrovna nedostupný.

**Nemusíte nic přepisovat ručně.** Když se v iSportu změní obsazenost nebo přibude kurz, projeví se to na webu samo, nejpozději do několika minut.

## První nastavení

Toto obvykle udělá administrátor při instalaci. Pro úplnost:

1. **iSport → Nastavení**
2. Vyplňte **adresu iSport systému** (například `https://jojogym.isportsystem.cz`).
3. Zadejte **aktuální pololetí od–do**. Toto jediné nastavení řídí, jak daleko dopředu se stahují lekce a které kurzy se považují za probíhající. Platí pro všechny kurzy najednou.
4. Uložte a na obrazovce **Přehled** klikněte na **Synchronizovat nyní**.

## Zobrazovací sady

Zobrazovací sada je **pojmenovaná odpověď na otázku „co má tenhle výpis ukazovat“**. Stránka se na sadu odkazuje jménem, takže když sadu změníte, změní se všechny stránky, které ji používají — bez otevírání Divi a bez zásahu do vzhledu.

Sady najdete v **iSport → Zobrazovací sady**. U každé nastavíte:

- **Co ukazuje** – *Kurzy* (to, na co se lidé přihlašují), nebo *Lekce* (jednotlivé termíny v rozvrhu). Každý typ má jinou nabídku sloupců.
- **Sloupce** – zaškrtnutím vyberete, co se zobrazí, číslem určíte pořadí a volitelně zadáte **vlastní popisek**. Sloupec, ve kterém by nebyla ani jedna hodnota — třeba tlačítko, když je globálně vypnuté — se z výpisu vynechá sám a vrátí se, jakmile bude co zobrazit. Popisek se vyplatí držet krátký: na mobilu se tabulka překlopí tak, že popisky jdou v levém sloupci a hodnoty v pravém.
- **Co zahrnout** – sály, lektory, aktivity (nic zaškrtnutého = všechno), jak daleko dopředu (celé pololetí / tento týden / nejbližší dny / mezi dvěma daty), zda zahrnout **pronájmy a cizí oddíly**, jak naložit se **zrušenými lekcemi** a zda vynechat kurzy bez volných míst.
- **Řazení a rozsah** – podle čeho řadit, vzestupně/sestupně, počet položek na stránku (nula = všechno najednou) a od kolika volných míst má výpis hlásit „posledních pár míst“.
- **Texty** – nadpis, text tlačítka, co napsat, když není co zobrazit, a co místo tlačítka, když je kurz plný.

Ke každé sadě je ve výpisu rovnou napsaný **shortcode**, například `[cscs_courses set="kurzy-pro-deti"]`. Jméno sady v hranatých závorkách vzniká z názvu při vytvoření a **už se nikdy nemění** — kdyby se měnilo s přejmenováním, přestala by fungovat stránka, která ho má v sobě napsaný. Přejmenovat sadu tedy můžete kdykoli.

Smazání sady stránky nerozbije, ale výpis na nich zůstane prázdný — proto to potvrzení.

## Vložení na stránku

Podle toho, jak je stránka postavená:

**V Divi 5** – přidejte modul **iSport výpis** a v jeho nastavení vyberte Zobrazovací sadu. To je jediné pole, které v obsahu je; co se vypisuje, se mění v *iSport → Zobrazovací sady*.

> Designové záložky modulu (typografie, barvy, mezery, rámečky) patří administrátorovi. Když je změní někdo bez oprávnění `cscs_manage_design`, uloží se **původní design** — obsahová změna se zachová, designová ne. Není to schované, je to vyhodnocené při ukládání na serveru: co rozhodne prohlížeč, jde v prohlížeči zase zrušit.

**V editoru bloků** – přidejte blok *Kurzy a rozvrh* a vyberte sadu.

**Kdekoli jinde** (klasický editor, textový widget, šablona) – vložte zkrácený kód:

```
[cscs_courses set="kurzy-pro-deti"]
[cscs_schedule set="tydenni-rozvrh"]
```

Přesné znění je napsané u každé sady v seznamu Zobrazovacích sad, takže ho stačí zkopírovat. Když máte jen jednu sadu daného typu, funguje i `[cscs_courses]` bez parametru — s více sadami by plugin musel hádat, a to raději neudělá a napíše to.

Když sada s daným jménem neexistuje, přihlášený redaktor uvidí na stránce vysvětlující poznámku, návštěvník nic. Chyba, se kterou návštěvník stejně nic nezmůže, mu nemá kazit stránku.

> Všechny tři cesty — Divi modul, blok i shortcode — vykresluje **tentýž kód**. Výpis tedy nemůže vypadat jinak v editoru a jinak na webu, a změna se projeví všude naráz.

## Správa kurzů

**iSport → Kurzy** obsahuje všechny kurzy stažené z iSportu. Sloupce napovídají, na co se u seznamu nejčastěji ptáte: **Stav** (probíhá / ukončený / iSport už kurz nenabízí), **Tlačítko pro přihlášení** a **Kontakt**.

V editaci kurzu můžete doplnit obsah, který v iSportu není:

- vlastní **obrázek** kurzu (náhledový obrázek),
- **delší popis** pro stránku kurzu (hlavní editor),
- **krátký úvod** (výpisek),
- **kontakt na lektora** – jméno, e-mail, telefon a poznámka. To je určené hlavně pro kurzy, na které se přes iSport nepřihlašuje: návštěvník potřebuje někoho, koho se zeptá. Když jméno necháte prázdné, zobrazí se lektor z iSportu.

Panel **Z iSportu** vedle editoru ukazuje, co o kurzu říká iSport – ID, cena, kapacita, obsazenost, termíny a kdy se to naposledy stahovalo – plus odkaz na kurz přímo v iSportu. Tyhle údaje se mění v iSport systému, ne tady.

> **Zámek pole:** panel *Pole, kterých se synchronizace nesmí dotknout* obsahuje název kurzu, popis z iSportu, lektora, sál a cenu. Co zaškrtnete, si nechá vaši verzi napořád; všechno ostatní se dál obnovuje. Kapacita ani obsazenost mezi zámky nejsou schválně — zamknout číslo, které se mění každou hodinu, znamená udělat ze stránky nepravdu.

Kurzy se nikdy nemažou. Po skončení přejdou do stavu *Ukončený*, zmizí z výpisů, ale jejich stránka zůstane dostupná.

## Tlačítko „Přihlásit v iSport systému“

Tlačítko vedoucí do iSportu můžete řídit ve třech úrovních:

1. **Globálně** – v Nastavení určíte, zda se tlačítko zobrazuje standardně.
2. **U jednoho kurzu** – v editaci kurzu přepínač *Podle nastavení / Vždy zobrazit / Nikdy nezobrazovat*.
3. **Hromadně** – v seznamu kurzů zaškrtněte libovolné kurzy a použijte hromadnou akci *Tlačítko pro přihlášení: vždy zobrazit / nikdy nezobrazovat / podle nastavení*. Seznam lze předtím profiltrovat, například podle sálu nebo lektora.

Tlačítko se navíc **samo skryje**, když je kurz plný nebo když iSport hlásí, že přihlašování není povolené.

## Nespárované lekce

iSport systém bohužel neposkytuje přímou vazbu mezi kurzem a jeho jednotlivými termíny, takže je plugin spáruje podle názvu a času. Ve většině případů to funguje samo — u dat Jojo Gymu vychází párování na 100 %.

Obrazovka **iSport → Nespárované lekce** ukazuje, co se nepodařilo zařadit, a vedle toho i lekce, o kterých plugin rozhodl, že nepatří žádnému kurzu:

- **Nespárované** – tady je potřeba rozhodnout. Vyberete kurz a uložíte.
- **Pronájmy a open lekce** – hala pronajatá někomu jinému nebo lekce bez kurzu. Kurz se u nich nečeká.
- **Aktivity, na které se nepřihlašuje** – kurzy externích lektorů, individuální tréninky.

Čtvrtý filtr **Přiřazené ručně** ukazuje všechno, co jste kdy přiřadil ručně — a je tam právě proto, aby šlo omyl najít a opravit. Volbou „nepatří k žádnému kurzu“ lekci vrátíte zpět párování.

Poslední dvě skupiny jsou tu proto, abyste se mohl podívat, co klasifikace zachytila; když by některá lekce ke kurzu přece jen patřila, přiřadíte ji stejným výběrem. **Ruční přiřazení je trvalé** – synchronizace ho už nikdy nepřepíše. Uložením se párování rovnou spustí znovu nad uloženými daty, takže výsledek vidíte hned a bez dotazu do iSportu.

V menu u položky svítí číslo, kolik lekcí čeká na rozhodnutí.

## Náhradní lekce

Náhradní lekce patří vždy k jednomu konkrétnímu kurzu, ale z dat iSportu se nedá poznat ke kterému: rozvrh je pojmenuje třeba `Náhradní lekce 4-6 let I. pololetí` a v té věkové kategorii běží tucet kurzů. Stejný název se navíc používá pro náhradní termín kteréhokoli z nich, takže **dva termíny se shodným názvem mohou patřit dvěma různým kurzům**. Proto se přiřazuje po jednotlivých termínech, ne podle názvu.

Slouží k tomu obrazovka **iSport → Náhradní lekce**. U každého termínu vidíte datum, čas, sál a lektora a vyberete kurz, který zastupuje. Nic nemusíte přiřadit hned – termíny bez kurzu se normálně ukládají i počítají, jen se nikde u kurzu neobjeví.

- V menu u položky **Náhradní lekce** svítí číslo, kolik termínů ještě nikdo nepřiřadil. Nové termíny přibývají průběžně, takže je to jediné, co vás na ně upozorní.
- Filtry nahoře přepínají mezi **nepřiřazenými**, **přiřazenými** a **všemi**.
- Tlačítko **Doplnit a uložit návrhy** přiřadí kurz tam, kde ho název lekce sám jmenuje a sedí právě jeden (například `Náhradní lekce 37-Gymnastika 9-11 let dívky I. pololetí`). Už přiřazené termíny nechá být. Návrh je jen návrh – po doplnění ho projděte a případně změňte jako kterýkoli jiný řádek.
- Tlačítko **Přiřadit stejně jako zbytek řady** u řádku (a **Přiřadit opakující se termíny podle řady** dole pro všechny najednou) zkopíruje kurz z termínů, které se opakují se stejným názvem, ve stejný den v týdnu, ve stejnou hodinu a ve stejném sále. Stačí tedy přiřadit první termín řady a zbytek doplnit jedním stiskem. Když byly dva termíny jedné řady přiřazeny ke dvěma různým kurzům, nenabídne se nic — taková řada žádná řada není. Lektor se do porovnání nepočítá, protože rozvrh ho někdy uvádí a jindy ne; vidíte ho ve sloupci vedle.
- **Přiřazení jde vzít zpět.** Uložený termín se přesune do filtru **Přiřazené**, kde má u sebe tlačítko *Zrušit přiřazení*. Nic z toho tedy není nevratné.
- Přiřazení se ukládá až tlačítkem **Uložit přiřazení**. Výběrem `— nepřiřazeno —` vazbu zase zrušíte. Tlačítka výše ukládají rovnou, ale nejdřív uloží i to, co jste zatím vybral ručně, takže se nic neztratí.

Přiřazení přežívá synchronizaci. Když termín zestárne a plugin ho podle nastavené doby uchovávání smaže, zmizí s ním i jeho vazba – budoucích termínů se to nedotkne.

## Sály

**iSport → Sály** slouží k tomu, aby se sály na webu jmenovaly srozumitelně. iSport je pojmenovává pro lidi, kteří rozvrh spravují — „Gymnastická hala 1 a veřejnost“ je přesné a v tabulce na mobilu nečitelné. U každého sálu proto můžete nastavit:

- **název na webu** (například `Hala 1`); prázdný název použije ten z iSportu,
- **pořadí** ve výpisech; sály se stejným pořadím se řadí abecedně, takže nechat všude nulu je v pořádku,
- **barvu**, pokud nechcete použít barvu z iSportu — použije se jen když zaškrtnete „Použít“,
- **skrytí** – sál, který se na webu nemá objevit vůbec.

Ve výpisu jsou sály, které uložený rozvrh skutečně používá. Žádný seznam sálů API nenabízí, a vymýšlet ho ze zastaralých dat by znamenalo nabízet sály, které už neexistují. V iSportu se přejmenováním nic nerozbije: název na webu je navázaný na ID sálu, ne na jeho jméno.

## Stránka kurzu

Každý kurz má vlastní stránku. Kromě textu a obrázku, které k němu doplníte, se na ní zobrazí:

- **fakta** – cena, **den** a **čas od–do** (odvozené z lekcí, každý termín na svém řádku, aby se dvojice četla naproti sobě), termín od–do, počet lekcí, sál, trenér a volná místa; údaj, který není čím vyplnit, se vynechá,
- **jméno trenéra jako odkaz** na jeho stránku, pokud ji má,
- **kontakt na lektora**, pokud jste ho vyplnil — u kurzů, na které se přes iSport nepřihlašuje, je to to hlavní, co návštěvník potřebuje,
- **tlačítko pro přihlášení** podle stejných tří pravidel jako všude jinde,
- **nejbližší lekce kurzu** a **náhradní lekce**, které jste k tomuto kurzu přiřadil.

Stránka se vkládá do obsahu, takže hlavičku, patičku i vzhled okolo kreslí dál vaše téma. Do hlavičky stránky se navíc přidává strojově čitelný popis kurzu (JSON-LD) pro vyhledávače — nic v něm netvrdíme, co by stránka neříkala i slovy.

## Trenéři

Trenéři jsou vlastní typ obsahu, *iSport → Trenéři*. Zakládá je synchronizace: jakmile nějaký kurz jmenuje trenéra, vznikne mu stránka, stáhne se fotografie z iSportu do knihovny médií a kurz se s ním spáruje. Prohlížeč návštěvníka se tak iSportu nikdy na nic neptá.

Páruje se **podle jména**, bez ohledu na mezery a velikost písmen — jiný společný identifikátor obě strany nemají. Kurzy, které trenér vede, vidíte přímo na jeho editační obrazovce a návštěvník je vidí na jeho stránce.

Na stránce trenéra doplníte to, co iSport nemá kam uložit:

- **Kvalifikace** a **Záliby** – opakovatelné řádky. Přidáte je tlačítkem *Přidat řádek*, odeberete vyprázdněním nebo tlačítkem *Odebrat*. Vypíšou se v pořadí, v jakém je necháte.
- **Zajímavost** – věta nebo dvě, díky kterým je z jména v rozvrhu člověk.
- **Motto**.
- **Fotografie** – ve výchozím stavu ta z iSportu. Chcete-li jinou, nastavte **náhledový obrázek**: ten má vždycky přednost a synchronizace ho nikdy nepřepíše.

Text, který napíšete do editoru, se na stránce zobrazí jako u kurzu. Synchronizace se dotýká jen jména a fotografie — nic z toho, co napíšete, nikdy nepřepíše.

## Bloky a moduly pro vlastní design

Když vám výchozí stránka kurzu nebo trenéra nestačí, postavíte si vlastní. Každý údaj má svůj **blok** (Gutenberg) i **modul** (Divi 5) pod vlastním jménem: *Cena, Den, Čas, Termín, Lekce, Sál, Trenér, Volná místa, Tlačítko přihlášení, Nejbližší lekce, Náhradní lekce, Obrázek kurzu, Popis kurzu, Na koho se obrátit, Název kurzu* — a u trenéra *Fotografie, Kvalifikace, Záliby, Zajímavost, Motto, Popis, Kurzy které trenér vede*.

Každý z nich umí:

- **nadpis** – zapnout či vypnout, přejmenovat, zvolit HTML prvek (H1–H6, P, DIV…), přidat za něj oddělovač,
- **rozvržení** – nadpis nad hodnotou, nebo vedle sebe, s nastavitelnou mezerou,
- **vzhled nadpisu i hodnoty zvlášť** – písmo, řez, velikost, řádkování, prostrkání, verzálky, barvu a zarovnání. Tohle je hlavní důvod, proč bloky existují: „Cena“ a „4 160 Kč“ jsou dvě věci, které chce návrhář nastavit jinak,
- **všechno ostatní, co editor nabízí** – barvy, pozadí, přechody, odsazení, rámečky, stín, sticky pozici, šířku na celou stránku; v Divi navíc jeho vlastní skupiny *Text nadpisu* a *Text hodnoty*,
- **animaci** při prvním objevení na obrazovce (prolnutí, posun, zvětšení), která se nespustí návštěvníkovi, který si v systému vyžádal omezení pohybu.

Pole, které nemá co říct, se **vynechá celé** — nadpis nad prázdným místem vypadá jako rozbitá stránka, ne jako odpověď „žádné“. Chcete-li místo toho něco napsat, vyplňte *Když není co zobrazit*.

**Zdroj**: pokud nic nevyberete, blok ukáže ten kurz nebo toho trenéra, o kterém stránka je. Právě proto z nich jde postavit jednu šablonu v Divi Theme Builderu nebo v editoru šablon, která poslouží všem kurzům. Konkrétní kurz vyberete jen tam, kde blok stojí na běžné stránce.

### Kde moduly v Divi najdete

Všechny jsou pohromadě: v seznamu modulů je jedna položka **iSport** a v ní všech dvacet čtyři. Stejně to dělá WooCommerce se svou sekcí *Woo Modules*. Každý modul má navíc svou ikonu, aby se v seznamu daly rozeznat od sebe.

### Tabulky

*Nejbližší lekce*, *Náhradní lekce* a *Kurzy které trenér vede* nevypisují jednu hodnotu, ale tabulku — a ta se dá navrhnout po částech:

- **Hlavička tabulky** a **Buňka tabulky** mají každá vlastní písmo, velikost, barvu, pozadí a vnitřní okraj,
- **Odkaz v tabulce** vlastní barvu a zdobení. Když ho necháte být, odkaz si nechá barvu ze šablony webu,
- **Čáry a pruhování** – síla a barva čáry pod řádkem a barva, kterou se obarví každý druhý řádek,
- **Sloupce** – u každého sloupce zvlášť šířka a zarovnání. V Divi má každý sloupec navíc vlastní skupinu s celou typografií.

Co nenastavíte, zůstane tak, jak to vypadá teď — plugin nic nepřepisuje jen proto, že je nastavení k dispozici.

## Kurzy, které v iSportu nejsou

Na některé kurzy — typicky kurzy externích lektorů — Jojo Gym nepřijímá přihlášky ani platby, takže v iSportu žádný záznam kurzu nikdy nevznikne. Přesto zabírají místo v rozvrhu a na web patří.

Takový kurz můžete založit dvěma způsoby:

1. **Z lekce** – na obrazovce *Nespárované lekce* je u každé lekce bez kurzu tlačítko **Založit z toho vlastní kurz**. Plugin převezme název, lektora, sál a cenu, kurz vytvoří a lekci k němu trvale přiřadí.
2. **Ručně** – v *iSport → Kurzy* přes *Přidat nový*, jako každý jiný obsah.

Ručně založený kurz se chová jako každý jiný: má stránku, obsah, kontakt na lektora i tlačítko. Synchronizace se ho nedotkne a nikdy ho neoznačí jako „už se nenabízí“ — v iSportu nikdy nebyl, takže jeho nepřítomnost tam nic neznamená. Poznáte ho v panelu *Z iSportu* podle poznámky u ID.

Další lekce k takovému kurzu přiřadíte na obrazovce *Nespárované lekce* výběrem ze seznamu; podle názvu se nespárují, protože ručně založený kurz nemá v datech termíny, proti kterým by se čas dal ověřit.

## Přehled a synchronizace

**iSport → Přehled** ukazuje, jestli je všechno v pořádku:

- kdy proběhla poslední synchronizace a kolik záznamů přinesla,
- kolik dotazů plugin dnes odeslal do iSportu,
- kolik procent lekcí se podařilo spárovat s kurzy,
- případné chyby.

Tlačítko **Synchronizovat nyní** vynutí okamžité načtení. Používejte ho, když jste právě v iSportu něco změnili a chcete to hned vidět na webu.

## Procházení výpisu

Nad rozvrhem se zobrazí **přepínač týdnů** (u sady nastavené na „tento týden"), **filtr sálů**, pokud výpis pokrývá víc než jeden, a pod výpisem **stránkování**, pokud má sada nastavený počet položek na stránku.

Všechno jsou to obyčejné odkazy — volba je v adrese stránky, takže se dá poslat e-mailem a funguje i tam, kde se nenačte JavaScript. Když se načte, výpis se překreslí na místě bez načtení celé stránky.

Návštěvník může výpis zúžit jen na sál, který sada obsahuje; rozšířit ho přes to, co jste v sadě nastavil, nemůže.

## Co plugin dělá na mobilu

Každá tabulka se pod nastavenou šířkou (výchozí 768 px, mění se v Nastavení) **překlopí**: řádek se změní v kartu, popisky sloupců jdou v levém sloupci a hodnoty v pravém.

```
Desktop                          Mobil
┌────────┬─────┬──────┐          ┌──────────────────────┐
│ Kurz   │Cena │Místa │          │ Kurz    Aerobic mini │
├────────┼─────┼──────┤    →     │ Cena         1 960 Kč│
│Aerobic │1960 │  3   │          │ Místa               3│
└────────┴─────┴──────┘          └──────────────────────┘
```

Proto se u sloupců vyplatí krátký popisek: na mobilu je z něj nadpis vedle hodnoty, ne záhlaví tabulky.

Zrušené lekce jsou přeškrtnuté a označené, plné kurzy mají místo tlačítka text „Obsazeno“ (nebo ten, který si nastavíte v sadě).

## Kdy volat administrátora

Tyto věci správce webu záměrně nastavit nemůže, protože ovlivňují vzhled celého webu:

- barvy, písma, velikosti a mezery ve výpisech,
- šířka displeje, při které se tabulka překlápí,
- adresa iSport systému a intervaly synchronizace,
- rozsah pololetí.

Pokud potřebujete něco z toho změnit, obraťte se na administrátora.

## Řešení potíží

**Menu „iSport“ v administraci vůbec není.** Přihlášený uživatel nemá oprávnění `cscs_manage_content`. Plugin si ho od verze 0.4 doplňuje sám při prvním načtení stránky; pokud přesto chybí, pomůže `wp cscs caps install`, nebo deaktivace a opětovná aktivace pluginu. Zkontrolovat stav jde příkazem `wp cscs caps list`.


**Na stránce se nic nezobrazuje.**
Zkontrolujte, že modul nebo zkrácený kód má vybranou Zobrazovací sadu a že filtry sady nejsou tak úzké, že jim nic neodpovídá. Zkuste dočasně zrušit filtr sálu.

**Kurz na webu chybí.**
Podívejte se na Přehled, kdy proběhla poslední synchronizace, a spusťte **Synchronizovat nyní**. Pokud kurz stále chybí, ověřte, že je v iSportu skutečně publikovaný a že spadá do nastaveného pololetí.

**Volná místa nesedí.**
Údaje se obnovují řádově v minutách, takže krátké zpoždění je normální. Trvá-li rozdíl déle než čtvrt hodiny, zkontrolujte na Přehledu, jestli synchronizace nehlásí chybu.

**Na Přehledu svítí chyba.**
Nejčastěji je iSport systém dočasně nedostupný. Plugin se sám pokusí znovu připojit a zatím zobrazuje poslední známá data, takže web funguje dál. Když chyba trvá déle než hodinu, ozvěte se administrátorovi.

**Lekce se objevuje v rozvrhu dvakrát.**
Zkontrolujte v Nespárovaných lekcích, jestli není přiřazená ke dvěma kurzům. Případ ohlaste administrátorovi.
