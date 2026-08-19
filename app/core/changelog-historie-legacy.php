<?php
// ===== Vaste changelog-regels (ontwikkelaar) =====
//
// Dit bestand hoort bij het tabblad "Changelog" in beheer.php en bevat de
// regels die bij de code horen: alles wat er aan de website zelf is
// gebouwd, gewijzigd of opgelost. Het staat bewust in de repo en niet in
// data/, zodat een nieuwe regel gewoon met de code meegaat bij de volgende
// deploy en niet handmatig in het beheerpaneel hoeft te worden ingetypt.
//
// Regels die het bestuur zelf via beheer.php toevoegt komen in
// data/changelog.json terecht. Beheer.php voegt die twee lijsten samen en
// toont ze op datum. De regels hieronder zijn in het beheerpaneel niet te
// bewerken of te verwijderen: bij de volgende deploy zouden ze toch weer
// terugkomen.
//
// Opbouw per regel:
//   datum  yyyy-mm-dd
//   cat    nieuw | verbeterd | opgelost | beveiliging | onderhoud
//   titel  eén korte zin, geen punt aan het eind
//   tekst  optionele toelichting, mag leeg blijven
//
// Nieuwste regel bovenaan.

return [

  [
    'datum' => '2026-08-17',
    'cat' => 'verbetering',
    'titel' => 'Homepage-JavaScript opgesplitst en centraal geïnitialiseerd',
    'tekst' => 'De omvangrijke inline JavaScript uit index.php staat nu in homepage.js en draait vanuit één expliciete initHomepage-lifecycle. De bestaande functionaliteit en datastromen zijn behouden, terwijl initialisatievolgorde, scope en onderhoudbaarheid duidelijker zijn geworden. De gedeelde sitefunctionaliteit blijft in site-i18n.js.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'fix',
    'titel' => 'Direct laden van Engelse en Duitse homepage hersteld',
    'tekst' => 'De initiële taalwissel op de homepage wordt nu pas uitgevoerd nadat alle renderhelpers zijn geïnitialiseerd. Daardoor veroorzaakt een refresh op ?lang=en of ?lang=de geen JavaScript-fout meer en blijven alle secties en scroll-reveals zichtbaar.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'fix',
    'titel' => 'Artikel 1 baanreglement weer volledig vanuit Beheer',
    'tekst' => 'De nieuwe tekst over actuele openingstijden staat nu als NL/EN/DE-inhoud in het beheer van het baanreglement. Bestaande oude artikel-1 teksten met concrete tijden worden eenmalig gemigreerd, terwijl alle andere beheerteksten intact blijven. De publieke pagina leest artikel 1 weer rechtstreeks uit baanreglement.json.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'fix',
    'titel' => 'Baanreglement verwijst voortaan naar actuele openingstijden',
    'tekst' => 'Artikel 1 bevat geen concrete openingstijden meer. Het verwijst naar de actuele tijden op de website, vermeldt de vrijdagse publicatietermijn, de ledenavond op woensdag, de betekenis van tot einde en de mogelijkheid van sluiting bij slecht weer of onvoorziene omstandigheden. De tekst is opgenomen in NL, EN en DE.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'fix',
    'titel' => 'Dubbele openingstijden in baanreglement voorkomen',
    'tekst' => 'Openingstijdenregels die nog in de via Beheer opgeslagen tekst van artikel 1 staan, worden op de publieke pagina automatisch weggelaten. De overige beheertekst blijft zichtbaar en de actuele tijden komen uitsluitend uit contact.json.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'fix',
    'titel' => 'Artikel 1 baanreglement weer volledig via Beheer aanpasbaar',
    'tekst' => 'De tekst van artikel 1 wordt weer uit baanreglement.json geladen en volgt dus wijzigingen via Beheer. Alleen het aparte blok met actuele openingstijden komt uit contact.json, zodat tijden centraal beheerd blijven zonder de artikeltekst te overschrijven.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'verbetering',
    'titel' => 'Baanreglement gebruikt voortaan de beheerde openingstijden',
    'tekst' => 'Artikel 1 van het baanreglement haalt de openingstijden en tijdelijke dagstatussen nu uit dezelfde contact.json als de homepage. Daardoor worden wijzigingen vanuit Beheer automatisch op beide plekken doorgevoerd. Ook is de oude /Lidmaatschap/-route permanent doorgestuurd naar de lidmaatschapssectie op de homepage.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'beveiliging',
    'titel' => 'Loginlimieten, datalock en deployketen verder aangescherpt',
    'tekst' => 'Mislukte logins worden nu atomair bijgehouden onder een apart slot en begrensd per account én per gehasht bron-IP. De centrale lees-wijzig-schrijf-lock faalt voortaan gesloten met HTTP 503 in plaats van zonder lock door te gaan. De GitHub deploy-workflow heeft expliciet alleen contents: read en zowel checkout als de SFTP-action zijn op vaste commit-SHA’s gepind.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'beveiliging',
    'titel' => 'Audit-afwerking: masterhash en directe rechtencontrole',
    'tekst' => 'Het masterwachtwoord kan nu als password_hash worden opgeslagen; de bestaande plaintext-configuratie blijft tijdelijk als migratiepad werken zodat niemand door de wijziging wordt buitengesloten. Op de ledenpagina worden bestuursrol en accountkoppeling voortaan direct door PHP als bewerkbaar of alleen-lezen gerenderd, waardoor de JavaScript-omweg en het losse status-endpoint niet meer nodig zijn. De accountlijst wordt alleen ingelezen met het expliciete recht Gebruikers en CSV-formules worden direct bij het schrijven van de export geneutraliseerd. Ook de browser-tabtitel volgt nu een taalwissel zonder herladen.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'beveiliging',
    'titel' => 'Accountnamen afgeschermd en ledenexport veilig voor Excel',
    'tekst' => 'Wie de ledenadministratie mag gebruiken maar niet het recht Gebruikers heeft, krijgt de bestuursrol en accountkoppeling alleen nog als alleen-lezen tekst en ontvangt de overige inlognamen niet meer in de HTML-bron. Daarnaast neutraliseert de CSV-export waarden die een spreadsheet als formule zou kunnen uitvoeren, zoals tekst die met =, +, - of @ begint.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'beveiliging',
    'titel' => 'Ledenbeheer kan geen bestuursrechten meer uitdelen',
    'tekst' => 'De bestuursfunctie van een lid en de koppeling met een inlogaccount bepalen welke bestuursdelen iemand kan openen. Iemand met alleen toegang tot Leden kon die twee velden zelf aanpassen en zo bestuursrechten krijgen, ook door een ander ledenrecord aan zijn account te koppelen. Die autorisatievelden zijn nu centraal beschermd: alleen de hoofdbeheerder of iemand met het expliciete recht Gebruikers mag ze wijzigen. Dezelfde bescherming geldt ook bij CSV-import, zodat een omweg via een importbestand niet mogelijk is. Gewone ledengegevens blijven voor ledenbeheerders gewoon bewerkbaar.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'opgelost',
    'titel' => 'Sponsorlink op de homepage herkent index.php weer',
    'tekst' => 'Na de overgang van index.html naar index.php herkende het gedeelde taalscript een rechtstreeks bezoek aan /index.php niet meer als de homepage. Daardoor wees de sponsoroproep in de footer daar via index.html terug naar het contactformulier. De homepage-detectie accepteert nu /, index.html en index.php, zodat de link altijd rechtstreeks naar het contactformulier op dezelfde pagina gaat.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'opgelost',
    'titel' => 'Google en social media zien de site nu in de juiste taal',
    'tekst' => 'De site is drietalig, maar het omschakelen gebeurde pas in de browser. Google en zeker Facebook en WhatsApp kijken naar de pagina zoals die van de server komt, en die was altijd Nederlands: dezelfde Nederlandse titel voor alle drie de talen, en een Nederlandse omschrijving zodra iemand een Engelse of Duitse link deelde. Titel, omschrijving en deelbericht komen nu meteen in de goede taal van de server. De publieke pagina\'s hebben daarvoor een eigen Engelse en Duitse titel en omschrijving gekregen, en de sitemap vertelt Google nu ook dat de drie taalversies bij elkaar horen. De omschrijving van de homepage is in alle drie de talen ingekort, want Google kapt na ongeveer 155 tekens af en de laatste zin viel weg. Voor bezoekers verandert er niets, ook de adressen blijven hetzelfde.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'beveiliging',
    'titel' => 'Uitloggen ruimt nu ook het kenmerk in je browser op',
    'tekst' => 'Bij het uitloggen werd de sessie op de server weggegooid, maar het cookie waarmee je browser zich meldde bleef staan. Dat wordt nu ook opgeruimd. Daarnaast accepteert de site alleen nog een sessie die hij zelf heeft uitgegeven, zodat een oud of van buitenaf opgedrongen kenmerk niet kan blijven rondzingen. Dat telt vooral op een computer die je met anderen deelt. Voor het in- en uitloggen zelf verandert er niets. De vertaalknoppen in het beheerpaneel gebruiken nu dezelfde sessie-instellingen als de rest van de site.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'beveiliging',
    'titel' => 'Ingelogd blijven kan nu alleen nog over een beveiligde verbinding',
    'tekst' => 'De site onthoudt met een cookie dat je bent ingelogd. Dat cookie hoort het stempel "alleen over een beveiligde verbinding versturen" te krijgen, maar of dat stempel erop kwam werd bepaald door een controle die bij onze hosting altijd negatief uitvalt: die handelt de beveiliging af voordat de site zelf aan de beurt is. In de praktijk ging het cookie dus zonder dat stempel de deur uit. Het staat nu altijd aan. Daarnaast stuurt de server iedereen die de site zonder beveiliging opvraagt eerst door naar de beveiligde versie, zodat er geen onbeveiligde weg naar het beheer en de ledenpagina meer bestaat.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'verbeterd',
    'titel' => 'De markering "alleen bij voldoende animo" is rustiger',
    'tekst' => 'Die stand was een massief goud vlak en schreeuwde harder dan de melding dat we gesloten zijn. Het is nu een open randje in goud met gewone tekst, zowel in de openingstijdenkaart als in de balk bovenaan de pagina. Zo loopt het op in zwaarte: een randje bij voldoende animo, een gevuld teal vlak bij alleen leden, een gevuld rood vlak bij gesloten.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'verbeterd',
    'titel' => 'De mededelingsbalk bovenaan is rustiger van kleur',
    'tekst' => 'De balk was een massief gouden vlak en las daardoor als een waarschuwing, ook bij een gewone mededeling. De balk heeft nu een zachte zandkleur met een groene onderlijn, dezelfde kleur als de knoppen. Het blokje Actueel in de openingstijdenkaart is meegelopen, zodat dezelfde melding op beide plekken hetzelfde oogt. In de donkere stand past de kleur zich net als de rest van de site aan.',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'verbeterd',
    'titel' => 'Een tijdelijke stand bij de openingstijden begint nu met "Deze"',
    'tekst' => 'Er stond "Zondag alleen open voor leden", wat leest als een vaste afspraak voor elke zondag. Zet je een dag op alleen leden, gesloten, onderhoud of slecht weer, dan staat er nu "Deze zondag alleen open voor leden". De standen die wel bij de vaste opzet van een dag horen, alleen bij voldoende animo en de variant daarvan voor leden, blijven zonder "Deze", want die gelden elke week. Ook in het Engels ("This Sunday") en Duits ("Diesen Sonntag").',
  ],

  [
    'datum' => '2026-08-17',
    'cat' => 'verbeterd',
    'titel' => 'Notities bij de openingstijden staan nu als opsomming',
    'tekst' => 'De regel over het onderhoud op de eerste zaterdag of zondag en de waarschuwing bij slecht weer stonden als doorlopende tekst onder elkaar. Ze staan nu als twee punten met een opsommingsteken, net als de notities onder de kaarten Gastrijden en Lidmaatschap. De uitroeptekens en het informatie-icoon zijn weg: het opsommingsteken doet dat werk nu. De teksten zelf blijven aan te passen in het tabblad Website bij Openingstijden.',
  ],

  [
    'datum' => '2026-08-15',
    'cat' => 'nieuw',
    'titel' => 'Contributie voor volgend jaar apart in te stellen',
    'tekst' => 'Het aanmeldformulier noemde al wat je volgend jaar betaalt, maar rekende daarvoor met het bedrag van dit jaar. In het tabblad Rekentabel staan nu twee extra velden voor de jaarcontributie van volgend jaar, jeugd en senior. Zolang die leeg zijn verandert er niets; vul je ze in, dan ziet iemand die zich nu aanmeldt meteen het juiste bedrag, en biedt de ledenadministratie datzelfde bedrag aan bij een contributieregel voor dat jaar.',
  ],

  [
    'datum' => '2026-08-14',
    'cat' => 'verbeterd',
    'titel' => 'Gebruikers, Log en Back-ups zijn nu ook een vinkje',
    'tekst' => 'Die drie waren alleen bereikbaar door met het beheerderswachtwoord in te loggen. Nu zijn het gewone rechten die je per gebruiker aan kunt zetten, net als de andere tabbladen. Let op wat je met Gebruikers weggeeft: wie dat heeft, kan zichzelf en anderen alles geven en wachtwoorden opnieuw instellen. Bestaande accounts krijgen ze niet vanzelf.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'verbeterd',
    'titel' => 'Een lid aan een inlogaccount koppelen kan nu iedereen die de ledenadministratie bijhoudt',
    'tekst' => 'Dat kon alleen met het beheerderswachtwoord, waardoor iemand met alle rechten toch geen account aan een lid kon hangen.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'beveiliging',
    'titel' => 'Twee mensen die tegelijk opslaan overschrijven elkaar niet meer',
    'tekst' => 'Alle gegevens worden gelezen, aangepast en in hun geheel teruggeschreven. Zonder slot leest de tweede nog de oude versie en schrijft daar overheen. Het beheer, de ledenpagina, het inschrijven op een evenement en het aanmeldformulier gebruiken nu allemaal hetzelfde slot. Vooral het aanmeldformulier was gevoelig: dat wordt op willekeurige momenten van buitenaf aangeroepen.',
  ],

  [
    'datum' => '2026-08-14',
    'cat' => 'nieuw',
    'titel' => 'Ledenpagina op rc045.nl/leden',
    'tekst' => 'Leden loggen daar in met hun eigen account en zien wat er bij de leden open staat aan actiepunten, de ledenvergaderingen en ALV\'s, en de evenementen waarvoor ze zich kunnen inschrijven. Bestuursleden vinden er ook de ledenadministratie, commissies, vergaderingen, takenlijst, operationele taken en evenementen.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'nieuw',
    'titel' => 'Leden schrijven zich zelf in voor een evenement',
    'tekst' => 'Met de capaciteit erbij, zodat een vol evenement dicht gaat, en het betaalverzoek dat zichtbaar wordt zodra iemand is ingeschreven. De deelnemerslijst blijft dezelfde als die het bestuur in het tabblad Evenementen ziet.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'nieuw',
    'titel' => 'Agenda en notulen op concept of definitief',
    'tekst' => 'Bij een ledenvergadering staat nu per stuk of het concept of definitief is. Leden zien de agenda altijd, met dat label erbij, en het verslag pas als de notulen op definitief staan. Vergaderingen van voor deze instelling tellen als concept.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'verbeterd',
    'titel' => 'Beheer gaat alleen nog over de website',
    'tekst' => 'Alles wat over de vereniging gaat is naar de ledenpagina verhuisd. Wat er in het beheer overblijft zijn de pagina\'s, de content, de rekentabel en de changelog. De schermen zelf zijn niet veranderd, ze staan alleen op een andere plek.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'verbeterd',
    'titel' => 'Toegang tot het beheer en tot de ledenpagina staan los van elkaar',
    'tekst' => 'Iemand kan tegelijk gewoon lid zijn en de website beheren. Bij een gebruiker bepalen de aangevinkte tabbladen wat hij kan: zonder vinkjes alleen de ledenpagina, met vinkjes ook het beheer. Achteraf aanpassen kan altijd.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'beveiliging',
    'titel' => 'Een nieuwe gebruiker krijgt niet meer standaard alles',
    'tekst' => 'Bij het aanmaken stonden alle beheertabbladen aangevinkt, waardoor een account dat even snel werd aangemaakt de hele website kon bewerken en de ledenadministratie kon inzien. Ze staan nu standaard uit.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'beveiliging',
    'titel' => 'Twee leden die tegelijk inschrijven overschrijven elkaar niet meer',
    'tekst' => 'Bij het in- en uitschrijven wordt het evenementenbestand nu als geheel vergrendeld, van lezen tot terugschrijven. Ook de controle op de capaciteit gebeurt binnen dat slot, zodat er niemand extra bij kan als de laatste plek net weg is.',
  ],
  [
    'datum' => '2026-08-14',
    'cat' => 'onderhoud',
    'titel' => 'Opmaak en scripts gedeeld tussen beheer en ledenpagina',
    'tekst' => 'De opmaak en de scripts van de afgeschermde schermen stonden in beheer.php zelf en zouden voor de ledenpagina een tweede keer nodig zijn. Ze staan nu in paneel.css, paneel.js en paneel-thema.js, die allebei de pagina\'s gebruiken. Inloggen zit in auth.php, ook gedeeld, zodat er maar een plek is waar wachtwoorden en rechten worden gecontroleerd.',
  ],

  [
    'datum' => '2026-08-13',
    'cat' => 'opgelost',
    'titel' => 'Menu bijna onleesbaar in donkere stand',
    'tekst' => 'Het menu links had een vaste crèmekleurige achtergrond in plaats van de themavariabele, waardoor de donkere tekstkleur er in de donkere stand bovenop bijna niet te lezen was.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'nieuw',
    'titel' => 'Donker thema in het beheerpaneel',
    'tekst' => 'Beheer.php had nog geen donkere stand, terwijl de rest van de site die al had. Zelfde knop en systeem als op de publieke pagina\'s (systeem/licht/donker, rechtsboven), inclusief een donkere versie van badges en meldingen.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'verbeterd',
    'titel' => 'Operationele taken en evenementen verhuisd naar groep Bestuur',
    'tekst' => 'Stonden eerst in een eigen groep "Operationeel", staan nu bij Bestuur samen met bestuursvergadering, ledenvergadering en takenlijst.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'verbeterd',
    'titel' => 'Menu-groepen in beheerpaneel zijn inklapbaar',
    'tekst' => 'De groepen in het menu links (Pagina\'s, Content, Leden & contributie, enz.) staan nu standaard dicht en klappen open door op de groepsnaam te klikken. Ze blijven altijd dicht bij het laden, ook de groep met de actieve tab.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'opgelost',
    'titel' => 'Tekst onleesbaar bij hover op menu-groep',
    'tekst' => 'Doordat het groepskopje in het menu een knop is geworden, verdween de tekst bij het hoveren erover (donkere tekst op donkere achtergrond). Hersteld naar een leesbaar contrast.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'opgelost',
    'titel' => 'Datumkiezer reageerde niet op klikken in Brave en Firefox',
    'tekst' => 'Het kalenderknopje bij de datumvelden opende de kalender niet betrouwbaar. Nu wordt de kalender expliciet geopend zodra er op het knopje wordt geklikt, in elke browser.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'verbeterd',
    'titel' => 'Datumkiezer naast elk datumveld',
    'tekst' => 'Bij elk datumveld in het beheerpaneel (leden, vergaderingen, evenementen, nieuws, agenda, media, fotoboek, changelog) staat nu een kalenderknopje naast het handmatige invulveld. Het invulveld (dd-mm-jjjj) blijft gewoon werken zoals voorheen.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'nieuw',
    'titel' => 'Eindtijd bij een evenement',
    'tekst' => 'Naast de aanvangstijd staat er nu ook een veld voor de eindtijd. Beide velden begrijpen ook losse notaties zoals "9", "9.30" of "930". Staat de eindtijd voor de aanvang, dan volgt een foutmelding.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'nieuw',
    'titel' => 'Begin- en einddatum inschrijving bij een evenement',
    'tekst' => 'Een evenement kan al aangemaakt en voorbereid worden voordat het voor leden zichtbaar is: met een begindatum inschrijving verschijnt het pas op de afgesproken datum in de lijst voor leden (ook niet via een geraden link ervoor). De einddatum inschrijving is voor nu ter informatie.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'nieuw',
    'titel' => 'Betaalverzoek bij een evenement',
    'tekst' => 'Bij een evenement staat nu ook een veld om de link van een betaalverzoek in te plakken (bv. Tikkie), zodat leden meteen kunnen betalen. Is de link een echte http(s)-link, dan staat er ook een knop om die meteen te openen.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'nieuw',
    'titel' => 'Tabblad Evenementen',
    'tekst' => 'Activiteiten waar leden zich kunnen aanmelden, zoals een clubdag of wedstrijd, met datum, locatie, een optioneel maximaal aantal deelnemers en een deelnemerslijst. Het bestuur beheert die lijst voorlopig zelf, net als de presentielijst bij een ledenvergadering; zodra er een ledenportaal is, kunnen leden zich daar straks ook zelf voor aan- of afmelden. Een evenement kan op "Bestuursleden" gezet worden: die ziet een lid zonder bestuursfunctie dan niet. Wie toegang tot dit tabblad krijgt, staat net als bij de andere tabbladen bij Gebruikers.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'opgelost',
    'titel' => 'Taalvlaggetjes onzichtbaar in Chrome, Edge en Brave op Windows',
    'tekst' => 'De vlaggen bij de taalkeuze stonden als emoji in de tekst. Windows levert zelf geen vlag-emoji mee aan Chromium-browsers (Chrome, Edge, Brave), waardoor daar twee letters te zien waren in plaats van een vlaggetje; in Firefox ging dit toevallig wel goed omdat die browser zijn eigen emoji-lettertype meelevert. De vlaggen zijn nu kleine afbeeldingen (SVG) die overal hetzelfde tonen.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'verbeterd',
    'titel' => 'IBAN-kopieerknop gelijkgetrokken',
    'tekst' => 'De knop op de bedankt-pagina had een ander uiterlijk en een aparte werking dan dezelfde knop op het aanmeldformulier. Nu identiek.',
  ],
  [
    'datum' => '2026-08-13',
    'cat' => 'verbeterd',
    'titel' => 'Opmaak en teksten van de hele site nagelopen',
    'tekst' => 'Typefouten en ontbrekende leestekens gecorrigeerd, dubbele stijlregels samengevoegd, lazy loading toegevoegd aan de footer-afbeeldingen, en dode code (ongebruikte teksten en CSS) opgeruimd.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'nieuw',
    'titel' => 'Tabblad Operationele taken',
    'tekst' => 'Terugkerende klussen die de club sowieso moet doen, met een uitvoeringsfrequentie (dagelijks tot jaarlijks, of naar behoefte) en desgewenst een verantwoordelijk lid. Bij het afmelden als uitgevoerd komt de datum in de geschiedenis van die taak te staan. Een taak kan op "Bestuursleden" gezet worden: die ziet een lid zonder bestuursfunctie dan niet. Wie toegang tot dit tabblad krijgt, staat net als bij de andere tabbladen bij Gebruikers.',
  ],
  [
    'datum' => '2026-08-12',
    'cat' => 'verbeterd',
    'titel' => 'Bestuurstaak toewijzen aan een lid',
    'tekst' => 'Bij een taak in de Takenlijst staat nu ook een veld "Toegewezen aan", naast de bestaande koppeling met een vergadering of commissie.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'beveiliging',
    'titel' => 'Vergaderingen en takenlijst afgeschermd via .htaccess',
    'tekst' => 'De databestanden van bestuursvergaderingen, ledenvergaderingen/ALV\'s en de takenlijst stonden nog niet in de blokkeerregel, terwijl de code er al wel van uitging.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'nieuw',
    'titel' => 'Tabblad Takenlijst',
    'tekst' => 'Bestuurstaken bijhouden met een status (open, in behandeling, afgerond), desgewenst gekoppeld aan een bestuursvergadering, een ledenvergadering/ALV of een commissie. Staat bij Bestuur, alleen zichtbaar voor leden met een bestuursfunctie.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'nieuw',
    'titel' => 'Tabblad Ledenvergadering, inclusief ALV',
    'tekst' => 'Ledenvergaderingen en ALV\'s los van de bestuursvergaderingen bijhouden, met een eigen nummering. Een ALV is qua opzet gewoon een ledenvergadering met dat label erbij. Datum, tijd, locatie, agendapunten, notulen en een presentielijst tegen de actieve leden, net als bij de bestuursvergadering maar dan voor de hele club.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'nieuw',
    'titel' => 'Tabblad Commissies: bestuurslid en commissiehoofd erbij',
    'tekst' => 'De commissielijst is een eigen tabblad geworden (stond eerst onderaan Leden) en heeft nu ook een verantwoordelijk bestuurslid en een commissiehoofd per commissie. Een commissiehoofd hoeft geen bestuurslid te zijn. Aanmaken, hernoemen en verwijderen werkt zoals voorheen.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'verbeterd',
    'titel' => 'Datums met streepjes, tijd van een vergadering ook meteen herschreven',
    'tekst' => 'Datumvelden in beheer tonen nu dd-mm-jjjj in plaats van dd/mm/jjjj. De aanvangstijd van een vergadering werd bij het opslaan al herkend (2100 werd 21:00), dat gebeurt nu ook meteen bij het verlaten van het veld, net als bij een datum.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'verbeterd',
    'titel' => 'Datumveld schrijft zichzelf meteen om naar dd/mm/jjjj',
    'tekst' => 'Vorige regel loste alleen het opslaan op; typte je bijvoorbeeld 01092026 dan bleef dat zo in beeld staan tot de pagina opnieuw laadde. Elk datumveld in beheer herschrijft de invoer nu meteen zodra je naar een ander veld gaat, ook bij later toegevoegde regels zoals een extra agendapunt of contributiejaar.',
  ],

  [
    'datum' => '2026-08-12',
    'cat' => 'opgelost',
    'titel' => 'Datum aaneengeschreven invullen werkte nergens in beheer',
    'tekst' => 'Een datum als 01092026 gaf de melding "Die datum begrijp ik niet". Zulke invoer wordt nu ook gelezen als 01/09/2026: bij een vergadering, een lid (geboortedatum, inschrijfdatum, contributie), en bij Agenda, Nieuws, Media en Fotoboek.',
  ],

  // ===== augustus 2026 =====
  [
    'datum' => '2026-08-12',
    'cat' => 'opgelost',
    'titel' => 'Import maakte dubbele leden aan bij een lid zonder mailadres en zonder geboortedatum',
    'tekst' => 'Zo iemand werd bij een tweede import niet herkend en kwam er een tweede keer bij te staan, ook als je een export meteen weer inlas. De herkenning kijkt nu ook naar het lidnummer samen met de naam, en als laatste redmiddel naar alleen de naam, mits die maar bij één lid voorkomt en er niets tegenspreekt. Twee leden met dezelfde naam worden dus nooit stiekem samengevoegd. In het controleoverzicht staat per regel waarop hij herkend is, met een waarschuwing als dat alleen de naam was.',
  ],
  [
    'datum' => '2026-08-12',
    'cat' => 'nieuw',
    'titel' => 'Tabblad Bestuursvergadering',
    'tekst' => 'Vergaderingen vastleggen met datum, tijd, locatie, presentielijst, agendapunten en notulen. Bij elk agendapunt kun je na afloop het besluit invullen. Ieder bestuurslid kan een vergadering aanmaken en bijwerken, ongeacht de functie. Het tabblad is alleen zichtbaar voor leden met een bestuursfunctie.',
  ],
  [
    'datum' => '2026-08-12',
    'cat' => 'nieuw',
    'titel' => 'Rol bij een lid: bestuursfunctie en commissies',
    'tekst' => 'Onder Vereniging staat nu een keuzelijst voor voorzitter, penningmeester, secretaris of bestuurslid, plus vinkjes voor de commissies. Voorzitter, penningmeester en secretaris tellen automatisch mee als bestuurslid, en een bestuurslid mag ook in een commissie zitten. De rol bepaalt wie het tabblad Bestuursvergadering ziet.',
  ],
  [
    'datum' => '2026-08-12',
    'cat' => 'nieuw',
    'titel' => 'Commissies zelf samenstellen',
    'tekst' => 'Onderaan het tabblad Leden staat een lijst waarin je commissies toevoegt, hernoemt of verwijdert, met het aantal leden erbij. Hernoemen laat de koppeling met de leden intact; verwijderen haalt de commissie ook bij alle leden weg.',
  ],
  [
    'datum' => '2026-08-12',
    'cat' => 'verbeterd',
    'titel' => 'Ledenlijst: filteren en zoeken op rol',
    'tekst' => 'Nieuwe keuzelijst voor alle bestuursleden, een losse functie, een commissie of juist de leden zonder rol. De rol staat ook onder de naam in het overzicht en je kunt erop zoeken. Bestuursfunctie en commissies zitten daarnaast in de CSV-export en worden bij een import weer herkend.',
  ],
  [
    'datum' => '2026-08-12',
    'cat' => 'opgelost',
    'titel' => 'Beheerpaneel op telefoon: piepkleine invulvelden en pagina buiten beeld',
    'tekst' => 'Bij Vragen, Sponsors, Agenda, Nieuws en de tekstpagina\'s werden invulvelden soms tot één letter breed samengedrukt, met grote lege gaten ertussen, en liep de kaart buiten het scherm. De vertaalvelden staan op een smal scherm nu netjes onder elkaar en de kopregels breken af in plaats van de pagina breder te maken.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'nieuw',
    'titel' => 'Changelog in het beheerpaneel',
    'tekst' => 'Nieuw tabblad met alle wijzigingen aan de website, op datum en per categorie. De historie is opgebouwd uit de commits vanaf de eerste versie. Het bestuur kan zelf regels toevoegen; toegang is per gebruiker in te stellen bij Gebruikers.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'verbeterd',
    'titel' => 'Contributie: snelkeuze bedrag met pro rata',
    'tekst' => 'Bij een contributieregel staan nu knopjes voor het volledige jaarbedrag en het pro-rata bedrag volgens de rekentabel. Handmatig invullen blijft mogelijk.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'verbeterd',
    'titel' => 'Ledenformulier: land als keuzelijst, Gemeente heet nu Woonplaats',
    'tekst' => 'Veelgebruikte landen staan bovenaan de lijst.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'verbeterd',
    'titel' => 'Lidnummer wordt gecontroleerd in plaats van vastgezet',
    'tekst' => 'Een dubbel lidnummer is nu zichtbaar en op te lossen, in plaats van dat het veld helemaal op slot zit.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'opgelost',
    'titel' => 'Knop "Gebruik nummer" deed niets tijdens het bewerken van een lid',
    'tekst' => '',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'opgelost',
    'titel' => 'Een leeg lid verwijderen deed niets',
    'tekst' => '',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'verbeterd',
    'titel' => 'Ledenlijst: zoeken op jeugdlid of senior',
    'tekst' => '',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'nieuw',
    'titel' => 'Contributiestatus van meerdere leden tegelijk aanpassen',
    'tekst' => 'Vink leden aan in de lijst en zet ze in één keer op betaald, open of vrijgesteld.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'verbeterd',
    'titel' => 'Ledenbeheer overzichtelijker',
    'tekst' => 'Het formulier is opgedeeld in secties, de contributiestatus is filterbaar, het nummer staat in een eigen kolom en de gekozen sortering wordt onthouden. Tijdens het bewerken van een lid blijven de lijst en de import verborgen.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'nieuw',
    'titel' => 'Automatisch vertalen naar Engels en Duits',
    'tekst' => 'Bij elk tekstblok in beheer staat een vertaalknop die de Nederlandse tekst via DeepL omzet. De vertaling blijft daarna gewoon met de hand aan te passen.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'verbeterd',
    'titel' => 'Vertalingen standaard ingeklapt',
    'tekst' => 'Per blok is alleen het Nederlands zichtbaar. Engels en Duits schuiven ernaast open met een knop.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'opgelost',
    'titel' => 'Vertaalknop leverde HTML-codes op in gewone tekstvelden',
    'tekst' => '',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'nieuw',
    'titel' => 'Navigatiemenu, footer, contactformulier en prijskaarten bewerkbaar',
    'tekst' => 'De laatste vaste teksten op de website staan nu ook in het tabblad Homepage.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'verbeterd',
    'titel' => 'Beheermenu gegroepeerd',
    'tekst' => 'Vier groepen (Pagina\'s, Content, Leden en contributie, Beheer), in dezelfde volgorde als de website zelf. De homepage-velden zitten onder vijf hoofdstukken.',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'beveiliging',
    'titel' => 'DeepL-sleutel afgeschermd via .htaccess',
    'tekst' => '',
  ],
  [
    'datum' => '2026-08-11',
    'cat' => 'opgelost',
    'titel' => 'Mailadresveld in ledenbeheer miste opmaak',
    'tekst' => '',
  ],

  [
    'datum' => '2026-08-10',
    'cat' => 'nieuw',
    'titel' => 'Ledenadministratie in beheer',
    'tekst' => 'Ledenlijst met status, sorteerbare kolommen, klikbare statusbadges als snelfilter, meerjarige contributie en import en export via CSV. Een rij aanklikken opent het lid.',
  ],
  [
    'datum' => '2026-08-10',
    'cat' => 'nieuw',
    'titel' => 'Rechten per gebruiker',
    'tekst' => 'Per beheerder is in te stellen welke tabbladen zichtbaar zijn. Gebruikers, Log en Back-ups blijven altijd alleen voor de hoofdbeheerder.',
  ],
  [
    'datum' => '2026-08-10',
    'cat' => 'nieuw',
    'titel' => 'Alle overige paginateksten beheerbaar',
    'tekst' => 'Homepage, Ontstaan, Baanreglement, Aanmelden, Bedankt, Media en Fotoboek zijn nu volledig vanuit beheer aan te passen.',
  ],
  [
    'datum' => '2026-08-10',
    'cat' => 'verbeterd',
    'titel' => 'Lidmaatschapsprijzen op de homepage komen uit de rekentabel',
    'tekst' => 'Eén plek aanpassen is genoeg; de bedragen op de website volgen automatisch.',
  ],
  [
    'datum' => '2026-08-10',
    'cat' => 'verbeterd',
    'titel' => 'Beheer op mobiel bruikbaar',
    'tekst' => 'Menu links met hamburgerknop, tabellen als kaarten op een smal scherm en datums overal in dd/mm/jjjj.',
  ],
  [
    'datum' => '2026-08-10',
    'cat' => 'opgelost',
    'titel' => 'Een veld leegmaken overschreef de standaardtekst niet',
    'tekst' => '',
  ],
  [
    'datum' => '2026-08-10',
    'cat' => 'onderhoud',
    'titel' => 'Deploy-workflow naar actions/checkout v4',
    'tekst' => 'De oude versie draaide op een verouderde Node.js.',
  ],

  [
    'datum' => '2026-08-08',
    'cat' => 'nieuw',
    'titel' => 'Aanmeldingen komen binnen in de ledenadministratie',
    'tekst' => 'Het aanmeldformulier slaat de aanmelding op met status "nieuw", met spamfilter, snelheidsbegrenzing en controle op dubbele aanmeldingen.',
  ],
  [
    'datum' => '2026-08-08',
    'cat' => 'onderhoud',
    'titel' => 'Gedeelde opmaak en scripts',
    'tekst' => 'Navigatie, footer, sponsorblok, hamburgermenu en de terug-naar-boven knop staan nu in styles.css en site-i18n.js in plaats van los in elke pagina.',
  ],

  [
    'datum' => '2026-08-01',
    'cat' => 'nieuw',
    'titel' => 'Openingstijden met tijdelijke statussen',
    'tekst' => 'Gesloten, gesloten wegens onderhoud en gesloten wegens slecht weer, met een statusbadge op de homepage. Tijdelijke statussen vervallen automatisch om 20:00.',
  ],

  // ===== juli 2026 =====
  [
    'datum' => '2026-07-29',
    'cat' => 'onderhoud',
    'titel' => 'Sitemap bijgewerkt',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-28',
    'cat' => 'verbeterd',
    'titel' => 'Vragen, sponsors en media schalen mee met het scherm',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-26',
    'cat' => 'nieuw',
    'titel' => 'Automatische back-ups van alle beheerdata',
    'tekst' => 'Voor elke opslag gaat er een kopie naar een afgeschermde map. In het tabblad Back-ups is een eerdere versie per onderdeel terug te zetten.',
  ],
  [
    'datum' => '2026-07-26',
    'cat' => 'beveiliging',
    'titel' => 'Blokkade na vijf mislukte inlogpogingen',
    'tekst' => 'Daarnaast wordt het sessie-ID vernieuwd bij het inloggen.',
  ],
  [
    'datum' => '2026-07-26',
    'cat' => 'nieuw',
    'titel' => 'Logboek met filters',
    'tekst' => 'Wie wat wanneer heeft aangepast, te filteren op gebruiker en onderdeel. Bewaartermijn 90 dagen.',
  ],
  [
    'datum' => '2026-07-26',
    'cat' => 'verbeterd',
    'titel' => 'Foto-upload tot 25 MB met voortgangsbalk',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-25',
    'cat' => 'nieuw',
    'titel' => 'Fotoboek: albums verbergen en omschrijving toevoegen',
    'tekst' => 'Ook foto\'s vanaf een iPhone (HEIC) worden nu geaccepteerd.',
  ],
  [
    'datum' => '2026-07-25',
    'cat' => 'nieuw',
    'titel' => 'Sponsoroproep in de footer',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-24',
    'cat' => 'nieuw',
    'titel' => 'Watermerk op fotoboekfoto\'s',
    'tekst' => 'Per album in te stellen, ook toe te passen op al geüploade foto\'s.',
  ],
  [
    'datum' => '2026-07-24',
    'cat' => 'verbeterd',
    'titel' => 'Afgelopen agenda-activiteiten vallen minder op',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-24',
    'cat' => 'verbeterd',
    'titel' => 'Openingstijden per dag instelbaar',
    'tekst' => 'Inclusief de Facebook-link in de footer, die nu ook uit beheer komt.',
  ],
  [
    'datum' => '2026-07-13',
    'cat' => 'nieuw',
    'titel' => 'Beheerpaneel (beheer.php)',
    'tekst' => 'Eerste versie met tabbladen voor openingstijden, agenda, vragen, sponsors en de rekentabel voor de contributie.',
  ],
  [
    'datum' => '2026-07-13',
    'cat' => 'nieuw',
    'titel' => 'Agenda, vragen en sponsors komen uit databestanden',
    'tekst' => 'De website leest ze in, zodat ze zonder code aan te passen te wijzigen zijn.',
  ],
  [
    'datum' => '2026-07-09',
    'cat' => 'nieuw',
    'titel' => 'Meldingsbalk bovenaan de homepage',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-08',
    'cat' => 'verbeterd',
    'titel' => 'Footer opgeschoond en contributieteksten bijgewerkt',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-07',
    'cat' => 'nieuw',
    'titel' => 'Sponsorlogo\'s in de footer',
    'tekst' => '',
  ],
  [
    'datum' => '2026-07-06',
    'cat' => 'nieuw',
    'titel' => 'Bezoekersstatistieken via GoatCounter',
    'tekst' => 'Zonder cookies, dus zonder cookiemelding.',
  ],
  [
    'datum' => '2026-07-06',
    'cat' => 'onderhoud',
    'titel' => 'Automatisch publiceren naar de server',
    'tekst' => 'Elke wijziging in GitHub gaat via een workflow direct naar de hosting.',
  ],
  [
    'datum' => '2026-07-06',
    'cat' => 'onderhoud',
    'titel' => 'Oude adressen doorgestuurd',
    'tekst' => 'Bijvoorbeeld /Over-ons naar het juiste onderdeel op de homepage.',
  ],

  // ===== juni 2026 =====
  [
    'datum' => '2026-06-24',
    'cat' => 'nieuw',
    'titel' => 'Mediapagina',
    'tekst' => 'Overzicht van krantenartikelen en tv-items over RC045.',
  ],
  [
    'datum' => '2026-06-22',
    'cat' => 'nieuw',
    'titel' => 'IBAN kopiëren met één klik',
    'tekst' => 'Op de bedankpagina na het aanmelden.',
  ],
  [
    'datum' => '2026-06-21',
    'cat' => 'beveiliging',
    'titel' => 'Spamfilter op contact- en aanmeldformulier',
    'tekst' => 'Een verborgen veld dat alleen bots invullen.',
  ],
  [
    'datum' => '2026-06-21',
    'cat' => 'nieuw',
    'titel' => 'Foto\'s bij de baan en het ontstaansverhaal',
    'tekst' => 'Met vergroting bij aanklikken.',
  ],
  [
    'datum' => '2026-06-17',
    'cat' => 'nieuw',
    'titel' => 'Website in drie talen',
    'tekst' => 'Nederlands, Engels en Duits, met een taalkeuze die onthouden wordt.',
  ],
  [
    'datum' => '2026-06-15',
    'cat' => 'nieuw',
    'titel' => 'Eerste versie van rc045.nl online',
    'tekst' => '',
  ],

];
