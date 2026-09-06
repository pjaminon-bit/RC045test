<?php
require_once dirname(__DIR__) . '/control-plane/control-plane-runtime.php';
require_once dirname(__DIR__) . '/control-plane/control-plane-observability.php';
require_once dirname(__DIR__) . '/control-plane/control-plane-operations.php';
require_once dirname(__DIR__) . '/control-plane/control-plane-admin-suite.php';
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$operator = cp51Operator();
$csrf = cp51Csrf();
$secties = ['overview','tenants','onboarding','operations','operators','audit'];
$section = in_array((string)($_GET['section'] ?? ''), $secties, true) ? (string)$_GET['section'] : 'overview';

function h51(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tijd51(?string $utc): string {
    if (!$utc) return '—';
    try { return (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i:s'); }
    catch (Throwable $e) { return '—'; }
}
function leeftijd51(?int $seconds): string { return $seconds === null ? 'onbekend' : cpAdminLeeftijdLabel($seconds); }
function label51(string $status): string {
    return match($status) {
        'setup_required'=>'Installatie afronden','unmanaged'=>'Nog niet geadopteerd','active'=>'Actief',
        'suspended'=>'Uitgeschakeld','pending_delete'=>'Verwijdering aangevraagd','invalid'=>'Controle nodig',
        default=>$status !== '' ? $status : 'Onbekend',
    };
}
function action51(string $actie): string {
    return match($actie) {
        'adopt-active'=>'Onder beheer brengen','suspend'=>'Uitschakelen','activate'=>'Heractiveren',
        'recover'=>'Transition herstellen','export'=>'Volledige export maken','delete'=>'Verwijdering aanvragen',
        'cancel-delete'=>'Verwijdering annuleren','purge'=>'Definitief verwijderen','provision'=>'Vereniging aanmaken',
        'admin-refresh'=>'Platformdata vernieuwen','operator-role-set'=>'Operatorrol wijzigen','schedule-create'=>'Actie plannen',
        'schedule-cancel'=>'Planning annuleren','diagnose'=>'Veilige diagnose','tls-renew'=>'TLS vernieuwen',
        default=>$actie,
    };
}
function pct51(mixed $value): string { return is_float($value)||is_int($value) ? number_format((float)$value,1,',','.') . '%' : 'onbekend'; }
function load51(mixed $value): string { return is_float($value)||is_int($value) ? number_format((float)$value,2,',','.') : 'onbekend'; }
function tls51(array $tls): string {
    return match((string)($tls['status'] ?? '')) {
        'valid'=>'Geldig','expiring'=>'Verloopt binnenkort','expired'=>'Verlopen','missing'=>'Ontbreekt',
        'invalid'=>'Ongeldig','not_configured'=>'Nog niet ingericht',default=>'Onbekend',
    };
}
function schedule51(string $status): string {
    return match($status) {'scheduled'=>'Gepland','queued'=>'In queue','completed'=>'Uitgevoerd','failed'=>'Mislukt','cancelled'=>'Geannuleerd',default=>$status};
}
function tenantAandacht51(array $tenant): array {
    $items = cpOpsTenantAttention($tenant);
    $tls = is_array($tenant['tls'] ?? null) ? $tenant['tls'] : [];
    $days = $tls['days_remaining'] ?? null;
    if (($tenant['status'] ?? '') === 'active' && in_array(($tls['status'] ?? ''), ['missing','invalid','expired'], true)) $items[] = 'TLS-certificaat is niet bruikbaar.';
    elseif (is_int($days) && $days <= 30) $items[] = 'TLS-certificaat verloopt binnen ' . $days . ' dag(en).';
    $backup = is_array($tenant['backup'] ?? null) ? $tenant['backup'] : [];
    $age = $backup['age_days'] ?? null;
    if (is_int($age) && $age >= 30) $items[] = 'Laatste geverifieerde export is ' . $age . ' dagen oud.';
    return array_values(array_unique($items));
}
function volgendeOnboarding51(array $onboarding): ?array {
    foreach ($onboarding['steps'] ?? [] as $step) if (is_array($step) && ($step['done'] ?? false) !== true) return $step;
    return null;
}

if (($_GET['export'] ?? '') === 'audit-csv') {
    $rows = cpSuiteAuditRows(500);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="platform-audit-' . gmdate('Ymd') . '.csv"');
    $out = fopen('php://output', 'wb');
    if (!is_resource($out)) cp51Fail('audit CSV-uitvoer kon niet worden geopend');
    fputcsv($out, ['tijd_utc','operator','tenant','actie','resultaat','melding']);
    foreach ($rows as $row) fputcsv($out, [$row['timestamp_utc'],$row['operator'],$row['tenant_key'],$row['action'],$row['result'],$row['message']]);
    fclose($out);
    exit;
}

$melding = '';
$fout = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        cp51CsrfControle((string)($_POST['csrf'] ?? ''));
        $actie = trim((string)($_POST['action'] ?? ''));
        $return = in_array((string)($_POST['return_section'] ?? ''), $secties, true) ? (string)$_POST['return_section'] : 'overview';
        if ($actie === 'provision') {
            cpSuiteRequire('mutate');
            $id = cp57ProvisionRequest($_POST);
        } elseif ($actie === 'admin-refresh') {
            $id = cpSuiteRefreshRequest();
        } elseif ($actie === 'operator-role-set') {
            $id = cpSuiteRoleRequest($_POST);
        } elseif ($actie === 'schedule-create') {
            $id = cpSuiteScheduleRequest($_POST);
        } elseif ($actie === 'schedule-cancel') {
            $id = cpSuiteScheduleCancelRequest($_POST);
        } elseif ($actie === 'diagnose') {
            $id = cpSuiteDiagnoseRequest(trim((string)($_POST['tenant'] ?? '')));
        } elseif ($actie === 'tls-renew') {
            $id = cpSuiteTlsRenewRequest(trim((string)($_POST['tenant'] ?? '')));
        } else {
            cpSuiteRequire('mutate');
            $tenant = trim((string)($_POST['tenant'] ?? ''));
            $id = cp51Request($tenant, $actie, $_POST);
        }
        header('Location: /?section=' . rawurlencode($return) . '&queued=' . rawurlencode($id), true, 303);
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
        if ($resultaat['result'] === 'ok') $melding = 'Aanvraag ' . substr($queued,0,8) . ' is uitgevoerd.' . ($samenvatting!==''?' '.$samenvatting:'');
        else $fout = 'Aanvraag ' . substr($queued,0,8) . ' is mislukt.' . ($samenvatting!==''?' '.$samenvatting:'');
    } else $melding = 'Aanvraag ' . substr($queued,0,8) . ' staat in de uitvoerqueue of wordt nog verwerkt. Vernieuw de pagina voor de definitieve uitkomst.';
}

$snapshot = cp51Snapshot();
$tenants = is_array($snapshot['tenants'] ?? null) ? $snapshot['tenants'] : [];
$platform = cpAdminPlatformStatus($snapshot);
$system = $platform['system'];
$recenteActies = cpAdminRecenteResultaten($operator, 12);
$openstaandeActies = cpOpsPendingRequests($operator, 12);
$rolesState = cpSuiteRolesState($operator);
$role = (string)$rolesState['role'];
$schedules = cpSuiteSchedules(100);
$notifications = cpSuiteNotifications($snapshot, $platform, $schedules);
$auditRows = cpSuiteAuditRows(500);
$canMutate = (bool)$platform['ok'] && (bool)$rolesState['valid'] && cpSuiteCan('mutate');
$canSchedule = (bool)$platform['ok'] && (bool)$rolesState['valid'] && cpSuiteCan('schedule');
$canDiagnose = (bool)$platform['ok'] && (bool)$rolesState['valid'] && cpSuiteCan('diagnose');
$canTls = (bool)$platform['ok'] && (bool)$rolesState['valid'] && cpSuiteCan('tls');
$canRoles = (bool)$platform['ok'] && (bool)$rolesState['valid'] && cpSuiteCan('roles');
$mutatiesBeschikbaar = $canMutate;

$moduleLabels = [
    'website'=>'Website','ledenadministratie'=>'Ledenadministratie','werkgroepen'=>'Werkgroepen','evenementen'=>'Evenementen',
    'vergaderingen'=>'Vergaderingen','taken'=>'Taken','operationele_taken'=>'Operationele taken','fotoboek'=>'Fotoboek',
    'sponsors'=>'Sponsors','media'=>'Media','aanmelden'=>'Aanmelden',
];
$counts = $platform['counts'];
$attention = 0;$onboardingOpen=0;$storageTotal=0;$storageKnown=0;
foreach($tenants as$t){
    if(!is_array($t))continue;
    if(tenantAandacht51($t)!==[])$attention++;
    $ob=cpSuiteOnboarding($t);if($ob['percent']<100)$onboardingOpen++;
    $bytes=$t['storage']['bytes']??null;if(is_int($bytes)){$storageTotal+=$bytes;$storageKnown++;}
}
$criticalCount=count(array_filter($notifications,static fn($n)=>($n['severity']??'')==='critical'));
$warningCount=count(array_filter($notifications,static fn($n)=>($n['severity']??'')==='warning'));
$statusClass = !$platform['ok'] ? 'critical' : ($criticalCount>0||$warningCount>0 ? 'warning-state' : 'healthy-state');
$statusLabel = !$platform['ok'] ? 'Actie vereist' : ($criticalCount>0||$warningCount>0 ? 'Aandachtspunten' : 'Operationeel');
$processingLabel = $platform['queue']['processing'] === null ? 'root-only' : (string)$platform['queue']['processing'];
$release = is_string($system['release_sha'] ?? null) ? substr((string)$system['release_sha'],0,12) : 'onbekend';
$diskUsed = cpAdminBytesLabel($system['disk']['used_bytes'] ?? null);$diskTotal=cpAdminBytesLabel($system['disk']['total_bytes'] ?? null);
$memoryUsed=cpAdminBytesLabel($system['memory']['used_bytes'] ?? null);$memoryTotal=cpAdminBytesLabel($system['memory']['total_bytes'] ?? null);
$tenantsRoot=(string)cp51Config()['tenants_root'];

$auditQ=mb_strtolower(trim((string)($_GET['audit_q']??'')));$auditResult=(string)($_GET['audit_result']??'all');
$filteredAudit=array_values(array_filter($auditRows,static function(array$r)use($auditQ,$auditResult):bool{
    $hay=mb_strtolower(implode(' ',[(string)$r['operator'],(string)$r['tenant_key'],(string)$r['action'],(string)$r['message']]));
    return($auditQ===''||str_contains($hay,$auditQ))&&($auditResult==='all'||$r['result']===$auditResult);
}));
?><!doctype html>
<html lang="nl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verenigingsplatform · Platformbeheer</title>
<link rel="stylesheet" href="csp205-index-d89da7f1f8bf.css">
</head><body><div class="shell">
<aside class="sidebar"><div class="brand"><strong>Verenigingsplatform</strong><span>Platformbeheer</span></div><nav class="nav" aria-label="Platformbeheer navigatie">
<a class="<?=$section==='overview'?'active':''?>" href="/?section=overview">Overzicht<?php if($criticalCount+$warningCount>0):?><span class="count"><?=$criticalCount+$warningCount?></span><?php endif;?></a>
<a class="<?=$section==='tenants'?'active':''?>" href="/?section=tenants">Verenigingen<span class="count"><?=count($tenants)?></span></a>
<a class="<?=$section==='onboarding'?'active':''?>" href="/?section=onboarding">Onboarding<?php if($onboardingOpen):?><span class="count"><?=$onboardingOpen?></span><?php endif;?></a>
<a class="<?=$section==='operations'?'active':''?>" href="/?section=operations">Operaties<?php if(count($openstaandeActies)):?><span class="count"><?=count($openstaandeActies)?></span><?php endif;?></a>
<a class="<?=$section==='operators'?'active':''?>" href="/?section=operators">Operators</a>
<a class="<?=$section==='audit'?'active':''?>" href="/?section=audit">Auditlog</a>
</nav><div class="side-foot"><strong><?=h51($operator)?></strong><?=h51(control58RoleLabel($role))?> · release <?=h51($release)?></div></aside>
<main class="content"><div class="wrap">
<div class="top"><div><div class="eyebrow">Verenigingsplatform</div><h1>Platformbeheer</h1><p class="sub">Van signalering naar actie: verenigingen, onboarding, beveiliging, capaciteit en audit in één console.</p></div><div class="top-actions"><span class="role-chip"><?=h51(control58RoleLabel($role))?></span><form method="post"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="admin-refresh"><input type="hidden" name="return_section" value="<?=h51($section)?>"><button class="btn secondary" type="submit">↻ Platformdata vernieuwen</button></form><?php if($canMutate):?><a class="btn primary" href="/?section=tenants#nieuwe-vereniging">+ Nieuwe vereniging</a><?php endif;?></div></div>
<?php if($melding!==''):?><div class="notice ok"><?=h51($melding)?></div><?php endif;?>
<?php if($fout!==''):?><div class="notice err"><?=h51($fout)?></div><?php endif;?>
<?php if(!$rolesState['initialized']):?><div class="notice info"><strong>Operatorrollen worden bij de eerstvolgende platformrefresh geïnitialiseerd.</strong> Bestaande geauthenticeerde operators behouden tijdens deze migratie hun huidige beheerrechten.</div><?php elseif(!$rolesState['valid']):?><div class="notice err"><strong>Operatorrollenbestand is ongeldig.</strong> De console valt veilig terug naar alleen-lezen totdat de server-side rollenstore is hersteld.</div><?php endif;?>
<?php if(!cpSuiteCan('mutate')):?><div class="notice info">Je bent ingelogd met een alleen-lezen platformrol. Mutaties, planning, TLS-renew en rolwijzigingen zijn verborgen en worden ook door de root-executor geweigerd.</div><?php endif;?>

<?php if($section==='overview'):?>
<section class="health-panel <?=h51($statusClass)?>" aria-label="Platformstatus"><div class="health-top"><div><div class="health-title">Platformstatus: <?=h51($statusLabel)?></div><div class="health-meta"><span class="health-chip">Snapshot <?=h51(cpAdminLeeftijdLabel($platform['snapshot_age_seconds']))?> oud</span><span class="health-chip">Queue: <?=$platform['queue']['pending']?> wachtend</span><span class="health-chip">Eigen openstaand: <?=count($openstaandeActies)?></span><span class="health-chip">Processing: <?=h51($processingLabel)?></span><span class="health-chip">Gezond: <?=$counts['healthy']?> / <?=$counts['active']?> actief</span></div></div><strong><?=$mutatiesBeschikbaar?'Mutaties beschikbaar':'Mutaties geblokkeerd'?></strong></div></section>
<div class="summary"><div class="stat"><span>Verenigingen</span><strong><?=$counts['total']?></strong></div><div class="stat"><span>Actief & gezond</span><strong><?=$counts['healthy']?></strong></div><div class="stat attention"><span>Aandacht nodig</span><strong><?=$attention?></strong></div><div class="stat"><span>Onboarding open</span><strong><?=$onboardingOpen?></strong></div><div class="stat"><span>Geplande acties</span><strong><?=count(array_filter($schedules,static fn($s)=>in_array($s['status'],['scheduled','queued'],true)))?></strong></div><div class="stat"><span>Tenantopslag bekend</span><strong><?=h51(cpAdminBytesLabel($storageKnown>0?$storageTotal:null))?></strong></div></div>
<div class="section-grid"><div class="stack">
<section class="card"><div class="head"><div><h2>Meldingen & aandachtspunten</h2><div class="host">Kritieke signalen eerst; geen losse monitoringpagina's meer nodig voor de eerste triage.</div></div><div class="chips"><span class="chip critical"><?=$criticalCount?> kritiek</span><span class="chip warning"><?=$warningCount?> waarschuwing</span></div></div>
<div class="notification-list"><?php if(!$notifications):?><div class="empty">Geen actuele platformmeldingen.</div><?php else:?><?php foreach(array_slice($notifications,0,12)as$n):?><div class="notification <?=h51((string)$n['severity'])?>"><strong><?=h51((string)$n['title'])?></strong><span><?=h51((string)$n['message'])?></span><?php if(is_string($n['tenant']??null)):?> <a href="/?section=tenants#tenant-<?=h51((string)$n['tenant'])?>">Open vereniging</a><?php endif;?></div><?php endforeach;?><?php endif;?></div></section>
<section class="card"><div class="head"><div><h2>Recente beheeracties</h2><div class="host">Alleen resultaten van de huidige operator worden getoond.</div></div><a class="btn secondary" href="/?section=audit">Volledig auditlog</a></div><?php if(!$recenteActies):?><div class="empty" style="margin-top:12px">Nog geen recente afgeronde beheeracties voor deze operator.</div><?php else:?><div class="table-wrap"><table class="history-table"><thead><tr><th>Tijd</th><th>Vereniging</th><th>Actie</th><th>Resultaat</th></tr></thead><tbody><?php foreach($recenteActies as$r):?><tr><td><?=h51(tijd51($r['completed_at_utc']))?></td><td><?=h51($r['tenant_key'])?></td><td><?=h51(action51($r['action']))?></td><td class="<?=$r['result']==='ok'?'result-ok':'result-failed'?>"><?=$r['result']==='ok'?'Geslaagd':'Mislukt'?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
</div><div class="stack">
<section class="card system-card" aria-label="Systeem en capaciteit"><div class="system-head"><div><h2>Systeem & capaciteit</h2><p class="host">Read-only hostinformatie; de beheerwebapp krijgt hiervoor geen extra systeemrechten.</p></div><span class="badge">live</span></div><div class="system-grid"><div class="metric"><span>Platformopslag</span><strong><?=h51(pct51($system['disk']['used_percent']??null))?></strong><small><?=h51($diskUsed)?> van <?=h51($diskTotal)?></small></div><div class="metric"><span>Geheugen</span><strong><?=h51(pct51($system['memory']['used_percent']??null))?></strong><small><?=h51($memoryUsed)?> van <?=h51($memoryTotal)?></small></div><div class="metric"><span>Systeemload</span><strong><?=h51(load51($system['load']['one']??null))?></strong><small>1 min · <?=h51((string)($system['cpu_count']??1))?> CPU</small></div><div class="metric"><span>Uptime</span><strong><?=h51(cpAdminUptimeLabel($system['uptime_seconds']??null))?></strong><small>Linux host</small></div><div class="metric"><span>Release</span><strong><?=h51($release)?></strong><small>immutable commit</small></div><div class="metric"><span>Runtime</span><strong>PHP <?=h51((string)($system['php_version']??'onbekend'))?></strong><small>control-plane FPM</small></div></div></section>
<section class="card"><div class="head"><div><h2>Onboarding</h2><div class="host">Verenigingen die nog niet volledig operationeel zijn.</div></div><a class="btn secondary" href="/?section=onboarding">Open wizard</a></div><?php $shown=0;foreach($tenants as$t):if(!is_array($t))continue;$ob=cpSuiteOnboarding($t);if($ob['percent']>=100)continue;$shown++;?><div class="mini" style="margin-top:9px"><strong><?=h51((string)($t['name']??$t['tenant_key']))?></strong><div class="progress"><span style="width:<?=$ob['percent']?>%"></span></div><small><?=$ob['done']?> / <?=$ob['total']?> stappen · <?=$ob['percent']?>%</small></div><?php endforeach;?><?php if($shown===0):?><div class="empty" style="margin-top:10px">Geen open onboarding.</div><?php endif;?></section>
</div></div>

<?php elseif($section==='tenants'):?>
<div class="section-title"><h2>Verenigingen</h2><p>Compact overzicht; detailinformatie en lifecycle-acties staan pas open wanneer je ze nodig hebt.</p></div>
<?php if($canMutate):?><section class="card create-card" id="nieuwe-vereniging"><div class="create-head"><div><h2>Nieuwe vereniging</h2><p class="host">Basisprovisioning met eigen tenantidentiteit, PDO-opslagprofiel en modulekeuze.</p></div><span class="badge setup_required">Basisprovisioning</span></div><form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="provision"><input type="hidden" name="return_section" value="tenants"><div class="form-grid"><div class="field"><label for="name">Verenigingsnaam</label><input id="name" type="text" name="name" maxlength="120" required placeholder="Voorbeeldvereniging"></div><div class="field"><label for="tenant_key">Technische tenant-key</label><input id="tenant_key" type="text" name="tenant_key" minlength="3" maxlength="63" required pattern="[a-z0-9](?:[a-z0-9-]*[a-z0-9])?" placeholder="voorbeeldvereniging"><span class="hint">Permanent, lowercase, geen spaties.</span></div><div class="field"><label for="host">Domeinnaam</label><input id="host" type="text" name="host" maxlength="253" required placeholder="vereniging.example.nl"><span class="hint">Alleen hostnaam; HTTPS is het productiecontract.</span></div></div><div class="modules"><span class="modules-title">Modules</span><div class="module-grid"><?php foreach($moduleLabels as$module=>$label):?><label class="module-option"><input type="checkbox" name="modules[]" value="<?=h51($module)?>" <?=$module==='website'?'checked disabled':'checked'?>> <?=h51($label)?><?=$module==='website'?' (verplicht)':''?></label><?php endforeach;?><input type="hidden" name="modules[]" value="website"></div></div><div class="create-foot"><div class="security-note">Geen beheerderswachtwoord in deze queue. De eerste tenantbeheerder wordt in de onboarding via de veilige server-side bootstrap geactiveerd.</div><button class="btn primary" type="submit" <?=$platform['ok']?'':'disabled'?>>Vereniging aanmaken</button></div></form></section><?php endif;?>
<div class="toolbar" aria-label="Verenigingen filteren"><input id="tenant-search" type="search" placeholder="Zoek op naam, tenant-key of domein…" autocomplete="off"><select id="tenant-filter"><option value="all">Alle statussen</option><option value="attention">Aandacht nodig</option><option value="active">Actief</option><option value="suspended">Uitgeschakeld</option><option value="setup_required">Installatie afronden</option><option value="unmanaged">Niet geadopteerd</option><option value="pending_delete">Pending delete</option><option value="invalid">Controle nodig</option></select><span class="result-count" id="tenant-count"><?=count($tenants)?> zichtbaar</span></div><div class="no-results" id="no-results" hidden>Geen verenigingen voldoen aan dit filter.</div>
<div class="grid" id="tenant-grid"><?php if(!$tenants):?><div class="empty">Nog geen verenigingen gevonden.</div><?php endif;?><?php foreach($tenants as$t):if(!is_array($t))continue;$key=(string)$t['tenant_key'];$status=(string)$t['status'];$acties=cp51ToegestaneActies($t);$host=(string)($t['canonical_host']??'');$healthy=($t['healthy']??false)===true;$name=(string)($t['name']??'');$name=$name!==''?$name:$key;$redenen=tenantAandacht51($t);$heeftAandacht=$redenen!==[];$tls=is_array($t['tls']??null)?$t['tls']:[];$backup=is_array($t['backup']??null)?$t['backup']:[];$storage=is_array($t['storage']??null)?$t['storage']:[];$ob=cpSuiteOnboarding($t);?>
<section class="card tenant-card" id="tenant-<?=h51($key)?>" data-tenant="<?=h51(mb_strtolower($name.' '.$key.' '.$host))?>" data-status="<?=h51($status)?>" data-attention="<?=$heeftAandacht?'1':'0'?>"><div class="head"><div><div class="tenant"><?=h51($name)?></div><div class="technical"><?=h51($key)?><?php if($host!==''):?> · <?=h51($host)?><?php endif;?></div></div><div class="chips"><span class="badge <?=h51($status)?>"><?=h51(label51($status))?></span><span class="chip <?=$healthy?'good':($status==='active'?'critical':'info')?>"><?=$healthy?'Health gezond':($status==='active'?'Health probleem':'Health n.v.t.')?></span><span class="chip <?=($tls['status']??'')==='valid'?'good':(in_array(($tls['status']??''),['expiring'],true)?'warning':'info')?>">TLS <?=h51(tls51($tls))?></span></div></div>
<div class="meta"><div class="<?=$status==='active'&&!$healthy?'bad':''?>"><span>Laatste status</span><strong><?=h51(tijd51($t['updated_at_utc']??null))?></strong><small><?=h51(leeftijd51(cpOpsStatusLeeftijd($t['updated_at_utc']??null)))?> oud</small></div><div><span>Veilige export</span><strong><?=($backup['available']??false)?'Beschikbaar':'Niet beschikbaar'?></strong><small><?=is_int($backup['age_days']??null)?(int)$backup['age_days'].' dagen oud':'—'?></small></div><div><span>TLS geldig tot</span><strong><?=h51(tijd51($tls['valid_to_utc']??null))?></strong><small><?=is_int($tls['days_remaining']??null)?(int)$tls['days_remaining'].' dagen resterend':h51(tls51($tls))?></small></div><div><span>Tenantopslag</span><strong><?=h51(cpAdminBytesLabel(is_int($storage['bytes']??null)?$storage['bytes']:null))?></strong><small><?=is_int($storage['files']??null)?(int)$storage['files'].' bestanden':'onbekend'?><?=($storage['truncated']??false)?' +':''?></small></div><div><span>Onboarding</span><strong><?=$ob['percent']?>%</strong><small><?=$ob['done']?> / <?=$ob['total']?> stappen</small></div></div>
<?php if($heeftAandacht):?><div class="warning-note"><strong>Aandachtspunten</strong><ul class="attention-list"><?php foreach($redenen as$reden):?><li><?=h51($reden)?></li><?php endforeach;?></ul></div><?php endif;?>
<details class="details"><summary>Details & acties</summary><div class="detail-body"><div class="meta"><div><span>Transition</span><strong><?=($t['transition']??null)!==null?'Openstaand':'Geen'?></strong></div><div><span>Purge vanaf</span><strong><?=h51(tijd51($t['purge_not_before_utc']??null))?></strong></div><div><span>Modules</span><strong><?=count($t['modules']??[])?> actief</strong></div><div><span>Storage scan</span><strong><?=($storage['truncated']??false)?'Begrensd':'Volledig'?></strong></div><div><span>Technische key</span><strong><?=h51($key)?></strong></div></div><?php if(is_array($t['modules']??null)&&$t['modules']):?><div class="chips"><?php foreach($t['modules']as$m):?><span class="chip info"><?=h51($moduleLabels[$m]??$m)?></span><?php endforeach;?></div><?php endif;?>
<div class="action-group"><h3>Veilige hulpmiddelen</h3><div class="actions"><button class="btn secondary copy-btn" type="button" data-copy="<?=h51($key)?>">Kopieer key</button><?php if($host!==''):?><button class="btn secondary copy-btn" type="button" data-copy="<?=h51($host)?>">Kopieer domein</button><a class="btn" href="https://<?=h51($host)?>/beheer/" target="_blank" rel="noopener noreferrer">Tenantbeheer ↗</a><a class="btn" href="https://<?=h51($host)?>/" target="_blank" rel="noopener noreferrer">Open website ↗</a><?php endif;?><?php if($canDiagnose):?><form method="post"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="diagnose"><input type="hidden" name="tenant" value="<?=h51($key)?>"><input type="hidden" name="return_section" value="tenants"><button class="btn" type="submit">Veilige diagnose</button></form><?php endif;?><?php if($canTls&&is_int($tls['days_remaining']??null)&&$tls['days_remaining']<=35):?><form method="post" data-confirm-action="TLS-certificaat voor <?=h51($key)?> nu via de normale Certbot-renewroute controleren/vernieuwen?"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="tls-renew"><input type="hidden" name="tenant" value="<?=h51($key)?>"><input type="hidden" name="return_section" value="tenants"><button class="btn warning" type="submit">TLS vernieuwen</button></form><?php endif;?></div></div>
<?php if($canSchedule&&in_array($status,['active','suspended','pending_delete'],true)):?><div class="action-group"><h3>Actie plannen</h3><form method="post" class="schedule-form"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="schedule-create"><input type="hidden" name="tenant" value="<?=h51($key)?>"><input type="hidden" name="return_section" value="operations"><label>Actie<select name="scheduled_action" required><?php if($status==='active'):?><option value="suspend">Uitschakelen</option><?php elseif($status==='suspended'):?><option value="activate">Heractiveren</option><option value="export">Volledige export maken</option><?php elseif($status==='pending_delete'):?><option value="cancel-delete">Verwijdering annuleren</option><?php endif;?></select></label><label>Moment<input type="datetime-local" name="execute_at_local" required></label><div class="hint">Europe/Amsterdam · minimaal 1 minuut vooruit · delete/purge zijn nooit planbaar.</div><button class="btn" type="submit">Inplannen</button></form></div><?php endif;?>
<?php if($canMutate&&$acties):?><div class="action-group <?=$status==='pending_delete'||in_array('delete',$acties,true)?'danger-zone':''?>"><h3>Lifecycle</h3><div class="actions"><?php foreach($acties as$actie):?><form method="post" autocomplete="off" <?=$actie==='suspend'?'data-confirm-suspend="1" data-tenant-label="'.h51($name).'"':''?>><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="tenant" value="<?=h51($key)?>"><input type="hidden" name="action" value="<?=h51($actie)?>"><input type="hidden" name="return_section" value="tenants"><?php if(in_array($actie,['delete','purge'],true)):?><input type="text" name="confirm_tenant" placeholder="Typ <?=h51($key)?>" required aria-label="Tenant-key bevestigen"><?php endif;?><?php if($actie==='purge'):?><input type="text" name="confirm_purge" placeholder="VERWIJDER-DEFINITIEF" required aria-label="Definitieve purge bevestigen"><?php endif;?><button class="btn <?=$actie==='purge'?'danger':($actie==='delete'||$actie==='suspend'?'warning':'')?>" type="submit"><?=h51(action51($actie))?></button></form><?php endforeach;?></div></div><?php endif;?></div></details></section>
<?php endforeach;?></div>

<?php elseif($section==='onboarding'):?>
<div class="section-title"><h2>Onboardingwizard</h2><p>De console detecteert alle technische fases. Secrets blijven buiten de webqueue; de eerste beheerder wordt daarom bewust server-side ingesteld.</p></div><div class="grid"><?php $shown=0;foreach($tenants as$t):if(!is_array($t))continue;$ob=cpSuiteOnboarding($t);if($ob['percent']>=100)continue;$shown++;$key=(string)$t['tenant_key'];$next=volgendeOnboarding51($ob);?><section class="card"><div class="head"><div><h3><?=h51((string)($t['name']??$key))?></h3><div class="host"><?=h51($key)?> · <?=h51(label51((string)$t['status']))?></div></div><strong><?=$ob['percent']?>%</strong></div><div class="progress"><span style="width:<?=$ob['percent']?>%"></span></div><div class="step-list"><?php foreach($ob['steps']as$step):?><div class="step <?=($step['done']??false)?'done':''?>"><span class="step-dot"><?=($step['done']??false)?'✓':'•'?></span><div><strong><?=h51((string)$step['label'])?></strong><?php if(!($step['done']??false)&&($step['key']??'')==='admin'):?><div class="hint">Wachtwoord blijft buiten browser en queue.</div><?php endif;?></div></div><?php endforeach;?></div><?php if($next):?><div class="action-group"><h3>Volgende stap: <?=h51((string)$next['label'])?></h3><?php if(($next['key']??'')==='admin'):?><p class="muted">Activeer de eerste beheerder via een verborgen TTY of veilige STDIN-secretbron:</p><div class="code">sudo php <?=h51((string)cp51Config()['app_root'])?>/bin/bootstrap-tenant-admin.php --config=<?=h51($tenantsRoot.'/'.$key.'/config.php')?></div><?php else:?><p class="muted">Deze infrastructuurstap blijft een vaste, gevalideerde prepare/apply-fase. Gebruik geen vrij shellcommando vanuit de browser; voer de bestaande server-side provisioningflow voor <strong><?=h51((string)$next['label'])?></strong> uit en vernieuw daarna de platformdata.</p><?php endif;?></div><?php endif;?></section><?php endforeach;?><?php if($shown===0):?><div class="empty">Alle verenigingen hebben hun onboarding afgerond.</div><?php endif;?></div>

<?php elseif($section==='operations'):?>
<div class="section-title"><h2>Operaties</h2><p>Openstaande queue-items en geplande lifecycle-acties op één plaats.</p></div>
<section class="card"><div class="head"><div><h2>Openstaande aanvragen</h2><div class="host">Alleen pending aanvragen van de huidige operator. De root-only processing-map blijft bewust afgeschermd.</div></div><span class="badge"><?=count($openstaandeActies)?> openstaand</span></div><?php if(!$openstaandeActies):?><div class="empty" style="margin-top:12px">Geen eigen aanvragen wachten op verwerking.</div><?php else:?><div class="table-wrap"><table class="history-table"><thead><tr><th>Aangevraagd</th><th>Vereniging</th><th>Actie</th><th>Leeftijd</th><th>Request</th></tr></thead><tbody><?php foreach($openstaandeActies as$r):?><tr><td><?=h51(tijd51($r['requested_at_utc']))?></td><td><?=h51($r['tenant_key'])?></td><td><?=h51(action51($r['action']))?></td><td class="<?=$r['stale']?'stale':''?>"><?=h51(leeftijd51((int)$r['age_seconds']))?><?=$r['stale']?' · controleer queue':''?></td><td><code><?=h51(substr($r['request_id'],0,8))?></code></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<section class="card" style="margin-top:16px"><div class="head"><div><h2>Geplande acties</h2><div class="host">Uitschakelen, activeren, exporteren en annuleren kunnen vooruit worden gepland. Delete en purge nooit.</div></div><span class="badge"><?=count($schedules)?> geregistreerd</span></div><?php if(!$schedules):?><div class="empty" style="margin-top:12px">Nog geen geplande acties.</div><?php else:?><div class="table-wrap"><table class="history-table"><thead><tr><th>Moment</th><th>Vereniging</th><th>Actie</th><th>Operator</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($schedules as$s):?><tr><td><?=h51(tijd51($s['execute_at_utc']))?></td><td><?=h51($s['tenant_key'])?></td><td><?=h51(action51($s['action']))?></td><td><?=h51($s['operator'])?></td><td class="<?=$s['status']==='failed'?'result-failed':($s['status']==='completed'?'result-ok':'')?>"><?=h51(schedule51($s['status']))?></td><td><?php if($canSchedule&&$s['status']==='scheduled'):?><form method="post" data-confirm-action="Deze geplande actie annuleren?"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="schedule-cancel"><input type="hidden" name="tenant" value="<?=h51($s['tenant_key'])?>"><input type="hidden" name="schedule_id" value="<?=h51($s['schedule_id'])?>"><input type="hidden" name="return_section" value="operations"><button class="btn" type="submit">Annuleren</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>

<?php elseif($section==='operators'):?>
<div class="section-title"><h2>Operators & rollen</h2><p>Authenticatie blijft in het root-owned htpasswd-bestand; autorisatie wordt hier expliciet en server-side afgedwongen.</p></div>
<section class="card"><div class="head"><div><h2>Platformrollen</h2><div class="host">Eigenaar kan rollen beheren; Beheerder kan operationele acties uitvoeren; Alleen lezen kan uitsluitend observeren en exporteren.</div></div><span class="badge"><?=count($rolesState['roles'])?> bekend</span></div><div class="operator-grid"><?php foreach($rolesState['roles']as$user=>$userRole):?><div class="operator-row"><div><strong><?=h51((string)$user)?></strong><?php if(hash_equals($operator,(string)$user)):?><span class="hint">huidige sessie</span><?php endif;?></div><span class="role-chip"><?=h51(control58RoleLabel((string)$userRole))?></span><?php if($canRoles):?><form method="post" class="actions"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="action" value="operator-role-set"><input type="hidden" name="target_operator" value="<?=h51((string)$user)?>"><input type="hidden" name="return_section" value="operators"><select name="target_role" aria-label="Rol voor <?=h51((string)$user)?>"><?php foreach(control58Roles()as$r):?><option value="<?=h51($r)?>" <?=$r===$userRole?'selected':''?>><?=h51(control58RoleLabel($r))?></option><?php endforeach;?></select><button class="btn" type="submit">Opslaan</button></form><?php endif;?></div><?php endforeach;?></div></section>
<section class="card" style="margin-top:16px"><h2>Operator toevoegen of wachtwoord roteren</h2><p class="muted">Een wachtwoord is een secret en gaat daarom nooit via deze webqueue. Voeg of roteer operators via de bestaande root-only bootstrap met verborgen invoer. Na de eerstvolgende platformrefresh verschijnt een nieuwe operator standaard als <strong>Alleen lezen</strong>.</p><div class="code">sudo php <?=h51((string)cp51Config()['app_root'])?>/bin/bootstrap-control-plane-operator.php --user=&lt;operator&gt;</div></section>

<?php elseif($section==='audit'):?>
<div class="section-title"><h2>Centraal auditlog</h2><p>Gesanitiseerde read-only kopie van de laatste server-side beheeracties. Het originele root-auditlog blijft buiten de weblaag.</p></div><section class="card"><div class="head"><form method="get" class="toolbar"><input type="hidden" name="section" value="audit"><input type="search" name="audit_q" value="<?=h51((string)($_GET['audit_q']??''))?>" placeholder="Zoek operator, tenant, actie of melding"><select name="audit_result"><option value="all">Alle resultaten</option><option value="ok" <?=$auditResult==='ok'?'selected':''?>>Geslaagd</option><option value="failed" <?=$auditResult==='failed'?'selected':''?>>Mislukt</option></select><button class="btn" type="submit">Filter</button></form><a class="btn secondary" href="/?section=audit&export=audit-csv">CSV exporteren</a></div><?php if(!$filteredAudit):?><div class="empty">Geen auditregels voor dit filter.</div><?php else:?><div class="table-wrap"><table class="history-table"><thead><tr><th>Tijd</th><th>Operator</th><th>Tenant</th><th>Actie</th><th>Resultaat</th><th>Melding</th></tr></thead><tbody><?php foreach($filteredAudit as$r):?><tr><td><?=h51(tijd51($r['timestamp_utc']))?></td><td><?=h51($r['operator'])?></td><td><?=h51($r['tenant_key'])?></td><td><?=h51(action51($r['action']))?></td><td class="<?=$r['result']==='ok'?'result-ok':'result-failed'?>"><?=$r['result']==='ok'?'Geslaagd':'Mislukt'?></td><td><?=h51($r['message'])?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?></section>
<?php endif;?>
<p class="foot">Snapshot bijgewerkt: <?=h51(tijd51($snapshot['generated_at_utc']??null))?> · Tijdzone Europe/Amsterdam · DNS-providerrecords worden nooit automatisch verwijderd. De console bevat bewust geen impersonatie, vrije shell of databaseconsole.</p>
</div></main></div>
<!-- CSP-safe interacties staan in app.js; daarin worden window.confirm en tenant-filtering uitgevoerd. -->
<script src="/app.js" defer></script>
</body></html>
