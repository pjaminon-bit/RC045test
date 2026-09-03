<?php
// ============================================================
// Browser-CSP runtime
// ============================================================
// Eén per-response nonce bindt noodzakelijke inline scripts aan de response.
// De legacy templates bevatten nog een klein aantal inline eventattributen.
// Die worden vóór verzending omgezet naar inert data-* gedrag; alleen de vier
// hieronder expliciet ondersteunde interacties worden door een nonce-gebonden
// bridge hersteld. Onbekende inline handlers blijven onaangeroerd en worden
// door script-src-attr 'none' geblokkeerd; de regressietest laat zulke broncode
// bovendien fail-closed falen.
// ============================================================

function siteCspNonce(): string
{
    static $nonce = null;
    if (!is_string($nonce)) {
        // 18 bytes levert exact 24 base64tekens zonder padding. De waarde is
        // cryptografisch willekeurig en bestaat uitsluitend binnen deze request.
        $nonce = base64_encode(random_bytes(18));
    }
    return $nonce;
}

function siteCspHtmlWaarde(string $waarde): string
{
    return htmlspecialchars($waarde, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
}

function siteCspEventBridge(string $nonce): string
{
    $n = siteCspHtmlWaarde($nonce);
    $script = <<<'JS'
<script nonce="__NONCE__" id="site-csp-event-bridge">
(function () {
  'use strict';

  document.querySelectorAll('[data-csp-lang]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      var taal = knop.getAttribute('data-csp-lang') || '';
      if (/^[a-z]{2}$/.test(taal) && typeof window.setLang === 'function') window.setLang(taal);
    });
  });

  document.querySelectorAll('[data-csp-scroll-top]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  document.addEventListener('change', function (event) {
    var doel = event.target instanceof Element ? event.target.closest('[data-csp-submit-form]') : null;
    if (doel && doel.form) doel.form.submit();
  });

  document.addEventListener('submit', function (event) {
    var formulier = event.target instanceof HTMLFormElement ? event.target : null;
    if (!formulier || !formulier.hasAttribute('data-csp-confirm')) return;
    if (!window.confirm(formulier.getAttribute('data-csp-confirm') || '')) event.preventDefault();
  });

  document.addEventListener('click', function (event) {
    var doel = event.target instanceof Element ? event.target.closest('[data-csp-confirm]') : null;
    if (!doel || doel instanceof HTMLFormElement) return;
    if (!window.confirm(doel.getAttribute('data-csp-confirm') || '')) {
      event.preventDefault();
      event.stopImmediatePropagation();
    }
  });
})();
</script>
JS;
    // Nowdoc bewaart backslashes letterlijk; normaliseer uitsluitend de twee
    // vaste HTML-attributen van onze eigen bridge naar gewone quotes.
    $script = str_replace(['nonce=\\"__NONCE__\\"', 'id=\\"site-csp-event-bridge\\"'], ['nonce="__NONCE__"', 'id="site-csp-event-bridge"'], $script);
    return str_replace('__NONCE__', $n, $script);
}

function siteCspHardenHtml(string $html): string
{
    // JSON, CSV, redirects en andere niet-HTML responses blijven bytegelijk.
    if (stripos($html, '<html') === false && stripos($html, '<!doctype') === false) return $html;

    $bridgeNodig = false;

    // Legacy confirm-handlers worden semantische data-attributen. Alleen een
    // vaste quoted string is toegestaan; dynamische code of andere JavaScript-
    // expressies matchen bewust niet.
    $html = preg_replace_callback(
        '~\s(?:onsubmit|onclick)\s*=\s*(["\'])\s*return\s+confirm\(\s*\'([^\'\r\n]*)\'\s*\)\s*;?\s*\1~i',
        static function (array $m) use (&$bridgeNodig): string {
            $bridgeNodig = true;
            return ' data-csp-confirm="' . siteCspHtmlWaarde($m[2]) . '"';
        },
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '~\sonchange\s*=\s*(["\'])\s*this\.form\.submit\(\)\s*;?\s*\1~i',
        static function (array $m) use (&$bridgeNodig): string {
            $bridgeNodig = true;
            return ' data-csp-submit-form="1"';
        },
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '~\sonclick\s*=\s*(["\'])\s*window\.scrollTo\(\{\s*top\s*:\s*0\s*,\s*behavior\s*:\s*\'smooth\'\s*\}\)\s*;?\s*\1~i',
        static function (array $m) use (&$bridgeNodig): string {
            $bridgeNodig = true;
            return ' data-csp-scroll-top="1"';
        },
        $html
    ) ?? $html;

    $html = preg_replace_callback(
        '~\sonclick\s*=\s*(["\'])\s*setLang\(\'([a-z]{2})\'\)\s*;?\s*\1~i',
        static function (array $m) use (&$bridgeNodig): string {
            $bridgeNodig = true;
            return ' data-csp-lang="' . siteCspHtmlWaarde(strtolower($m[2])) . '"';
        },
        $html
    ) ?? $html;

    // Bovenstaande PHP single-quoted strings houden \" letterlijk. Dat is
    // bewust één centrale normalisatie in plaats van vier foutgevoelige mixes
    // van PHP-, HTML- en regexquotes.
    $html = str_replace(
        ['data-csp-confirm=\\"', 'data-csp-submit-form=\\"1\\"', 'data-csp-scroll-top=\\"1\\"', 'data-csp-lang=\\"'],
        ['data-csp-confirm="', 'data-csp-submit-form="1"', 'data-csp-scroll-top="1"', 'data-csp-lang="'],
        $html
    );
    $html = preg_replace('~(data-csp-(?:confirm|lang)="[^"]*)\\"~', '$1"', $html) ?? $html;

    // De bestaande paginascripts zoeken de actieve taalknop nog op via het
    // voormalige onclick-attribuut. Bind die selector aan dezelfde inert data-
    // semantiek zonder de paginascripts verder te herschrijven.
    $html = str_replace(
        '[onclick="setLang(\'${lang}\')"]',
        '[data-csp-lang="${lang}"]',
        $html
    );

    $nonce = siteCspNonce();
    $nonceHtml = siteCspHtmlWaarde($nonce);

    // Alleen inline scripts krijgen de nonce. Externe scripts behouden exact
    // hun bestaande origincontract; een nonce op een extern script zou die
    // origin anders buiten script-src 'self' om kunnen autoriseren.
    $html = preg_replace_callback(
        '~<script\b([^>]*)>~i',
        static function (array $m) use ($nonceHtml): string {
            $attrs = (string)$m[1];
            if (preg_match('~\bsrc\s*=~i', $attrs) === 1) return $m[0];
            $attrs = preg_replace('~\snonce\s*=\s*(["\']).*?\1~is', '', $attrs) ?? $attrs;
            return '<script nonce="' . $nonceHtml . '"' . $attrs . '>';
        },
        $html
    ) ?? $html;
    $html = str_replace('<script nonce=\\"', '<script nonce="', $html);
    $html = preg_replace('~(<script nonce="[^"]*)\\"~', '$1"', $html) ?? $html;

    if ($bridgeNodig && strpos($html, 'id="site-csp-event-bridge"') === false) {
        $bridge = siteCspEventBridge($nonce);
        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('~</body>~i', $bridge . "\n</body>", $html, 1) ?? $html;
        } elseif (stripos($html, '</html>') !== false) {
            $html = preg_replace('~</html>~i', $bridge . "\n</html>", $html, 1) ?? $html;
        } else {
            $html .= "\n" . $bridge;
        }
    }

    return $html;
}

function siteCspRuntimeStart(): void
{
    static $gestart = false;
    if ($gestart) return;
    $gestart = true;
    ob_start('siteCspHardenHtml');
}
