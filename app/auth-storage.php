<?php
// ============================================================
// Tenant- en installatiebewuste auth storage
// ============================================================
require_once __DIR__ . '/core/tenant-runtime.php';

/**
 * Externe tenantmasters accepteren uitsluitend een geldige password_hash en
 * mogen geen plaintext compatibiliteitsvariabele bevatten. De standalone
 * installatie houdt zijn tijdelijke legacypad buiten deze validator.
 */
function authStorageValideerExterneMaster(string $privateRoot): void
{
    $master = $privateRoot . '/auth/master.php';
    if (!file_exists($master) && !is_link($master)) return;
    if (!is_file($master) || is_link($master) || !is_readable($master)) {
        tenantRuntimeConfiguratieFout('Tenant masterconfig is niet veilig leesbaar.');
    }

    $waarden = (static function (string $pad): array {
        $BEHEER_WACHTWOORD_HASH = null;
        $BEHEER_WACHTWOORD = null;
        require $pad;
        return [
            'hash' => $BEHEER_WACHTWOORD_HASH,
            'legacy' => $BEHEER_WACHTWOORD,
        ];
    })($master);

    $hash = $waarden['hash'] ?? null;
    $legacy = $waarden['legacy'] ?? null;
    $hashGeldig = is_string($hash)
        && $hash !== ''
        && ((password_get_info($hash)['algoName'] ?? 'unknown') !== 'unknown');
    $heeftPlaintext = is_string($legacy) && trim($legacy) !== '';
    if (!$hashGeldig || $heeftPlaintext) {
        tenantRuntimeConfiguratieFout('Externe tenantmaster vereist uitsluitend een geldige password_hash.');
    }
}

/** Bestaande security-JSON mag nooit stilzwijgend als een lege toestand gelden. */
function authStorageValideerJsonBestand(string $pad, string $label): void
{
    if (!file_exists($pad) && !is_link($pad)) return;
    if (!is_file($pad) || is_link($pad) || !is_readable($pad)) {
        tenantRuntimeConfiguratieFout($label . ' is niet veilig leesbaar.');
    }
    $raw = @file_get_contents($pad);
    if (!is_string($raw) || trim($raw) === '') {
        tenantRuntimeConfiguratieFout($label . ' is leeg of onleesbaar; herstel de securityopslag vóór verder gebruik.');
    }
    try {
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        tenantRuntimeConfiguratieFout($label . ' bevat ongeldige JSON; herstel de securityopslag vóór verder gebruik.');
    }
    if (!is_array($data)) {
        tenantRuntimeConfiguratieFout($label . ' heeft een ongeldig formaat; herstel de securityopslag vóór verder gebruik.');
    }
}

function authStorageValideerSecurityBestanden(array $paden): void
{
    foreach ([
        'users' => 'Gebruikersopslag',
        'audit' => 'Auditlog',
        'login_attempts' => 'Login-rate-limitopslag',
    ] as $sleutel => $label) {
        $pad = (string)($paden[$sleutel] ?? '');
        if ($pad !== '') authStorageValideerJsonBestand($pad, $label);
    }
}

/**
 * De actieve masterconfig is ook een sessiegeneratie. Een wachtwoordrotatie
 * moet bestaande mastercookies niet ongemerkt laten doorrollen naar de nieuwe
 * credentialgeneratie.
 */
function authStorageMasterGeneratieVoorPad(string $master): string
{
    if (!file_exists($master) && !is_link($master)) return 'geen-master';
    if (!is_file($master) || is_link($master) || !is_readable($master)) {
        tenantRuntimeConfiguratieFout('Masterconfig is niet veilig leesbaar voor sessiebinding.');
    }
    $hash = @hash_file('sha256', $master);
    if (!is_string($hash) || $hash === '') {
        tenantRuntimeConfiguratieFout('Masterconfig kon niet aan de sessienamespace worden gebonden.');
    }
    return $hash;
}

function authStorageMasterGeneratie(string $privateRoot): string
{
    return authStorageMasterGeneratieVoorPad($privateRoot . '/auth/master.php');
}

/**
 * Iedere installatie krijgt een eigen cookie-naam, serverside sessiepad en
 * bindingsleutel. Dit geldt óók voor standalone/legacy. Daardoor kunnen twee
 * installaties op hetzelfde domein (bijvoorbeeld PROD / en DEV /dev/) nooit
 * dezelfde PHP-sessie vertrouwen, zelfs niet als de hosting globaal dezelfde
 * session.save_path zou gebruiken.
 */
function authStorageSessieContext(array $siteConfig, string $projectRoot, ?string $privateRoot = null): array
{
    $tenantKey = tenantRuntimeVeiligeSleutel((string)($siteConfig['vereniging']['sleutel'] ?? 'default'));
    $siteUrl = strtolower(rtrim(trim((string)($siteConfig['vereniging']['site_url'] ?? '')), '/'));

    if ($privateRoot !== null) {
        $installatieBron = $privateRoot;
        $masterGeneratie = authStorageMasterGeneratie($privateRoot);
    } else {
        $realProjectRoot = realpath($projectRoot);
        $installatieBron = is_string($realProjectRoot) && $realProjectRoot !== '' ? $realProjectRoot : $projectRoot;
        $masterGeneratie = authStorageMasterGeneratieVoorPad($projectRoot . '/beheer-config.php');
    }

    $fingerprint = hash('sha256', $installatieBron . "\0" . $tenantKey . "\0" . $siteUrl . "\0" . $masterGeneratie);
    $sessiePad = $privateRoot !== null
        ? $privateRoot . '/sessions'
        : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'verenigingsplatform-sessions' . DIRECTORY_SEPARATOR . substr($fingerprint, 0, 32);

    return [
        'name' => 'VST' . substr($fingerprint, 0, 24),
        'path' => $sessiePad,
        'binding' => $fingerprint,
    ];
}

/**
 * Activeert vóór session_start() de installatie-eigen PHP-session namespace.
 * Er bestaat bewust geen terugval naar de globale PHP session.save_path.
 */
function authStorageActiveerSessieIsolatie(array $siteConfig, string $projectRoot, ?string $privateRoot = null): array
{
    $context = authStorageSessieContext($siteConfig, $projectRoot, $privateRoot);
    $sessiePad = $context['path'];

    if (is_link($sessiePad)) {
        tenantRuntimeConfiguratieFout('Sessiemap mag geen symlink zijn.');
    }
    if (!is_dir($sessiePad) && !@mkdir($sessiePad, 0750, true) && !is_dir($sessiePad)) {
        tenantRuntimeConfiguratieFout('Installatie-eigen sessiemap kon niet worden aangemaakt.');
    }
    @chmod($sessiePad, 0750);
    if (!is_dir($sessiePad) || !is_writable($sessiePad)) {
        tenantRuntimeConfiguratieFout('Installatie-eigen sessiemap is niet schrijfbaar.');
    }

    if (session_status() !== PHP_SESSION_NONE) {
        tenantRuntimeConfiguratieFout('Session namespace moet vóór session_start() worden geactiveerd.');
    }
    if (headers_sent()) {
        tenantRuntimeConfiguratieFout('Session namespace moet vóór response-output worden geactiveerd.');
    }

    $actiefPad = (string)ini_get('session.save_path');
    if (!hash_equals($sessiePad, $actiefPad)) {
        $gezet = ini_set('session.save_path', $sessiePad);
        if ($gezet === false || !hash_equals($sessiePad, (string)ini_get('session.save_path'))) {
            tenantRuntimeConfiguratieFout('Installatie-eigen PHP session.save_path kon niet worden geactiveerd.');
        }
    }

    session_name($context['name']);
    if (session_name() !== $context['name']) {
        tenantRuntimeConfiguratieFout('Installatie-eigen sessiecookie kon niet worden geactiveerd.');
    }

    return $context;
}

/**
 * Bepaalt alle server-only authpaden als één ondeelbaar contract. Authdata van
 * externe tenants blijft volledig onder private_root. Standalone gebruikt nog
 * zijn legacy databestanden, maar niet langer een gedeelde PHP-sessieruimte.
 */
function authStoragePaden(array $siteConfig, string $projectRoot): array
{
    $privateRoot = tenantRuntimePrivateRoot($siteConfig);
    if ($privateRoot !== null) authStorageValideerExterneMaster($privateRoot);

    if (PHP_SAPI === 'cli' && headers_sent()) {
        $sessieContext = authStorageSessieContext($siteConfig, $projectRoot, $privateRoot);
    } else {
        $sessieContext = authStorageActiveerSessieIsolatie($siteConfig, $projectRoot, $privateRoot);
    }

    if ($privateRoot === null) {
        $paden = [
            'tenant_private' => false,
            'config' => $projectRoot . '/beheer-config.php',
            'users' => $projectRoot . '/beheer-users.json',
            'audit' => $projectRoot . '/beheer-log.json',
            'login_attempts' => $projectRoot . '/beheer-login-pogingen.json',
            'login_lock' => $projectRoot . '/data-backups/.login-pogingen.lock',
            'backups' => $projectRoot . '/data-backups',
            'sessions' => $sessieContext['path'],
            'session_name' => $sessieContext['name'],
            'session_binding' => $sessieContext['binding'],
        ];
        authStorageValideerSecurityBestanden($paden);
        return $paden;
    }

    $paden = [
        'tenant_private' => true,
        'config' => $privateRoot . '/auth/master.php',
        'users' => $privateRoot . '/auth/users.json',
        'audit' => $privateRoot . '/audit/log.json',
        'login_attempts' => $privateRoot . '/security/login-attempts.json',
        'login_lock' => $privateRoot . '/security/.login-attempts.lock',
        'backups' => $privateRoot . '/backups/auth',
        'sessions' => $sessieContext['path'],
        'session_name' => $sessieContext['name'],
        'session_binding' => $sessieContext['binding'],
    ];
    authStorageValideerSecurityBestanden($paden);
    return $paden;
}

function authStorageMaakSchrijfmap(string $bestand): bool
{
    $map = dirname($bestand);
    if (is_dir($map)) return !is_link($map);
    return (@mkdir($map, 0750, true) || is_dir($map)) && !is_link($map);
}
