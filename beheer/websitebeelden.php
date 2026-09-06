<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/core/tenant-settings.php';
require_once dirname(__DIR__) . '/app/core/tenant-branding-assets.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!authHeeftCapability('system.settings.manage', true)) { http_response_code(403); echo 'Geen toegang tot Websitebeelden.'; exit; }

function wbEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function wbPreview(string $url): string {
    if ($url === '') return '';
    return preg_match('~^https?://~i', $url) ? $url : '../' . ltrim($url, '/');
}

$config = $authSiteConfig;
$extern = tenantRuntimeExternConfigPad() !== null;
$melding = '';
$type = '';
$labels = [
    'hero' => ['Hero / paginakop', 'Brede sfeerfoto achter de bovenste paginakop. Landschap werkt het best.'],
    'about' => ['Over-ons foto', 'De belangrijkste foto bij de introductie van de vereniging.'],
    'activity' => ['Activiteitenfoto', 'Wordt gebruikt bij baan/activiteiten en andere dynamische beeldvlakken.'],
    'gallery' => ['Fotostrook', 'Basisbeeld voor de brede fotostrook/carrousel zolang geen specifieke galerij is ingericht.'],
];

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) { $melding='Sessie verlopen. Ververs de pagina en probeer opnieuw.'; $type='fout'; }
    elseif (!$extern) { $melding='Websitebeelden zijn alleen bewerkbaar op een geïsoleerde tenantinstallatie.'; $type='fout'; }
    else {
        try {
            $beelden = is_array($config['branding']['afbeeldingen'] ?? null) ? $config['branding']['afbeeldingen'] : [];
            foreach (array_keys($labels) as $sleutel) {
                $huidig = trim((string)($beelden[$sleutel] ?? ''));
                if (!empty($_FILES[$sleutel]) && (int)($_FILES[$sleutel]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $huidig = tenantBrandingAssetUpload($config, $_FILES[$sleutel], $sleutel);
                }
                if (!empty($_POST[$sleutel . '_verwijderen'])) $huidig = '';
                $beelden[$sleutel] = $huidig;
            }
            $input = [
                'vereniging' => [
                    'naam' => $config['vereniging']['naam'] ?? 'Vereniging',
                    'volledige_naam' => $config['vereniging']['volledige_naam'] ?? ($config['vereniging']['naam'] ?? 'Vereniging'),
                    'slogan' => $config['vereniging']['slogan'] ?? '',
                ],
                'branding' => [
                    'logo' => $config['branding']['logo'] ?? '',
                    'favicon' => $config['branding']['favicon'] ?? '',
                    'theme_color' => $config['branding']['theme_color'] ?? '#1E2C13',
                    'kleuren' => $config['branding']['kleuren'] ?? [],
                    'afbeeldingen' => $beelden,
                ],
                'betaling' => $config['betaling'] ?? [],
            ];
            if (!tenantSettingsSchrijf($config, $input)) throw new RuntimeException('Websitebeelden konden niet veilig worden opgeslagen.');
            schrijfLog($logBestand, $huidigeGebruiker, 'websitebeelden', 'publieke websitebeelden bijgewerkt');
            header('Location: websitebeelden.php?opgeslagen=1');
            exit;
        } catch (Throwable $e) {
            error_log('[platform] websitebeelden opslaan mislukt: ' . $e->getMessage());
            $melding = 'Opslaan is mislukt. Gebruik een geldige PNG, JPG of WebP van maximaal 8 MB.';
            $type = 'fout';
        }
    }
}
if (isset($_GET['opgeslagen'])) { $melding='Websitebeelden opgeslagen en direct actief.'; $type='ok'; }
$config = require dirname(__DIR__) . '/site-config.php';
$beelden = is_array($config['branding']['afbeeldingen'] ?? null) ? $config['branding']['afbeeldingen'] : [];
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Websitebeelden</title><link rel="stylesheet" href="csp205-websitebeelden-319a01ff34de.css"></head><body><header class="top"><div class="topin"><a href="./">← Terug naar beheer</a><a href="../index.html" target="_blank" rel="noopener">Bekijk website ↗</a></div></header><main class="wrap"><div class="intro"><h1>Websitebeelden</h1><p>Geef de template direct een eigen gezicht. Zonder upload gebruikt de website een neutrale placeholder; voorbeeldfoto’s van een andere vereniging worden nooit getoond.</p></div><?php if($melding!==''):?><div class="melding <?=wbEsc($type)?>"><?=wbEsc($melding)?></div><?php endif;?><?php if(!$extern):?><div class="waarschuwing">Deze functie is alleen actief voor geïsoleerde tenantinstallaties.</div><?php endif;?><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=wbEsc($csrfToken)?>"><div class="grid"><?php foreach($labels as$sleutel=>$info):$url=trim((string)($beelden[$sleutel]??''));?><section class="kaart"><div class="preview"><?php if($url!==''):?><img src="<?=wbEsc(wbPreview($url))?>" alt="Voorbeeld <?=wbEsc($info[0])?>"><?php else:?><div class="placeholder">Nog geen eigen beeld</div><?php endif;?></div><div class="inhoud"><h2><?=wbEsc($info[0])?></h2><p><?=wbEsc($info[1])?></p><input type="file" name="<?=wbEsc($sleutel)?>" accept="image/png,image/jpeg,image/webp"><?php if($url!==''):?><label class="check"><input type="checkbox" name="<?=wbEsc($sleutel)?>_verwijderen" value="1"> Huidig beeld verwijderen</label><?php endif;?></div></section><?php endforeach;?></div><button class="btn" type="submit"<?=$extern?'':' disabled'?>>Websitebeelden opslaan</button></form></main></body></html>
