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
- [Druh kurzu a karty jako na starém webu](#druh-kurzu-a-karty-jako-na-starém-webu)
- [Pro koho kurz je](#pro-koho-kurz-je)
- [Trenéři](#trenéři)
- [Bloky a moduly pro vlastní design](#bloky-a-moduly-pro-vlastní-design)
- [Kurzy, které v iSportu nejsou](#kurzy-které-v-isportu-nejsou)
- [Když kurz v iSportu skončí](#když-kurz-v-isportu-skončí)
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
- **Co zahrnout** – **druh kurzu**, sály, lektory, **pohlaví**, **úroveň** a **věk** (nic zaškrtnutého a prázdný věk = všechno), jak daleko dopředu (celé pololetí / tento týden / nejbližší dny / mezi dvěma daty), zda zahrnout **pronájmy a cizí oddíly**, jak naložit se **zrušenými lekcemi** a zda vynechat kurzy bez volných míst.
- **Kurzy navíc a Kurzy, které vynechat** – konkrétní kurzy zaškrtnuté jménem, pro výjimku, kterou žádný filtr nevystihne. *Navíc* se zobrazí bez ohledu na filtry výše, *vynechat* se nezobrazí nikdy. Sada, která má zaškrtnuté kurzy a žádný filtr, ukazuje přesně ty zaškrtnuté a nic jiného.
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
- **pohlaví** (dívky, kluci, mix, ženy, muži) a **úroveň** (začátečníci, mírně pokročilí, pokročilí, závodní průprava) – ve výchozím stavu *— podle názvu —*, tedy se čtou z názvu kurzu. Jakmile jednu z voleb vyberete, platí vaše a synchronizace ji už nikdy nepřepíše; vrátit se k automatickému čtení můžete kdykoli výběrem *— podle názvu —*.
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

### Co tlačítko říká

V **iSport → Nastavení**, pole *Co říká přihlašovací odkaz*, se znění nastaví
pro celý web najednou. Použije se všude, kde není řečeno jinak — na stránce
kurzu, v bloku i v Divi modulu. Necháte-li ho prázdné, píše se „Přihlásit
v iSport systému“.

Přepsat to jde na dvou místech: v **zobrazovací sadě** (pole *Text tlačítka*,
platí pro výpis) a přímo v **bloku nebo modulu** *Tlačítko pro přihlášení*
(pole *Text odkazu*). Prázdné pole vždycky znamená „použij to, co je nad tebou“,
takže změna v nastavení se propíše všude, kde jste to nepřebil ručně.

### Tlačítko, nebo odkaz

Jsou to **dva samostatné bloky a moduly**:

- **Tlačítko přihlášení** je skutečné Divi tlačítko. V panelu Návrh má celou
  Diviho skupinu *Tlačítko* — text, pozadí, rámeček, ikonu, stav po najetí — a
  přebírá nastavení tlačítek, které máte pro web jako celek.
- **Přihlašovací odkaz** je odkaz v textu. Vypadá jako kterýkoli jiný odkaz na
  webu a v panelu Návrh má vlastní skupinu *Odkaz* s písmem, odsazením
  a rámečkem.

Obojí vede na totéž místo a obojí se samo skryje, když je kurz plný. Liší se
tím, jak se to navrhuje — a to je celý důvod, proč jsou to dva moduly a ne
jeden s přepínačem.

Jedna věc, kterou je dobré vědět: **Divi předvolba (preset) patří ke konkrétnímu
modulu.** Předvolby uložené pro Diviho vlastní modul Tlačítko se tedy na tenhle
modul nepřenesou — má svoje. Co se přenáší, je nastavení tlačítek pro celý web.

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

- **popis** – co jste ke kurzu napsal v editoru; když jste nenapsal nic, vypíše se **popis z iSportu** (dnes ho má 111 kurzů ze 113). Cokoli napsaného ve WordPressu má vždycky přednost,
- **fakta** – cena, **den** a **čas od–do** (odvozené z lekcí, každý termín na svém řádku, aby se dvojice četla naproti sobě), **pohlaví** a **úroveň**, termín od–do, počet lekcí, sál, trenér a volná místa; údaj, který není čím vyplnit, se vynechá,
- **jméno trenéra jako odkaz** na jeho stránku, pokud ji má,
- **kontakt na lektora**, pokud jste ho vyplnil — u kurzů, na které se přes iSport nepřihlašuje, je to to hlavní, co návštěvník potřebuje,
- **tlačítko pro přihlášení** podle stejných tří pravidel jako všude jinde,
- **nejbližší lekce kurzu** a **náhradní lekce**, které jste k tomuto kurzu přiřadil.

Stránka se vkládá do obsahu, takže hlavičku, patičku i vzhled okolo kreslí dál vaše téma. Do hlavičky stránky se navíc přidává strojově čitelný popis kurzu (JSON-LD) pro vyhledávače — nic v něm netvrdíme, co by stránka neříkala i slovy.

## Druh kurzu a karty jako na starém webu

Karta **Gymnastika dívky** — jeden nadpis, jedna tabulka, devatenáct kurzů — se
postaví takhle: v sadě zaškrtnete **druh kurzu** *Gymnastika* a **pohlaví**
*dívky*. Nic víc.

**Druh kurzu** je nový údaj, který si plugin přečte z názvu: *„56-Gymnastika pro
radost 7-11 let dívky I. pololetí“* je druh **Gymnastika pro radost**, ne
Gymnastika — druh končí tam, kde v názvu začíná věk, pohlaví, úroveň nebo
pololetí, takže víceslovný název zůstane celý. Na dnešní nabídce z toho vychází
25 druhů, což je přesně 25 karet, které web publikuje: Gymnastika (23 kurzů),
Jojo přípravka (15), Lezení (12), Parkour (12), Gymnastika pro radost (7)…

Když iSport u kurzu pošle **jiný název aktivity** než název kurzu, rozhoduje
aktivita — je to její jméno, které říká, kam kurz patří. Takových kurzů je dnes
čtrnáct a u třinácti jde jen o mezeru navíc; u kurzu 25 se ale liší doopravdy
(*Gymnastika 4-6 let dívky pokročilé* × *Jojo přípravka 4-6 let dívky
pokročilé*) a patří k Jojo přípravce, přesně jako na dosavadním webu.

V seznamu **Druhy kurzů** je sloupec **Popis** (prvních pár vět) a **Kurzy**
(kolik jich pod druh spadá) — hned je vidět, které stránky jsou ještě prázdné.
Když je nechcete vidět, odškrtnete je nahoře v **Nastavení zobrazení**.

Stránka druhu se při založení rovnou vyplní **popisem, který sdílí nejvíc kurzů
toho druhu** — máte tedy co ukazovat, aniž byste psal 25 textů. Pak už do ní
synchronizace nikdy nesáhne. Když chcete jiný, v editaci stránky je panel
**Popis z kurzu**: vyberete kurz, kliknete na *Načíst popis* a text se přepíše
(vlastní znění si napřed uložte, vrátit to odtud nejde).

Cena druhu není jedno číslo — hodinová a půldruhahodinová lekce stojí jinak.
Blok i modul **Ceny tohoto druhu** proto vypíšou tabulku o dvou sloupcích,
**Délka** a **Cena**, a jen tolik řádků, kolik je u druhu různých dvojic:
u *Gymnastiky* dva — *60 minut / 4 160 Kč* a *90 minut / 5 160 Kč* — ať jich
pod ní běží dvacet dva. Co se opakuje, se nevypisuje podruhé. Je to obyčejná
tabulka pluginu, takže se dá nastavit stejně jako ostatní. V tabulce kurzů
druhu už proto délka ani cena nejsou — opakovat dvě čísla ve dvaadvaceti
řádcích jen ubíralo místo sloupcům, kvůli kterým se člověk na rozvrh dívá.

Každý druh má **vlastní stránku** — *iSport → Druhy kurzů*. Je to běžný
příspěvek: napíšete text, dáte náhledový obrázek, výpisek i SEO, adresa je
`/druh/gymnastika/`. Stránky vznikají samy, jakmile je pod druh zařazen první
kurz, a synchronizace do nich nikdy nesahá. Pod tím, co napíšete, se vypíše
**rozvrh všech kurzů toho druhu**; totéž jde vložit kamkoli blokem nebo Divi
modulem **Kurzy tohoto druhu** (sloupce Den, Čas od–do, Věk, Pohlaví, Úroveň,
Volná místa a tlačítko, s celým nastavením vzhledu tabulky).

### Jeden druh jako dvě stránky

*Gymnastika* je jedna karta a vy chcete zvlášť dívky a zvlášť kluky. Nepřeřazujte
dvaadvacet kurzů pod druh vymyšlený jen proto, aby polovinu z nich pojal —
udělejte druhou **stránku druhu**:

1. *iSport → Druhy kurzů*, u *Gymnastiky* klikněte na **Kopírovat**. Vznikne
   koncept s týmž popisem i obrázkem.
2. Přejmenujte ho na *Gymnastika dívky*.
3. V panelu **Které kurzy tato stránka ukazuje** nechte *Druh kurzu* na
   Gymnastice a u *Pro koho kurz je* zvolte **Dívky**. Publikujte.

Hotovo. Rozvrh na té stránce má osmnáct řádků, na původní dvaadvacet, na stránce
pro kluky dva — a tabulka **cen** se řídí týmž výběrem, takže klukům ukáže jen
jejich cenu.

Důležité je, že se ptáte **na stránce**, ne v designu. Globální šablona v Divi
Theme Builderu nebo v Šablonách Gutenbergu je jeden design pro všechny stránky
druhu; kdybyste „dívky" nastavil v modulu v šabloně, platilo by to i pro stránku
kluků. Takhle šablonu uděláte jednou a každá stránka si do ní vypíše své.

Stránka, která se jmenuje jinak než druh, se sama s žádným druhem nespáruje —
proto je v panelu i volba **Druh kurzu**. U stránek, které založila
synchronizace, ji nechte být.

Nastavit se to dá i přímo v bloku či modulu (panel **Které kurzy**), ale to má
smysl jen tam, kde modul stojí na jedné konkrétní stránce a má ukázat něco
jiného než ona. Co v modulu vyplníte, přebije stránku; čeho se nedotknete,
nechá stránku mluvit. Kurz, jehož název o dané věci nic neříká, se pod
nastavením, které se na ni ptá, neukáže.

Text stránky druhu umí do šablony vložit blok a modul **Popis druhu kurzu** —
je to protějšek *Popisu kurzu* a *Popisu z iSportu*, které se ptají kurzu,
a proto na stránce druhu nic nenajdou. Odrážky v něm se dají nastavit stejně
jako u ostatních textů.

Druhy jako zařazení najdete pod **iSport → Druhy kurzů**. Přejmenování druhu se propíše všem
kurzům pod ním. Když je čtení u nějakého kurzu vedle — nebo když chcete dva
druhy sloučit do jednoho — přepište u kurzu druh ručně a v panelu *Pole, kterých
se synchronizace nesmí dotknout* zaškrtněte **Druh kurzu**; od té chvíle je vaše
volba nedotknutelná.

## Pro koho kurz je

U kurzu se vypisuje, **pro koho je** (dívky, kluci, mix, ženy, muži), jakou má
**úroveň** (začátečníci, mírně pokročilí, pokročilí, závodní průprava) a pro
jaký **věk** je určen. iSport neposílá ani jedno — nemá na to pole — takže se
všechno tři čte z názvu kurzu, přesně jako to dělá dosavadní web:
*„101-Lezení od 10 let mix mírně pokročilí“* je mix, mírně pokročilí, od 10 let.
Kurz, jehož název nic neříká, nemá vyplněné nic; raději prázdno než domněnka.

Věk se čte ve třech tvarech: *9-11 let* je rozsah, *od 10 let* je spodní hranice
bez horní a *4 roky* je jeden věk. Půlrok se nezaokrouhluje — *2,5-3 roky* jsou
opravdu dva a půl. Číslo kurzu na začátku názvu se za věk nepovažuje, protože za
věkem musí stát „let“ nebo „rok“.

Když čtení něco splete nebo název mlčí, vyberete správnou hodnotu ručně
v editaci kurzu — u věku vyplníte dvě políčka od–do (druhé nechte prázdné
u kurzu bez horní hranice). Od té chvíle platí vaše volba a žádná synchronizace ji
nepřepíše. Vrátit se k automatickému čtení znamená vybrat *— podle názvu —*.

U dívek a žen se úroveň vypíše v ženském rodě (*začátečnice*, *pokročilé*),
jinak v mužském — to není nastavení, plyne to z toho, pro koho kurz je.

## Trenéři

Trenéři jsou vlastní typ obsahu, *iSport → Trenéři*. Zakládá je synchronizace: jakmile nějaký kurz jmenuje trenéra, vznikne mu stránka, stáhne se fotografie z iSportu do knihovny médií a kurz se s ním spáruje. Prohlížeč návštěvníka se tak iSportu nikdy na nic neptá.

Páruje se **podle jména**, bez ohledu na mezery a velikost písmen — jiný společný identifikátor obě strany nemají. Kurzy, které trenér vede, vidíte přímo na jeho editační obrazovce a návštěvník je vidí na jeho stránce.

Na stránce trenéra doplníte to, co iSport nemá kam uložit:

- **Kvalifikace** a **Záliby** – opakovatelné řádky. Přidáte je tlačítkem *Přidat řádek*, odeberete vyprázdněním nebo tlačítkem *Odebrat*. Vypíšou se v pořadí, v jakém je necháte.
- **Zajímavost** – věta nebo dvě, díky kterým je z jména v rozvrhu člověk.
- **Motto**.
- **Fotografie** – ve výchozím stavu ta z iSportu. Chcete-li jinou, nastavte **náhledový obrázek**: ten má vždycky přednost a synchronizace ho nikdy nepřepíše.

Text, který napíšete do editoru, se na stránce zobrazí jako u kurzu. Synchronizace se dotýká jen jména a fotografie — nic z toho, co napíšete, nikdy nepřepíše.

**iSport vede pod jedním jménem víc záznamů trenéra** — u Jojo Gymu jedenáct
z dvaadvaceti jmen má dva nebo tři, každý s vlastní fotkou — a kurz jmenuje ten
záznam, na který byl založen. Plugin páruje podle jména, takže si všechny ty
fotky pamatuje jako fotky jednoho člověka a stáhne každou právě jednou. Kterou
z nich ukáže, neřešte: chcete-li konkrétní, nastavte **náhledový obrázek**, ten
má přednost vždycky.

Kdybyste v knihovně médií našel stovky kopií týchž fotek, jsou z verze před
alfou 2, kdy se fotka stahovala znovu při každém přepnutí adresy. Uklidí je
administrátor příkazem `wp cscs trainers tidy` (nejdřív s `--dry-run`); fotku,
kterou stránka ukazuje, i každý náhledový obrázek nechá být.

## Bloky a moduly pro vlastní design

Když vám výchozí stránka kurzu nebo trenéra nestačí, postavíte si vlastní. Každý údaj má svůj **blok** (Gutenberg) i **modul** (Divi 5) pod vlastním jménem: *Cena, Den, Čas, Věk, Pohlaví, Úroveň, Termín, Lekce, Sál, Trenér, Volná místa, Tlačítko přihlášení, Nejbližší lekce, Náhradní lekce, Obrázek kurzu, Popis kurzu, Popis z iSportu, Na koho se obrátit, Název kurzu* — a u trenéra *Fotografie, Kvalifikace, Záliby, Zajímavost, Motto, Popis, Kurzy které trenér vede*.

Nastavení bloku je rozdělené jako ve WordPressu samotném: v kartě **Nastavení** je,
*co* blok ukazuje (který kurz, co napsat, když není co), v kartě **Styly** všechno
ostatní — nadpis a rozvržení, písmo nadpisu i hodnoty, tabulka, odrážky a animace.

Každý z nich umí:

- **nadpis** – zapnout či vypnout, přejmenovat, zvolit HTML prvek (H1–H6, P, DIV…), přidat za něj oddělovač,
- **rozvržení** – nadpis nad hodnotou, nebo vedle sebe, s nastavitelnou mezerou,
- **vzhled nadpisu i hodnoty zvlášť** – písmo, řez, velikost, řádkování, prostrkání, verzálky, barvu a zarovnání. Tohle je hlavní důvod, proč bloky existují: „Cena“ a „4 160 Kč“ jsou dvě věci, které chce návrhář nastavit jinak,
- **všechno ostatní, co editor nabízí** – barvy, pozadí, přechody, odsazení, rámečky, stín, sticky pozici, šířku na celou stránku; v Divi navíc jeho vlastní skupiny *Text nadpisu* a *Text hodnoty*,
- **odrážky** – u polí, jejichž text může obsahovat seznam (*Popis kurzu*, *Popis z iSportu*, *Popis trenéra*, *Kvalifikace*, *Záliby*): čím se odráží, barvu odrážky, odsazení a mezeru mezi položkami. V Divi jsou to skupiny *Položka seznamu* a *Značka odrážky*,
- **animaci** při prvním objevení na obrazovce (prolnutí, posun, zvětšení), která se nespustí návštěvníkovi, který si v systému vyžádal omezení pohybu.

Pole, které nemá co říct, se **vynechá celé** — nadpis nad prázdným místem vypadá jako rozbitá stránka, ne jako odpověď „žádné“. Chcete-li místo toho něco napsat, vyplňte *Když není co zobrazit*.

**Zdroj**: pokud nic nevyberete, blok ukáže ten kurz nebo toho trenéra, o kterém stránka je. Právě proto z nich jde postavit jednu šablonu v Divi Theme Builderu nebo v editoru šablon, která poslouží všem kurzům. Konkrétní kurz vyberete jen tam, kde blok stojí na běžné stránce.

### Kde bloky a moduly najdete

Všechny jsou pohromadě, v obou editorech. V Divi je v seznamu modulů jedna
položka **iSport** a v ní všech jedenatřicet; v Gutenbergu má vkladač bloků oddíl
**iSport** a v něm také jedenatřicet. Stejně to dělá WooCommerce. Každý modul
má navíc svou ikonu, aby se v seznamu daly rozeznat od sebe — mezi padesáti
bloky, které nabízí WordPress a šablona, by *Cena* nebo *Název* samy o sobě
neřekly nic.

### Tabulky

*Nejbližší lekce*, *Náhradní lekce* a *Kurzy které trenér vede* nevypisují jednu hodnotu, ale tabulku. U kurzů trenéra jsou **Den** a **Čas od–do** dva sloupce, ne jeden — kurz, který se schází dvakrát týdně, má dva dny a dva časy a čtou se vedle sebe. Ve zobrazovacích sadách si můžete vybrat: buď původní sloupec *Dny a časy*, nebo tyhle dva.

Tabulka se dá navrhnout po částech:

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

## Když kurz v iSportu skončí

Kurz, který iSport přestane nabízet, plugin **stáhne z webu**: zmizí ze všech
výpisů a jeho vlastní stránka se přestane zobrazovat. Nesmaže se — dostane
vlastní stav **Zrušený kurz** a v *iSport → Kurzy* na něj vede záložka
s počtem, takže se dá kdykoli dohledat. Všechno, co je na stránce napsané —
popis, fotka, kontakt — zůstává.

Kdo přijde na starý odkaz (z Googlu, z loňského e-mailu), **se přesměruje na
stránku druhu kurzu**, kam kurz patřil, aby místo chybové hlášky viděl kurzy,
které běží. Když druh svou stránku nemá, zobrazí se běžná stránka „nenalezeno“.

Až iSport kurz zase nabídne, stránka se sama publikuje zpátky. Ručně opravovat
se nic nemusí — a nemá: kdybyste zrušený kurz otevřel a dal *Aktualizovat*,
plugin ho v tom stavu ponechá, protože příští synchronizace by ho stejně
stáhla znovu a stránka by se objevovala a mizela bez vysvětlení. Kurz založený
ručně se nikdy nezruší, ten v iSportu nikdy nebyl.

## Přehled a synchronizace

**iSport → Přehled** ukazuje, jestli je všechno v pořádku:

- kdy proběhla poslední synchronizace a kolik záznamů přinesla,
- kolik dotazů plugin dnes odeslal do iSportu,
- kolik procent lekcí se podařilo spárovat s kurzy,
- případné chyby.

Tlačítko **Synchronizovat nyní** vynutí okamžité načtení. Používejte ho, když jste právě v iSportu něco změnili a chcete to hned vidět na webu. Než doběhne, tlačítka zešednou a vedle nich se točí kolečko — synchronizace sáhne do iSportu několikrát a může trvat skoro minutu.

**Pozastavit synchronizaci** zastaví naplánované úlohy. Web běží dál a čísla na něm zůstanou taková, jaká byla; samo se nic nenačítá, dokud nezmáčknete **Obnovit synchronizaci**. Ruční synchronizace funguje i během pauzy. Hodí se, když se v iSportu zrovna zakládá nové pololetí a nechcete, aby se rozdělaná data průběžně objevovala na webu.

**Načíst všechny chybějící popisy** projde všechny stránky druhů kurzů a doplní popis z iSportu tam, kde ještě žádný není. Stránku, na které už něco napsaného je, nechá být — na to je tlačítko přímo v editoru druhu kurzu. Na konci řekne, kolik stránek doplnil, kolik nechal a u kolika iSport žádný popis nemá.

### Pololetí a to, které kurzy se vůbec načtou

V **iSport → Nastavení** jsou pole **Začátek pololetí** a **Konec pololetí**.
Začátek pololetí neurčuje jen rozvrh — plugin se podle něj ptá iSportu, které
kurzy má vůbec poslat. Ptá se s měsíční rezervou dopředu, protože pololetí
nezačíná v jeden den (kurzy se otevírají celý první týden), ale pokud by tam
bylo datum o celé měsíce vedle, část kurzů by v odpovědi chyběla a plugin by je
považoval za zrušené. Když se po synchronizaci ztratí kurzy, tohle pole je první
místo, kam se podívat.

### Export a import nastavení

Dole na stránce nastavení jsou **Stáhnout nastavení** a **Importovat nastavení**.
Soubor obsahuje všechno z té obrazovky včetně seznamů aktivit a pravidel pro
štítky — hodí se při stěhování webu nebo při zakládání testovací kopie. Import
přepíše jen ta nastavení, která soubor uvádí; hodnotu, kterou by plugin
neuložil ani ve formuláři, odmítne a spočítá. Kurzy, stránky ani rozvrh se
importem nemění.

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


**Volná místa nesedí s tím, co ukazuje iSport.**
Podívejte se na *iSport → Přehled*, do tabulky **Naplánované úlohy**. U řádku
*Kurzy* má být napsáno, kdy poběží příště; když tam stojí **Neběží**, čísla se
prostě přestala stahovat a stránky ukazují stav z poslední proběhlé
synchronizace. Tlačítko *Synchronizovat* je srovná hned a úloha se od načtení
téhle stránky plánuje sama.

**Na stránce se nic nezobrazuje.**
Zkontrolujte, že modul nebo zkrácený kód má vybranou Zobrazovací sadu a že filtry sady nejsou tak úzké, že jim nic neodpovídá. Zkuste dočasně zrušit filtr sálu.

**Kurz na webu chybí.**
Podívejte se na Přehled, kdy proběhla poslední synchronizace, a spusťte **Synchronizovat nyní**. Pokud kurz stále chybí, ověřte, že je v iSportu skutečně publikovaný, a hlavně zkontrolujte **Začátek pololetí** v nastavení: plugin se podle něj ptá, které kurzy má iSport poslat, a datum posunuté dopředu nechá část kurzů mimo odpověď. V *iSport → Kurzy* poznáte takový kurz podle stavu **iSport ho už nenabízí**.

**U některých kurzů chybí věk, pohlaví nebo úroveň.**
Plugin je čte z názvu kurzu. Když v názvu nejsou, nemá je odkud vzít — doplní se ručně na stránce kurzu a synchronizace je pak už nepřepíše.

**Tlačítko „Načíst popis“ u druhu kurzu nic neudělá.**
Od alfy 2 vždycky napíše, co se stalo. Hláška *„iSport pro ten kurz žádný popis nemá“* znamená, že popis je prázdný přímo v iSportu — napravit se to dá jen tam, plugin nemá co načíst.

**Volná místa nesedí.**
Údaje se obnovují řádově v minutách, takže krátké zpoždění je normální. Trvá-li rozdíl déle než čtvrt hodiny, zkontrolujte na Přehledu, jestli synchronizace nehlásí chybu.

**Na Přehledu svítí chyba.**
Nejčastěji je iSport systém dočasně nedostupný. Plugin se sám pokusí znovu připojit a zatím zobrazuje poslední známá data, takže web funguje dál. Když chyba trvá déle než hodinu, ozvěte se administrátorovi.

**Lekce se objevuje v rozvrhu dvakrát.**
Zkontrolujte v Nespárovaných lekcích, jestli není přiřazená ke dvěma kurzům. Případ ohlaste administrátorovi.
