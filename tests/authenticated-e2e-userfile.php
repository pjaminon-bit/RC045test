<?php
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Alleen CLI.\n"); exit(1); }

$input = $argv[1] ?? '';
$output = $argv[2] ?? '';
$admin = getenv('E2E_ADMIN_USER') ?: '';
$member = getenv('E2E_MEMBER_USER') ?: '';
$password = getenv('E2E_PASSWORD') ?: '';

if ($input === '' || $output === '' || !is_file($input)) {
    fwrite(STDERR, "Input/output ontbreekt.\n"); exit(1);
}
foreach ([$admin, $member] as $u) {
    if (preg_match('/^[a-zA-Z0-9._-]{2,30}$/D', $u) !== 1) {
        fwrite(STDERR, "Ongeldige tijdelijke gebruikersnaam.\n"); exit(1);
    }
}
if ($admin === $member || strlen($password) < 24) {
    fwrite(STDERR, "Ongeldige tijdelijke E2E-credential.\n"); exit(1);
}

$raw = file_get_contents($input);
if (!is_string($raw)) { fwrite(STDERR, "Authstore onleesbaar.\n"); exit(1); }
if (trim($raw) === '') $users = [];
else {
    try { $users = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { fwrite(STDERR, "Authstore bevat ongeldige JSON.\n"); exit(1); }
    if (!is_array($users) || !array_is_list($users)) { fwrite(STDERR, "Authstore is geen gebruikerslijst.\n"); exit(1); }
}

foreach ($users as $u) {
    if (!is_array($u)) { fwrite(STDERR, "Authstore bevat ongeldig record.\n"); exit(1); }
    $name = strtolower(trim((string)($u['gebruikersnaam'] ?? '')));
    if ($name === strtolower($admin) || $name === strtolower($member)) {
        fwrite(STDERR, "Tijdelijke E2E-gebruikersnaam bestaat onverwacht al.\n"); exit(1);
    }
}

$defs = require dirname(__DIR__) . '/app/core/platform-definities.php';
$allCaps = array_keys(is_array($defs['capabilities'] ?? null) ? $defs['capabilities'] : []);
sort($allCaps, SORT_STRING);
$legacyTabs = [];
foreach ($allCaps as $cap) {
    foreach ((array)($defs['capabilities'][$cap]['legacy'] ?? []) as $tab) {
        $tab = trim((string)$tab);
        if ($tab !== '' && !in_array($tab, $legacyTabs, true)) $legacyTabs[] = $tab;
    }
}
sort($legacyTabs, SORT_STRING);
$now = gmdate('c');
$hash = password_hash($password, PASSWORD_DEFAULT);
if (!is_string($hash) || $hash === '') { fwrite(STDERR, "Password hash faalde.\n"); exit(1); }

$users[] = [
    'id' => 'usr_e2e_admin_' . substr(hash('sha256', $admin), 0, 16),
    'gebruikersnaam' => $admin,
    'hash' => $hash,
    'aangemaakt' => $now,
    'wachtwoord_gewijzigd' => $now,
    'sessie_versie' => 1,
    'actief' => true,
    'capabilities' => $allCaps,
    'tabs' => $legacyTabs,
];
$users[] = [
    'id' => 'usr_e2e_member_' . substr(hash('sha256', $member), 0, 16),
    'gebruikersnaam' => $member,
    'hash' => $hash,
    'aangemaakt' => $now,
    'wachtwoord_gewijzigd' => $now,
    'sessie_versie' => 1,
    'actief' => true,
    'capabilities' => [],
    'tabs' => [],
];

$json = json_encode($users, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json) || file_put_contents($output, $json . "\n") === false) {
    fwrite(STDERR, "Tijdelijke authstore kon niet worden geschreven.\n"); exit(1);
}
@chmod($output, 0600);
echo "E2E authstore prepared: 2 tijdelijke accounts.\n";
