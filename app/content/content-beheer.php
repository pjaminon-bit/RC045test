<?php
// ============================================================
// Generieke beheerlaag voor configureerbare contentpagina's
// ============================================================
require_once __DIR__ . '/content-pagina.php';
require_once dirname(__DIR__) . '/beheer/editor-hulp.php';

function contentBeheerVelden(string $sleutel): array
{
    $def = contentPaginaDefinitie($sleutel);
    if (!$def) return [];
    $velden = [];
    foreach (($def['velden'] ?? []) as $veld => $info) {
        if (!is_array($info)) continue;
        $velden[(string) $veld] = [(string) ($info['label'] ?? $veld), (string) ($info['type'] ?? 'tekst')];
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
    if (!empty($def['groepen']) && is_array($def['groepen'])) return $def['groepen'];
    if (($def['type'] ?? '') !== 'artikelen') return ['Inhoud' => array_keys(contentBeheerVelden($sleutel))];
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

function contentBeheerMaxLengte(string $type, string $sleutel = ''): int
{
    if ($sleutel !== '') {
        $def = contentPaginaDefinitie($sleutel);
        $max = $def['max_lengte'][$type] ?? null;
        if (is_numeric($max) && (int) $max > 0) return (int) $max;
    }
    return $type === 'blok' ? 3000 : 200;
}

function contentBeheerKort($waarde, int $max): string
{
    $tekst = trim(is_scalar($waarde) ? (string) $waarde : '');
    return function_exists('mb_substr') ? mb_substr($tekst, 0, $max, 'UTF-8') : substr($tekst, 0, $max);
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
    if (!$velden || $pad === null) return ['ok' => false, 'melding' => 'Deze contentpagina is niet correct geconfigureerd.'];
    $prefix = contentBeheerPostPrefix($sleutel);
    $nieuw = [];
    foreach ($velden as $veld => $info) {
        $type = (string) ($info[1] ?? 'tekst');
        $max = contentBeheerMaxLengte($type, $sleutel);
        $nieuw[$veld] = [];
        foreach (['nl', 'en', 'de'] as $taal) $nieuw[$veld][$taal] = $kort(contentBeheerLeesPostWaarde($post, $prefix, $veld, $taal), $max);
    }
    if (!$schrijfJson($pad, $nieuw)) return ['ok' => false, 'melding' => 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.'];
    return ['ok' => true, 'melding' => $sleutel === 'homepage' ? 'Opgeslagen. De homepage gebruikt meteen deze tekst.' : 'Opgeslagen. De contentpagina is bijgewerkt.', 'data' => $nieuw];
}

function contentBeheerHuidigeWaarde(array $data, string $veld, string $taal): string
{
    $waarde = $data[$veld][$taal] ?? '';
    return is_scalar($waarde) ? (string) $waarde : '';
}

function contentBeheerSchrijfJson(string $pad, array $data): bool
{
    return beheerEditorSchrijfJson($pad, $data);
}

if (strtolower(basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''))) !== 'content-beheer.php') return;

require_once dirname(__DIR__, 2) . '/auth.php';
require_once dirname(__DIR__) . '/data-slot.php';
require_once dirname(__DIR__) . '/core/site.php';
if (!$ingelogd) { header('Location: beheer.php'); exit; }

$paginaSleutel = isset($_GET['pagina']) && is_string($_GET['pagina']) ? trim($_GET['pagina']) : '';
$def = contentPaginaDefinitie($paginaSleutel);
if (!$def) { http_response_code(404); echo 'Onbekende contentpagina.'; exit; }
$beheerTab = contentPaginaBeheerTab($paginaSleutel);
$beheerModule = siteModuleVoorBeheerTab($beheerTab);
if ($beheerModule !== null && !siteModuleActief($beheerModule)) {
    http_response_code(404);
    echo 'Deze module is voor deze vereniging niet ingeschakeld.';
    exit;
}
$rechten = authRechten([$beheerTab => (string) ($def['label'] ?? $beheerTab)], []);
if (!$isMaster && !in_array($beheerTab, $rechten['toegestaneTabs'] ?? [], true)) { http_response_code(403); echo 'Geen toegang tot deze contentpagina.'; exit; }

$meldingEditor = '';
$meldingTypeEditor = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) { $meldingEditor = 'Sessie verlopen. Ververs de pagina en probeer opnieuw.'; $meldingTypeEditor = 'fout'; }
    else {
        $slot = dataSlotOpen();
        try {
            $resultaat = contentBeheerOpslaan($paginaSleutel, $_POST, static fn($waarde,$max)=>contentBeheerKort($waarde,$max), static fn($pad,$data)=>contentBeheerSchrijfJson($pad,$data));
        } finally { dataSlotDicht($slot); }
        $meldingEditor = (string) ($resultaat['melding'] ?? '');
        $meldingTypeEditor = !empty($resultaat['ok']) ? 'ok' : 'fout';
        if (!empty($resultaat['ok']) && function_exists('schrijfLog')) schrijfLog($logBestand, $huidigeGebruiker, $paginaSleutel === 'homepage' ? 'homepage' : 'contentpagina', $paginaSleutel . ' bijgewerkt');
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
<html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title><?= contentBeheerEsc($label) ?> beheren</title>
<link rel="stylesheet" href="csp205-content-beheer-abc228d76762.css"></head><body>
<div class="top"><div class="topin"><a href="beheer.php#<?= contentBeheerEsc($beheerTab) ?>">← Terug naar beheer</a><a href="<?= contentBeheerEsc($slug) ?>.html" target="_blank" rel="noopener">Bekijk pagina ↗</a></div></div>
<main class="wrap"><div class="head"><div><h1><?= contentBeheerEsc($label) ?></h1><p>Generieke contenteditor · type <?= contentBeheerEsc(contentPaginaType($paginaSleutel)) ?></p></div></div>
<?php if ($meldingEditor !== ''): ?><div class="melding <?= contentBeheerEsc($meldingTypeEditor) ?>"><?= contentBeheerEsc($meldingEditor) ?></div><?php endif; ?>
<?php if ($paginaSleutel === 'homepage'): ?><div class="uitleg">Nederlands is de basis. Engels en Duits zijn optioneel; laat je die leeg, dan kan de website terugvallen op de Nederlandse tekst. De echte openingstijden, agenda-items en prijzen beheer je in hun eigen modules.</div><?php endif; ?>
<form method="post"><input type="hidden" name="csrf" value="<?= contentBeheerEsc($csrfToken) ?>">
<?php foreach ($groepen as $groepLabel=>$groepVelden): ?><section class="groep"><h2><?= contentBeheerEsc($groepLabel) ?></h2>
<?php foreach ($groepVelden as $veld): if (!isset($velden[$veld])) continue; [$veldLabel,$type]=$velden[$veld]; ?>
<div class="veld"><label><?= contentBeheerEsc($veldLabel) ?></label><div class="talen">
<?php foreach (['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $taal=>$taalLabel): $waarde=contentBeheerHuidigeWaarde($data,$veld,$taal); ?>
<div class="taal"><label><?= contentBeheerEsc($taalLabel) ?><?= $taal === 'nl' ? '' : ' (optioneel)' ?></label><?php if ($type === 'blok'): ?><textarea name="<?= contentBeheerEsc($prefix) ?>[<?= contentBeheerEsc($veld) ?>][<?= $taal ?>]" maxlength="<?= contentBeheerMaxLengte($type,$paginaSleutel) ?>"><?= contentBeheerEsc($waarde) ?></textarea><?php else: ?><input type="text" name="<?= contentBeheerEsc($prefix) ?>[<?= contentBeheerEsc($veld) ?>][<?= $taal ?>]" maxlength="<?= contentBeheerMaxLengte($type,$paginaSleutel) ?>" value="<?= contentBeheerEsc($waarde) ?>"><?php endif; ?></div>
<?php endforeach; ?></div></div><?php endforeach; ?></section><?php endforeach; ?>
<div class="acties"><button class="btn btn-primary" type="submit">Opslaan</button><a class="btn btn-secondary" href="beheer.php#<?= contentBeheerEsc($beheerTab) ?>">Annuleren</a></div></form></main></body></html>