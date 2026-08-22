# Vývojářské nástroje

Nic z této složky se nedistribuuje — `.distignore` ji vylučuje z obou buildů.
Slouží jedinému účelu: prostředí, ve kterém se plugin vyvíjí, nemá přístup
k Packagistu, takže se v něm nedá spustit `composer install`. Místo čekání na
síť si tu leží tři malé nástroje, které dělají to, co by jinak dělal Composer
a WP-CLI.

## `phpunit-shim/`

Náhrada za PHPUnit, dost velká na to, co testy pluginu potřebují: `assertSame`,
`assertTrue`, poskytovatelé dat, `setUp`, očekávané výjimky. Spouští se z kořene
repozitáře:

```
php tools/phpunit-shim/run.php
```

Projde `tests/Unit/*.php` a vypíše počet testů, úspěchů a selhání. Až bude
Composer dostupný, tohle zmizí a nahradí ho `vendor/bin/phpunit` — testy samotné
jsou psané proti běžnému `PHPUnit\Framework\TestCase`, takže se měnit nebudou.

## `extract-strings.php`

Náhrada za `wp i18n make-pot`. Projde všechny `.php` soubory mimo `vendor`,
`tests` a `tools`, najde volání překladových funkcí (`__`, `_e`, `esc_html__`,
`_n`, `_x`, …) a uloží nalezené řetězce do `tools/i18n/strings.json`.

```
php tools/extract-strings.php
```

## `i18n/`

`cs.py` je slovník českých překladů — pro jednoduché řetězce (`CS`), množná
čísla (`CS_PLURAL`), řetězce s kontextem (`CONTEXT`) a řetězce v JavaScriptu
(`JS`, `JS_DIVI`). `build.py` z něj a ze `strings.json` vyrobí všechno, co
WordPress k překladu potřebuje:

```
php tools/extract-strings.php && python3 tools/i18n/build.py
```

Vznikne `.pot`, česká `.po`, zkompilovaná `.mo` a dva JED soubory pro
JavaScript. Jména JED souborů obsahují MD5 cesty ke skriptu — když se skript
přejmenuje nebo přesune, musí se přejmenovat i jeho JED soubor, jinak ho
WordPress nenajde. Pojmenování je `{textdomain}-{locale}-{md5(relativní cesta
ke skriptu)}.json`.

Kdyby se `cs.py` někdy ztratil, dá se sestavit zpátky z
`languages/course-schedule-connector-cs_CZ.po` — jsou v ní všechny páry
původního a přeloženého řetězce.
