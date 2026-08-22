<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function spvCheck(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function spvBron(string $pad): string {
    $inhoud = @file_get_contents($pad);
    if (!is_string($inhoud)) throw new RuntimeException('Testbron ontbreekt: ' . $pad);
    return $inhoud;
}
function spvRmdir(string $pad): void {
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        spvRmdir($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

// H-02 — autorisatie zonder expliciete metadata is fail-closed.
require_once $root . '/app/auth-capabilities.php';
spvCheck(authGebruikerCapabilities(['gebruikersnaam'=>'zonder-rechten']) === [], 'account zonder autorisatiemetadata krijgt nul capabilities');
spvCheck(authGebruikerCapabilities(['capabilities'=>'ongeldig']) === [], 'malforme capabilitymetadata faalt gesloten');
spvCheck(authGebruikerCapabilities(['tabs'=>'ongeldig']) === [], 'malforme legacy-tabmetadata faalt gesloten');
$ledenCaps = authGebruikerCapabilities(['tabs'=>['leden']]);
spvCheck($ledenCaps !== [] && in_array('members.manage', $ledenCaps, true), 'expliciete legacy-tab wordt beperkt gemigreerd naar bekende capabilities');
$migratie = authGebruikerMigreerRecord(['gebruikersnaam'=>'legacy-zonder-rechten']);
spvCheck(($migratie['capabilities'] ?? null) === [] && ($migratie['tabs'] ?? null) === [], 'legacy-account zonder rechten migreert naar expliciet lege rechten');
$capBron = spvBron($root . '/app/auth-capabilities.php');
spvCheck(!str_contains($capBron, 'authLegacyBredeCapabilities'), 'brede legacy capabilityfallback bestaat niet meer');

$authBron = spvBron($root . '/auth.php');
spvCheck(str_contains($authBron, "array_key_exists('capabilities'") && !str_contains($authBron, '$toegestaneTabs = array_keys($alleTabs); // legacy'), 'legacy UI-rechten worden niet meer breed toegekend');
spvCheck(str_contains($authBron, "empty($authPaden['tenant_private'])") && str_contains($authBron, 'password_verify'), 'plaintext masterfallback is beperkt tot standalone en tenantmaster gebruikt hashverificatie');

// H-01 — installatiegrens staat als harde sessiecontext in de storage/checklaag.
$storageBron = spvBron($root . '/app/auth-storage.php');
$sessionBron = spvBron($root . '/app/auth-session-tenant.php');
spvCheck(str_contains($storageBron, "verenigingsplatform-sessions") && str_contains($storageBron, "'session_binding'"), 'standalone auth gebruikt installatie-eigen sessiepad en binding');
spvCheck(str_contains($sessionBron, 'installation_binding') && str_contains($sessionBron, 'authSessionTenantHerstart'), 'sessiebewaking controleert installatiebinding en trekt mismatch in');

// H-03 — vertaallaag mag niet meer zelf een losse sessie vertrouwen.
$vertaal = spvBron($root . '/vertaal.php');
spvCheck(str_contains($vertaal, "require_once __DIR__ . '/auth.php'") && str_contains($vertaal, 'authHeeftCapability'), 'vertaalendpoint gebruikt centrale auth en capabilitycontrole');
spvCheck(!str_contains($vertaal, 'session_start('), 'vertaalendpoint start geen eigen losse PHP-sessie');
spvCheck(str_contains($vertaal, "application/json") && str_contains($vertaal, 'HTTP_SEC_FETCH_SITE') && str_contains($vertaal, 'HTTP_ORIGIN'), 'vertaalendpoint accepteert alleen same-origin JSON');
spvCheck(str_contains($vertaal, 'strlen($ruw) > 65536') && str_contains($vertaal, '$totaalBytes > 49152') && str_contains($vertaal, '$maxAanroepen = 20'), 'vertaalendpoint begrenst requestgrootte, tekstvolume en aanroepfrequentie');
spvCheck(str_contains($vertaal, "https://(api-free|api)\\.deepl\\.com") && str_contains($vertaal, 'CURLPROTO_HTTPS') && str_contains($vertaal, 'CURLOPT_FOLLOWLOCATION => false'), 'DeepL outbound request is host- en HTTPS-begrensd zonder redirects');

// M-01 — openbare signup limiter staat buiten de release-root, is geïsoleerd en
// faalt gesloten bij corrupte state.
require_once $root . '/app/security/signup-rate-limit.php';
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rc045-spv-' . bin2hex(random_bytes(5));
$projA = $tmp . '/release-a';
$projB = $tmp . '/release-b';
@mkdir($projA, 0700, true);
@mkdir($projB, 0700, true);
$cfg = ['vereniging'=>['sleutel'=>'rc045','site_url'=>'https://rc045.nl']];
try {
    $pA = signupRateLimitPaden($cfg, $projA);
    $pB = signupRateLimitPaden($cfg, $projB);
    spvCheck(dirname($pA['data']) !== $projA && dirname($pB['data']) !== $projB, 'signup limiter schrijft niet in de release-root');
    spvCheck($pA['data'] !== $pB['data'], 'twee standalone installaties delen geen signup limiter state');
    $toegestaan = [];
    for ($i=0; $i<6; $i++) $toegestaan[] = signupRateLimitConsume($cfg, $projA, '192.0.2.10', 5, 3600)['allowed'];
    spvCheck($toegestaan === [true,true,true,true,true,false], 'signup limiter blokkeert de zesde poging binnen het venster');
    file_put_contents($pB['data'], '{kapot-json');
    $failClosed = false;
    try { signupRateLimitConsume($cfg, $projB, '192.0.2.11', 5, 3600); }
    catch (Throwable $e) { $failClosed = true; }
    spvCheck($failClosed, 'corrupte limiterstate wordt niet als onbeperkte toegang behandeld');
    spvRmdir(dirname($pA['data']));
    spvRmdir(dirname($pB['data']));
} finally {
    spvRmdir($tmp);
}
$signupEndpoint = spvBron($root . '/aanmelden-ontvangst.php');
spvCheck(str_contains($signupEndpoint, 'signupRateLimitConsume') && !str_contains($signupEndpoint, "__DIR__ . '/aanmelden-pogingen.php'"), 'aanmeldendpoint gebruikt nieuwe security-store en geen releasebestand');
spvCheck(str_contains($signupEndpoint, "aanmeldenAntwoord(503"), 'limiterfout faalt gesloten met 503');

// M-02/M-03 — GitHub Actions trust en fixturemutatie.
$workflow = spvBron($root . '/.github/workflows/full-regression.yml');
spvCheck(str_contains($workflow, 'FTP_SSH_KNOWN_HOSTS') && !str_contains($workflow, 'ssh-keyscan'), 'authenticated CI gebruikt vooraf vertrouwde SSH-hostkey en geen TOFU keyscan');
spvCheck(str_contains($workflow, 'group: rc045test-dev-auth-fixture') && str_contains($workflow, 'cancel-in-progress: false'), 'authenticated DEV-fixtures zijn globaal geserialiseerd');
spvCheck(str_contains($workflow, "grep -Eq 'e2e-(admin|member)-[0-9]+'"), 'CI bewijst na herstel dat synthetische accounts verdwenen zijn');
spvCheck(substr_count($workflow, 'npm ci --ignore-scripts') >= 2, 'browserjobs installeren uitsluitend gelockte dependencies');

// M-04/M-05 — afdwingbare CSP, geen externe scripts in script-src.
$ht = spvBron($root . '/.htaccess');
spvCheck(str_contains($ht, 'Content-Security-Policy') && str_contains($ht, "default-src 'self'") && str_contains($ht, "object-src 'none'") && str_contains($ht, "base-uri 'self'"), 'Apache levert een afdwingbare basis-CSP');
spvCheck(str_contains($ht, "script-src 'self' 'unsafe-inline'") && !preg_match('/script-src[^;]*(?:gc\\.zgo\\.at|goatcounter)/i', $ht), 'CSP staat uitvoerbare scripts alleen vanaf eigen origin toe');

// Low — control-plane sessie en dependency reproducibility.
$cp = spvBron($root . '/app/control-plane/control-plane-runtime.php');
spvCheck(str_contains($cp, "session.use_strict_mode") && str_contains($cp, 'operator_identity') && str_contains($cp, 'session_regenerate_id(true)'), 'control-plane sessie is strict en aan REMOTE_USER gebonden');
$package = json_decode(spvBron($root . '/package-lock.json'), true);
spvCheck(is_array($package) && (($package['packages']['node_modules/@playwright/test']['version'] ?? '') === '1.62.1') && str_starts_with((string)($package['packages']['node_modules/@playwright/test']['integrity'] ?? ''), 'sha512-'), 'Playwright is exact gelockt met integriteitshash');
$supply = spvBron($root . '/.github/workflows/security-supply-chain.yml');
spvCheck(str_contains($supply, 'fetch-depth: 0') && str_contains($supply, 'gitleaks/gitleaks-action@e0c47f4f8be36e29cdc102c57e68cb5cbf0e8d1e') && str_contains($supply, 'npm audit --audit-level=high'), 'CI scant volledige Git-historie en dependencykwetsbaarheden');

echo "Pre-VPS security hardening: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
