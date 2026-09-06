<?php
require_once __DIR__ . '/seo-head.php';
require_once __DIR__ . '/content-pagina.php';

function contentEsc($waarde): string
{
    return htmlspecialchars((string) $waarde, ENT_QUOTES, 'UTF-8');
}

function contentTaalWaarde($waarde, string $taal, string $standaard = ''): string
{
    if (is_array($waarde)) {
        if (isset($waarde[$taal]) && is_scalar($waarde[$taal]) && trim((string) $waarde[$taal]) !== '') return (string) $waarde[$taal];
        if (isset($waarde['nl']) && is_scalar($waarde['nl'])) return (string) $waarde['nl'];
        return $standaard;
    }
    return is_scalar($waarde) ? (string) $waarde : $standaard;
}

function contentRenderVerhaal(string $sleutel): void
{
    $bootstrap = contentPaginaBootstrap($sleutel);
    if (!$bootstrap || ($bootstrap['type'] ?? '') !== 'verhaal') {
        http_response_code(404);
        echo 'Pagina niet gevonden.';
        return;
    }

    $def = $bootstrap['definitie'];
    $data = $bootstrap['data'];
    $hero = $bootstrap['hero'];
    $taal = rc045Taal();
    $seoSleutel = $bootstrap['seo_sleutel'];

    $heroLabel = contentTaalWaarde($hero['label'] ?? [], $taal, '');
    $heroTitel = contentTaalWaarde($hero['titel'] ?? [], $taal, (string) ($def['label'] ?? ''));
    $heroSub = contentPaginaWaarde($data, 'hero_sub', $taal, '');
    $heroCss = contentPaginaHeroCss($sleutel);

    $galerij = is_array($def['galerij'] ?? null) ? $def['galerij'] : [];
    $galerijTitel = contentTaalWaarde($galerij['titel'] ?? [], $taal, '');
    $afbeeldingen = is_array($galerij['afbeeldingen'] ?? null) ? $galerij['afbeeldingen'] : [];

    $logo = contentEsc(siteAsset('branding.logo'));
    $naam = contentEsc(siteNaam());
    $slogan = contentEsc(siteSlogan());
    ?><!DOCTYPE html>
<html lang="<?= contentEsc($taal) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead($seoSleutel); ?>
<?php siteHeadBranding(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style nonce="<?=siteCspHtmlWaarde(siteCspNonce())?>" id="content-page-hero-1"><?= $heroCss ?></style>
<link rel="stylesheet" href="csp205-content-renderer-1-3035699392f1.css">
</head>
<body>
<a href="#main-content" class="skip-link">Naar hoofdinhoud</a>
<div class="content-page-nav-wrap"><nav class="content-page-nav" aria-label="Hoofdnavigatie">
<a class="content-page-brand" href="index.html"><?php if ($logo !== ''): ?><img src="<?= $logo ?>" alt="<?= $naam ?> logo"><?php endif; ?><strong><?= $naam ?></strong></a>
<div class="content-page-links"><a class="home-link" href="index.html">Home</a><a href="index.html#over-ons">Over ons</a><a href="index.html#contact">Contact</a><span class="content-langs"><?php foreach (siteTalen() as $code => $_locale): ?><a class="<?= $code === $taal ? 'active' : '' ?>" href="<?= contentEsc(($def['slug'] ?? $sleutel) . '.html' . ($code === siteStandaardTaal() ? '' : '?lang=' . rawurlencode($code))) ?>"><?= strtoupper(contentEsc($code)) ?></a><?php endforeach; ?></span></div>
</nav></div>
<div class="page-hero"><div class="page-hero-bg"></div><div class="page-hero-gradient"></div><div class="page-hero-content"><?php if ($heroLabel !== ''): ?><div class="section-label"><?= contentEsc($heroLabel) ?></div><?php endif; ?><h1><?= contentEsc($heroTitel) ?></h1><?php if ($heroSub !== ''): ?><p><?= contentEsc($heroSub) ?></p><?php endif; ?></div></div>
<main class="content-main" id="main-content">
<section class="content-card">
<?php foreach (($def['velden'] ?? []) as $veld => $veldDef): if ($veld === 'hero_sub') continue; $tekst = contentPaginaWaarde($data, (string) $veld, $taal, ''); if ($tekst === '') continue; ?>
<p><?= nl2br(contentEsc($tekst)) ?></p>
<?php endforeach; ?>
</section>
<?php if ($afbeeldingen): ?>
<section aria-label="<?= contentEsc($galerijTitel ?: 'Galerij') ?>"><?php if ($galerijTitel !== ''): ?><div class="content-gallery-title"><?= contentEsc($galerijTitel) ?></div><?php endif; ?><div class="content-gallery"><?php foreach ($afbeeldingen as $afb): $src = trim((string) ($afb['src'] ?? '')); if ($src === '' || str_contains($src, '..') || preg_match('~^(?:https?:)?//~i', $src)) continue; ?><img src="<?= contentEsc($src) ?>" alt="<?= contentEsc($afb['alt'] ?? '') ?>" loading="lazy" decoding="async"><?php endforeach; ?></div></section>
<?php endif; ?>
</main>
<footer class="content-simple-footer"><div class="content-simple-footer-inner"><div class="content-simple-footer-brand"><?php if ($logo !== ''): ?><img src="<?= $logo ?>" alt=""><?php endif; ?><div><strong><?= $naam ?></strong><?php if ($slogan !== ''): ?><div><?= $slogan ?></div><?php endif; ?></div></div><div>&copy; <?= date('Y') ?> <?= $naam ?></div></div></footer>
</body>
</html><?php
}

function contentRenderArtikelen(string $sleutel): void
{
    $bootstrap = contentPaginaBootstrap($sleutel);
    if (!$bootstrap || ($bootstrap['type'] ?? '') !== 'artikelen') {
        http_response_code(404);
        echo 'Pagina niet gevonden.';
        return;
    }

    $def = $bootstrap['definitie'];
    $data = $bootstrap['data'];
    $hero = $bootstrap['hero'];
    $taal = rc045Taal();
    $seoSleutel = $bootstrap['seo_sleutel'];

    $heroLabel = contentTaalWaarde($hero['label'] ?? [], $taal, '');
    $heroTitel = contentTaalWaarde($hero['titel'] ?? [], $taal, (string) ($def['label'] ?? ''));
    $heroSub = contentPaginaWaarde($data, 'hero_sub', $taal, '');
    $introBold = contentPaginaWaarde($data, 'intro_bold', $taal, '');
    $introText = contentPaginaWaarde($data, 'intro_text', $taal, '');
    $heroCss = contentPaginaHeroCss($sleutel);
    $artikelen = is_array($def['artikelen'] ?? null) ? $def['artikelen'] : [];

    $logo = contentEsc(siteAsset('branding.logo'));
    $naam = contentEsc(siteNaam());
    $slogan = contentEsc(siteSlogan());
    ?><!DOCTYPE html>
<html lang="<?= contentEsc($taal) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead($seoSleutel); ?>
<?php siteHeadBranding(); ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="styles.css">
<style nonce="<?=siteCspHtmlWaarde(siteCspNonce())?>" id="content-page-hero-2"><?= $heroCss ?></style>
<link rel="stylesheet" href="csp205-content-renderer-2-935f1f35b2f9.css">
</head>
<body>
<a href="#main-content" class="skip-link">Naar hoofdinhoud</a>
<div class="content-page-nav-wrap"><nav class="content-page-nav" aria-label="Hoofdnavigatie">
<a class="content-page-brand" href="index.html"><?php if ($logo !== ''): ?><img src="<?= $logo ?>" alt="<?= $naam ?> logo"><?php endif; ?><strong><?= $naam ?></strong></a>
<div class="content-page-links"><a class="home-link" href="index.html">Home</a><a href="index.html#over-ons">Over ons</a><a href="index.html#contact">Contact</a><span class="content-langs"><?php foreach (siteTalen() as $code => $_locale): ?><a class="<?= $code === $taal ? 'active' : '' ?>" href="<?= contentEsc(($def['slug'] ?? $sleutel) . '.html' . ($code === siteStandaardTaal() ? '' : '?lang=' . rawurlencode($code))) ?>"><?= strtoupper(contentEsc($code)) ?></a><?php endforeach; ?></span></div>
</nav></div>
<div class="page-hero"><div class="page-hero-bg"></div><div class="page-hero-gradient"></div><div class="page-hero-content"><?php if ($heroLabel !== ''): ?><div class="section-label"><?= contentEsc($heroLabel) ?></div><?php endif; ?><h1><?= contentEsc($heroTitel) ?></h1><?php if ($heroSub !== ''): ?><p><?= contentEsc($heroSub) ?></p><?php endif; ?></div></div>
<main class="content-main" id="main-content">
<?php if ($introBold !== '' || $introText !== ''): ?><section class="content-intro"><?php if ($introBold !== ''): ?><strong><?= contentEsc($introBold) ?></strong><?php endif; ?><?php if ($introText !== ''): ?> <?= nl2br(contentEsc($introText)) ?><?php endif; ?></section><?php endif; ?>
<?php foreach ($artikelen as $nummer => $artikelDef):
    $titelVeld = (string) ($artikelDef['titel'] ?? '');
    $inhoudVeld = (string) ($artikelDef['inhoud'] ?? '');
    $titel = $titelVeld !== '' ? contentPaginaWaarde($data, $titelVeld, $taal, '') : '';
    $inhoud = $inhoudVeld !== '' ? contentPaginaWaarde($data, $inhoudVeld, $taal, '') : '';
    if ($titel === '' && $inhoud === '') continue;
    $alineas = preg_split('/\R\s*\R/u', trim($inhoud)) ?: [];
?>
<section class="content-article">
<div class="content-article-head"><div class="content-article-num"><?= str_pad((string) $nummer, 2, '0', STR_PAD_LEFT) ?></div><div class="content-article-title"><?= contentEsc($titel) ?></div></div>
<?php if ($alineas): ?><div class="content-article-body"><?php foreach ($alineas as $alinea): $alinea = trim((string) $alinea); if ($alinea === '') continue; ?><p><?= nl2br(contentEsc($alinea)) ?></p><?php endforeach; ?></div><?php endif; ?>
</section>
<?php endforeach; ?>
</main>
<footer class="content-simple-footer"><div class="content-simple-footer-inner"><div class="content-simple-footer-brand"><?php if ($logo !== ''): ?><img src="<?= $logo ?>" alt=""><?php endif; ?><div><strong><?= $naam ?></strong><?php if ($slogan !== ''): ?><div><?= $slogan ?></div><?php endif; ?></div></div><div>&copy; <?= date('Y') ?> <?= $naam ?></div></div></footer>
</body>
</html><?php
}
