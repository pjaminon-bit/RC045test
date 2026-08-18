<?php require_once __DIR__ . '/seo-head.php'; ?><!DOCTYPE html>
<html lang="<?php echo rc045Taal(); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, follow">
<?php rc045SeoHead('bedankt', false); ?>
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
  <style>body { min-height: 100vh; display: flex; flex-direction: column; }
    
    h1, h2, h3 { font-family: 'Poppins', sans-serif; line-height: 1.2; }.nav { position: static; }
    
    .nav-back { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; color: var(--muted); transition: background 0.15s, color 0.15s; }
    
    .nav-back:hover { background: var(--teal-light); color: var(--teal-dark); }
    
    .main { flex: 1; display: flex; align-items: center; justify-content: center; padding: 48px 24px; }
    
    .bedankt-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 56px 48px; box-shadow: var(--shadow); max-width: 600px; width: 100%; text-align: center; }
    
    .bedankt-icon { font-size: 64px; margin-bottom: 24px; animation: bounceIn 0.6s ease; }
    
    @keyframes bounceIn { 0% { transform: scale(0); opacity: 0; } 60% { transform: scale(1.1); } 100% { transform: scale(1); opacity: 1; } }
    
    .bedankt-card h1 { font-size: 32px; font-weight: 800; color: var(--dark); margin-bottom: 16px; }
    
    .bedankt-card p { font-size: 16px; color: var(--muted); line-height: 1.7; margin-bottom: 12px; }
    
    .iban-card { background: var(--teal-light); border: 1.5px solid var(--teal); border-radius: var(--radius); padding: 20px 24px; margin: 28px 0; text-align: left; }
    
    .iban-card h4 { font-size: 13px; font-weight: 700; color: var(--teal-dark); text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px; }
    
    .iban-number-row { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
    
    .iban-number { font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 800; color: var(--dark); letter-spacing: 0.08em; }
    
    .iban-copy-btn { background: none; border: 1.5px solid var(--teal); border-radius: 6px; padding: 3px 10px; font-size: 12px; font-weight: 600; color: var(--teal-dark); cursor: pointer; transition: background 0.15s; flex-shrink: 0; }
    
    .iban-copy-btn:hover { background: var(--teal-light); }
    
    .iban-copy-btn.copied { background: var(--teal); color: white; border-color: var(--teal); }
    
    .iban-detail { font-size: 13px; color: var(--teal-dark); line-height: 1.6; }
    
    .stappen { display: flex; flex-direction: column; gap: 12px; margin: 24px 0; text-align: left; }
    
    .stap { display: flex; align-items: flex-start; gap: 14px; padding: 14px 16px; background: var(--bg); border-radius: 10px; border: 1px solid var(--border); }
    
    .stap-num { width: 28px; height: 28px; border-radius: 50%; background: var(--teal); color: white; font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 14px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    
    .stap-text { font-size: 14px; color: var(--text); line-height: 1.5; }
    
    .stap-text strong { color: var(--dark); }
    
    .btn-group { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; margin-top: 32px; }
    
    .btn-outline { background: transparent; color: var(--teal-dark); border: 1.5px solid var(--teal); }
    
    .btn-outline:hover { background: var(--teal-light); }.footer { padding: 32px 24px; text-align: center; font-size: 13px; }
    
    .footer a { color: var(--teal); }
    
    @media (max-width: 600px) {
          .bedankt-card { padding: 36px 24px; }
          .bedankt-card h1 { font-size: 26px; }
          .iban-number { font-size: 18px; }
        }
  </style>
  <script data-goatcounter="https://rc045.goatcounter.com/count"
        async src="//gc.zgo.at/count.js"></script>
</head>
<body>
<a href="#main-content" class="skip-link">Naar hoofdinhoud</a>

<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Terug naar boven">↑</button>

<nav class="nav">
  <div class="nav-inner">
    <a href="index.html" class="nav-logo">
      <img width="400" height="423" src="rc045-logo.png" alt="RC045 logo">
      <div><span class="nav-logo-text">RC045</span></div>
    </a>
    <div style="display:flex; align-items:center; gap:12px;">
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
      <a href="index.html" class="nav-back" data-i18n="nav.back">← Terug naar de site</a>
    </div>
  </div>
</nav>

<main class="main" id="main-content">
  <div class="bedankt-card">
    <div class="bedankt-icon">🏎️</div>
    <h1 id="bd-title" data-i18n="title">Welkom bij RC045!</h1>
    <p id="bd-sub" data-i18n="sub">Je aanmelding is ontvangen. Het bestuur neemt zo snel mogelijk contact met je op om je aanmelding te bevestigen.</p>

    <div class="stappen">
      <div class="stap">
        <div class="stap-num">1</div>
        <div class="stap-text" id="bd-stap1" data-i18n="stap1">Maak de contributie over via onderstaande gegevens. Vermeld je naam duidelijk.</div>
      </div>
      <div class="stap">
        <div class="stap-num">2</div>
        <div class="stap-text" id="bd-stap2" data-i18n="stap2">Wacht op bevestiging van het bestuur per e-mail of WhatsApp.</div>
      </div>
      <div class="stap">
        <div class="stap-num">3</div>
        <div class="stap-text" id="bd-stap3" data-i18n="stap3">Je bent van harte welkom op onze baan zodra je lidmaatschap is bevestigd!</div>
      </div>
    </div>

    <div class="iban-card">
      <h4 id="bd-iban-title" data-i18n="iban.title">Betalingsgegevens</h4>
      <div class="iban-number-row">
        <div class="iban-number" id="iban-number">NL51 RABO 0367 6153 63</div>
        <button class="iban-copy-btn" type="button" data-i18n="iban.copy">📋 Kopieer</button>
      </div>
      <div class="iban-detail">
        <span id="bd-iban-name" data-i18n="iban.name">T.n.v. RC045</span><br>
        <span id="bd-iban-ref" data-i18n="iban.ref">Vermeld bij overboeking: voornaam + achternaam + "contributie RC045 {jaar}"</span>
      </div>
    </div>

    <div class="btn-group">
      <a href="index.html" class="btn btn-primary" id="bd-btn-home" data-i18n="btn.home">🏠 Naar de hoofdpagina</a>
      <a href="index.html#locatie" class="btn btn-outline" id="bd-btn-location" data-i18n="btn.location">📍 Hoe kom ik er?</a>
    </div>
  </div>
</main>

<footer class="footer">
  <p>© 2021 – <span id="footer-year"></span> RC045 · Bashers of the South · <a href="index.html" data-i18n="nav.back">Terug naar de site</a></p>
</footer>

<script src="site-i18n.js"></script>
  <script>
  const i18n = {
    nl: {
      'nav.back': '← Terug naar de site',
      'title': 'Welkom bij RC045!',
      'sub': 'Je aanmelding is ontvangen. Het bestuur neemt zo snel mogelijk contact met je op om je aanmelding te bevestigen.',
      'stap1': 'Maak de contributie over via onderstaande gegevens. Vermeld je naam duidelijk.',
      'stap2': 'Wacht op bevestiging van het bestuur per e-mail of WhatsApp.',
      'stap3': 'Je bent van harte welkom op onze baan zodra je lidmaatschap is bevestigd!',
      'iban.title': 'Betalingsgegevens', 'iban.name': 'T.n.v. RC045',
      'iban.ref': 'Vermeld bij overboeking: voornaam + achternaam + "contributie RC045 {jaar}"',
      'iban.copy': '📋 Kopieer',
      'iban.copied': 'Gekopieerd!',
      'btn.home': '🏠 Naar de hoofdpagina', 'btn.location': '📍 Hoe kom ik er?'
    },
    en: {
      'nav.back': '← Back to the site',
      'title': 'Welcome to RC045!',
      'sub': 'Your registration has been received. The board will contact you as soon as possible to confirm your membership.',
      'stap1': 'Transfer the membership fee using the details below. Include your name clearly.',
      'stap2': 'Wait for confirmation from the board by email or WhatsApp.',
      'stap3': 'You are very welcome at our track once your membership is confirmed!',
      'iban.title': 'Payment details', 'iban.name': 'In the name of RC045',
      'iban.ref': 'Reference: first name + last name + "contributie RC045 {jaar}"',
      'iban.copy': '📋 Copy',
      'iban.copied': 'Copied!',
      'btn.home': '🏠 Go to the homepage', 'btn.location': '📍 How to find us?'
    },
    de: {
      'nav.back': '← Zurück zur Website',
      'title': 'Willkommen bei RC045!',
      'sub': 'Deine Anmeldung ist eingegangen. Der Vorstand wird sich so schnell wie möglich bei dir melden, um deine Mitgliedschaft zu bestätigen.',
      'stap1': 'Überweise den Mitgliedsbeitrag mit den untenstehenden Angaben. Gib deinen Namen deutlich an.',
      'stap2': 'Warte auf die Bestätigung des Vorstands per E-Mail oder WhatsApp.',
      'stap3': 'Du bist herzlich willkommen auf unserer Strecke, sobald deine Mitgliedschaft bestätigt ist!',
      'iban.title': 'Zahlungsdaten', 'iban.name': 'Auf den Namen RC045',
      'iban.ref': 'Verwendungszweck: Vorname + Nachname + "contributie RC045 {jaar}"',
      'iban.copy': '📋 Kopieren',
      'iban.copied': 'Kopiert!',
      'btn.home': '🏠 Zur Hauptseite', 'btn.location': '📍 Wie komme ich hin?'
    }
  };

  // ===== IBAN KOPIEER KNOP =====
  // Zelfde patroon als op aanmelden.html: knoptekst wisselt tijdelijk naar
  // een bevestiging in plaats van een los icoon te wisselen. ibanRaw (zonder
  // spaties) is een aparte, aanpasbare variabele omdat het IBAN-nummer via
  // beheer.php gewijzigd kan worden (data/bedankt.json, zie verderop).
  let ibanRaw = 'NL51RABO0367615363';
  document.querySelectorAll('.iban-copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      var t = i18n[currentLang] || i18n.nl;
      var orig = btn.textContent;
      var gelukt = function() {
        btn.textContent = '✓ ' + (t['iban.copied'] || 'Gekopieerd!');
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('copied'); }, 2000);
      };
      navigator.clipboard.writeText(ibanRaw).then(gelukt).catch(function() {
        var hulp = document.createElement('textarea');
        hulp.value = ibanRaw;
        hulp.style.position = 'fixed';
        hulp.style.opacity = '0';
        document.body.appendChild(hulp);
        hulp.select();
        try { document.execCommand('copy'); gelukt(); } catch (err) { /* stil falen */ }
        document.body.removeChild(hulp);
      });
    });
  });

  

  let currentLang = getInitialLang();

  // ===== REKENTABEL-JAAR (data/rekentabel.json, bewerkbaar via beheer.php) =====
  // Alleen het jaartal is hier nodig, voor de betaalreferentie hieronder.
  // Startwaarde gelijk aan wat voorheen hardcoded stond.
  let rekentabelJaar = '2026';

  function vulRekentabelTokens(tekst) {
    if (!tekst) return tekst;
    return String(tekst).replace(/\{jaar\}/g, rekentabelJaar);
  }

  function vulTokensInDOM() {
    document.querySelectorAll('[data-i18n]').forEach(el => {
      el.textContent = vulRekentabelTokens(el.textContent);
    });
  }

  function setLang(lang) {
    currentLang = lang;
    const t = i18n[lang];
    document.querySelectorAll('[data-i18n]').forEach(el => {
      const key = el.getAttribute('data-i18n');
      if (t[key]) el.textContent = vulRekentabelTokens(t[key]);
    });
    document.querySelectorAll('.lang-flag').forEach(btn => { btn.classList.remove('active'); btn.setAttribute('aria-pressed', 'false'); });
    const activeBtn = document.querySelector(`.lang-flag[onclick="setLang('${lang}')"]`);
    activeBtn.classList.add('active');
    activeBtn.setAttribute('aria-pressed', 'true');
    document.documentElement.lang = lang;
    localStorage.setItem('rc045_lang', lang);
    updateInternalLinks(lang);
    renderBedanktGegevens();
  }

  // ===== INHOUD BEDANKT-PAGINA (data/bedankt.json, bewerkbaar via beheer.php) =====
  // De titel, introtekst, drie stappen en betaalgegevens stonden hiervoor
  // vast in dit bestand. Die tekst blijft hier ook als vangnet staan
  // (zichtbaar zolang het JSON-bestand leeg is of niet laadt), maar
  // data/bedankt.json overschrijft die zodra het bestaat. iban_ref bevat het
  // {jaar}-token, dat wordt hier net als de rest van de pagina vervangen door
  // het actuele contributiejaar.
  var bedanktGegevens = null;
  var bedanktVelden = ['title', 'sub', 'stap1', 'stap2', 'stap3', 'iban_title', 'iban_name', 'iban_ref', 'btn_home', 'btn_location'];
  function renderBedanktGegevens() {
    if (!bedanktGegevens) return;
    if (bedanktGegevens.iban_number) {
      ibanRaw = String(bedanktGegevens.iban_number).replace(/\s+/g, '');
      var numEl = document.getElementById('iban-number');
      if (numEl) numEl.textContent = bedanktGegevens.iban_number;
    }
    bedanktVelden.forEach(function (veld) {
      var bron = bedanktGegevens[veld];
      if (!bron) return;
      var tekst = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
      if (!tekst) return;
      var el = document.getElementById('bd-' + veld.replace(/_/g, '-'));
      if (el) el.textContent = vulRekentabelTokens(tekst);
    });
  }
  fetch('data/bedankt.json', { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d) return;
      bedanktGegevens = d;
      renderBedanktGegevens();
    })
    .catch(function () {});

  document.getElementById('footer-year').textContent = new Date().getFullYear();

  vulTokensInDOM();
  if (currentLang !== 'nl') setLang(currentLang);
  else updateInternalLinks('nl');

  fetch('data/rekentabel.json', { cache: 'no-store' })
    .then(function(r) { return r.ok ? r.json() : null; })
    .then(function(rt) {
      if (!rt || !rt.jaar) return;
      rekentabelJaar = String(rt.jaar);
      vulTokensInDOM();
      // Ook opnieuw toepassen op eventueel al geladen beheer-tekst (iban_ref
      // bevat het {jaar}-token), voor het geval deze fetch later klaar is
      // dan die van data/bedankt.json hierboven.
      renderBedanktGegevens();
    })
    .catch(function() { /* standaardwaarde blijft gelden */ });
</script>

</body>
</html>
