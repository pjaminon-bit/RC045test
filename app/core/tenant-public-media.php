<?php
// ============================================================
// Tenant-eigen websitebeelden
// ============================================================
// Draait als binnenste outputfilter vóór de algemene neutralisatielaag. Eigen
// tenantbeelden krijgen absolute HTTPS-URLs en blijven daardoor intact wanneer
// alle overgebleven voorbeeldmedia daarna naar placeholders worden omgezet.
// ============================================================

function tenantPublicMediaUrl(array $config, string $sleutel): string
{
    $beelden = is_array($config['branding']['afbeeldingen'] ?? null) ? $config['branding']['afbeeldingen'] : [];
    $url = trim((string)($beelden[$sleutel] ?? ''));
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    return in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true) ? $url : '';
}

function tenantPublicMediaTransform(string $html, array $config): string
{
    if ($html === '' || !class_exists(DOMDocument::class)) return $html;
    $hero = tenantPublicMediaUrl($config, 'hero');
    $about = tenantPublicMediaUrl($config, 'about');
    $activity = tenantPublicMediaUrl($config, 'activity');
    $gallery = tenantPublicMediaUrl($config, 'gallery');
    if ($hero === '' && $about === '' && $activity === '' && $gallery === '') return $html;

    $fallback = static function (string ...$waarden): string {
        foreach ($waarden as $waarde) if ($waarde !== '') return $waarde;
        return '';
    };
    $about = $fallback($about, $activity, $gallery, $hero);
    $activity = $fallback($activity, $gallery, $about, $hero);
    $gallery = $fallback($gallery, $activity, $about, $hero);
    $hero = $fallback($hero, $gallery, $activity, $about);

    $vorige = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $geladen = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($vorige);
    if (!$geladen) return $html;
    foreach ($dom->childNodes as $kind) {
        if ($kind->nodeType === XML_PI_NODE) { $dom->removeChild($kind); break; }
    }
    $xpath = new DOMXPath($dom);

    $zetImg = static function ($nodes, string $url): void {
        if ($url === '') return;
        foreach ($nodes ?: [] as $node) {
            if (!$node instanceof DOMElement) continue;
            $node->setAttribute('src', $url);
            if ($node->hasAttribute('data-src')) $node->setAttribute('data-src', $url);
            $node->setAttribute('loading', $node->getAttribute('loading') ?: 'lazy');
        }
    };
    $klasse = static fn(string $naam): string => "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$naam} ')]";

    $zetImg($xpath->query($klasse('about-img-main')), $about);
    $zetImg($xpath->query($klasse('about-img-secondary')), $activity);
    $zetImg($xpath->query($klasse('about-photo')), $gallery);
    $zetImg($xpath->query($klasse('track-photo')), $activity);
    $zetImg($xpath->query($klasse('carousel-img')), $gallery);

    foreach ($xpath->query($klasse('carousel-slide-bg')) ?: [] as $node) {
        if ($node instanceof DOMElement && $gallery !== '') $node->setAttribute('data-bg', $gallery);
    }
    foreach ($xpath->query("//*[@id='hero-bg' or contains(concat(' ', normalize-space(@class), ' '), ' page-hero-bg ')]") ?: [] as $node) {
        if (!$node instanceof DOMElement || $hero === '') continue;
        $node->setAttribute('data-bg', $hero);
        $node->setAttribute('style', "background-image:url('" . htmlspecialchars($hero, ENT_QUOTES, 'UTF-8') . "')");
    }

    return $dom->saveHTML() ?: $html;
}

function tenantPublicMediaStart(array $config, ?string $externPad): void
{
    if ($externPad === null || PHP_SAPI === 'cli') return;
    static $gestart = false;
    if ($gestart) return;
    $gestart = true;
    ob_start(static fn(string $html): string => tenantPublicMediaTransform($html, $config));
}
