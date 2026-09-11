<?php
// ============================================================
// Modulaire beheerpagina: Sponsors
// ============================================================

require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/core/site.php';
require_once dirname(__DIR__) . '/app/core/atomic-file-transaction.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/content/public-content-store.php';
require_once dirname(__DIR__) . '/app/content/public-asset-store.php';

if (!$ingelogd) {
    header('Location: ../beheer.php');
    exit;
}

if (!siteModuleActief('sponsors')) {
    http_response_code(404);
    echo 'De sponsormodule is voor deze vereniging niet ingeschakeld.';
    exit;
}

$rechten = authRechten(['sponsors' => 'Sponsors'], []);
if (!$isMaster && !in_array('sponsors', $rechten['toegestaneTabs'] ?? [], true)) {
    http_response_code(403);
    echo 'Geen toegang tot Sponsors.';
    exit;
}

$sponsorBestand = publicContentPad('sponsors');
$sponsorMap = publicAssetMaakNamespaceMap('sponsors');
if ($sponsorBestand === null || $sponsorMap === null) {
    throw new RuntimeException('Sponsoropslag is niet correct geregistreerd voor deze tenant.');
}

function sponsorsEsc($waarde): string
{
    return htmlspecialchars((string) $waarde, ENT_QUOTES, 'UTF-8');
}

function sponsorsKort($waarde, int $max): string
{
    $tekst = trim(is_scalar($waarde) ? (string) $waarde : '');
    return function_exists('mb_substr') ? mb_substr($tekst, 0, $max, 'UTF-8') : substr($tekst, 0, $max);
}

function sponsorsLees(string $pad): array
{
    if (!is_file($pad)) return ['updated' => null, 'items' => [], 'cta' => ['nl' => '', 'en' => '', 'de' => '']];
    $data = json_decode((string) @file_get_contents($pad), true);
    if (!is_array($data)) return ['updated' => null, 'items' => [], 'cta' => ['nl' => '', 'en' => '', 'de' => '']];
    $data['items'] = isset($data['items']) && is_array($data['items']) ? array_values($data['items']) : [];
    $data['cta'] = isset($data['cta']) && is_array($data['cta']) ? $data['cta'] : [];
    return $data;
}

function sponsorsSchrijf(string $pad, array $data): bool
{
    global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
    if (publicContentIsTenantPad($pad)) {
        $sleutel = publicContentSleutelVoorPad($pad);
        return $sleutel !== null && publicContentSchrijfTenant($sleutel, $data, true);
    }
    maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false || !is_dir(dirname($pad))) return false;
    try { $suffix = bin2hex(random_bytes(4)); } catch (Throwable $e) { $suffix = (string) mt_rand(100000, 999999); }
    $tmp = $pad . '.tmp.' . $suffix;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (!@rename($tmp, $pad)) { @unlink($tmp); return false; }
    return true;
}

function sponsorsLogoAfmetingen(string $naam): array
{
    global $sponsorMap;
    if ($naam === '') return ['width' => 0, 'height' => 0];
    $pad = $sponsorMap . '/' . basename($naam);
    if (is_link($pad)) return ['width' => 0, 'height' => 0];
    $info = @getimagesize($pad);
    return is_array($info) ? ['width' => (int) ($info[0] ?? 0), 'height' => (int) ($info[1] ?? 0)] : ['width' => 0, 'height' => 0];
}

function sponsorsVerwerkLogo(string $veld, int $slot, string $huidig): array
{
    global $sponsorMap;

    if (!isset($_FILES[$veld]) || ($_FILES[$veld]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'logo' => $huidig] + sponsorsLogoAfmetingen($huidig);
    }

    $bestand = $_FILES[$veld];
    if (($bestand['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_INI_SIZE || ($bestand['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'fout' => 'logo is te groot.'];
    }
    if (($bestand['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'fout' => 'uploaden van het logo is mislukt.'];
    }
    if ((int) ($bestand['size'] ?? 0) > 1024 * 1024) {
        return ['ok' => false, 'fout' => 'logo is groter dan 1 MB.'];
    }

    $tmp = (string) ($bestand['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) return ['ok' => false, 'fout' => 'tijdelijk uploadbestand is ongeldig.'];
    $info = @getimagesize($tmp);
    if (!is_array($info)) return ['ok' => false, 'fout' => 'bestand is geen geldige afbeelding.'];
    $breedte = (int) ($info[0] ?? 0);
    $hoogte = (int) ($info[1] ?? 0);
    if ($breedte < 1 || $hoogte < 1 || $breedte > 8000 || $hoogte > 8000 || ($breedte * $hoogte) > 20000000) {
        return ['ok' => false, 'fout' => 'logo heeft onveilige afbeeldingsafmetingen.'];
    }

    $mime = (string) ($info['mime'] ?? '');
    $extensies = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!isset($extensies[$mime])) return ['ok' => false, 'fout' => 'alleen PNG, JPG of WEBP is toegestaan.'];

    $bestandsnaam = 'sponsor-' . ($slot + 1) . '.' . $extensies[$mime];
    $doel = $sponsorMap . '/' . $bestandsnaam;
    if (is_link($doel)) return ['ok' => false, 'fout' => 'onveilig bestaand bestandsdoel geweigerd.'];

    foreach (['png', 'jpg', 'webp'] as $ext) {
        $oud = $sponsorMap . '/sponsor-' . ($slot + 1) . '.' . $ext;
        if ($oud === $doel) continue;
        if (is_link($oud)) { @unlink($oud); continue; }
        if (is_file($oud)) @unlink($oud);
    }

    // Upload eerst naar een unieke sibling-tempfile. Daarmee is activatie via
    // rename altijd een nieuwe inode-versie en blijft de O(1) ETag-validator
    // deterministisch, ook wanneer upload_tmp_dir op een ander filesystem staat.
    try { $suffix = bin2hex(random_bytes(5)); }
    catch (Throwable $e) { return ['ok' => false, 'fout' => 'veilige tijdelijke logonaam kon niet worden gemaakt.']; }
    $staging = $doel . '.tmp.' . $suffix;
    if (!@move_uploaded_file($tmp, $staging)) {
        return ['ok' => false, 'fout' => 'logo kon niet veilig worden gestaged.'];
    }
    try {
        publicAssetBeveiligBestand($staging);
        if (!@rename($staging, $doel)) {
            @unlink($staging);
            return ['ok' => false, 'fout' => 'logo kon niet atomair worden geactiveerd.'];
        }
        publicAssetBeveiligBestand($doel);
    } catch (Throwable $e) {
        @unlink($staging);
        return ['ok' => false, 'fout' => 'logo kon niet veilig worden opgeslagen.'];
    }

    return [
        'ok' => true,
        'logo' => $bestandsnaam,
        'width' => $breedte,
        'height' => $hoogte,
    ];
}

$data = sponsorsLees($sponsorBestand);
$melding = '';
$meldingType = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        $melding = 'Sessie verlopen. Ververs de pagina en probeer opnieuw.';
        $meldingType = 'fout';
    } else {
        $slot = dataSlotOpen();
        try {
            try {
                atomicFileTransactie([$sponsorBestand, $sponsorMap], function () use (&$data, &$melding, &$meldingType, $sponsorBestand): bool {
                    $bestaande = $data['items'];
                    $cta = [
                        'nl' => sponsorsKort($_POST['cta_nl'] ?? '', 200),
                        'en' => sponsorsKort($_POST['cta_en'] ?? '', 200),
                        'de' => sponsorsKort($_POST['cta_de'] ?? '', 200),
                    ];

                    $items = [];
                    $fout = null;
                    foreach ((array) ($_POST['sponsor'] ?? []) as $i => $rij) {
                        if (!is_array($rij)) continue;
                        $index = is_numeric($i) ? (int) $i : count($items);
                        $naam = sponsorsKort($rij['name'] ?? '', 60);
                        if ($naam === '') continue;

                        $url = trim(is_scalar($rij['url'] ?? '') ? (string) $rij['url'] : '');
                        if ($url !== '' && !preg_match('#^https?://#i', $url)) {
                            $fout = 'Website van "' . $naam . '" moet beginnen met http:// of https://.';
                            break;
                        }
                        $url = sponsorsKort($url, 200);

                        $huidigLogo = (string) ($bestaande[$index]['logo'] ?? '');
                        $logo = sponsorsVerwerkLogo('sponsor_logo_' . $index, $index, $huidigLogo);
                        if (!$logo['ok']) {
                            $fout = 'Logo van "' . $naam . '": ' . ($logo['fout'] ?? 'onbekende fout');
                            break;
                        }
                        if (($logo['logo'] ?? '') === '') {
                            $fout = 'Voeg een logo toe voor "' . $naam . '".';
                            break;
                        }

                        $items[] = [
                            'name' => $naam,
                            'url' => $url,
                            'logo' => (string) $logo['logo'],
                            'width' => (int) ($logo['width'] ?? 0),
                            'height' => (int) ($logo['height'] ?? 0),
                        ];
                    }

                    if ($fout !== null) {
                        $melding = $fout;
                        $meldingType = 'fout';
                        return false;
                    }

                    $nieuw = ['updated' => date('c'), 'items' => $items, 'cta' => $cta];
                    if (!sponsorsSchrijf($sponsorBestand, $nieuw)) {
                        $melding = 'Opslaan mislukt. De eerdere sponsorbestanden zijn teruggezet.';
                        $meldingType = 'fout';
                        return false;
                    }

                    $data = $nieuw;
                    $melding = 'Opgeslagen. De sponsoren op de website zijn bijgewerkt.';
                    $meldingType = 'ok';
                    schrijfLog($GLOBALS['logBestand'], $GLOBALS['huidigeGebruiker'], 'sponsors', count($items) . ' sponsor(s) opgeslagen via modulaire editor');
                    return true;
                });
            } catch (Throwable $e) {
                error_log('[platform] sponsortransactie mislukt: ' . $e->getMessage());
                $melding = 'Opslaan is veilig afgebroken; de vorige sponsorconfiguratie is behouden.';
                $meldingType = 'fout';
            }
        } finally {
            dataSlotDicht($slot);
        }
    }
}

$items = $data['items'];
$cta = $data['cta'];
if (!$items) $items = [['name' => '', 'url' => '', 'logo' => '', 'width' => 0, 'height' => 0]];
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sponsors beheren</title>
<link rel="stylesheet" href="csp205-sponsors-c06f2d388f62.css">
</head>
<body>
<div class="top"><div class="topin"><a href="../beheer.php">← Terug naar beheer</a><a href="../index.html" target="_blank" rel="noopener">Bekijk website ↗</a></div></div>
<main class="wrap">
<h1>Sponsors</h1>
<p>Modulaire sponsorbeheerder. Wijzig namen, websites, logo's en de meertalige sponsor-CTA.</p>
<?php if ($melding !== ''): ?><div class="melding <?= sponsorsEsc($meldingType) ?>"><?= sponsorsEsc($melding) ?></div><?php endif; ?>
<form method="post" enctype="multipart/form-data" id="sponsor-form">
<input type="hidden" name="csrf" value="<?= sponsorsEsc($csrfToken) ?>">
<section class="kaart"><h2>Sponsor worden-tekst</h2><div class="talen">
<?php foreach (['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $taal=>$label): ?><div class="veld"><label><?= $label ?></label><textarea name="cta_<?= $taal ?>" maxlength="200"><?= sponsorsEsc($cta[$taal] ?? '') ?></textarea></div><?php endforeach; ?>
</div></section>
<div id="sponsor-lijst">
<?php foreach ($items as $i => $sp): ?><section class="kaart sponsor-rij" data-index="<?= (int) $i ?>"><div class="rij-kop"><h2>Sponsor <?= (int) $i + 1 ?></h2><button class="btn gevaar verwijder" type="button">Verwijderen</button></div>
<?php if (!empty($sp['logo'])): ?><img class="logo" src="../images/sponsors/<?= sponsorsEsc(basename((string) $sp['logo'])) ?>" alt=""><?php endif; ?>
<div class="grid"><div class="veld"><label>Naam</label><input type="text" name="sponsor[<?= (int) $i ?>][name]" maxlength="60" value="<?= sponsorsEsc($sp['name'] ?? '') ?>"></div><div class="veld"><label>Website (optioneel)</label><input type="text" name="sponsor[<?= (int) $i ?>][url]" maxlength="200" value="<?= sponsorsEsc($sp['url'] ?? '') ?>" placeholder="https://..."></div></div>
<div class="veld"><label>Logo<?= !empty($sp['logo']) ? ' (leeg laten om huidige te behouden)' : '' ?></label><input type="file" name="sponsor_logo_<?= (int) $i ?>" accept="image/png,image/jpeg,image/webp"><div class="hint">PNG, JPG of WEBP, maximaal 1 MB.</div></div>
</section><?php endforeach; ?>
</div>
<button class="btn secundair" id="voeg-toe" type="button">+ Sponsor toevoegen</button>
<div class="acties"><button class="btn primair" type="submit">Opslaan</button><a class="btn secundair" href="../beheer.php">Annuleren</a></div>
</form>
</main>
<template id="sponsor-template"><section class="kaart sponsor-rij"><div class="rij-kop"><h2>Sponsor</h2><button class="btn gevaar verwijder" type="button">Verwijderen</button></div><div class="grid"><div class="veld"><label>Naam</label><input class="naam" type="text" maxlength="60"></div><div class="veld"><label>Website (optioneel)</label><input class="url" type="text" maxlength="200" placeholder="https://..."></div></div><div class="veld"><label>Logo</label><input class="logo-input" type="file" accept="image/png,image/jpeg,image/webp"><div class="hint">PNG, JPG of WEBP, maximaal 1 MB.</div></div></section></template>
<script>
(function(){const lijst=document.getElementById('sponsor-lijst'),tpl=document.getElementById('sponsor-template');function bind(){lijst.querySelectorAll('.verwijder').forEach(b=>b.onclick=()=>{if(lijst.querySelectorAll('.sponsor-rij').length>1)b.closest('.sponsor-rij').remove();else{const r=b.closest('.sponsor-rij');r.querySelectorAll('input[type=text]').forEach(i=>i.value='');}});}function volgendeIndex(){let max=-1;lijst.querySelectorAll('.sponsor-rij').forEach(r=>{const i=parseInt(r.dataset.index||'-1',10);if(i>max)max=i;});return max+1;}document.getElementById('voeg-toe').onclick=()=>{const i=volgendeIndex(),node=tpl.content.firstElementChild.cloneNode(true);node.dataset.index=i;node.querySelector('h2').textContent='Sponsor '+(i+1);node.querySelector('.naam').name='sponsor['+i+'][name]';node.querySelector('.url').name='sponsor['+i+'][url]';node.querySelector('.logo-input').name='sponsor_logo_'+i;lijst.appendChild(node);bind();};bind();})();
</script>
</body>
</html>