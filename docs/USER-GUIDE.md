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
- [Sály a aktivity](#sály-a-aktivity)
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
- **Sloupce** – zaškrtnutím vyberete, co se zobrazí, číslem určíte pořadí a volitelně zadáte **vlastní popisek**. Popisek se vyplatí držet krátký: na mobilu se tabulka překlopí tak, že popisky jdou v levém sloupci a hodnoty v pravém.
- **Co zahrnout** – sály, lektory, aktivity (nic zaškrtnutého = všechno), jak daleko dopředu (celé pololetí / tento týden / nejbližší dny / mezi dvěma daty), zda zahrnout **pronájmy a cizí oddíly**, jak naložit se **zrušenými lekcemi** a zda vynechat kurzy bez volných míst.
- **Řazení a rozsah** – podle čeho řadit, vzestupně/sestupně, počet položek na stránku (nula = všechno najednou) a od kolika volných míst má výpis hlásit „posledních pár míst“.
- **Texty** – nadpis, text tlačítka, co napsat, když není co zobrazit, a co místo tlačítka, když je kurz plný.

Ke každé sadě je ve výpisu rovnou napsaný **shortcode**, například `[cscs_courses set="kurzy-pro-deti"]`. Jméno sady v hranatých závorkách vzniká z názvu při vytvoření a **už se nikdy nemění** — kdyby se měnilo s přejmenováním, přestala by fungovat stránka, která ho má v sobě napsaný. Přejmenovat sadu tedy můžete kdykoli.

Smazání sady stránky nerozbije, ale výpis na nich zůstane prázdný — proto to potvrzení.

## Vložení na stránku

Podle toho, jak je stránka postavená:

**V Divi 5** – přidejte modul *Kurzy – karty*, *Kurzy – tabulka*, *Rozvrh – seznam* nebo *Rozvrh – kalendář* a v jeho nastavení vyberte Zobrazovací sadu. Nic dalšího nastavovat nemusíte; vzhled je nastavený administrátorem.

**V editoru bloků** – přidejte blok *Kurzy a rozvrh* a vyberte sadu.

**Kdekoli jinde** (klasický editor, textový widget) – vložte zkrácený kód:

```
[cscs_courses set="kurzy-pro-deti"]
[cscs_schedule set="tydenni-rozvrh"]
```

Slug sady najdete v seznamu Zobrazovacích sad.

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

Poslední dvě skupiny jsou tu proto, abyste se mohl podívat, co klasifikace zachytila; když by některá lekce ke kurzu přece jen patřila, přiřadíte ji stejným výběrem. **Ruční přiřazení je trvalé** – synchronizace ho už nikdy nepřepíše. Uložením se párování rovnou spustí znovu nad uloženými daty, takže výsledek vidíte hned a bez dotazu do iSportu.

V menu u položky svítí číslo, kolik lekcí čeká na rozhodnutí.

## Náhradní lekce

Náhradní lekce patří vždy k jednomu konkrétnímu kurzu, ale z dat iSportu se nedá poznat ke kterému: rozvrh je pojmenuje třeba `Náhradní lekce 4-6 let I. pololetí` a v té věkové kategorii běží tucet kurzů. Stejný název se navíc používá pro náhradní termín kteréhokoli z nich, takže **dva termíny se shodným názvem mohou patřit dvěma různým kurzům**. Proto se přiřazuje po jednotlivých termínech, ne podle názvu.

Slouží k tomu obrazovka **iSport → Náhradní lekce**. U každého termínu vidíte datum, čas, sál a lektora a vyberete kurz, který zastupuje. Nic nemusíte přiřadit hned – termíny bez kurzu se normálně ukládají i počítají, jen se nikde u kurzu neobjeví.

- V menu u položky **Náhradní lekce** svítí číslo, kolik termínů ještě nikdo nepřiřadil. Nové termíny přibývají průběžně, takže je to jediné, co vás na ně upozorní.
- Filtry nahoře přepínají mezi **nepřiřazenými**, **přiřazenými** a **všemi**.
- Tlačítko **Doplnit a uložit návrhy** přiřadí kurz tam, kde ho název lekce sám jmenuje a sedí právě jeden (například `Náhradní lekce 37-Gymnastika 9-11 let dívky I. pololetí`). Už přiřazené termíny nechá být. Návrh je jen návrh – po doplnění ho projděte a případně změňte jako kterýkoli jiný řádek.
- Tlačítko **Přiřadit stejně jako zbytek řady** u řádku (a **Přiřadit opakující se termíny podle řady** dole pro všechny najednou) zkopíruje kurz z termínů, které se opakují se stejným názvem, ve stejný den v týdnu, ve stejnou hodinu a ve stejném sále. Stačí tedy přiřadit první termín řady a zbytek doplnit jedním stiskem. Když byly dva termíny jedné řady přiřazeny ke dvěma různým kurzům, nenabídne se nic — taková řada žádná řada není. Lektor se do porovnání nepočítá, protože rozvrh ho někdy uvádí a jindy ne; vidíte ho ve sloupci vedle.
- Přiřazení se ukládá až tlačítkem **Uložit přiřazení**. Výběrem `— nepřiřazeno —` vazbu zase zrušíte. Tlačítka výše ukládají rovnou, ale nejdřív uloží i to, co jste zatím vybral ručně, takže se nic neztratí.

Přiřazení přežívá synchronizaci. Když termín zestárne a plugin ho podle nastavené doby uchovávání smaže, zmizí s ním i jeho vazba – budoucích termínů se to nedotkne.

## Sály a aktivity

**iSport → Sály a aktivity** slouží k tomu, aby se sály na webu jmenovaly srozumitelně. U každého sálu můžete nastavit:

- **zobrazovaný název** (například `Hala 1` místo `Gymnastická hala 1 a veřejnost`),
- **pořadí** ve výpisech a v kalendáři,
- **barvu**, pokud nechcete použít barvu z iSportu,
- **viditelnost** – sál, který nechcete na webu ukazovat vůbec.

## Přehled a synchronizace

**iSport → Přehled** ukazuje, jestli je všechno v pořádku:

- kdy proběhla poslední synchronizace a kolik záznamů přinesla,
- kolik dotazů plugin dnes odeslal do iSportu,
- kolik procent lekcí se podařilo spárovat s kurzy,
- případné chyby.

Tlačítko **Synchronizovat nyní** vynutí okamžité načtení. Používejte ho, když jste právě v iSportu něco změnili a chcete to hned vidět na webu.

## Co plugin dělá na mobilu

Tabulky se na malých displejích **překlopí**: každý řádek se změní v kartu, kde je vlevo popisek sloupce a vpravo hodnota.

```
Na počítači                      Na mobilu
┌────────┬─────┬──────┐          ┌──────────────────────┐
│ Kurz   │Cena │Místa │          │ Kurz    Aerobic mini │
├────────┼─────┼──────┤    →     │ Cena         1 200 Kč│
│Aerobic │1200 │  3   │          │ Místa               3│
└────────┴─────┴──────┘          └──────────────────────┘
```

Týdenní kalendář se na mobilu přepne do seznamu podle dnů, protože mřížka sál × čas se na úzký displej nevejde čitelně.

Nemusíte pro mobil nic nastavovat – je to automatické.

## Kdy volat administrátora

Tyto věci správce webu záměrně nastavit nemůže, protože ovlivňují vzhled celého webu:

- barvy, písma, velikosti a mezery ve výpisech,
- šířka displeje, při které se tabulka překlápí,
- adresa iSport systému a intervaly synchronizace,
- rozsah pololetí.

Pokud potřebujete něco z toho změnit, obraťte se na administrátora.

## Řešení potíží

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
