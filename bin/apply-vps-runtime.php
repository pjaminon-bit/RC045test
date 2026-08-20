<?php
// ============================================================
// Fase 4.1 — valideer/toepassen Linux tenant-runtime
// ============================================================
// --check is root-vrij en controleert de volledige runtimebundle opnieuw.
// --apply vereist Linux root en voert uitsluitend het gevalideerde plan uit.
// De tool reloadt PHP-FPM bewust niet: eerst moet de beheerder de complete
// serverconfiguratie testen; reload blijft een expliciete operationele stap.
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/runtime-contract.php';

function apply41Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function apply41Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/apply-vps-runtime.php --plan=/srv/verenigingen/club/runtime/runtime-plan.json --check\n";
    echo "  sudo php bin/apply-vps-runtime.php --plan=... --apply --fpm-pool-dir=/etc/php/8.3/fpm/pool.d\n\n";
    echo "Opties:\n";
    echo "  --check               valideer plan + gegenereerde FPM-config, wijzig niets\n";
    echo "  --apply               voer Linux user/group, ownership, modes en FPM-config uit\n";
    echo "  --fpm-pool-dir=PAD    verplicht bij --apply; bestaande servermap voor poolconfigs\n";
    echo "  --force               vervang afwijkende bestaande FPM-poolconfig na validatie\n";
    echo "  --help                toon deze hulp\n\n";
    echo "--check en --apply zijn wederzijds uitsluitend. De tool reloadt PHP-FPM niet automatisch.\n";
    echo "Bij een herhaalde --apply moet de tenant-PHP-FPM pool eerst gestopt zijn.\n";
}

function apply41Bundle(string $planPad): array
{
    try {
        $context = runtime41PlanLeesEnValideer($planPad);
        $plan = $context['plan'];
        $fpmPad = (string)$plan['bundle']['php_fpm_file'];
        $fpmPadReal = runtime41BestaandPad($fpmPad, 'Gegenereerde PHP-FPM poolconfig');
        if (runtime41NormPad(dirname($fpmPadReal)) !== runtime41NormPad($plan['bundle']['output_dir'])) {
            throw new RuntimeException('PHP-FPM poolconfig staat niet in de gebonden runtime outputmap.');
        }
        $huidig = (string)file_get_contents($fpmPadReal);
        $verwacht = runtime41FpmConfig($plan);
        if (!hash_equals(hash('sha256', $verwacht), hash('sha256', $huidig))) {
            throw new RuntimeException('Gegenereerde PHP-FPM poolconfig wijkt af van runtime-plan.json.');
        }
        return $context;
    } catch (Throwable $e) {
        apply41Stop($e->getMessage());
    }
}

function apply41Run(array $cmd): array
{
    $desc = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $proc = @proc_open($cmd, $desc, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($proc)) return [255, '', 'proces kon niet worden gestart'];
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    $code = proc_close($proc);
    return [$code, trim((string)$out), trim((string)$err)];
}

function apply41Getent(string $database, string $sleutel): ?array
{
    [$code, $out, $err] = apply41Run(['getent', $database, $sleutel]);
    if ($code === 2) return null;
    if ($code !== 0 || $out === '') apply41Stop("getent {$database} mislukt: " . ($err !== '' ? $err : 'onbekende fout'));
    return explode(':', strtok($out, "\n"));
}

function apply41GetentAlle(string $database): array
{
    [$code, $out, $err] = apply41Run(['getent', $database]);
    if ($code !== 0 || $out === '') {
        apply41Stop("getent {$database} kon niet volledig worden uitgelezen: " . ($err !== '' ? $err : 'onbekende fout'));
    }
    $records = [];
    foreach (preg_split('/\r?\n/', trim($out)) ?: [] as $regel) {
        if ($regel === '') continue;
        $record = explode(':', $regel);
        if (count($record) < 4) apply41Stop("getent {$database} bevat een onvolledig record.");
        $records[] = $record;
    }
    if ($records === []) apply41Stop("getent {$database} leverde geen controleerbare records op.");
    return $records;
}

function apply41EnsureGroup(string $groep): int
{
    $record = apply41Getent('group', $groep);
    if ($record === null) {
        [$code,, $err] = apply41Run(['groupadd', '--system', $groep]);
        if ($code !== 0) apply41Stop('System group kon niet worden aangemaakt: ' . $err);
        $record = apply41Getent('group', $groep);
    }
    $gid = isset($record[2]) && ctype_digit((string)$record[2]) ? (int)$record[2] : -1;
    if ($gid < 0) apply41Stop('System group heeft geen geldige GID.');
    if (trim((string)($record[3] ?? '')) !== '') {
        apply41Stop('Tenant system group mag geen expliciete groepsleden bevatten.');
    }
    return $gid;
}

function apply41ControleerGroepExclusief(string $groep, int $gid, string $tenantUser): void
{
    foreach (apply41GetentAlle('group') as $record) {
        $naam = (string)($record[0] ?? '');
        $recordGid = isset($record[2]) && ctype_digit((string)$record[2]) ? (int)$record[2] : -1;
        if ($recordGid === $gid && !hash_equals($groep, $naam)) {
            apply41Stop('Tenant-GID wordt ook door een andere groepsnaam gebruikt; isolatie kan niet worden bewezen.');
        }
    }
    foreach (apply41GetentAlle('passwd') as $record) {
        $naam = (string)($record[0] ?? '');
        $primaryGid = isset($record[3]) && ctype_digit((string)$record[3]) ? (int)$record[3] : -1;
        if ($primaryGid === $gid && !hash_equals($tenantUser, $naam)) {
            apply41Stop('Tenant-GID is primary group van een andere account; tenantdata zou groep-leesbaar zijn.');
        }
    }
}

function apply41EnsureUser(array $os, int $gid): int
{
    $user = (string)$os['user'];
    $record = apply41Getent('passwd', $user);
    if ($record === null) {
        [$code,, $err] = apply41Run([
            'useradd', '--system', '--gid', (string)$os['group'], '--home-dir', (string)$os['home'],
            '--shell', (string)$os['shell'], '--no-create-home', $user,
        ]);
        if ($code !== 0) apply41Stop('System user kon niet worden aangemaakt: ' . $err);
        $record = apply41Getent('passwd', $user);
    }
    if (!is_array($record) || count($record) < 7) apply41Stop('System user record is onvolledig.');
    $uid = ctype_digit((string)$record[2]) ? (int)$record[2] : -1;
    $primaryGid = ctype_digit((string)$record[3]) ? (int)$record[3] : -1;
    if ($uid < 0 || $primaryGid !== $gid) apply41Stop('Bestaande system user heeft niet de verwachte unieke primary group.');
    if (!hash_equals((string)$os['home'], (string)$record[5]) || !hash_equals((string)$os['shell'], (string)$record[6])) {
        apply41Stop('Bestaande system user heeft afwijkende home of login shell.');
    }
    [$idCode, $idOut, $idErr] = apply41Run(['id', '-G', $user]);
    if ($idCode !== 0) apply41Stop('Supplementary groups konden niet worden gecontroleerd: ' . $idErr);
    $groepen = array_values(array_filter(preg_split('/\s+/', trim($idOut)) ?: [], static fn($v) => $v !== ''));
    if ($groepen !== [(string)$gid]) apply41Stop('Tenant-runtimeuser mag geen supplementary groups hebben.');
    return $uid;
}

function apply41ControleerUidExclusief(string $tenantUser, int $uid): void
{
    foreach (apply41GetentAlle('passwd') as $record) {
        $naam = (string)($record[0] ?? '');
        $recordUid = isset($record[2]) && ctype_digit((string)$record[2]) ? (int)$record[2] : -1;
        if ($recordUid === $uid && !hash_equals($tenantUser, $naam)) {
            apply41Stop('Tenant-UID wordt ook door een andere account gebruikt; filesystemisolatie kan niet worden bewezen.');
        }
    }
}

function apply41RuntimeMoetInactiefZijn(string $tenantUser): void
{
    [$code, $out, $err] = apply41Run(['pgrep', '-u', $tenantUser]);
    if ($code === 1) return;
    if ($code === 0 && $out !== '') {
        apply41Stop('Tenant-runtimeuser heeft actieve processen. Stop eerst de tenant-PHP-FPM pool en voer --apply daarna opnieuw uit.');
    }
    apply41Stop('Actieve tenantprocessen konden niet fail-closed worden gecontroleerd: ' . ($err !== '' ? $err : 'pgrep ontbreekt of gaf een onverwachte status'));
}

function apply41SymlinksVerboden(string $root): void
{
    if (is_link($root)) apply41Stop("Symlink in tenantboom geweigerd: {$root}");
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $info) {
        $pad = $info->getPathname();
        if (is_link($pad)) apply41Stop("Symlink in tenantboom geweigerd: {$pad}");
    }
}

function apply41ChownMode(string $pad, string $owner, string $groep, int $mode): void
{
    if (is_link($pad) || (!is_file($pad) && !is_dir($pad))) apply41Stop("Onveilig filesystemdoel: {$pad}");
    if (!@chown($pad, $owner)) apply41Stop("Owner kon niet worden gezet op {$pad}");
    if (!@chgrp($pad, $groep)) apply41Stop("Group kon niet worden gezet op {$pad}");
    if (!@chmod($pad, $mode)) apply41Stop("Mode kon niet worden gezet op {$pad}");
}

function apply41PrivateRechten(array $plan): void
{
    $fs = $plan['filesystem'];
    $private = (string)$fs['private_root']['path'];
    $owner = (string)$fs['private_root']['owner'];
    $groep = (string)$fs['private_root']['group'];
    $dirMode = octdec((string)$fs['private_root']['directory_mode']);
    $fileMode = octdec((string)$fs['private_root']['file_mode']);

    $tmp = (string)$fs['tmp']['path'];
    if (!is_dir($tmp)) {
        if (!@mkdir($tmp, 0700) && !is_dir($tmp)) apply41Stop('Tenant upload_tmp_dir kon niet worden aangemaakt.');
    }
    apply41SymlinksVerboden($private);
    apply41ChownMode($private, $owner, $groep, $dirMode);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($private, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $info) {
        $pad = $info->getPathname();
        apply41ChownMode($pad, $owner, $groep, $info->isDir() ? $dirMode : $fileMode);
    }

    foreach (['sessions', 'tmp'] as $special) {
        $spec = $fs[$special];
        $map = (string)$spec['path'];
        apply41ChownMode($map, $owner, $groep, octdec((string)$spec['directory_mode']));
        foreach (scandir($map) ?: [] as $item) {
            if ($item === '.' || $item === '..') continue;
            $pad = $map . '/' . $item;
            if (is_link($pad) || is_dir($pad)) apply41Stop("Onverwacht object in afgeschermde {$special}-map: {$pad}");
            apply41ChownMode($pad, $owner, $groep, octdec((string)$spec['file_mode']));
        }
    }
}

function apply41MetadataRechten(array $plan): void
{
    $fs = $plan['filesystem'];
    apply41ChownMode(
        (string)$fs['tenant_root']['path'],
        (string)$fs['tenant_root']['owner'],
        (string)$fs['tenant_root']['group'],
        octdec((string)$fs['tenant_root']['mode'])
    );
    foreach ($fs['metadata_files'] as $pad) {
        apply41ChownMode((string)$pad, (string)$fs['metadata_owner'], (string)$fs['metadata_group'], octdec((string)$fs['metadata_mode']));
    }
    $bundleDir = (string)$plan['bundle']['output_dir'];
    apply41ChownMode($bundleDir, 'root', (string)$fs['metadata_group'], 0750);
    apply41ChownMode((string)$plan['bundle']['plan_file'], 'root', (string)$fs['metadata_group'], 0640);
    apply41ChownMode((string)$plan['bundle']['php_fpm_file'], 'root', (string)$fs['metadata_group'], 0640);
}

function apply41SharedCodeControle(array $plan, int $uid, int $gid): void
{
    $root = (string)$plan['filesystem']['shared_code']['real_path'];
    $controle = function(string $pad) use ($uid, $gid): void {
        if (is_link($pad)) apply41Stop("Symlink in immutable gedeelde release geweigerd: {$pad}");
        $stat = @lstat($pad);
        if (!is_array($stat)) apply41Stop("Shared-code metadata onleesbaar: {$pad}");
        $mode = (int)$stat['mode'] & 0777;
        if (($mode & 0002) !== 0) apply41Stop("Shared code is world-writable: {$pad}");
        if ((int)$stat['uid'] === $uid && ($mode & 0200) !== 0) apply41Stop("Tenantuser bezit schrijfbare shared code: {$pad}");
        if ((int)$stat['gid'] === $gid && ($mode & 0020) !== 0) apply41Stop("Tenantgroup kan shared code schrijven: {$pad}");
    };
    $controle($root);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $info) $controle($info->getPathname());
}

function apply41FpmInstall(array $plan, string $fpmPoolDir, bool $force): string
{
    try {
        $dir = runtime41BestaandPad($fpmPoolDir, 'PHP-FPM pool.d map', true);
    } catch (Throwable $e) {
        apply41Stop($e->getMessage());
    }
    $tenantRoot = (string)$plan['filesystem']['tenant_root']['path'];
    $appReal = (string)$plan['filesystem']['shared_code']['real_path'];
    if (runtime41Binnen($dir, $tenantRoot) || runtime41Binnen($dir, $appReal)) {
        apply41Stop('PHP-FPM pool.d map moet buiten tenantdata en gedeelde applicatiecode liggen.');
    }
    $doel = $dir . '/' . $plan['php_fpm']['pool_config_filename'];
    if (is_link($doel)) apply41Stop('Bestaande PHP-FPM poolconfig mag geen symlink zijn.');
    $inhoud = runtime41FpmConfig($plan);
    if (is_file($doel)) {
        $huidig = (string)file_get_contents($doel);
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) return 'ongewijzigd';
        if (!$force) apply41Stop('Afwijkende PHP-FPM poolconfig bestaat al; gebruik --force na controle.');
    } elseif (file_exists($doel)) {
        apply41Stop('PHP-FPM poolconfigdoel is geen regulier bestand.');
    }
    $tmp = $dir . '/.' . basename($doel) . '.tmp.' . bin2hex(random_bytes(8));
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) apply41Stop('PHP-FPM poolconfig kon niet tijdelijk worden geschreven.');
    @chown($tmp, 'root'); @chgrp($tmp, 'root'); @chmod($tmp, 0644);
    clearstatcache(true, $doel);
    if (is_link($doel)) { @unlink($tmp); apply41Stop('PHP-FPM poolconfig werd tijdens write een symlink.'); }
    if (!@rename($tmp, $doel)) { @unlink($tmp); apply41Stop('PHP-FPM poolconfig kon niet atomisch worden geplaatst.'); }
    @chown($doel, 'root'); @chgrp($doel, 'root'); @chmod($doel, 0644);
    return 'geschreven';
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key)(?:=|$)/i', (string)$arg) === 1) {
        apply41Stop('Secrets horen niet in fase-4.1 CLI-argumenten.');
    }
}
$opt = getopt('', ['plan:', 'check', 'apply', 'fpm-pool-dir::', 'force', 'help']);
if (isset($opt['help'])) { apply41Help(); exit(0); }
$planPad = trim((string)($opt['plan'] ?? ''));
if ($planPad === '') apply41Stop('--plan=/absoluut/pad/runtime-plan.json is verplicht.');
$check = isset($opt['check']);
$apply = isset($opt['apply']);
if ($check === $apply) apply41Stop('Kies exact één van --check of --apply.');

$context = apply41Bundle($planPad);
$plan = $context['plan'];

if ($check) {
    echo 'CHECK OK  tenant=' . $plan['tenant_key'] . ' user=' . $plan['os']['user'] . ' pool=' . $plan['php_fpm']['pool'] . "\n";
    exit(0);
}

if (PHP_OS_FAMILY !== 'Linux') apply41Stop('--apply is uitsluitend voor Linux bedoeld.');
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) apply41Stop('--apply vereist root (EUID 0).');
$fpmPoolDir = trim((string)($opt['fpm-pool-dir'] ?? ''));
if ($fpmPoolDir === '') apply41Stop('--fpm-pool-dir=/etc/.../pool.d is verplicht bij --apply.');

$tenantUser = (string)$plan['os']['user'];
$tenantGroup = (string)$plan['os']['group'];
$gid = apply41EnsureGroup($tenantGroup);
apply41ControleerGroepExclusief($tenantGroup, $gid, $tenantUser);
$uid = apply41EnsureUser($plan['os'], $gid);
apply41ControleerUidExclusief($tenantUser, $uid);
apply41RuntimeMoetInactiefZijn($tenantUser);
apply41SharedCodeControle($plan, $uid, $gid);
apply41MetadataRechten($plan);
apply41PrivateRechten($plan);
$statusFpm = apply41FpmInstall($plan, $fpmPoolDir, isset($opt['force']));

echo 'APPLY OK  tenant=' . $plan['tenant_key'] . ' user=' . $plan['os']['user'] . ' group=' . $plan['os']['group'] . "\n";
echo strtoupper($statusFpm) . '  ' . rtrim($fpmPoolDir, '/') . '/' . $plan['php_fpm']['pool_config_filename'] . "\n";
echo 'Volgende operationele stap: test de volledige PHP-FPM configuratie en reload de service pas daarna expliciet.' . "\n";