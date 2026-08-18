<?php require_once __DIR__ . '/seo-head.php'; ?><!DOCTYPE html>
<html lang="<?php echo rc045Taal(); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead('aanmelden'); ?>
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
  <style>.page-hero { min-height: 320px; }
    
    .page-hero-bg { position: absolute; top: -80px; left: 0; right: 0; bottom: -80px; background-image: url('images/hero-achtergrond.jpg'); background-size: cover; background-position: center; opacity: 0.35; will-change: transform; }
    
    .page-hero .section-label { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.12em; color: var(--gold); margin-bottom: 12px; }.page-hero p { max-width: 540px; }
    
    .main { max-width: 800px; margin: 0 auto; padding: 48px 24px 80px; }
    
    .contributie-card { background: var(--teal-light); border: 1.5px solid var(--teal); border-radius: var(--radius); padding: 24px 28px; margin-bottom: 32px; }
    
    .contributie-card h3 { font-size: 16px; font-weight: 700; color: var(--teal-dark); margin-bottom: 8px; }
    
    .contributie-bedrag { font-family: 'Poppins', sans-serif; font-size: 32px; font-weight: 800; color: var(--teal-dark); line-height: 1; margin: 8px 0 4px; }
    
    .contributie-sub { font-size: 13px; color: var(--teal-dark); opacity: 0.75; }
    
    .contributie-breakdown { margin-top: 12px; font-size: 13px; color: var(--teal-dark); line-height: 1.6; }
    
    .contributie-placeholder { font-size: 15px; color: var(--muted); font-style: italic; }
    
    .contributie-bedrag { animation: countUp 0.5s ease-out; }
    
    @keyframes countUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    
    .form-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 36px; box-shadow: var(--shadow); }
    
    .form-section-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--gold); margin: 28px 0 16px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
    
    .form-section-title:first-child { margin-top: 0; }
    
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    
    .form-group.full { grid-column: 1 / -1; }
    
    .form-group label span { color: var(--teal); }
    
    .form-group input, .form-group select { padding: 12px 16px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 15px; color: var(--text); background: var(--bg); transition: border-color 0.2s, box-shadow 0.2s; outline: none; width: 100%; }
    
    /* Safari (iOS) duwt de waarde van een datumveld standaard naar rechts en
       maakt het veld zo breed als de kolom. Links uitlijnen en begrenzen. */
    .form-group input[type="date"] { -webkit-appearance: none; appearance: none; text-align: left; max-width: 240px; min-height: 46px; }

    .form-group input:focus, .form-group select:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(58,122,119,0.12); }
    
    .phone-row { display: flex; gap: 8px; }
    
    .phone-row select { width: 120px; flex-shrink: 0; }
    
    .phone-row input { flex: 1; }
    
    .jeugdlid-badge { display: none; margin-top: 8px; padding: 8px 12px; border-radius: 8px; font-size: 13px; font-weight: 600; }
    
    .jeugdlid-badge.jeugd { background: #FEF3C7; color: #92400E; display: flex; align-items: center; gap: 6px; }
    
    .jeugdlid-badge.senior { background: var(--teal-light); color: var(--teal-dark); display: flex; align-items: center; gap: 6px; }
    
    .akkoord-group { display: flex; align-items: flex-start; gap: 12px; padding: 16px; background: var(--bg); border: 1.5px solid var(--border); border-radius: 10px; margin-top: 8px; transition: border-color 0.2s; cursor: pointer; }
    
    .akkoord-group:hover { border-color: var(--teal); }
    
    .akkoord-group input[type="checkbox"] { width: 18px; height: 18px; flex-shrink: 0; margin-top: 2px; accent-color: var(--teal); cursor: pointer; }
    
    .akkoord-group label { font-size: 14px; color: var(--text); line-height: 1.5; cursor: pointer; }
    
    .akkoord-group label a { color: var(--teal); font-weight: 600; text-decoration: underline; }
    
    .form-group label span.req { color: var(--teal); font-weight: 700; }
    
    .field-error { display: none; font-size: 13px; color: #DC2626; margin-top: 4px; font-weight: 500; }
    
    .form-group.has-error .field-error { display: block; }
    
    .input-error { border-color: #DC2626 !important; box-shadow: 0 0 0 3px rgba(220,38,38,0.12) !important; }
    
    .melding { display: none; padding: 12px 16px; border-radius: 8px; font-size: 14px; font-weight: 500; margin-top: 8px; }
    
    .melding.warning { background: #FEF3C7; color: #92400E; }
    
    .melding.error { background: #FEE2E2; color: #DC2626; }
    
    .melding.success { background: var(--teal-light); color: var(--teal-dark); font-size: 16px; font-weight: 700; text-align: center; padding: 20px; }
    
    .iban-copy-wrap { display: flex; align-items: center; gap: 10px; }
    
    .iban-copy-btn { background: none; border: 1.5px solid var(--teal); border-radius: 6px; padding: 3px 10px; font-size: 12px; font-weight: 600; color: var(--teal-dark); cursor: pointer; transition: background 0.15s; flex-shrink: 0; }
    
    .iban-copy-btn:hover { background: var(--teal-light); }
    
    .iban-copy-btn.copied { background: var(--teal); color: white; border-color: var(--teal); }
    
    .iban-card { background: var(--gold-light); border: 1.5px solid var(--gold); border-radius: var(--radius); padding: 20px 24px; margin-top: 16px; }
    
    .iban-card h4 { font-size: 14px; font-weight: 700; color: var(--dark); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.06em; }
    
    .iban-number { font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 800; color: var(--dark); letter-spacing: 0.08em; margin-bottom: 4px; }
    
    .iban-detail { font-size: 13px; color: var(--muted); line-height: 1.6; }
    
    .faq-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 36px; box-shadow: var(--shadow); margin-top: 24px; }
    
    .faq-title { font-size: 20px; font-weight: 700; color: var(--dark); margin-bottom: 24px; }
    
    .faq-item { border-bottom: 1px solid var(--border); padding: 16px 0; cursor: pointer; }
    
    .faq-item:last-child { border-bottom: none; padding-bottom: 0; }
    
    .faq-question { font-size: 15px; font-weight: 600; color: var(--text); display: flex; justify-content: space-between; align-items: center; gap: 16px; }
    
    .faq-question::after { content: '+'; font-size: 20px; color: var(--teal); flex-shrink: 0; transition: transform 0.2s; }
    
    .faq-item.open .faq-question::after { transform: rotate(45deg); }
    
    .faq-answer { font-size: 14px; color: var(--muted); line-height: 1.7; max-height: 0; overflow: hidden; transition: max-height 0.3s ease, padding 0.3s; }
    
    .faq-item.open .faq-answer { max-height: 600px; padding-top: 12px; }
    
    .btn-primary { width: 100%; justify-content: center; margin-top: 24px; font-size: 17px; padding: 16px; }
    
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
    
    @media (max-width: 600px) {
          .form-grid { grid-template-columns: 1fr; }
          .form-card { padding: 24px 20px; }
        }
    
    /* ===== BEDANKT-OVERLAY (verschijnt na een geslaagde aanmelding) ===== */
    /* Dit is een laag binnen de pagina zelf, geen nieuw browservenster,
       dus popupblokkers spelen hier geen rol. */
    .modal-overlay { display: none; position: fixed; inset: 0; z-index: 2000; background: rgba(30,44,19,0.55); backdrop-filter: blur(3px); align-items: center; justify-content: center; padding: 24px; }

    .modal-overlay.open { display: flex; }

    /* svh is de schermhoogte zonder de adres- en knoppenbalk van de browser.
       Zonder die eenheid valt de onderkant van de kaart op iOS achter de
       adresbalk. De vh-regel blijft staan als terugval voor oudere browsers. */
    .modal-card { position: relative; background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); box-shadow: 0 20px 60px rgba(0,0,0,0.30); width: 100%; max-width: 560px; max-height: calc(100vh - 48px); max-height: calc(100svh - 48px); overflow-y: auto; overscroll-behavior: contain; padding: 40px 36px 32px; text-align: center; animation: modalIn 0.28s ease; }

    @keyframes modalIn { from { opacity: 0; transform: translateY(16px) scale(0.98); } to { opacity: 1; transform: none; } }

    .modal-close { position: absolute; top: 10px; right: 12px; background: none; border: none; font-size: 26px; line-height: 1; color: var(--muted); cursor: pointer; padding: 4px 10px; border-radius: 8px; transition: background 0.15s, color 0.15s; }

    .modal-close:hover { background: var(--bg); color: var(--dark); }

    .modal-icon { font-size: 56px; margin-bottom: 16px; }

    .modal-card h2 { font-size: 28px; font-weight: 800; color: var(--dark); margin-bottom: 12px; }

    .modal-sub { font-size: 15px; color: var(--muted); line-height: 1.7; }

    .modal-stappen { display: flex; flex-direction: column; gap: 12px; text-align: left; margin-top: 24px; }

    .modal-stap { display: flex; gap: 12px; align-items: flex-start; }

    .modal-stap-num { flex-shrink: 0; width: 26px; height: 26px; border-radius: 50%; background: var(--teal); color: white; font-family: 'Poppins', sans-serif; font-size: 13px; font-weight: 700; display: flex; align-items: center; justify-content: center; }

    .modal-stap-text { font-size: 14px; color: var(--text); line-height: 1.6; }

    .modal-card .iban-card { text-align: left; margin-top: 24px; }

    .modal-card .iban-copy-wrap { flex-wrap: wrap; }

    .modal-btns { display: flex; gap: 10px; margin-top: 24px; }

    .modal-btn { flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 13px 18px; border-radius: 10px; font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 14px; cursor: pointer; transition: all 0.2s; border: 1.5px solid transparent; }

    .modal-btn-primary { background: var(--teal); color: white; }

    .modal-btn-primary:hover { background: var(--teal-dark); }

    .modal-btn-outline { background: none; border-color: var(--border); color: var(--text); }

    .modal-btn-outline:hover { background: var(--bg); }

    @media (max-width: 560px) {
          .modal-overlay { padding: 14px calc(14px + env(safe-area-inset-right)) calc(14px + env(safe-area-inset-bottom)) calc(14px + env(safe-area-inset-left)); }
          .modal-card { padding: 28px 20px 24px; max-height: calc(100vh - 28px); max-height: calc(100svh - 28px); }
          .modal-icon { font-size: 42px; margin-bottom: 10px; }
          .modal-card h2 { font-size: 22px; margin-bottom: 10px; }
          .modal-sub { font-size: 14px; line-height: 1.6; }
          .modal-stappen { margin-top: 18px; gap: 10px; }
          .modal-card .iban-card { margin-top: 18px; padding: 16px 18px; }
          .modal-card .iban-number { font-size: 17px; letter-spacing: 0.05em; }
          .modal-btns { flex-direction: column; margin-top: 18px; }
        }
  </style>
  <script data-goatcounter="https://rc045.goatcounter.com/count"
        async src="//gc.zgo.at/count.js"></script>
</head>
<body>
<a href="#main-content" class="skip-link">Naar hoofdinhoud</a>

<button class="back-to-top" id="backToTop" onclick="window.scrollTo({top:0,behavior:'smooth'})" aria-label="Terug naar boven">↑</button>

<!-- ===== NAV ===== -->
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
      <li class="nav-lid nav-active"><a href="aanmelden.html" id="nav-join" data-i18n="nav.join">Lid worden</a></li>
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

<!-- ===== HERO ===== -->
<div class="page-hero">
  <div class="page-hero-bg" id="hero-bg"></div>
  <div class="page-hero-gradient"></div>
  <div class="page-hero-content">
    <div class="section-label" id="am-hero-label" data-i18n="hero.label">Lidmaatschap</div>
    <h1 id="am-hero-title" data-i18n="hero.title">Aanmelden als lid</h1>
    <p id="am-hero-sub" data-i18n="hero.sub">Vul het formulier in om je aan te melden bij RC045. Na ontvangst nemen we zo snel mogelijk contact met je op.</p>
  </div>
</div>

<!-- ===== MAIN ===== -->
<main class="main" id="main-content">

  <!-- Contributie berekening -->
  <div class="contributie-card" id="contributie-card">
    <h3 id="am-contrib-title" data-i18n="contrib.title">Jouw contributie {jaar}</h3>
    <div id="contributie-content">
      <p class="contributie-placeholder" id="am-contrib-placeholder" data-i18n="contrib.placeholder">Vul je geboortedatum in om de contributie te berekenen.</p>
    </div>
  </div>

  <!-- Formulier -->
  <div class="form-card">
    <form id="aanmeld-form" action="https://formspree.io/f/mgobjlkl" method="POST" novalidate>
      <div class="hp-field" aria-hidden="true">
        <label for="website">Website</label>
        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
      </div>

      <!-- Persoonsgegevens -->
      <div class="form-section-title" id="am-form-personal" data-i18n="form.personal">Persoonsgegevens</div>
      <div class="form-grid">
        <div class="form-group">
          <label for="voornaam"><span data-i18n="form.firstname">Voornaam</span> <span class="req">*</span></label>
          <input type="text" id="voornaam" name="voornaam">
          <div class="field-error" id="voornaam-error" data-i18n="err.required">Dit veld is verplicht</div>
        </div>
        <div class="form-group">
          <label for="achternaam"><span data-i18n="form.lastname">Achternaam</span> <span class="req">*</span></label>
          <input type="text" id="achternaam" name="achternaam">
          <div class="field-error" id="achternaam-error" data-i18n="err.required">Dit veld is verplicht</div>
        </div>
        <div class="form-group full">
          <label for="geboortedatum"><span data-i18n="form.dob">Geboortedatum</span> <span class="req">*</span></label>
          <input type="date" id="geboortedatum" name="geboortedatum">
          <div class="field-error" id="geboortedatum-error" data-i18n="err.required">Dit veld is verplicht</div>
          <div class="jeugdlid-badge" id="jeugdlid-badge"></div>
        </div>
      </div>

      <!-- Adres -->
      <div class="form-section-title" id="am-form-address" data-i18n="form.address">Adresgegevens</div>
      <div class="form-grid">
        <div class="form-group">
          <label for="straat"><span data-i18n="form.street">Straat</span> <span class="req">*</span></label>
          <input type="text" id="straat" name="straat">
          <div class="field-error" id="straat-error" data-i18n="err.required">Dit veld is verplicht</div>
        </div>
        <div class="form-group">
          <label for="huisnummer"><span data-i18n="form.number">Huisnummer</span> <span class="req">*</span></label>
          <input type="text" id="huisnummer" name="huisnummer">
          <div class="field-error" id="huisnummer-error" data-i18n="err.required">Dit veld is verplicht</div>
        </div>
        <div class="form-group">
          <label for="postcode"><span data-i18n="form.postcode">Postcode</span> <span class="req">*</span></label>
          <input type="text" id="postcode" name="postcode">
          <div class="field-error" id="postcode-error" data-i18n="err.required">Dit veld is verplicht</div>
        </div>
        <div class="form-group">
          <label for="stad"><span data-i18n="form.city">Woonplaats</span> <span class="req">*</span></label>
          <input type="text" id="stad" name="stad">
          <div class="field-error" id="stad-error" data-i18n="err.required">Dit veld is verplicht</div>
        </div>
        <div class="form-group full">
          <label for="land"><span data-i18n="form.country">Land</span> <span class="req">*</span></label>
          <select id="land" name="land">
            <option value="Nederland" selected>Nederland</option>
            <option value="België">België</option>
            <option value="Duitsland">Duitsland</option>
            <option value="Anders">Anders</option>
          </select>
        </div>
      </div>

      <!-- Contactgegevens -->
      <div class="form-section-title" id="am-form-contact" data-i18n="form.contact">Contactgegevens</div>
      <div class="form-grid">
        <div class="form-group full">
          <label for="email"><span data-i18n="form.email">E-mailadres</span></label>
          <input type="email" id="email" name="email">
          <div class="melding warning" id="email-warning"><span data-i18n="warn.email">⚠️ Vul een geldig e-mailadres in (bijv. naam@voorbeeld.nl)</span></div>
        </div>
        <div class="form-group full">
          <label for="mobiel"><span data-i18n="form.mobile">Mobiel nummer</span></label>
          <div class="phone-row">
            <select id="landcode" name="landcode">
              <option value="+31">🇳🇱 +31</option>
              <option value="+32">🇧🇪 +32</option>
              <option value="+49">🇩🇪 +49</option>
            </select>
            <input type="tel" id="mobiel" name="_mobiel_input">
            <input type="hidden" id="mobiel-combined" name="mobiel">
          </div>
          <div class="melding warning" id="mobiel-warning"><span data-i18n="warn.mobile">⚠️ Vul een geldig mobiel nummer in (minimaal 9 cijfers)</span></div>
        </div>
        <div class="form-group full">
          <div class="melding warning" id="contact-warning"><span data-i18n="warn.contact">⚠️ We hebben een e-mailadres of mobiel nummer van je nodig om contact op te nemen</span></div>
        </div>
      </div>

      <!-- Verborgen velden voor Formspree -->
      <input type="hidden" id="jeugdlid-hidden" name="jeugdlid">
      <input type="hidden" id="contributiebedrag-hidden" name="contributiebedrag">

      <!-- Akkoord -->
      <div class="form-section-title" id="am-form-agreement" data-i18n="form.agreement">Akkoordverklaring</div>
      <div class="akkoord-group">
        <input type="checkbox" id="akkoord-reglement" name="akkoord_reglement" value="ja">
        <label for="akkoord-reglement">
          <span data-i18n="form.agree1a">Ik ga akkoord met het</span> <a href="baanreglement.html" data-i18n="form.agree1link">baanreglement</a> <span data-i18n="form.agree1b">van RC045 en verklaar dit gelezen en begrepen te hebben.</span> <span style="color:var(--teal); font-weight:700;">*</span>
        </label>
      </div>
      <div class="akkoord-group" style="margin-top: 10px;">
        <input type="checkbox" id="akkoord-betaling" name="akkoord_betaling" value="ja">
        <label for="akkoord-betaling">
          <span data-i18n="form.agree2">Ik ga akkoord met de contributieverplichting en zal het verschuldigde bedrag z.s.m. overmaken naar</span> <span class="iban-copy-wrap"><strong id="am-iban-number-inline">NL51 RABO 0367 6153 63</strong><button class="iban-copy-btn" type="button" data-i18n="iban.copy">📋 Kopieer</button></span> <span data-i18n="form.agree2b">t.n.v. RC045, onder vermelding van mijn voor- en achternaam + "contributie RC045 {jaar}".</span> <span style="color:var(--teal); font-weight:700;">*</span>
        </label>
      </div>

      <!-- Meldingen -->
      <div class="melding warning" id="akkoord-warning" style="margin-top:12px;"><span data-i18n="warn.agree">⚠️ Je dient akkoord te gaan met beide verklaringen om je te kunnen aanmelden.</span></div>
      <div class="melding error" id="form-error" style="margin-top:12px;"><span data-i18n="warn.error">❌ Er ging iets mis. Probeer het opnieuw of mail naar bestuur@rc045.nl</span></div>
      <div class="melding success" id="form-success" style="margin-top:12px;">
        <span id="am-success-title" data-i18n="success.title">✅ Aanmelding ontvangen! We nemen zo snel mogelijk contact met je op.</span><br><br>
        <span style="font-size:14px; font-weight:400;"><span id="am-success-sub" data-i18n="success.sub">Vergeet niet de contributie over te maken via de betalingsinstructies hierboven.</span></span>
      </div>

      <button type="submit" class="btn btn-primary" id="submit-btn" data-i18n="form.submit">Aanmelden bij RC045 →</button>

    </form>
  </div>

  <div class="faq-card" id="faq-card">
    <div class="faq-title" id="am-faq-title" data-i18n="faq.title">Veelgestelde vragen</div>
    <div id="faq-items">
      <!-- Vragen worden hier ingevuld vanuit data/faq.json, bij te werken via beheer.php -->
    </div>
  </div>

</main>

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

<!-- Bedankt-overlay. Wordt geopend door openBedankt() zodra het formulier
     succesvol is verstuurd. Staat standaard verborgen via .modal-overlay. -->
<div class="modal-overlay" id="bedankt-modal" role="dialog" aria-modal="true" aria-labelledby="bedankt-modal-title">
  <div class="modal-card">
    <button type="button" class="modal-close" id="bedankt-close" aria-label="Sluiten">&times;</button>

    <div class="modal-icon" aria-hidden="true">🏎️</div>
    <h2 id="bedankt-modal-title" data-i18n="bedankt.title">Welkom bij RC045!</h2>
    <p class="modal-sub" id="bd-sub" data-i18n="bedankt.sub">Je aanmelding is ontvangen. Het bestuur neemt zo snel mogelijk contact met je op om je aanmelding te bevestigen.</p>

    <div class="modal-stappen">
      <div class="modal-stap">
        <div class="modal-stap-num" aria-hidden="true">1</div>
        <div class="modal-stap-text" id="bd-stap1" data-i18n="bedankt.stap1">Maak de contributie over via onderstaande gegevens. Vermeld je naam duidelijk.</div>
      </div>
      <div class="modal-stap">
        <div class="modal-stap-num" aria-hidden="true">2</div>
        <div class="modal-stap-text" id="bd-stap2" data-i18n="bedankt.stap2">Wacht op bevestiging van het bestuur per e-mail of WhatsApp.</div>
      </div>
      <div class="modal-stap">
        <div class="modal-stap-num" aria-hidden="true">3</div>
        <div class="modal-stap-text" id="bd-stap3" data-i18n="bedankt.stap3">Je bent van harte welkom op onze baan zodra je lidmaatschap is bevestigd!</div>
      </div>
    </div>

    <div class="iban-card">
      <h4 id="bd-iban-title" data-i18n="iban.title">Betalingsgegevens</h4>
      <div class="iban-copy-wrap">
        <div class="iban-number" id="am-iban-number-modal">NL51 RABO 0367 6153 63</div>
        <button type="button" class="iban-copy-btn" data-i18n="iban.copy">📋 Kopieer</button>
      </div>
      <div class="iban-detail">
        <span id="bd-iban-name" data-i18n="iban.name">T.n.v. RC045</span><br>
        <span id="bd-iban-ref" data-i18n="iban.ref">Vermeld bij overboeking: voornaam + achternaam + "contributie RC045 {jaar}"</span>
      </div>
    </div>

    <div class="modal-btns">
      <a href="index.html" class="modal-btn modal-btn-primary" id="bd-btn-home" data-i18n="bedankt.home">🏠 Naar de hoofdpagina</a>
      <a href="index.html#locatie" class="modal-btn modal-btn-outline" id="bd-btn-location" data-i18n="bedankt.location">📍 Hoe kom ik er?</a>
    </div>
  </div>
</div>

<script src="site-i18n.js"></script>
  <script>
  // ===== VERTALINGEN =====
  const i18n = {
    nl: {
      'nav.about': 'Over ons', 'nav.membership': 'Lidmaatschap', 'nav.track': 'De baan',
      'nav.location': 'Locatie', 'nav.photobook': 'Fotoboek', 'nav.contact': 'Contact', 'nav.join': 'Lid worden', 'footer.credit': 'Website door', 'footer.brand': 'Een gezellige vereniging voor liefhebbers van elektrisch aangedreven RC-auto\'s in de regio Zuid-Limburg. Voor beginners én ervaren rijders.',
      'footer.nav': 'Navigatie', 'footer.origin': 'Het ontstaan', 'footer.media': 'Media', 'footer.photobook': 'Fotoboek', 'footer.calendar': 'Activiteitenkalender', 'footer.join': 'Meedoen',
      'footer.become': 'Lid worden', 'footer.rules': 'Baanreglement', 'footer.sponsor': 'Sponsoring', 'footer.sponsors.title': 'Met dank aan onze sponsoren', 'guest.tag': 'Gastrijden',
      'hero.label': 'Lidmaatschap', 'hero.title': 'Aanmelden als lid',
      'hero.sub': 'Vul het formulier in om je aan te melden bij RC045. Na ontvangst nemen we zo snel mogelijk contact met je op.',
      'contrib.title': 'Jouw contributie {jaar}',
      'contrib.placeholder': 'Vul je geboortedatum in om de contributie te berekenen.',
      'form.personal': 'Persoonsgegevens', 'form.address': 'Adresgegevens',
      'form.contact': 'Contactgegevens', 'form.agreement': 'Akkoordverklaring',
      'form.firstname': 'Voornaam', 'form.lastname': 'Achternaam',
      'form.dob': 'Geboortedatum', 'form.street': 'Straat',
      'form.number': 'Huisnummer', 'form.postcode': 'Postcode',
      'form.city': 'Woonplaats', 'form.country': 'Land',
      'form.email': 'E-mailadres', 'form.mobile': 'Mobiel nummer',
      'form.agree1a': 'Ik ga akkoord met het', 'form.agree1link': 'baanreglement',
      'form.agree1b': 'van RC045 en verklaar dit gelezen en begrepen te hebben.',
      'form.agree2': 'Ik ga akkoord met de contributieverplichting en zal het verschuldigde bedrag z.s.m. overmaken naar',
      'form.agree2b': 't.n.v. RC045, onder vermelding van mijn voor- en achternaam + "contributie RC045 {jaar}".',
      'iban.title': 'Betalingsgegevens', 'iban.name': 'T.n.v. RC045',
      'iban.ref': 'Vermeld bij overboeking: voornaam + achternaam + "contributie RC045 {jaar}"',
      'iban.copy': '📋 Kopieer', 'iban.copied': 'Gekopieerd!',
      'bedankt.title': 'Welkom bij RC045!',
      'bedankt.sub': 'Je aanmelding is ontvangen. Het bestuur neemt zo snel mogelijk contact met je op om je aanmelding te bevestigen.',
      'bedankt.stap1': 'Maak de contributie over via onderstaande gegevens. Vermeld je naam duidelijk.',
      'bedankt.stap2': 'Wacht op bevestiging van het bestuur per e-mail of WhatsApp.',
      'bedankt.stap3': 'Je bent van harte welkom op onze baan zodra je lidmaatschap is bevestigd!',
      'bedankt.home': '🏠 Naar de hoofdpagina', 'bedankt.location': '📍 Hoe kom ik er?',
      'bedankt.close': 'Sluiten',
      'form.sent': '✓ Aanmelding verzonden', 'form.sending': '⏳ Aanmelding verzenden...',
      'faq.title': 'Veelgestelde vragen',
      'form.submit': 'Aanmelden bij RC045 →',
      'err.required': 'Dit veld is verplicht.',
      'warn.contact': '⚠️ We hebben een e-mailadres of mobiel nummer van je nodig om contact op te nemen.',
      'warn.email': '⚠️ Vul een geldig e-mailadres in (bijv. naam@voorbeeld.nl).',
      'warn.mobile': '⚠️ Vul een geldig mobiel nummer in (minimaal 9 cijfers).',
      'warn.agree': '⚠️ Je dient akkoord te gaan met beide verklaringen om je te kunnen aanmelden.',
      'warn.error': '❌ Er ging iets mis. Probeer het opnieuw of mail naar bestuur@rc045.nl',
      'success.title': '✅ Aanmelding ontvangen! We nemen zo snel mogelijk contact met je op.',
      'success.sub': 'Vergeet niet de contributie over te maken via de betalingsinstructies hierboven.',
      'jeugd.badge': '🎯 Jeugdlid (t/m {leeftijd} jaar)', 'senior.badge': '✓ Seniorlid ({leeftijdplus}+)',
      'contrib.jeugd': 'Jeugdlid', 'contrib.senior': 'Seniorlid',
      'contrib.inschrijving': 'inschrijving in', 'contrib.prorata': 'je betaalt vanaf', 'contrib.label': 'Contributie',
      'contrib.inschrijfkosten': 'Eenmalige inschrijfkosten', 'contrib.totaal': 'Totaal over te maken',
      'contrib.volledig': 'Contributie {jaarnext}', 'contrib.niet_overmaken': 'nog niet over te maken',
    },
    en: {
      'nav.about': 'About us', 'nav.membership': 'Membership', 'nav.track': 'The track',
      'nav.location': 'Location', 'nav.photobook': 'Photo book', 'nav.contact': 'Contact', 'nav.join': 'Become a member', 'footer.credit': 'Website by', 'footer.brand': 'A friendly club for enthusiasts of electrically powered RC cars in the South Limburg region. For beginners and experienced riders alike.',
      'footer.nav': 'Navigation', 'footer.origin': 'Our history', 'footer.media': 'Media', 'footer.photobook': 'Photo book', 'footer.calendar': 'Events calendar', 'footer.join': 'Get involved',
      'footer.become': 'Become a member', 'footer.rules': 'Track regulations', 'footer.sponsor': 'Sponsorship', 'footer.sponsors.title': 'With thanks to our sponsors', 'guest.tag': 'Guest riding',
      'hero.label': 'Membership', 'hero.title': 'Register as a member',
      'hero.sub': 'Fill in the form to register with RC045. We will contact you as soon as possible after receiving your registration.',
      'contrib.title': 'Your membership fee {jaar}',
      'contrib.placeholder': 'Enter your date of birth to calculate the membership fee.',
      'form.personal': 'Personal details', 'form.address': 'Address details',
      'form.contact': 'Contact details', 'form.agreement': 'Declaration of agreement',
      'form.firstname': 'First name', 'form.lastname': 'Last name',
      'form.dob': 'Date of birth', 'form.street': 'Street',
      'form.number': 'House number', 'form.postcode': 'Postcode',
      'form.city': 'City', 'form.country': 'Country',
      'form.email': 'Email address', 'form.mobile': 'Mobile number',
      'form.agree1a': 'I agree to the', 'form.agree1link': 'track regulations',
      'form.agree1b': 'of RC045 and declare that I have read and understood them.',
      'form.agree2': 'I agree to the membership fee obligation and will transfer the amount due as soon as possible to',
      'form.agree2b': 'in the name of RC045, quoting my first and last name plus "contributie RC045 {jaar}".',
      'iban.title': 'Payment details', 'iban.name': 'In the name of RC045',
      'iban.ref': 'Reference: first name + last name + "contributie RC045 {jaar}"',
      'iban.copy': '📋 Copy', 'iban.copied': 'Copied!',
      'bedankt.title': 'Welcome to RC045!',
      'bedankt.sub': 'Your registration has been received. The board will contact you as soon as possible to confirm your membership.',
      'bedankt.stap1': 'Transfer the membership fee using the details below. Include your name clearly.',
      'bedankt.stap2': 'Wait for confirmation from the board by email or WhatsApp.',
      'bedankt.stap3': 'You are very welcome at our track once your membership is confirmed!',
      'bedankt.home': '🏠 Go to the homepage', 'bedankt.location': '📍 How to find us?',
      'bedankt.close': 'Close',
      'form.sent': '✓ Registration sent', 'form.sending': '⏳ Sending registration...',
      'faq.title': 'Frequently asked questions',
      'form.submit': 'Register with RC045 →',
      'err.required': 'This field is required.',
      'warn.contact': '⚠️ We need an email address or mobile number to contact you.',
      'warn.email': '⚠️ Please enter a valid email address (e.g. name@example.com).',
      'warn.mobile': '⚠️ Please enter a valid mobile number (at least 9 digits).',
      'warn.agree': '⚠️ You must agree to both declarations in order to register.',
      'warn.error': '❌ Something went wrong. Please try again or email bestuur@rc045.nl',
      'success.title': '✅ Registration received! We will contact you as soon as possible.',
      'success.sub': 'Don\'t forget to transfer the membership fee using the payment instructions above.',
      'jeugd.badge': '🎯 Youth member (up to {leeftijd})', 'senior.badge': '✓ Senior member ({leeftijdplus}+)',
      'contrib.jeugd': 'Youth member', 'contrib.senior': 'Senior member',
      'contrib.inschrijving': 'registration in', 'contrib.prorata': 'you pay from', 'contrib.label': 'Membership fee',
      'contrib.inschrijfkosten': 'One-time registration fee', 'contrib.totaal': 'Total to transfer',
      'contrib.volledig': 'Membership fee {jaarnext}', 'contrib.niet_overmaken': 'not due yet',
    },
    de: {
      'nav.about': 'Über uns', 'nav.membership': 'Mitgliedschaft', 'nav.track': 'Die Strecke',
      'nav.location': 'Standort', 'nav.photobook': 'Fotobuch', 'nav.contact': 'Kontakt', 'nav.join': 'Mitglied werden', 'footer.credit': 'Website von', 'footer.brand': 'Ein freundlicher Verein für Liebhaber von elektrisch angetriebenen RC-Autos in der Region Südlimburg. Für Anfänger und erfahrene Fahrer.',
      'footer.nav': 'Navigation', 'footer.origin': 'Unsere Geschichte', 'footer.media': 'Medien', 'footer.photobook': 'Fotobuch', 'footer.calendar': 'Veranstaltungskalender', 'footer.join': 'Mitmachen',
      'footer.become': 'Mitglied werden', 'footer.rules': 'Streckenreglement', 'footer.sponsor': 'Sponsoring', 'footer.sponsors.title': 'Mit Dank an unsere Sponsoren', 'guest.tag': 'Gastfahren',
      'hero.label': 'Mitgliedschaft', 'hero.title': 'Als Mitglied anmelden',
      'hero.sub': 'Fülle das Formular aus, um dich bei RC045 anzumelden. Nach Eingang melden wir uns so schnell wie möglich bei dir.',
      'contrib.title': 'Dein Mitgliedsbeitrag {jaar}',
      'contrib.placeholder': 'Gib dein Geburtsdatum ein, um den Mitgliedsbeitrag zu berechnen.',
      'form.personal': 'Persönliche Daten', 'form.address': 'Adressdaten',
      'form.contact': 'Kontaktdaten', 'form.agreement': 'Einverständniserklärung',
      'form.firstname': 'Vorname', 'form.lastname': 'Nachname',
      'form.dob': 'Geburtsdatum', 'form.street': 'Straße',
      'form.number': 'Hausnummer', 'form.postcode': 'Postleitzahl',
      'form.city': 'Wohnort', 'form.country': 'Land',
      'form.email': 'E-Mail-Adresse', 'form.mobile': 'Handynummer',
      'form.agree1a': 'Ich stimme dem', 'form.agree1link': 'Streckenreglement',
      'form.agree1b': 'von RC045 zu und erkläre, es gelesen und verstanden zu haben.',
      'form.agree2': 'Ich stimme der Beitragspflicht zu und überweise den fälligen Betrag schnellstmöglich an',
      'form.agree2b': 'lautend auf RC045, unter Angabe meines Vor- und Nachnamens sowie "contributie RC045 {jaar}".',
      'iban.title': 'Zahlungsdaten', 'iban.name': 'Auf den Namen RC045',
      'iban.ref': 'Verwendungszweck: Vorname + Nachname + "contributie RC045 {jaar}"',
      'iban.copy': '📋 Kopieren', 'iban.copied': 'Kopiert!',
      'bedankt.title': 'Willkommen bei RC045!',
      'bedankt.sub': 'Deine Anmeldung ist eingegangen. Der Vorstand wird sich so schnell wie möglich bei dir melden, um deine Mitgliedschaft zu bestätigen.',
      'bedankt.stap1': 'Überweise den Mitgliedsbeitrag mit den untenstehenden Angaben. Gib deinen Namen deutlich an.',
      'bedankt.stap2': 'Warte auf die Bestätigung des Vorstands per E-Mail oder WhatsApp.',
      'bedankt.stap3': 'Du bist herzlich willkommen auf unserer Strecke, sobald deine Mitgliedschaft bestätigt ist!',
      'bedankt.home': '🏠 Zur Hauptseite', 'bedankt.location': '📍 Wie komme ich hin?',
      'bedankt.close': 'Schließen',
      'form.sent': '✓ Anmeldung gesendet', 'form.sending': '⏳ Anmeldung wird gesendet...',
      'faq.title': 'Häufig gestellte Fragen',
      'form.submit': 'Bei RC045 anmelden →',
      'err.required': 'Dieses Feld ist erforderlich.',
      'warn.contact': '⚠️ Wir benötigen eine E-Mail-Adresse oder Handynummer, um dich zu kontaktieren.',
      'warn.email': '⚠️ Bitte gib eine gültige E-Mail-Adresse ein (z.B. name@beispiel.de).',
      'warn.mobile': '⚠️ Bitte gib eine gültige Handynummer ein (mindestens 9 Ziffern).',
      'warn.agree': '⚠️ Du musst beiden Erklärungen zustimmen, um dich anmelden zu können.',
      'warn.error': '❌ Etwas ist schiefgelaufen. Bitte versuche es erneut oder schreibe an bestuur@rc045.nl',
      'success.title': '✅ Anmeldung erhalten! Wir werden uns so schnell wie möglich bei dir melden.',
      'success.sub': 'Vergiss nicht, den Mitgliedsbeitrag gemäß den obigen Zahlungsanweisungen zu überweisen.',
      'jeugd.badge': '🎯 Jugendmitglied (bis {leeftijd} Jahre)', 'senior.badge': '✓ Seniorenmitglied ({leeftijdplus}+)',
      'contrib.jeugd': 'Jugendmitglied', 'contrib.senior': 'Seniorenmitglied',
      'contrib.inschrijving': 'Anmeldung im', 'contrib.prorata': 'du zahlst ab', 'contrib.label': 'Mitgliedsbeitrag',
      'contrib.inschrijfkosten': 'Einmalige Anmeldegebühr', 'contrib.totaal': 'Gesamt zu überweisen',
      'contrib.volledig': 'Mitgliedsbeitrag {jaarnext}', 'contrib.niet_overmaken': 'noch nicht fällig',
    }
  };

  

  let currentLang = getInitialLang();
  const maandNamenNL = ['januari','februari','maart','april','mei','juni','juli','augustus','september','oktober','november','december'];
  const maandNamenEN = ['January','February','March','April','May','June','July','August','September','October','November','December'];
  const maandNamenDE = ['Januar','Februar','März','April','Mai','Juni','Juli','August','September','Oktober','November','Dezember'];

  function getMaandNamen() {
    return currentLang === 'en' ? maandNamenEN : currentLang === 'de' ? maandNamenDE : maandNamenNL;
  }

  // ===== REKENTABEL CONTRIBUTIE (data/rekentabel.json, bewerkbaar via beheer.php) =====
  // Startwaarden hieronder zijn gelijk aan wat voorheen hardcoded stond, dus
  // er verandert niets vóórdat de fetch verderop is teruggekomen (of als
  // data/rekentabel.json nog niet bestaat). Moet vóór de eerste mogelijke
  // aanroep van setLang()/berekenContributie() staan (zie "if (currentLang
  // !== 'nl') setLang(...)" verderop), anders zijn deze nog niet
  // geïnitialiseerd op het moment dat ze voor het eerst gebruikt worden.
  let rekentabelJaar = '2026';
  let rekentabelLeeftijdGrens = 15;
  let rekentabelInschrijfkosten = 10;
  let volledigeContributieSenior = 100;
  let volledigeContributieJeugd = 50;
  // Jaarcontributie voor het jaar na rekentabelJaar. null = nog niet
  // vastgesteld door de ledenvergadering; dan blijft het bedrag van dit jaar
  // staan, precies zoals het ging voordat deze velden bestonden.
  let volgendJaarContributieSenior = null;
  let volgendJaarContributieJeugd = null;
  let tabelSenior = {
    1: 92, 2: 83, 3: 75, 4: 67, 5: 58, 6: 50,
    7: 42, 8: 33, 9: 25, 10: 17, 11: 8, 12: null
  };
  let tabelJeugd = {
    1: 46, 2: 42, 3: 38, 4: 33, 5: 29, 6: 25,
    7: 21, 8: 17, 9: 13, 10: 8, 11: 4.16, 12: null
  };

  // Zelfde afronding als rekentabelProRata() in beheer.php: pro-rata deel
  // van het jaarbedrag, naar hele euro's afgerond. Maand 12 (december)
  // blijft altijd het speciale geval (alleen inschrijfkosten).
  function rekentabelProRataJS(jaarbedrag) {
    const tabel = {};
    for (let m = 1; m <= 11; m++) tabel[m] = Math.round(jaarbedrag * (12 - m) / 12);
    tabel[12] = null;
    return tabel;
  }

  // Het bedrag dat een nieuw lid volgend jaar betaalt: het apart ingestelde
  // bedrag voor dat jaar, of anders dat van dit jaar.
  function contributieVolgendJaar(isJeugd) {
    if (isJeugd) {
      return volgendJaarContributieJeugd !== null ? volgendJaarContributieJeugd : volledigeContributieJeugd;
    }
    return volgendJaarContributieSenior !== null ? volgendJaarContributieSenior : volledigeContributieSenior;
  }

  // Leeg veld, ontbrekend veld of iets onleesbaars wordt null: "niet
  // ingevuld", niet 0. Een 0 zou anders als gratis lidmaatschap op de pagina
  // belanden.
  function rekentabelGetalOfNull(waarde) {
    if (waarde === '' || waarde === null || waarde === undefined) return null;
    const getal = Number(waarde);
    return isNaN(getal) ? null : getal;
  }

  // Vervangt {jaar}/{jaarnext}/{leeftijd}/{leeftijdplus} in een vertaalde
  // tekst door de actuele rekentabelwaarden. Een string zonder die tokens
  // komt ongewijzigd terug.
  function vulRekentabelTokens(tekst) {
    if (!tekst) return tekst;
    return String(tekst)
      .replace(/\{jaar\}/g, rekentabelJaar)
      .replace(/\{jaarnext\}/g, String(Number(rekentabelJaar) + 1))
      .replace(/\{leeftijd\}/g, rekentabelLeeftijdGrens)
      .replace(/\{leeftijdplus\}/g, rekentabelLeeftijdGrens + 1);
  }

  // Ververst alle data-i18n-elementen die nog de rauwe (Nederlandse)
  // HTML-tekst tonen: nodig omdat setLang('nl') bij eerste load bewust wordt
  // overgeslagen (zie "if (currentLang !== 'nl')" verderop), dus die
  // elementen krijgen hun tokens pas hier vervangen.
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
    var sluitKnop = document.getElementById('bedankt-close');
    if (sluitKnop) sluitKnop.setAttribute('aria-label', t['bedankt.close'] || 'Sluiten');
    document.documentElement.lang = lang;
    localStorage.setItem('rc045_lang', lang);
    const currentUrl = new URL(window.location.href);
    if (lang === 'nl') currentUrl.searchParams.delete('lang');
    else currentUrl.searchParams.set('lang', lang);
    history.replaceState(null, '', currentUrl.pathname + currentUrl.search + currentUrl.hash);
    updateInternalLinks(lang);
    berekenContributie();
    renderFaq();
    renderSponsorCta();
    renderNavFooterTeksten();
    renderAanmeldenTekst();
    renderBedanktModalTekst();
  }

  // ===== TEKST AANMELDPAGINA (data/aanmelden.json, bewerkbaar via beheer.php) =====
  // Hero, contributiekop, formulierkoppen, bevestigingsmelding en FAQ-titel
  // stonden hiervoor vast in dit bestand. Die tekst blijft hier ook als
  // vangnet staan, maar data/aanmelden.json overschrijft die zodra het
  // bestaat. contrib_title bevat het {jaar}-token en loopt via
  // vulRekentabelTokens(), net als de rest van de pagina.
  var aanmeldenData = null;
  var aanmeldenVelden = ['hero_label', 'hero_title', 'hero_sub', 'contrib_title', 'contrib_placeholder', 'form_personal', 'form_address', 'form_contact', 'form_agreement', 'success_title', 'success_sub', 'faq_title'];
  function renderAanmeldenTekst() {
    if (!aanmeldenData) return;
    aanmeldenVelden.forEach(function (veld) {
      var bron = aanmeldenData[veld];
      if (!bron) return;
      var tekst = (bron[currentLang] && String(bron[currentLang]).trim()) ? bron[currentLang] : (bron.nl || '');
      if (!tekst) return;
      var el = document.getElementById('am-' + veld.replace(/_/g, '-'));
      if (el) el.textContent = vulRekentabelTokens(tekst);
    });
  }
  fetch('data/aanmelden.json', { cache: 'no-store' })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
      if (!d) return;
      aanmeldenData = d;
      renderAanmeldenTekst();
    })
    .catch(function () {});

  // ===== BEDANKT-OVERLAY EN IBAN (data/bedankt.json, bewerkbaar via beheer.php) =====
  // De overlay die opent na een geslaagde aanmelding toont dezelfde tekst en
  // betaalgegevens als bedankt.html, dus die lezen hier bewust hetzelfde
  // bestand: één plek in beheer om de IBAN of tekst aan te passen, in plaats
  // van twee plekken die uit elkaar kunnen gaan lopen. ibanRaw (zonder
  // spaties) wordt ook gebruikt door de kopieerknoppen hieronder.
  var bedanktModalData = null;
  var ibanGeformatteerd = 'NL51 RABO 0367 6153 63';
  var bedanktModalVelden = ['sub', 'stap1', 'stap2', 'stap3', 'iban_title', 'iban_name', 'iban_ref', 'btn_home', 'btn_location'];
  function renderBedanktModalTekst() {
    if (!bedanktModalData) return;
    if (bedanktModalData.iban_number) {
      ibanGeformatteerd = bedanktModalData.iban_number;
      var inlineEl = document.getElementById('am-iban-number-inline');
      if (inlineEl) inlineEl.textContent = ibanGeformatteerd;
      var modalNumEl = document.getElementById('am-iban-number-modal');
      if (modalNumEl) modalNumEl.textContent = ibanGeformatteerd;
    }
    var titelBron = bedanktModalData.title;
    if (titelBron) {
      var titel = (titelBron[currentLang] && String(titelBron[currentLang]).trim()) ? titelBron[currentLang] : (titelBron.nl || '');
      var titelEl = document.getElementById('bedankt-modal-title');
      if (titel && titelEl) titelEl.textContent = titel;
    }
    bedanktModalVelden.forEach(function (veld) {
      var bron = bedanktModalData[veld];
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
      bedanktModalData = d;
      renderBedanktModalTekst();
    })
    .catch(function () {});

  document.getElementById('footer-year').textContent = new Date().getFullYear();

  // Vult {jaar}/{leeftijd}-tokens in de rauwe (Nederlandse) HTML-tekst in,
  // ook als setLang() hieronder wordt overgeslagen (nl-standaardgeval).
  vulTokensInDOM();

  // Pas opgeslagen/URL-taal direct toe en update links bij eerste load
  if (currentLang !== 'nl') setLang(currentLang);
  else updateInternalLinks('nl');

  // ===== PARALLAX =====
  const heroBg = document.getElementById('hero-bg');
  window.addEventListener('scroll', function() {
    const scrollY = window.scrollY;
    if (scrollY < window.innerHeight * 1.2) heroBg.style.transform = `translateY(${scrollY * 0.5}px)`;
  }, { passive: true });

  function berekenContributie() {
    const t = i18n[currentLang];
    const maandNamen = getMaandNamen();
    const geboortedatum = document.getElementById('geboortedatum').value;
    const contentEl = document.getElementById('contributie-content');

    if (!geboortedatum) {
      contentEl.innerHTML = `<p class="contributie-placeholder">${t['contrib.placeholder']}</p>`;
      document.getElementById('jeugdlid-hidden').value = '';
      document.getElementById('contributiebedrag-hidden').value = '';
      return;
    }

    const geb = new Date(geboortedatum);
    const nu = new Date();
    const maand = nu.getMonth() + 1; // 1-12
    const jaar = nu.getFullYear();

    // Leeftijd berekenen
    let leeftijd = jaar - geb.getFullYear();
    const verjaardag = new Date(jaar, geb.getMonth(), geb.getDate());
    if (nu < verjaardag) leeftijd--;

    const isJeugd = leeftijd <= rekentabelLeeftijdGrens;
    const tabel = isJeugd ? tabelJeugd : tabelSenior;
    const contributie = tabel[maand];

    // Update badge
    const badge = document.getElementById('jeugdlid-badge');
    badge.className = 'jeugdlid-badge ' + (isJeugd ? 'jeugd' : 'senior');
    badge.textContent = vulRekentabelTokens(isJeugd ? t['jeugd.badge'] : t['senior.badge']);

    document.getElementById('jeugdlid-hidden').value = isJeugd ? 'Ja' : 'Nee';

    if (contributie === null) {
      const volledigeContributie = contributieVolgendJaar(isJeugd);
      document.getElementById('contributiebedrag-hidden').value = `€${rekentabelInschrijfkosten} (${t['contrib.inschrijfkosten']}, nu) + €${volledigeContributie} (${vulRekentabelTokens(t['contrib.volledig'])}, ${t['contrib.niet_overmaken']})`;

      contentEl.innerHTML = `
        <div class="contributie-bedrag">€${rekentabelInschrijfkosten},-</div>
        <div class="contributie-sub">${isJeugd ? t['contrib.jeugd'] : t['contrib.senior']} · ${t['contrib.inschrijving']} december</div>
        <div class="contributie-breakdown">
          ${t['contrib.inschrijfkosten']}: €${rekentabelInschrijfkosten}<br>
          <strong style="font-size:16px;">${t['contrib.totaal']}: €${rekentabelInschrijfkosten}</strong><br><br>
          ${vulRekentabelTokens(t['contrib.volledig'])}: €${volledigeContributie} (${t['contrib.niet_overmaken']})
        </div>
      `;
      return;
    }

    const totaal = contributie + rekentabelInschrijfkosten;
    const volledigeContributie = contributieVolgendJaar(isJeugd);
    document.getElementById('contributiebedrag-hidden').value = `€${contributie} + €${rekentabelInschrijfkosten} = €${totaal} (${t['contrib.totaal']}) + €${volledigeContributie} (${vulRekentabelTokens(t['contrib.volledig'])}, ${t['contrib.niet_overmaken']})`;

    contentEl.innerHTML = `
      <div class="contributie-bedrag">€${totaal},-</div>
      <div class="contributie-sub">${isJeugd ? t['contrib.jeugd'] : t['contrib.senior']} · ${t['contrib.inschrijving']} ${maandNamen[maand - 1]} ${jaar}</div>
      <div class="contributie-breakdown">
        ${t['contrib.label']}: €${contributie} (${t['contrib.prorata']} ${maandNamen[maand] || maandNamen[0]})<br>
        ${t['contrib.inschrijfkosten']}: €${rekentabelInschrijfkosten}<br>
        <strong style="font-size:16px;">${t['contrib.totaal']}: €${totaal}</strong><br><br>
        ${vulRekentabelTokens(t['contrib.volledig'])}: €${volledigeContributie} (${t['contrib.niet_overmaken']})
      </div>
    `;
  }

  document.getElementById('geboortedatum').addEventListener('change', berekenContributie);

  // ===== FORMULIER VALIDATIE & VERZENDING =====
  const form = document.getElementById('aanmeld-form');
  const verplichteVelden = ['voornaam', 'achternaam', 'geboortedatum', 'straat', 'huisnummer', 'postcode', 'stad'];

  function setFieldError(id, hasError) {
    const input = document.getElementById(id);
    const group = input.closest('.form-group');
    if (hasError) {
      input.classList.add('input-error');
      group.classList.add('has-error');
    } else {
      input.classList.remove('input-error');
      group.classList.remove('has-error');
    }
  }

  form.addEventListener('submit', async function(e) {
    e.preventDefault();

    // Honeypot: als dit verborgen veld is ingevuld, is het een bot. Stilletjes niets doen.
    if (document.getElementById('website').value.trim() !== '') {
      return;
    }

    const btn = document.getElementById('submit-btn');
    const emailWarning = document.getElementById('email-warning');
    const mobielWarning = document.getElementById('mobiel-warning');
    const contactWarning = document.getElementById('contact-warning');
    const akkoordWarning = document.getElementById('akkoord-warning');
    const formError = document.getElementById('form-error');
    const formSuccess = document.getElementById('form-success');

    // Reset alle meldingen
    [emailWarning, mobielWarning, contactWarning, akkoordWarning, formError, formSuccess].forEach(el => el.style.display = 'none');
    verplichteVelden.forEach(id => setFieldError(id, false));

    let firstErrorEl = null;

    // Check verplichte tekstvelden
    verplichteVelden.forEach(id => {
      const val = document.getElementById(id).value.trim();
      if (!val) {
        setFieldError(id, true);
        if (!firstErrorEl) firstErrorEl = document.getElementById(id);
      }
    });

    if (firstErrorEl) {
      firstErrorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
      firstErrorEl.focus();
      return;
    }

    // Email + mobiel: minimaal één verplicht, en als ingevuld moet het geldig zijn
    const email = document.getElementById('email').value.trim();
    const mobielInput = document.getElementById('mobiel').value.trim();
    const mobielCijfers = mobielInput.replace(/[\s\-()]/g, '');

    if (!email && !mobielInput) {
      contactWarning.style.display = 'block';
      document.getElementById('email').scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      emailWarning.style.display = 'block';
      setInputError('email', true);
      document.getElementById('email').scrollIntoView({ behavior: 'smooth', block: 'center' });
      document.getElementById('email').focus();
      return;
    }
    setInputError('email', false);

    if (mobielInput && (!/^\d+$/.test(mobielCijfers) || mobielCijfers.length < 9)) {
      mobielWarning.style.display = 'block';
      setInputError('mobiel', true);
      document.getElementById('mobiel').scrollIntoView({ behavior: 'smooth', block: 'center' });
      document.getElementById('mobiel').focus();
      return;
    }
    setInputError('mobiel', false);

    // Combineer landcode + mobiel
    if (mobielInput) {
      const landcode = document.getElementById('landcode').value;
      document.getElementById('mobiel-combined').value = landcode + ' ' + mobielInput;
    }

    // Akkoord validatie
    const akkoordReglement = document.getElementById('akkoord-reglement').checked;
    const akkoordBetaling = document.getElementById('akkoord-betaling').checked;
    if (!akkoordReglement || !akkoordBetaling) {
      akkoordWarning.style.display = 'block';
      akkoordWarning.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }

    // Verzenden
    btn.textContent = (i18n[currentLang] || i18n.nl)['form.sending'];
    btn.disabled = true;

    try {
      const res = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'Accept': 'application/json' }
      });
      if (res.ok) {
        var tt = i18n[currentLang] || i18n.nl;
        // Knop blijft uitgeschakeld staan, zodat er niet twee keer wordt verzonden.
        btn.setAttribute('data-i18n', 'form.sent');
        btn.textContent = tt['form.sent'];
        btn.disabled = true;
        // Bevestiging blijft ook in de pagina staan nadat de overlay is gesloten.
        formSuccess.style.display = 'block';
        // Voorheen telde GoatCounter de weergave van bedankt.html als aanmelding.
        // De overlay is geen paginaweergave, dus die telling gebeurt nu hier.
        if (window.goatcounter && typeof window.goatcounter.count === 'function') {
          window.goatcounter.count({ path: 'aanmelding-verzonden', title: 'Aanmelding verzonden', event: true });
        }
        openBedankt();
        // Daarnaast naar onze eigen server, zodat de aanmelding meteen in het
        // ledenbestand komt met de status "in verificatie". Bewust pas hier,
        // na Formspree: gaat dit mis, dan staat de aanmelding nog steeds in de
        // mail aan het bestuur en merkt de bezoeker er niets van.
        fetch('aanmelden-ontvangst.php', {
          method: 'POST',
          body: new FormData(form)
        }).catch(function() { /* stil falen, de mail is al onderweg */ });
      } else {
        throw new Error();
      }
    } catch {
      formError.style.display = 'block';
      btn.textContent = (i18n[currentLang] || i18n.nl)['form.submit'];
      btn.disabled = false;
    }
  });
  // ===== FAQ (openklappen, werkt ook voor dynamisch toegevoegde vragen) =====
  document.getElementById('faq-card').addEventListener('click', function(e) {
    var vraag = e.target.closest('.faq-question');
    if (!vraag) return;
    vraag.closest('.faq-item').classList.toggle('open');
  });

  // ===== VEELGESTELDE VRAGEN (data/faq.json, bijwerken via beheer.php) =====
  // Nederlands is verplicht per vraag; Engels/Duits vallen terug op Nederlands
  // als het bestuur die niet heeft ingevuld. renderFaq() tekent opnieuw bij
  // elke taalwissel (aangeroepen vanuit setLang), met de dan geldende taal.
  var faqData = null;

  function renderFaq() {
    if (!faqData) return;
    var container = document.getElementById('faq-items');
    container.innerHTML = ''; // eigen container, alleen door onszelf gevuld
    faqData.forEach(function(item) {
      if (!item || !item.q) return;

      // Ondersteunt zowel het oude platte formaat (q/a als tekst, van vóór
      // de talenvelden) als het huidige formaat ({nl,en,de}), zodat er nooit
      // "undefined" verschijnt als data/faq.json nog niet opnieuw is opgeslagen.
      var qIsTekst = typeof item.q === 'string';
      var vraagNl = qIsTekst ? item.q : (item.q.nl || '');
      if (!vraagNl || !String(vraagNl).trim()) return;

      var vraagTekst = vraagNl;
      var antwoordTekst = qIsTekst ? (item.a || '') : '';

      if (!qIsTekst) {
        vraagTekst = (item.q[currentLang] && String(item.q[currentLang]).trim()) ? item.q[currentLang] : vraagNl;
        var antwoordBron = (typeof item.a === 'object' && item.a) ? item.a : { nl: item.a || '' };
        antwoordTekst = (antwoordBron[currentLang] && String(antwoordBron[currentLang]).trim()) ? antwoordBron[currentLang] : (antwoordBron.nl || '');
      }

      var wrap = document.createElement('div');
      wrap.className = 'faq-item';
      var vraag = document.createElement('div');
      vraag.className = 'faq-question';
      vraag.textContent = vraagTekst;
      var antwoord = document.createElement('div');
      antwoord.className = 'faq-answer';
      antwoord.textContent = antwoordTekst;
      wrap.appendChild(vraag);
      wrap.appendChild(antwoord);
      container.appendChild(wrap);
    });
  }

  fetch('data/faq.json', { cache: 'no-store' })
    .then(function(r) { return r.ok ? r.json() : []; })
    .then(function(items) {
      faqData = Array.isArray(items) ? items : [];
      renderFaq();
    })
    .catch(function() { faqData = []; });

  // ===== REKENTABEL (data/rekentabel.json, bewerkbaar via beheer.php) =====
  // Overschrijft de startwaarden hierboven zodra dit bestand er is. Blijft
  // dat achterwege (bestand bestaat nog niet, of fetch mislukt), dan werkt
  // de calculator gewoon door met de eerder gezette standaardwaarden.
  fetch('data/rekentabel.json', { cache: 'no-store' })
    .then(function(r) { return r.ok ? r.json() : null; })
    .then(function(rt) {
      if (!rt) return;
      rekentabelJaar = String(rt.jaar || rekentabelJaar);
      rekentabelLeeftijdGrens = Number(rt.jeugd_leeftijd_tot) || rekentabelLeeftijdGrens;
      rekentabelInschrijfkosten = Number(rt.inschrijfkosten);
      if (isNaN(rekentabelInschrijfkosten)) rekentabelInschrijfkosten = 10;
      volledigeContributieJeugd = Number(rt.jeugd_jaarbedrag) || volledigeContributieJeugd;
      volledigeContributieSenior = Number(rt.senior_jaarbedrag) || volledigeContributieSenior;
      volgendJaarContributieJeugd = rekentabelGetalOfNull(rt.jeugd_jaarbedrag_volgend);
      volgendJaarContributieSenior = rekentabelGetalOfNull(rt.senior_jaarbedrag_volgend);
      tabelJeugd = rekentabelProRataJS(volledigeContributieJeugd);
      tabelSenior = rekentabelProRataJS(volledigeContributieSenior);
      vulTokensInDOM();
      berekenContributie();
    })
    .catch(function() { /* standaardwaarden blijven gelden */ });

  // ===== INPUT ERROR STYLING =====
  function setInputError(id, hasError) {
    const el = document.getElementById(id);
    if (hasError) el.classList.add('input-error');
    else el.classList.remove('input-error');
  }

  // ===== BEDANKT-OVERLAY =====
  // Een laag binnen de pagina, geen apart venster: popupblokkers grijpen hier
  // niet in. Valt terug op bedankt.html als de overlay om wat voor reden dan
  // ook niet in de pagina staat.
  var bedanktModal = document.getElementById('bedankt-modal');
  var bedanktVorigeFocus = null;

  function openBedankt() {
    if (!bedanktModal) { window.location.href = 'bedankt.html'; return; }
    bedanktVorigeFocus = document.activeElement;
    bedanktModal.classList.add('open');
    document.body.style.overflow = 'hidden'; // achtergrond niet mee laten scrollen
    var sluit = document.getElementById('bedankt-close');
    if (sluit) sluit.focus();
    document.addEventListener('keydown', bedanktToetsen);
  }

  function sluitBedankt() {
    if (!bedanktModal) return;
    bedanktModal.classList.remove('open');
    document.body.style.overflow = '';
    document.removeEventListener('keydown', bedanktToetsen);
    if (bedanktVorigeFocus && typeof bedanktVorigeFocus.focus === 'function') bedanktVorigeFocus.focus();
  }

  function bedanktToetsen(e) {
    if (e.key === 'Escape') { sluitBedankt(); return; }
    if (e.key !== 'Tab') return;
    // Focus binnen de overlay houden zolang die openstaat
    var velden = bedanktModal.querySelectorAll('button, a[href]');
    if (!velden.length) return;
    var eerste = velden[0];
    var laatste = velden[velden.length - 1];
    if (e.shiftKey && document.activeElement === eerste) { e.preventDefault(); laatste.focus(); }
    else if (!e.shiftKey && document.activeElement === laatste) { e.preventDefault(); eerste.focus(); }
  }

  if (bedanktModal) {
    document.getElementById('bedankt-close').addEventListener('click', sluitBedankt);
    // Klik naast de kaart sluit de overlay
    bedanktModal.addEventListener('click', function(e) { if (e.target === bedanktModal) sluitBedankt(); });
  }

  // ===== IBAN KOPIEER KNOP (formulier en overlay) =====
  // Gebruikt ibanGeformatteerd (zie hierboven), dat wordt bijgewerkt zodra
  // data/bedankt.json geladen is, zodat een gewijzigd IBAN-nummer via beheer
  // ook meteen hier klopt.
  document.querySelectorAll('.iban-copy-btn').forEach(btn => {
    btn.addEventListener('click', function() {
      var t = i18n[currentLang] || i18n.nl;
      var orig = btn.textContent;
      var gelukt = function() {
        btn.textContent = '✓ ' + (t['iban.copied'] || 'Gekopieerd!');
        btn.classList.add('copied');
        setTimeout(() => { btn.textContent = orig; btn.classList.remove('copied'); }, 2000);
      };
      navigator.clipboard.writeText(ibanGeformatteerd).then(gelukt).catch(function() {
        // Terugval voor browsers zonder clipboard-API (of zonder https)
        var hulp = document.createElement('textarea');
        hulp.value = ibanGeformatteerd;
        hulp.style.position = 'fixed';
        hulp.style.opacity = '0';
        document.body.appendChild(hulp);
        hulp.select();
        try { document.execCommand('copy'); gelukt(); } catch (err) { /* stil falen */ }
        document.body.removeChild(hulp);
      });
    });
  });
</script>

</body>
</html>
