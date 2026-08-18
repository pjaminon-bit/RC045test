<?php require_once __DIR__ . '/seo-head.php'; ?><!DOCTYPE html>
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
  <style>
    /* Ankers landen onder de vaste navigatiebalk in plaats van eronder te verdwijnen. */
    .hero, .section, .photo-strip { scroll-margin-top: 80px; }
    
    /* De mededelingsbalk was een massief gouden vlak. Dat viel op als een
       waarschuwing, terwijl het meestal gewoon een mededeling is. De kleur zit
       nu in de onderlijn (teal, dezelfde kleur als de knoppen) en het vlak is
       het zachte zand dat ook onder de kaarten zit. Beide kleuren zijn
       variabelen, dus de donkere stand rolt automatisch mee. */
    .announce-bar { background: var(--gold-light); color: var(--text); text-align: center; padding: 13px 20px; font-size: 15px; font-weight: 600; line-height: 1.5; display: flex; align-items: center; justify-content: center; gap: 10px; border-bottom: 2px solid var(--teal); }
    
    .announce-bar-icon { flex-shrink: 0; font-size: 17px; }
    
    @media (max-width: 700px) { .announce-bar { font-size: 14px; padding: 10px 16px; } }
    
    .nav-logo-sub { font-size: 13px; font-weight: 400; color: var(--muted); display: block; letter-spacing: 0.05em; text-transform: uppercase; }
    
    .nav-links li.active > a, .nav-links li.active.nav-cta > a { background: var(--teal-light); color: var(--teal-dark); font-weight: 700; box-shadow: inset 0 -2px 0 var(--teal); animation: navActivate 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
    
    @keyframes navActivate { 0% { transform: translateY(-6px) scale(0.9); opacity: 0; } 60% { transform: translateY(2px) scale(1.05); } 100% { transform: translateY(0) scale(1); opacity: 1; } }
    
    .hero { position: relative; overflow: hidden; background: var(--dark); min-height: 580px; display: flex; align-items: center; }
    
    .hero-bg { position: absolute; top: -80px; left: 0; right: 0; bottom: -80px; background-size: cover; background-position: center; opacity: 0.35; will-change: transform; }
    
    .hero-gradient { position: absolute; inset: 0; background: linear-gradient(135deg, rgba(30,44,19,0.88) 0%, rgba(58,122,119,0.25) 100%); }
    
    .hero-content { position: relative; z-index: 2; max-width: 1200px; margin: 0 auto; padding: 80px 24px; }
    
    @keyframes pulse-red { 0%, 100% { box-shadow: 0 0 0 3px rgba(239,68,68,0.3); } 50% { box-shadow: 0 0 0 6px rgba(239,68,68,0.1); } }
    
    @keyframes pulse { 0%, 100% { box-shadow: 0 0 0 3px rgba(34,197,94,0.3); } 50% { box-shadow: 0 0 0 6px rgba(34,197,94,0.1); } }
    
    .hero h1 { font-size: clamp(36px, 6vw, 64px); font-weight: 800; color: white; margin-bottom: 20px; max-width: 700px; }
    
    .hero h1 span { color: #E8C76A; }
    
    .hero p { font-size: 18px; color: rgba(255,255,255,0.75); max-width: 540px; margin-bottom: 36px; line-height: 1.7; }
    
    .hero-buttons { display: flex; flex-wrap: wrap; gap: 12px; }
    
    .btn-outline { background: rgba(255,255,255,0.1); color: white; border: 1.5px solid rgba(255,255,255,0.3); backdrop-filter: blur(4px); }
    
    .btn-outline:hover { background: rgba(255,255,255,0.18); transform: translateY(-1px); }
    
    .btn-white { background: white; color: var(--teal-dark); }
    
    .btn-white:hover { background: var(--teal-light); transform: translateY(-1px); }
    
    #announce-text { white-space: pre-line; }
    
    .actueel-hours { display: none; background: var(--gold-light); border: 1px solid var(--teal); border-radius: 8px; padding: 10px 14px; margin-bottom: 14px; font-size: 14px; line-height: 1.6; }
    
    .actueel-hours strong { color: var(--teal-dark); }
    
    .actueel-hours-text { white-space: pre-line; }
    
    .info-bar { background: var(--white); border-bottom: 1px solid var(--border); box-shadow: var(--shadow); }
    
    .info-bar-inner { max-width: 1200px; margin: 0 auto; padding: 0 24px; display: grid; grid-template-columns: repeat(3, 1fr); }
    
    .info-item { display: flex; align-items: center; gap: 16px; padding: 24px 0; border-right: 1px solid var(--border); }
    
    .info-item:last-child { border-right: none; padding-left: 32px; }
    
    .info-item:first-child { padding-right: 32px; }
    
    .info-item:nth-child(2) { padding: 24px 32px; }
    
    .info-icon { width: 48px; height: 48px; border-radius: 12px; background: var(--teal-light); display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 22px; }
    
    .info-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin-bottom: 2px; }
    
    .info-value { font-family: 'Poppins', sans-serif; font-weight: 600; font-size: 15px; color: var(--text); }
    
    .info-hours-note { font-size: 12px; color: var(--muted); margin-top: 6px; line-height: 1.5; }
    
    .info-value small { font-weight: 400; font-size: 13px; color: var(--muted); font-family: 'Inter', sans-serif; }
    
    .info-location-link { color: inherit; text-decoration: none; border-bottom: 1.5px dashed var(--muted); padding-bottom: 1px; transition: border-color 0.2s, color 0.2s; cursor: pointer; }
    
    .info-location-link:hover { color: var(--teal-dark); border-bottom-color: var(--teal-dark); }
    
    .status-open { display: inline-flex; align-items: center; gap: 6px; color: #16A34A; font-size: 13px; font-weight: 600; margin-top: 2px; }
    
    .status-open::before { content: ''; width: 7px; height: 7px; background: var(--green); border-radius: 50%; }
    
    .status-closed { display: inline-flex; align-items: center; gap: 6px; color: #DC2626; font-size: 13px; font-weight: 600; margin-top: 2px; }
    
    .status-closed::before { content: ''; width: 7px; height: 7px; background: #EF4444; border-radius: 50%; }
    
    .status-members { display: inline-flex; align-items: center; gap: 6px; color: var(--teal-dark); font-size: 13px; font-weight: 600; margin-top: 2px; }
    
    .status-members::before { content: ''; width: 7px; height: 7px; background: var(--teal); border-radius: 50%; }
    
    .status-animo { display: inline-flex; align-items: center; gap: 6px; color: #8A6A12; font-size: 13px; font-weight: 600; margin-top: 2px; }
    
    .status-animo::before { content: ''; width: 7px; height: 7px; background: var(--gold); border-radius: 50%; }
    
    .reveal { opacity: 0; transform: translateY(32px); transition: opacity 0.6s ease, transform 0.6s ease; }
    
    .reveal.visible { opacity: 1; transform: translateY(0); }
    
    .reveal-delay-1 { transition-delay: 0.1s; }
    
    .reveal-delay-2 { transition-delay: 0.2s; }
    
    .reveal-delay-3 { transition-delay: 0.3s; }
    
    .reveal-delay-4 { transition-delay: 0.4s; }
    
    .section { padding: 80px 24px; }
    
    .container { max-width: 1200px; margin: 0 auto; }
    
    .section-title { font-size: clamp(28px, 4vw, 42px); font-weight: 700; color: var(--dark); margin-bottom: 16px; }
    
    .section-sub { font-size: 17px; color: var(--muted); max-width: 560px; line-height: 1.7; }
    
    .section-header { margin-bottom: 48px; }
    
    .section-header.center { text-align: center; }
    
    .section-header.center .section-sub { margin: 0 auto; }
    
    .about { background: var(--white); }
    
    .about-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 64px; align-items: center; }
    
    .about-images { position: relative; }
    
    .about-img-main { width: 100%; height: 380px; object-fit: cover; border-radius: var(--radius); box-shadow: var(--shadow); cursor: pointer; transition: opacity 0.2s; }
    
    .about-img-main:hover { opacity: 0.92; }
    
    .about-img-secondary { position: absolute; bottom: -28px; right: -28px; width: 220px; height: 160px; object-fit: cover; border-radius: var(--radius); border: 4px solid var(--white); box-shadow: var(--shadow); cursor: pointer; transition: opacity 0.2s; }
    
    .about-img-secondary:hover { opacity: 0.92; }
    
    .about-features { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 32px; }
    
    .about-story-link { display: block; width: fit-content; margin-top: 28px; color: var(--teal-dark); font-weight: 600; font-size: 17px; border-bottom: 1.5px solid var(--teal); padding-bottom: 2px; transition: border-color 0.2s, color 0.2s; }
    
    .about-story-link:hover { color: var(--teal); }
    
    .about-story-link + .about-story-link { margin-top: 10px; }
    
    .feature-card { background: var(--bg); border: 1px solid var(--border); border-radius: 10px; padding: 16px; transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s; }
    
    .feature-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: var(--teal-light); }
    
    .feature-card-icon { font-size: 24px; margin-bottom: 8px; }
    
    .feature-card h4 { font-size: 14px; font-weight: 600; color: var(--dark); margin-bottom: 4px; }
    
    .feature-card p { font-size: 13px; color: var(--muted); }
    
    .about-photos-title { font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: var(--gold); margin: 56px 0 20px; }
    
    .about-photos-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; border-radius: var(--radius); overflow: hidden; }
    
    .about-photo { object-fit: cover; width: 100%; height: 200px; transition: transform 0.4s; cursor: pointer; }
    
    .about-photo:hover { transform: scale(1.05); }
    
    .about-photo-wrap { overflow: hidden; }
    
    .pricing { background: var(--bg); }
    
    .pricing-grid { display: grid; grid-template-columns: 1fr; gap: 24px; max-width: 600px; margin: 0 auto; }
    
    .price-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 36px; transition: transform 0.2s, box-shadow 0.2s; }
    
    .price-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
    
    .price-card.featured { background: var(--dark); border-color: var(--dark); color: white; position: relative; overflow: hidden; }
    
    .price-card.featured::before { content: ''; position: absolute; top: 0; left: -100%; width: 60%; height: 100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.12), transparent); animation: shimmer 4s infinite; pointer-events: none; }
    
    @keyframes shimmer { 0% { left: -100%; } 100% { left: 200%; } }
    
    .price-card-tag { display: inline-block; background: var(--teal-light); color: var(--teal-dark); padding: 4px 12px; border-radius: 100px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 20px; }
    
    .price-card.featured .price-card-tag { background: rgba(200,154,26,0.2); color: #E8C76A; }
    
    .price-card h3 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
    
    .price-card.featured h3 { color: white; }
    
    .price-list { list-style: none; margin: 20px 0 28px; }
    
    .price-list li { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 15px; }
    
    .price-card.featured .price-list li { border-bottom-color: rgba(255,255,255,0.1); }
    
    .price-list li:last-child { border-bottom: none; }
    
    .price-list .price-amount { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 17px; color: var(--teal); }
    
    .price-card .btn-primary { width: 100%; justify-content: center; }
    
    .price-card.featured .btn-white { width: 100%; justify-content: center; }
    
    .price-notes { list-style: none; margin: 0 0 24px; display: grid; gap: 9px; }
    
    .price-notes li { position: relative; padding-left: 16px; font-size: 13px; color: var(--muted); line-height: 1.55; }
    
    .price-notes li::before { content: ''; position: absolute; left: 0; top: 7px; width: 5px; height: 5px; border-radius: 50%; background: var(--teal); opacity: 0.5; }
    
    .price-notes a { color: inherit; text-decoration: underline; text-underline-offset: 2px; text-decoration-thickness: 1px; }
    
    .price-notes a:hover { color: var(--teal); }
    
    .price-card.featured .price-notes li { color: rgba(255,255,255,0.5); }
    
    .price-card.featured .price-notes li::before { background: #E8C76A; opacity: 0.6; }
    
    .price-card.featured .price-notes a:hover { color: #E8C76A; }
    
    .price-notes:empty { display: none; }
    
    .track { background: var(--white); }
    
    .track-grid { display: grid; grid-template-columns: 2fr 1fr; grid-template-rows: 240px 240px; gap: 16px; border-radius: var(--radius); overflow: hidden; }
    
    .track-grid + .track-grid { margin-top: 16px; }
    
    .track-photo { object-fit: cover; width: 100%; height: 100%; transition: transform 0.4s; cursor: pointer; }
    
    .track-photo:hover { transform: scale(1.05); }
    
    .track-photo-wrap { overflow: hidden; }
    
    .track-photo-wrap.tall { grid-row: span 2; }
    
    .track-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: start; }
    
    .photo-strip { width: 100%; height: 550px; overflow: hidden; }
    
    .carousel { position: relative; width: 100%; height: 100%; background: var(--dark); }
    
    .carousel-slide { position: absolute; inset: 0; opacity: 0; transition: opacity 0.6s ease; overflow: hidden; }
    
    .carousel-slide.active { opacity: 1; }
    
    .carousel-slide-bg { position: absolute; inset: -20px; background-size: cover; background-position: center; filter: blur(28px) brightness(0.6); transform: scale(1.15); }
    
    .carousel-img { position: relative; width: 100%; height: 100%; object-fit: contain; display: block; }
    
    .carousel-arrow { position: absolute; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; border-radius: 50%; background: rgba(30,44,19,0.55); color: white; border: none; font-size: 26px; line-height: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.2s; z-index: 2; }
    
    .carousel-arrow:hover { background: rgba(30,44,19,0.8); }
    
    .carousel-prev { left: 16px; }
    
    .carousel-next { right: 16px; }
    
    .carousel-dots { position: absolute; bottom: 16px; left: 50%; transform: translateX(-50%); display: flex; gap: 8px; z-index: 2; }
    
    .carousel-dot { width: 9px; height: 9px; border-radius: 50%; background: rgba(255,255,255,0.5); border: none; cursor: pointer; padding: 0; transition: background 0.2s, transform 0.2s; }
    
    .carousel-dot.active { background: white; transform: scale(1.2); }
    
    .agenda { background: var(--bg); }
    
    /* Kolommen passen zich vanzelf aan: nooit meer dan 5 naast elkaar
       (begrensd door de minmax-breedte hieronder samen met de
       max-breedte van .container), en met minder dan 5 kaarten vullen
       de bestaande kaarten de rij mooi op in plaats van een leeg gat
       aan het eind. Schaalt vanzelf terug naar 1 kolom op een smal
       scherm, daarom is er geen aparte @media-regel meer voor nodig. */
    .agenda-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 20px; }
    
    .agenda-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 24px; transition: transform 0.2s, box-shadow 0.2s; position: relative; overflow: hidden; opacity: 0; transform: translateY(24px); display: flex; flex-direction: column; }
    
    .agenda-card.visible { animation: cardDrop 0.5s ease forwards; }
    
    .agenda-card.visible:nth-child(2) { animation-delay: 0.1s; }
    
    .agenda-card.visible:nth-child(3) { animation-delay: 0.2s; }
    
    @keyframes cardDrop { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
    
    .agenda-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }
    
    .agenda-card-date { display: flex; flex-direction: column; align-items: center; justify-content: center; width: 56px; height: 56px; background: var(--teal-light); border-radius: 10px; margin-bottom: 16px; flex-shrink: 0; }
    
    .agenda-card-day { font-family: 'Poppins', sans-serif; font-size: 22px; font-weight: 800; color: var(--teal-dark); line-height: 1; }
    
    .agenda-card-month { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--teal); }
    
    .agenda-card-tag { display: inline-block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; padding: 3px 10px; border-radius: 100px; margin-bottom: 10px; }
    
    .agenda-card-tag.open-dag { background: var(--teal-light); color: var(--teal-dark); }
    
    .agenda-card-tag.leden { background: var(--gold-light); color: var(--gold); }
    
    .agenda-card-tag.wedstrijd { background: #FEE2E2; color: #DC2626; }
    
    .agenda-card h3 { font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 6px; }
    
    .agenda-card p { font-size: 14px; color: var(--muted); line-height: 1.6; }
    
    .agenda-card-time { font-size: 13px; font-weight: 600; color: var(--teal); margin-top: auto; padding-top: 12px; display: flex; align-items: center; gap: 4px; }
    
    .agenda-card.afgelopen { border-style: dashed; }
    
    .agenda-card.afgelopen:hover { transform: none; box-shadow: none; }
    
    .agenda-card.afgelopen .agenda-card-date { background: var(--bg); opacity: 0.3; }
    
    .agenda-card.afgelopen .agenda-card-tag { background: var(--bg); color: var(--muted); opacity: 0.3; }

    .nieuws { background: var(--bg); padding-top: 56px; padding-bottom: 56px; }

    .nieuws-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; }

    .nieuws-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 24px; box-shadow: var(--shadow); transition: transform 0.2s, box-shadow 0.2s; }

    .nieuws-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); }

    .nieuws-card-date { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: var(--gold); margin-bottom: 8px; }

    .nieuws-card h3 { font-size: 17px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }

    .nieuws-card p { font-size: 14px; color: var(--muted); line-height: 1.6; }

    .nieuws-card-link { display: inline-block; margin-top: 12px; font-size: 14px; font-weight: 600; color: var(--teal-dark); }

    .nieuws-card-link:hover { color: var(--teal); }

    .agenda-card.afgelopen h3,
        .agenda-card.afgelopen p,
        .agenda-card.afgelopen .agenda-card-time { opacity: 0.3; }
    
    .agenda-card-past-badge { position: absolute; top: 14px; right: 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; padding: 3px 10px; border-radius: 100px; background: var(--gold-light); color: var(--rust); }
    
    .rules { background: var(--dark); color: white; }
    
    .rules .section-title { color: white; }
    
    .rules .section-sub { color: rgba(255,255,255,0.6); }
    
    .rules-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
    
    .rule-card { background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: var(--radius); padding: 24px; transition: background 0.2s, transform 0.2s; }
    
    .rule-card:hover { background: rgba(255,255,255,0.09); transform: translateY(-3px); }
    
    .rule-card-num { font-family: 'Poppins', sans-serif; font-size: 36px; font-weight: 800; color: rgba(200,154,26,0.4); line-height: 1; margin-bottom: 12px; }
    
    .rule-card h4 { font-size: 16px; font-weight: 600; margin-bottom: 8px; color: white; }
    
    .rule-card p { font-size: 14px; color: rgba(255,255,255,0.55); line-height: 1.6; }
    
    .rules-link { display: inline-flex; align-items: center; gap: 8px; margin-top: 40px; color: #E8C76A; font-weight: 600; font-size: 17px; border-bottom: 1.5px solid rgba(200,154,26,0.4); padding-bottom: 2px; transition: border-color 0.2s; }
    
    .rules-link:hover { border-color: #E8C76A; }
    
    .location { background: var(--bg); }
    
    .location-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 48px; align-items: start; }
    
    .location-map { border-radius: var(--radius); overflow: hidden; box-shadow: var(--shadow); aspect-ratio: 4/3; background: #E5E3DF; display: flex; align-items: center; justify-content: center; position: relative; }
    
    .location-map iframe { width: 100%; height: 100%; border: none; position: absolute; inset: 0; }
    
    .opening-hours { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 28px; margin-bottom: 24px; }
    
    .opening-hours h3 { font-size: 18px; font-weight: 700; margin-bottom: 6px; display: flex; align-items: center; gap: 8px; }
    
    .hours-intro-note { font-size: 12px; color: var(--muted); line-height: 1.5; margin: 0 0 16px; }
    
    .hours-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border); font-size: 15px; }
    
    .hours-row:last-child { border-bottom: none; }
    
    .hours-day { font-weight: 500; }
    
    .hours-time { color: var(--muted); font-size: 14px; }
    
    /* Openingstijden die via beheer.php op een gesloten-stand staan (gewoon
       gesloten, onderhoud of slecht weer). De tijd blijft zichtbaar (doorgestreept en gedempt) zodat duidelijk is
       dat het om een tijdelijke afwijking gaat, met de reden eronder. De
       negatieve marges laten de markering doorlopen tot de rand van de kaart
       (.opening-hours heeft 28px padding); 3px rand + 25px padding houdt de
       tekst op dezelfde lijn als de andere regels. */
    .hours-time-wrap { display: flex; flex-direction: column; align-items: flex-end; gap: 2px; }
    
    .hours-closed-note { display: none; font-size: 12px; font-weight: 700; padding: 4px 11px; border-radius: 100px; background: var(--rust); color: var(--white); text-align: center; }
    
    .hours-row.is-gesloten { align-items: flex-start; background: rgba(139,51,25,0.09); border-left: 3px solid var(--rust); margin-left: -28px; margin-right: -28px; padding-left: 25px; padding-right: 28px; }
    
    .hours-row.is-gesloten .hours-time { color: var(--muted); text-decoration: line-through; text-decoration-color: var(--rust); text-decoration-thickness: 2px; }
    
    .hours-row.is-gesloten .hours-closed-note { display: block; }
    
    /* Alleen open voor leden is geen sluiting: de baan is die dag gewoon open,
       maar niet voor gasten. De tijd blijft daarom leesbaar staan (geen
       doorhaling) en de markering is teal in plaats van rust, zodat het
       zichtbaar iets anders is dan een gesloten dag. */
    .hours-row.is-leden { align-items: flex-start; background: rgba(58,122,119,0.08); border-left: 3px solid var(--teal); margin-left: -28px; margin-right: -28px; padding-left: 25px; padding-right: 28px; }
    
    .hours-row.is-leden .hours-closed-note { display: block; background: var(--teal); }
    
    /* Alleen bij voldoende animo: de baan is die dag open, maar of het doorgaat
       hangt van de opkomst af. Dat is de mildste van de drie afwijkingen, en
       zo ziet hij er ook uit: geen gevuld vlak zoals bij gesloten (rust) en
       alleen leden (teal), maar een open randje in goud. De tijd blijft
       leesbaar, want de dag is niet afgelast. */
    .hours-row.is-animo { align-items: flex-start; background: rgba(200,154,26,0.06); border-left: 3px solid var(--gold); margin-left: -28px; margin-right: -28px; padding-left: 25px; padding-right: 28px; }
    
    .hours-row.is-animo .hours-closed-note { display: block; background: transparent; color: var(--text); border: 1px solid var(--gold); font-weight: 600; }
    
    /* Zelfde melding in de info-balk bovenaan. Daar is bewust geen achtergrond
       en randje gebruikt: dat blok is een smalle strook van drie kolommen en
       wordt daar te zwaar van. */
    .info-value .tijd-gesloten { text-decoration: line-through; text-decoration-color: var(--rust); text-decoration-thickness: 2px; color: var(--muted); }
    
    .info-closed-note { display: block; width: fit-content; margin-top: 4px; font-family: 'Inter', sans-serif; font-size: 12px; font-weight: 700; padding: 4px 11px; border-radius: 100px; background: var(--rust); color: var(--white); }
    
    .info-closed-note.is-leden { background: var(--teal); }
    
    .info-closed-note.is-animo { background: transparent; color: var(--text); border: 1px solid var(--gold); font-weight: 600; }
    
    /* Openingstijden-notities: zelfde opsommingsstijl als de notities onder de
       prijskaarten (.price-notes), zodat beide blokken er gelijk uitzien. */
    .hours-note { list-style: none; font-size: 13px; color: var(--muted); margin: 14px 0 0; padding: 12px; background: var(--teal-light); border-radius: 8px; line-height: 1.5; display: grid; gap: 8px; }

    .hours-note li { position: relative; padding-left: 16px; }

    .hours-note li::before { content: ''; position: absolute; left: 0; top: 7px; width: 5px; height: 5px; border-radius: 50%; background: var(--teal); opacity: 0.5; }
    
    .address-card { background: var(--white); border: 1.5px solid var(--border); border-radius: var(--radius); padding: 20px 28px; display: flex; gap: 16px; align-items: flex-start; }
    
    .address-card-icon { font-size: 24px; margin-top: 2px; }
    
    .address-card h4 { font-size: 15px; font-weight: 600; margin-bottom: 4px; }
    
    .address-card p { font-size: 14px; color: var(--muted); line-height: 1.6; }
    
    .weather-card { background: var(--teal-light); border: 1.5px solid var(--teal-light); border-radius: var(--radius); padding: 20px 28px; margin-bottom: 24px; display: flex; align-items: center; gap: 20px; }
    
    .weather-icon-big { font-size: 48px; line-height: 1; flex-shrink: 0; }
    
    .weather-temp-big { font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 36px; color: var(--teal-dark); line-height: 1; }
    
    .weather-desc-text { font-size: 14px; color: var(--muted); margin-top: 4px; }
    
    .weather-details { display: flex; gap: 16px; margin-top: 8px; flex-wrap: wrap; }
    
    .weather-detail { font-size: 12px; color: var(--muted); display: flex; align-items: center; gap: 4px; }
    
    .weather-label { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--teal-dark); margin-bottom: 6px; }
    
    .contact { background: var(--white); }
    
    .contact-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 64px; align-items: start; }
    
    .contact-info p { font-size: 16px; color: var(--muted); line-height: 1.7; margin-bottom: 32px; }
    
    .contact-channels { display: flex; flex-direction: column; gap: 12px; }
    
    .channel { display: flex; align-items: center; gap: 16px; padding: 16px; background: var(--bg); border: 1px solid var(--border); border-radius: 10px; transition: border-color 0.2s, transform 0.2s; }
    
    .channel:hover { border-color: var(--teal); transform: translateX(4px); }
    
    .channel-icon { width: 44px; text-align: center; display: flex; align-items: center; justify-content: center; font-size: 22px; }
    
    .channel-label { font-size: 12px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; }
    
    .channel-value { font-size: 15px; font-weight: 600; color: var(--text); }
    
    .contact-form { display: flex; flex-direction: column; gap: 16px; }
    
    .form-group input, .form-group textarea, .form-group select { padding: 12px 16px; border: 1.5px solid var(--border); border-radius: 8px; font-family: 'Inter', sans-serif; font-size: 15px; color: var(--text); background: var(--bg); transition: border-color 0.2s, box-shadow 0.2s; outline: none; }
    
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus { border-color: var(--teal); box-shadow: 0 0 0 3px rgba(58,122,119,0.12); }
    
    .form-group textarea { resize: vertical; min-height: 120px; }
    
    .form-submit .btn-primary { width: 100%; justify-content: center; }
    
    @media (max-width: 900px) {
          .about-grid, .pricing-grid, .location-grid, .contact-grid, .footer-top { grid-template-columns: 1fr; }
          .info-bar-inner { grid-template-columns: 1fr; }
          .info-item { border-right: none; border-bottom: 1px solid var(--border); padding: 20px 0 !important; }
          .info-item:last-child { border-bottom: none; }
          .rules-grid { grid-template-columns: 1fr; gap: 12px; }
          .track-layout { grid-template-columns: 1fr; }
          .about-img-secondary { display: none; }
          .about-features { grid-template-columns: 1fr; }
          .footer-top { gap: 32px; }
          .footer-bottom { flex-direction: column; gap: 8px; text-align: center; }
        }
    
    @media (max-width: 700px) {
          .section { padding: 60px 20px; }
          .hero-content { padding: 64px 20px; }
          .track-grid { grid-template-columns: 1fr; grid-template-rows: auto; }
          .about-photos-grid { grid-template-columns: 1fr 1fr; }
          .track-photo-wrap.tall { grid-row: span 1; }
          .photo-strip { height: 320px; }
        }
  </style>
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
