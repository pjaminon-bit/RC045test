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
<style>
<?= $heroCss ?>
.content-main{max-width:1000px;margin:0 auto;padding:56px 24px 80px}.content-card{background:var(--white);border:1.5px solid var(--border);border-radius:var(--radius);padding:40px;box-shadow:var(--shadow);margin-bottom:48px}.content-card p{font-size:16px;color:var(--muted);line-height:1.85;margin:0 0 18px}.content-card p:last-child{margin-bottom:0}.content-gallery-title{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--gold);margin-bottom:20px}.content-gallery{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}.content-gallery img{width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:var(--radius);cursor:pointer;box-shadow:var(--shadow)}.content-langs{display:flex;gap:8px;align-items:center}.content-langs a{text-decoration:none;font-weight:700;font-size:13px}.content-langs a.active{text-decoration:underline}.content-simple-footer{border-top:1px solid var(--border);padding:28px 24px;color:var(--muted)}.content-simple-footer-inner{max-width:1000px;margin:auto;display:flex;justify-content:space-between;gap:20px;align-items:center}.content-simple-footer-brand{display:flex;align-items:center;gap:12px}.content-simple-footer img{width:42px;height:42px;object-fit:contain}.content-page-nav{display:flex;align-items:center;justify-content:space-between;gap:18px;padding:12px 24px;max-width:1180px;margin:auto}.content-page-nav-wrap{background:var(--white);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:50}.content-page-brand{display:flex;align-items:center;gap:10px;text-decoration:none}.content-page-brand img{width:46px;height:46px;object-fit:contain}.content-page-brand strong{color:var(--dark);font-family:Poppins,sans-serif}.content-page-links{display:flex;gap:16px;align-items:center}.content-page-links a{color:var(--dark);text-decoration:none;font-weight:600;font-size:14px}@media(max-width:760px){.content-gallery{grid-template-columns:1fr 1fr}.content-page-links a:not(.home-link){display:none}.content-card{padding:26px 22px}}@media(max-width:520px){.content-gallery{grid-template-columns:1fr}.content-simple-footer-inner{flex-direction:column;align-items:flex-start}}
</style>
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
