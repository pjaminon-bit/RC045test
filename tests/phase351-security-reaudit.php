<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check351(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

function rr351(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rr351($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function run351(array $args, ?string $stdin = null, ?array $env = null): array
{
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = proc_open($args, $desc, $pipes, null, $env, ['bypass_shell' => true]);
    if (!is_resource($proc)) return [255, 'proc_open mislukt'];
    if ($stdin !== null) fwrite($pipes[0], $stdin);
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($proc);
    return [$code, trim((string)$out . "\n" . (string)$err)];
}

function env351(string $config, string $private): array
{
    return [
        'VERENIGING_REQUIRE_TENANT_CONFIG' => '1',
        'VERENIGING_CONFIG_FILE' => $config,
        'VERENIGING_PRIVATE_ROOT' => $private,
    ];
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-phase351-' . bin2hex(random_bytes(5));
$base = $tmp . '/tenants';
@mkdir($base, 0750, true);

try {
    require_once $root . '/app/core/tenant-runtime.php';
    if (DIRECTORY_SEPARATOR === '/') {
        check351(tenantRuntimeIsAbsoluutPad('/srv/verenigingen') === true, 'POSIX absoluut pad vanaf / blijft geldig');
        check351(tenantRuntimeIsAbsoluutPad('\\srv\\verenigingen') === false, 'POSIX backslash-pad wordt niet langer als absoluut behandeld');
        check351(tenantRuntimeIsAbsoluutPad('C:\\srv\\verenigingen') === false, 'Windows drivepad wordt op POSIX niet als lokale absolute tenantgrens geaccepteerd');
    } else {
        check351(tenantRuntimeIsAbsoluutPad('C:\\srv\\verenigingen') === true, 'Windows drive-root wordt als absoluut behandeld');
        check351(tenantRuntimeIsAbsoluutPad('\\\\server\\share\\club') === true, 'Windows UNC-pad wordt als absoluut behandeld');
        check351(tenantRuntimeIsAbsoluutPad('\\club') === false, 'Windows drive-relatief backslash-pad wordt geweigerd');
    }

    $provision = $root . '/bin/provision-tenant.php';
    [$pc, $po] = run351([
        PHP_BINARY, $provision,
        '--key=reaudit-club',
        '--name=Reaudit Club',
        '--url=https://reaudit.example',
        '--root=' . $base,
        '--modules=website,ledenadministratie',
    ]);
    $tenant = $base . '/reaudit-club';
    $config = $tenant . '/config.php';
    $private = $tenant . '/private';
    check351($pc === 0 && is_file($config), 'audittenant wordt via echte provisioner aangemaakt');

    if (DIRECTORY_SEPARATOR === '/') {
        [$badRootCode, $badRootOut] = run351([
            PHP_BINARY, $provision,
            '--key=backslash-root',
            '--name=Backslash Root',
            '--url=https://backslash.example',
            '--root=\\tmp\\verenigingen',
            '--dry-run',
        ]);
        check351($badRootCode !== 0 && str_contains($badRootOut, 'absoluut pad'), 'provisioner weigert backslash-schijnroot op Linux');
    } else {
        check351(true, 'POSIX backslash-root aanvalstest niet van toepassing op Windows');
    }

    $bootstrap = $root . '/bin/bootstrap-tenant-admin.php';
    [$bc, $bo] = run351([
        PHP_BINARY, $bootstrap,
        '--config=' . $config,
        '--password-stdin',
    ], "Reaudit-Admin-Eerste-2026!\n");
    check351($bc === 0, 'eerste mastercredential wordt aangemaakt');

    $sessionMap = $private . '/sessions';
    file_put_contents($sessionMap . '/sess_master-canary', 'master');
    file_put_contents($sessionMap . '/sess_user-canary', 'user');
    check351(count(array_diff(scandir($sessionMap) ?: [], ['.','..'])) === 2, 'canary beheer- en gebruikerssessies bestaan vóór rotatie');

    [$rotateCode, $rotateOut] = run351([
        PHP_BINARY, $bootstrap,
        '--config=' . $config,
        '--password-stdin',
        '--rotate',
    ], "Reaudit-Admin-Tweede-2026!\n");
    $sessionOver = array_values(array_diff(scandir($sessionMap) ?: [], ['.','..']));
    check351($rotateCode === 0 && str_contains($rotateOut, 'Ingetrokken tenant-sessies: 2'), 'masterrotatie rapporteert ingetrokken bestaande tenant-sessies');
    check351($sessionOver === [], 'masterrotatie laat geen bestaande master- of gebruikerssessie doorlopen');

    $users = $private . '/auth/users.json';
    file_put_contents($users, json_encode([
        ['gebruikersnaam'=>'alice','hash'=>'x','sessie_versie'=>9,'actief'=>true],
    ], JSON_PRETTY_PRINT));
    @chmod($users, 0640);

    $worker = $tmp . '/backup-worker.php';
    file_put_contents($worker, <<<'PHP'
<?php
$root=$argv[1];$actie=$argv[2];require $root.'/app/storage/tenant-backup-store.php';
if($actie==='maak'){
  $data=[
    ['gebruikersnaam'=>'alice','hash'=>'oud-a','sessie_versie'=>2,'actief'=>true],
    ['gebruikersnaam'=>'bob','hash'=>'oud-b','sessie_versie'=>4,'actief'=>true],
  ];
  $p=tenantBackupMaakArray('auth-gebruikers',$data);echo json_encode(['name'=>$p?basename($p):null]);
}elseif($actie==='lees'){
  $f=null;$d=tenantBackupLeesArray('auth-gebruikers',$argv[3],$f);echo json_encode(['data'=>$d,'error'=>$f]);
}
PHP);
    [$mkCode, $mkRaw] = run351([PHP_BINARY,$worker,$root,'maak'], null, env351($config,$private));
    $mk = json_decode($mkRaw, true);
    $backupName = is_array($mk) ? (string)($mk['name'] ?? '') : '';
    check351($mkCode === 0 && $backupName !== '', 'gebruikerssnapshot met oude sessieversies wordt gemaakt');

    [$rdCode, $rdRaw] = run351([PHP_BINARY,$worker,$root,'lees',$backupName], null, env351($config,$private));
    $rd = json_decode($rdRaw, true);
    $restored = is_array($rd) && is_array($rd['data'] ?? null) ? $rd['data'] : [];
    $versieAlice = (int)($restored[0]['sessie_versie'] ?? 0);
    $versieBob = (int)($restored[1]['sessie_versie'] ?? 0);
    check351($rdCode === 0 && $versieAlice === 10, 'gebruikersrestore zet sessieversie boven actuele versie en kan ingetrokken Alice-sessie niet herleven');
    check351($versieBob === 5, 'ook alleen-in-snapshot gebruiker krijgt een nieuwe hogere sessieversie');

    $ht = (string)file_get_contents($root . '/.htaccess');
    check351(!str_contains($ht, '%{HTTP_HOST}') && !str_contains($ht, 'https://%1%{REQUEST_URI}'), 'gedeelde .htaccess reflecteert geen Host-header in redirects');
    check351(str_contains($ht, '\\.github|\\.git'), '.github en .git zijn beide expliciet uit HTTP-oppervlak verwijderd');

    $auth = (string)file_get_contents($root . '/auth.php');
    check351(!str_contains($auth, 'microtime(true) - floor(microtime(true))'), 'authbackups gebruiken geen twee afzonderlijke microtime-calls meer');
    check351(str_contains($auth, '$nu = microtime(true);') && str_contains($auth, "date('Y-m-d_His', \$seconde)"), 'authbackuptijdstempel gebruikt één consistente tijdmeting');

    $bootstrapSrc = (string)file_get_contents($bootstrap);
    check351(!str_contains($bootstrapSrc, 'microtime(true) - floor(microtime(true))') && str_contains($bootstrapSrc, 'bootstrap34MicroTijd()'), 'masterbackup gebruikt secondegrens-veilige tijdstempelhelper');
    check351(str_contains($bootstrapSrc, 'bootstrap34TrekSessiesIn') && str_contains($bootstrapSrc, 'Sessies eerst weg'), 'masterrotatie bevat expliciete sessie-intrekking vóór credentialwrite');

    $backupUi = (string)file_get_contents($root . '/beheer/backups.php');
    check351(!str_contains($backupUi, 'cryptografisch aan tenant + onderdeel gebonden'), 'backup-UI claimt niet langer onterecht cryptografische ondertekening');
    check351(str_contains($backupUi, 'geen cryptografische ondertekening'), 'backup-UI beschrijft tenantbinding technisch correct');

    $deploySrc = (string)file_get_contents($root . '/bin/prepare-vps-deployment.php');
    check351(str_contains($deploySrc, 'default_vhost_must_reject') && str_contains($deploySrc, 'reject_unknown_hosts'), 'VPS-contract vereist catch-all onbekende-hostafwijzing');
    check351(str_contains($deploySrc, 'redirect_must_not_use_request_host') && str_contains($deploySrc, 'http_redirect_target'), 'VPS-contract gebruikt vaste canonical-host redirects zonder request Host');
} finally {
    rr351($tmp);
}

echo "Phase 3.5.1 security reaudit: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
