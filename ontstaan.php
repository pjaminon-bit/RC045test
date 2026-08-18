<?php require_once __DIR__ . '/seo-head.php'; ?><!DOCTYPE html>
<html lang="<?php echo rc045Taal(); ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead('ontstaan'); ?>
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
    
    .page-hero-bg { position: absolute; top: -80px; left: 0; right: 0; bottom: -80px; background-image: url('images/crawlergroen.jpg'); background-size: cover; background-position: center; opacity: 0.35; will-change: transform; }
    
    .main { max-width: 1000px; margin: 0 auto; padding: 56px 24px 80px; }
    
    .story-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 40px; box-shadow: var(--shadow); margin-bottom: 48px; }
    
    .story-card p { font-size: 16px; color: var(--muted); line-height: 1.85; margin-bottom: 18px; }
    
    .story-card p:last-child { margin-bottom: 0; }
    
    .story-card strong { color: var(--dark); }
    
    .photos-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--gold); margin-bottom: 20px; }
    
    .ontstaan-grid { display: grid; grid-template-columns: 2fr 1fr; grid-template-rows: 240px 240px; gap: 16px; border-radius: var(--radius); overflow: hidden; }
    
    .ontstaan-grid + .ontstaan-grid { margin-top: 16px; }
    
    .ontstaan-photo { object-fit: cover; width: 100%; height: 100%; transition: transform 0.4s; cursor: pointer; }
    
    .ontstaan-photo:hover { transform: scale(1.05); }
    
    .ontstaan-photo-wrap { overflow: hidden; }
    
    .ontstaan-photo-wrap.tall { grid-row: span 2; }
    
    .ontstaan-photo-wrap.empty { background: var(--teal-light); display: flex; align-items: center; justify-content: center; color: var(--teal-dark); font-size: 13px; font-weight: 600; text-align: center; padding: 16px; }
    
    @media (max-width: 700px) {
    .ontstaan-grid { grid-template-columns: 1fr; grid-template-rows: auto; }
    .ontstaan-photo-wrap.tall { grid-row: span 1; }
    }
    
    @media (max-width: 600px) { .story-card { padding: 24px 20px; } }
  </style>
  <script data-goatcounter="https://rc045.goatcounter.com/count"
        async src="//gc.zgo.at/count.js"></script>
</head>
<body>
<a href="#main-content" class="skip-link">Naar hoofdinhoud</a>
<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Terug naar boven">↑</button>
<div class="lightbox" id="lightbox">
<button class="lightbox-close" id="lightbox-close" aria-label="Sluiten">×</button>
<img src="" alt="" id="lightbox-img">
</div>
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
<div class="section-label" data-i18n="hero.label">Onze geschiedenis</div>
<h1 data-i18n="hero.title">Het ontstaan van RC045</h1>
<p id="ont-hero-sub" data-i18n="hero.sub">Van een vakantie met vrienden tot een bloeiende vereniging, dit is ons verhaal.</p>
</div>
</div>
<main class="main" id="main-content">
<div class="story-card">
<p id="ont-story-p1" data-i18n="story.p1">In 2020 zijn wij met een groep vrienden op vakantie geweest waarbij ook enkele RC-auto's werden meegenomen. Het RC-virus werd hier al snel overgedragen en kort na deze vakantie had één ieder al snel één of meerdere RC-auto's aangeschaft.</p>
<p id="ont-story-p2" data-i18n="story.p2">Tijdens de zoektocht naar een locatie om te kunnen rijden kwamen wij al snel uit op het grasveld bij sportpark Strijthagen (naast het MTB parcours). Al snel bleek dat hier meer hobbyisten met hun RC-auto hun rondje kwamen rijden.</p>
<p id="ont-story-p3" data-i18n="story.p3">Dit groeide al snel uit tot een wekelijkse samenkomst op zondag, en de groep hobbyisten én toeschouwers werd steeds groter.</p>
<p id="ont-story-p4" data-i18n="story.p4">We kregen ook steeds meer aandacht van de media, o.a. RTV Parkstad, Omroep Landgraaf en L1 hebben ons één of meerdere keren bezocht en hebben eraan meegeholpen dat onze activiteiten steeds meer bekendheid kregen.</p>
<p id="ont-story-p5" data-i18n="story.p5">Door de steeds groter wordende belangstelling en omdat wij één en ander op een goede, gezellige en veilige manier wilden faciliteren hebben wij al snel onze vereniging RC045 opgericht.</p>
<p id="ont-story-p6" data-i18n="story.p6">In 2022 kregen we de mogelijkheid om een mooi stukje terrein in Eygelshoven te huren. Na heel hard ploeteren konden we daar eindelijk onze RC-baan realiseren.</p>
<p id="ont-story-p7" data-i18n="story.p7">Nog steeds voegen we optimalisaties en veranderingen door aan onze baan, het is nooit af.</p>
</div>
<div class="photos-title" data-i18n="photos.title">Foto's door de jaren heen</div>
<div class="ontstaan-grid">
<div class="ontstaan-photo-wrap tall">
<img width="1024" height="768" src="images/basherbaaneersteaanleg.jpg" alt="Eerste aanleg van de RC045 baan" class="ontstaan-photo lightbox-trigger" loading="lazy" decoding="async">
</div>
<div class="ontstaan-photo-wrap">
<img width="960" height="640" src="images/basherbaaneersteaanleghek.jpg" alt="Plaatsen van het hek tijdens de eerste aanleg" class="ontstaan-photo lightbox-trigger" loading="lazy" decoding="async">
</div>
<div class="ontstaan-photo-wrap">
<img width="1024" height="768" src="images/basherbaaneersteaanleglucht.jpg" alt="Luchtfoto van de eerste aanleg van de baan" class="ontstaan-photo lightbox-trigger" loading="lazy" decoding="async">
</div>
</div>
<div class="ontstaan-grid">
<div class="ontstaan-photo-wrap tall">
<img width="2000" height="934" src="images/basherbaaneersteaanlegmaaien.jpg" alt="Maaien tijdens de eerste aanleg van de baan" class="ontstaan-photo lightbox-trigger" loading="lazy" decoding="async">
</div>
<div class="ontstaan-photo-wrap">
<img width="899" height="1599" src="images/basherbaaneersteaanlegtrack.jpg" alt="Aanleg van het parcours" class="ontstaan-photo lightbox-trigger" loading="lazy" decoding="async">
</div>
<div class="ontstaan-photo-wrap">
<img width="1600" height="1066" src="images/crawlerbaaneersteaanleg.jpg" alt="Eerste aanleg van de crawlerbaan" class="ontstaan-photo lightbox-trigger" loading="lazy" decoding="async">
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
'nav.location': 'Locatie', 'nav.photobook': 'Fotoboek', 'nav.contact': 'Contact', 'nav.join': 'Lid worden',
'footer.brand': 'Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto\'s in de regio Zuid-Limburg. Voor beginners én ervaren rijders.',
'footer.nav': 'Navigatie', 'footer.origin': 'Het ontstaan', 'footer.media': 'Media', 'footer.photobook': 'Fotoboek', 'footer.calendar': 'Activiteitenkalender', 'footer.join': 'Meedoen',
'footer.become': 'Lid worden', 'footer.rules': 'Baanreglement', 'footer.sponsor': 'Sponsoring',
'footer.sponsors.title': 'Met dank aan onze sponsoren',
'footer.credit': 'Website door',
'guest.tag': 'Gastrijden',
'hero.label': 'Onze geschiedenis', 'hero.title': 'Het ontstaan van RC045',
'hero.sub': 'Van een vakantie met vrienden tot een bloeiende vereniging, dit is ons verhaal.',
'story.p1': 'In 2020 zijn wij met een groep vrienden op vakantie geweest waarbij ook enkele RC-auto\'s werden meegenomen. Het RC-virus werd hier al snel overgedragen en kort na deze vakantie had één ieder al snel één of meerdere RC-auto\'s aangeschaft.',
'story.p2': 'Tijdens de zoektocht naar een locatie om te kunnen rijden kwamen wij al snel uit op het grasveld bij sportpark Strijthagen (naast het MTB parcours). Al snel bleek dat hier meer hobbyisten met hun RC-auto hun rondje kwamen rijden.',
'story.p3': 'Dit groeide al snel uit tot een wekelijkse samenkomst op zondag, en de groep hobbyisten én toeschouwers werd steeds groter.',
'story.p4': 'We kregen ook steeds meer aandacht van de media, o.a. RTV Parkstad, Omroep Landgraaf en L1 hebben ons één of meerdere keren bezocht en hebben eraan meegeholpen dat onze activiteiten steeds meer bekendheid kregen.',
'story.p5': 'Door de steeds groter wordende belangstelling en omdat wij één en ander op een goede, gezellige en veilige manier wilden faciliteren hebben wij al snel onze vereniging RC045 opgericht.',
'story.p6': 'In 2022 kregen we de mogelijkheid om een mooi stukje terrein in Eygelshoven te huren. Na heel hard ploeteren konden we daar eindelijk onze RC-baan realiseren.',
'story.p7': 'Nog steeds voegen we optimalisaties en veranderingen door aan onze baan, het is nooit af.',
'photos.title': 'Foto\'s door de jaren heen', 'photo.placeholder': 'Foto volgt'
},
en: {
'nav.about': 'About us', 'nav.membership': 'Membership', 'nav.track': 'The track',
'nav.location': 'Location', 'nav.photobook': 'Photo book', 'nav.contact': 'Contact', 'nav.join': 'Become a member',
'footer.brand': 'A friendly club for enthusiasts of electrically powered RC cars in the South Limburg region. For beginners and experienced riders alike.',
'footer.nav': 'Navigation', 'footer.origin': 'Our history', 'footer.media': 'Media', 'footer.photobook': 'Photo book', 'footer.calendar': 'Events calendar', 'footer.join': 'Get involved',
'footer.become': 'Become a member', 'footer.rules': 'Track regulations', 'footer.sponsor': 'Sponsorship',
'footer.sponsors.title': 'With thanks to our sponsors',
'footer.credit': 'Website by',
'guest.tag': 'Guest riding',
'hero.label': 'Our history', 'hero.title': 'The origin of RC045',
'hero.sub': 'From a holiday with friends to a thriving club, this is our story.',
'story.p1': 'In 2020 we went on holiday together with a group of friends, bringing along a few RC cars. The RC virus spread quickly here, and shortly after this holiday everyone soon had one or more RC cars of their own.',
'story.p2': 'While searching for a location to drive, we soon found the grass field at Sportpark Strijthagen (next to the MTB course). It quickly became clear that other hobbyists were already coming there with their RC cars too.',
'story.p3': 'This soon grew into a weekly gathering on Sundays, and the group of hobbyists and spectators kept getting bigger.',
'story.p4': 'We also received more and more media attention. RTV Parkstad, Omroep Landgraaf, and L1 visited us on several occasions and helped our activities become increasingly well known.',
'story.p5': 'Because of the growing interest, and because we wanted to facilitate everything in a proper, friendly, and safe way, we soon founded our club RC045.',
'story.p6': 'In 2022 we got the opportunity to rent a beautiful piece of land in Eygelshoven. After a lot of hard work, we were finally able to build our RC track there.',
'story.p7': 'We are still adding improvements and changes to our track, it is never truly finished.',
'photos.title': 'Photos through the years', 'photo.placeholder': 'Photo coming soon'
},
de: {
'nav.about': 'Über uns', 'nav.membership': 'Mitgliedschaft', 'nav.track': 'Die Strecke',
'nav.location': 'Standort', 'nav.photobook': 'Fotobuch', 'nav.contact': 'Kontakt', 'nav.join': 'Mitglied werden',
'footer.brand': 'Ein freundlicher Verein für Liebhaber von elektrisch angetriebenen RC-Autos in der Region Südlimburg. Für Anfänger und erfahrene Fahrer.',
'footer.nav': 'Navigation', 'footer.origin': 'Unsere Geschichte', 'footer.media': 'Medien', 'footer.photobook': 'Fotobuch', 'footer.calendar': 'Veranstaltungskalender', 'footer.join': 'Mitmachen',
'footer.become': 'Mitglied werden', 'footer.rules': 'Streckenreglement', 'footer.sponsor': 'Sponsoring',
'footer.sponsors.title': 'Mit Dank an unsere Sponsoren',
'footer.credit': 'Website von',
'guest.tag': 'Gastfahren',
'hero.label': 'Unsere Geschichte', 'hero.title': 'Die Entstehung von RC045',
'hero.sub': 'Von einem Urlaub mit Freunden zu einem blühenden Verein, das ist unsere Geschichte.',
'story.p1': 'Im Jahr 2020 waren wir mit einer Gruppe von Freunden gemeinsam im Urlaub und hatten dabei auch einige RC-Autos mitgenommen. Das RC-Virus wurde hier schnell übertragen, und kurz nach diesem Urlaub hatte bald jeder eines oder mehrere RC-Autos gekauft.',
'story.p2': 'Bei der Suche nach einem Ort zum Fahren landeten wir schnell auf der Wiese beim Sportpark Strijthagen (neben der MTB-Strecke). Schnell stellte sich heraus, dass auch andere Hobbyisten hier mit ihren RC-Autos ihre Runden drehten.',
'story.p3': 'Daraus wuchs schnell ein wöchentliches Treffen sonntags, und die Gruppe von Hobbyisten und Zuschauern wurde immer größer.',
'story.p4': 'Wir bekamen auch immer mehr Aufmerksamkeit von den Medien, u.a. haben uns RTV Parkstad, Omroep Landgraaf und L1 mehrmals besucht und dazu beigetragen, dass unsere Aktivitäten immer bekannter wurden.',
'story.p5': 'Wegen des wachsenden Interesses und weil wir alles auf eine gute, gesellige und sichere Weise gestalten wollten, gründeten wir bald unseren Verein RC045.',
'story.p6': 'Im Jahr 2022 bekamen wir die Möglichkeit, ein schönes Stück Gelände in Eygelshoven zu mieten. Nach harter Arbeit konnten wir dort endlich unsere RC-Strecke verwirklichen.',
'story.p7': 'Wir fügen unserer Strecke noch immer Verbesserungen und Änderungen hinzu, sie ist nie wirklich fertig.',
'photos.title': 'Fotos im Laufe der Jahre', 'photo.placeholder': 'Foto folgt'
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
renderOntstaanTekst();
}
// ===== TEKST "HET ONTSTAAN" (data/ontstaan.json, bijwerken via beheer.php) =====
// De ondertitel en de zeven alinea's stonden hiervoor vast in dit bestand.
// De Nederlandse tekst blijft hier ook als vangnet staan (zichtbaar zolang
// het JSON-bestand leeg is of niet laadt), maar data/ontstaan.json overschrijft
// die zodra het bestaat. Leeg gelaten EN/DE valt terug op de Nederlandse tekst,
// zelfde patroon als bij de homepage.
var ontstaanData = null;
var ontstaanVelden = ['hero_sub', 'story_p1', 'story_p2', 'story_p3', 'story_p4', 'story_p5', 'story_p6', 'story_p7'];
function renderOntstaanTekst() {
if (!ontstaanData) return;
ontstaanVelden.forEach(function (veld) {
var bron = ontstaanData[veld];
if (!bron) return;
var tekst = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
if (!tekst) return;
var el = document.getElementById('ont-' + veld.replace(/_/g, '-'));
if (el) el.textContent = tekst;
});
}
fetch('data/ontstaan.json', { cache: 'no-store' })
.then(function (r) { return r.ok ? r.json() : null; })
.then(function (d) {
if (!d) return;
ontstaanData = d;
renderOntstaanTekst();
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
const backToTop = document.getElementById('backToTop');
window.addEventListener('scroll', function() {
backToTop.classList.toggle('visible', window.scrollY > 400);
}, { passive: true });
const lightbox = document.getElementById('lightbox');
const lightboxImg = document.getElementById('lightbox-img');
document.querySelectorAll('.lightbox-trigger').forEach(img => {
img.addEventListener('click', function() {
lightboxImg.src = this.src; lightboxImg.alt = this.alt;
lightbox.classList.add('open'); document.body.style.overflow = 'hidden';
});
});
document.getElementById('lightbox-close').addEventListener('click', closeLightbox);
lightbox.addEventListener('click', function(e) { if (e.target === lightbox) closeLightbox(); });
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeLightbox(); });
function closeLightbox() { lightbox.classList.remove('open'); document.body.style.overflow = ''; }
</script>
</body>
</html>
