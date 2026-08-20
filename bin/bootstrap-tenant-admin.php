<?php
// ============================================================
// Fase 3.4 — veilige eerste tenantbeheerder / mastercredential
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/core/tenant-runtime.php';

function bootstrap34Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function bootstrap34Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/bootstrap-tenant-admin.php --config=/srv/verenigingen/club/config.php\n";
    echo "  secret-tool ... | php bin/bootstrap-tenant-admin.php --config=/srv/verenigingen/club/config.php --password-stdin\n\n";
    echo "Opties:\n";
    echo "  --config=PAD       absoluut pad naar de door de provisioner gemaakte tenantconfig\n";
    echo "  --password-stdin   lees exact één wachtwoordregel van STDIN; het wachtwoord staat nooit in argv\n";
    echo "  --rotate           vervang een bestaande masterhash gecontroleerd en maak eerst een backup\n";
    echo "  --help             toon deze hulp\n\n";
    echo "Zonder --password-stdin wordt het wachtwoord twee keer verborgen via een interactieve TTY gevraagd.\n";
}

function bootstrap34NormPad(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string)preg_replace('~/+~', '/', $pad);
    if ($pad !== '/') $pad = rtrim($pad, '/');
    if (DIRECTORY_SEPARATOR === '\\') $pad = strtolower($pad);
    return $pad;
}

function bootstrap34Binnen(string $pad, string $root): bool
{
    $pad = bootstrap34NormPad($pad);
    $root = bootstrap34NormPad($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function bootstrap34SymlinkInPad(string $pad): ?string
{
    $cursor = rtrim($pad, '/\\');
    if ($cursor === '') $cursor = DIRECTORY_SEPARATOR;
    while (true) {
        if (is_link($cursor)) return $cursor;
        $parent = dirname($cursor);
        if ($parent === $cursor) break;
        $cursor = $parent;
    }
    return null;
}

function bootstrap34BestaandVeiligPad(string $pad, string $label, bool $map = false): string
{
    if (!tenantRuntimeIsAbsoluutPad($pad)) bootstrap34Stop("{$label} moet een absoluut pad zijn.");
    $link = bootstrap34SymlinkInPad($pad);
    if ($link !== null) bootstrap34Stop("{$label} mag geen symlink bevatten: {$link}");
    $real = realpath($pad);
    if ($real === false) bootstrap34Stop("{$label} bestaat niet of kan niet fysiek worden opgelost.");
    if ($map ? !is_dir($real) : !is_file($real)) bootstrap34Stop("{$label} heeft niet het verwachte bestandstype.");
    return rtrim($real, '/\\');
}

function bootstrap34ParseArgs(array $argv): array
{
    $resultaat = ['config' => null, 'password_stdin' => false, 'rotate' => false, 'help' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help') { $resultaat['help'] = true; continue; }
        if ($arg === '--password-stdin') { $resultaat['password_stdin'] = true; continue; }
        if ($arg === '--rotate') { $resultaat['rotate'] = true; continue; }
        if (str_starts_with($arg, '--config=')) {
            if ($resultaat['config'] !== null) bootstrap34Stop('--config mag maar één keer worden opgegeven.');
            $resultaat['config'] = substr($arg, strlen('--config='));
            continue;
        }
        if (str_starts_with($arg, '--password') || str_starts_with($arg, '--hash')) {
            bootstrap34Stop('Wachtwoorden en hashes mogen nooit als CLI-argument worden meegegeven; gebruik verborgen invoer of --password-stdin.');
        }
        bootstrap34Stop("Onbekende optie: {$arg}");
    }
    return $resultaat;
}

function bootstrap34LeesStdin(): string
{
    $regel = fgets(STDIN, 4098);
    if ($regel === false) bootstrap34Stop('STDIN bevat geen wachtwoord.');
    if (!str_ends_with($regel, "\n") && !feof(STDIN)) bootstrap34Stop('Wachtwoordregel op STDIN is te lang.');
    return rtrim($regel, "\r\n");
}

function bootstrap34LeesVerborgen(string $prompt): string
{
    if (!function_exists('stream_isatty') || !stream_isatty(STDIN)) {
        bootstrap34Stop('Geen interactieve TTY beschikbaar; gebruik --password-stdin met een veilige secretbron.');
    }
    if (PHP_OS_FAMILY === 'Windows') {
        bootstrap34Stop('Verborgen TTY-invoer is op dit platform niet beschikbaar; gebruik --password-stdin.');
    }

    $status = [];
    exec('stty -g 2>/dev/null', $status, $code);
    $oudeStatus = trim((string)($status[0] ?? ''));
    if ($code !== 0 || $oudeStatus === '') bootstrap34Stop('Terminalstatus kon niet veilig worden gelezen.');

    fwrite(STDOUT, $prompt);
    exec('stty -echo 2>/dev/null', $uit, $echoCode);
    if ($echoCode !== 0) bootstrap34Stop('Terminalecho kon niet worden uitgeschakeld.');
    try {
        $regel = fgets(STDIN, 4098);
    } finally {
        exec('stty ' . escapeshellarg($oudeStatus) . ' 2>/dev/null');
        fwrite(STDOUT, "\n");
    }
    if ($regel === false) bootstrap34Stop('Wachtwoordinvoer is afgebroken.');
    if (!str_ends_with($regel, "\n") && !feof(STDIN)) bootstrap34Stop('Wachtwoord is te lang.');
    return rtrim($regel, "\r\n");
}

function bootstrap34ValideerWachtwoord(string $wachtwoord): void
{
    $lengte = strlen($wachtwoord);
    if ($lengte < 14) bootstrap34Stop('Wachtwoord moet minimaal 14 tekens lang zijn.');
    if ($lengte > 256) bootstrap34Stop('Wachtwoord mag maximaal 256 bytes lang zijn.');
    if (str_contains($wachtwoord, "\0") || str_contains($wachtwoord, "\r") || str_contains($wachtwoord, "\n")) {
        bootstrap34Stop('Wachtwoord bevat een ongeldig besturingsteken.');
    }
    if (hash_equals('VeranderDitWachtwoord', $wachtwoord)) bootstrap34Stop('Het standaard placeholderwachtwoord is niet toegestaan.');
}

function bootstrap34TenantContext(string $configInvoer): array
{
    $projectRoot = bootstrap34BestaandVeiligPad(dirname(__DIR__), 'Applicatieroot', true);
    $configPad = bootstrap34BestaandVeiligPad($configInvoer, 'Tenantconfig');
    if (bootstrap34Binnen($configPad, $projectRoot)) bootstrap34Stop('Tenantconfig moet buiten de gedeelde applicatie/documentroot staan.');

    $config = require $configPad;
    if (!is_array($config)) bootstrap34Stop('Tenantconfig moet een PHP-array retourneren.');

    $tenantKey = (string)($config['vereniging']['sleutel'] ?? '');
    if (preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $tenantKey) !== 1 || strlen($tenantKey) < 3 || strlen($tenantKey) > 63 || str_contains($tenantKey, '--') || $tenantKey === 'default') {
        bootstrap34Stop('Tenantconfig bevat geen geldige canonieke tenant-key.');
    }

    try {
        $privateInvoer = tenantRuntimePrivateRoot($config);
    } catch (Throwable $e) {
        bootstrap34Stop('Tenantconfig bevat geen veilige private_root.');
    }
    if ($privateInvoer === null) bootstrap34Stop('Bootstrap is alleen toegestaan voor externe tenants met private_root.');
    $privateRoot = bootstrap34BestaandVeiligPad($privateInvoer, 'Private tenantroot', true);
    if (bootstrap34Binnen($privateRoot, $projectRoot)) bootstrap34Stop('Private tenantroot mag niet binnen de gedeelde applicatie/documentroot liggen.');

    $tenantRoot = dirname($privateRoot);
    if (bootstrap34NormPad(dirname($configPad)) !== bootstrap34NormPad($tenantRoot)) {
        bootstrap34Stop('Tenantconfig en private_root horen niet bij dezelfde tenantroot.');
    }

    $manifestPad = $tenantRoot . DIRECTORY_SEPARATOR . 'tenant.json';
    $manifestPad = bootstrap34BestaandVeiligPad($manifestPad, 'Tenantmanifest');
    $manifest = json_decode((string)file_get_contents($manifestPad), true);
    if (!is_array($manifest)) bootstrap34Stop('Tenantmanifest bevat geen geldige JSON.');
    if (!hash_equals($tenantKey, (string)($manifest['tenant_key'] ?? ''))) bootstrap34Stop('Tenantmanifest hoort bij een andere tenant-key.');

    $manifestConfig = (string)($manifest['config_file'] ?? '');
    $manifestPrivate = (string)($manifest['private_root'] ?? '');
    if ($manifestConfig === '' || $manifestPrivate === '') bootstrap34Stop('Tenantmanifest mist config_file of private_root binding.');
    $manifestConfigReal = bootstrap34BestaandVeiligPad($manifestConfig, 'Manifest config_file');
    $manifestPrivateReal = bootstrap34BestaandVeiligPad($manifestPrivate, 'Manifest private_root', true);
    if (bootstrap34NormPad($manifestConfigReal) !== bootstrap34NormPad($configPad)
        || bootstrap34NormPad($manifestPrivateReal) !== bootstrap34NormPad($privateRoot)) {
        bootstrap34Stop('Tenantmanifest bindt niet aan deze config/private_root combinatie.');
    }
    if (($manifest['require_tenant_config'] ?? false) !== true) bootstrap34Stop('Tenantmanifest staat niet in fail-closed tenantmodus.');

    $authMap = $privateRoot . DIRECTORY_SEPARATOR . 'auth';
    $authMap = bootstrap34BestaandVeiligPad($authMap, 'Tenant authmap', true);
    if (!bootstrap34Binnen($authMap, $privateRoot)) bootstrap34Stop('Tenant authmap valt buiten de private tenantroot.');

    $backupMap = $privateRoot . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'auth';
    $backupMap = bootstrap34BestaandVeiligPad($backupMap, 'Tenant authbackupmap', true);
    if (!bootstrap34Binnen($backupMap, $privateRoot)) bootstrap34Stop('Tenant authbackupmap valt buiten de private tenantroot.');

    return compact('tenantKey', 'configPad', 'privateRoot', 'tenantRoot', 'authMap', 'backupMap');
}

function bootstrap34BackupMaster(string $masterPad, string $backupMap): void
{
    if (!is_file($masterPad)) return;
    $micro = (int)round((microtime(true) - floor(microtime(true))) * 1000000);
    if ($micro >= 1000000) $micro = 999999;
    $backup = $backupMap . DIRECTORY_SEPARATOR . date('Y-m-d_His') . '_' . sprintf('%06d', $micro) . '_master.php';
    if (bootstrap34SymlinkInPad($backup) !== null) bootstrap34Stop('Onveilige symlink in authbackup-pad.');
    if (!@copy($masterPad, $backup)) bootstrap34Stop('Bestaande masterconfig kon niet veilig worden geback-upt.');
    @chmod($backup, 0640);

    $bestanden = glob($backupMap . DIRECTORY_SEPARATOR . '*_master.php') ?: [];
    sort($bestanden, SORT_STRING);
    $grens = time() - 90 * 86400;
    $recent = [];
    foreach ($bestanden as $bestand) {
        if (is_link($bestand)) bootstrap34Stop('Symlink aangetroffen in authbackupmap.');
        $tijd = @filemtime($bestand);
        if ($tijd !== false && $tijd < $grens) @unlink($bestand); else $recent[] = $bestand;
    }
    while (count($recent) > 20) {
        $oudste = array_shift($recent);
        if ($oudste !== null) @unlink($oudste);
    }
}

function bootstrap34SchrijfAtomisch(string $masterPad, string $inhoud): void
{
    $authMap = dirname($masterPad);
    if (is_link($masterPad)) bootstrap34Stop('Masterconfig mag geen symlink zijn.');
    $tmp = $authMap . DIRECTORY_SEPARATOR . '.master.php.tmp.' . bin2hex(random_bytes(8));
    if (bootstrap34SymlinkInPad($tmp) !== null) bootstrap34Stop('Onveilig tijdelijk pad voor masterconfig.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) bootstrap34Stop('Tijdelijke masterconfig kon niet worden geschreven.');
    @chmod($tmp, 0640);
    clearstatcache(true, $masterPad);
    if (is_link($masterPad)) { @unlink($tmp); bootstrap34Stop('Masterconfig werd tijdens bootstrap een symlink.'); }
    if (!@rename($tmp, $masterPad)) { @unlink($tmp); bootstrap34Stop('Masterconfig kon niet atomisch worden geplaatst.'); }
    @chmod($masterPad, 0640);
    clearstatcache(true, $masterPad);
    if (!is_file($masterPad) || is_link($masterPad)) bootstrap34Stop('Geplaatste masterconfig is niet veilig.');
}

$opt = bootstrap34ParseArgs($argv);
if ($opt['help']) { bootstrap34Help(); exit(0); }
$configInvoer = (string)($opt['config'] ?? '');
if ($configInvoer === '') bootstrap34Stop('--config=/absoluut/pad/config.php is verplicht.');

$context = bootstrap34TenantContext($configInvoer);
$masterPad = $context['authMap'] . DIRECTORY_SEPARATOR . 'master.php';
$lockPad = $context['authMap'] . DIRECTORY_SEPARATOR . '.admin-bootstrap.lock';
if (bootstrap34SymlinkInPad($masterPad) !== null) bootstrap34Stop('Masterconfigpad bevat een symlink.');
if (is_link($lockPad)) bootstrap34Stop('Bootstrap-lock mag geen symlink zijn.');

$lock = @fopen($lockPad, 'c');
if ($lock === false) bootstrap34Stop('Bootstrap-lock kon niet worden geopend.');
@chmod($lockPad, 0640);
if (!flock($lock, LOCK_EX)) { fclose($lock); bootstrap34Stop('Bootstrap-lock kon niet exclusief worden verkregen.'); }

try {
    clearstatcache(true, $masterPad);
    $bestaat = file_exists($masterPad) || is_link($masterPad);
    if (is_link($masterPad)) bootstrap34Stop('Bestaande masterconfig is een symlink en wordt geweigerd.');
    if ($bestaat && !is_file($masterPad)) bootstrap34Stop('Bestaand masterconfigpad is geen regulier bestand.');
    if ($bestaat && !$opt['rotate']) bootstrap34Stop('Deze tenant heeft al een mastercredential; gebruik alleen bewust --rotate voor vervanging.');
    if (!$bestaat && $opt['rotate']) bootstrap34Stop('--rotate is alleen geldig wanneer al een mastercredential bestaat.');

    if ($opt['password_stdin']) {
        $wachtwoord = bootstrap34LeesStdin();
    } else {
        $wachtwoord = bootstrap34LeesVerborgen('Nieuw beheerderswachtwoord: ');
        $bevestiging = bootstrap34LeesVerborgen('Herhaal beheerderswachtwoord: ');
        if (!hash_equals($wachtwoord, $bevestiging)) bootstrap34Stop('De twee wachtwoordinvoeren zijn niet gelijk.');
        unset($bevestiging);
    }
    bootstrap34ValideerWachtwoord($wachtwoord);

    $hash = password_hash($wachtwoord, PASSWORD_DEFAULT);
    if (!is_string($hash) || $hash === '' || !password_verify($wachtwoord, $hash)) bootstrap34Stop('Password hash kon niet veilig worden aangemaakt.');

    if ($bestaat) bootstrap34BackupMaster($masterPad, $context['backupMap']);
    $inhoud = "<?php\n// Gegenereerd door bin/bootstrap-tenant-admin.php — bewaar alleen server-side.\n\$BEHEER_WACHTWOORD_HASH = " . var_export($hash, true) . ";\n";
    bootstrap34SchrijfAtomisch($masterPad, $inhoud);

    unset($wachtwoord, $hash, $inhoud);
    echo ($bestaat ? 'Mastercredential geroteerd' : 'Eerste tenantbeheerder geactiveerd') . ": {$context['tenantKey']}\n";
    echo "Login: laat de gebruikersnaam leeg en gebruik het zojuist ingestelde beheerderswachtwoord.\n";
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
