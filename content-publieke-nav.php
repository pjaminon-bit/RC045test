<?php
// ============================================================
// Publieke navigatie voor generieke contentpagina's
// ============================================================
// Houdt de nieuwe generieke content-renderers visueel gelijk aan de normale
// publieke website. De eenvoudige tijdelijke content-page-nav uit fase 1D
// wordt tijdens rendering vervangen door de standaard site-navigatie.
// ============================================================

function contentPubliekeNavUrl(string $slug, string $taal): string
{
    return $slug . '.html' . ($taal === siteStandaardTaal() ? '' : '?lang=' . rawurlencode($taal));
}

function contentPubliekeNavMarkup(string $slug, string $taal): string
{
    $logo = htmlspecialchars(siteAsset('branding.logo'), ENT_QUOTES, 'UTF-8');
    $naam = htmlspecialchars(siteNaam(), ENT_QUOTES, 'UTF-8');
    $huidigeUrl = static function (string $code) use ($slug): string {
        return htmlspecialchars(contentPubliekeNavUrl($slug, $code), ENT_QUOTES, 'UTF-8');
    };

    $talen = siteTalen();
    $taalKnoppen = '';
    foreach ($talen as $code => $_locale) {
        $codeEsc = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $label = ['nl' => 'Nederlands', 'en' => 'English', 'de' => 'Deutsch'][$code] ?? strtoupper($code);
        $actief = $code === $taal ? ' active' : '';
        $pressed = $code === $taal ? 'true' : 'false';
        $taalKnoppen .= '<a class="lang-flag' . $actief . '" href="' . $huidigeUrl($code) . '" data-code="' . strtoupper($codeEsc) . '" aria-pressed="' . $pressed . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</a>';
    }

    return '<nav class="nav" id="main-nav">'
        . '<div class="nav-inner">'
        . '<a href="index.html" class="nav-logo">'
        . ($logo !== '' ? '<img width="400" height="423" src="' . $logo . '" alt="' . $naam . ' logo">' : '')
        . '<div><span class="nav-logo-text">' . $naam . '</span></div></a>'
        . '<ul class="nav-links" id="nav-links">'
        . '<li><a href="index.html#over-ons" id="nav-about">Over ons</a></li>'
        . '<li><a href="index.html#lidmaatschap" id="nav-membership">Lidmaatschap</a></li>'
        . '<li><a href="index.html#baan" id="nav-track">De baan</a></li>'
        . '<li><a href="index.html#locatie" id="nav-location">Locatie</a></li>'
        . '<li><a href="fotoboek.html" id="nav-photobook">Fotoboek</a></li>'
        . '<li class="nav-cta"><a href="index.html#contact" id="nav-contact">Contact</a></li>'
        . '<li class="nav-lid"><a href="aanmelden.html" id="nav-join">Lid worden</a></li>'
        . '</ul>'
        . '<div class="lang-switch" id="lang-switch">'
        . '<button class="lang-trigger" type="button" aria-haspopup="true" aria-expanded="false" aria-label="Taal / Language / Sprache">'
        . '<span class="lang-trigger-code">' . strtoupper(htmlspecialchars($taal, ENT_QUOTES, 'UTF-8')) . '</span><span class="lang-chevron" aria-hidden="true"></span></button>'
        . '<div class="lang-menu">' . $taalKnoppen . '</div></div>'
        . '<button class="nav-hamburger" id="hamburger" aria-label="Menu openen" aria-expanded="false" aria-controls="nav-links"><span></span><span></span><span></span></button>'
        . '</div></nav>';
}

function contentPubliekeNavScript(): string
{
    return '<script>(function(){'
        . 'var h=document.getElementById("hamburger"),n=document.getElementById("nav-links");'
        . 'if(h&&n){h.addEventListener("click",function(){var o=n.classList.toggle("open");h.classList.toggle("open",o);h.setAttribute("aria-expanded",o?"true":"false");});}'
        . 'var t=document.querySelector(".lang-trigger"),m=document.querySelector(".lang-menu");'
        . 'if(t&&m){t.addEventListener("click",function(e){e.stopPropagation();var o=m.classList.toggle("open");t.setAttribute("aria-expanded",o?"true":"false");});document.addEventListener("click",function(){m.classList.remove("open");t.setAttribute("aria-expanded","false");});}'
        . '})();</script>';
}

function contentPubliekeNavStartFilter(string $slug, string $taal): void
{
    ob_start(function ($html) use ($slug, $taal) {
        if (!is_string($html)) return $html;

        $nav = contentPubliekeNavMarkup($slug, $taal);
        $html = preg_replace(
            '~<div class="content-page-nav-wrap">\s*<nav class="content-page-nav".*?</nav>\s*</div>~is',
            $nav,
            $html,
            1
        ) ?? $html;

        if (stripos($html, '</body>') !== false) {
            $html = preg_replace('~</body>~i', contentPubliekeNavScript() . "\n</body>", $html, 1) ?? $html;
        }
        return $html;
    });
}
