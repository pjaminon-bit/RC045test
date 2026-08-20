<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function check321path(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
    } else {
        $fout++;
        fwrite(STDERR, "FOUT: {$label}\n");
    }
}

function rrmdir321path(string $pad): void
{
    if (is_link($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) { if (is_file($pad)) @unlink($pad); return; }
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        rrmdir321path($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function run321path(string $script, string $key, string $base, bool $dryRun = false, bool $force = false): array
{
    $cmd = escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg($script)
        . ' --key=' . escapeshellarg($key)
        . ' --name=' . escapeshellarg('Path Security Test')
        . ' --url=' . escapeshellarg('https://path-security.example')
        . ' --root=' . escapeshellarg($base);
    if ($dryRun) $cmd .= ' --dry-run';
    if ($force) $cmd .= ' --force';
    $out = [];
    exec($cmd . ' 2>&1', $out, $code);
    return [$code, implode("\n", $out)];
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-phase321-path-' . bin2hex(random_bytes(4));
@mkdir($tmp, 0750, true);
$script = $root . '/bin/provision-tenant.php';

try {
    // Baseline: een volledig nog niet bestaande, gewone absolute root moet
    // veilig kunnen worden opgebouwd en daarna idempotent blijven.
    $normaleBase = $tmp . '/nieuw/diep/tenants';
    [$codeNormaal] = run321path($script, 'normaal', $normaleBase);
    check321path($codeNormaal === 0 && is_file($normaleBase . '/normaal/config.php'), 'canonieke niet-bestaande tenantroot wordt veilig aangemaakt');
    [$codeNormaal2, $outNormaal2] = run321path($script, 'normaal', $normaleBase);
    check321path($codeNormaal2 === 0 && str_contains($outNormaal2, 'ONGEWIJZIGD'), 'path-hardening behoudt idempotente tweede provisioningrun');

    // Padtraversal/ambiguïteit: niet normaliseren maar expliciet weigeren.
    $dotdotBase = $tmp . '/dotdot/../escape';
    [$codeDotdot, $outDotdot] = run321path($script, 'escape', $dotdotBase, true);
    check321path($codeDotdot !== 0 && str_contains($outDotdot, 'geen . of ..'), '.. segment in --root wordt fail-closed geweigerd');
    check321path(!is_dir($tmp . '/escape/escape'), 'geweigerde .. root schrijft niets buiten bedoelde padstructuur');

    $dotBase = $tmp . '/dot/./tenants';
    [$codeDot] = run321path($script, 'dot', $dotBase, true);
    check321path($codeDot !== 0, '. segment in --root wordt geweigerd');

    // Root als symlink naar een gewone externe directory: ook dit wordt bewust
    // geweigerd, zodat filesystemindirectie nooit onderdeel van provisioning is.
    $safeTarget = $tmp . '/safe-target';
    @mkdir($safeTarget, 0750, true);
    $rootLink = $tmp . '/root-link';
    $symlinkOk = @symlink($safeTarget, $rootLink);
    check321path($symlinkOk && is_link($rootLink), 'testomgeving kan root-symlink aanmaken');
    [$codeRootLink, $outRootLink] = run321path($script, 'via-link', $rootLink, true);
    check321path($codeRootLink !== 0 && stripos($outRootLink, 'symlink') !== false, 'symlink als --root wordt geweigerd');

    // Nog belangrijker: een symlink die fysiek naar de applicatieroot wijst mag
    // zelfs in dry-run nooit door de preflight komen.
    $projectLink = $tmp . '/project-link';
    $projectLinkOk = @symlink($root, $projectLink);
    check321path($projectLinkOk && is_link($projectLink), 'testomgeving kan projectroot-symlink aanmaken');
    [$codeProjectLink] = run321path($script, 'zou-in-code-landen', $projectLink, true);
    check321path($codeProjectLink !== 0, 'symlink-bypass richting applicatieroot wordt geblokkeerd');

    // Symlink hoeft niet het laatste component te zijn: ook een bestaande
    // ancestor midden in een verder nog niet bestaand pad moet worden gevonden.
    $nestedBase = $tmp . '/nested';
    @mkdir($nestedBase, 0750, true);
    $nestedTarget = $tmp . '/nested-target';
    @mkdir($nestedTarget, 0750, true);
    @symlink($nestedTarget, $nestedBase . '/link');
    [$codeNested] = run321path($script, 'nested', $nestedBase . '/link/nieuwe-root', true);
    check321path($codeNested !== 0, 'symlink in bestaande ancestor van nog niet bestaande root wordt geweigerd');

    // Ook de afgeleide tenantmap zelf mag niet vooraf als symlink bestaan.
    $tenantBase = $tmp . '/tenant-base';
    @mkdir($tenantBase, 0750, true);
    $tenantTarget = $tmp . '/tenant-target';
    @mkdir($tenantTarget, 0750, true);
    @symlink($tenantTarget, $tenantBase . '/tenant-link');
    [$codeTenantLink] = run321path($script, 'tenant-link', $tenantBase, true);
    check321path($codeTenantLink !== 0, 'vooraf bestaande symlink op tenantroot wordt geweigerd');

    // Broken symlinks zijn eveneens onveilig: file_exists() alleen zou die als
    // niet-bestaand kunnen behandelen.
    $brokenRoot = $tmp . '/broken-root';
    @symlink($tmp . '/bestaat-niet', $brokenRoot);
    [$codeBroken] = run321path($script, 'broken', $brokenRoot, true);
    check321path($codeBroken !== 0, 'broken symlink in provisioningroot wordt geweigerd');

    // Interne symlink onder een gewone tenantroot mag evenmin worden gevolgd.
    $internBase = $tmp . '/intern-base';
    $internTenant = $internBase . '/intern';
    @mkdir($internTenant, 0750, true);
    $internTarget = $tmp . '/intern-target';
    @mkdir($internTarget, 0750, true);
    @symlink($internTarget, $internTenant . '/private');
    [$codeIntern] = run321path($script, 'intern', $internBase, true);
    check321path($codeIntern !== 0, 'symlink in afgeleid private tenantpad wordt vóór gebruik geweigerd');

    // Een symlink op een te schrijven bestand mag nooit gevolgd of via --force
    // als legitiem configbestand behandeld worden. Het externe canarybestand
    // moet volledig ongewijzigd blijven.
    $fileBase = $tmp . '/file-base';
    $fileTenant = $fileBase . '/file-link';
    @mkdir($fileTenant, 0750, true);
    $canary = $tmp . '/gevoelig-canary.txt';
    file_put_contents($canary, 'NIET-WIJZIGEN');
    @symlink($canary, $fileTenant . '/config.php');
    [$codeFileLink] = run321path($script, 'file-link', $fileBase, false, true);
    check321path($codeFileLink !== 0, 'symlink op config.php wordt ook met --force geweigerd');
    check321path((string)file_get_contents($canary) === 'NIET-WIJZIGEN', 'extern symlinkdoel blijft byte-inhoudelijk ongemoeid');

} finally {
    rrmdir321path($tmp);
}

echo "Phase 3.2.1 provisioner path security: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
