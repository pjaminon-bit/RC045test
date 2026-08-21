<?php
// ============================================================
// Eindacceptatie — publieke render-hardening
// ============================================================
// Kleine compatibiliteitslaag voor historische publieke templates. Deze laag
// start vroeg via seo-head.php en past uitsluitend de uiteindelijke HTML aan:
// - responsive fixes die na pagina-inline-CSS moeten winnen;
// - toegankelijke click-targets voor carouselbediening;
// - semantiek voor velden die de bestaande UI al als verplicht presenteert;
// - toegankelijke naam voor de telefoon-landcode.
// Geen tenantdata, secrets of mutaties.
// ============================================================

function siteRenderHardeningCss(): string
{
    return <<<'CSS'
<style id="site-render-hardening">
/* Navigatie moet vóór 820px al inklappen; bij 700px liep hij aantoonbaar 23px buiten beeld. */
@media (max-width: 900px) {
  .nav-links { display: none; }
  .nav-links.open {
    display: flex;
    flex-direction: column;
    position: absolute;
    top: 72px;
    left: 0;
    right: 0;
    background: var(--nav-bg-open);
    border-bottom: 1px solid var(--border);
    padding: 12px 24px 20px;
    gap: 4px;
    z-index: 99;
  }
  .nav-hamburger { display: flex; }
}

/* Griditems mogen op smalle schermen kleiner worden dan hun intrinsieke contentbreedte. */
.about-grid,
.about-grid > *,
.about-images,
.about-features,
.feature-card { min-width: 0; }

.about-img-main { max-width: 100%; }

/* Visueel blijft de dot klein; het daadwerkelijke pointerdoel is minimaal 28x28. */
.carousel-dot {
  position: relative;
  width: 28px !important;
  height: 28px !important;
  background: transparent !important;
  transform: none !important;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.carousel-dot::before {
  content: '';
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: rgba(255,255,255,0.5);
  transition: background 0.2s, transform 0.2s;
}
.carousel-dot.active::before { background: white; transform: scale(1.2); }

/* Compacte actieknoppen moeten ook als pointerdoel bruikbaar blijven. */
.iban-copy-btn { min-height: 28px; }
</style>
CSS;
}

function siteRenderHardeningHtml(string $html): string
{
    if (stripos($html, '</head>') !== false && strpos($html, 'id="site-render-hardening"') === false) {
        $html = preg_replace('~</head>~i', siteRenderHardeningCss() . "\n</head>", $html, 1) ?? $html;
    }

    // De select hoort inhoudelijk bij het telefoonnummer maar had geen eigen accessible name.
    $html = preg_replace(
        '~<select\s+id="landcode"(?![^>]*\baria-label=)([^>]*)>~i',
        '<select id="landcode" aria-label="Landcode"$1>',
        $html
    ) ?? $html;

    // De aanmeldpagina markeert deze velden al zichtbaar met een *. De browser- en
    // toegankelijkheidssemantiek moet daarmee overeenkomen. E-mail/mobiel blijven
    // conditioneel: daar is bewust "minimaal één van beide" vereist.
    $requiredIds = [
        'voornaam','achternaam','geboortedatum','straat','huisnummer','postcode','stad','land',
        'akkoord-reglement','akkoord-betaling',
    ];
    foreach ($requiredIds as $id) {
        $quoted = preg_quote($id, '~');
        $html = preg_replace_callback(
            '~<(input|select)\b([^>]*\bid="' . $quoted . '"[^>]*)>~i',
            static function (array $m): string {
                $attrs = $m[2];
                if (preg_match('/\srequired(?:\s|=|>|$)/i', $attrs) !== 1) $attrs .= ' required';
                if (preg_match('/\saria-required=/i', $attrs) !== 1) $attrs .= ' aria-required="true"';
                return '<' . $m[1] . $attrs . '>';
            },
            $html,
            1
        ) ?? $html;
    }

    return $html;
}

function siteStartRenderHardening(): void
{
    static $actief = false;
    if ($actief) return;
    $actief = true;
    ob_start(static fn(string $html): string => siteRenderHardeningHtml($html));
}
