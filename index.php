<?php
require_once __DIR__ . '/app/content/seo-head.php';
require_once __DIR__ . '/app/content/tenant-homepage.php';
tenantHomepageStartOutputFilter();
?><!DOCTYPE html>
<html lang="<?php echo rc045Taal(); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead('index'); ?>
  <link rel="icon" type="image/x-icon" href="favicon.ico">
  <link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
  <link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="48x48" href="favicon-48x48.png">
  <link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
  <link rel="manifest" href="site.webmanifest">
  <meta name="theme-color" content="#1E2C13">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="styles.css">
  <link rel="stylesheet" href="csp205-index-6c81cdcc5da6.css">
  <script type="application/ld+json" id="structured-data">
  {
    "@context": "https://schema.org",
    "@type": "SportsClub",
    "name": "RC045 – Bashers of the South",
    "alternateName": "RC045",
    "description": "Een gezellige vereniging in Zuid-Limburg voor liefhebbers van elektrisch aangedreven, radiografisch bestuurbare auto's. Voor beginners en ervaren hobbyisten, jong en oud.",
    "url": "https://rc045.nl",
    "logo": "https://rc045.nl/rc045-logo.png",
    "image": "https://rc045.nl/rc045-logo.png",
    "address": {
      "@type": "PostalAddress",
      "streetAddress": "Wijngaardsberg 26",
      "postalCode": "6464 EZ",
      "addressLocality": "Eygelshoven",
      "addressRegion": "Limburg",
      "addressCountry": "NL"
    },
    "geo": {
      "@type": "GeoCoordinates",
      "latitude": 50.889462,
      "longitude": 6.071899
    },
    "openingHoursSpecification": [
      {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": "Saturday",
        "opens": "11:00",
        "closes": "15:00"
      },
      {
        "@type": "OpeningHoursSpecification",
        "dayOfWeek": "Sunday",
        "opens": "10:00",
        "closes": "17:00"
      }
    ],
    "sameAs": [
      "https://www.facebook.com/rc045/"
    ],
    "email": "bestuur@rc045.nl"
  }
  </script>
  <script data-goatcounter="https://rc045.goatcounter.com/count"
        async src="//gc.zgo.at/count.js"></script>
</head>
<body>
<div id="testsite-banner" role="status" style="position:fixed;top:8px;left:50%;transform:translateX(-50%);z-index:99999;background:#b42318;color:#fff;padding:5px 12px;border-radius:999px;font:700 12px/1.2 Arial,sans-serif;letter-spacing:.08em;box-shadow:0 2px 8px rgba(0,0,0,.25);pointer-events:none">TESTSITE</div>

<a href="#main-content" class="skip-link">Naar hoofdinhoud</a>

<!-- ===== LIGHTBOX ===== -->
<div class="lightbox" id="lightbox">
  <button class="lightbox-close" id="lightbox-close" aria-label="Sluiten">×</button>
  <img src="" alt="" id="lightbox-img">
</div>

<!-- ===== TERUG NAAR BOVEN ===== -->
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Terug naar boven">↑</button>

<!-- ===== NAVIGATION ===== -->
<nav class="nav" id="main-nav">
  <div class="nav-inner">
    <a href="#" class="nav-logo">
      <img width="400" height="423" src="rc045-logo.png" alt="RC045 logo">
      <div>
        <span class="nav-logo-text">RC045</span>
      </div>
    </a>
    <ul class="nav-links" id="nav-links">
      <li data-section="over-ons"><a href="#over-ons" id="nav-about" data-i18n="nav.about">Over ons</a></li>
      <li data-section="lidmaatschap"><a href="#lidmaatschap" id="nav-membership" data-i18n="nav.membership">Lidmaatschap</a></li>
      <li data-section="baan"><a href="#baan" id="nav-track" data-i18n="nav.track">De baan</a></li>
      <li data-section="locatie"><a href="#locatie" id="nav-location" data-i18n="nav.location">Locatie</a></li>
      <li><a href="fotoboek.html" id="nav-photobook" data-i18n="nav.photobook">Fotoboek</a></li>
      <li class="nav-cta" data-section="contact"><a href="#contact" id="nav-contact" data-i18n="nav.contact">Contact</a></li>
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

<!-- ===== MEDEDELING (inhoud komt uit data/actueel.json, bijwerken via beheer.php) ===== -->
<div class="announce-bar" id="announce-bar" style="display:none;">
  <span class="announce-bar-icon" aria-hidden="true">📣</span>
  <span id="announce-text"></span>
</div>



<!-- ===== HERO ===== -->
<section class="hero" id="main-content">
  <div class="hero-bg" id="hero-bg"></div>
  <div class="hero-gradient"></div>
  <img width="400" height="423" src="rc045-logo.png" alt="" aria-hidden="true" style="position:absolute; right:-40px; top:50%; transform:translateY(-50%); height: 520px; width: auto; opacity: 0.13; pointer-events:none; filter: drop-shadow(0 0 40px rgba(200,154,26,0.2)); z-index:1;">
  <div class="hero-content">
    <img width="400" height="423" src="rc045-logo.png" alt="RC045" style="height: 140px; width: auto; margin-bottom: 24px; filter: drop-shadow(0 4px 16px rgba(0,0,0,0.4));">
    <h1>RC045<br><span>BASHERS OF THE SOUTH</span></h1>
    <p id="hp-hero-intro" data-i18n="hero.intro">Wij zijn een gezellige vereniging uit het zuiden van Limburg voor liefhebbers van elektrisch aangedreven, radiografisch bestuurbare auto's. Voor beginners én ervaren hobbyisten. Jong én oud.</p>
    <div class="hero-buttons">
      <a href="aanmelden.html" class="btn btn-primary" id="hp-hero-btn-member" data-i18n="hero.btn.member">Lid worden!</a>
      <a href="#over-ons" class="btn btn-outline" id="hp-hero-btn-more" data-i18n="hero.btn.more">Meer over ons</a>
    </div>
  </div>
</section>

<!-- ===== INFO BAR ===== -->
<div class="info-bar">
  <div class="info-bar-inner">
    <div class="info-item">
      <div class="info-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3A7A77" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      </div>
      <div>
        <div class="info-label" id="hp-info-hours" data-i18n="info.hours">Openingstijden</div>
        <div class="info-value" id="info-sat-value">Zaterdag 11:00 – 15:00</div>
        <div class="info-value" id="info-sun-value">Zondag 10:00 – 17:00</div>
        <div id="status-indicator"></div>
        <div class="info-hours-note" id="info-hours-note">
          <span data-i18n="info.hours.note">Op vrijdag passen we onze actuele openingstijden aan.</span>
        </div>
      </div>
    </div>
    <div class="info-item">
      <div class="info-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3A7A77" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13S3 17 3 10a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div>
        <div class="info-label" id="hp-info-location" data-i18n="info.location">Locatie</div>
        <div class="info-value">
          <a href="#locatie" class="info-location-link"><span id="info-adres-straat">Wijngaardsberg 26</span> <small id="info-adres-plaats">Kerkrade (Eygelshoven)</small></a>
        </div>
      </div>
    </div>
    <div class="info-item">
      <div class="info-icon">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3A7A77" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      </div>
      <div>
        <div class="info-label" id="hp-info-membership" data-i18n="info.membership">Lidmaatschap</div>
        <div class="info-value" id="info-membership-value">Vanaf €50/jaar</div>
      </div>
    </div>
  </div>
</div>

<!-- ===== NIEUWS ===== -->
<section class="section nieuws" id="nieuws" style="display:none;">
  <div class="container">
    <div class="section-header reveal">
      <div class="section-label" id="hp-nieuws-label" data-i18n="nieuws.label">Nieuws</div>
      <h2 class="section-title" id="hp-nieuws-title" data-i18n="nieuws.title">Laatste updates</h2>
      <p class="section-sub" id="hp-nieuws-sub" data-i18n="nieuws.sub">Het laatste nieuws van RC045.</p>
    </div>
    <div class="nieuws-grid" id="nieuws-grid">
      <!-- Kaarten worden hier ingevuld vanuit data/nieuws.json, bij te werken via beheer.php -->
    </div>
  </div>
</section>

<!-- ===== OVER ONS ===== -->
<section class="section about" id="over-ons">
  <div class="container">
    <div class="about-grid">
      <div class="about-images reveal">
        <img width="2048" height="1152" src="images/crawlergroep.jpg" alt="RC045 leden hun crawlers op een rij" class="about-img-main lightbox-trigger" loading="lazy" decoding="async">
        <img width="1024" height="768" src="images/basherbaaneersteaanleglucht.jpg" alt="Luchtfoto van de RC045 baan" class="about-img-secondary lightbox-trigger" loading="lazy" decoding="async">
      </div>
      <div class="reveal reveal-delay-2">
        <div class="section-label" id="hp-about-label" data-i18n="about.label">Wie zijn wij</div>
        <h2 class="section-title" id="hp-about-title" data-i18n="about.title">Dé RC-vereniging van Zuid-Limburg</h2>
        <p style="color: var(--muted); line-height: 1.8; margin-bottom: 24px;" id="hp-about-p1" data-i18n="about.p1">RC045 is een actieve vereniging voor liefhebbers van radiografisch bestuurbare auto's. We rijden met elektrische RC-auto's in alle schalen. Of je nu net begint of al jaren rijdt: bij ons ben je welkom.</p>
        <p style="color: var(--muted); line-height: 1.8;" id="hp-about-p2" data-i18n="about.p2">We beschikken over een eigen baan in Eygelshoven, op het terrein van Kok Lexmond. Naast de basher baan hebben we ook een enorm crawler-parcours en een jump-track.</p>
        <div class="about-features">
          <div class="feature-card reveal reveal-delay-1">
            <div class="feature-card-icon">⚡</div>
            <h4 id="hp-feat1-title" data-i18n="feat1.title">Alleen elektrisch</h4>
            <p id="hp-feat1-text" data-i18n="feat1.text">Nitro en benzine zijn niet toegestaan. Alle elektrische auto's zijn welkom!</p>
          </div>
          <div class="feature-card reveal reveal-delay-2">
            <div class="feature-card-icon">🏔️</div>
            <h4 id="hp-feat2-title" data-i18n="feat2.title">Crawler-baan</h4>
            <p id="hp-feat2-text" data-i18n="feat2.text">Speciaal terrein voor crawlers en uitdagende obstakels, we breiden ons parcours regelmatig uit.</p>
          </div>
          <div class="feature-card reveal reveal-delay-3">
            <div class="feature-card-icon">🚀</div>
            <h4 id="hp-feat3-title" data-i18n="feat3.title">Jump-track</h4>
            <p id="hp-feat3-text" data-i18n="feat3.text">Volle gas over de schans! Voor wie van actie houdt.</p>
          </div>
          <div class="feature-card reveal reveal-delay-4">
            <div class="feature-card-icon">👨‍👩‍👧</div>
            <h4 id="hp-feat4-title" data-i18n="feat4.title">Voor iedereen</h4>
            <p id="hp-feat4-text" data-i18n="feat4.text">Vanaf 4 jaar is iedereen welkom!</p>
          </div>
        </div>
        <a href="ontstaan.html" class="about-story-link reveal reveal-delay-4" id="hp-about-storylink" data-i18n="about.storylink">Lees het ontstaansverhaal →</a>
        <a href="media.html" class="about-story-link reveal reveal-delay-4" id="hp-about-medialink" data-i18n="about.medialink">RC045 in de media →</a>
      </div>
    </div>
    <div class="about-photos-title reveal" id="hp-about-photos-title" data-i18n="about.photos.title">Crawlerparcours</div>
    <div class="about-photos-grid reveal">
      <div class="about-photo-wrap">
        <img width="1160" height="2048" src="images/crawlercollage.jpg" alt="RC045 crawler collage" class="about-photo lightbox-trigger" loading="lazy" decoding="async">
      </div>
      <div class="about-photo-wrap">
        <img width="1206" height="1171" src="images/crawlercollage2.jpg" alt="RC045 crawler collage" class="about-photo lightbox-trigger" loading="lazy" decoding="async">
      </div>
      <div class="about-photo-wrap">
        <img width="1206" height="1191" src="images/crawlercollage3.jpg" alt="RC045 crawler collage" class="about-photo lightbox-trigger" loading="lazy" decoding="async">
      </div>
      <div class="about-photo-wrap">
        <img width="1206" height="1204" src="images/crawlercollage4.jpg" alt="RC045 crawler collage" class="about-photo lightbox-trigger" loading="lazy" decoding="async">
      </div>
    </div>
  </div>
</section>

<!-- ===== LIDMAATSCHAP & GASTRIJDEN ===== -->
<section class="section pricing" id="lidmaatschap">
  <div class="container">
    <div class="section-header center reveal">
      <div class="section-label" data-i18n="pricing.label">Meedoen</div>
      <h2 class="section-title" id="hp-pricing-title" data-i18n="pricing.title">Lid worden of een keer komen kijken?</h2>
      <p class="section-sub" id="hp-pricing-sub" data-i18n="pricing.sub">Je kunt altijd eerst als gast komen rijden om te ervaren of het iets voor jou is. Daarna kun je eventueel lid worden en volop genieten van onze banen.</p>
    </div>
    <div class="pricing-grid">
      <div class="price-card reveal reveal-delay-1">
        <div class="price-card-tag" id="hp-guest-tag" data-i18n="guest.tag">Gastrijden</div>
        <h3 id="hp-guest-title" data-i18n="guest.title">Kom eens gastrijden!</h3>
        <p style="font-size: 14px; color: var(--muted); margin-top: 8px; line-height: 1.6;" id="hp-guest-text" data-i18n="guest.text">Rij een hele dag mee op onze baan zonder lidmaatschap. Check onze openingstijden en kom gewoon langs, meld je wel even bij een (bestuurs)lid als je er bent!</p>
        <ul class="price-list">
          <li><span id="hp-guest-adult" data-i18n="guest.adult">Volwassene (16+)</span><span class="price-amount">€10</span></li>
          <li><span id="hp-guest-youth" data-i18n="guest.youth">Jeugd (t/m 15 jaar)</span><span class="price-amount">€5</span></li>
          <li><span id="hp-guest-group" data-i18n="guest.group">Groepen krijgen korting!</span><span class="price-amount">%</span></li>
        </ul>
        <ul class="price-notes" id="hp-guest-notes">
          <li>Kom je met 4 of meer personen? Meld je dan van te voren via het <a href="#contact">contactformulier</a> of <a href="mailto:bestuur@rc045.nl">bestuur@rc045.nl</a></li>
          <li>Begeleiding door ouder/verzorger verplicht voor -16 jaar.</li>
          <li>Tijdens besloten- of ledenevenementen is gastrijden niet mogelijk.</li>
        </ul>
        <a href="#contact" class="btn btn-primary" id="hp-guest-btn" data-i18n="guest.btn">Stuur ons een berichtje →</a>
      </div>
      <div class="price-card featured reveal reveal-delay-2">
        <div class="price-card-tag" id="hp-member-tag" data-i18n="member.tag">Lidmaatschap</div>
        <h3 id="hp-member-title" data-i18n="member.title">Word lid van RC045</h3>
        <p style="font-size: 14px; color: rgba(255,255,255,0.6); margin-top: 8px; line-height: 1.6;" id="hp-member-text" data-i18n="member.text">Onbeperkt rijden op alle banen, toegang tot de groepsapp, kennis delen met medehobbyisten en altijd iemand om je mee te helpen.</p>
        <ul class="price-list">
          <li><span id="hp-member-youth" data-i18n="member.youth">Jeugdlid (t/m 15 jaar)</span><span class="price-amount" id="prijs-jeugd">€50/jaar</span></li>
          <li><span id="hp-member-senior" data-i18n="member.senior">Seniorlid (16+)</span><span class="price-amount" id="prijs-senior">€100/jaar</span></li>
          <li><span id="hp-member-fee" data-i18n="member.fee">Eenmalige inschrijfkosten</span><span class="price-amount" id="prijs-inschrijf">€10</span></li>
        </ul>
        <ul class="price-notes" id="hp-member-notes">
          <li>Contributie pro-rata: je betaalt alleen voor de resterende maanden van het jaar.</li>
        </ul>
        <a href="aanmelden.html" class="btn btn-white" id="hp-member-btn" data-i18n="member.btn">Ik wil graag lid worden! →</a>
      </div>
    </div>
  </div>
</section>

<!-- ===== DE BAAN ===== -->
<section class="section track" id="baan">
  <div class="container">
    <div class="track-layout">
      <div class="reveal">
        <div class="section-label" id="hp-track-label" data-i18n="track.label">Onze locatie</div>
        <h2 class="section-title" id="hp-track-title" data-i18n="track.title">De baan in Eygelshoven</h2>
        <p style="color: var(--muted); line-height: 1.8; margin-bottom: 28px;" id="hp-track-p1" data-i18n="track.p1">Ons terrein bevindt zich op het perceel van Kok Lexmond in Eygelshoven (Kerkrade). We beschikken over meerdere banen: een race-circuit, een crawler-parcours, en een jump-track voor de echte thrill-seekers.</p>
        <p style="color: var(--muted); line-height: 1.8; margin-bottom: 28px;" id="hp-track-p2" data-i18n="track.p2">Volg bij aankomst de pijlen met het RC045-logo en je ziet ons vanzelf. Er is voldoende gratis parkeergelegenheid.</p>
        <ul style="list-style:none; display:flex; flex-direction:column; gap:12px;">
          <li style="display:flex; align-items:center; gap:10px; font-size:15px;"><span style="color:var(--green); font-size:18px;">✓</span><span id="hp-track-f1" data-i18n="track.f1">Race-circuit voor buggy's, truggies en meer</span></li>
          <li style="display:flex; align-items:center; gap:10px; font-size:15px;"><span style="color:var(--green); font-size:18px;">✓</span><span id="hp-track-f2" data-i18n="track.f2">Off-road crawler-parcours</span></li>
          <li style="display:flex; align-items:center; gap:10px; font-size:15px;"><span style="color:var(--green); font-size:18px;">✓</span><span id="hp-track-f3" data-i18n="track.f3">Jump-track met schans</span></li>
          <li style="display:flex; align-items:center; gap:10px; font-size:15px;"><span style="color:var(--green); font-size:18px;">✓</span><span id="hp-track-f4" data-i18n="track.f4">Kantine & werkruimte aanwezig</span></li>
          <li style="display:flex; align-items:center; gap:10px; font-size:15px;"><span style="color:var(--green); font-size:18px;">✓</span><span id="hp-track-f5" data-i18n="track.f5">Voldoende parkeerruimte</span></li>
        </ul>
      </div>
      <div class="reveal reveal-delay-2">
        <div class="track-grid">
          <div class="track-photo-wrap tall">
            <img width="1920" height="1080" src="images/crawlergroen.jpg" alt="RC045 crawler in actie op het parcours" class="track-photo lightbox-trigger" loading="lazy" decoding="async">
          </div>
          <div class="track-photo-wrap">
            <img width="1536" height="2048" src="images/crawlerblauw.jpg" alt="RC045 Ford crawler op het podium" class="track-photo lightbox-trigger" loading="lazy" decoding="async">
          </div>
          <div class="track-photo-wrap">
            <img width="1500" height="844" src="images/crawlerobstakel.jpg" alt="RC045 Bronco crawler op het obstakelparcours" class="track-photo lightbox-trigger" loading="lazy" decoding="async">
          </div>
        </div>
        <div class="track-grid">
          <div class="track-photo-wrap tall">
            <img width="2048" height="2048" src="images/basherjump.jpg" alt="RC045 auto over de jump" class="track-photo lightbox-trigger" loading="lazy" decoding="async">
          </div>
          <div class="track-photo-wrap">
            <img width="2048" height="2048" src="images/basherbocht2.jpg" alt="RC045 auto in de bocht" class="track-photo lightbox-trigger" loading="lazy" decoding="async">
          </div>
          <div class="track-photo-wrap">
            <img width="2048" height="1151" src="images/basherjumpen.jpg" alt="RC045 auto's springen over de jump" class="track-photo lightbox-trigger" loading="lazy" decoding="async">
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== FOTOSTROOK CAROUSEL ===== -->
<section class="photo-strip reveal">
  <div class="carousel" id="photo-carousel">
    <div class="carousel-slide active">
      <div class="carousel-slide-bg" data-bg="images/crawlerbrug.jpg"></div>
      <img width="2048" height="1365" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlerbrug.jpg" alt="RC045 Defender crawler op de touwbrug" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlervlag.jpg"></div>
      <img width="2048" height="1365" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlervlag.jpg" alt="RC045 crawler met vlag" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlerduo.jpg"></div>
      <img width="2048" height="1536" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlerduo.jpg" alt="RC045 crawlers samen" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlervijver.jpg"></div>
      <img width="1200" height="1600" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlervijver.jpg" alt="RC045 crawler bij de vijver" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/basherbocht.jpg"></div>
      <img width="2048" height="2048" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/basherbocht.jpg" alt="RC045 baan, de bocht" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/basherbocht3.jpg"></div>
      <img width="2048" height="2048" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/basherbocht3.jpg" alt="RC045 baan, de bocht" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlerblauw2.jpg"></div>
      <img width="1825" height="1258" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlerblauw2.jpg" alt="RC045 crawler in actie" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlercamel.jpg"></div>
      <img width="1206" height="1599" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlercamel.jpg" alt="RC045 crawler op het parcours" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlerfile.jpg"></div>
      <img width="2048" height="1536" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlerfile.jpg" alt="RC045 crawlers op een rij" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlergrijs.jpg"></div>
      <img width="1141" height="1557" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlergrijs.jpg" alt="RC045 grijze crawler" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlergroep2.jpg"></div>
      <img width="2048" height="1170" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlergroep2.jpg" alt="RC045 leden bij elkaar met hun crawlers" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlerheuvel.jpg"></div>
      <img width="2048" height="1152" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlerheuvel.jpg" alt="RC045 crawler op de heuvel" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlerjeep.jpg"></div>
      <img width="1206" height="898" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlerjeep.jpg" alt="RC045 crawler jeep" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlerrood.jpg"></div>
      <img width="2048" height="2048" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlerrood.jpg" alt="RC045 rode crawler" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlersamen.jpg"></div>
      <img width="1206" height="1593" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlersamen.jpg" alt="RC045 leden samen met hun crawlers" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/crawlersneeuw.jpg"></div>
      <img width="923" height="2048" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/crawlersneeuw.jpg" alt="RC045 crawler in de sneeuw" class="carousel-img" decoding="async">
    </div>
    <div class="carousel-slide">
      <div class="carousel-slide-bg" data-bg="images/rc045kerst.jpg"></div>
      <img width="2048" height="1536" src="data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==" data-src="images/rc045kerst.jpg" alt="RC045 kerstsfeer" class="carousel-img" decoding="async">
    </div>
    <button class="carousel-arrow carousel-prev" aria-label="Vorige foto">‹</button>
    <button class="carousel-arrow carousel-next" aria-label="Volgende foto">›</button>
    <div class="carousel-dots">
      <button class="carousel-dot active" data-index="0" aria-label="Foto 1"></button>
      <button class="carousel-dot" data-index="1" aria-label="Foto 2"></button>
      <button class="carousel-dot" data-index="2" aria-label="Foto 3"></button>
      <button class="carousel-dot" data-index="3" aria-label="Foto 4"></button>
      <button class="carousel-dot" data-index="4" aria-label="Foto 5"></button>
      <button class="carousel-dot" data-index="5" aria-label="Foto 6"></button>
      <button class="carousel-dot" data-index="6" aria-label="Foto 7"></button>
      <button class="carousel-dot" data-index="7" aria-label="Foto 8"></button>
      <button class="carousel-dot" data-index="8" aria-label="Foto 9"></button>
      <button class="carousel-dot" data-index="9" aria-label="Foto 10"></button>
      <button class="carousel-dot" data-index="10" aria-label="Foto 11"></button>
      <button class="carousel-dot" data-index="11" aria-label="Foto 12"></button>
      <button class="carousel-dot" data-index="12" aria-label="Foto 13"></button>
      <button class="carousel-dot" data-index="13" aria-label="Foto 14"></button>
      <button class="carousel-dot" data-index="14" aria-label="Foto 15"></button>
      <button class="carousel-dot" data-index="15" aria-label="Foto 16"></button>
      <button class="carousel-dot" data-index="16" aria-label="Foto 17"></button>
    </div>
  </div>
</section>

<!-- ===== ACTIVITEITEN ===== -->
<section class="section agenda" id="activiteiten">
  <div class="container">
    <div class="section-header reveal">
      <div class="section-label" id="hp-agenda-label" data-i18n="agenda.label">Agenda</div>
      <h2 class="section-title" id="hp-agenda-title" data-i18n="agenda.title">Activiteiten</h2>
      <p class="section-sub" id="hp-agenda-sub" data-i18n="agenda.sub">Kijk hier wat er op de planning staat bij RC045. Check onze Facebook-pagina voor de meest actuele informatie.</p>
    </div>
    <div class="agenda-grid" id="agenda-grid">
      <!-- Kaarten worden hier ingevuld vanuit data/agenda.json, bij te werken via beheer.php -->
    </div>
  </div>
</section>

<!-- ===== BAANREGLEMENT ===== -->
<section class="section rules">
  <div class="container">
    <div class="section-header reveal">
      <div class="section-label" id="hp-rules-label" data-i18n="rules.label">Reglement</div>
      <h2 class="section-title" id="hp-rules-title" data-i18n="rules.title">Veiligheid staat voorop</h2>
      <p class="section-sub" id="hp-rules-sub" data-i18n="rules.sub">We hebben duidelijke regels zodat iedereen veilig en met plezier kan rijden. Hieronder lees je de belangrijkste punten.</p>
    </div>
    <div class="rules-grid">
      <div class="rule-card reveal reveal-delay-1"><div class="rule-card-num">01</div><h4 id="hp-rule1-title" data-i18n="rule1.title">Alleen elektrisch</h4><p id="hp-rule1-text" data-i18n="rule1.text">Nitro en benzine zijn niet toegestaan op ons terrein. Alleen elektrisch aangedreven voertuigen zijn welkom.</p></div>
      <div class="rule-card reveal reveal-delay-2"><div class="rule-card-num">02</div><h4 id="hp-rule2-title" data-i18n="rule2.title">Veiligheid baan</h4><p id="hp-rule2-text" data-i18n="rule2.text">Alleen rijders mogen zich op het rijderspodium begeven. Kijken doe je achter het hek. De baanmeester (oranje hesje) bepaalt of er gereden mag worden.</p></div>
      <div class="rule-card reveal reveal-delay-3"><div class="rule-card-num">03</div><h4 id="hp-rule3-title" data-i18n="rule3.title">Gastrijders</h4><p id="hp-rule3-text" data-i18n="rule3.text">Aanmelden bij een bestuurslid verplicht. Onder 16 jaar altijd begeleid door ouder/verzorger.</p></div>
      <div class="rule-card reveal reveal-delay-1"><div class="rule-card-num">04</div><h4 id="hp-rule4-title" data-i18n="rule4.title">Laden van accu's</h4><p id="hp-rule4-text" data-i18n="rule4.text">Accu's laden we alleen buiten, bij de daarvoor bestemde laadplek te herkennen aan het laadpaal-bord. Defecte accu's mag je niet weggooien in onze emmers, neem ze mee naar huis en voer ze zelf af.</p></div>
      <div class="rule-card reveal reveal-delay-2"><div class="rule-card-num">05</div><h4 id="hp-rule5-title" data-i18n="rule5.title">Opgeruimd staat netjes</h4><p id="hp-rule5-text" data-i18n="rule5.text">Ieder lid ruimt mee op. Afval scheiden we in de daarvoor aangewezen bakken. De kantine laten we schoon achter.</p></div>
      <div class="rule-card reveal reveal-delay-3"><div class="rule-card-num">06</div><h4 id="hp-rule6-title" data-i18n="rule6.title">Geen alcohol of drugs</h4><p id="hp-rule6-text" data-i18n="rule6.text">Alcoholhoudende dranken en verdovende middelen zijn te allen tijde verboden op het gehele terrein.</p></div>
      <div class="rule-card reveal reveal-delay-1"><div class="rule-card-num">07</div><h4 id="hp-rule7-title" data-i18n="rule7.title">We rijden nooit op het asfalt</h4><p id="hp-rule7-text" data-i18n="rule7.text">Het is verboden om te rijden op het asfalt. Van de kantine naar het rijderspodium rijd je stapvoets.</p></div>
    </div>
    <a href="baanreglement.html" class="rules-link" id="hp-rules-link" data-i18n="rules.link">Volledig (statutair) baanreglement lezen →</a>
  </div>
</section>

<!-- ===== LOCATIE ===== -->
<section class="section location" id="locatie">
  <div class="container">
    <div class="section-header reveal">
      <div class="section-label" id="hp-loc-label" data-i18n="loc.label">Bezoek ons</div>
      <h2 class="section-title" id="hp-loc-title" data-i18n="loc.title">Hoe vind je ons?</h2>
    </div>
    <div class="location-grid">
      <div class="location-map reveal">
        <iframe src="https://www.openstreetmap.org/export/embed.html?bbox=6.0694%2C50.8879%2C6.0744%2C50.8909&layer=mapnik&marker=50.889462%2C6.071899" allowfullscreen loading="lazy" title="RC045 locatie"></iframe>
      </div>
      <div class="reveal reveal-delay-2">
        <div class="weather-card">
          <div class="weather-icon-big" id="weather-icon">🌤️</div>
          <div>
            <div class="weather-label" id="hp-info-weather" data-i18n="info.weather">Weer in Eygelshoven</div>
            <div class="weather-temp-big" id="weather-temp">—</div>
            <div class="weather-desc-text" id="weather-desc">Laden...</div>
            <div class="weather-details">
              <div class="weather-detail">💨 <span id="weather-wind">—</span></div>
              <div class="weather-detail">💧 <span id="weather-humid">—</span></div>
            </div>
          </div>
        </div>
        <div class="opening-hours">
          <h3 id="hp-hours-title" data-i18n="hours.title">🕐 Openingstijden</h3>
          <p class="hours-intro-note" data-i18n="info.hours.note">Op vrijdag passen we onze actuele openingstijden aan.</p>
          <div class="actueel-hours" id="actueel-hours">
            <strong id="hp-update-label" data-i18n="update.label">📣 Actueel:</strong> <span class="actueel-hours-text" id="actueel-hours-text"></span>
          </div>
          <div class="hours-row" id="hours-wed-row">
            <span class="hours-day" id="hp-hours-wed" data-i18n="hours.wed">Woensdag</span>
            <span class="hours-time-wrap">
              <span class="hours-time" id="hours-wed-time">19:00 – 22:00</span>
              <span class="hours-closed-note" id="hours-wed-closed">🤝 Woensdag alleen bij voldoende animo</span>
            </span>
          </div>
          <div class="hours-row" id="hours-sat-row">
            <span class="hours-day" id="hp-hours-sat" data-i18n="hours.sat">Zaterdag</span>
            <span class="hours-time-wrap">
              <span class="hours-time" id="hours-sat-time">11:00 – 15:00</span>
              <span class="hours-closed-note" id="hours-sat-closed">⛔ Deze zaterdag gesloten</span>
            </span>
          </div>
          <div class="hours-row" id="hours-sun-row">
            <span class="hours-day" id="hp-hours-sun" data-i18n="hours.sun">Zondag</span>
            <span class="hours-time-wrap">
              <span class="hours-time" id="hours-sun-time">10:00 – 17:00</span>
              <span class="hours-closed-note" id="hours-sun-closed">⛔ Deze zondag gesloten</span>
            </span>
          </div>
          <ul class="hours-note">
            <li><strong id="hp-hours-note-attention" data-i18n="hours.note.attention">Let op:</strong> <span id="hp-hours-note-text" data-i18n="hours.note.text">We zijn de eerste zaterdag of zondag van de maand gesloten wegens onderhoud.</span></li>
            <li id="hp-hours-weather" data-i18n="hours.weather">Bij slecht weer kunnen we besluiten eerder te sluiten of helemaal niet open te gaan.</li>
          </ul>
        </div>
        <div class="address-card">
          <div class="address-card-icon">📍</div>
          <div>
            <h4 id="hp-addr-title" data-i18n="addr.title">Adres</h4>
            <p><span id="addr-straat">Wijngaardsberg 26</span><br><span id="addr-postcode-plaats">6464 EZ Eygelshoven</span><br><br><span id="hp-addr-text" data-i18n="addr.text">Onze baan ligt op het terrein van Kok Lexmond, bij aankomst volg je de pijlen RC045.</span></p>
            <a href="https://www.openstreetmap.org/search?lat=50.889462&lon=6.071899&zoom=19#map=19/50.889461/6.071900" target="_blank" style="display:inline-block; margin-top:12px; color:var(--teal); font-weight:600; font-size:14px;" id="hp-addr-route" data-i18n="addr.route">Routebeschrijving openen →</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===== CONTACT ===== -->
<section class="section contact" id="contact">
  <div class="container">
    <div class="contact-grid">
      <div class="contact-info reveal">
        <div class="section-label" id="hp-contact-label" data-i18n="contact.label">Contact</div>
        <h2 class="section-title" id="hp-contact-title" data-i18n="contact.title">Heb je een vraag?</h2>
        <p id="hp-contact-text" data-i18n="contact.text">Wil je meer weten over een lidmaatschap, gastrijden, eens komen kijken, of heb je gewoon een vraag? Stuur ons een bericht en we reageren zo snel mogelijk.</p>
        <div class="contact-channels">
          <a href="mailto:bestuur@rc045.nl" class="channel" id="contact-email-link">
            <div class="channel-icon">✉️</div>
            <div>
              <div class="channel-label" data-i18n="contact.email.label">E-mail</div>
              <div class="channel-value" id="contact-email-value">bestuur@rc045.nl</div>
            </div>
          </a>
          <a href="https://www.facebook.com/rc045/" target="_blank" class="channel" id="contact-facebook-link">
            <div class="channel-icon"><img src="https://upload.wikimedia.org/wikipedia/commons/b/b9/2023_Facebook_icon.svg" alt="" width="28" height="28" aria-hidden="true" loading="lazy" decoding="async"></div>
            <div>
              <div class="channel-label">Facebook</div>
              <div class="channel-value" id="contact-facebook-value">facebook.com/rc045</div>
            </div>
          </a>
          <div class="channel" style="opacity: 0.4; cursor: default; pointer-events: none;">
            <div class="channel-icon"><img src="https://upload.wikimedia.org/wikipedia/commons/a/a5/Instagram_icon.png" alt="" width="28" height="28" aria-hidden="true" loading="lazy" decoding="async"></div>
            <div>
              <div class="channel-label">Instagram</div>
              <div class="channel-value" id="hp-instagram-soon" data-i18n="instagram.soon">Binnenkort beschikbaar</div>
            </div>
          </div>
        </div>
      </div>
      <form class="contact-form reveal reveal-delay-2" action="https://formspree.io/f/xbdevlzw" method="POST" id="contact-form">
        <div class="hp-field" aria-hidden="true">
          <label for="website">Website</label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>
        <div class="form-group">
          <label for="naam" id="hp-form-name" data-i18n="form.name">Naam *</label>
          <input type="text" id="naam" name="naam" required>
        </div>
        <div class="form-group">
          <label for="email" id="hp-form-email" data-i18n="form.email">E-mailadres</label>
          <input type="email" id="email" name="email">
        </div>
        <div id="email-warning" data-i18n="warn.email" style="display:none; padding:12px 16px; background:#FEF3C7; border-radius:8px; color:#92400E; font-size:14px; font-weight:500;">
          ⚠️ Vul een geldig e-mailadres in (bijv. naam@voorbeeld.nl)
        </div>
        <div class="form-group">
          <label for="telefoon" id="hp-form-phone" data-i18n="form.phone">Telefoonnummer</label>
          <div style="display:flex; gap:8px;">
            <select id="landcode" style="width:110px; flex-shrink:0;">
              <option value="+31">🇳🇱 +31</option>
              <option value="+32">🇧🇪 +32</option>
              <option value="+49">🇩🇪 +49</option>
            </select>
            <input type="tel" id="telefoon" style="flex:1;"><input type="hidden" id="telefoon-combined" name="telefoon">
          </div>
        </div>
        <div id="phone-warning" data-i18n="warn.phone" style="display:none; padding:12px 16px; background:#FEF3C7; border-radius:8px; color:#92400E; font-size:14px; font-weight:500;">
          ⚠️ Vul een geldig telefoonnummer in (minimaal 9 cijfers)
        </div>
        <div id="contact-warning" data-i18n="warn.contact" style="display:none; padding:12px 16px; background:#FEF3C7; border-radius:8px; color:#92400E; font-size:14px; font-weight:500;">
          ⚠️ We hebben een e-mailadres of telefoonnummer van je nodig om contact op te nemen.
        </div>
        <div class="form-group">
          <label for="onderwerp" id="hp-form-subject" data-i18n="form.subject">Onderwerp</label>
          <select id="onderwerp" name="onderwerp">
            <option value="" id="hp-form-select" data-i18n="form.select">Selecteer een onderwerp...</option>
            <option id="hp-form-opt1" data-i18n="form.opt1">Vraag over lidmaatschap</option>
            <option id="hp-form-opt4" data-i18n="form.opt4">Sponsoring</option>
            <option id="hp-form-opt5" data-i18n="form.opt5">Overige vragen</option>
          </select>
        </div>
        <div class="form-group">
          <label for="bericht" id="hp-form-message" data-i18n="form.message">Bericht *</label>
          <textarea id="bericht" name="bericht" data-i18n-placeholder="form.message.ph" placeholder="Schrijf hier je vraag of bericht..." required></textarea>
        </div>
        <div id="form-success" data-i18n="form.success" style="display:none; padding:16px; background:var(--teal-light); border-radius:8px; color:var(--teal-dark); font-weight:600; text-align:center;">
          ✅ Bericht verzonden! We nemen zo snel mogelijk contact op.
        </div>
        <div id="form-error" data-i18n="form.error" style="display:none; padding:16px; background:#FEE2E2; border-radius:8px; color:#DC2626; font-weight:600; text-align:center;">
          ❌ Er ging iets mis. Probeer het opnieuw of mail naar bestuur@rc045.nl
        </div>
        <div class="form-submit">
          <button type="submit" class="btn btn-primary" id="form-btn" data-i18n="form.send">Verstuur bericht →</button>
        </div>
      </form>
    </div>
  </div>
</section>

<!-- ===== FOOTER ===== -->
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
          <li><a href="#over-ons" id="footer-link-about" data-i18n="nav.about">Over ons</a></li>
          <li><a href="ontstaan.html" id="footer-link-origin" data-i18n="footer.origin">Het ontstaan</a></li>
          <li><a href="media.html" id="footer-link-media" data-i18n="footer.media">Media</a></li>
          <li><a href="fotoboek.html" id="footer-link-photobook" data-i18n="footer.photobook">Fotoboek</a></li>
          <li><a href="#activiteiten" id="footer-link-calendar" data-i18n="footer.calendar">Activiteitenkalender</a></li>
        </ul>
      </div>
      <div class="footer-col">
        <h4 id="footer-join-title" data-i18n="footer.join">Meedoen</h4>
        <ul>
          <li><a href="aanmelden.html" id="footer-link-become" data-i18n="footer.become">Lid worden</a></li>
          <li><a href="#lidmaatschap" id="footer-link-guesttag" data-i18n="guest.tag">Gastrijden</a></li>
          <li><a href="baanreglement.html" id="footer-link-rules" data-i18n="footer.rules">Baanreglement</a></li>
          <li><a href="#contact" id="footer-link-sponsor" data-i18n="footer.sponsor">Sponsoring</a></li>
          <li><a href="#contact" id="footer-link-contact" data-i18n="nav.contact">Contact</a></li>
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
<script src="homepage.js"></script>


</body>
</html>
