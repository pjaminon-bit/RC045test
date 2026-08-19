# Fase 2 — Gebruikersbeheer modulariseren

Datum: 2026-08-19

## Uitgevoerd

- Zelfstandige beheerroute toegevoegd: `/beheer/gebruikers.php`.
- Historische Gebruikers-tab in `beheer.php` wordt verborgen.
- Historische POST-routes `gebruiker_toevoegen`, `gebruiker_tabs_bijwerken` en `gebruiker_verwijderen` worden geblokkeerd zodra de module actief is.
- Toegang tot de nieuwe module vereist het expliciete recht `gebruikers`; de brede legacy-fallback uit `authRechten()` geeft dus geen toegang tot deze gevoelige pagina.
- De bestaande opslag in `beheer-users.json` en de bestaande `schrijfGebruikers()`-functie blijven behouden, inclusief automatische back-up naar `data-backups/`.
- Alle read-modify-write-acties lopen onder het globale dataslot.

## UX- en veiligheidsverbeteringen

- Account aanmaken, rechten wijzigen en wachtwoord wijzigen zijn nu drie expliciete acties. Een bestaand account wordt niet langer stilzwijgend als wachtwoordreset behandeld via het formulier voor een nieuwe gebruiker.
- Nieuwe en gewijzigde wachtwoorden moeten minimaal 10 tekens zijn.
- Browser-side sterke wachtwoordgenerator toegevoegd (20 tekens via `crypto.getRandomValues`).
- Rechten zijn gegroepeerd in Pagina's, Content, Contributie en Beheer.
- Gevoelige rechten (`gebruikers`, `backups`, `log`) zijn visueel gemarkeerd.
- Accountoverzicht toont aanmaakdatum, laatste succesvolle login uit het bestaande logboek, aantal rechten en een waarschuwing voor oude accounts zonder expliciete `tabs`-lijst.
- Zoekveld toegevoegd voor grotere verenigingen met meerdere beheerders.
- Verwijderen vereist het letterlijk overtypen van de gebruikersnaam; een gewone gebruiker kan het eigen ingelogde account niet verwijderen.
- Wachtwoordgegevens worden nooit in het logboek geschreven.
- Alle mutaties blijven auditbaar via `gebruiker_aangemaakt`, `toegang_bijgewerkt`, `wachtwoord_reset` en `gebruiker_verwijderd`.

## Bewuste compatibiliteit

- Bestaande accountrecords en hashes worden niet gemigreerd of opnieuw gehasht zolang het wachtwoord niet wordt gewijzigd.
- Het master-account uit `beheer-config.php` blijft buiten `beheer-users.json` en kan niet via deze pagina worden verwijderd.
- Het bestaande rechtenmodel in `auth.php` blijft leidend.

## DEV-validatie 19-08-2026

Geslaagd:

1. Gebruikers opent vanuit het normale beheer-menu.
2. Een tijdelijk testaccount met beperkte rechten kon worden aangemaakt.
3. Na inloggen waren alleen de gekozen onderdelen bereikbaar.
4. Een extra recht werd na wijzigen correct zichtbaar.
5. Het wachtwoord kon worden gewijzigd en opnieuw gebruikt voor inloggen.
6. Het testaccount kon worden verwijderd.
7. De bijbehorende gebruikersacties verschenen in het Logboek.

De server-side validatie in `/beheer/gebruikers.php` gebruikt conform afspraak een minimum van 10 tekens; de eerdere vermelding van 12 tekens in dit migratielog was documentatiefout en is gecorrigeerd.

Status: **DEV-gevalideerd**.
