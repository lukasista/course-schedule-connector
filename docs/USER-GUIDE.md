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

Zobrazovací sada je **pojmenované nastavení toho, co se má zobrazit**. Vytvoříte ji jednou a použijete kdekoli na webu.

Příklad: sada *„Kurzy pro děti – úvodní stránka“* může obsahovat jen kurzy z Gymnastické haly 2, seřazené podle data, omezené na šest položek, se sloupci Název, Trenér, Cena a Volná místa.

U každé sady nastavíte:

| Volba | Co dělá |
|---|---|
| **Typ zobrazení** | karty, tabulka, seznam po dnech nebo týdenní kalendář |
| **Sloupce a jejich pořadí** | co se zobrazí a v jakém pořadí |
| **Vlastní popisky** | například `Volná místa` místo `available` |
| **Filtry** | sál, trenér, typ aktivity, rozsah dat, jen kurzy s volnými místy |
| **Pronájmy a cizí oddíly** | zahrnout, nebo skrýt (Veřejnost, Gym Dobřichovice, Sokol Radotín…) |
| **Zrušené lekce** | zobrazit přeškrtnuté, nebo úplně skrýt |
| **Řazení a počet** | podle čeho řadit, kolik položek, stránkování |
| **Texty** | nadpis, text tlačítka, text při prázdném výpisu, text u vyprodaného kurzu |
| **Prahy volných míst** | od kolika míst hlásit „Poslední místa“ a kdy „Obsazeno“ |

**Změna sady se okamžitě projeví všude, kde je použitá.** Nemusíte upravovat jednotlivé stránky.

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

**iSport → Kurzy** obsahuje všechny kurzy stažené z iSportu. U každého můžete doplnit obsah, který v iSportu není:

- vlastní **obrázek** kurzu,
- **delší popis** pro stránku kurzu,
- **vlastní název**, pokud je ten z iSportu příliš technický,
- text pro **SEO**.

Údaje, které přicházejí z iSportu – cena, kapacita, termíny, trenér – **nelze přepsat**. Ty se mění v iSport systému.

> **Zámek pole:** když u kurzu přepíšete název nebo popis, plugin si to zapamatuje a při další synchronizaci vaši verzi nepřepíše. U pole se objeví ikona zámku. Kliknutím na ni se pole odemkne a začne se opět aktualizovat z iSportu.

Kurzy se nikdy nemažou. Po skončení přejdou do stavu *Ukončený*, zmizí z výpisů, ale jejich stránka zůstane dostupná.

## Tlačítko „Přihlásit v iSport systému“

Tlačítko vedoucí do iSportu můžete řídit ve třech úrovních:

1. **Globálně** – v Nastavení určíte, zda se tlačítko zobrazuje standardně.
2. **U jednoho kurzu** – v editaci kurzu přepínač *Dědit / Vždy zobrazit / Vždy skrýt*.
3. **Hromadně** – v seznamu kurzů zaškrtněte libovolné kurzy a použijte hromadnou akci *Zobrazit tlačítko iSport* nebo *Skrýt tlačítko iSport*. Seznam lze předtím profiltrovat, například podle sálu.

Tlačítko se navíc **samo skryje**, když je kurz plný nebo když iSport hlásí, že přihlašování není povolené.

## Nespárované lekce

iSport systém bohužel neposkytuje přímou vazbu mezi kurzem a jeho jednotlivými termíny, takže je plugin spáruje podle názvu a času. Ve většině případů to funguje samo.

Když se lekce spárovat nepodaří, objeví se na obrazovce **iSport → Nespárované lekce**. Tam k ní vyberete správný kurz a potvrdíte. **Takové ruční přiřazení je trvalé** – synchronizace ho už nikdy nepřepíše.

Nespárovaná lekce se v rozvrhu zobrazuje normálně; jen se u ní neukáže odkaz na kurz a nezapočítá se kurzu do seznamu sálů.

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
