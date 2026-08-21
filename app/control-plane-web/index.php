<?php
require_once dirname(__DIR__) . '/control-plane/control-plane-runtime.php';
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
        $tenant = trim((string)($_POST['tenant'] ?? ''));
        $actie = trim((string)($_POST['action'] ?? ''));
        $id = cp51Request($tenant, $actie, $_POST);
        header('Location: /?queued=' . rawurlencode($id), true, 303);
        exit;
    } catch (Throwable $e) {
        $fout = $e->getMessage();
    }
}
if (isset($_GET['queued']) && preg_match('/^[0-9a-f]{32}$/D', (string)$_GET['queued'])) {
    $melding = 'Aanvraag ' . substr((string)$_GET['queued'], 0, 8) . ' is veilig in de uitvoerqueue geplaatst.';
}
$snapshot = cp51Snapshot();
$tenants = $snapshot['tenants'];
$tz = new DateTimeZone('Europe/Amsterdam');
function h51(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function tijd51(?string $utc): string {
    if (!$utc) return '—';
    try { return (new DateTimeImmutable($utc))->setTimezone(new DateTimeZone('Europe/Amsterdam'))->format('d-m-Y H:i:s'); }
    catch (Throwable $e) { return '—'; }
}
function label51(string $status): string {
    return match($status) {
        'unmanaged' => 'Nog niet geadopteerd',
        'active' => 'Actief',
        'suspended' => 'Uitgeschakeld',
        'pending_delete' => 'Verwijdering aangevraagd',
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
        default => $actie,
    };
}
?><!doctype html>
<html lang="nl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Verenigingsplatform beheer</title>
<style>
:root{font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#17211b;background:#f5f7f5}*{box-sizing:border-box}body{margin:0}.wrap{max-width:1180px;margin:0 auto;padding:32px 20px 56px}.top{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:24px}.eyebrow{font-size:.78rem;text-transform:uppercase;letter-spacing:.08em;color:#607067;font-weight:700}h1{margin:.25rem 0 .35rem;font-size:clamp(1.65rem,3vw,2.35rem)}.sub{color:#66736b;margin:0}.operator{background:#fff;border:1px solid #dce3de;border-radius:12px;padding:10px 13px;font-size:.9rem}.notice{padding:12px 14px;border-radius:10px;margin:0 0 18px}.ok{background:#eaf7ee;border:1px solid #b9dfc2}.err{background:#fff0f0;border:1px solid #efc1c1}.summary{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:20px}.stat{background:#fff;border:1px solid #dce3de;border-radius:14px;padding:16px}.stat strong{display:block;font-size:1.45rem}.grid{display:grid;gap:16px}.card{background:#fff;border:1px solid #dce3de;border-radius:16px;padding:18px;box-shadow:0 2px 12px rgba(22,40,29,.04)}.head{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}.tenant{font-size:1.18rem;font-weight:750}.host{color:#66736b;font-size:.9rem;margin-top:3px}.badge{font-size:.8rem;font-weight:700;padding:6px 9px;border-radius:999px;background:#edf1ee;white-space:nowrap}.badge.active{background:#e8f6eb;color:#1f6a37}.badge.suspended{background:#fff4d8;color:#735b16}.badge.pending_delete{background:#ffe6e6;color:#8a2b2b}.meta{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin:16px 0}.meta div{background:#f7f9f7;border-radius:10px;padding:10px;min-width:0}.meta span{display:block;color:#738078;font-size:.76rem;margin-bottom:4px}.meta strong{font-size:.88rem;word-break:break-word}.actions{display:flex;flex-wrap:wrap;gap:8px}.actions form{display:inline-flex;gap:7px;align-items:center;flex-wrap:wrap}.btn{border:1px solid #bec9c1;background:#fff;color:#1d2b22;border-radius:9px;padding:9px 11px;font-weight:650;cursor:pointer}.btn:hover{background:#f3f6f4}.danger{border-color:#d9a4a4;color:#8b2020}.warning{border-color:#d9c27e;color:#6c5511}input[type=text]{border:1px solid #cbd4ce;border-radius:8px;padding:8px 9px;min-width:170px}.empty{background:#fff;border:1px dashed #cbd4ce;border-radius:14px;padding:30px;text-align:center;color:#66736b}.foot{margin-top:22px;color:#77817b;font-size:.8rem}@media(max-width:760px){.top{display:block}.operator{margin-top:12px}.summary,.meta{grid-template-columns:1fr 1fr}}@media(max-width:520px){.summary,.meta{grid-template-columns:1fr}.card{padding:14px}}
</style>
</head>
<body><main class="wrap">
<div class="top"><div><div class="eyebrow">Control-plane · fase 5.1</div><h1>Verenigingsplatform beheer</h1><p class="sub">Tenantstatus en veilige lifecycle-aanvragen. Rootuitvoering gebeurt buiten de webapp.</p></div><div class="operator">Ingelogd als <strong><?=h51($operator)?></strong></div></div>
<?php if($melding!==''):?><div class="notice ok"><?=h51($melding)?></div><?php endif;?>
<?php if($fout!==''):?><div class="notice err"><?=h51($fout)?></div><?php endif;?>
<?php
$actief=0;$uit=0;$pending=0;foreach($tenants as $t){$s=(string)($t['status']??'');if($s==='active')$actief++;elseif($s==='suspended')$uit++;elseif($s==='pending_delete')$pending++;}
?>
<div class="summary"><div class="stat"><span>Verenigingen</span><strong><?=count($tenants)?></strong></div><div class="stat"><span>Actief</span><strong><?=$actief?></strong></div><div class="stat"><span>Uit / pending delete</span><strong><?=$uit+$pending?></strong></div></div>
<div class="grid">
<?php if(!$tenants):?><div class="empty">Nog geen lifecycle-tenants gevonden. Na installatie ververst de root-executor deze status automatisch.</div><?php endif;?>
<?php foreach($tenants as $t): $key=(string)$t['tenant_key'];$status=(string)$t['status'];$acties=cp51ToegestaneActies($t); ?>
<section class="card"><div class="head"><div><div class="tenant"><?=h51($key)?></div><div class="host"><?=h51((string)($t['canonical_host']??''))?></div></div><span class="badge <?=h51($status)?>"><?=h51(label51($status))?></span></div>
<div class="meta"><div><span>Health</span><strong><?=($t['healthy']??false)?'Gezond':'Niet gezond / n.v.t.'?></strong></div><div><span>Laatste status</span><strong><?=h51(tijd51($t['updated_at_utc']??null))?></strong></div><div><span>Laatste export</span><strong><?=h51(isset($t['last_export']['created_at_utc'])?tijd51((string)$t['last_export']['created_at_utc']):'—')?></strong></div><div><span>Purge vanaf</span><strong><?=h51(tijd51($t['purge_not_before_utc']??null))?></strong></div></div>
<div class="actions">
<?php foreach($acties as $actie):?>
<form method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?=h51($csrf)?>"><input type="hidden" name="tenant" value="<?=h51($key)?>"><input type="hidden" name="action" value="<?=h51($actie)?>">
<?php if(in_array($actie,['delete','purge'],true)):?><input type="text" name="confirm_tenant" placeholder="Typ <?=h51($key)?>" required aria-label="Tenant-key bevestigen"><?php endif;?>
<?php if($actie==='purge'):?><input type="text" name="confirm_purge" placeholder="VERWIJDER-DEFINITIEF" required aria-label="Definitieve purge bevestigen"><?php endif;?>
<button class="btn <?=$actie==='purge'?'danger':($actie==='delete'||$actie==='suspend'?'warning':'')?>" type="submit"><?=h51(action51($actie))?></button></form>
<?php endforeach;?>
</div></section>
<?php endforeach;?>
</div>
<p class="foot">Snapshot bijgewerkt: <?=h51(tijd51($snapshot['generated_at_utc']??null))?> · Tijdzone Europe/Amsterdam. DNS-providerrecords worden nooit automatisch verwijderd.</p>
</main></body></html>