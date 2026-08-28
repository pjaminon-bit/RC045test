<?php
// ============================================================
// Tenantlaag bovenop de bestaande RC045-homepagestructuur
// ============================================================
// Externe tenants gebruiken exact dezelfde index.php, CSS-klassen, navigatie,
// sectievolgorde en responsive breakpoints als RC045. Alleen identiteit,
// inhoud, links en media worden vóór verzending tenantveilig ingevuld.
// ============================================================

require_once dirname(__DIR__) . '/core/site.php';
require_once __DIR__ . '/public-content-store.php';
require_once __DIR__ . '/tenant-content-policy.php';

function tenantHomepageActief(): bool
{
    return tenantContentIsExtern();
}

function tenantHomepageDataset(string $sleutel): array
{
    try {
        $data = publicContentLees($sleutel);
    } catch (Throwable $e) {
        error_log('[platform] tenant-homepage kon dataset niet lezen: ' . $sleutel);
        return [];
    }
    if (!is_array($data)) return [];
    if (tenantContentBevatLegacy($data)) {
        error_log('[platform] legacy publieke content geweigerd voor externe tenant: ' . $sleutel);
        return [];
    }
    return $data;
}

function tenantHomepageTaalWaarde($waarde, string $taal = 'nl'): string
{
    if (is_array($waarde)) {
        $waarde = $waarde[$taal] ?? $waarde['nl'] ?? '';
    }
    return is_scalar($waarde) ? trim((string) $waarde) : '';
}

function tenantHomepageDomTekst(DOMDocument $dom, string $id, string $tekst): void
{
    $node = $dom->getElementById($id);
    if (!$node instanceof DOMElement || $tekst === '') return;
    while ($node->firstChild !== null) $node->removeChild($node->firstChild);
    $node->appendChild($dom->createTextNode($tekst));
}

function tenantHomepageDomHref(DOMDocument $dom, string $id, string $href): void
{
    $node = $dom->getElementById($id);
    if ($node instanceof DOMElement) $node->setAttribute('href', $href);
}

function tenantHomepageVeiligeUrl($waarde): string
{
    $url = trim((string) $waarde);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true) ? $url : '';
}

function tenantHomepagePasTemplateToe(string $html): string
{
    if (!tenantHomepageActief() || trim($html) === '') return $html;
    if (!class_exists(DOMDocument::class)) {
        throw new RuntimeException('DOM-extensie ontbreekt; tenanthomepage kan niet veilig worden gerenderd.');
    }

    $naam = trim(siteNaam()) ?: 'Vereniging';
    $slogan = trim(siteSlogan());
    $taal = function_exists('rc045Taal') ? rc045Taal() : siteStandaardTaal();
    $homepage = array_replace_recursive(tenantContentNeutraleHomepage($naam), tenantHomepageDataset('homepage'));
    $contact = array_replace_recursive(tenantContentNeutraalContact(), tenantHomepageDataset('contact'));
    $logo = trim(siteAsset('branding.logo')) ?: 'images/template-placeholder.svg';
    $email = filter_var((string) ($contact['email'] ?? ''), FILTER_VALIDATE_EMAIL) ? (string) $contact['email'] : '';
    $facebook = tenantHomepageVeiligeUrl($contact['facebook'] ?? '');
    $instagram = tenantHomepageVeiligeUrl($contact['instagram'] ?? '');
    $siteUrl = siteUrl();

    $vorige = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $geladen = $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
    libxml_clear_errors();
    libxml_use_internal_errors($vorige);
    if (!$geladen) throw new RuntimeException('Tenanthomepage-HTML kon niet veilig worden verwerkt.');

    foreach ($dom->childNodes as $kind) {
        if ($kind->nodeType === XML_PI_NODE) { $dom->removeChild($kind); break; }
    }
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body instanceof DOMElement) {
        $body->setAttribute('data-template', 'tenant-shared-v1');
        $body->setAttribute('class', trim($body->getAttribute('class') . ' tenant-shared-template'));
    }

    $veldIds = [];
    foreach (array_keys($homepage) as $veld) $veldIds[(string) $veld] = 'hp-' . str_replace('_', '-', (string) $veld);
    $veldIds['form_send'] = 'form-btn';
    $veldIds += [
        'nav_about'=>'nav-about', 'nav_membership'=>'nav-membership', 'nav_track'=>'nav-track',
        'nav_location'=>'nav-location', 'nav_photobook'=>'nav-photobook', 'nav_contact'=>'nav-contact', 'nav_join'=>'nav-join',
        'footer_brand'=>'footer-brand-text', 'footer_nav'=>'footer-nav-title', 'footer_origin'=>'footer-link-origin',
        'footer_media'=>'footer-link-media', 'footer_photobook'=>'footer-link-photobook', 'footer_calendar'=>'footer-link-calendar',
        'footer_join'=>'footer-join-title', 'footer_become'=>'footer-link-become', 'footer_rules'=>'footer-link-rules',
        'footer_sponsor'=>'footer-link-sponsor', 'footer_sponsors_title'=>'footer-sponsors-title', 'footer_credit'=>'footer-credit-text',
    ];
    foreach ($veldIds as $veld => $id) {
        $tekst = tenantHomepageTaalWaarde($homepage[$veld] ?? '', $taal);
        if ($tekst !== '') tenantHomepageDomTekst($dom, $id, $tekst);
    }

    $xpath = new DOMXPath($dom);
    foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' nav-logo-text ')]") ?: [] as $node) {
        if ($node instanceof DOMElement) $node->textContent = $naam;
    }
    foreach ($xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' nav-logo-sub ')]") ?: [] as $node) {
        if ($node instanceof DOMElement) $node->textContent = $slogan;
    }

    $heroH1 = $xpath->query("//section[contains(concat(' ', normalize-space(@class), ' '), ' hero ')]//h1")->item(0);
    if ($heroH1 instanceof DOMElement) {
        while ($heroH1->firstChild !== null) $heroH1->removeChild($heroH1->firstChild);
        $heroH1->appendChild($dom->createTextNode($naam));
        if ($slogan !== '') {
            $heroH1->appendChild($dom->createElement('br'));
            $span = $dom->createElement('span');
            $span->appendChild($dom->createTextNode($slogan));
            $heroH1->appendChild($span);
        }
    }

    foreach ($dom->getElementsByTagName('img') as $img) {
        if (!$img instanceof DOMElement) continue;
        foreach (['src', 'data-src'] as $attribuut) {
            $bron = $img->getAttribute($attribuut);
            if ($bron === '') continue;
            if (preg_match('~(?:rc045-logo|images/(?:crawler|basher|rc045))~i', $bron)) {
                $img->setAttribute($attribuut, str_contains(strtolower($bron), 'logo') ? $logo : 'images/template-placeholder.svg');
            }
        }
        if (preg_match('~rc045|crawler|basher~i', $img->getAttribute('alt'))) $img->setAttribute('alt', $naam);
    }
    foreach ($xpath->query('//*[@data-bg]') ?: [] as $node) {
        if ($node instanceof DOMElement) $node->setAttribute('data-bg', 'images/template-placeholder.svg');
    }
    $heroBg = $dom->getElementById('hero-bg');
    if ($heroBg instanceof DOMElement) {
        $heroBg->removeAttribute('style');
        $heroBg->setAttribute('data-bg', 'images/template-placeholder.svg');
    }

    $straat = trim((string) ($contact['adres_straat'] ?? ''));
    $plaats = trim((string) ($contact['adres_postcode_plaats'] ?? ''));
    $adresTekst = $straat !== '' ? $straat : 'Nog niet ingevuld';
    $plaatsTekst = $plaats !== '' ? $plaats : 'Nog niet ingevuld';
    foreach (['info-adres-straat', 'addr-straat'] as $id) tenantHomepageDomTekst($dom, $id, $adresTekst);
    foreach (['info-adres-plaats', 'addr-postcode-plaats'] as $id) tenantHomepageDomTekst($dom, $id, $plaatsTekst);
    tenantHomepageDomTekst($dom, 'contact-email-value', $email !== '' ? $email : 'Nog niet ingevuld');
    tenantHomepageDomHref($dom, 'contact-email-link', $email !== '' ? 'mailto:' . $email : '#contact');
    tenantHomepageDomTekst($dom, 'contact-facebook-value', $facebook !== '' ? preg_replace('~^https?://~i', '', $facebook) : 'Nog niet ingevuld');
    tenantHomepageDomHref($dom, 'contact-facebook-link', $facebook !== '' ? $facebook : '#contact');
    tenantHomepageDomHref($dom, 'footer-facebook-link', $facebook !== '' ? $facebook : '#contact');
    tenantHomepageDomHref($dom, 'contact-instagram-link', $instagram !== '' ? $instagram : '#contact');
    foreach (['prijs-gast-volwassen','prijs-gast-jeugd','prijs-jeugd','prijs-senior','prijs-inschrijf','info-membership-value'] as $id) {
        tenantHomepageDomTekst($dom, $id, '—');
    }

    foreach ($dom->getElementsByTagName('iframe') as $iframe) {
        if (!$iframe instanceof DOMElement) continue;
        $iframe->removeAttribute('src');
        $iframe->setAttribute('srcdoc', '<!doctype html><html lang="nl"><body style="margin:0;display:grid;place-items:center;height:100%;font-family:sans-serif;background:#eef3ef;color:#526258">Locatiekaart nog niet ingesteld</body></html>');
        $iframe->setAttribute('title', 'Locatie van ' . $naam);
    }

    $form = $dom->getElementById('contact-form');
    $formActie = tenantHomepageVeiligeUrl(siteConfigGet('contact.form_action', ''));
    if ($form instanceof DOMElement && $formActie === '') {
        $form->setAttribute('action', '#contact');
        $form->setAttribute('data-tenant-disabled', '1');
        foreach ($xpath->query('.//button[@type="submit"]', $form) ?: [] as $knop) {
            if ($knop instanceof DOMElement) $knop->setAttribute('disabled', 'disabled');
        }
    } elseif ($form instanceof DOMElement) {
        $form->setAttribute('action', $formActie);
    }

    $structured = $dom->getElementById('structured-data');
    if ($structured instanceof DOMElement) {
        $json = ['@context'=>'https://schema.org','@type'=>'Organization','name'=>siteVolledigeNaam(),'url'=>$siteUrl];
        if ($email !== '') $json['email'] = $email;
        $structured->textContent = json_encode($json, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?: '{}';
    }
    foreach ($xpath->query('//script[@data-goatcounter or contains(@src,"goatcounter") or contains(@src,"gc.zgo.at")]') ?: [] as $script) {
        $script->parentNode?->removeChild($script);
    }

    $context = [
        'external'=>true,
        'tenantKey'=>(string) siteConfigGet('vereniging.sleutel', 'tenant'),
        'name'=>$naam,
        'siteUrl'=>$siteUrl,
        'homepage'=>$homepage,
        'contact'=>$contact,
    ];
    $contextScript = $dom->createElement('script');
    $contextScript->setAttribute('id', 'vereniging-site-context');
    $contextScript->textContent = 'window.verenigingSiteContext=' . (json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?: '{}') . ';';
    $head = $dom->getElementsByTagName('head')->item(0);
    if ($head instanceof DOMElement) $head->appendChild($contextScript);

    $vervangingen = [
        'RC045 – Bashers of the South'=>$naam . ($slogan !== '' ? ' – ' . $slogan : ''),
        'RC045 · Bashers of the South'=>$naam . ($slogan !== '' ? ' · ' . $slogan : ''),
        'Bashers of the South'=>$slogan,
        'bestuur@rc045.nl'=>$email !== '' ? $email : 'contactgegevens volgen',
        'facebook.com/rc045'=>$facebook !== '' ? preg_replace('~^https?://~i', '', $facebook) : 'sociale media volgen',
        'RC045'=>$naam,
        'Wijngaardsberg 26'=>$adresTekst,
        '6464 EZ Eygelshoven'=>$plaatsTekst,
        'Kerkrade (Eygelshoven)'=>$plaatsTekst,
        'Eygelshoven'=>$plaatsTekst,
        'Kok Lexmond'=>'de locatiebeheerder',
    ];
    $uit = $dom->saveHTML() ?: '';
    $uit = str_ireplace(array_keys($vervangingen), array_values($vervangingen), $uit);
    if (tenantContentBevatLegacy($uit)) {
        throw new RuntimeException('Legacy-identiteit bleef achter in tenanthomepage-output.');
    }
    return $uit;
}

function tenantHomepageStartOutputFilter(): void
{
    static $actief = false;
    if ($actief || !tenantHomepageActief()) return;
    $actief = true;
    ob_start(static fn(string $html): string => tenantHomepagePasTemplateToe($html));
}
