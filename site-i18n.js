// RC045 gedeelde taal helpers (gebruikt door elke pagina)

function getInitialLang(translations) {
    // Pagina's mogen hun vertalingen lokaal houden en ze expliciet meegeven.
    // Voor bestaande pagina's zonder argument blijft de oude globale i18n-opzet
    // volledig ondersteund.
    var bron = translations || (typeof i18n !== 'undefined' ? i18n : null) || {};
    const urlLang = new URLSearchParams(window.location.search).get('lang');
    if (urlLang && bron[urlLang]) return urlLang;
    const storedLang = localStorage.getItem('rc045_lang');
    if (storedLang && bron[storedLang]) return storedLang;
    return 'nl';
  }

function updateLiveDocumentTitle(lang) {
  var meta = document.querySelector('meta[name="rc045-title-' + lang + '"]');
  if (meta && meta.getAttribute('content')) document.title = meta.getAttribute('content');
}

function updateInternalLinks(lang) {
    document.querySelectorAll('a[href]').forEach(a => {
      const href = a.getAttribute('href');
      // Externe/mailto/tel-links en puur lokale anchors blijven ongemoeid.
      if (!href || href.startsWith('http') || href.startsWith('mailto:') || href.startsWith('tel:') || href.startsWith('#')) return;

      // Legacy homepage-links (index.html) worden altijd map-relatief gemaakt.
      // Daardoor wijst dezelfde code op productie naar / en in de testomgeving
      // naar /dev/, zonder hardcoded omgevingspad.
      const isLegacyHomepageLink = href === 'index.html' || href.startsWith('index.html#') || href.startsWith('index.html?');
      const isHomepageLink = href === './' || href.startsWith('./#') || href.startsWith('./?');
      const isHtmlLink = href.endsWith('.html') || href.includes('.html#') || href.includes('.html?');
      if (!isLegacyHomepageLink && !isHomepageLink && !isHtmlLink) return;

      const bronHref = isLegacyHomepageLink ? './' + href.slice('index.html'.length) : href;
      const url = new URL(bronHref, window.location.href);
      if (lang === 'nl') {
        url.searchParams.delete('lang');
      } else {
        url.searchParams.set('lang', lang);
      }

      const newHref = (isLegacyHomepageLink || isHomepageLink)
        ? './' + url.search + url.hash
        : url.pathname.split('/').pop() + url.search + url.hash;
      a.setAttribute('href', newHref);
    });
  }

// Taalkeuze als uitklapmenu. De knoppen in het menu roepen nog steeds setLang()
// aan via hun onclick, dit stuk regelt alleen openen, sluiten en het bijwerken
// van de knop bovenin.
(function () {
  function init() {
    var wrap = document.getElementById('lang-switch');
    if (!wrap) return;
    var trigger = wrap.querySelector('.lang-trigger');
    var vlag = wrap.querySelector('.lang-trigger-flag');
    var code = wrap.querySelector('.lang-trigger-code');
    var opties = wrap.querySelectorAll('.lang-flag');

    function toonKeuze() {
      var actief = wrap.querySelector('.lang-flag.active') || opties[0];
      if (!actief) return;
      // De vlag komt uit het bijbehorende menu-item (een kleine SVG, geen
      // emoji): Windows heeft in Chrome/Edge/Brave geen vlag-emoji's in het
      // systeemlettertype, dus die zouden daar als losse letters verschijnen.
      var actiefVlag = actief.querySelector('.lang-menu-flag');
      vlag.innerHTML = actiefVlag ? actiefVlag.innerHTML : '';
      code.textContent = actief.getAttribute('data-code') || '';
    }

    function zetOpen(aan) {
      wrap.classList.toggle('open', aan);
      trigger.setAttribute('aria-expanded', aan ? 'true' : 'false');
    }

    trigger.addEventListener('click', function (e) {
      e.stopPropagation();
      zetOpen(!wrap.classList.contains('open'));
    });

    Array.prototype.forEach.call(opties, function (btn) {
      // Het onclick attribuut (setLang) draait als eerste, daarna pas dit.
      btn.addEventListener('click', function () {
        zetOpen(false);
        toonKeuze();
        updateLiveDocumentTitle(document.documentElement.lang || 'nl');
      });
    });

    function buitenaf(e) { if (!wrap.contains(e.target)) zetOpen(false); }
    document.addEventListener('click', buitenaf);
    document.addEventListener('touchstart', buitenaf, { passive: true });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' || e.key === 'Esc') { zetOpen(false); trigger.focus(); }
    });

    toonKeuze();
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// ===== THEMAKEUZE (licht / donker) =====
// Zet data-thema op <html> en plaatst zelf een knop naast de taalkiezer, zodat
// geen enkele pagina aangepast hoeft te worden. Drie standen: systeem (volgt de
// instelling van het apparaat), licht en donker. De keuze staat in sessionStorage,
// zodat elk nieuw bezoek weer met de OS instelling begint en een eerdere
// handmatige keuze niet voor altijd blijft hangen. Alle kleuren horen bij dit
// thema staan in styles.css.
(function () {
  var SLEUTEL = 'rc045_thema';
  var STANDEN = ['systeem', 'licht', 'donker'];
  var ICONEN = { systeem: '🌓', licht: '☀️', donker: '🌙' };
  var TEKST = {
    nl: { systeem: 'Thema: systeem', licht: 'Thema: licht', donker: 'Thema: donker' },
    en: { systeem: 'Theme: system', licht: 'Theme: light', donker: 'Theme: dark' },
    de: { systeem: 'Thema: System', licht: 'Thema: hell', donker: 'Thema: dunkel' }
  };
  var donkerQuery = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
  var knop = null;

  function gekozenStand() {
    var opgeslagen = null;
    try { opgeslagen = sessionStorage.getItem(SLEUTEL); } catch (e) { /* privémodus */ }
    return STANDEN.indexOf(opgeslagen) === -1 ? 'systeem' : opgeslagen;
  }

  // Vertaalt de gekozen stand naar wat er daadwerkelijk getoond wordt.
  function toepassen() {
    var stand = gekozenStand();
    var donker = stand === 'donker' || (stand === 'systeem' && donkerQuery && donkerQuery.matches);
    document.documentElement.setAttribute('data-thema', donker ? 'donker' : 'licht');
    werkKnopBij(stand);
  }

  function werkKnopBij(stand) {
    if (!knop) return;
    var taal = document.documentElement.lang;
    var tekst = (TEKST[taal] || TEKST.nl)[stand];
    knop.textContent = ICONEN[stand];
    knop.setAttribute('aria-label', tekst);
    knop.setAttribute('title', tekst);
  }

  function volgende() {
    var nu = STANDEN.indexOf(gekozenStand());
    var nieuw = STANDEN[(nu + 1) % STANDEN.length];
    try { sessionStorage.setItem(SLEUTEL, nieuw); } catch (e) { /* privémodus */ }
    toepassen();
  }

  function init() {
    var taalKiezer = document.getElementById('lang-switch');
    if (taalKiezer && taalKiezer.parentNode) {
      // Taalkiezer en themaknop samen in een omhulsel, zodat de navigatiebalk
      // evenveel directe kinderen houdt en de verdeling niet verschuift.
      var omhulsel = document.createElement('div');
      omhulsel.className = 'nav-tools';
      knop = document.createElement('button');
      knop.type = 'button';
      knop.className = 'thema-switch';
      knop.addEventListener('click', volgende);
      taalKiezer.parentNode.insertBefore(omhulsel, taalKiezer);
      omhulsel.appendChild(knop);
      omhulsel.appendChild(taalKiezer);

      // Label meeveranderen als de bezoeker van taal wisselt.
      Array.prototype.forEach.call(taalKiezer.querySelectorAll('.lang-flag'), function (b) {
        b.addEventListener('click', function () { werkKnopBij(gekozenStand()); });
      });
    }
    toepassen();
  }

  // Zo vroeg mogelijk toepassen, nog voor de rest van de pagina klaar is.
  toepassen();

  // Meeveranderen als de systeeminstelling wisselt en de bezoeker op 'systeem' staat.
  if (donkerQuery) {
    if (donkerQuery.addEventListener) donkerQuery.addEventListener('change', toepassen);
    else if (donkerQuery.addListener) donkerQuery.addListener(toepassen);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// ===== MOBIEL MENU =====
// Stond eerder op zes pagina's apart, met kleine onderlinge verschillen: op
// twee pagina's werd aria-expanded niet bijgewerkt en op een pagina ontbrak
// { passive: true } bij de scroll-listener. Nu overal gelijk.
(function () {
  function init() {
    var knop = document.getElementById('hamburger');
    var links = document.querySelector('.nav-links');
    var balk = document.getElementById('main-nav');
    if (!knop || !links) return;

    knop.addEventListener('click', function () {
      var open = links.classList.toggle('open');
      knop.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    function sluit() {
      links.classList.remove('open');
      knop.setAttribute('aria-expanded', 'false');
    }

    window.addEventListener('scroll', sluit, { passive: true });
    document.addEventListener('touchstart', function (e) {
      if (balk && !balk.contains(e.target)) sluit();
    }, { passive: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// ===== TERUG NAAR BOVEN =====
(function () {
  function init() {
    var knop = document.getElementById('backToTop');
    if (!knop) return;
    window.addEventListener('scroll', function () {
      knop.classList.toggle('visible', window.scrollY > 400);
    }, { passive: true });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();

// ===== SPONSORS IN DE FOOTER (data/sponsors.json, bijwerken via beheer.php) =====
// De "sponsor worden?"-tekst onder de logo's is vertaald met NL-terugval. Het
// woord "contactformulier" (of de vertaling ervan) wordt automatisch een link;
// dat gebeurt puur op basis van dat woord in de tekst.
// renderSponsorCta staat bewust buiten de functie hieronder: elke pagina roept
// hem aan vanuit setLang(), zodat de tekst bij een taalwissel meegaat.
var sponsorCtaData = null;
var sponsorCtaDefault = {
  nl: 'Sponsor worden? Neem contact op via het contactformulier.',
  en: 'Want to become a sponsor? Get in touch via the contact form.',
  de: 'Sponsor werden? Kontaktieren Sie uns über das Kontaktformular.'
};
var sponsorCtaKeyword = { nl: 'contactformulier', en: 'contact form', de: 'Kontaktformular' };
// Op de homepage staat het contactformulier op dezelfde pagina, elders niet.
// Zowel /, index.html en index.php behandelen we als homepage; eventuele oude
// index.html-links worden door updateInternalLinks() map-relatief gemaakt.
var sponsorContactLink = (function () {
  var bestand = window.location.pathname.split('/').pop().toLowerCase();
  var isHomepage = bestand === '' || bestand === 'index.html' || bestand === 'index.php';
  return isHomepage ? '#contact' : './#contact';
})();

function sponsorTaal() {
  // currentLang hoort bij het paginascript, dat pas na dit bestand draait.
  // Zolang dat er nog niet is, terugvallen op het lang-attribuut.
  try {
    if (typeof currentLang === 'string' && currentLang) return currentLang;
  } catch (e) { /* nog niet gedefinieerd */ }
  return document.documentElement.lang || 'nl';
}

function renderSponsorCta() {
  var el = document.getElementById('footer-sponsors-cta');
  if (!el) return;
  var taal = sponsorTaal();
  var bron = sponsorCtaData || sponsorCtaDefault;
  var tekst = bron[taal] || bron.nl || sponsorCtaDefault[taal] || sponsorCtaDefault.nl;
  var keyword = sponsorCtaKeyword[taal] || sponsorCtaKeyword.nl;
  el.textContent = '';
  var idx = tekst.toLowerCase().indexOf(keyword.toLowerCase());
  if (idx === -1) {
    el.textContent = tekst;
    return;
  }
  el.appendChild(document.createTextNode(tekst.slice(0, idx)));
  var a = document.createElement('a');
  a.href = sponsorContactLink;
  a.textContent = tekst.slice(idx, idx + keyword.length);
  el.appendChild(a);
  el.appendChild(document.createTextNode(tekst.slice(idx + keyword.length)));
}

(function () {
  if (!document.getElementById('footer-sponsors-cta') && !document.getElementById('sponsors-grid')) return;

  fetch('data/sponsors.json', { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (d && d.cta) sponsorCtaData = d.cta;
      renderSponsorCta();

      if (!d || !Array.isArray(d.items) || d.items.length === 0) return;
      var grid = document.getElementById('sponsors-grid');
      if (!grid) return;
      var versie = d.updated ? ('?v=' + encodeURIComponent(d.updated)) : '';

      d.items.forEach(function (sp) {
        if (!sp || !sp.name || !sp.logo) return;

        var img = document.createElement('img');
        img.src = 'images/sponsors/' + encodeURIComponent(sp.logo) + versie;
        img.alt = sp.name;
        img.className = 'footer-sponsor-logo';
        img.loading = 'lazy';
        img.decoding = 'async';
        // Afmetingen staan in sponsors.json (beheer.php leest ze uit bij het
        // opslaan). Daarmee reserveert de browser meteen de juiste breedte, zodat
        // de footer niet verspringt zodra de logo's binnen zijn. Oudere regels
        // zonder die velden blijven gewoon werken.
        if (sp.width > 0 && sp.height > 0) {
          img.width = sp.width;
          img.height = sp.height;
        }

        var kaart = document.createElement('div');
        kaart.className = 'footer-sponsor-card';
        kaart.appendChild(img);

        if (sp.url && /^https?:\/\//i.test(sp.url)) {
          var link = document.createElement('a');
          link.href = sp.url;
          link.target = '_blank';
          link.rel = 'noopener noreferrer';
          link.style.display = 'contents';
          link.appendChild(kaart);
          grid.appendChild(link);
        } else {
          grid.appendChild(kaart);
        }
      });
    })
    .catch(function () { renderSponsorCta(); });
})();

// ===== NAVIGATIEMENU & FOOTER (data/homepage.json, bijwerken via beheer.php:
// tabblad Homepage, groepen "Navigatiemenu" en "Footer") =====
// Het menu en de footer staan op elke pagina apart in de HTML (links en
// "actief"-status verschillen per pagina), maar de teksten zelf komen van
// hier uit een centrale plek, zodat je ze maar op één plek hoeft aan te
// passen in plaats van in alle zeven bestanden. Leeg gelaten EN/DE valt
// terug op de Nederlandse tekst, net als bij de rest van het CMS.
var navFooterData = null;
var navFooterVelden = {
  'nav-about': 'nav_about', 'footer-link-about': 'nav_about',
  'nav-membership': 'nav_membership',
  'nav-track': 'nav_track',
  'nav-location': 'nav_location',
  'nav-photobook': 'nav_photobook',
  'nav-contact': 'nav_contact', 'footer-link-contact': 'nav_contact',
  'nav-join': 'nav_join',
  'footer-brand-text': 'footer_brand',
  'footer-nav-title': 'footer_nav',
  'footer-link-origin': 'footer_origin',
  'footer-link-media': 'footer_media',
  'footer-link-photobook': 'footer_photobook',
  'footer-link-calendar': 'footer_calendar',
  'footer-join-title': 'footer_join',
  'footer-link-become': 'footer_become',
  'footer-link-guesttag': 'guest_tag',
  'footer-link-rules': 'footer_rules',
  'footer-link-sponsor': 'footer_sponsor',
  'footer-sponsors-title': 'footer_sponsors_title',
  'footer-credit-text': 'footer_credit'
};
function renderNavFooterTeksten() {
  if (!navFooterData) return;
  var taal = sponsorTaal();
  Object.keys(navFooterVelden).forEach(function (elId) {
    var bron = navFooterData[navFooterVelden[elId]];
    if (!bron) return;
    var tekst = (bron[taal] && String(bron[taal]).trim()) ? bron[taal] : (bron.nl || '');
    if (!tekst) return;
    var el = document.getElementById(elId);
    if (el) el.textContent = tekst;
  });
}
(function () {
  fetch('data/homepage.json', { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d) return;
      navFooterData = d;
      renderNavFooterTeksten();
    })
    .catch(function () {});
})();

// ===== FACEBOOK-LINK IN DE FOOTER (data/contact.json) =====
// De homepage haalt contact.json toch al op voor de openingstijden en zet de
// link daar zelf; die zet rc045EigenContact zodat we hier niet dubbel ophalen.
(function () {
  function init() {
    if (window.rc045EigenContact) return;
    var el = document.getElementById('footer-facebook-link');
    if (!el) return;
    fetch('data/contact.json', { cache: 'no-store' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d && d.facebook) el.href = d.facebook; })
      .catch(function () {});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();