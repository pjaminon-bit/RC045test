<?php require_once __DIR__ . '/seo-head.php'; ?><!DOCTYPE html>
<html lang="<?php echo rc045Taal(); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead('baanreglement'); ?>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="48x48" href="favicon-48x48.png">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#1E2C13">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <style>
    
    .page-hero-bg { position: absolute; top: -80px; left: 0; right: 0; bottom: -80px; background-image: url('images/hero-achtergrond.jpg'); background-size: cover; background-position: center; opacity: 0.35; will-change: transform; }
    
    .main { max-width: 800px; margin: 0 auto; padding: 56px 24px 80px; }
    
    .intro-card { background: var(--teal-light); border: 1.5px solid var(--teal); border-radius: var(--radius); padding: 24px 28px; margin-bottom: 40px; font-size: 15px; color: var(--teal-dark); line-height: 1.7; }
    
    .artikel { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 32px; margin-bottom: 20px; box-shadow: var(--shadow); }
    
    .artikel-header { display: flex; align-items: flex-start; gap: 20px; margin-bottom: 20px; }
    
    .artikel-num { font-family: 'Poppins', sans-serif; font-size: 36px; font-weight: 800; color: rgba(200,154,26,0.35); line-height: 1; flex-shrink: 0; min-width: 48px; }
    
    .artikel-title { font-family: 'Poppins', sans-serif; font-size: 20px; font-weight: 700; color: var(--dark); padding-top: 6px; }
    
    .artikel-body { font-size: 15px; color: var(--muted); line-height: 1.8; }
    
    .artikel-body p { margin-bottom: 12px; }
    
    .artikel-body p:last-child { margin-bottom: 0; }
    
    .artikel-body ul { list-style: none; display: flex; flex-direction: column; gap: 10px; margin-bottom: 12px; }
    
    .artikel-body ul li { display: flex; align-items: flex-start; gap: 10px; }
    
    .artikel-body ul li::before { content: '•'; color: var(--teal); font-size: 18px; flex-shrink: 0; margin-top: -1px; }
    
    .sub-artikel { margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--border); }
    
    .sub-artikel-title { font-family: 'Poppins', sans-serif; font-size: 14px; font-weight: 700; color: var(--dark); margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.06em; }
    
    @media (max-width: 600px) {
          .artikel { padding: 24px 20px; }
          .artikel-num { font-size: 28px; }
          .artikel-title { font-size: 17px; }
        }
    
    </style>
  <script data-goatcounter="https://rc045.goatcounter.com/count"
        async src="//gc.zgo.at/count.js"></script>
</head>
<body>
<a href="#main-content" class="skip-link">Naar hoofdinhoud</a>

<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Terug naar boven">↑</button>

<nav class="nav" id="main-nav">
  <div class="nav-inner">
    <a href="index.html" class="nav-logo">
      <img width="400" height="423" src="rc045-logo.png" alt="RC045 logo">
      <div>
        <span class="nav-logo-text">RC045</span>
      </div>
    </a>
    <ul class="nav-links" id="nav-links">
      <li><a href="index.html#over-ons" id="nav-about" data-i18n="nav.about">Over ons</a></li>
      <li><a href="index.html#lidmaatschap" id="nav-membership" data-i18n="nav.membership">Lidmaatschap</a></li>
      <li><a href="index.html#baan" id="nav-track" data-i18n="nav.track">De baan</a></li>
      <li><a href="index.html#locatie" id="nav-location" data-i18n="nav.location">Locatie</a></li>
      <li><a href="fotoboek.html" id="nav-photobook" data-i18n="nav.photobook">Fotoboek</a></li>
      <li class="nav-cta"><a href="index.html#contact" id="nav-contact" data-i18n="nav.contact">Contact</a></li>
      <li class="nav-lid"><a href="aanmelden.html" id="nav-join" data-i18n="nav.join">Lid worden</a></li>
    </ul>
    <div class="lang-switch" id="lang-switch">
      <button class="lang-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Taal / Language / Sprache">
        <span class="lang-trigger-flag" aria-hidden="true"><svg viewBox="0 0 30 20" width="20" height="14"><rect width="30" height="6.67" fill="#AE1C28"/><rect y="6.67" width="30" height="6.66" fill="#fff"/><rect y="13.33" width="30" height="6.67" fill="#21468B"/></svg></span>
        <span class="lang-trigger-code">NL</span>
        <span class="lang-chevron" aria-hidden="true"></span>
      </button>
      <div class="lang-menu">
        <button class="lang-flag active" onclick="setLang('nl')" data-code="NL" title="Nederlands" aria-label="Nederlands" aria-pressed="true"><span class="lang-menu-flag" aria-hidden="true"><svg viewBox="0 0 30 20" width="20" height="14"><rect width="30" height="6.67" fill="#AE1C28"/><rect y="6.67" width="30" height="6.66" fill="#fff"/><rect y="13.33" width="30" height="6.67" fill="#21468B"/></svg></span>Nederlands</button>
        <button class="lang-flag" onclick="setLang('en')" data-code="EN" title="English" aria-label="English" aria-pressed="false"><span class="lang-menu-flag" aria-hidden="true"><svg viewBox="0 0 30 20" width="20" height="14"><rect width="30" height="20" fill="#00247d"/><path d="M0,0 30,20 M30,0 0,20" stroke="#fff" stroke-width="4"/><path d="M0,0 30,20 M30,0 0,20" stroke="#cf142b" stroke-width="2"/><path d="M15,0 15,20 M0,10 30,10" stroke="#fff" stroke-width="7"/><path d="M15,0 15,20 M0,10 30,10" stroke="#cf142b" stroke-width="4"/></svg></span>English</button>
        <button class="lang-flag" onclick="setLang('de')" data-code="DE" title="Deutsch" aria-label="Deutsch" aria-pressed="false"><span class="lang-menu-flag" aria-hidden="true"><svg viewBox="0 0 30 20" width="20" height="14"><rect width="30" height="6.67" fill="#000"/><rect y="6.67" width="30" height="6.66" fill="#DD0000"/><rect y="13.33" width="30" height="6.67" fill="#FFCE00"/></svg></span>Deutsch</button>
      </div>
    </div>
    <button class="nav-hamburger" id="hamburger" aria-label="Menu openen" aria-expanded="false" aria-controls="nav-links">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>

<div class="page-hero">
  <div class="page-hero-bg" id="hero-bg"></div>
  <div class="page-hero-gradient"></div>
  <div class="page-hero-content">
    <div class="section-label" data-i18n="hero.label">RC045</div>
    <h1 data-i18n="hero.title">Baanreglement</h1>
    <p id="br-hero-sub" data-i18n="hero.sub">Dit reglement is in het leven geroepen om onze hobby veilig uit te oefenen. Met het betreden van het RC045-terrein ga je akkoord met de inhoud van dit reglement.</p>
  </div>
</div>

<main class="main" id="main-content">

  <div class="intro-card">
    <strong id="br-intro-bold" data-i18n="intro.bold">Belangrijk:</strong> <span id="br-intro-text" data-i18n="intro.text">Er zal niet worden gediscussieerd over de inhoud van dit reglement. Het niet correct opvolgen van deze regels kan leiden tot een reprimande van het bestuur in welke aard dan ook (e.e.a. conform de notarieel vastgelegde statuten).</span>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">01</div>
      <div class="artikel-title" id="br-a1-title" data-i18n="a1.title">Openingstijden van de baan</div>
    </div>
    <div class="artikel-body" id="br-a1-body">
      <div id="br-a1-text"></div>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">02</div>
      <div class="artikel-title" id="br-a2-title" data-i18n="a2.title">Veiligheid</div>
    </div>
    <div class="artikel-body" id="br-a2-body">
      <div class="sub-artikel" style="padding-top:0; border-top:none;">
        <div class="sub-artikel-title" data-i18n="a2.1.title">2.1 Rijderspodium</div>
        <p data-i18n="a2.1.p1">Het is verboden voor niet-rijders zich op de baan te bevinden. Alleen rijders bevinden zich op de baan (rijderspodium). Aangewezen personen met een hesje mogen op de baan zijn in de rol van baanmeester. Kijkers dienen achter de draad te staan.</p>
        <p data-i18n="a2.1.p2">De definitie van rijder is een lid of gastrijder die op dat moment een RC-auto bestuurt.</p>
      </div>
      <div class="sub-artikel">
        <div class="sub-artikel-title" data-i18n="a2.2.title">2.2 Jumptrack</div>
        <p data-i18n="a2.2.p1">Bij de jumptrack mogen alleen aangewezen personen rondom de schans staan. Rijders staan altijd aan de voorzijde. Indien er een auto beschadigd of stuk gaat zal deze door een baanmeester worden opgehaald. Wanneer een baanmeester een signaal geeft of gaat bewegen stopt iedereen met rijden.</p>
      </div>
      <div class="sub-artikel">
        <div class="sub-artikel-title" data-i18n="a2.3.title">2.3 Crawlerbaan</div>
        <p data-i18n="a2.3.p1">Bij en op de crawlerbaan mogen rijders langs het parcours lopen. Kijkers blijven op een gepaste afstand. Wanneer de rijder aangeeft dat iemand opzij moet of van een hindernis af moet dient men hier gehoor aan te geven.</p>
        <p data-i18n="a2.3.p2">De crawlerbaan is hoofdzakelijk alleen toegankelijk voor leden en gastrijders. Zonder toestemming van het bestuur is het betreden van de crawlerbaan verboden. Op de crawlerbaan is het alleen toegestaan om met crawler auto's deel te nemen. Kraton, X-Max en dergelijke zijn hier niet toegestaan.</p>
      </div>
      <div class="sub-artikel">
        <div class="sub-artikel-title" data-i18n="a2.4.title">2.4 Accu's en apparatuur</div>
        <p data-i18n="a2.4.p1">Auto's moeten technisch goed functioneren en alles moet goed vastzitten. Accu's worden buiten geladen en niet in de kantine of op het rijderspodium. Laad-apparatuur is veilig en voorzien van een goed functionerende stekker en stekkermaterialen.</p>
      </div>
      <div class="sub-artikel">
        <div class="sub-artikel-title" data-i18n="a2.5.title">2.5 Rijden op asfalt</div>
        <p data-i18n="a2.5.p1">Het is niet toegestaan buiten de baan op het asfalt te rijden. Voor het hekwerk gedoseerd rijden met veiligheid op de eerste plaats (alleen rechtstreeks van de baan naar werktafel of v.v.).</p>
      </div>
      <div class="sub-artikel">
        <div class="sub-artikel-title" data-i18n="a2.6.title">2.6 Aansprakelijkheid</div>
        <p data-i18n="a2.6.p1">Het betreden van het RC045-terrein is op eigen risico. Wij zijn niet aansprakelijk voor diefstal of andere schades aan bv. auto's of materiaal. Dit geldt ook bij schades die onderling veroorzaakt worden op en rondom de banen.</p>
      </div>
      <div class="sub-artikel">
        <div class="sub-artikel-title" data-i18n="a2.7.title">2.7 Verantwoordelijkheid gasten</div>
        <p data-i18n="a2.7.p1">Ieder lid is verantwoordelijk voor het gedrag en de acties van zijn/haar gasten en/of bezoekers.</p>
      </div>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">03</div>
      <div class="artikel-title" id="br-a3-title" data-i18n="a3.title">Gastrijders</div>
    </div>
    <div class="artikel-body" id="br-a3-body">
      <p data-i18n="a3.p1">Een gastrijder moet zich altijd aanmelden bij een bestuurslid of sleuteldrager. De baanregels moeten worden besproken met de gastrijder. Ieder verenigingslid moet zorgdragen dat een gastrijder op de hoogte is van de geldende regels. Gastrijders krijgen het reglement.</p>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">04</div>
      <div class="artikel-title" id="br-a4-title" data-i18n="a4.title">Baanmeester</div>
    </div>
    <div class="artikel-body" id="br-a4-body">
      <p data-i18n="a4.p1">De aangewezen baanmeester is herkenbaar door een oranje hesje en heeft op het moment dat hij op de baan staat de leiding. Dat wil zeggen dat alles wat hij zegt moet worden uitgevoerd. Het stil leggen van een race dient per direct opgevolgd te worden.</p>
      <p data-i18n="a4.p2">De baanmeester kan uit meerdere personen bestaan. Het is een roulerende taak en ieder lid zal zijn rol hierin nemen.</p>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">05</div>
      <div class="artikel-title" id="br-a5-title" data-i18n="a5.title">Rijden en gebruik van brandstof</div>
    </div>
    <div class="artikel-body" id="br-a5-body">
      <p data-i18n="a5.p1">Nitro en benzine zijn niet toegestaan. Alleen elektrisch aangedreven voertuigen zijn welkom op ons terrein.</p>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">06</div>
      <div class="artikel-title" id="br-a6-title" data-i18n="a6.title">Verdovende middelen</div>
    </div>
    <div class="artikel-body" id="br-a6-body">
      <p data-i18n="a6.p1">Het is verboden op en rond de baan verdovende middelen te gebruiken.</p>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">07</div>
      <div class="artikel-title" id="br-a7-title" data-i18n="a7.title">Roken en drank</div>
    </div>
    <div class="artikel-body" id="br-a7-body">
      <p data-i18n="a7.p1">Het is niet toegestaan te roken of te drinken op de baan. Alcoholhoudende dranken zijn tijdens openingstijden verboden op het gehele terrein.</p>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">08</div>
      <div class="artikel-title" id="br-a8-title" data-i18n="a8.title">Opruimen</div>
    </div>
    <div class="artikel-body" id="br-a8-body">
      <div class="sub-artikel" style="padding-top:0; border-top:none;">
        <div class="sub-artikel-title" data-i18n="a8.1.title">8.1 Terrein netjes houden</div>
        <p data-i18n="a8.1.p1">Ieder lid en gastrijder verplicht zich tot het opruimen en netjes houden van ons terrein. Zwerfafval moet worden voorkomen, waar nodig ruimen we dit met z'n allen op. Afval doen we scheiden in de daarvoor aangewezen prullenbakken en zakken. De kantine wordt door iedereen opgeruimd en schoongemaakt.</p>
      </div>
      <div class="sub-artikel">
        <div class="sub-artikel-title" data-i18n="a8.2.title">8.2 Chemisch afval</div>
        <p data-i18n="a8.2.p1">Elke rijder is verplicht milieubelastende materialen mee naar huis te nemen en zelf in te leveren als chemisch afval (bv. een defecte accu).</p>
      </div>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">09</div>
      <div class="artikel-title" id="br-a9-title" data-i18n="a9.title">AVG</div>
    </div>
    <div class="artikel-body" id="br-a9-body">
      <p data-i18n="a9.p1">Bij het betreden van het RC045-terrein stem je in met het delen van foto's en video's op social media.</p>
    </div>
  </div>

  <div class="artikel">
    <div class="artikel-header">
      <div class="artikel-num">10</div>
      <div class="artikel-title" id="br-a10-title" data-i18n="a10.title">Het bestuur</div>
    </div>
    <div class="artikel-body" id="br-a10-body">
      <p data-i18n="a10.p1">Indien er een geschil ontstaat over bovengenoemde regels moet dit met het bestuur worden besproken. Een uitspraak van het bestuur is bindend en niet discutabel. Eventuele wijzigingen worden gecommuniceerd via de nieuwsbrief of in een ALV.</p>
    </div>
  </div>

</main>

<footer class="footer">
  <div class="footer-inner">
    <div class="footer-top">
      <div class="footer-brand">
        <img width="400" height="423" src="rc045-logo.png" alt="RC045" loading="lazy" decoding="async">
        <p id="footer-brand-text" data-i18n="footer.brand">Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto's in de regio Zuid-Limburg. Voor beginners én ervaren rijders.</p>
        <div class="footer-social">
          <a href="https://www.facebook.com/rc045/" target="_blank" title="Facebook" aria-label="RC045 op Facebook" id="footer-facebook-link">
            <img src="https://upload.wikimedia.org/wikipedia/commons/b/b9/2023_Facebook_icon.svg" alt="" width="28" height="28" aria-hidden="true" loading="lazy" decoding="async">
          </a>
          <span title="Instagram (binnenkort)" style="opacity: 0.3; display: flex; align-items: center;" aria-label="Instagram binnenkort beschikbaar">
            <img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Instagram_icon.png" alt="" width="28" height="28" aria-hidden="true" loading="lazy" decoding="async">
          </span>
        </div>
      </div>
      <div class="footer-col">
        <h4 id="footer-nav-title" data-i18n="footer.nav">Navigatie</h4>
        <ul>
          <li><a href="index.html#over-ons" id="footer-link-about" data-i18n="nav.about">Over ons</a></li>
          <li><a href="ontstaan.html" id="footer-link-origin" data-i18n="footer.origin">Het ontstaan</a></li>
          <li><a href="media.html" id="footer-link-media" data-i18n="footer.media">Media</a></li>
          <li><a href="fotoboek.html" id="footer-link-photobook" data-i18n="footer.photobook">Fotoboek</a></li>
          <li><a href="index.html#activiteiten" id="footer-link-calendar" data-i18n="footer.calendar">Activiteitenkalender</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4 id="footer-join-title" data-i18n="footer.join">Meedoen</h4>
        <ul>
          <li><a href="aanmelden.html" id="footer-link-become" data-i18n="footer.become">Lid worden</a></li>
          <li><a href="index.html#lidmaatschap" id="footer-link-guesttag" data-i18n="guest.tag">Gastrijden</a></li>
          <li><a href="baanreglement.html" id="footer-link-rules" data-i18n="footer.rules">Baanreglement</a></li>
          <li><a href="index.html#contact" id="footer-link-sponsor" data-i18n="footer.sponsor">Sponsoring</a></li>
          <li><a href="index.html#contact" id="footer-link-contact" data-i18n="nav.contact">Contact</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-sponsors">
      <div class="footer-sponsors-title" id="footer-sponsors-title" data-i18n="footer.sponsors.title">Met dank aan onze sponsoren</div>
      <div class="footer-sponsors-grid" id="sponsors-grid">
        <!-- Sponsors worden hier ingevuld vanuit data/sponsors.json, bij te werken via beheer.php -->
      </div>
      <p class="footer-sponsors-cta" id="footer-sponsors-cta"></p>
    </div>
    <div class="footer-bottom">
      <span>© 2021 – <span id="footer-year"></span> RC045 · Bashers of the South</span>
      <span><span id="footer-credit-text" data-i18n="footer.credit">Website door</span> <a class="footer-credit-link" href="mailto:pjaminon@me.com?subject=Website%20RC045">Pascal Jaminon</a></span>
    </div>
  </div>
</footer>

<script src="site-i18n.js"></script>
  <script>
const i18n = {
  nl: {
    'nav.about': 'Over ons', 'nav.membership': 'Lidmaatschap', 'nav.track': 'De baan',
    'nav.location': 'Locatie', 'nav.photobook': 'Fotoboek', 'nav.contact': 'Contact', 'nav.join': 'Lid worden', 'footer.credit': 'Website door',
      'footer.brand': 'Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto\'s in de regio Zuid-Limburg. Voor beginners én ervaren rijders.',
      'footer.nav': 'Navigatie', 'footer.origin': 'Het ontstaan', 'footer.media': 'Media', 'footer.photobook': 'Fotoboek', 'footer.calendar': 'Activiteitenkalender', 'footer.join': 'Meedoen',
      'footer.become': 'Lid worden', 'footer.rules': 'Baanreglement', 'footer.sponsor': 'Sponsoring', 'footer.sponsors.title': 'Met dank aan onze sponsoren', 'guest.tag': 'Gastrijden',
    'hero.label': 'RC045', 'hero.title': 'Baanreglement',
    'hero.sub': 'Dit reglement is in het leven geroepen om onze hobby veilig uit te oefenen. Met het betreden van het RC045-terrein ga je akkoord met de inhoud van dit reglement.',
    'intro.bold': 'Belangrijk:',
    'intro.text': 'Er zal niet worden gediscussieerd over de inhoud van dit reglement. Het niet correct opvolgen van deze regels kan leiden tot een reprimande van het bestuur in welke aard dan ook (e.e.a. conform de notarieel vastgelegde statuten).',
    'a1.title': 'Openingstijden van de baan',
    'a1.b1': 'Woensdagavond 18:00 uur tot einde (bij voldoende belangstelling en afhankelijk van het weer)',
    'a1.b2': 'Zaterdag 10:00 uur tot einde (bij voldoende belangstelling en afhankelijk van het weer)',
    'a1.b3': 'Zondag 10:00 uur tot einde',
    'a1.p1': '"Tot einde" wil zeggen dat bij onvoldoende animo het RC045-terrein zal worden gesloten door de sleutelhouder. Sluiting van het RC045-terrein zal ook via de WhatsApp groep van RC045 worden gecommuniceerd.',
    'a2.title': 'Veiligheid',
    'a2.1.title': '2.1 Rijderspodium',
    'a2.1.p1': 'Het is verboden voor niet-rijders zich op de baan te bevinden. Alleen rijders bevinden zich op de baan (rijderspodium). Aangewezen personen met een hesje mogen op de baan zijn in de rol van baanmeester. Kijkers dienen achter de draad te staan.',
    'a2.1.p2': 'De definitie van rijder is een lid of gastrijder die op dat moment een RC-auto bestuurt.',
    'a2.2.title': '2.2 Jumptrack',
    'a2.2.p1': 'Bij de jumptrack mogen alleen aangewezen personen rondom de schans staan. Rijders staan altijd aan de voorzijde. Indien er een auto beschadigd of stuk gaat zal deze door een baanmeester worden opgehaald. Wanneer een baanmeester een signaal geeft of gaat bewegen stopt iedereen met rijden.',
    'a2.3.title': '2.3 Crawlerbaan',
    'a2.3.p1': 'Bij en op de crawlerbaan mogen rijders langs het parcours lopen. Kijkers blijven op een gepaste afstand. Wanneer de rijder aangeeft dat iemand opzij moet of van een hindernis af moet dient men hier gehoor aan te geven.',
    'a2.3.p2': 'De crawlerbaan is hoofdzakelijk alleen toegankelijk voor leden en gastrijders. Zonder toestemming van het bestuur is het betreden van de crawlerbaan verboden. Op de crawlerbaan is het alleen toegestaan om met crawler auto\'s deel te nemen. Kraton, X-Max en dergelijke zijn hier niet toegestaan.',
    'a2.4.title': '2.4 Accu\'s en apparatuur',
    'a2.4.p1': 'Auto\'s moeten technisch goed functioneren en alles moet goed vastzitten. Accu\'s worden buiten geladen en niet in de kantine of op het rijderspodium. Laad-apparatuur is veilig en voorzien van een goed functionerende stekker en stekkermaterialen.',
    'a2.5.title': '2.5 Rijden op asfalt',
    'a2.5.p1': 'Het is niet toegestaan buiten de baan op het asfalt te rijden. Voor het hekwerk gedoseerd rijden met veiligheid op de eerste plaats (alleen rechtstreeks van de baan naar werktafel of v.v.).',
    'a2.6.title': '2.6 Aansprakelijkheid',
    'a2.6.p1': 'Het betreden van het RC045-terrein is op eigen risico. Wij zijn niet aansprakelijk voor diefstal of andere schades aan bv. auto\'s of materiaal. Dit geldt ook bij schades die onderling veroorzaakt worden op en rondom de banen.',
    'a2.7.title': '2.7 Verantwoordelijkheid gasten',
    'a2.7.p1': 'Ieder lid is verantwoordelijk voor het gedrag en de acties van zijn/haar gasten en/of bezoekers.',
    'a3.title': 'Gastrijders',
    'a3.p1': 'Een gastrijder moet zich altijd aanmelden bij een bestuurslid of sleuteldrager. De baanregels moeten worden besproken met de gastrijder. Ieder verenigingslid moet zorgdragen dat een gastrijder op de hoogte is van de geldende regels. Gastrijders krijgen het reglement.',
    'a4.title': 'Baanmeester',
    'a4.p1': 'De aangewezen baanmeester is herkenbaar door een oranje hesje en heeft op het moment dat hij op de baan staat de leiding. Dat wil zeggen dat alles wat hij zegt moet worden uitgevoerd. Het stil leggen van een race dient per direct opgevolgd te worden.',
    'a4.p2': 'De baanmeester kan uit meerdere personen bestaan. Het is een roulerende taak en ieder lid zal zijn rol hierin nemen.',
    'a5.title': 'Rijden en gebruik van brandstof',
    'a5.p1': 'Nitro en benzine zijn niet toegestaan. Alleen elektrisch aangedreven voertuigen zijn welkom op ons terrein.',
    'a6.title': 'Verdovende middelen',
    'a6.p1': 'Het is verboden op en rond de baan verdovende middelen te gebruiken.',
    'a7.title': 'Roken en drank',
    'a7.p1': 'Het is niet toegestaan te roken of te drinken op de baan. Alcoholhoudende dranken zijn tijdens openingstijden verboden op het gehele terrein.',
    'a8.title': 'Opruimen',
    'a8.1.title': '8.1 Terrein netjes houden',
    'a8.1.p1': 'Ieder lid en gastrijder verplicht zich tot het opruimen en netjes houden van ons terrein. Zwerfafval moet worden voorkomen, waar nodig ruimen we dit met z\'n allen op. Afval doen we scheiden in de daarvoor aangewezen prullenbakken en zakken. De kantine wordt door iedereen opgeruimd en schoongemaakt.',
    'a8.2.title': '8.2 Chemisch afval',
    'a8.2.p1': 'Elke rijder is verplicht milieubelastende materialen mee naar huis te nemen en zelf in te leveren als chemisch afval (bv. een defecte accu).',
    'a9.title': 'AVG',
    'a9.p1': 'Bij het betreden van het RC045-terrein stem je in met het delen van foto\'s en video\'s op social media.',
    'a10.title': 'Het bestuur',
    'a10.p1': 'Indien er een geschil ontstaat over bovengenoemde regels moet dit met het bestuur worden besproken. Een uitspraak van het bestuur is bindend en niet discutabel. Eventuele wijzigingen worden gecommuniceerd via de nieuwsbrief of in een ALV.'
  },
  en: {
    'nav.about': 'About us', 'nav.membership': 'Membership', 'nav.track': 'The track',
    'nav.location': 'Location', 'nav.photobook': 'Photo book', 'nav.contact': 'Contact', 'nav.join': 'Become a member', 'footer.credit': 'Website by',
      'footer.brand': 'A friendly club for enthusiasts of electrically powered RC cars in the South Limburg region. For beginners and experienced riders alike.',
      'footer.nav': 'Navigation', 'footer.origin': 'Our history', 'footer.media': 'Media', 'footer.photobook': 'Photo book', 'footer.calendar': 'Events calendar', 'footer.join': 'Get involved',
      'footer.become': 'Become a member', 'footer.rules': 'Track regulations', 'footer.sponsor': 'Sponsorship', 'footer.sponsors.title': 'With thanks to our sponsors', 'guest.tag': 'Guest riding',
    'hero.label': 'RC045', 'hero.title': 'Track Regulations',
    'hero.sub': 'These regulations were created to ensure our hobby can be practised safely. By entering the RC045 grounds, you agree to the contents of these regulations.',
    'intro.bold': 'Important:',
    'intro.text': 'These regulations are not open for discussion. Failure to comply may result in a reprimand from the board in any form (in accordance with the notarially recorded statutes).',
    'a1.title': 'Track Opening Hours',
    'a1.b1': 'Wednesday evening from 18:00 until close (subject to sufficient interest and weather conditions)',
    'a1.b2': 'Saturday from 10:00 until close (subject to sufficient interest and weather conditions)',
    'a1.b3': 'Sunday from 10:00 until close',
    'a1.p1': '"Until close" means the RC045 grounds will be closed by the key holder if there is insufficient interest. Closure will also be communicated via the RC045 WhatsApp group.',
    'a2.title': 'Safety',
    'a2.1.title': '2.1 Driver\'s Platform',
    'a2.1.p1': 'Non-drivers are not permitted on the track. Only drivers are allowed on the track (driver\'s platform). Designated persons wearing a vest may be on the track in the role of track marshal. Spectators must stand behind the barrier.',
    'a2.1.p2': 'A driver is defined as a member or guest rider who is actively controlling an RC car at that moment.',
    'a2.2.title': '2.2 Jump Track',
    'a2.2.p1': 'Only designated persons may stand around the jump ramp. Drivers always stand at the front. If a car is damaged or breaks down, it will be retrieved by a track marshal. When a track marshal gives a signal or begins to move, everyone must stop driving.',
    'a2.3.title': '2.3 Crawler Track',
    'a2.3.p1': 'Drivers may walk alongside the crawler course. Spectators must keep a safe distance. When a driver indicates that someone needs to move aside or step away from an obstacle, this must be respected.',
    'a2.3.p2': 'The crawler track is primarily accessible to members and guest riders only. Entering the crawler track without permission from the board is prohibited. Only crawler vehicles may participate on the crawler track. Kraton, X-Max and similar vehicles are not permitted here.',
    'a2.4.title': '2.4 Batteries and Equipment',
    'a2.4.p1': 'Vehicles must be in good technical working order and everything must be securely fastened. Batteries are charged outside only, not in the canteen or on the driver\'s platform. Charging equipment must be safe and fitted with a properly functioning plug and connector.',
    'a2.5.title': '2.5 Riding on Asphalt',
    'a2.5.p1': 'It is not permitted to ride on the asphalt outside the designated track area. Near the fence, ride carefully with safety as the top priority (only directly from the track to the work table or vice versa).',
    'a2.6.title': '2.6 Liability',
    'a2.6.p1': 'Entering the RC045 grounds is at your own risk. We are not liable for theft or other damage to vehicles or equipment. This also applies to damage caused between participants on and around the tracks.',
    'a2.7.title': '2.7 Responsibility for Guests',
    'a2.7.p1': 'Each member is responsible for the behaviour and actions of their guests and/or visitors.',
    'a3.title': 'Guest Riders',
    'a3.p1': 'A guest rider must always check in with a board member or key holder. The track rules must be discussed with the guest rider. Every club member must ensure that a guest rider is informed of the applicable rules. Guest riders will receive a copy of the regulations.',
    'a4.title': 'Track Marshal',
    'a4.p1': 'The designated track marshal is recognisable by an orange vest and has authority when on the track. This means that everything they say must be followed. Stopping a race must be complied with immediately.',
    'a4.p2': 'There may be multiple track marshals. It is a rotating role and every member will take on this responsibility.',
    'a5.title': 'Driving and Fuel Use',
    'a5.p1': 'Nitro and petrol are not permitted. Only electrically powered vehicles are welcome on our grounds.',
    'a6.title': 'Narcotics',
    'a6.p1': 'The use of narcotics on and around the track is prohibited.',
    'a7.title': 'Smoking and Alcohol',
    'a7.p1': 'Smoking and drinking on the track are not permitted. Alcoholic beverages are prohibited on the entire grounds during opening hours.',
    'a8.title': 'Tidying Up',
    'a8.1.title': '8.1 Keeping the Grounds Tidy',
    'a8.1.p1': 'Every member and guest rider is obliged to help clean up and keep our grounds tidy. Litter must be avoided and we all clean it up together when needed. Waste is separated into the designated bins and bags. The canteen is cleaned up by everyone.',
    'a8.2.title': '8.2 Chemical Waste',
    'a8.2.p1': 'Every rider is obliged to take environmentally hazardous materials home and dispose of them as chemical waste themselves (e.g. a defective battery).',
    'a9.title': 'GDPR',
    'a9.p1': 'By entering the RC045 grounds, you consent to the sharing of photos and videos on social media.',
    'a10.title': 'The Board',
    'a10.p1': 'If a dispute arises regarding the above rules, it must be discussed with the board. A decision by the board is binding and not open to discussion. Any changes will be communicated via the newsletter or at a general meeting.'
  },
  de: {
    'nav.about': 'Über uns', 'nav.membership': 'Mitgliedschaft', 'nav.track': 'Die Strecke',
    'nav.location': 'Standort', 'nav.photobook': 'Fotobuch', 'nav.contact': 'Kontakt', 'nav.join': 'Mitglied werden', 'footer.credit': 'Website von',
      'footer.brand': 'Ein freundlicher Verein für Liebhaber von elektrisch angetriebenen RC-Autos in der Region Südlimburg. Für Anfänger und erfahrene Fahrer.',
      'footer.nav': 'Navigation', 'footer.origin': 'Unsere Geschichte', 'footer.media': 'Medien', 'footer.photobook': 'Fotobuch', 'footer.calendar': 'Veranstaltungskalender', 'footer.join': 'Mitmachen',
      'footer.become': 'Mitglied werden', 'footer.rules': 'Streckenreglement', 'footer.sponsor': 'Sponsoring', 'footer.sponsors.title': 'Mit Dank an unsere Sponsoren', 'guest.tag': 'Gastfahren',
    'hero.label': 'RC045', 'hero.title': 'Streckenreglement',
    'hero.sub': 'Dieses Reglement wurde erstellt, um unser Hobby sicher ausüben zu können. Mit dem Betreten des RC045-Geländes stimmst du dem Inhalt dieses Reglements zu.',
    'intro.bold': 'Wichtig:',
    'intro.text': 'Über den Inhalt dieses Reglements wird nicht diskutiert. Die Nichteinhaltung dieser Regeln kann zu einer Abmahnung durch den Vorstand jeglicher Art führen (gemäß den notariell festgelegten Statuten).',
    'a1.title': 'Öffnungszeiten der Strecke',
    'a1.b1': 'Mittwochabend ab 18:00 Uhr bis Ende (bei ausreichend Interesse und abhängig vom Wetter)',
    'a1.b2': 'Samstag ab 10:00 Uhr bis Ende (bei ausreichend Interesse und abhängig vom Wetter)',
    'a1.b3': 'Sonntag ab 10:00 Uhr bis Ende',
    'a1.p1': '"Bis Ende" bedeutet, dass das RC045-Gelände bei unzureichendem Interesse vom Schlüsselträger geschlossen wird. Die Schließung des RC045-Geländes wird auch über die WhatsApp-Gruppe von RC045 kommuniziert.',
    'a2.title': 'Sicherheit',
    'a2.1.title': '2.1 Fahrerpodium',
    'a2.1.p1': 'Es ist Nicht-Fahrern verboten, sich auf der Strecke aufzuhalten. Nur Fahrer befinden sich auf der Strecke (Fahrerpodium). Ausgewiesene Personen mit einer Weste dürfen als Streckenmarschall auf der Strecke sein. Zuschauer müssen hinter der Absperrung stehen.',
    'a2.1.p2': 'Als Fahrer gilt ein Mitglied oder Gastfahrer, der zu diesem Zeitpunkt ein RC-Auto steuert.',
    'a2.2.title': '2.2 Sprungstrecke',
    'a2.2.p1': 'Bei der Sprungstrecke dürfen nur ausgewiesene Personen rund um die Rampe stehen. Fahrer stehen immer an der Vorderseite. Wenn ein Auto beschädigt wird oder kaputt geht, wird es von einem Streckenmarschall abgeholt. Wenn ein Streckenmarschall ein Signal gibt oder sich bewegt, hört jeder mit dem Fahren auf.',
    'a2.3.title': '2.3 Crawler-Strecke',
    'a2.3.p1': 'Auf und an der Crawler-Strecke dürfen Fahrer entlang des Parcours laufen. Zuschauer halten einen angemessenen Abstand. Wenn der Fahrer angibt, dass jemand zur Seite treten oder von einem Hindernis absteigen soll, muss dem Folge geleistet werden.',
    'a2.3.p2': 'Die Crawler-Strecke ist hauptsächlich nur für Mitglieder und Gastfahrer zugänglich. Das Betreten der Crawler-Strecke ohne Genehmigung des Vorstands ist verboten. Auf der Crawler-Strecke dürfen nur Crawler-Fahrzeuge teilnehmen. Kraton, X-Max und ähnliche sind hier nicht erlaubt.',
    'a2.4.title': '2.4 Akkus und Ausrüstung',
    'a2.4.p1': 'Fahrzeuge müssen technisch einwandfrei funktionieren und alles muss fest sitzen. Akkus werden nur draußen geladen, nicht in der Kantine oder auf dem Fahrerpodium. Ladegeräte sind sicher und mit einem einwandfrei funktionierenden Stecker und Steckermaterial ausgestattet.',
    'a2.5.title': '2.5 Fahren auf Asphalt',
    'a2.5.p1': 'Es ist nicht erlaubt, außerhalb der Strecke auf dem Asphalt zu fahren. Vor dem Zaun dosiert fahren mit Sicherheit an erster Stelle (nur direkt von der Strecke zum Werktisch oder umgekehrt).',
    'a2.6.title': '2.6 Haftung',
    'a2.6.p1': 'Das Betreten des RC045-Geländes erfolgt auf eigene Gefahr. Wir haften nicht für Diebstahl oder andere Schäden an z.B. Fahrzeugen oder Material. Dies gilt auch für Schäden, die gegenseitig auf und um die Strecken verursacht werden.',
    'a2.7.title': '2.7 Verantwortung für Gäste',
    'a2.7.p1': 'Jedes Mitglied ist verantwortlich für das Verhalten und die Handlungen seiner Gäste und/oder Besucher.',
    'a3.title': 'Gastfahrer',
    'a3.p1': 'Ein Gastfahrer muss sich immer bei einem Vorstandsmitglied oder Schlüsselträger anmelden. Die Streckenregeln müssen mit dem Gastfahrer besprochen werden. Jedes Vereinsmitglied muss dafür sorgen, dass ein Gastfahrer über die geltenden Regeln informiert ist. Gastfahrer erhalten das Reglement.',
    'a4.title': 'Streckenmarschall',
    'a4.p1': 'Der ausgewiesene Streckenmarschall ist an einer orangenen Weste erkennbar und hat die Leitung, wenn er sich auf der Strecke befindet. Das bedeutet, dass alles, was er sagt, ausgeführt werden muss. Das Stoppen eines Rennens muss sofort befolgt werden.',
    'a4.p2': 'Es kann mehrere Streckenmarschälle geben. Es ist eine rotierende Aufgabe und jedes Mitglied wird seine Rolle darin übernehmen.',
    'a5.title': 'Fahren und Kraftstoffnutzung',
    'a5.p1': 'Nitro und Benzin sind nicht erlaubt. Nur elektrisch angetriebene Fahrzeuge sind auf unserem Gelände willkommen.',
    'a6.title': 'Betäubungsmittel',
    'a6.p1': 'Es ist verboten, auf und um die Strecke Betäubungsmittel zu verwenden.',
    'a7.title': 'Rauchen und Alkohol',
    'a7.p1': 'Es ist nicht erlaubt, auf der Strecke zu rauchen oder zu trinken. Alkoholische Getränke sind während der Öffnungszeiten auf dem gesamten Gelände verboten.',
    'a8.title': 'Aufräumen',
    'a8.1.title': '8.1 Gelände sauber halten',
    'a8.1.p1': 'Jedes Mitglied und jeder Gastfahrer verpflichtet sich, unser Gelände aufzuräumen und sauber zu halten. Müll muss vermieden werden; wenn nötig, räumen wir ihn gemeinsam auf. Abfall wird in die dafür vorgesehenen Müllbehälter und Säcke getrennt. Die Kantine wird von allen aufgeräumt und gereinigt.',
    'a8.2.title': '8.2 Chemischer Abfall',
    'a8.2.p1': 'Jeder Fahrer ist verpflichtet, umweltbelastende Materialien mit nach Hause zu nehmen und selbst als Sondermüll zu entsorgen (z.B. ein defekter Akku).',
    'a9.title': 'DSGVO',
    'a9.p1': 'Mit dem Betreten des RC045-Geländes stimmst du der Veröffentlichung von Fotos und Videos in sozialen Medien zu.',
    'a10.title': 'Der Vorstand',
    'a10.p1': 'Wenn ein Streit über die oben genannten Regeln entsteht, muss dieser mit dem Vorstand besprochen werden. Eine Entscheidung des Vorstands ist bindend und nicht diskutierbar. Etwaige Änderungen werden über den Newsletter oder in einer Hauptversammlung kommuniziert.'
  }
};

  

  let currentLang = getInitialLang();

function setLang(lang) {
  currentLang = lang;
  const t = i18n[lang];
  document.querySelectorAll('[data-i18n]').forEach(el => {
    const key = el.getAttribute('data-i18n');
    if (t[key]) el.textContent = t[key];
  });
  document.querySelectorAll('.lang-flag').forEach(btn => { btn.classList.remove('active'); btn.setAttribute('aria-pressed', 'false'); });
  const activeBtn = document.querySelector(`.lang-flag[onclick="setLang('${lang}')"]`);
    activeBtn.classList.add('active');
    activeBtn.setAttribute('aria-pressed', 'true');
  document.documentElement.lang = lang;
  localStorage.setItem('rc045_lang', lang);
  const currentUrl = new URL(window.location.href);
  if (lang === 'nl') currentUrl.searchParams.delete('lang');
  else currentUrl.searchParams.set('lang', lang);
  history.replaceState(null, '', currentUrl.pathname + currentUrl.search + currentUrl.hash);
  updateInternalLinks(lang);
  renderSponsorCta();
    renderNavFooterTeksten();
  renderBaanreglementTekst();
}

// ===== TEKST BAANREGLEMENT (data/baanreglement.json, bijwerken via beheer.php) =====
// De ondertitel, introtekst en de tien artikelen stonden hiervoor vast in dit
// bestand. Die Nederlandse tekst blijft hier ook als vangnet staan (zichtbaar
// zolang het JSON-bestand leeg is of niet laadt), maar data/baanreglement.json
// overschrijft die zodra het bestaat. Leeg gelaten EN/DE valt terug op de
// Nederlandse tekst. Elk artikel is in beheer één tekstvak: een lege regel
// erin wordt hier weer als aparte alinea getoond (de losse bullets/
// subartikelen van hiervoor zijn dus samengevoegd tot gewone alinea's).
var baanreglementData = null;
var baanreglementArtikelen = [2, 3, 4, 5, 6, 7, 8, 9, 10];
function renderBaanreglementTekst() {
  if (!baanreglementData) return;
  function tekstVoor(veld) {
    var bron = baanreglementData[veld];
    if (!bron) return '';
    var t = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
    return t;
  }
  function zetTekst(id, veld) {
    var tekst = tekstVoor(veld);
    if (!tekst) return;
    var el = document.getElementById(id);
    if (el) el.textContent = tekst;
  }
  zetTekst('br-hero-sub', 'hero_sub');
  zetTekst('br-intro-bold', 'intro_bold');
  zetTekst('br-intro-text', 'intro_text');
  zetTekst('br-a1-title', 'a1_title');

  // Artikel 1 wordt net als de overige artikelen volledig vanuit Beheer geladen.
  var artikel1Body = document.getElementById('br-a1-text');
  var artikel1Tekst = tekstVoor('a1_body');
  if (artikel1Body && artikel1Tekst) {
    artikel1Body.innerHTML = '';
    artikel1Tekst.split(/\n\s*\n/).map(function (a) { return a.trim(); }).filter(Boolean).forEach(function (alinea) {
      var paragraaf = document.createElement('p');
      paragraaf.textContent = alinea;
      artikel1Body.appendChild(paragraaf);
    });
  }

  baanreglementArtikelen.forEach(function (n) {
    zetTekst('br-a' + n + '-title', 'a' + n + '_title');
    var tekst = tekstVoor('a' + n + '_body');
    if (!tekst) return;
    var body = document.getElementById('br-a' + n + '-body');
    if (!body) return;
    var alineas = tekst.split(/\n\s*\n/).map(function (a) { return a.trim(); }).filter(Boolean);
    if (!alineas.length) return;
    body.innerHTML = '';
    alineas.forEach(function (alinea) {
      var p = document.createElement('p');
      p.textContent = alinea;
      body.appendChild(p);
    });
  });
}
fetch('data/baanreglement.json', { cache: 'no-store' })
  .then(function (r) { return r.ok ? r.json() : null; })
  .then(function (d) {
    if (!d) return;
    baanreglementData = d;
    renderBaanreglementTekst();
  })
  .catch(function () {});

document.getElementById('footer-year').textContent = new Date().getFullYear();

// Pas opgeslagen/URL-taal direct toe en update links bij eerste load
if (currentLang !== 'nl') setLang(currentLang);
else updateInternalLinks('nl');

const heroBg = document.getElementById('hero-bg');
window.addEventListener('scroll', function() {
  const scrollY = window.scrollY;
  if (scrollY < window.innerHeight * 1.2) heroBg.style.transform = `translateY(${scrollY * 0.5}px)`;
}, { passive: true });</script>

</body>
</html>
