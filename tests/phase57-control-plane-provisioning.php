<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function c57(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function rm57(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $naam) {
        if ($naam === '.' || $naam === '..') continue;
        rm57($pad . '/' . $naam);
    }
    @rmdir($pad);
}

$tmp = sys_get_temp_dir() . '/rc045-phase57-' . bin2hex(random_bytes(5));
$state = $tmp . '/state';
$tenants = $tmp . '/tenants';
foreach ([$state . '/pending', $state . '/processing', $state . '/results', $state . '/sessions', $tenants] as $dir) @mkdir($dir, 0770, true);

try {
    $cfg = [
        'schema'=>1,
        'phase'=>'5.1-runtime',
        'host'=>'beheer.platform.example',
        'app_root'=>$root,
        'tenants_root'=>$tenants,
        'runtime_user'=>get_current_user() ?: 'runner',
        'pending_dir'=>$state . '/pending',
        'processing_dir'=>$state . '/processing',
        'results_dir'=>$state . '/results',
        'sessions_dir'=>$state . '/sessions',
        'snapshot_file'=>$state . '/snapshot.json',
        'executor_lock'=>$state . '/executor.lock',
        'audit_file'=>$state . '/audit.jsonl',
        'lifecycle_apply'=>$root . '/bin/apply-vps-lifecycle.php',
    ];
    file_put_contents($tmp . '/runtime.json', json_encode($cfg));
    file_put_contents($state . '/snapshot.json', json_encode([
        'schema'=>1,
        'phase'=>'5.1-snapshot',
        'generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
        'tenants'=>[],
    ]));
    putenv('VP_CONTROL_PLANE_CONFIG=' . $tmp . '/runtime.json');
    $_SERVER['REMOTE_USER'] = 'operator.test';
    require_once $root . '/app/control-plane/control-plane-runtime.php';

    $id = cp57ProvisionRequest([
        'tenant_key'=>'nieuwe-club',
        'name'=>'Nieuwe Club Zuid-Limburg',
        'host'=>'club.example.nl',
        'modules'=>['website','ledenadministratie','evenementen','aanmelden'],
    ]);
    $request = json_decode((string)file_get_contents($state . '/pending/' . $id . '.json'), true);
    c57(preg_match('/^[0-9a-f]{32}$/D', $id) === 1, 'provisioning gebruikt een random request-id');
    c57(($request['action'] ?? '') === 'provision' && ($request['tenant_key'] ?? '') === 'nieuwe-club', 'queue bevat uitsluitend de vaste provisioningactie en canonieke tenant-key');
    c57(($request['provision']['name'] ?? '') === 'Nieuwe Club Zuid-Limburg' && ($request['provision']['host'] ?? '') === 'club.example.nl', 'naam en productiehost worden geschematiseerd opgeslagen');
    c57(($request['provision']['modules'] ?? []) === ['website','ledenadministratie','evenementen','aanmelden'], 'modulekeuze wordt in vaste platformvolgorde opgeslagen');
    c57(!isset($request['password']) && !isset($request['secret']) && !isset($request['command']) && !isset($request['argv']) && !isset($request['root']), 'webqueue bevat geen secret, commando, argv of serverpad');

    $ongeldig = false;
    try { cp57ProvisionRequest(['tenant_key'=>'Foute-Key','name'=>'Club','host'=>'fout.example.nl','modules'=>['website']]); }
    catch (Throwable $e) { $ongeldig = true; }
    c57($ongeldig, 'niet-canonieke tenant-key wordt in de weblaag geweigerd');

    $ongeldig = false;
    try { cp57ProvisionRequest(['tenant_key'=>'foute--key','name'=>'Club','host'=>'fout.example.nl','modules'=>['website']]); }
    catch (Throwable $e) { $ongeldig = true; }
    c57($ongeldig, 'dubbele koppeltekens in tenant-key worden geweigerd');

    $ongeldig = false;
    try { cp57ProvisionRequest(['tenant_key'=>'goede-key','name'=>'Club','host'=>'https://fout.example.nl','modules'=>['website']]); }
    catch (Throwable $e) { $ongeldig = true; }
    c57($ongeldig, 'hostveld accepteert geen scheme of URL-pad');

    $ongeldig = false;
    try { cp57ProvisionRequest(['tenant_key'=>'goede-key','name'=>'Club','host'=>'fout.example.nl','modules'=>['website','shell']]); }
    catch (Throwable $e) { $ongeldig = true; }
    c57($ongeldig, 'onbekende module wordt fail-closed geweigerd');

    $snapshot = [
        'schema'=>1,
        'phase'=>'5.1-snapshot',
        'generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
        'tenants'=>[[
            'tenant_key'=>'bestaand',
            'canonical_host'=>'bestaand.example.nl',
            'status'=>'setup_required',
            'transition'=>null,
            'healthy'=>false,
            'updated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
            'last_export'=>null,
            'delete_export'=>null,
            'purge_not_before_utc'=>null,
        ]],
    ];
    file_put_contents($state . '/snapshot.json', json_encode($snapshot));
    $dubbel = false;
    try { cp57ProvisionRequest(['tenant_key'=>'bestaand','name'=>'Dubbel','host'=>'nieuw.example.nl','modules'=>['website']]); }
    catch (Throwable $e) { $dubbel = true; }
    c57($dubbel, 'bestaande tenant-key wordt vóór queuewrite geweigerd');
    $dubbel = false;
    try { cp57ProvisionRequest(['tenant_key'=>'andere-club','name'=>'Dubbel','host'=>'bestaand.example.nl','modules'=>['website']]); }
    catch (Throwable $e) { $dubbel = true; }
    c57($dubbel, 'bestaande domeinnaam wordt vóór queuewrite geweigerd');

    $resultId = str_repeat('a', 32);
    file_put_contents($state . '/results/' . $resultId . '.json', json_encode([
        'schema'=>1,
        'phase'=>'5.1-result',
        'request_id'=>$resultId,
        'tenant_key'=>'nieuwe-club',
        'action'=>'provision',
        'operator'=>'operator.test',
        'result'=>'ok',
        'message'=>'Basisprovisioning voltooid.',
        'completed_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),
    ]));
    c57((cp51RecentResult($resultId, 'operator.test')['action'] ?? '') === 'provision', 'resultaatlezer accepteert de nieuwe provisioningactie operatorgebonden');

    $web = (string)file_get_contents($root . '/app/control-plane/control-plane-runtime.php');
    $gui = (string)file_get_contents($root . '/app/control-plane-web/index.php');
    $exec = (string)file_get_contents($root . '/bin/control-plane-executor.php');
    c57(!str_contains($web, 'proc_open(') && !str_contains($web, 'shell_exec(') && !str_contains($web, 'system(') && !str_contains($web, 'exec('), 'provisioning voegt geen procesuitvoering toe aan de weblaag');
    c57(str_contains($web, 'function cp57ProvisionRequest') && str_contains($web, "'action'=>'provision'") && str_contains($web, "'confirm'=>[]"), 'weblaag schrijft een apart strikt provisioningschema');
    c57(str_contains($exec, 'function cpeProvisionPayload') && str_contains($exec, 'Queue bevat onbekende top-level velden.'), 'root-executor valideert provisioningpayload en weigert veldsmuggling');
    c57(str_contains($exec, "'--driver=pdo'") && str_contains($exec, "'--modules='.implode") && str_contains($exec, "'--root='.\$c['tenants_root']"), 'executor bepaalt storageprofiel, modules en tenantroot server-side');
    c57(str_contains($exec, 'cpeProvisionCommand($c,$r,true)') && str_contains($exec, "\$cmd[]='--dry-run'"), 'executor voert vóór de echte provisioning een dry-run uit');
    c57(str_contains($exec, '$php=PHP_BINARY') && str_contains($exec, 'exact gepinde productie-PHP-binary'), 'provisioner draait onder dezelfde gepinde PHP-binary als de root-executor');
    c57(str_contains($exec, 'cpeProvisionUniek') && str_contains($exec, 'Domeinnaam is al aanwezig in een tenantmanifest.'), 'executor controleert key- en hostuniciteit opnieuw vlak voor mutatie');
    c57(str_contains($exec, "'status'=>'setup_required'") && str_contains($exec, 'Basisprovisioning voltooid. Activeer nu de eerste beheerder'), 'basisgeprovisioneerde tenant blijft zichtbaar met duidelijke vervolgstap');
    c57(str_contains($gui, '+ Nieuwe vereniging') && str_contains($gui, 'name="action" value="provision"'), 'Platformbeheer bevat de Nieuwe vereniging actie en provisioningformulier');
    c57(str_contains($gui, 'name="tenant_key"') && str_contains($gui, 'name="host"') && str_contains($gui, 'name="modules[]"'), 'formulier bevat tenant-key, domein en modulekeuze');
    c57(!str_contains($gui, 'name="password"') && !str_contains($gui, 'name="secret"'), 'GUI vraagt geen beheerderswachtwoord via de control-plane queue');
    c57(!str_contains($gui, 'Control-plane · fase 5.1') && str_contains($gui, '<h1>Platformbeheer</h1>'), 'productie-UI gebruikt producttaal in plaats van fase-ontwikkeltaal');

} finally {
    rm57($tmp);
}

if ($fout > 0) {
    fwrite(STDERR, "\n{$fout} fase-5.7 test(s) mislukt; {$ok} geslaagd.\n");
    exit(1);
}
echo "\nALLE FASE-5.7 CONTROL-PLANE PROVISIONINGTESTS GESLAAGD ({$ok})\n";
