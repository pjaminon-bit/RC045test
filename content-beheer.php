<?php
// ============================================================
// Generieke beheerlaag voor configureerbare contentpagina's
// ============================================================
// Als helper ingesloten: levert veld-/groep-/opslagfuncties.
// Rechtstreeks geopend: toont één generieke editor voor alle pagina's uit
// pagina-definities.php. Daarmee hoeft een nieuwe contentpagina geen eigen
// formuliercode meer te krijgen.
// ============================================================

require_once __DIR__ . '/content-pagina.php';

function contentBeheerVelden(string $sleutel): array
{
    $def = contentPaginaDefinitie($sleutel);
    if (!$def) return [];

    $velden = [];
    foreach (($def['velden'] ?? []) as $veld => $info) {
        if (!is_array($info)) continue;
        $velden[(string) $veld] = [
            (string) ($info['label'] ?? $veld),
            (string) ($info['type'] ?? 'tekst'),
        ];
    }

    foreach (($def['artikelen'] ?? []) as $nummer => $artikel) {
        if (!is_array($artikel)) continue;
        $titelVeld = trim((string) ($artikel['titel'] ?? ''));
        $inhoudVeld = trim((string) ($artikel['inhoud'] ?? ''));
        if ($titelVeld !== '') $velden[$titelVeld] = ['Artikel ' . $nummer . ': titel', 'tekst'];
        if ($inhoudVeld !== '') $velden[$inhoudVeld] = ['Artikel ' . $nummer . ': inhoud', 'blok'];
    }

    return $velden;
}

function contentBeheerGroepen(string $sleutel): array
{
    $def = contentPaginaDefinitie($sleutel);
    if (!$def) return [];

    if (($def['type'] ?? '') !== 'artikelen') {
        return ['Inhoud' => array_keys(contentBeheerVelden($sleutel))];
    }

    $groepen = [];
    $intro = array_keys((array) ($def['velden'] ?? []));
    if ($intro) $groepen['Intro'] = $intro;

    foreach (($def['artikelen'] ?? []) as $nummer => $artikel) {
        if (!is_array($artikel)) continue;
        $velden = [];
        foreach (['titel', 'inhoud'] as $soort) {
            $veld = trim((string) ($artikel[$soort] ?? ''));
            if ($veld !== '') $velden[] = $veld;
        }
        if ($velden) $groepen['Artikel ' . $nummer] = $velden;
    }

    return $groepen;
}

function contentBeheerPostPrefix(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    $prefix = trim((string) ($def['beheer_prefix'] ?? ''));
    return $prefix !== '' ? $prefix : 'content_' . preg_replace('/[^a-z0-9_\-]/i', '_', $sleutel);
}

function contentBeheerMaxLengte(string $type): int
{
    return $type === 'blok' ? 3000 : 200;
}

function contentBeheerKort($waarde, int $max): string
{
    $tekst = trim(is_scalar($waarde) ? (string) $waarde : '');
    if (function_exists('mb_substr')) return mb_substr($tekst, 0, $max, 'UTF-8');
    return substr($tekst, 0, $max);
}

function contentBeheerLeesPostWaarde(array $post, string $prefix, string $veld, string $taal): string
{
    $waarde = $post[$prefix][$veld][$taal] ?? '';
    return is_scalar($waarde) ? trim((string) $waarde) : '';
}

function contentBeheerOpslaan(string $sleutel, array $post, callable $kort, callable $schrijfJson): array
{
    $velden = contentBeheerVelden($sleutel);
    $pad = contentPaginaDataPad($sleutel);
    if (!$velden || $pad === null) {
        return ['ok' => false, 'melding' => 'Deze contentpagina is niet correct geconfigureerd.'];
    }

    $prefix = contentBeheerPostPrefix($sleutel);
    $nieuw = [];
    foreach ($velden as $veld => $info) {
        $type = (string) ($info[1] ?? 'tekst');
        $max = contentBeheerMaxLengte($type);
        $nieuw[$veld] = [];
        foreach (['nl', 'en', 'de'] as $taal) {
            $nieuw[$veld][$taal] = $kort(contentBeheerLeesPostWaarde($post, $prefix, $veld, $taal), $max);
        }
    }

    if (!$schrijfJson($pad, $nieuw)) {
        return ['ok' => false, 'melding' => 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.'];
    }

    return ['ok' => true, 'melding' => 'Opgeslagen. De contentpagina is bijgewerkt.', 'data' => $nieuw];
}

function contentBeheerHuidigeWaarde(array $data, string $veld, string $taal): string
{
    $waarde = $data[$veld][$taal] ?? '';
    return is_scalar($waarde) ? (string) $waarde : '';
}

function contentBeheerSchrijfJson(string $pad, array $data): bool
{
    global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;
    if (function_exists('maakDataBackup')) {
        maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
    }
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    return $json !== false && file_put_contents($pad, $json, LOCK_EX) !== false;
}

if (strtolower(basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) !== 'content-beheer.php') {
    return;
}

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/data-slot.php';

if (!$ingelogd) {
    header('Location: beheer.php');
    exit;
}

$paginaSleutel = isset($_GET['pagina']) && is_string($_GET['pagina']) ? trim($_GET['pagina']) : '';
$def = contentPaginaDefinitie($paginaSleutel);
if (!$def) {
    http_response_code(404);
    echo 'Onbekende contentpagina.';
    exit;
}

$beheerTab = contentPaginaBeheerTab($paginaSleutel);
$rechten = authRechten([$beheerTab => (string) ($def['label'] ?? $beheerTab)], []);
if (!$isMaster && !in_array($beheerTab, $rechten['toegestaneTabs'] ?? [], true)) {
    http_response_code(403);
    echo 'Geen toegang tot deze contentpagina.';
    exit;
}

$meldingEditor = '';
$meldingTypeEditor = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        $meldingEditor = 'Sessie verlopen. Ververs de pagina en probeer opnieuw.';
        $meldingTypeEditor = 'fout';
    } else {
        $slot = dataSlotOpen();
        try {
            $resultaat = contentBeheerOpslaan(
                $paginaSleutel,
                $_POST,
                static fn($waarde, $max) => contentBeheerKort($waarde, $max),
                static fn($pad, $data) => contentBeheerSchrijfJson($pad, $data)
            );
        } finally {
            dataSlotDicht($slot);
        }

        $meldingEditor = (string) ($resultaat['melding'] ?? '');
        $meldingTypeEditor = !empty($resultaat['ok']) ? 'ok' : 'fout';
        if (!empty($resultaat['ok']) && function_exists('schrijfLog')) {
            schrijfLog($logBestand, $huidigeGebruiker, 'contentpagina', $paginaSleutel . ' bijgewerkt');
        }
    }
}

$data = contentPaginaLees($paginaSleutel);
$velden = contentBeheerVelden($paginaSleutel);
$groepen = contentBeheerGroepen($paginaSleutel);
$prefix = contentBeheerPostPrefix($paginaSleutel);
$label = (string) ($def['label'] ?? $paginaSleutel);
$slug = (string) ($def['slug'] ?? $paginaSleutel);

function contentBeheerEsc($waarde): string { return htmlspecialchars((string) $waarde, ENT_QUOTES, 'UTF-8'); }
?><!DOCTYPE html>
<html lang="nl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= contentBeheerEsc($label) ?> beheren</title>
<style>
body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#fff;border-bottom:1px solid #ddd8c0;padding:16px 24px;position:sticky;top:0;z-index:10}.topin{max-width:1180px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:16px}.top a{color:#2d6260;text-decoration:none;font-weight:700}.wrap{max-width:1180px;margin:32px auto;padding:0 24px 60px}.head{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.head h1{margin:0 0 6px}.head p{margin:0;color:#66705e}.groep{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:24px;margin:0 0 20px}.groep h2{margin:0 0 20px;font-size:19px}.veld{margin-bottom:22px}.veld:last-child{margin-bottom:0}.veld>label{display:block;font-weight:700;margin-bottom:9px}.talen{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}.taal label{display:block;font-size:12px;font-weight:700;color:#6a7560;margin-bottom:5px}.taal input,.taal textarea{box-sizing:border-box;width:100%;border:1px solid #cfcab7;border-radius:8px;padding:10px 11px;font:inherit;background:#fff}.taal textarea{min-height:130px;resize:vertical;line-height:1.5}.acties{position:sticky;bottom:0;background:rgba(246,242,232,.94);backdrop-filter:blur(8px);padding:14px 0;display:flex;gap:12px}.btn{border:0;border-radius:9px;padding:11px 18px;font:inherit;font-weight:700;cursor:pointer;text-decoration:none;display:inline-block}.btn-primary{background:#3a7a77;color:#fff}.btn-secondary{background:#fff;color:#26351d;border:1px solid #cfcab7}.melding{padding:12px 14px;border-radius:9px;margin-bottom:20px}.melding.ok{background:#e8f5ee;color:#205b38}.melding.fout{background:#fdeceb;color:#8b2e27}@media(max-width:800px){.talen{grid-template-columns:1fr}.head{flex-direction:column}}
</style>
</head>
<body>
<div class="top"><div class="topin"><a href="beheer.php#<?= contentBeheerEsc($beheerTab) ?>">← Terug naar beheer</a><a href="<?= contentBeheerEsc($slug) ?>.html" target="_blank" rel="noopener">Bekijk pagina ↗</a></div></div>
<main class="wrap">
<div class="head"><div><h1><?= contentBeheerEsc($label) ?></h1><p>Generieke contenteditor · type <?= contentBeheerEsc(contentPaginaType($paginaSleutel)) ?></p></div></div>
<?php if ($meldingEditor !== ''): ?><div class="melding <?= contentBeheerEsc($meldingTypeEditor) ?>"><?= contentBeheerEsc($meldingEditor) ?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="csrf" value="<?= contentBeheerEsc($csrfToken) ?>">
<?php foreach ($groepen as $groepLabel => $groepVelden): ?>
<section class="groep"><h2><?= contentBeheerEsc($groepLabel) ?></h2>
<?php foreach ($groepVelden as $veld): if (!isset($velden[$veld])) continue; [$veldLabel,$type] = $velden[$veld]; ?>
<div class="veld"><label><?= contentBeheerEsc($veldLabel) ?></label><div class="talen">
<?php foreach (['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $taal=>$taalLabel): $waarde = contentBeheerHuidigeWaarde($data,$veld,$taal); ?>
<div class="taal"><label><?= contentBeheerEsc($taalLabel) ?></label><?php if ($type === 'blok'): ?><textarea name="<?= contentBeheerEsc($prefix) ?>[<?= contentBeheerEsc($veld) ?>][<?= $taal ?>]" maxlength="<?= contentBeheerMaxLengte($type) ?>"><?= contentBeheerEsc($waarde) ?></textarea><?php else: ?><input type="text" name="<?= contentBeheerEsc($prefix) ?>[<?= contentBeheerEsc($veld) ?>][<?= $taal ?>]" maxlength="<?= contentBeheerMaxLengte($type) ?>" value="<?= contentBeheerEsc($waarde) ?>"><?php endif; ?></div>
<?php endforeach; ?></div></div>
<?php endforeach; ?>
</section>
<?php endforeach; ?>
<div class="acties"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn btn-secondary" href="beheer.php#<?= contentBeheerEsc($beheerTab) ?>">Annuleren</a></div>
</form>
</main>
</body>
</html>
