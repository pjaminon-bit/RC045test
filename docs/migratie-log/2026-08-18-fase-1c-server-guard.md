# Fase 1C — server-side blokkade voor uitgeschakelde beheer-modules

Datum: 2026-08-18
Branch: `agent/template-foundation`

## Doel

Een uitgeschakelde module mag niet alleen uit de beheerinterface verdwijnen; ook een handmatig opgebouwde POST mag geen data van die module kunnen wijzigen.

## Aanpak

`paneel-hulp.php` wordt door `beheer.php` geladen na `auth.php` en vóór de normale opslagafhandeling. Dat is daarom een geschikte centrale plek om beheer-POSTs al vóór de bestaande formulierverwerking af te vangen.

De moduleguard controleert `$_POST['formulier']` tegen deze mapping:

- `agenda` -> `evenementen`
- `sponsors` -> `sponsors`
- `media` -> `media`
- `media_tekst` -> `media`
- `fotoboek_tekst` -> `fotoboek`
- `fotoboek_album_aanmaken` -> `fotoboek`
- `fotoboek_album_bewerken` -> `fotoboek`
- `aanmelden` -> `aanmelden`

Als de gekoppelde module in `site-config.php` op `false` staat, wordt de formuliernaam vóór de opslagafhandeling leeg gemaakt. `beheer.php` herkent de request daardoor niet meer als een geldige opslagactie en voert geen modulemutatie uit.

## Waarom naast gebruikersrechten

`beheer.php` had al een server-side rechtencontrole per tabblad. Dat controleert of de ingelogde gebruiker een bepaalde beheeractie mag uitvoeren.

Feature flags hebben een ander doel: zij bepalen of de functionaliteit voor de vereniging überhaupt bestaat. Daarom geldt de nieuwe moduleguard ook voor de mastergebruiker. Een master heeft alle gebruikersrechten, maar mag een voor de tenant uitgeschakelde module niet alsnog via een handmatige POST muteren.

## Logging

Een geblokkeerde poging wordt, wanneer de bestaande auth-logger beschikbaar is, vastgelegd als:

`module_geblokkeerd`

met alleen de formuliernaam en de module. POST-inhoud, persoonsgegevens en uploadinhoud worden niet in het log geschreven.

## Resultaat

Voor de vijf gekoppelde modules zijn nu drie lagen aanwezig:

1. publieke zichtbaarheid/toegang;
2. beheerinterface-zichtbaarheid;
3. server-side blokkade van beheer-mutaties.

Daarmee zijn de eerste feature flags functioneel veel dichter bij echte tenantmodules gekomen in plaats van alleen visuele schakelaars.

## Nog open

- Publieke data-loaders en JSON-endpoints hoeven bij een uitgeschakelde module idealiter ook geen moduledata meer te laden of terug te geven.
- `ledenadministratie`, `vergaderingen`, `taken` en `operationele_taken` zijn interne modules en vragen om een aparte inventarisatie van `leden.php` en bijbehorende opslaglagen.
- De huidige modulemapping staat nog op meerdere plekken; in een volgende opschoningsstap kan één centrale moduledefinitie bron van waarheid worden voor publieke pagina, beheertab en formulieracties.
