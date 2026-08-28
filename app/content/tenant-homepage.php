<?php
// ============================================================
// Server-side publieke homepage voor externe tenants
// ============================================================

require_once dirname(__DIR__) . '/core/site.php';
require_once __DIR__ . '/public-content-store.php';
require_once __DIR__ . '/tenant-content-policy.php';

function tenantHomepageActief(): bool
{
    return tenantContentIsExtern();
}

function tenantHomepageEsc($waarde): string
{
    return htmlspecialchars((string) $waarde, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

function tenantHomepageTekst(array $data, string $veld, string $taal, string $standaard = ''): string
{
    if (!array_key_exists($veld, $data)) return $standaard;
    $waarde = $data[$veld];
    if (is_array($waarde)) {
        $kandidaat = $waarde[$taal] ?? $waarde['nl'] ?? '';
        return is_scalar($kandidaat) && trim((string) $kandidaat) !== '' ? trim((string) $kandidaat) : $standaard;
    }
    return is_scalar($waarde) && trim((string) $waarde) !== '' ? trim((string) $waarde) : $standaard;
}

function tenantHomepageVeiligeUrl($waarde): string
{
    $url = trim((string) $waarde);
    if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) return '';
    $schema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($schema, ['http', 'https'], true) ? $url : '';
}

function tenantHomepageInitialen(string $naam): string
{
    $woorden = preg_split('/\s+/u', trim($naam)) ?: [];
    $letters = '';
    foreach ($woorden as $woord) {
        if ($woord === '') continue;
        $letters .= strtoupper(substr($woord, 0, 1));
        if (strlen($letters) >= 2) break;
    }
    return $letters !== '' ? $letters : 'V';
}

function tenantHomepageRender(): void
{
    $naam = trim(siteNaam()) ?: 'Vereniging';
    $volledigeNaam = trim(siteVolledigeNaam()) ?: $naam;
    $slogan = trim(siteSlogan());
    $taal = rc045Taal();
    $logo = trim(siteAsset('branding.logo'));
    $standaard = tenantContentNeutraleHomepage($naam);
    $opgeslagen = tenantHomepageDataset('homepage');
    $homepage = array_replace_recursive($standaard, $opgeslagen);
    $contact = tenantHomepageDataset('contact');

    $email = trim((string) ($contact['email'] ?? ''));
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) $email = '';
    $facebook = tenantHomepageVeiligeUrl($contact['facebook'] ?? '');
    $instagram = tenantHomepageVeiligeUrl($contact['instagram'] ?? '');
    $straat = trim((string) ($contact['adres_straat'] ?? ''));
    $plaats = trim((string) ($contact['adres_postcode_plaats'] ?? ''));

    $ui = [
        'nl' => ['skip'=>'Ga naar de inhoud','nav'=>'Hoofdnavigatie','home'=>'Home','about'=>'Over ons','participate'=>'Meedoen','contact'=>'Contact','eyebrow'=>'Welkom bij','about_eyebrow'=>'Onze vereniging','participate_eyebrow'=>'Doe mee','contact_eyebrow'=>'Contact','contact_empty'=>'Contactgegevens kunnen door het verenigingsbeheer worden ingevuld.','official'=>'Officiële verenigingswebsite','more'=>'Meer informatie','member'=>'Lid worden','manage'=>'Beheer','language'=>'Taal'],
        'en' => ['skip'=>'Skip to content','nav'=>'Main navigation','home'=>'Home','about'=>'About us','participate'=>'Join us','contact'=>'Contact','eyebrow'=>'Welcome to','about_eyebrow'=>'Our association','participate_eyebrow'=>'Get involved','contact_eyebrow'=>'Contact','contact_empty'=>'Contact details can be added by the association administrator.','official'=>'Official association website','more'=>'More information','member'=>'Become a member','manage'=>'Admin','language'=>'Language'],
        'de' => ['skip'=>'Zum Inhalt','nav'=>'Hauptnavigation','home'=>'Start','about'=>'Über uns','participate'=>'Mitmachen','contact'=>'Kontakt','eyebrow'=>'Willkommen bei','about_eyebrow'=>'Unser Verein','participate_eyebrow'=>'Mitmachen','contact_eyebrow'=>'Kontakt','contact_empty'=>'Kontaktdaten können von der Vereinsverwaltung ergänzt werden.','official'=>'Offizielle Vereinswebsite','more'=>'Mehr erfahren','member'=>'Mitglied werden','manage'=>'Verwaltung','language'=>'Sprache'],
    ][$taal] ?? null;
    if (!is_array($ui)) $ui = ['skip'=>'Ga naar de inhoud','nav'=>'Hoofdnavigatie','home'=>'Home','about'=>'Over ons','participate'=>'Meedoen','contact'=>'Contact','eyebrow'=>'Welkom bij','about_eyebrow'=>'Onze vereniging','participate_eyebrow'=>'Doe mee','contact_eyebrow'=>'Contact','contact_empty'=>'Contactgegevens kunnen door het verenigingsbeheer worden ingevuld.','official'=>'Officiële verenigingswebsite','more'=>'Meer informatie','member'=>'Lid worden','manage'=>'Beheer','language'=>'Taal'];

    $intro = tenantHomepageTekst($homepage, 'hero_intro', $taal, $standaard['hero_intro'][$taal] ?? $standaard['hero_intro']['nl']);
    $aboutTitel = tenantHomepageTekst($homepage, 'about_title', $taal, $standaard['about_title'][$taal] ?? $standaard['about_title']['nl']);
    $aboutP1 = tenantHomepageTekst($homepage, 'about_p1', $taal, $standaard['about_p1'][$taal] ?? $standaard['about_p1']['nl']);
    $aboutP2 = tenantHomepageTekst($homepage, 'about_p2', $taal, $standaard['about_p2'][$taal] ?? $standaard['about_p2']['nl']);
    $participateTitel = tenantHomepageTekst($homepage, 'pricing_title', $taal, $standaard['pricing_title'][$taal] ?? $standaard['pricing_title']['nl']);
    $participateTekst = tenantHomepageTekst($homepage, 'pricing_sub', $taal, $standaard['pricing_sub'][$taal] ?? $standaard['pricing_sub']['nl']);
    $contactTitel = tenantHomepageTekst($homepage, 'contact_title', $taal, $standaard['contact_title'][$taal] ?? $standaard['contact_title']['nl']);
    $contactTekst = tenantHomepageTekst($homepage, 'contact_text', $taal, $standaard['contact_text'][$taal] ?? $standaard['contact_text']['nl']);
    $features = [];
    foreach ([1, 2, 3, 4] as $nummer) {
        $titel = tenantHomepageTekst($homepage, 'feat' . $nummer . '_title', $taal, $standaard['feat' . $nummer . '_title'][$taal] ?? $standaard['feat' . $nummer . '_title']['nl']);
        $tekst = tenantHomepageTekst($homepage, 'feat' . $nummer . '_text', $taal, $standaard['feat' . $nummer . '_text'][$taal] ?? $standaard['feat' . $nummer . '_text']['nl']);
        if ($titel !== '' || $tekst !== '') $features[] = ['titel' => $titel, 'tekst' => $tekst];
    }

    $structured = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $volledigeNaam, 'url' => siteUrl()];
    if ($email !== '') $structured['email'] = $email;
    $structuredJson = json_encode($structured, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    $jaar = date('Y');
    ?><!DOCTYPE html>
<html lang="<?= tenantHomepageEsc($taal) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
<?php rc045SeoHead('index'); ?>
<?php siteHeadBranding(); ?>
<?= siteThemeMarkup() ?>
  <link rel="stylesheet" href="tenant-homepage.css">
  <script id="tenant-structured-data" type="application/ld+json"><?= $structuredJson ?: '{}' ?></script>
</head>
<body class="tenant-homepage" data-template="tenant-neutral-v1">
  <a class="tp-skip" href="#inhoud"><?= tenantHomepageEsc($ui['skip']) ?></a>
  <header class="tp-header">
    <div class="tp-header-inner">
      <a class="tp-brand" href="index.html" aria-label="<?= tenantHomepageEsc($naam) ?>">
        <?php if ($logo !== ''): ?>
          <img class="tp-logo" src="<?= tenantHomepageEsc($logo) ?>" alt="<?= tenantHomepageEsc($naam) ?> logo">
        <?php else: ?>
          <span class="tp-monogram" aria-hidden="true"><?= tenantHomepageEsc(tenantHomepageInitialen($naam)) ?></span>
        <?php endif; ?>
        <span><strong><?= tenantHomepageEsc($naam) ?></strong><?php if ($slogan !== ''): ?><small><?= tenantHomepageEsc($slogan) ?></small><?php endif; ?></span>
      </a>
      <nav class="tp-nav" aria-label="<?= tenantHomepageEsc($ui['nav']) ?>">
        <a href="#over-ons"><?= tenantHomepageEsc($ui['about']) ?></a>
        <a href="#meedoen"><?= tenantHomepageEsc($ui['participate']) ?></a>
        <a href="#contact"><?= tenantHomepageEsc($ui['contact']) ?></a>
        <a href="beheer/" rel="nofollow"><?= tenantHomepageEsc($ui['manage']) ?></a>
      </nav>
    </div>
  </header>

  <main id="inhoud">
    <section class="tp-hero" aria-labelledby="tp-title">
      <div class="tp-shell tp-hero-inner">
        <div class="tp-hero-copy">
          <p class="tp-eyebrow"><?= tenantHomepageEsc($ui['eyebrow']) ?></p>
          <h1 id="tp-title"><?= tenantHomepageEsc($volledigeNaam) ?></h1>
          <p class="tp-lead"><?= tenantHomepageEsc($intro) ?></p>
          <div class="tp-actions">
            <a class="tp-button tp-button-primary" href="#over-ons"><?= tenantHomepageEsc($ui['more']) ?></a>
            <a class="tp-button tp-button-secondary" href="#contact"><?= tenantHomepageEsc($ui['contact']) ?></a>
          </div>
        </div>
        <div class="tp-hero-mark" aria-hidden="true"><?= tenantHomepageEsc(tenantHomepageInitialen($naam)) ?></div>
      </div>
    </section>

    <div class="tp-shell tp-flow">
      <section class="tp-section" id="over-ons" aria-labelledby="tp-about-title">
        <p class="tp-eyebrow"><?= tenantHomepageEsc($ui['about_eyebrow']) ?></p>
        <h2 id="tp-about-title"><?= tenantHomepageEsc($aboutTitel) ?></h2>
        <div class="tp-copy"><p><?= nl2br(tenantHomepageEsc($aboutP1)) ?></p><p><?= nl2br(tenantHomepageEsc($aboutP2)) ?></p></div>
        <div class="tp-feature-list">
          <?php foreach ($features as $feature): ?>
            <article class="tp-card"><h3><?= tenantHomepageEsc($feature['titel']) ?></h3><p><?= nl2br(tenantHomepageEsc($feature['tekst'])) ?></p></article>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="tp-section tp-section-accent" id="meedoen" aria-labelledby="tp-participate-title">
        <p class="tp-eyebrow"><?= tenantHomepageEsc($ui['participate_eyebrow']) ?></p>
        <h2 id="tp-participate-title"><?= tenantHomepageEsc($participateTitel) ?></h2>
        <p class="tp-lead tp-lead-small"><?= nl2br(tenantHomepageEsc($participateTekst)) ?></p>
        <?php if (siteModuleActief('aanmelden')): ?><a class="tp-button tp-button-primary" href="aanmelden.html"><?= tenantHomepageEsc($ui['member']) ?></a><?php endif; ?>
      </section>

      <section class="tp-section" id="contact" aria-labelledby="tp-contact-title">
        <p class="tp-eyebrow"><?= tenantHomepageEsc($ui['contact_eyebrow']) ?></p>
        <h2 id="tp-contact-title"><?= tenantHomepageEsc($contactTitel) ?></h2>
        <p class="tp-lead tp-lead-small"><?= nl2br(tenantHomepageEsc($contactTekst)) ?></p>
        <?php if ($email !== '' || $straat !== '' || $plaats !== '' || $facebook !== '' || $instagram !== ''): ?>
          <address class="tp-contact-list">
            <?php if ($email !== ''): ?><a class="tp-card" href="mailto:<?= tenantHomepageEsc($email) ?>"><strong>E-mail</strong><span><?= tenantHomepageEsc($email) ?></span></a><?php endif; ?>
            <?php if ($straat !== '' || $plaats !== ''): ?><div class="tp-card"><strong>Adres</strong><span><?= tenantHomepageEsc($straat) ?><?php if ($straat !== '' && $plaats !== ''): ?><br><?php endif; ?><?= tenantHomepageEsc($plaats) ?></span></div><?php endif; ?>
            <?php if ($facebook !== ''): ?><a class="tp-card" href="<?= tenantHomepageEsc($facebook) ?>" rel="noopener noreferrer" target="_blank"><strong>Facebook</strong><span><?= tenantHomepageEsc($facebook) ?></span></a><?php endif; ?>
            <?php if ($instagram !== ''): ?><a class="tp-card" href="<?= tenantHomepageEsc($instagram) ?>" rel="noopener noreferrer" target="_blank"><strong>Instagram</strong><span><?= tenantHomepageEsc($instagram) ?></span></a><?php endif; ?>
          </address>
        <?php else: ?>
          <p class="tp-empty"><?= tenantHomepageEsc($ui['contact_empty']) ?></p>
        <?php endif; ?>
      </section>
    </div>
  </main>

  <footer class="tp-footer">
    <div class="tp-shell tp-footer-inner">
      <div><strong><?= tenantHomepageEsc($naam) ?></strong><span><?= tenantHomepageEsc($ui['official']) ?></span></div>
      <div class="tp-languages" aria-label="<?= tenantHomepageEsc($ui['language']) ?>">
        <?php foreach (siteTalen() as $code => $_locale): ?><a<?= $code === $taal ? ' aria-current="page"' : '' ?> href="<?= $code === siteStandaardTaal() ? 'index.html' : 'index.html?lang=' . rawurlencode((string) $code) ?>"><?= strtoupper(tenantHomepageEsc((string) $code)) ?></a><?php endforeach; ?>
      </div>
      <span>© <?= tenantHomepageEsc($jaar) ?></span>
    </div>
  </footer>
</body>
</html><?php
}
