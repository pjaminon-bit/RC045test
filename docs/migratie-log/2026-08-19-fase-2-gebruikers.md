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
- Nieuwe en gewijzigde wachtwoorden moeten minimaal 12 tekens zijn.
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

## Nog te valideren op DEV

1. Gebruikers opent vanuit het normale beheer-menu.
2. Bestaande accounts en rechten worden correct getoond.
3. Een tijdelijk testaccount aanmaken met beperkte rechten.
4. Inloggen met dat testaccount en controleren dat alleen de gekozen onderdelen bereikbaar zijn.
5. Rechten wijzigen en opnieuw controleren.
6. Wachtwoord wijzigen en opnieuw inloggen.
7. Testaccount verwijderen.
8. Controleren dat mutaties in Logboek en gebruikersback-ups verschijnen.

Status: **wacht op DEV-validatie**.
