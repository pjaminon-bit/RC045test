<?php
// ============================================================
// Modulaire beheerpagina: Gebruikers
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/data-slot.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!function_exists('authHeeftExplicietRecht') || !authHeeftExplicietRecht('gebruikers')) {
    http_response_code(403);
    echo 'Geen toegang tot Gebruikers.';
    exit;
}

$rechtenDef = require __DIR__ . '/gebruikers-rechten.php';
$rechtGroepen = is_array($rechtenDef['groepen'] ?? null) ? $rechtenDef['groepen'] : [];
$gevoeligeRechten = is_array($rechtenDef['gevoelig'] ?? null) ? $rechtenDef['gevoelig'] : [];
$alleRechten = [];
foreach ($rechtGroepen as $groep => $items) {
    foreach ((array)$items as $sleutel => $label) $alleRechten[$sleutel] = $label;
}

function guEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function guFlash(string $tekst, string $type = 'ok'): void {
    $_SESSION['gebruikers_module_flash'] = ['tekst' => $tekst, 'type' => $type];
}
function guRedirect(): void { header('Location: gebruikers.php'); exit; }
function guVindIndex(array $gebruikers, string $naam): ?int {
    foreach ($gebruikers as $i => $g) {
        if (isset($g['gebruikersnaam']) && strcasecmp((string)$g['gebruikersnaam'], $naam) === 0) return $i;
    }
    return null;
}
function guGekozenRechten(array $toegestaan): array {
    $gekozen = array_map('strval', (array)($_POST['tabs'] ?? []));
    return array_values(array_intersect(array_keys($toegestaan), $gekozen));
}
function guLeesLogins(string $pad): array {
    if (!is_file($pad)) return [];
    $raw = @file_get_contents($pad);
    $regels = $raw === false ? null : json_decode($raw, true);
    if (!is_array($regels)) return [];
    $resultaat = [];
    foreach ($regels as $r) {
        if (!is_array($r) || ($r['actie'] ?? '') !== 'login') continue;
        $naam = trim((string)($r['gebruiker'] ?? ''));
        $tijd = strtotime((string)($r['tijd'] ?? ''));
        if ($naam === '' || $tijd === false) continue;
        $k = strtolower($naam);
        if (!isset($resultaat[$k]) || $tijd > $resultaat[$k]) $resultaat[$k] = $tijd;
    }
    return $resultaat;
}
function guDatum(?int $tijd): string { return $tijd ? date('d-m-Y H:i', $tijd) : 'Nog niet geregistreerd'; }
function guAangemaakt(array $g): string {
    $ts = strtotime((string)($g['aangemaakt'] ?? ''));
    return $ts === false ? 'Onbekend' : date('d-m-Y', $ts);
}

$flash = $_SESSION['gebruikers_module_flash'] ?? null;
unset($_SESSION['gebruikers_module_flash']);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        guFlash('Sessie verlopen. Ververs de pagina en probeer het opnieuw.', 'fout');
        guRedirect();
    }

    $actie = (string)($_POST['actie'] ?? '');
    $slot = dataSlotOpen();
    if (!$slot) {
        guFlash('Opslaan is tijdelijk niet mogelijk omdat het dataslot niet kon worden verkregen.', 'fout');
        guRedirect();
    }

    try {
        $gebruikers = laadGebruikers($usersBestand);

        if ($actie === 'aanmaken') {
            $naam = trim((string)($_POST['gebruikersnaam'] ?? ''));
            $wachtwoord = (string)($_POST['wachtwoord'] ?? '');
            $herhaal = (string)($_POST['wachtwoord_herhaald'] ?? '');
            $tabs = guGekozenRechten($alleRechten);

            if ($naam === '' || !preg_match('/^[a-zA-Z0-9._-]{2,30}$/', $naam)) {
                guFlash('Gebruikersnaam moet 2 tot 30 tekens zijn: letters, cijfers, punt, streepje of underscore.', 'fout');
            } elseif (strcasecmp($naam, 'beheerder') === 0) {
                guFlash('“beheerder” is gereserveerd voor het hoofdbeheeraccount.', 'fout');
            } elseif (guVindIndex($gebruikers, $naam) !== null) {
                guFlash('Er bestaat al een account met deze gebruikersnaam. Gebruik bij dat account “Wachtwoord wijzigen”.', 'fout');
            } elseif (strlen($wachtwoord) < 12) {
                guFlash('Een nieuw wachtwoord moet minimaal 12 tekens lang zijn.', 'fout');
            } elseif ($wachtwoord !== $herhaal) {
                guFlash('De twee wachtwoorden komen niet overeen.', 'fout');
            } else {
                $gebruikers[] = [
                    'gebruikersnaam' => $naam,
                    'hash' => password_hash($wachtwoord, PASSWORD_DEFAULT),
                    'aangemaakt' => date('c'),
                    'tabs' => $tabs,
                ];
                if (schrijfGebruikers($usersBestand, $gebruikers)) {
                    schrijfLog($logBestand, $huidigeGebruiker, 'gebruiker_aangemaakt', $naam . ' · ' . count($tabs) . ' recht(en)');
                    guFlash('Account “' . $naam . '” is aangemaakt.');
                } else guFlash('Account kon niet worden opgeslagen.', 'fout');
            }
        } elseif ($actie === 'rechten') {
            $naam = trim((string)($_POST['gebruikersnaam'] ?? ''));
            $tabs = guGekozenRechten($alleRechten);
            $idx = guVindIndex($gebruikers, $naam);
            if ($idx === null) {
                guFlash('Gebruiker niet gevonden.', 'fout');
            } else {
                $gebruikers[$idx]['tabs'] = $tabs;
                if (schrijfGebruikers($usersBestand, $gebruikers)) {
                    schrijfLog($logBestand, $huidigeGebruiker, 'toegang_bijgewerkt', $naam . ': ' . ($tabs ? implode(', ', $tabs) : 'geen beheerrechten'));
                    guFlash('Rechten van “' . $naam . '” zijn bijgewerkt.');
                } else guFlash('Rechten konden niet worden opgeslagen.', 'fout');
            }
        } elseif ($actie === 'wachtwoord') {
            $naam = trim((string)($_POST['gebruikersnaam'] ?? ''));
            $wachtwoord = (string)($_POST['wachtwoord'] ?? '');
            $herhaal = (string)($_POST['wachtwoord_herhaald'] ?? '');
            $idx = guVindIndex($gebruikers, $naam);
            if ($idx === null) {
                guFlash('Gebruiker niet gevonden.', 'fout');
            } elseif (strlen($wachtwoord) < 12) {
                guFlash('Het nieuwe wachtwoord moet minimaal 12 tekens lang zijn.', 'fout');
            } elseif ($wachtwoord !== $herhaal) {
                guFlash('De twee wachtwoorden komen niet overeen.', 'fout');
            } else {
                $gebruikers[$idx]['hash'] = password_hash($wachtwoord, PASSWORD_DEFAULT);
                if (schrijfGebruikers($usersBestand, $gebruikers)) {
                    schrijfLog($logBestand, $huidigeGebruiker, 'wachtwoord_reset', $naam);
                    guFlash('Wachtwoord van “' . $naam . '” is gewijzigd.');
                } else guFlash('Wachtwoord kon niet worden opgeslagen.', 'fout');
            }
        } elseif ($actie === 'verwijderen') {
            $naam = trim((string)($_POST['gebruikersnaam'] ?? ''));
            $bevestiging = trim((string)($_POST['bevestiging'] ?? ''));
            $idx = guVindIndex($gebruikers, $naam);
            if ($idx === null) {
                guFlash('Gebruiker niet gevonden.', 'fout');
            } elseif (strcasecmp($naam, $huidigeGebruiker) === 0 && !$isMaster) {
                guFlash('Je kunt het account waarmee je bent ingelogd niet verwijderen.', 'fout');
            } elseif ($bevestiging !== $naam) {
                guFlash('Verwijderen geweigerd: typ de gebruikersnaam exact over ter bevestiging.', 'fout');
            } else {
                array_splice($gebruikers, $idx, 1);
                if (schrijfGebruikers($usersBestand, $gebruikers)) {
                    schrijfLog($logBestand, $huidigeGebruiker, 'gebruiker_verwijderd', $naam);
                    guFlash('Account “' . $naam . '” is verwijderd.');
                } else guFlash('Account kon niet worden verwijderd.', 'fout');
            }
        } else {
            guFlash('Onbekende gebruikersactie.', 'fout');
        }
    } finally {
        dataSlotDicht($slot);
    }
    guRedirect();
}

$gebruikers = laadGebruikers($usersBestand);
usort($gebruikers, static fn($a, $b) => strcasecmp((string)($a['gebruikersnaam'] ?? ''), (string)($b['gebruikersnaam'] ?? '')));
$laatsteLogins = guLeesLogins($logBestand);
$aantalMetGebruikersrecht = 0;
$aantalLegacy = 0;
foreach ($gebruikers as $g) {
    $tabs = $g['tabs'] ?? null;
    if (!is_array($tabs)) $aantalLegacy++;
    if (is_array($tabs) && in_array('gebruikers', $tabs, true)) $aantalMetGebruikersrecht++;
}
?><!DOCTYPE html>
<html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Gebruikersbeheer</title>
<style>
:root{--bg:#f6f2e8;--card:#fff;--ink:#26351d;--muted:#68705f;--line:#ddd8c0;--primary:#3a7a77;--primary2:#2d6260;--danger:#a23b32;--dangerbg:#fff0ed;--warn:#8a6513;--warnbg:#fff7db;--ok:#23613e;--okbg:#eaf6ee}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{position:sticky;top:0;z-index:20;background:rgba(255,255,255,.96);border-bottom:1px solid var(--line);padding:14px 22px}.topin{max-width:1180px;margin:auto;display:flex;justify-content:space-between;align-items:center;gap:16px}.top a{font-weight:750;color:var(--primary2);text-decoration:none}.wrap{max-width:1180px;margin:30px auto;padding:0 22px 80px}h1{margin-bottom:4px}.sub{color:var(--muted);margin-top:0}.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:22px 0}.stat,.card{background:var(--card);border:1px solid var(--line);border-radius:15px;box-shadow:0 3px 16px rgba(35,49,25,.04)}.stat{padding:18px}.stat b{display:block;font-size:28px}.stat span{color:var(--muted);font-size:14px}.card{padding:22px;margin-bottom:18px}.flash{padding:13px 15px;border-radius:10px;margin:16px 0;font-weight:650}.flash.ok{background:var(--okbg);color:var(--ok)}.flash.fout{background:var(--dangerbg);color:var(--danger)}.toolbar{display:flex;gap:10px;align-items:center;justify-content:space-between;flex-wrap:wrap}.search{min-width:260px;max-width:420px;width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:9px;font:inherit}.accounts{display:grid;gap:14px}.account{background:#fff;border:1px solid var(--line);border-radius:14px;overflow:hidden}.account-head{padding:16px 18px;display:flex;align-items:center;justify-content:space-between;gap:15px}.ident{display:flex;gap:12px;align-items:center}.avatar{width:42px;height:42px;border-radius:50%;background:#eaf4f3;color:var(--primary2);display:grid;place-items:center;font-weight:800;font-size:18px}.naam{font-size:18px;font-weight:800}.meta{color:var(--muted);font-size:13px;margin-top:3px}.badges{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.badge{font-size:12px;padding:4px 8px;border-radius:99px;background:#eef0e8}.badge.sens{background:var(--warnbg);color:var(--warn);font-weight:700}.badge.legacy{background:var(--dangerbg);color:var(--danger);font-weight:700}details{border-top:1px solid #eee9dc}summary{cursor:pointer;padding:12px 18px;font-weight:750;color:var(--primary2)}.detailbody{padding:5px 18px 18px}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:18px}.rights{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:9px}.rechtgroep{border:1px solid #e5e0d1;border-radius:10px;padding:12px}.rechtgroep h4{margin:0 0 8px}.check{display:flex;gap:8px;align-items:flex-start;margin:7px 0}.check small{color:var(--warn);font-weight:700}.actierij{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.btn{border:0;border-radius:9px;padding:10px 14px;font:inherit;font-weight:750;cursor:pointer;text-decoration:none;display:inline-block}.primary{background:var(--primary);color:#fff}.secondary{background:#fff;border:1px solid #cbc6b5;color:var(--ink)}.danger{background:var(--danger);color:#fff}.velden{display:grid;gap:11px}.veld label{display:block;font-weight:700;margin-bottom:5px}.veld input{width:100%;padding:10px;border:1px solid #cbc6b5;border-radius:8px;font:inherit}.hint{font-size:13px;color:var(--muted)}.waarschuwing{background:var(--warnbg);color:#6d5316;padding:12px;border-radius:9px;margin:10px 0}.dangerzone{border:1px solid #efc7c1;background:#fff9f7;border-radius:10px;padding:13px;margin-top:14px}.pwrow{display:flex;gap:7px}.pwrow input{flex:1}.preset{display:flex;gap:7px;flex-wrap:wrap;margin-bottom:10px}.preset button{font-size:12px;padding:6px 9px}@media(max-width:850px){.stats,.grid2{grid-template-columns:1fr}.rights{grid-template-columns:1fr}.account-head{align-items:flex-start;flex-direction:column}.badges{justify-content:flex-start}}@media(max-width:520px){.wrap{padding-left:12px;padding-right:12px}.stats{grid-template-columns:1fr}.pwrow{flex-direction:column}}
</style></head><body>
<div class="top"><div class="topin"><a href="../beheer.php">← Terug naar beheer</a><strong>Gebruikers & rechten</strong></div></div>
<main class="wrap"><h1>Gebruikersbeheer</h1><p class="sub">Beheer accounts, toegangsrechten en wachtwoorden. Het hoofdbeheeraccount uit <code>beheer-config.php</code> staat bewust niet in deze lijst.</p>
<?php if(is_array($flash)):?><div class="flash <?=guEsc($flash['type']??'ok')?>"><?=guEsc($flash['tekst']??'')?></div><?php endif;?>
<div class="stats"><div class="stat"><b><?=count($gebruikers)?></b><span>gewone beheeraccounts</span></div><div class="stat"><b><?=$aantalMetGebruikersrecht?></b><span>account(s) mogen gebruikers beheren</span></div><div class="stat"><b><?=$aantalLegacy?></b><span>legacy-account(s) zonder expliciete rechten</span></div></div>

<section class="card"><h2>Nieuw account</h2><p class="sub">Maak een persoonlijk account aan. Kies alleen de rechten die deze persoon nodig heeft.</p>
<form method="post" class="grid2"><input type="hidden" name="csrf" value="<?=guEsc($csrfToken)?>"><input type="hidden" name="actie" value="aanmaken"><div class="velden"><div class="veld"><label>Gebruikersnaam</label><input name="gebruikersnaam" autocomplete="off" required pattern="[A-Za-z0-9._-]{2,30}" placeholder="bijv. secretaris"></div><div class="veld"><label>Wachtwoord</label><div class="pwrow"><input class="pw1" type="password" name="wachtwoord" minlength="12" autocomplete="new-password" required><button class="btn secondary genpw" type="button">Genereer</button><button class="btn secondary showpw" type="button">Toon</button></div><div class="hint">Minimaal 12 tekens. De generator maakt een willekeurig wachtwoord van 20 tekens.</div></div><div class="veld"><label>Herhaal wachtwoord</label><input class="pw2" type="password" name="wachtwoord_herhaald" minlength="12" autocomplete="new-password" required></div></div><div><div class="preset"><button class="btn secondary preset-content" type="button">Contentbeheer</button><button class="btn secondary preset-all" type="button">Alles selecteren</button><button class="btn secondary preset-none" type="button">Alles wissen</button></div><div class="rights"><?php foreach($rechtGroepen as $groep=>$items):?><div class="rechtgroep"><h4><?=guEsc($groep)?></h4><?php foreach($items as $key=>$label):?><label class="check"><input type="checkbox" name="tabs[]" value="<?=guEsc($key)?>" data-recht="<?=guEsc($key)?>"><span><?=guEsc($label)?><?php if(in_array($key,$gevoeligeRechten,true)):?> <small>gevoelig</small><?php endif;?></span></label><?php endforeach;?></div><?php endforeach;?></div></div><div><button class="btn primary" type="submit">Account aanmaken</button></div></form></section>

<section class="card"><div class="toolbar"><div><h2 style="margin:0">Bestaande accounts</h2><p class="sub" style="margin:4px 0 0">Klik een account open voor rechten, wachtwoord of verwijderen.</p></div><input id="account-search" class="search" type="search" placeholder="Zoek gebruiker…"></div></section>
<div class="accounts" id="accounts">
<?php foreach($gebruikers as $g): $naam=(string)($g['gebruikersnaam']??''); $tabs=is_array($g['tabs']??null)?array_values(array_intersect(array_keys($alleRechten),$g['tabs'])):null; $legacy=$tabs===null; $effectief=$legacy?array_keys($alleRechten):$tabs; $sens=array_values(array_intersect($gevoeligeRechten,$effectief)); $initial=strtoupper(substr($naam,0,1)); ?>
<article class="account" data-user="<?=guEsc(strtolower($naam))?>"><div class="account-head"><div class="ident"><div class="avatar"><?=guEsc($initial)?></div><div><div class="naam"><?=guEsc($naam)?></div><div class="meta">Aangemaakt: <?=guEsc(guAangemaakt($g))?> · Laatste login: <?=guEsc(guDatum($laatsteLogins[strtolower($naam)]??null))?></div></div></div><div class="badges"><span class="badge"><?=count($effectief)?> recht(en)</span><?php if($sens):?><span class="badge sens">hoog vertrouwen</span><?php endif;?><?php if($legacy):?><span class="badge legacy">legacy brede toegang</span><?php endif;?></div></div>
<details><summary>Rechten beheren</summary><div class="detailbody"><?php if($legacy):?><div class="waarschuwing"><strong>Legacy-account:</strong> er is nog geen expliciete rechtenlijst opgeslagen. Volgens het bestaande compatibiliteitsmodel heeft dit account brede toegang, behalve de later toegevoegde gevoelige beheerrechten. Sla hieronder een expliciete selectie op om dit voorspelbaar te maken.</div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=guEsc($csrfToken)?>"><input type="hidden" name="actie" value="rechten"><input type="hidden" name="gebruikersnaam" value="<?=guEsc($naam)?>"><div class="rights"><?php foreach($rechtGroepen as $groep=>$items):?><div class="rechtgroep"><h4><?=guEsc($groep)?></h4><?php foreach($items as $key=>$label):?><label class="check"><input type="checkbox" name="tabs[]" value="<?=guEsc($key)?>" <?=in_array($key,$effectief,true)?'checked':''?>><span><?=guEsc($label)?><?php if(in_array($key,$gevoeligeRechten,true)):?> <small>gevoelig</small><?php endif;?></span></label><?php endforeach;?></div><?php endforeach;?></div><div class="actierij"><button class="btn primary" type="submit">Rechten opslaan</button></div></form></div></details>
<details><summary>Wachtwoord wijzigen</summary><div class="detailbody"><form method="post" class="velden"><input type="hidden" name="csrf" value="<?=guEsc($csrfToken)?>"><input type="hidden" name="actie" value="wachtwoord"><input type="hidden" name="gebruikersnaam" value="<?=guEsc($naam)?>"><div class="veld"><label>Nieuw wachtwoord voor <?=guEsc($naam)?></label><div class="pwrow"><input class="pw1" type="password" name="wachtwoord" minlength="12" autocomplete="new-password" required><button class="btn secondary genpw" type="button">Genereer</button><button class="btn secondary showpw" type="button">Toon</button></div></div><div class="veld"><label>Herhaal nieuw wachtwoord</label><input class="pw2" type="password" name="wachtwoord_herhaald" minlength="12" autocomplete="new-password" required></div><div><button class="btn primary" type="submit">Wachtwoord wijzigen</button></div></form></div></details>
<details><summary style="color:var(--danger)">Account verwijderen</summary><div class="detailbody"><div class="dangerzone"><strong>Permanent verwijderen</strong><p class="hint">De gebruikerslijst wordt vooraf automatisch geback-upt. Typ <strong><?=guEsc($naam)?></strong> om te bevestigen.</p><form method="post"><input type="hidden" name="csrf" value="<?=guEsc($csrfToken)?>"><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="gebruikersnaam" value="<?=guEsc($naam)?>"><div class="pwrow"><input name="bevestiging" autocomplete="off" required placeholder="<?=guEsc($naam)?>"><button class="btn danger" type="submit">Account verwijderen</button></div></form></div></div></details>
</article><?php endforeach;?>
<?php if(!$gebruikers):?><div class="card">Er zijn nog geen gewone beheeraccounts. Het hoofdbeheeraccount blijft wel beschikbaar.</div><?php endif;?></div>
</main>
<script>
function sterkWachtwoord(){const chars='ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%+-_';const a=new Uint32Array(20);crypto.getRandomValues(a);return Array.from(a,n=>chars[n%chars.length]).join('');}
document.addEventListener('click',function(e){const b=e.target.closest('button');if(!b)return;if(b.classList.contains('genpw')){const form=b.closest('form');const p=sterkWachtwoord();form.querySelector('.pw1').value=p;form.querySelector('.pw2').value=p;form.querySelectorAll('.pw1,.pw2').forEach(i=>i.type='text');}if(b.classList.contains('showpw')){const form=b.closest('form');const inputs=form.querySelectorAll('.pw1,.pw2');const nieuw=inputs[0].type==='password'?'text':'password';inputs.forEach(i=>i.type=nieuw);b.textContent=nieuw==='text'?'Verberg':'Toon';}if(b.classList.contains('preset-all')||b.classList.contains('preset-none')||b.classList.contains('preset-content')){const form=b.closest('form');form.querySelectorAll('input[name="tabs[]"]').forEach(c=>{if(b.classList.contains('preset-all'))c.checked=true;else if(b.classList.contains('preset-none'))c.checked=false;else c.checked=!['gebruikers','backups','log'].includes(c.value);});}});
const zoek=document.getElementById('account-search');if(zoek)zoek.addEventListener('input',()=>{const q=zoek.value.trim().toLowerCase();document.querySelectorAll('.account').forEach(a=>a.hidden=q!==''&&!a.dataset.user.includes(q));});
</script></body></html>
