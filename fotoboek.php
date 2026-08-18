<?php require_once __DIR__ . '/seo-head.php'; ?><!DOCTYPE html>
<html lang="<?php echo rc045Taal(); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead('fotoboek'); ?>
<link rel="icon" type="image/x-icon" href="favicon.ico">
<link rel="icon" type="image/png" sizes="16x16" href="favicon-16x16.png">
<link rel="icon" type="image/png" sizes="32x32" href="favicon-32x32.png">
<link rel="icon" type="image/png" sizes="48x48" href="favicon-48x48.png">
<link rel="apple-touch-icon" sizes="180x180" href="apple-touch-icon.png">
<link rel="manifest" href="site.webmanifest">
<meta name="theme-color" content="#1E2C13">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="vendor/photoswipe/photoswipe.css">
<link rel="stylesheet" href="styles.css">
  <style>
    
    .page-hero-bg { position: absolute; top: -80px; left: 0; right: 0; bottom: -80px; background-image: url('images/crawlercollage.jpg'); background-size: cover; background-position: center; opacity: 0.35; will-change: transform; }
    
    .main { max-width: 1100px; margin: 0 auto; padding: 56px 24px 80px; }
    
    .intro-text { font-size: 16px; color: var(--muted); line-height: 1.8; margin-bottom: 40px; }
    
    .album-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    
    .album-card { position: relative; display: block; border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); aspect-ratio: 4 / 3; cursor: pointer; transition: transform 0.2s, box-shadow 0.2s; }
    
    .album-card:hover { transform: translateY(-3px); box-shadow: 0 8px 32px rgba(0,0,0,0.16); }
    
    .album-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.3s; }
    
    .album-card:hover img { transform: scale(1.05); }
    
    .album-card-overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0) 40%, rgba(0,0,0,0.75) 100%); display: flex; flex-direction: column; justify-content: flex-end; padding: 16px; color: white; }
    
    .album-card-title { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 17px; }
    
    .album-card-count { font-size: 12px; opacity: 0.85; margin-top: 2px; }
    
    .album-card-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; font-size: 40px; background: var(--dark); }
    
    .album-empty { color: var(--muted); font-size: 15px; }
    
    .back-link { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600; color: var(--teal-dark); margin-bottom: 24px; }
    
    .back-link:hover { color: var(--teal); }
    
    .album-detail-title { font-size: clamp(22px, 3vw, 30px); font-weight: 800; margin-bottom: 8px; color: var(--dark); }
    
    .album-detail-title:has(+ .album-detail-beschrijving[hidden]) { margin-bottom: 24px; }
    
    .album-detail-beschrijving { white-space: pre-line; color: var(--muted); font-size: 15px; line-height: 1.75; max-width: 640px; margin-bottom: 28px; }
    
    .photo-grid { columns: 4; column-gap: 14px; }
    
    .photo-grid a { display: block; position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 14px; break-inside: avoid; }
    
    .photo-grid img { width: 100%; height: auto; display: block; transition: transform 0.25s; }
    
    .photo-grid a:hover img { transform: scale(1.04); }
    
    .photo-grid .photo-video { display: block; position: relative; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 14px; break-inside: avoid; background: #000; }
    
    .photo-grid .photo-video video { width: 100%; height: auto; display: block; }
    
    .photo-grid .photo-video-caption { font-size: 12px; color: var(--muted); padding: 6px 2px 0; }
    
    @media (max-width: 900px) {
      .photo-grid { columns: 3; }
    }
    
    @media (max-width: 700px) {
      .album-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
      .photo-grid { columns: 2; column-gap: 10px; }
      .photo-grid a { margin-bottom: 10px; }
      .album-card-title { font-size: 14px; }
      .album-card-count { font-size: 11px; }
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
      <li class="nav-active"><a href="fotoboek.html" id="nav-photobook" data-i18n="nav.photobook">Fotoboek</a></li>
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
    <div class="section-label" data-i18n="hero.label">Fotoboek</div>
    <h1 data-i18n="hero.title">Fotoboek</h1>
    <p id="ft-hero-sub" data-i18n="hero.sub">Foto's van onze evenementen en banen, gerangschikt per album. Klik op een album om de foto's te bekijken.</p>
  </div>
</div>
<main class="main" id="main-content">
  <div id="album-grid" class="album-grid">
    <p class="album-empty" data-i18n="fotoboek.loading">Albums worden geladen...</p>
  </div>
  <div id="album-detail" class="album-detail" hidden>
    <a href="#" id="back-to-albums" class="back-link" data-i18n="fotoboek.back">← Terug naar albums</a>
    <h2 id="album-detail-title" class="album-detail-title"></h2>
    <p id="album-detail-beschrijving" class="album-detail-beschrijving" hidden></p>
    <div id="photo-grid" class="photo-grid"></div>
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
    'hero.label': 'Fotoboek', 'hero.title': 'Fotoboek',
    'hero.sub': 'Foto\'s van onze evenementen en banen, gerangschikt per album. Klik op een album om de foto\'s te bekijken.',
    'fotoboek.loading': 'Albums worden geladen...',
    'fotoboek.empty': 'Er zijn nog geen albums geplaatst.',
    'fotoboek.back': '← Terug naar albums',
    'footer.brand': 'Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto\'s in de regio Zuid-Limburg. Voor beginners én ervaren rijders.',
    'footer.nav': 'Navigatie', 'footer.origin': 'Het ontstaan', 'footer.media': 'Media', 'footer.photobook': 'Fotoboek',
    'footer.photos': 'foto\'s',
    'footer.calendar': 'Activiteitenkalender', 'footer.join': 'Meedoen',
    'footer.become': 'Lid worden', 'footer.rules': 'Baanreglement',
    'footer.sponsor': 'Sponsoring',    'footer.sponsors.title': 'Met dank aan onze sponsoren',
    'footer.credit': 'Website door',
    'guest.tag': 'Gastrijden'
  },
  en: {
    'nav.about': 'About us', 'nav.membership': 'Membership', 'nav.track': 'The track',
    'nav.location': 'Location', 'nav.photobook': 'Photo book', 'nav.contact': 'Contact', 'nav.join': 'Become a member',
    'hero.label': 'Photo book', 'hero.title': 'Photo book',
    'hero.sub': 'Photos from our events and tracks, sorted by album. Click an album to view the photos.',
    'fotoboek.loading': 'Loading albums...',
    'fotoboek.empty': 'No albums have been added yet.',
    'fotoboek.back': '← Back to albums',
    'footer.brand': 'A friendly club for enthusiasts of electrically powered RC cars in the South Limburg region. For beginners and experienced riders alike.',
    'footer.nav': 'Navigation', 'footer.origin': 'Our history', 'footer.media': 'Media', 'footer.photobook': 'Photo book',
    'footer.photos': 'photos',
    'footer.calendar': 'Events calendar', 'footer.join': 'Get involved',
    'footer.become': 'Become a member', 'footer.rules': 'Track regulations',
    'footer.sponsor': 'Sponsorship',    'footer.sponsors.title': 'With thanks to our sponsors',
    'footer.credit': 'Website by',
    'guest.tag': 'Guest riding'
  },
  de: {
    'nav.about': 'Über uns', 'nav.membership': 'Mitgliedschaft', 'nav.track': 'Die Strecke',
    'nav.location': 'Standort', 'nav.photobook': 'Fotobuch', 'nav.contact': 'Kontakt', 'nav.join': 'Mitglied werden',
    'hero.label': 'Fotobuch', 'hero.title': 'Fotobuch',
    'hero.sub': 'Fotos von unseren Veranstaltungen und Strecken, sortiert nach Album. Klicke auf ein Album, um die Fotos anzusehen.',
    'fotoboek.loading': 'Alben werden geladen...',
    'fotoboek.empty': 'Es wurden noch keine Alben angelegt.',
    'fotoboek.back': '← Zurück zu den Alben',
    'footer.brand': 'Ein freundlicher Verein für Liebhaber von elektrisch angetriebenen RC-Autos in der Region Südlimburg. Für Anfänger und erfahrene Fahrer.',
    'footer.nav': 'Navigation', 'footer.origin': 'Unsere Geschichte', 'footer.media': 'Medien', 'footer.photobook': 'Fotobuch',
    'footer.photos': 'Fotos',
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
  renderFotoboekView();
  renderSponsorCta();
    renderNavFooterTeksten();
  renderFotoboekTekst();
}
// ===== ONDERTITEL (data/fotoboek-pagina.json, bewerkbaar via beheer.php) =====
var fotoboekTekstData = null;
function renderFotoboekTekst() {
  if (!fotoboekTekstData) return;
  var bron = fotoboekTekstData.hero_sub;
  if (!bron) return;
  var tekst = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
  if (!tekst) return;
  var el = document.getElementById('ft-hero-sub');
  if (el) el.textContent = tekst;
}
fetch('data/fotoboek-pagina.json', { cache: 'no-store' })
  .then(function (r) { return r.ok ? r.json() : null; })
  .then(function (d) {
    if (!d) return;
    fotoboekTekstData = d;
    renderFotoboekTekst();
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
// ===== FOTOBOEK (data/fotoboek.json, bijwerken via beheer.php) =====
// Albums staan in images/fotoboek/<album-slug>/, met thumbnails in de
// submap thumbs/. Welk album open staat wordt bijgehouden via het
// hash-gedeelte van de URL (#album=<slug>), zodat de terug-knop van de
// browser gewoon werkt en een album ook direct te linken is.
var fotoboekData = null;

function fotoboekAlbumFromHash() {
  var m = /^#album=(.+)$/.exec(window.location.hash);
  return m ? decodeURIComponent(m[1]) : null;
}

var fotoboekMaanden = {
  nl: ['jan', 'feb', 'mrt', 'apr', 'mei', 'jun', 'jul', 'aug', 'sep', 'okt', 'nov', 'dec'],
  en: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
  de: ['Jan.', 'Feb.', 'März', 'Apr.', 'Mai', 'Juni', 'Juli', 'Aug.', 'Sep.', 'Okt.', 'Nov.', 'Dez.']
};
function fotoboekFormatDatum(datumStr) {
  var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(datumStr || '');
  if (!m) return '';
  var maanden = fotoboekMaanden[currentLang] || fotoboekMaanden.nl;
  var dag = parseInt(m[3], 10);
  var maand = maanden[parseInt(m[2], 10) - 1];
  return dag + ' ' + maand + ' ' + m[1];
}

function renderAlbumGrid() {
  var grid = document.getElementById('album-grid');
  grid.innerHTML = '';
  var zichtbareAlbums = (fotoboekData && Array.isArray(fotoboekData.albums))
    ? fotoboekData.albums.filter(function(a) { return !a.verborgen; })
    : [];
  if (zichtbareAlbums.length === 0) {
    var p = document.createElement('p');
    p.className = 'album-empty';
    p.textContent = i18n[currentLang]['fotoboek.empty'];
    grid.appendChild(p);
    return;
  }
  zichtbareAlbums.forEach(function(album) {
    var titel = (album.title && (album.title[currentLang] || album.title.nl)) || album.slug;
    var items = Array.isArray(album.photos) ? album.photos : [];
    var aantal = items.length;
    // Cover is altijd een foto (nooit een video); bestaat die niet (bijv. een
    // album met alleen video's), val dan terug op de thumbnail van de eerste
    // video, en anders op een generiek video-icoon in plaats van een kapotte
    // afbeelding.
    var eersteVideoMetPoster = items.find(function(f) { return f.type === 'video' && f.poster; });
    var cover = album.cover || (eersteVideoMetPoster && eersteVideoMetPoster.poster);

    var kaart = document.createElement('a');
    kaart.href = '#album=' + encodeURIComponent(album.slug);
    kaart.className = 'album-card';

    if (cover) {
      var img = document.createElement('img');
      img.src = 'images/fotoboek/' + encodeURIComponent(album.slug) + '/thumbs/' + encodeURIComponent(cover);
      img.alt = titel;
      img.loading = 'lazy';
      img.decoding = 'async';
      kaart.appendChild(img);
    } else {
      var placeholder = document.createElement('div');
      placeholder.className = 'album-card-placeholder';
      placeholder.textContent = '🎬';
      kaart.appendChild(placeholder);
    }

    var overlay = document.createElement('div');
    overlay.className = 'album-card-overlay';
    var titelEl = document.createElement('div');
    titelEl.className = 'album-card-title';
    titelEl.textContent = titel;
    var countEl = document.createElement('div');
    countEl.className = 'album-card-count';
    var datumTekst = fotoboekFormatDatum(album.date);
    countEl.textContent = aantal + ' ' + i18n[currentLang]['footer.photos'] + (datumTekst ? ' · ' + datumTekst : '');
    overlay.appendChild(titelEl);
    overlay.appendChild(countEl);
    kaart.appendChild(overlay);

    grid.appendChild(kaart);
  });
}

function renderAlbumDetail(slug) {
  var album = fotoboekData && Array.isArray(fotoboekData.albums)
    ? fotoboekData.albums.find(function(a) { return a.slug === slug; })
    : null;
  if (!album || album.verborgen) { window.location.hash = ''; return; }

  var titel = (album.title && (album.title[currentLang] || album.title.nl)) || album.slug;
  document.getElementById('album-detail-title').textContent = titel;

  // Kort verhaal onder de titel: optioneel, NL-terugval, helemaal verborgen
  // als er ook geen Nederlandse tekst is ingevuld.
  var beschrijvingEl = document.getElementById('album-detail-beschrijving');
  var beschrijving = (album.beschrijving && (album.beschrijving[currentLang] || album.beschrijving.nl)) || '';
  beschrijvingEl.textContent = beschrijving;
  beschrijvingEl.hidden = beschrijving === '';

  var photoGrid = document.getElementById('photo-grid');
  photoGrid.innerHTML = '';
  (album.photos || []).forEach(function(foto) {
    var bijschrift = (foto.caption && (foto.caption[currentLang] || foto.caption.nl)) || titel;

    // Video's worden inline afgespeeld met de standaard browserbediening, niet
    // via de foto-lightbox (PhotoSwipe bindt alleen aan <a>-elementen in dit
    // raster, dus een video buiten een <a> doet daar vanzelf niet aan mee).
    if (foto.type === 'video') {
      var videoBlok = document.createElement('div');
      videoBlok.className = 'photo-video';
      var video = document.createElement('video');
      video.controls = true;
      video.preload = 'metadata';
      video.playsInline = true;
      if (foto.width && foto.height) {
        video.width = foto.width;
        video.height = foto.height;
      }
      if (foto.poster) video.poster = 'images/fotoboek/' + encodeURIComponent(album.slug) + '/thumbs/' + encodeURIComponent(foto.poster);
      var bron = document.createElement('source');
      bron.src = 'images/fotoboek/' + encodeURIComponent(album.slug) + '/' + encodeURIComponent(foto.file);
      bron.type = 'video/mp4';
      video.appendChild(bron);
      videoBlok.appendChild(video);
      if (bijschrift) {
        var onderschrift = document.createElement('div');
        onderschrift.className = 'photo-video-caption';
        onderschrift.textContent = bijschrift;
        videoBlok.appendChild(onderschrift);
      }
      photoGrid.appendChild(videoBlok);
      return;
    }

    var link = document.createElement('a');
    link.href = 'images/fotoboek/' + encodeURIComponent(album.slug) + '/' + encodeURIComponent(foto.file);
    link.setAttribute('data-pswp-width', foto.width);
    link.setAttribute('data-pswp-height', foto.height);
    link.setAttribute('target', '_blank');
    if (bijschrift) link.setAttribute('data-pswp-caption', bijschrift);

    var img = document.createElement('img');
    img.src = 'images/fotoboek/' + encodeURIComponent(album.slug) + '/thumbs/' + encodeURIComponent(foto.file);
    img.alt = bijschrift;
    img.loading = 'lazy';
    img.decoding = 'async';
    // Afmetingen meegeven zodat de browser de ruimte al reserveert voordat de
    // thumb binnen is. De thumb heeft dezelfde verhouding als het origineel,
    // dus de waarden uit fotoboek.json kloppen. Voorkomt springen in het raster.
    if (foto.width && foto.height) {
      img.width = foto.width;
      img.height = foto.height;
    }
    link.appendChild(img);

    photoGrid.appendChild(link);
  });

  document.getElementById('album-grid').hidden = true;
  document.getElementById('album-detail').hidden = false;
}

function renderFotoboekView() {
  if (!fotoboekData) return;
  var slug = fotoboekAlbumFromHash();
  if (slug) {
    renderAlbumDetail(slug);
  } else {
    document.getElementById('album-detail').hidden = true;
    document.getElementById('album-grid').hidden = false;
    renderAlbumGrid();
  }
}

document.getElementById('back-to-albums').addEventListener('click', function(e) {
  e.preventDefault();
  window.location.hash = '';
});
window.addEventListener('hashchange', renderFotoboekView);

fetch('data/fotoboek.json', { cache: 'no-store' })
  .then(function(r) { return r.ok ? r.json() : null; })
  .then(function(d) {
    fotoboekData = d && Array.isArray(d.albums) ? d : { albums: [] };
    renderFotoboekView();
  })
  .catch(function() {
    fotoboekData = { albums: [] };
    renderFotoboekView();
  });
</script>
<script type="module">
  import PhotoSwipeLightbox from './vendor/photoswipe/photoswipe-lightbox.esm.min.js';
  var lightbox = new PhotoSwipeLightbox({
    gallery: '#photo-grid',
    children: 'a',
    pswpModule: function() { return import('./vendor/photoswipe/photoswipe.esm.min.js'); }
  });
  lightbox.init();
</script>
</body>
</html>
