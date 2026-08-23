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

function r54(array $argv, ?array $env = null): array
{
    $spec = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proces = proc_open($argv, $spec, $pipes, null, $env, ['bypass_shell' => true]);
    if (!is_resource($proces)) return [255, '', 'proc_open faalde'];
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    return [proc_close($proces), (string)$stdout, (string)$stderr];
}

function wis54(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $naam) {
        if ($naam === '.' || $naam === '..') continue;
        wis54($pad . DIRECTORY_SEPARATOR . $naam);
    }
    @rmdir($pad);
}

$tmp = sys_get_temp_dir() . '/rc045-phase54-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0750, true);

try {
    require_once $root . '/app/security/registration-rate-limit.php';

    $state = $tmp . '/rate/aanmelden-pogingen.json';
    $lock = $tmp . '/rate/.aanmelden-pogingen.lock';
    $resultaten = [];
    for ($i = 0; $i < 6; $i++) {
        $resultaten[] = registrationRateLimitToestaan($state, $lock, '198.51.100.10', 5, 3600, 1700000000 + $i);
    }
    c54($resultaten === [true,true,true,true,true,false], 'aanmeld-rate-limit staat vijf unieke pogingen toe en blokkeert de zesde');
    c54(registrationRateLimitToestaan($state, $lock, '198.51.100.11', 5, 3600, 1700000006) === true, 'rate-limit is per bron-IP gescheiden');
    $raw = is_file($state) ? (string)file_get_contents($state) : '';
    c54($raw !== '' && !str_contains($raw, '198.51.100.10'), 'rate-limit bewaart bron-IP niet in plaintext');

    $legacyState = $tmp . '/legacy/aanmelden-pogingen.php';
    $legacyLock = $tmp . '/legacy/.aanmelden-pogingen.lock';
    c54(registrationRateLimitToestaan($legacyState, $legacyLock, '203.0.113.9', 5, 3600, 1700000100), 'standalone legacy rate-limit blijft schrijfbaar');
    $legacyRaw = is_file($legacyState) ? (string)file_get_contents($legacyState) : '';
    c54(str_starts_with($legacyRaw, "<?php exit; ?>\n"), 'standalone rate-limitbestand behoudt PHP-guard');

    @mkdir($tmp . '/corrupt', 0750, true);
    file_put_contents($tmp . '/corrupt/state.json', '{geen geldige json');
    $gooide = false;
    try {
        registrationRateLimitToestaan($tmp . '/corrupt/state.json', $tmp . '/corrupt/.lock', '192.0.2.1', 5, 3600, 1700000200);
    } catch (Throwable $e) {
        $gooide = true;
    }
    c54($gooide, 'corrupte rate-limitstate faalt gesloten');

    $privateRoot = $tmp . '/tenant-private';
    @mkdir($privateRoot, 0750, true);
    $configPad = $tmp . '/tenant-config.php';
    $config = "<?php\nreturn [\n"
        . "  'vereniging'=>['sleutel'=>'audittenant','naam'=>'Auditvereniging','site_url'=>'https://audit.example'],\n"
        . "  'opslag'=>['private_root'=>" . var_export($privateRoot, true) . ",'private_driver'=>'json'],\n"
        . "];\n";
    file_put_contents($configPad, $config);

    $seoScript = 'require ' . var_export($root . '/app/content/seo-head.php', true)
        . '; ob_start(); rc045SeoHead("aanmelden"); $o=ob_get_clean(); echo $o;';
    [$seoCode, $seoOut, $seoErr] = r54([
        PHP_BINARY, '-r', $seoScript,
    ], [
        'VERENIGING_CONFIG_FILE' => $configPad,
        'VERENIGING_REQUIRE_TENANT_CONFIG' => '1',
    ]);
    c54($seoCode === 0, 'externe tenant SEO-head rendert zonder configuratiefout');
    c54(str_contains($seoOut, "connect-src 'self'; form-action 'self'"), 'externe aanmeldpagina blokkeert browserdata naar externe endpoints');
    c54(str_contains($seoOut, 'verenigingsplatform-aanmelden-same-origin'), 'externe aanmeldpagina markeert same-origin beleid');
    c54(str_contains($seoOut, "setAttribute('action','aanmelden-ontvangst.php')"), 'externe aanmeldpagina zet formulieraction op tenant-eigen endpoint');

    [$legacySeoCode, $legacySeoOut] = r54([PHP_BINARY, '-r', $seoScript], []);
    c54($legacySeoCode === 0 && !str_contains($legacySeoOut, 'verenigingsplatform-aanmelden-same-origin'), 'standalone RC045 behoudt voorlopig legacy aanmeldtransport');

    $auth = (string)file_get_contents($root . '/auth.php');
    c54(str_contains($auth, '$authTenantPrivate = !empty($authPaden[\'tenant_private\']);'), 'auth kent expliciete tenant-private veiligheidsmodus');
    c54(str_contains($auth, '$beheerLegacyOk = !$authTenantPrivate'), 'plaintext masterfallback is voor externe tenants uitgeschakeld');
    c54(str_contains($auth, 'if ($authTenantPrivate) return false;'), 'masterwachtwoordcontrole faalt extern dicht zonder hash');
    c54(str_contains($auth, "&& (bool)(\$gevondenGebruiker['actief'] ?? true)"), 'geblokkeerd account wordt vóór sessiecreatie geweigerd');
    c54(str_contains($auth, '$toegestaneTabs = $authTenantPrivate ? [] : array_keys($alleTabs);'), 'legacy ontbrekende tabs falen bij externe tenant dicht');

    $ontvangst = (string)file_get_contents($root . '/aanmelden-ontvangst.php');
    c54(str_contains($ontvangst, "$privateRoot.'/security'" ) === false, 'test bevat geen per ongeluk hardcoded tijdelijke private root in productiebron');
    c54(str_contains($ontvangst, "\$map=\$privateRoot.'/security'"), 'aanmeld-rate-limit gebruikt tenant-private securitymap');
    c54(!str_contains($ontvangst, '@file_put_contents($pogingenPad'), 'oude stil falende immutable-release rate-limitwrite is verwijderd');
    c54(str_contains($ontvangst, 'count($passend)===1?$passend[0]:null'), 'ontvangst kiest zonder clientveld alleen een uniek passend lidmaatschapstype');
} finally {
    wis54($tmp);
}

echo "RESULTAAT: {$ok} ok, {$fout} fout\n";
exit($fout === 0 ? 0 : 1);
