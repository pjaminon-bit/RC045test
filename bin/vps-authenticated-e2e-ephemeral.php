<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Alleen via CLI beschikbaar.'); }

$root = dirname(__DIR__);
foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|secret|token|key)(?:=|$)/i', (string)$arg) === 1 && (string)$arg !== '--password-stdin') {
        fwrite(STDERR, "FOUT: secrets zijn niet toegestaan in CLI-argumenten. Gebruik --password-stdin.\n");
        exit(2);
    }
}
$opt = getopt('', ['config:','expected-tenant:','expected-site:','admin-user:','member-user:','password-stdin','check','apply','cleanup','help']);
if (isset($opt['help'])) {
    echo "Gebruik: php bin/vps-authenticated-e2e-ephemeral.php ... --check | --password-stdin --apply | --cleanup\n";
    exit(0);
}
$check = isset($opt['check']);
$apply = isset($opt['apply']);
$cleanup = isset($opt['cleanup']);
if (((int)$check + (int)$apply + (int)$cleanup) !== 1) { fwrite(STDERR, "FOUT: kies exact één van --check, --apply of --cleanup.\n"); exit(2); }
if ($apply && !isset($opt['password-stdin'])) { fwrite(STDERR, "FOUT: --apply vereist --password-stdin.\n"); exit(2); }
if (!$apply && isset($opt['password-stdin'])) { fwrite(STDERR, "FOUT: --password-stdin is uitsluitend toegestaan bij --apply.\n"); exit(2); }

$configPad = trim((string)($opt['config'] ?? ''));
$expectedTenant = trim((string)($opt['expected-tenant'] ?? ''));
$expectedSite = rtrim(trim((string)($opt['expected-site'] ?? '')), '/');
$adminUser = trim((string)($opt['admin-user'] ?? ''));
$memberUser = trim((string)($opt['member-user'] ?? ''));
if ($configPad === '' || $configPad[0] !== '/' || !is_file($configPad) || !is_readable($configPad) || is_link($configPad)) { fwrite(STDERR, "FOUT: --config moet een veilig leesbaar absoluut tenantconfigbestand zijn.\n"); exit(2); }
if ($expectedTenant === '' || $expectedSite === '' || $adminUser === '' || $memberUser === '') { fwrite(STDERR, "FOUT: expected tenant/site en beide E2E-gebruikers zijn verplicht.\n"); exit(2); }

putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE=' . $configPad);
require_once $root . '/app/deployment/authenticated-e2e-fixture.php';
require_once $root . '/app/deployment/authenticated-e2e-ephemeral.php';
require_once $root . '/app/auth-storage.php';
require_once $root . '/app/storage/domein-repositories.php';
require_once $root . '/app/leden/contributies.php';
require_once $root . '/app/leden/groepen.php';

function e2e511Stop(string $message, int $code = 1): void { fwrite(STDERR, 'FOUT: ' . $message . "\n"); exit($code); }
function e2e511AuthLees(string $pad): array {
    if (!file_exists($pad)) return [];
    if (!is_file($pad) || is_link($pad) || !is_readable($pad)) throw new RuntimeException('Authstore is niet veilig leesbaar.');
    $raw = file_get_contents($pad); if (!is_string($raw) || trim($raw) === '') return [];
    $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !array_is_list($data)) throw new RuntimeException('Authstore is geen geldige gebruikerslijst.');
    return $data;
}
function e2e511AuthSchrijf(string $pad, array $users, string $backupDir): void {
    $dir = dirname($pad);
    foreach ([$dir, $backupDir] as $map) {
        if (is_link($map)) throw new RuntimeException('E2E authpad bevat een symlink.');
        if (!is_dir($map) && !mkdir($map, 0750, true) && !is_dir($map)) throw new RuntimeException('E2E authmap kon niet worden aangemaakt.');
        @chmod($map, 0750);
    }
    if (file_exists($pad)) {
        if (!is_file($pad) || is_link($pad)) throw new RuntimeException('Authstore is geen veilig regulier bestand.');
        $backup = $backupDir . '/e2e-pre-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '-users.json';
        if (!copy($pad, $backup)) throw new RuntimeException('Authstore-backup kon niet worden gemaakt.');
        @chmod($backup, 0640);
    }
    $json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    $tmp = $dir . '/.users.e2e.' . bin2hex(random_bytes(6)) . '.tmp';
    if (file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('Tijdelijke authstore kon niet worden geschreven.');
    @chmod($tmp, 0640);
    if (is_link($pad) || !rename($tmp, $pad)) { @unlink($tmp); throw new RuntimeException('Authstore kon niet atomisch worden geplaatst.'); }
    @chmod($pad, 0640);
}
function e2e511AuthHerstel(string $pad, bool $bestond, ?string $raw): void {
    if ($bestond) {
        if (!is_string($raw)) throw new RuntimeException('Originele authstore ontbreekt voor herstel.');
        $tmp = dirname($pad) . '/.users.e2e.restore.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($tmp, $raw, LOCK_EX) === false) throw new RuntimeException('Authstore-herstel kon niet worden voorbereid.');
        @chmod($tmp, 0640);
        if (!rename($tmp, $pad)) { @unlink($tmp); throw new RuntimeException('Authstore-herstel kon niet atomisch worden geplaatst.'); }
        @chmod($pad, 0640);
    } elseif (file_exists($pad) && !is_link($pad)) {
        @unlink($pad);
    }
}
function e2e511SchrijfDomein(array $leden, array $contributies, array $groepen, array $vergaderingen, array $taken): void {
    if (!repoLedenSchrijf($leden)) throw new RuntimeException('Ledenfixture kon niet worden opgeslagen.');
    if (!contributiesSchrijf($contributies)) throw new RuntimeException('Contributiefixture kon niet worden opgeslagen.');
    if (!groepenSchrijfDocument($groepen)) throw new RuntimeException('Groepsfixture kon niet worden opgeslagen.');
    if (!repoVergaderingenSchrijf($vergaderingen)) throw new RuntimeException('Vergaderfixture kon niet worden opgeslagen.');
    if (!repoTakenSchrijf($taken)) throw new RuntimeException('Taakfixture kon niet worden opgeslagen.');
}

try {
    $config = require $root . '/site-config.php';
    if (!is_array($config)) throw new RuntimeException('site-config levert geen array.');
    $tenant = tenantRuntimeVeiligeSleutel((string)($config['vereniging']['sleutel'] ?? ''));
    $site = rtrim((string)($config['vereniging']['site_url'] ?? ''), '/');
    if (!hash_equals($expectedTenant, $tenant)) throw new RuntimeException('Actieve tenant-key wijkt af van --expected-tenant.');
    if (!hash_equals($expectedSite, $site)) throw new RuntimeException('Actieve site_url wijkt af van --expected-site.');
    e2e510Username($adminUser); e2e510Username($memberUser);
    if (strcasecmp($adminUser, $memberUser) === 0) throw new RuntimeException('E2E-admin en E2E-lid moeten verschillende accounts zijn.');
    if (strtolower(trim((string)($config['opslag']['private_driver'] ?? ''))) !== 'pdo') throw new RuntimeException('Authenticated VPS-E2E vereist private_driver=pdo.');
    $privateRoot = tenantRuntimePrivateRoot($config);
    if ($privateRoot === null || !is_dir($privateRoot) || is_link($privateRoot) || !is_readable($privateRoot) || !is_writable($privateRoot)) throw new RuntimeException('Tenant private_root is niet veilig leesbaar/schrijfbaar.');
    authStorageValideerExterneMaster($privateRoot);
    $authPad = $privateRoot . '/auth/users.json';
    $authBackup = $privateRoot . '/backups/auth';
    $users = e2e511AuthLees($authPad);
    $leden = repoLedenLees();
    $contributies = contributiesLees();
    $groepen = groepenLeesDocument();
    $vergaderingen = repoVergaderingenLees();
    $taken = repoTakenLees();
    e2e511AssertReservedSlots($leden, $contributies, $groepen, $vergaderingen, $taken, $tenant);

    if ($check) {
        $probeHash = password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT);
        if (!is_string($probeHash)) throw new RuntimeException('Password hash probe faalde.');
        e2e510MergeAuthUsers($users, $tenant, $adminUser, $memberUser, $probeHash);
        echo 'E2E EPHEMERAL CHECK OK  tenant=' . $tenant . ' storage=pdo fixture=' . e2e510Marker() . "\n";
        exit(0);
    }

    if ($cleanup) {
        $before = e2e511CountAll($users, $leden, $contributies, $groepen, $vergaderingen, $taken, $tenant);
        if ($before === 0) {
            echo 'E2E CLEANUP OK  tenant=' . $tenant . ' removed=0 fixture=' . e2e510Marker() . "\n";
            exit(0);
        }
        $newUsers = e2e511CleanupAuth($users, $tenant);
        [$leden, $contributies, $groepen, $vergaderingen, $taken] = e2e511CleanupDocuments($leden, $contributies, $groepen, $vergaderingen, $taken, $tenant);
    } else {
        $password = stream_get_contents(STDIN);
        $password = is_string($password) ? rtrim($password, "\r\n") : '';
        if (strlen($password) < 32 || strlen($password) > 256 || str_contains($password, "\0")) throw new RuntimeException('Ephemeral E2E-wachtwoord via stdin moet 32-256 tekens bevatten.');
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $password = str_repeat("\0", strlen($password)); unset($password);
        if (!is_string($hash) || (password_get_info($hash)['algoName'] ?? 'unknown') === 'unknown') throw new RuntimeException('Password hash faalde.');
        $newUsers = e2e510MergeAuthUsers($users, $tenant, $adminUser, $memberUser, $hash);
        $leden = e2e510MergeLeden($leden, $tenant, $memberUser);
        $contributies = e2e510MergeContributies($contributies, $tenant);
        $groepen = e2e510MergeGroepen($groepen, $tenant);
        $vergaderingen = e2e510MergeVergaderingen($vergaderingen, $tenant);
        $taken = e2e510MergeTaken($taken, $tenant);
        [$leden, $contributies, $groepen, $vergaderingen, $taken] = e2e511MarkDocuments($leden, $contributies, $groepen, $vergaderingen, $taken, $tenant);
    }

    $authBestond = is_file($authPad) && !is_link($authPad);
    $authRaw = $authBestond ? file_get_contents($authPad) : null;
    if ($authBestond && !is_string($authRaw)) throw new RuntimeException('Originele authstore kon niet worden gesnapshot.');
    e2e511AuthSchrijf($authPad, $newUsers, $authBackup);
    try {
        privateStoreTransactie(function() use ($leden, $contributies, $groepen, $vergaderingen, $taken): void {
            e2e511SchrijfDomein($leden, $contributies, $groepen, $vergaderingen, $taken);
        });
    } catch (Throwable $e) {
        e2e511AuthHerstel($authPad, $authBestond, $authRaw);
        throw $e;
    }

    $verifyUsers = e2e511AuthLees($authPad);
    $verifyLeden = repoLedenLees();
    $verifyContrib = contributiesLees();
    $verifyGroepen = groepenLeesDocument();
    $verifyVerg = repoVergaderingenLees();
    $verifyTaken = repoTakenLees();
    $remaining = e2e511CountAll($verifyUsers, $verifyLeden, $verifyContrib, $verifyGroepen, $verifyVerg, $verifyTaken, $tenant);
    if ($cleanup) {
        if ($remaining !== 0) throw new RuntimeException('E2E-cleanup kon niet volledig worden nageverifieerd.');
        echo 'E2E CLEANUP OK  tenant=' . $tenant . ' removed=' . $before . ' fixture=' . e2e510Marker() . "\n";
        exit(0);
    }
    if ($remaining < 7) throw new RuntimeException('Ephemeral E2E-fixture is niet volledig aanwezig na apply.');
    echo 'E2E APPLY OK  tenant=' . $tenant . ' accounts=2 linked_member=1 fixture=' . e2e510Marker() . "\n";
} catch (Throwable $e) {
    e2e511Stop($e->getMessage());
}
