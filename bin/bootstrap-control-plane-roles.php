<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }

require_once dirname(__DIR__) . '/app/deployment/control-plane-admin-suite-contract.php';
require_once dirname(__DIR__) . '/app/deployment/control-plane-auth-hardening.php';

function roles146Stop(string $message, int $code = 1): never
{
    fwrite(STDERR, "FOUT: {$message}\n");
    exit($code);
}

function roles146Help(): never
{
    echo "Gebruik:\n";
    echo "  sudo php bin/bootstrap-control-plane-roles.php --config=/pad/runtime.json --owner=<operator> [--recover]\n\n";
    echo "Zonder --recover initialiseert dit uitsluitend een ontbrekende rollenstore.\n";
    echo "--recover is vereist voor een corrupte of ownerloze bestaande rollenstore.\n";
    exit(0);
}

function roles146Config(string $path): array
{
    if (!runtime41IsAbsoluutPad($path) || runtime41HeeftRelatieveSegmenten($path) || runtime41SymlinkInPad($path) !== null || !is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Runtimeconfig is onveilig of onleesbaar.');
    }
    $raw = @file_get_contents($path);
    try { $config = is_string($raw) ? json_decode($raw, true, 64, JSON_THROW_ON_ERROR) : null; }
    catch (Throwable $e) { $config = null; }
    if (!is_array($config) || (int)($config['schema'] ?? 0) !== 1 || ($config['phase'] ?? '') !== '5.1-runtime') {
        throw new RuntimeException('Runtimeconfig heeft onbekend schema.');
    }
    foreach (['snapshot_file','runtime_user'] as $key) {
        if (!isset($config[$key]) || !is_string($config[$key]) || $config[$key] === '') throw new RuntimeException('Runtimeconfig mist '.$key.'.');
    }
    if (!runtime41IsAbsoluutPad($config['snapshot_file']) || runtime41HeeftRelatieveSegmenten($config['snapshot_file'])) {
        throw new RuntimeException('Runtimeconfig bevat onveilig snapshotpad.');
    }
    $config['_config_file'] = $path;
    return $config;
}

function roles146HtpasswdUsers(string $file): array
{
    if (runtime41SymlinkInPad($file) !== null || !is_file($file) || !is_readable($file)) throw new RuntimeException('Basic-Auth operatorbestand ontbreekt of is onveilig.');
    $raw = @file_get_contents($file);
    if (!is_string($raw)) throw new RuntimeException('Basic-Auth operatorbestand kon niet worden gelezen.');
    control512HtpasswdValidate($raw);
    $users = [];
    foreach (preg_split('/\r?\n/', trim($raw)) ?: [] as $line) {
        if ($line === '') continue;
        $pos = strpos($line, ':');
        if ($pos === false) throw new RuntimeException('Basic-Auth operatorbestand bevat ongeldige regel.');
        $user = substr($line, 0, $pos);
        if (!control58OperatorValid($user)) throw new RuntimeException('Basic-Auth operatorbestand bevat ongeldige gebruikersnaam.');
        $users[] = $user;
    }
    $users = array_values(array_unique($users));
    sort($users, SORT_STRING);
    if ($users === []) throw new RuntimeException('Basic-Auth operatorbestand bevat geen operators.');
    return $users;
}

function roles146ReadJsonRegular(string $file): mixed
{
    if (is_link($file) || !is_file($file) || !is_readable($file)) throw new RuntimeException('Statebestand is onveilig: '.$file);
    $raw = @file_get_contents($file);
    if (!is_string($raw)) return null;
    try { return json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { return null; }
}

function roles146Gid(string $group): int
{
    if (!function_exists('posix_getgrnam')) throw new RuntimeException('Groepscontrole vereist posix_getgrnam.');
    $entry = @posix_getgrnam($group);
    if (!is_array($entry)) throw new RuntimeException('Runtimegroep bestaat niet: '.$group);
    return (int)$entry['gid'];
}

function roles146Write(string $path, array $document, string $group): void
{
    $dir = dirname($path);
    if (runtime41SymlinkInPad($dir) !== null || !is_dir($dir) || is_link($dir)) throw new RuntimeException('Rollenstate-map is onveilig of ontbreekt.');
    if (is_link($path)) throw new RuntimeException('Statepad is een symlink en wordt niet overschreven: '.$path);
    try { $json = json_encode($document, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n"; }
    catch (Throwable $e) { throw new RuntimeException('State kon niet als JSON worden opgebouwd.'); }
    $tmp = $dir.'/.'.basename($path).'.bootstrap.'.bin2hex(random_bytes(8));
    $handle = @fopen($tmp, 'x');
    if (!is_resource($handle)) throw new RuntimeException('Tijdelijk statebestand kon niet exclusief worden aangemaakt.');
    try {
        $written = fwrite($handle, $json);
        if ($written === false || $written !== strlen($json)) throw new RuntimeException('Tijdelijke statewrite was onvolledig.');
        if (!fflush($handle)) throw new RuntimeException('Tijdelijke statewrite kon niet worden geflusht.');
        if (function_exists('fsync') && !fsync($handle)) throw new RuntimeException('Tijdelijke statewrite kon niet worden gesynchroniseerd.');
    } finally {
        fclose($handle);
    }
    $gid = roles146Gid($group);
    if (!@chown($tmp, 0) || !@chgrp($tmp, $gid) || !@chmod($tmp, 0640)) { @unlink($tmp); throw new RuntimeException('Tijdelijke statemetadata kon niet veilig worden gezet.'); }
    $meta = @lstat($tmp);
    if (!is_array($meta) || is_link($tmp) || !is_file($tmp) || (int)$meta['uid'] !== 0 || (int)$meta['gid'] !== $gid || (((int)$meta['mode'] & 0777) !== 0640)) {
        @unlink($tmp); throw new RuntimeException('Tijdelijke statemetadata wijkt af van root:'.$group.' 0640.');
    }
    if (!@rename($tmp, $path)) { @unlink($tmp); throw new RuntimeException('Atomische statewrite faalde.'); }
    clearstatcache(true, $path);
    $meta = @lstat($path);
    if (!is_array($meta) || is_link($path) || !is_file($path) || (int)$meta['uid'] !== 0 || (int)$meta['gid'] !== $gid || (((int)$meta['mode'] & 0777) !== 0640)) {
        throw new RuntimeException('Definitieve statemetadata wijkt af van root:'.$group.' 0640.');
    }
}

if (function_exists('posix_geteuid') && posix_geteuid() !== 0) roles146Stop('Deze bootstrap moet als root draaien.');
$opt = getopt('', ['config:','owner:','recover','help']);
if (isset($opt['help'])) roles146Help();
$configPath = trim((string)($opt['config'] ?? ''));
$owner = trim((string)($opt['owner'] ?? ''));
$recover = array_key_exists('recover', $opt);
if ($configPath === '' || $owner === '') roles146Stop('Gebruik --config=<runtime.json> en --owner=<bestaande operator>.');
if (!control58OperatorValid($owner)) roles146Stop('Ownernaam is ongeldig.');

try {
    $config = roles146Config($configPath);
    $paths = control58StatePaths($config);
    $authFile = runtime41NormPad(dirname($configPath).'/operators.htpasswd');
    $users = roles146HtpasswdUsers($authFile);
    if (!in_array($owner, $users, true)) throw new RuntimeException('Gekozen owner staat niet in het Basic-Auth operatorbestand.');

    $rolesExists = file_exists($paths['roles_file']) || is_link($paths['roles_file']);
    $existingRoles = null;
    if ($rolesExists) {
        if (is_link($paths['roles_file'])) throw new RuntimeException('Rollenstore is een symlink; handmatig root-onderzoek is vereist.');
        $existingRoles = control58RolesDocument(roles146ReadJsonRegular($paths['roles_file']));
        if ($existingRoles !== null && $existingRoles['roles'] !== []) throw new RuntimeException('Geldige rollenstore met owner bestaat al; gebruik normaal rollenbeheer, niet bootstrap.');
        if (!$recover) throw new RuntimeException('Bestaande corrupte of ownerloze rollenstore vereist expliciet --recover.');
    }

    $now = gmdate('Y-m-d\TH:i:s\Z');
    $marker = null;
    $markerExists = file_exists($paths['roles_bootstrap_file']) || is_link($paths['roles_bootstrap_file']);
    if ($markerExists) {
        if (is_link($paths['roles_bootstrap_file'])) throw new RuntimeException('Bootstrapmarker is een symlink; handmatig root-onderzoek is vereist.');
        $marker = control58RolesBootstrapDocument(roles146ReadJsonRegular($paths['roles_bootstrap_file']));
        if ($marker === null && !$recover) throw new RuntimeException('Bootstrapmarker is ongeldig; expliciet --recover is vereist.');
    }
    if ($marker === null) {
        $marker = ['schema'=>1,'phase'=>'5.8-roles-bootstrap','owner'=>$owner,'initialized_at_utc'=>$now,'recovery_count'=>$recover?1:0,'last_recovered_at_utc'=>$recover?$now:null];
    } elseif ($recover) {
        $marker['owner'] = $owner;
        $marker['recovery_count'] = (int)$marker['recovery_count'] + 1;
        $marker['last_recovered_at_utc'] = $now;
    } elseif ($rolesExists) {
        throw new RuntimeException('Rollenstate is al eerder geïnitialiseerd; gebruik --recover voor een ownerloze/corrupte hersteltoestand.');
    }
    if (control58RolesBootstrapDocument($marker) === null) throw new RuntimeException('Bootstrapmarker kon niet veilig worden opgebouwd.');
    $roles = control58InitialRolesDocument($users, $owner, $now);

    // Schrijf de marker eerst: een onderbreking vóór de rollenwrite blijft fail-closed.
    roles146Write($paths['roles_bootstrap_file'], $marker, $config['runtime_user']);
    roles146Write($paths['roles_file'], $roles, $config['runtime_user']);

    echo 'OK: control-plane rollen expliciet '.($recover?'hersteld':'geïnitialiseerd').'; owner='.$owner.', overige operators=viewer.' . "\n";
} catch (Throwable $e) {
    roles146Stop($e->getMessage());
}
