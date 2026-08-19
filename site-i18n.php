<?php
// ============================================================
// RC045 automatische vertaling (client-side)
// ------------------------------------------------------------
// Eén centrale plek voor Nederlands (bron) + EN/DE via Google Translate.
// Pagina's die dit bestand includen krijgen:
// - hreflang/canonical via rc045VertaalHead();
// - dezelfde taalkeuze NL/EN/DE via rc045TaalSwitcher();
// - automatische vertaling via rc045VertaalScript().
//
// De grote formulieren (aanmelden.php) gebruiken hun bestaande handmatige
// i18n-script. Daardoor blijven validatiemeldingen en berekeningen daar
// voorspelbaar. De vertaal-helper voegt op die pagina alleen head-links toe.
// ============================================================
require_once __DIR__ . '/app/core/site.php';

function rc045Taal() {
  return siteTaal();
}

function rc045Url($taal, $bestand = null) {
  return siteTaalUrl((string) $taal, $bestand === null ? null : (string) $bestand);
}

function rc045VertaalInit() {
  // De uiteindelijke <html lang> wordt per pagina gezet. Deze functie blijft
  // bestaan zodat bestaande pagina's niet aangepast hoeven te worden.
}

function rc045VertaalHead($bestand = null) {
  $taal = rc045Taal();
  $canonical = rc045Url($taal, $bestand);
  $urlNl = rc045Url('nl', $bestand);
  $urlEn = rc045Url('en', $bestand);
  $urlDe = rc045Url('de', $bestand);
  echo '<link rel="canonical" href="' . htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') . '">' . "\n";
  echo '<link rel="alternate" hreflang="nl" href="' . htmlspecialchars($urlNl, ENT_QUOTES, 'UTF-8') . '">' . "\n";
  echo '<link rel="alternate" hreflang="en" href="' . htmlspecialchars($urlEn, ENT_QUOTES, 'UTF-8') . '">' . "\n";
  echo '<link rel="alternate" hreflang="de" href="' . htmlspecialchars($urlDe, ENT_QUOTES, 'UTF-8') . '">' . "\n";
  echo '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($urlNl, ENT_QUOTES, 'UTF-8') . '">' . "\n";

  // Aanmelden gebruikt bewust zijn bestaande handmatige vertalingen, maar de
  // contributie/lidmaatschapskeuze wordt vanaf fase 2.5 uit de configureerbare
  // lidmaatschapstypen opgebouwd. `defer` laat dit pas na de HTML uitvoeren.
  $scriptBestand = strtolower(basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')));
  if ($scriptBestand === 'aanmelden.php') {
    echo '<script defer src="lidmaatschap-aanmelden.js?v=20260819"></script>' . "\n";
  }
}

function rc045TaalSwitcher($compact = false) {
  $actief = rc045Taal();
  $labels = ['nl' => 'Nederlands', 'en' => 'English', 'de' => 'Deutsch'];
  echo '<div class="lang-switch' . ($compact ? ' lang-switch-compact' : '') . '" aria-label="Taal kiezen">';
  foreach ($labels as $code => $label) {
    $class = 'lang-btn' . ($actief === $code ? ' active' : '');
    $aria = $actief === $code ? ' aria-current="page"' : '';
    echo '<a class="' . $class . '"' . $aria . ' href="' . htmlspecialchars(rc045Url($code), ENT_QUOTES, 'UTF-8') . '">';
    echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    echo '</a>';
  }
  echo '</div>';
}

function rc045VertaalScript() {
  // Alleen vertalen als Engels of Duits actief is. Nederlands is de bron.
  if (rc045Taal() === 'nl') return;
  ?>
  <script>
  (() => {
    const targetLang = <?php echo json_encode(rc045Taal(), JSON_UNESCAPED_UNICODE); ?>;
    const sourceLang = 'nl';
    const cacheKey = 'rc045_auto_translation_v1';
    const protectedSelector = [
      'script','style','noscript','code','pre','textarea','input','select','option','button',
      '.notranslate','.lang-switch','.lang-switch *','[data-no-translate]','[data-no-translate] *'
    ].join(',');

    function isProtected(node) {
      const parent = node && node.parentElement;
      return !parent || parent.closest(protectedSelector);
    }

    function collectTextNodes() {
      const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
        acceptNode(node) {
          if (isProtected(node)) return NodeFilter.FILTER_REJECT;
          const text = (node.nodeValue || '').trim();
          if (!text || text.length < 2) return NodeFilter.FILTER_REJECT;
          if (/^[\d\s.,:;!?+\-–—|/\\()€$%°•·→←↑↓]+$/.test(text)) return NodeFilter.FILTER_REJECT;
          return NodeFilter.FILTER_ACCEPT;
        }
      });
      const nodes = [];
      let n;
      while ((n = walker.nextNode())) nodes.push(n);
      return nodes;
    }

    function loadCache() {
      try {
        const raw = localStorage.getItem(cacheKey);
        const data = raw ? JSON.parse(raw) : {};
        return data && typeof data === 'object' ? data : {};
      } catch (e) { return {}; }
    }

    function saveCache(cache) {
      try { localStorage.setItem(cacheKey, JSON.stringify(cache)); } catch (e) {}
    }

    async function translateText(text) {
      const endpoint = 'https://translate.googleapis.com/translate_a/single?client=gtx&sl=' +
        encodeURIComponent(sourceLang) + '&tl=' + encodeURIComponent(targetLang) + '&dt=t&q=' + encodeURIComponent(text);
      const response = await fetch(endpoint, { credentials: 'omit', referrerPolicy: 'no-referrer' });
      if (!response.ok) throw new Error('Translate HTTP ' + response.status);
      const data = await response.json();
      if (!Array.isArray(data) || !Array.isArray(data[0])) throw new Error('Onverwacht vertaalantwoord');
      return data[0].map(part => Array.isArray(part) ? (part[0] || '') : '').join('');
    }

    async function run() {
      const nodes = collectTextNodes();
      if (!nodes.length) return;
      const cache = loadCache();
      const taalCache = cache[targetLang] || (cache[targetLang] = {});
      const uniek = [];
      const gezien = new Set();
      for (const node of nodes) {
        const key = (node.nodeValue || '').trim();
        if (!gezien.has(key)) { gezien.add(key); uniek.push(key); }
      }
      let cacheGewijzigd = false;
      for (const text of uniek) {
        if (taalCache[text]) continue;
        try {
          taalCache[text] = await translateText(text);
          cacheGewijzigd = true;
        } catch (e) {
          console.warn('Vertaling mislukt:', e);
        }
      }
      if (cacheGewijzigd) saveCache(cache);
      for (const node of nodes) {
        const origineel = (node.nodeValue || '').trim();
        const vertaald = taalCache[origineel];
        if (!vertaald) continue;
        const voor = (node.nodeValue || '').match(/^\s*/)?.[0] || '';
        const na = (node.nodeValue || '').match(/\s*$/)?.[0] || '';
        node.nodeValue = voor + vertaald + na;
      }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run, { once: true });
    else run();
  })();
  </script>
  <?php
}
