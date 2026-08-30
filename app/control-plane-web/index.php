<?php
require_once dirname(__DIR__) . '/control-plane/control-plane-runtime.php';
require_once dirname(__DIR__) . '/control-plane/control-plane-observability.php';
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$operator = cp51Operator();
$csrf = cp51Csrf();
$melding = '';
$fout = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        cp51CsrfControle((string)($_POST['csrf'] ?? ''));
        $actie = trim((string)($_POST['action'] ?? ''));
        if ($actie === 'provision') {
            $id = cp57ProvisionRequest($_POST);
        } else {
            $tenant = trim((string)($_POST['tenant'] ?? ''));
            $id = cp51Request($tenant, $actie, $_POST);
        }
        header('Location: /?queued=' . rawurlencode($id), true, 303);
        exit;
    } catch (Throwable $e) {
        $fout = $e->getMessage();
    }
}
if (isset($_GET['queued']) && preg_match('/^[0-9a-f]{32}$/D', (string)$_GET['queued'])) {
    $queued = (string)$_GET['queued'];
    $resultaat = cp51RecentResult($queued, $operator);
    if (is_array($resultaat)) {
        $samenvatting = trim((string)$resultaat['message']);
        if ($resultaat['result'] === 'ok') {
            $melding = 'Aanvraag ' . substr($queued, 0, 8) . ' is uitgevoerd.' . ($samenvatting !== '' ? ' ' . $samenvatting : '');
        } else {
            $fout = 'Aanvraag ' . substr($queued, 0, 8) . ' is mislukt.' . ($samenvatting !== '' ? ' ' . $samenvatting : '');
        }
    } else {
        $melding = 'Aanvraag ' . substr($queued, 0, 8) . ' staat in de uitvoerqueue of wordt nog verwerkt. Vernieuw de pagina voor de definitieve uitkomst.';
    }
}

$snapshot = cp51Snapshot();
$tenants = $snapshot['tenants'];
$platform = cpAdminPlatformStatus($snapshot);
$system = $platform['system'];
$recenteActies = cpAdminRecenteResultaten($operator, 8);
$mutatiesBeschikbaar = (bool)$platform['ok'];

function h51(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tijd51(?string $utc): string {
    if (!$utc) return '—';
    try { return (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i:s'); }
    catch (Throwable $e) { return '—'; }
}
function label51(string $status): string {
    return match($status) {
        'setup_required' => 'Installatie afronden',
        'unmanaged' => 'Nog niet geadopteerd',
        'active' => 'Actief',
        'suspended' => 'Uitgeschakeld',
        'pending_delete' => 'Verwijdering aangevraagd',
        'invalid' => 'Controle nodig',
        default => $status !== '' ? $status : 'Onbekend',
    };
}
function action51(string $actie): string {
    return match($actie) {
        'adopt-active' => 'Onder beheer brengen',
        'suspend' => 'Uitschakelen',
        'activate' => 'Heractiveren',
        'recover' => 'Transition herstellen',
        'export' => 'Volledige export maken',
        'delete' => 'Verwijdering aanvragen',
        'cancel-delete' => 'Verwijdering annuleren',
        'purge' => 'Definitief verwijderen',
        'provision' => 'Vereniging aanmaken',
        default => $actie,
    };
}
function pct51(mixed $value): string {
    return is_float($value) || is_int($value) ? number_format((float)$value, 1, ',', '.') . '%' : 'onbekend';
}
function load51(mixed $value): string {
    return is_float($value) || is_int($value) ? number_format((float)$value, 2, ',', '.') : 'onbekend';
}

$moduleLabels = [
    'website' => 'Website',
    'ledenadministratie' => 'Ledenadministratie',
    'werkgroepen' => 'Werkgroepen',
    'evenementen' => 'Evenementen',
    'vergaderingen' => 'Vergaderingen',
    'taken' => 'Taken',
    'operationele_taken' => 'Operationele taken',
    'fotoboek' => 'Fotoboek',
    'sponsors' => 'Sponsors',
    'media' => 'Media',
    'aanmelden' => 'Aanmelden',
];
$counts = $platform['counts'];
$attention = $counts['unhealthy'] + $counts['invalid'] + $counts['transitions'];
$statusClass = !$platform['ok'] ? 'critical' : ($platform['warnings'] ? 'warning-state' : 'healthy-state');
$statusLabel = !$platform['ok'] ? 'Actie vereist' : ($platform['warnings'] ? 'Waarschuwingen' : 'Operationeel');
$processingLabel = $platform['queue']['processing'] === null ? 'root-only' : (string)$platform['queue']['processing'];
$release = is_string($system['release_sha'] ?? null) ? substr((string)$system['release_sha'], 0, 12) : 'onbekend';
$diskUsed = cpAdminBytesLabel($system['disk']['used_bytes'] ?? null);
$diskTotal = cpAdminBytesLabel($system['disk']['total_bytes'] ?? null);
$memoryUsed = cpAdminBytesLabel($system['memory']['used_bytes'] ?? null);
$memoryTotal = cpAdminBytesLabel($system['memory']['total_bytes'] ?? null);
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verenigingsplatform · Platformbeheer</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17211b;background:#f5f7f5}*{box-sizing:border-box}body{margin:0}.wrap{max-width:1240px;margin:0 auto;padding:32px 20px 56px}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.top-actions{display:flex;gap:10px;align-items:center;flex-wrap:wrap;justify-content:flex-end}.eyebrow{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:#607067;font-weight:700}h1{margin:.25rem 0 .35rem;font-size:clamp(1.65rem,3vw,2.35rem)}h2{margin:0;font-size:1.15rem}.sub{color:#66736b;margin:0}.operator{background:#fff;border:1px solid #dce3de;border-radius:12px;padding:10px 13px;font-size:.9rem}.notice{padding:12px 14px;border-radius:10px;margin:0 0 18px}.ok{background:#eaf7ee;border:1px solid #b9dfc2}.err{background:#fff0f0;border:1px solid #efc1c1}.health-panel{border-radius:16px;padding:18px;margin-bottom:18px;border:1px solid}.health-panel.healthy-state{background:#edf8f0;border-color:#badfc4}.health-panel.warning-state{background:#fff8e7;border-color:#ead395}.health-panel.critical{background:#fff0f0;border-color:#e8b8b8}.health-top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.health-title{display:flex;gap:10px;align-items:center;font-weight:800}.health-dot{width:11px;height:11px;border-radius:50%;background:#2c8b4b;box-shadow:0 0 0 4px rgba(44,139,75,.12)}.warning-state .health-dot{background:#a27808;box-shadow:0 0 0 4px rgba(162,120,8,.12)}.critical .health-dot{background:#b33b3b;box-shadow:0 0 0 4px rgba(179,59,59,.12)}.health-meta{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}.health-chip{background:rgba(255,255,255,.65);border:1px solid rgba(76,95,82,.16);border-radius:999px;padding:6px 9px;font-size:.8rem}.health-list{margin:12px 0 0;padding-left:20px;font-size:.88rem}.health-list li+li{margin-top:5px}.summary{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;margin-bottom:20px}.stat{background:#fff;border:1px solid #dce3de;border-radius:14px;padding:16px}.stat span{color:#68746d;font-size:.82rem}.stat strong{display:block;font-size:1.45rem;margin-top:3px}.stat.attention strong{color:#9a3b28}.card{background:#fff;border:1px solid #dce3de;border-radius:16px;padding:18px;box-shadow:0 2px 12px rgba(22,40,29,.04)}.system-card{margin-bottom:20px}.system-head{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}.system-head p{margin:.35rem 0 0;color:#66736b;font-size:.88rem}.system-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:15px}.metric{background:#f7f9f7;border:1px solid #e3e8e4;border-radius:12px;padding:12px;min-width:0}.metric span{display:block;color:#6d7971;font-size:.75rem;margin-bottom:5px}.metric strong{display:block;font-size:1rem;word-break:break-word}.metric small{display:block;color:#78827c;margin-top:4px;font-size:.72rem}.toolbar{display:flex;gap:10px;align-items:center;margin:0 0 16px;flex-wrap:wrap}.toolbar input,.toolbar select{border:1px solid #cbd4ce;background:#fff;border-radius:9px;padding:10px 11px;font:inherit}.toolbar input{min-width:260px;flex:1}.toolbar select{min-width:180px}.toolbar .result-count{color:#6e7a72;font-size:.84rem;margin-left:auto}.grid{display:grid;gap:16px}.card.hidden{display:none}.head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.tenant{font-size:1.18rem;font-weight:750}.host{color:#66736b;font-size:.9rem;margin-top:3px}.host a{color:inherit}.badge{font-size:.8rem;font-weight:700;padding:6px 9px;border-radius:999px;background:#edf1ee;white-space:nowrap}.badge.active{background:#e8f6eb;color:#1f6a37}.badge.suspended{background:#fff4d8;color:#735b16}.badge.pending_delete,.badge.invalid{background:#ffe6e6;color:#8a2b2b}.badge.setup_required,.badge.unmanaged{background:#eef0ff;color:#4d4b91}.meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:16px 0}.meta div{background:#f7f9f7;border-radius:10px;padding:10px;min-width:0}.meta span{display:block;color:#738078;font-size:.76rem;margin-bottom:4px}.meta strong{font-size:.88rem;word-break:break-word}.meta .bad strong{color:#9a322a}.actions{display:flex;flex-wrap:wrap;gap:8px;align-items:center}.actions form{display:inline-flex;gap:7px;align-items:center;flex-wrap:wrap}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid #bec9c1;background:#fff;color:#1d2b22;border-radius:9px;padding:9px 11px;font-weight:650;cursor:pointer;font:inherit}.btn:hover{background:#f3f6f4}.btn:disabled{cursor:not-allowed;opacity:.48}.primary{background:#1f5f3a;color:#fff;border-color:#1f5f3a}.primary:hover{background:#174d2f}.danger{border-color:#d9a4a4;color:#8b2020}.warning{border-color:#d9c27e;color:#6c5511}input[type=text]{border:1px solid #cbd4ce;border-radius:8px;padding:9px 10px;min-width:170px;font:inherit}.empty{background:#fff;border:1px dashed #cbd4ce;border-radius:14px;padding:30px;text-align:center;color:#66736b}.foot{margin-top:22px;color:#77817b;font-size:.8rem}.create-card{margin-bottom:20px;scroll-margin-top:18px}.create-card:target{outline:3px solid rgba(31,95,58,.14)}.create-head{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;margin-bottom:16px}.create-head p{margin:.35rem 0 0;color:#66736b;max-width:760px}.form-grid{display:grid;grid-template-columns:1.1fr 1fr 1.2fr;gap:12px}.field label,.modules-title{display:block;font-size:.82rem;font-weight:700;margin-bottom:6px}.field input{width:100%;min-width:0}.hint{display:block;color:#748078;font-size:.76rem;margin-top:5px}.modules{margin-top:16px}.module-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.module-option{display:flex;gap:8px;align-items:center;background:#f7f9f7;border:1px solid #e2e7e3;border-radius:9px;padding:9px 10px;font-size:.85rem}.module-option input{margin:0}.create-foot{display:flex;justify-content:space-between;gap:16px;align-items:center;margin-top:16px;padding-top:14px;border-top:1px solid #edf0ed}.security-note{font-size:.8rem;color:#68766e;max-width:760px}.setup-note{margin-top:12px;padding:10px 12px;border-radius:9px;background:#f7f8ff;color:#4d4b72;font-size:.84rem}.invalid-note,.health-note{margin-top:12px;padding:10px 12px;border-radius:9px;background:#fff0f0;color:#7c2929;font-size:.84rem}.history{margin-top:20px}.history-table{width:100%;border-collapse:collapse;margin-top:12px}.history-table th,.history-table td{text-align:left;padding:10px 8px;border-bottom:1px solid #edf0ed;font-size:.84rem;vertical-align:top}.history-table th{color:#6d7871;font-size:.76rem;text-transform:uppercase;letter-spacing:.04em}.result-ok{color:#21693a;font-weight:700}.result-failed{color:#982f2f;font-weight:700}.quicklink{margin-left:auto}.no-results{display:none;background:#fff;border:1px dashed #cbd4ce;border-radius:14px;padding:22px;text-align:center;color:#66736b}@media(max-width:1080px){.system-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:960px){.summary{grid-template-columns:repeat(3,minmax(0,1fr))}.module-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.form-grid{grid-template-columns:1fr 1fr}.field:last-child{grid-column:1/-1}}@media(max-width:760px){.top{display:block}.top-actions{justify-content:flex-start;margin-top:12px}.summary,.meta{grid-template-columns:1fr 1fr}.system-grid{grid-template-columns:1fr 1fr}.module-grid{grid-template-columns:1fr 1fr}.create-foot,.health-top,.system-head{align-items:flex-start;flex-direction:column}.history{overflow-x:auto}.toolbar .result-count{width:100%;margin-left:0}}@media(max-width:520px){.summary,.meta,.form-grid,.module-grid,.system-grid{grid-template-columns:1fr}.field:last-child{grid-column:auto}.card{padding:14px}.toolbar input,.toolbar select{width:100%;min-width:0}}
</style>
</head>
<body><main class="wrap">
<div class="top"><div><div class="eyebrow">Verenigingsplatform</div><h1>Platformbeheer</h1><p class="sub">Operationeel overzicht, gebruik, capaciteit, tenantstatus en veilige lifecycle-acties.</p></div><div class="top-actions"><a class="btn primary" href="#nieuwe-vereniging">+ Nieuwe vereniging</a><div class="operator">Ingelogd als <strong><?=h51($operator)?></strong></div></div></div>
<?php if($melding!==''):?><div class="notice ok"><?=h51($melding)?></div><?php endif;?>
<?php if($fout!==''):?><div class="notice err"><?=h51($fout)?></div><?php endif;?>

<section class="health-panel <?=h51($statusClass)?>" aria-label="Platformstatus">
<div class="health-top"><div><div class="health-title"><span class="health-dot" aria-hidden="true"></span>Platformstatus: <?=h51($statusLabel)?></div><div class="health-meta"><span class="health-chip">Snapshot <?=h51(cpAdminLeeftijdLabel($platform['snapshot_age_seconds']))?> oud</span><span class="health-chip">Queue: <?=$platform['queue']['pending']?> wachtend</span><span class="health-chip">Processing: <?=h51($processingLabel)?></span><span class="health-chip">Gezond: <?=$counts['healthy']?> / <?=$counts['active']?> actief</span></div></div><div><strong><?=$mutatiesBeschikbaar?'Mutaties beschikbaar':'Mutaties geblokkeerd'?></strong></div></div>
<?php if($platform['critical']||$platform['warnings']):?><ul class="health-list">
<?php foreach($platform['critical'] as $item):?><li><strong><?=h51($item)?></strong></li><?php endforeach;?>
<?php foreach($platform['warnings'] as $item):?><li><?=h51($item)?></li><?php endforeach;?>
</ul><?php endif;?>
</section>

<div class="summary">
<div class="stat"><span>Verenigingen</span><strong><?=$counts['total']?></strong></div>
<div class="stat"><span>Actief & gezond</span><strong><?=$counts['healthy']?></strong></div>
<div class="stat attention"><span>Aandacht nodig</span><strong><?=$attention?></strong></div>
<div class="stat"><span>Installatie afronden</span><strong><?=$counts['setup']?></strong></div>
<div class="stat"><span>Uit / pending delete</span><strong><?=$counts['suspended']+$counts['pending_delete']?></strong></div>
</div>

<section class="card system-card" aria-label="Systeem en capaciteit"><div class="system-head"><div><h2>Systeem & capaciteit</h2><p>Read-only platforminformatie; hiervoor krijgt de beheerwebapp geen extra systeemrechten.</p></div><span class="badge">live</span></div><div class="system-grid">
<div class="metric"><span>Platformopslag</span><strong><?=h51(pct51($system['disk']['used_percent']??null))?></strong><small><?=h51($diskUsed)?> van <?=h51($diskTotal)?></small></div>
<div class="metric"><span>Geheugen</span><strong><?=h51(pct51($system['memory']['used_percent']??null))?></strong><small><?=h51($memoryUsed)?> van <?=h51($memoryTotal)?></small></div>
<div class="metric"><span>Systeemload</span><strong><?=h51(load51($system['load']['one']??null))?></strong><small>1 min · <?=h51((string)($system['cpu_count']??1))?> CPU-threads</small></div>
<div class="metric"><span>Uptime</span><strong><?=h51(cpAdminUptimeLabel($system['uptime_seconds']??null))?></strong><small>Linux host</small></div>
<div class="metric"><span>Release</span><strong><?=h51($release)?></strong><small>actieve immutable commit</small></div>
<div class="metric"><span>Runtime</span><strong>PHP <?=h51((string)($system['php_version']??'onbekend'))?></strong><small>control-plane FPM</small></div>
</div></section>

<section class="card create-card" id="nieuwe-vereniging">
<div class="create-head"><div><h2>Nieuwe vereniging</h2><p>Maak de tenant veilig aan met een eigen technische identiteit, productiehost, PDO-opslagprofiel en modulekeuze.</p></div><span class="badge setup_required">Basisprovisioning</span></div>
<?php if(!$mutatiesBeschikbaar):?><div class="health-note">Nieuwe provisioning is tijdelijk uitgeschakeld omdat de control-plane niet alle vereiste schrijfpaden gezond verklaart. Herstel eerst de platformstatus.</div><?php endif;?>
<form method="post" autocomplete="off">
<input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="provision">
<div class="form-grid">
<div class="field"><label for="name">Verenigingsnaam</label><input id="name" type="text" name="name" maxlength="120" required placeholder="Voorbeeldvereniging"><span class="hint">De publieke naam; later aanpasbaar via het tenantbeheer.</span></div>
<div class="field"><label for="tenant_key">Technische tenant-key</label><input id="tenant_key" type="text" name="tenant_key" minlength="3" maxlength="63" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" placeholder="voorbeeldvereniging"><span class="hint">Permanent, lowercase, geen spaties of dubbele koppeltekens.</span></div>
<div class="field"><label for="host">Domeinnaam</label><input id="host" type="text" name="host" maxlength="253" required inputmode="url" placeholder="vereniging.example.nl"><span class="hint">Alleen de hostnaam; HTTPS wordt als productiecontract gebruikt.</span></div>
</div>
<div class="modules"><span class="modules-title">Modules</span><div class="module-grid">
<?php foreach($moduleLabels as $module=>$label):?>
<label class="module-option"><input type="checkbox" name="modules[]" value="<?=h51($module)?>" <?=$module==='website'?'checked disabled':'checked'?>> <?=h51($label)?><?=$module==='website'?' (verplicht)':''?></label>
<?php endforeach;?>
<input type="hidden" name="modules[]" value="website">
</div></div>
<div class="create-foot"><div class="security-note">De webinterface voert geen rootcommando's uit. De aanvraag gaat naar de root-executor, die alle waarden opnieuw valideert. Een beheerderswachtwoord wordt bewust niet via deze queue verwerkt.</div><button class="btn primary" type="submit" <?=$mutatiesBeschikbaar?'':'disabled'?>>Vereniging aanmaken</button></div>
</form>
</section>

<div class="toolbar" aria-label="Verenigingen filteren"><input id="tenant-search" type="search" placeholder="Zoek op tenant-key of domein…" autocomplete="off"><select id="tenant-filter"><option value="all">Alle statussen</option><option value="active">Actief</option><option value="suspended">Uitgeschakeld</option><option value="setup_required">Installatie afronden</option><option value="unmanaged">Niet geadopteerd</option><option value="pending_delete">Pending delete</option><option value="invalid">Controle nodig</option></select><span class="result-count" id="tenant-count"><?=count($tenants)?> zichtbaar</span></div>
<div class="no-results" id="no-results">Geen verenigingen voldoen aan dit filter.</div>
<div class="grid" id="tenant-grid">
<?php if(!$tenants):?><div class="empty">Nog geen verenigingen gevonden. Gebruik <strong>Nieuwe vereniging</strong> om de eerste tenant aan te maken.</div><?php endif;?>
<?php foreach($tenants as $t): $key=(string)$t['tenant_key'];$status=(string)$t['status'];$acties=cp51ToegestaneActies($t);$host=(string)($t['canonical_host']??'');$healthy=($t['healthy']??false)===true; ?>
<section class="card tenant-card" data-tenant="<?=h51(strtolower($key.' '.$host))?>" data-status="<?=h51($status)?>"><div class="head"><div><div class="tenant"><?=h51($key)?></div><div class="host"><?php if($host!==''):?><a href="https://<?=h51($host)?>/" target="_blank" rel="noopener noreferrer"><?=h51($host)?></a><?php else:?>Geen geldige host<?php endif;?></div></div><span class="badge <?=h51($status)?>"><?=h51(label51($status))?></span></div>
<div class="meta"><div class="<?=$status==='active'&&!$healthy?'bad':''?>"><span>Health</span><strong><?=$healthy?'Gezond':($status==='setup_required'?'Nog niet actief':'Niet gezond / n.v.t.')?></strong></div><div><span>Laatste status</span><strong><?=h51(tijd51($t['updated_at_utc']??null))?></strong></div><div><span>Laatste export</span><strong><?=h51(isset($t['last_export']['created_at_utc'])?tijd51((string)$t['last_export']['created_at_utc']):'—')?></strong></div><div><span>Purge vanaf</span><strong><?=h51(tijd51($t['purge_not_before_utc']??null))?></strong></div></div>
<?php if($status==='active'&&!$healthy):?><div class="health-note">Deze actieve tenant heeft geen actuele gezonde monitoringstatus. Controleer dit voordat je niet-noodzakelijke lifecyclemutaties uitvoert.</div><?php endif;?>
<?php if($status==='setup_required'):?><div class="setup-note">Basisprovisioning is voltooid. Activeer de eerste beheerder via de veilige server-side bootstrap en rond daarna runtime, database, vhost, DNS/TLS, monitoring en lifecycle-activatie af.</div><?php endif;?>
<?php if($status==='invalid'):?><div class="invalid-note">Deze tenantmap is onvolledig of bevat ongeldige provisioning-/lifecyclemetadata. Controleer dit server-side voordat je verdergaat.</div><?php endif;?>
<div class="actions">
<?php if($acties): foreach($acties as $actie):?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="tenant" value="<?=h51($key)?>"><input type="hidden" name="action" value="<?=h51($actie)?>">
<?php if(in_array($actie,['delete','purge'],true)):?><input type="text" name="confirm_tenant" placeholder="Typ <?=h51($key)?>" required aria-label="Tenant-key bevestigen"><?php endif;?>
<?php if($actie==='purge'):?><input type="text" name="confirm_purge" placeholder="VERWIJDER-DEFINITIEF" required aria-label="Definitieve purge bevestigen"><?php endif;?>
<button class="btn <?=$actie==='purge'?'danger':($actie==='delete'||$actie==='suspend'?'warning':'')?>" type="submit" <?=$mutatiesBeschikbaar?'':'disabled'?>><?=h51(action51($actie))?></button></form>
<?php endforeach; endif;?>
<?php if($host!==''):?><a class="btn quicklink" href="https://<?=h51($host)?>/" target="_blank" rel="noopener noreferrer">Open website ↗</a><?php endif;?>
</div></section>
<?php endforeach;?>
</div>

<section class="card history"><div class="head"><div><h2>Recente beheeracties</h2><div class="host">Alleen resultaten van de huidige operator worden getoond.</div></div><span class="badge"><?=count($recenteActies)?> recent</span></div>
<?php if(!$recenteActies):?><div class="empty" style="margin-top:12px">Nog geen recente afgeronde beheeracties voor deze operator.</div><?php else:?><table class="history-table"><thead><tr><th>Tijd</th><th>Vereniging</th><th>Actie</th><th>Resultaat</th><th>Melding</th></tr></thead><tbody>
<?php foreach($recenteActies as $r):?><tr><td><?=h51(tijd51($r['completed_at_utc']))?></td><td><?=h51($r['tenant_key'])?></td><td><?=h51(action51($r['action']))?></td><td class="<?=$r['result']==='ok'?'result-ok':'result-failed'?>"><?=$r['result']==='ok'?'Geslaagd':'Mislukt'?></td><td><?=h51($r['message'])?></td></tr><?php endforeach;?>
</tbody></table><?php endif;?>
</section>

<p class="foot">Snapshot bijgewerkt: <?=h51(tijd51($snapshot['generated_at_utc']??null))?> · Tijdzone Europe/Amsterdam · DNS-providerrecords worden nooit automatisch verwijderd.</p>
</main>
<script>
(() => {
  const search = document.getElementById('tenant-search');
  const filter = document.getElementById('tenant-filter');
  const cards = [...document.querySelectorAll('.tenant-card')];
  const count = document.getElementById('tenant-count');
  const empty = document.getElementById('no-results');
  const apply = () => {
    const q = (search.value || '').trim().toLowerCase();
    const status = filter.value;
    let visible = 0;
    cards.forEach(card => {
      const matchesText = q === '' || (card.dataset.tenant || '').includes(q);
      const matchesStatus = status === 'all' || card.dataset.status === status;
      const show = matchesText && matchesStatus;
      card.classList.toggle('hidden', !show);
      if (show) visible++;
    });
    count.textContent = `${visible} zichtbaar`;
    empty.style.display = cards.length > 0 && visible === 0 ? 'block' : 'none';
  };
  search?.addEventListener('input', apply);
  filter?.addEventListener('change', apply);
})();
</script>
</body></html>