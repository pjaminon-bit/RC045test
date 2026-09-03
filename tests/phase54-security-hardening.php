<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c54(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function r54(array $argv, ?string $stdin = null, ?array $env = null): array
{
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proces = proc_open($argv, $spec, $pipes, null, $env, ['bypass_shell' => true]);
    if (!is_resource($proces)) return [255, '', 'proc_open faalde'];
    if ($stdin !== null) fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($proces), (string)$stdout, (string)$stderr];
}

function wis54(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        wis54($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

$tmp = sys_get_temp_dir() . '/rc045-phase54-' . bin2hex(random_bytes(5));
$base = $tmp . '/tenants';
@mkdir($base, 0750, true);

try {
    [$provisionCode, $provisionOut, $provisionErr] = r54([
        PHP_BINARY,
        $root . '/bin/provision-tenant.php',
        '--key=audit-club',
        '--name=Audit Club',
        '--url=https://audit-club.example',
        '--root=' . $base,
        '--modules=website,ledenadministratie,aanmelden',
    ]);
    $tenant = $base . '/audit-club';
    $config = $tenant . '/config.php';
    $private = $tenant . '/private';
    c54($provisionCode === 0 && is_file($config) && is_dir($private . '/security'), 'audittenant met private security-opslag wordt echt geprovisioned');

    $env = [
        'VERENIGING_REQUIRE_TENANT_CONFIG' => '1',
        'VERENIGING_CONFIG_FILE' => $config,
        'VERENIGING_PRIVATE_ROOT' => $private,
    ];

    // 1. Capabilitymigratie: ontbrekend legacyprofiel mag op geen enkele
    // installatie in brede beheerrechten veranderen.
    $capWorker = $tmp . '/cap-worker.php';
    file_put_contents($capWorker, <<<'PHP'
<?php
$root=$argv[1];
require $root.'/app/auth-capabilities.php';
$legacy=['gebruikersnaam'=>'legacy'];
$migrated=authGebruikerMigreerRecord($legacy);
echo json_encode([
  'caps'=>authGebruikerCapabilities($legacy),
  'migrated_caps'=>$migrated['capabilities']??null,
  'migrated_tabs'=>$migrated['tabs']??null,
]);
PHP);
    [$capCode, $capRaw, $capErr] = r54([PHP_BINARY, $capWorker, $root], null, $env);
    $cap = json_decode($capRaw, true);
    c54($capCode === 0 && is_array($cap), 'capabilityworker draait onder echte externe tenant-env');
    c54(($cap['caps'] ?? null) === [], 'legacy account krijgt extern geen impliciete brede capabilities');
    c54(($cap['migrated_caps'] ?? null) === [] && ($cap['migrated_tabs'] ?? null) === [], 'legacy migratie schrijft extern een expliciet leeg rechtenprofiel');

    // Standalone volgt dezelfde fail-closed rechtenregel: zonder expliciet
    // rechtenprofiel ontstaan nooit impliciete brede beheerrechten.
    [$legacyCode, $legacyRaw] = r54([PHP_BINARY, $capWorker, $root]);
    $legacy = json_decode($legacyRaw, true);
    c54($legacyCode === 0 && is_array($legacy) && ($legacy['caps'] ?? null) === [], 'standalone account zonder expliciet rechtenprofiel krijgt geen brede legacy-capabilities');

    // 2. Centrale sessiepoort: zelfs vóór een expliciete migratie kan een oud
    // account op een externe tenant geen beheerpagina bereiken. De synthetische
    // sessie bevat de nieuwe installatiebinding zodat alleen de rechtenpoort
    // onderwerp van deze testcase is.
    $users = $private . '/auth/users.json';
    file_put_contents($users, json_encode([
        ['gebruikersnaam'=>'legacy','hash'=>'x','sessie_versie'=>1,'actief'=>true],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    @chmod($users, 0640);
    $sessionDir = $private . '/sessions';
    $sessionWorker = $tmp . '/session-worker.php';
    file_put_contents($sessionWorker, <<<'PHP'
<?php
$root=$argv[1];$sessionDir=$argv[2];$users=$argv[3];$binding=str_repeat('d',64);
session_save_path($sessionDir);
session_start();
$_SESSION=['tenant_key'=>'audit-club','installation_binding'=>$binding,'csrf'=>str_repeat('a',64),'gebruiker'=>'legacy','is_master'=>false,'user_session_version'=>1];
$authSiteConfig=['vereniging'=>['sleutel'=>'audit-club']];
$authPaden=['tenant_private'=>true,'session_binding'=>$binding];
$csrfToken=(string)$_SESSION['csrf'];$ingelogd=true;$isMaster=false;$huidigeGebruiker='legacy';$usersBestand=$users;$inlogFout='';
function laadGebruikers($pad){$d=json_decode((string)file_get_contents($pad),true);return is_array($d)?$d:[];}
require $root.'/app/auth-session-check.php';
echo json_encode(['ingelogd'=>$ingelogd,'fout'=>$inlogFout,'session_user'=>$_SESSION['gebruiker']??null]);
session_write_close();
PHP);
    [$sessCode, $sessRaw, $sessErr] = r54([PHP_BINARY, $sessionWorker, $root, $sessionDir, $users], null, $env);
    $sess = json_decode($sessRaw, true);
    c54($sessCode === 0 && is_array($sess), 'sessiepoort kan met echte tenant/installatiebinding worden getest');
    c54(($sess['ingelogd'] ?? true) === false && ($sess['session_user'] ?? null) === null, 'externe legacy sessie zonder rechtenprofiel wordt fail-closed uitgelogd');
    c54(str_contains((string)($sess['fout'] ?? ''), 'rechtenprofiel'), 'geweigerde legacy sessie geeft gerichte migratiemelding');

    // Na veilige migratie naar een expliciet leeg profiel is de accountstate
    // geldig; rechten kunnen daarna bewust door de master worden toegekend.
    file_put_contents($users, json_encode([
        ['gebruikersnaam'=>'legacy','hash'=>'x','sessie_versie'=>1,'actief'=>true,'capabilities'=>[],'tabs'=>[]],
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    [$sess2Code, $sess2Raw] = r54([PHP_BINARY, $sessionWorker, $root, $sessionDir, $users], null, $env);
    $sess2 = json_decode($sess2Raw, true);
    c54($sess2Code === 0 && ($sess2['ingelogd'] ?? false) === true, 'expliciet leeg tenantrechtenprofiel is geldig en wordt niet als legacy gezien');

    // 3. Publieke aanmeldlimiter: state is per tenant, poging zes blokkeert en
    // beschadigde state schakelt de bescherming niet stil uit.
    $rateWorker = $tmp . '/rate-worker.php';
    file_put_contents($rateWorker, <<<'PHP'
<?php
$root=$argv[1];$private=$argv[2];
require $root.'/aanmeldingen-opslag.php';
$key=hash('sha256','203.0.113.77');$nu=1700000000;$result=[];
for($i=0;$i<6;$i++)$result[]=aanmeldenPogingRegistreer($key,$nu,5,3600);
$pad=aanmeldenPogingenPad();
$expected=$private.DIRECTORY_SEPARATOR.'security'.DIRECTORY_SEPARATOR.'aanmelden-pogingen.json';
file_put_contents($pad,'{kapot');
$corruptClosed=false;
try{aanmeldenPogingRegistreer(hash('sha256','203.0.113.78'),$nu,5,3600);}catch(Throwable $e){$corruptClosed=true;}
echo json_encode(['result'=>$result,'path'=>$pad,'expected'=>$expected,'exists'=>is_file($pad),'corrupt_closed'=>$corruptClosed]);
PHP);
    [$rateCode, $rateRaw, $rateErr] = r54([PHP_BINARY, $rateWorker, $root, $private], null, $env);
    $rate = json_decode($rateRaw, true);
    c54($rateCode === 0 && is_array($rate), 'aanmeld-rate-limitworker draait onder echte tenant-env');
    c54(($rate['path'] ?? '') === ($rate['expected'] ?? ''), 'aanmeld-rate-limitstate staat exact onder private_root/security');
    c54(($rate['result'] ?? null) === [true,true,true,true,true,false], 'vijf publieke aanmeldpogingen zijn toegestaan en poging zes wordt geblokkeerd');
    c54(($rate['exists'] ?? false) === true && ($rate['corrupt_closed'] ?? false) === true, 'corrupte rate-limitstate faalt gesloten in plaats van bescherming uit te schakelen');
    c54(!file_exists($root . '/aanmelden-pogingen.php'), 'nieuwe limiter schrijft geen runtime-state in de gedeelde applicatierelease');

    // 4. Privacyretentie: ook nog onbeoordeelde aanmeldingen hebben een harde
    // maximale bewaartermijn, gerekend vanaf ontvangst.
    $retentieWorker = $tmp . '/retentie-worker.php';
    file_put_contents($retentieWorker, <<<'PHP'
<?php
$root=$argv[1];
require $root.'/aanmeldingen-opslag.php';
$nu=strtotime('2026-08-23T12:00:00+02:00');
$data=['aanmeldingen'=>[
 ['id'=>'oud-nieuw','status'=>'nieuw','aangemaakt'=>date('c',$nu-91*86400),'gewijzigd'=>date('c',$nu-1*86400)],
 ['id'=>'recent-nieuw','status'=>'nieuw','aangemaakt'=>date('c',$nu-10*86400),'gewijzigd'=>date('c',$nu-1*86400)],
 ['id'=>'oud-beoordeeld','status'=>'afgewezen','aangemaakt'=>date('c',$nu-120*86400),'beoordeeld_op'=>date('c',$nu-91*86400)],
 ['id'=>'recent-beoordeeld','status'=>'afgewezen','aangemaakt'=>date('c',$nu-120*86400),'beoordeeld_op'=>date('c',$nu-10*86400)],
 ['id'=>'zonder-datum','status'=>'nieuw'],
]];
$removed=aanmeldingenPasRetentieToe($data,$nu);
echo json_encode(['removed'=>$removed,'ids'=>array_column($data['aanmeldingen'],'id')]);
PHP);
    [$retCode, $retRaw, $retErr] = r54([PHP_BINARY, $retentieWorker, $root], null, $env);
    $ret = json_decode($retRaw, true);
    c54($retCode === 0 && is_array($ret), 'retentieworker draait onder echte tenantconfig');
    c54(($ret['removed'] ?? null) === 3, 'oude onbeoordeelde, oude beoordeelde en datumloze aanmeldingen worden verwijderd');
    c54(($ret['ids'] ?? null) === ['recent-nieuw','recent-beoordeeld'], 'recente aanmeldingen blijven binnen de bewaartermijn beschikbaar');

    // 5. Contactprivacy/CSP: alle installaties houden formulierdata same-origin.
    // Externe tenant en standalone hebben voor contact geen externe provider
    // meer nodig; Open-Meteo blijft de enige functionele connect-src uitzondering.
    $siteConfigSrc = (string)file_get_contents($root . '/site-config.php');
    $contactRuntimeSrc = (string)file_get_contents($root . '/app/core/contact-inbox-runtime.php');
    c54(str_contains($siteConfigSrc, "if (PHP_SAPI !== 'cli' && !headers_sent())"), 'CSP wordt voor webresponses centraal gezet');
    c54(
        str_contains($siteConfigSrc, "default-src 'self'")
        && str_contains($siteConfigSrc, 'script-src \'self\' \'nonce-{$cspNonce}\'')
        && str_contains($siteConfigSrc, "script-src-attr 'none'")
        && !str_contains($siteConfigSrc, "script-src 'self' 'unsafe-inline'")
        && str_contains($siteConfigSrc, "object-src 'none'"),
        'centrale CSP bevat afdwingbare nonce-gebonden basis- en scriptgrenzen'
    );
    c54(str_contains($siteConfigSrc, '$formAction = "\'self\'"') && !str_contains($siteConfigSrc, 'formspree.io'), 'CSP houdt formulieracties voor alle installaties uitsluitend same-origin');
    c54(str_contains($siteConfigSrc, "'self' https://api.open-meteo.com") && !str_contains($siteConfigSrc, 'https://formspree.io'), 'connect-src bevat geen externe formulierprovider meer');
    c54(str_contains($contactRuntimeSrc, 'contact-ontvangst.php') && str_contains($contactRuntimeSrc, 'RuntimeException'), 'contactruntime forceert de gedeelde contactvorm fail-closed naar eigen endpoint');
} finally {
    wis54($tmp);
}

echo "Phase 5.4 security hardening: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
