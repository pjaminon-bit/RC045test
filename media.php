<?php require_once __DIR__ . '/seo-head.php'; ?><!DOCTYPE html>
<html lang="<?php echo rc045Taal(); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead('media'); ?>
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
    
    .page-hero-bg { position: absolute; top: -80px; left: 0; right: 0; bottom: -80px; background-image: url('images/crawlergroep.jpg'); background-size: cover; background-position: center; opacity: 0.35; will-change: transform; }
    
    .main { max-width: 990px; margin: 0 auto; padding: 56px 24px 80px; }
    
    .intro-text { font-size: 16px; color: var(--muted); line-height: 1.8; margin-bottom: 48px; }
    
    .media-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    
    .media-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 22px; box-shadow: var(--shadow); display: flex; align-items: flex-start; gap: 16px; transition: transform 0.2s, box-shadow 0.2s; }
    
    .media-card:hover { transform: translateY(-3px); box-shadow: 0 8px 32px rgba(0,0,0,0.12); }
    
    .media-logo { flex-shrink: 0; width: 44px; height: 44px; border-radius: 10px; background: var(--teal-light); display: flex; align-items: center; justify-content: center; font-size: 20px; }
    
    .media-body { flex: 1; }
    
    .media-date { font-size: 12px; font-weight: 600; color: var(--muted); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 6px; }
    
    .media-source { font-size: 12px; font-weight: 700; color: var(--teal-dark); margin-bottom: 8px; }
    
    .media-title { font-family: 'Poppins', sans-serif; font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
    
    .media-desc { font-size: 14px; color: var(--muted); line-height: 1.7; margin-bottom: 16px; }
    
    .media-link { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: var(--teal-dark); border-bottom: 1.5px solid var(--teal); padding-bottom: 1px; transition: color 0.2s, border-color 0.2s; }
    
    .media-link:hover { color: var(--teal); border-color: var(--teal); }
    
    @media (max-width: 700px) {
      .media-grid { grid-template-columns: 1fr; }
      .media-card { flex-direction: column; gap: 16px; padding: 20px; }
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
      <div><span class="nav-logo-text">RC045</span></div>
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
    <div class="section-label" data-i18n="hero.label">Over ons</div>
    <h1 data-i18n="hero.title">Media</h1>
    <p id="mt-hero-sub" data-i18n="hero.sub">Voordat wij een eigen baan hadden, gaf de media ons veelvuldig aandacht. Hier vind je een overzicht van die berichtgevingen.</p>
  </div>
</div>
<main class="main" id="main-content">
  <div class="media-grid" id="media-grid">
    <!-- Kaarten worden hier ingevuld vanuit data/media.json, bij te werken via beheer.php -->
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
    'nav.location': 'Locatie', 'nav.photobook': 'Fotoboek', 'nav.contact': 'Contact', 'nav.join': 'Lid worden',
    'hero.label': 'Over ons', 'hero.title': 'Media',
    'hero.sub': 'Voordat wij een eigen baan hadden, gaf de media ons veelvuldig aandacht. Hier vind je een overzicht van die berichtgevingen.',
    'footer.brand': 'Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto\'s in de regio Zuid-Limburg. Voor beginners én ervaren rijders.',
    'footer.nav': 'Navigatie', 'footer.origin': 'Het ontstaan', 'footer.media': 'Media', 'footer.photobook': 'Fotoboek',
    'footer.calendar': 'Activiteitenkalender', 'footer.join': 'Meedoen',
    'footer.become': 'Lid worden', 'footer.rules': 'Baanreglement',
    'footer.sponsor': 'Sponsoring',    'footer.sponsors.title': 'Met dank aan onze sponsoren',
    'footer.credit': 'Website door',
    'guest.tag': 'Gastrijden'
  },
  en: {
    'nav.about': 'About us', 'nav.membership': 'Membership', 'nav.track': 'The track',
    'nav.location': 'Location', 'nav.photobook': 'Photo book', 'nav.contact': 'Contact', 'nav.join': 'Become a member',
    'hero.label': 'About us', 'hero.title': 'Media',
    'hero.sub': 'Before we had our own track, the media paid us frequent attention. Here you will find an overview of those features.',
    'footer.brand': 'A friendly club for enthusiasts of electrically powered RC cars in the South Limburg region. For beginners and experienced riders alike.',
    'footer.nav': 'Navigation', 'footer.origin': 'Our history', 'footer.media': 'Media', 'footer.photobook': 'Photo book',
    'footer.calendar': 'Events calendar', 'footer.join': 'Get involved',
    'footer.become': 'Become a member', 'footer.rules': 'Track regulations',
    'footer.sponsor': 'Sponsorship',    'footer.sponsors.title': 'With thanks to our sponsors',
    'footer.credit': 'Website by',
    'guest.tag': 'Guest riding'
  },
  de: {
    'nav.about': 'Über uns', 'nav.membership': 'Mitgliedschaft', 'nav.track': 'Die Strecke',
    'nav.location': 'Standort', 'nav.photobook': 'Fotobuch', 'nav.contact': 'Kontakt', 'nav.join': 'Mitglied werden',
    'hero.label': 'Über uns', 'hero.title': 'Medien',
    'hero.sub': 'Bevor wir eine eigene Strecke hatten, schenkten uns die Medien häufig Aufmerksamkeit. Hier findest du eine Übersicht dieser Berichterstattungen.',
    'footer.brand': 'Ein freundlicher Verein für Liebhaber von elektrisch angetriebenen RC-Autos in der Region Südlimburg. Für Anfänger und erfahrene Fahrer.',
    'footer.nav': 'Navigation', 'footer.origin': 'Unsere Geschichte', 'footer.media': 'Medien', 'footer.photobook': 'Fotobuch',
    'footer.calendar': 'Veranstaltungskalender', 'footer.join': 'Mitmachen',
    'footer.become': 'Mitglied werden', 'footer.rules': 'Streckenreglement',
    'footer.sponsor': 'Sponsoring',    'footer.sponsors.title': 'Mit Dank an unsere Sponsoren',
    'footer.credit': 'Website von',
    'guest.tag': 'Gastfahren'
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
  renderMedia();
  renderSponsorCta();
    renderNavFooterTeksten();
  renderMediaTekst();
}
// ===== ONDERTITEL (data/media-pagina.json, bewerkbaar via beheer.php) =====
var mediaTekstData = null;
function renderMediaTekst() {
  if (!mediaTekstData) return;
  var bron = mediaTekstData.hero_sub;
  if (!bron) return;
  var tekst = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
  if (!tekst) return;
  var el = document.getElementById('mt-hero-sub');
  if (el) el.textContent = tekst;
}
fetch('data/media-pagina.json', { cache: 'no-store' })
  .then(function (r) { return r.ok ? r.json() : null; })
  .then(function (d) {
    if (!d) return;
    mediaTekstData = d;
    renderMediaTekst();
  })
  .catch(function () {});
document.getElementById('footer-year').textContent = new Date().getFullYear();
if (currentLang !== 'nl') setLang(currentLang);
else updateInternalLinks('nl');
const heroBg = document.getElementById('hero-bg');
window.addEventListener('scroll', function() {
  const scrollY = window.scrollY;
  if (scrollY < window.innerHeight * 1.2) heroBg.style.transform = `translateY(${scrollY * 0.5}px)`;
}, { passive: true });
// ===== MEDIA / PERSBERICHTEN (data/media.json, bijwerken via beheer.php) =====
  // Nederlands is verplicht per kaart; Engels/Duits vallen terug op Nederlands
  // als het bestuur die niet heeft ingevuld. renderMedia() tekent opnieuw bij
  // elke taalwissel (aangeroepen vanuit setLang), zodat de datumnotatie klopt.
  var mediaMaanden = {
    nl: ['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'],
    en: ['January','February','March','April','May','June','July','August','September','October','November','December'],
    de: ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember']
  };
  function mediaFormatDatum(datumStr) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(datumStr || '');
    if (!m) return '';
    var maanden = mediaMaanden[currentLang] || mediaMaanden.nl;
    var dag = parseInt(m[3], 10);
    var maand = maanden[parseInt(m[2], 10) - 1];
    return currentLang === 'de' ? (dag + '. ' + maand + ' ' + m[1]) : (dag + ' ' + maand + ' ' + m[1]);
  }

  var mediaData = null;
  function renderMedia() {
    if (!mediaData) return;
    var grid = document.getElementById('media-grid');
    if (!grid) return;
    grid.innerHTML = '';

    mediaData.forEach(function(mi) {
      if (!mi) return;
      var titelNl = (mi.title && mi.title.nl) || '';
      if (!titelNl.trim()) return;

      var titelTekst = (mi.title[currentLang] && mi.title[currentLang].trim()) ? mi.title[currentLang] : titelNl;
      var descBron = mi.desc || { nl: '' };
      var descTekst = (descBron[currentLang] && descBron[currentLang].trim()) ? descBron[currentLang] : (descBron.nl || '');
      var linktekstBron = mi.linktekst || { nl: '' };
      var linktekstTekst = (linktekstBron[currentLang] && linktekstBron[currentLang].trim()) ? linktekstBron[currentLang] : (linktekstBron.nl || '');

      var kaart = document.createElement('div');
      kaart.className = 'media-card';

      var logo = document.createElement('div');
      logo.className = 'media-logo';
      logo.textContent = mi.icoon || '📺';

      var body = document.createElement('div');
      body.className = 'media-body';

      var datumEl = document.createElement('div');
      datumEl.className = 'media-date';
      datumEl.textContent = mediaFormatDatum(mi.date);

      var bronEl = document.createElement('div');
      bronEl.className = 'media-source';
      bronEl.textContent = mi.bron || '';

      var titelEl = document.createElement('div');
      titelEl.className = 'media-title';
      titelEl.textContent = titelTekst;

      var descEl = document.createElement('div');
      descEl.className = 'media-desc';
      descEl.textContent = descTekst;

      body.appendChild(datumEl);
      body.appendChild(bronEl);
      body.appendChild(titelEl);
      body.appendChild(descEl);

      if (mi.link) {
        var linkEl = document.createElement('a');
        linkEl.href = mi.link;
        linkEl.target = '_blank';
        linkEl.rel = 'noopener noreferrer';
        linkEl.className = 'media-link';
        linkEl.textContent = linktekstTekst;
        body.appendChild(linkEl);
      }

      kaart.appendChild(logo);
      kaart.appendChild(body);
      grid.appendChild(kaart);
    });
  }

  fetch('data/media.json', { cache: 'no-store' })
    .then(function(r) { return r.ok ? r.json() : []; })
    .then(function(items) {
      mediaData = Array.isArray(items) ? items : [];
      renderMedia();
    })
    .catch(function() { mediaData = []; });
</script>
</body>
</html>
