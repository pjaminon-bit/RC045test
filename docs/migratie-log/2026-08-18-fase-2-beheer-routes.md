# Fase 2 — canonieke beheer-routes

Datum: 2026-08-18

## Doel

Alle zelfstandige beheermodules krijgen hetzelfde URL-patroon onder `/beheer/`. `beheer.php` blijft het dashboard/menu en gemigreerde functionaliteit verhuist naar losse editors.

## Huidige canonieke routes

- Contentpagina's: `/beheer/content.php?pagina=...`
- Agenda: `/beheer/agenda.php`
- Sponsors: `/beheer/sponsors.php`

## Uitgevoerd

- `beheer/content.php` toegevoegd als canonieke route voor de generieke contenteditor.
- Menu-items Ontstaan / geschiedenis en Reglement wijzen voortaan naar `/beheer/content.php`.
- `beheer/module-registry.php` bevat nu ook voor Contentpagina's het editorpad.
- De bestaande root-route `content-beheer.php` blijft voorlopig bestaan als compatibiliteits- en helperlaag.

## Tijdelijke compatibiliteit

`beheer/content.php` gebruikt tijdens deze tussenfase de bestaande implementatie uit `content-beheer.php`. Bij verdere opsplitsing van helperfuncties en editor-rendering verhuist de implementatie fysiek naar `/beheer/` en kan de root-route worden verwijderd of een redirect worden.

## Architectuurregel vanaf nu

Nieuwe zelfstandige beheeronderdelen worden niet meer als los root-PHP-bestand toegevoegd. De route komt onder `/beheer/`; `beheer.php` blijft de centrale navigatie-ingang.
