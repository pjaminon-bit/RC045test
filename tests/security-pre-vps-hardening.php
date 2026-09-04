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

$cap = spvBron($root . '/app/auth-capabilities.php');
spvCheck(
    str_contains($cap, "if(array_key_exists('tabs',\$r)&&is_array(\$r['tabs']))return authCapabilitiesVanTabs(\$r['tabs']);return[];}")
    && !str_contains($cap, 'authExterneTenantActief()?[]:authLegacyBredeCapabilities()'),
    'ontbrekende capability- en tabvelden geven nooit brede legacyrechten'
);

$sessionStorage = spvBron($root . '/app/auth-storage.php');
$sessionCheck = spvBron($root . '/app/auth-session-check.php');
$sessionTenant = spvBron($root . '/app/auth-session-tenant.php');
spvCheck(
    str_contains($sessionStorage, 'verenigingsplatform-sessions')
    && str_contains($sessionStorage, "'session_binding'")
    && str_contains($sessionStorage, 'authStorageMasterGeneratieVoorPad'),
    'iedere installatie heeft een eigen sessienamespace en binding'
);
spvCheck(
    str_contains($sessionStorage, 'authStorageValideerExterneMaster')
    && str_contains($sessionStorage, 'password_get_info')
    && str_contains($sessionStorage, '$heeftPlaintext'),
    'externe tenantmaster vereist hash-only configuratie'
);
spvCheck(
    str_contains($sessionTenant, 'installation_binding')
    && str_contains($sessionTenant, 'authSessionTenantHerstart')
    && str_contains($sessionCheck, '$authSessionInstallatieBinding'),
    'sessiebewaking trekt tenant/installatiemismatch in'
);
spvCheck(
    str_contains($sessionCheck, '$heeftExplicietTabprofiel')
    && str_contains($sessionCheck, "!empty(\$authPaden['tenant_private'])"),
    'externe tenant vereist expliciet legacy-tabprofiel tijdens autorisatiemigratie'
);

$vertaal = spvBron($root . '/vertaal.php');
spvCheck(
    str_contains($vertaal, "require_once __DIR__ . '/auth.php'")
    && str_contains($vertaal, 'authHeeftCapability')
    && !str_contains($vertaal, 'session_start('),
    'vertaalendpoint gebruikt centrale auth en geen losse sessie'
);
spvCheck(
    str_contains($vertaal, 'HTTP_SEC_FETCH_SITE')
    && str_contains($vertaal, 'HTTP_ORIGIN')
    && str_contains($vertaal, 'strlen($ruw) > 65536')
    && str_contains($vertaal, '$maxAanroepen = 20'),
    'vertaalendpoint begrenst origin, requestgrootte en frequentie'
);
spvCheck(
    str_contains($vertaal, "https://(api-free|api)\\.deepl\\.com")
    && str_contains($vertaal, 'CURLPROTO_HTTPS')
    && str_contains($vertaal, 'CURLOPT_FOLLOWLOCATION => false'),
    'DeepL outbound contract is HTTPS- en hostbegrensd'
);

$signupStore = spvBron($root . '/aanmeldingen-opslag.php');
$signupEndpoint = spvBron($root . '/aanmelden-ontvangst.php');
spvCheck(
    str_contains($signupStore, "DIRECTORY_SEPARATOR.'security'")
    && str_contains($signupStore, 'aanmeldenPogingenPadVeilig')
    && str_contains($signupStore, 'aanmeldenPogingenSchrijf'),
    'publieke signup limiter gebruikt tenant/private security storage'
);
spvCheck(
    str_contains($signupEndpoint, 'aanmeldenPogingRegistreer')
    && str_contains($signupEndpoint, "aanmeldenAntwoord(503"),
    'signup limiter faalt gesloten bij opslagproblemen'
);
spvCheck(
    !str_contains($signupStore, "if(\$status==='nieuw')return true")
    && str_contains($signupStore, 'aanmeldingenBewaardagen()*86400'),
    'ook onbeoordeelde aanmeldingen vallen onder maximale bewaartermijn'
);

$siteConfig = spvBron($root . '/site-config.php');
spvCheck(
    str_contains($siteConfig, "default-src 'self'")
    && str_contains($siteConfig, 'script-src \'self\' \'nonce-{$cspNonce}\'')
    && str_contains($siteConfig, "script-src-attr 'none'")
    && !str_contains($siteConfig, "script-src 'self' 'unsafe-inline'")
    && str_contains($siteConfig, "object-src 'none'")
    && str_contains($siteConfig, "frame-ancestors 'none'"),
    'PHP-responses krijgen een afdwingbare nonce-CSP zonder brede inline scriptrechten'
);
spvCheck(
    str_contains($siteConfig, 'frame-src https://www.openstreetmap.org')
    && substr_count($siteConfig, 'https://www.openstreetmap.org') === 1,
    'OpenStreetMap is uitsluitend als frame toegestaan en krijgt geen scriptrechten'
);
spvCheck(
    str_contains($siteConfig, '$formAction = "\'self\'"')
    && str_contains($siteConfig, '$connectSrc')
    && !str_contains($siteConfig, 'formspree.io'),
    'CSP houdt publieke formulieren en contactdata uitsluitend same-origin'
);

$contactEndpoint = spvBron($root . '/contact-ontvangst.php');
$contactStore = spvBron($root . '/contactberichten-opslag.php');
$contactBeheer = spvBron($root . '/beheer/contactberichten.php');
$contactRuntime = spvBron($root . '/app/core/contact-inbox-runtime.php');
spvCheck(
    str_contains($contactEndpoint, 'aanmeldenPogingRegistreer')
    && str_contains($contactEndpoint, "contactAntwoord(503")
    && str_contains($contactEndpoint, "hash('sha256','contact|'")
    && !str_contains($contactStore, 'REMOTE_ADDR')
    && !str_contains($contactStore, "'ip'=>"),
    'contactendpoint gebruikt private fail-closed limiter zonder raw IP-opslag in inbox'
);
spvCheck(
    str_contains($contactBeheer, "authHeeftCapability('contact.messages.manage', true)")
    && str_contains($contactBeheer, 'csrfOk()')
    && str_contains($contactRuntime, 'contact-ontvangst.php')
    && str_contains($contactRuntime, 'RuntimeException'),
    'contactinbox vereist expliciete gevoelige autorisatie en fail-closed same-origin routing'
);

$workflow = spvBron($root . '/.github/workflows/full-regression.yml');
$deployWorkflow = spvBron($root . '/.github/workflows/deploy-vps-test.yml');
$deployEntry = spvBron($root . '/ops/vps-test-deploy/verenigingsplatform-github-entry');
$e2eInstaller = spvBron($root . '/bin/install-vps-authenticated-e2e-gateway.sh');
spvCheck(
    !str_contains($workflow, 'sshpass')
    && !str_contains($workflow, 'StrictHostKeyChecking=accept-new')
    && !str_contains($workflow, 'FTP_USERNAME')
    && !str_contains($workflow, 'FTP_PASSWORD')
    && !str_contains($deployWorkflow, 'sshpass')
    && !str_contains($deployWorkflow, 'StrictHostKeyChecking=accept-new')
    && !str_contains($deployWorkflow, 'FTP_USERNAME')
    && !str_contains($deployWorkflow, 'FTP_PASSWORD')
    && str_contains($deployWorkflow, 'group: rc045test-vps-test-deploy')
    && str_contains($deployWorkflow, 'cancel-in-progress: false'),
    'authenticated CI muteert geen VPS-data via legacy SFTP/TOFU en blijft met deploy per run geserialiseerd'
);
spvCheck(
    str_contains($deployWorkflow, 'StrictHostKeyChecking=yes')
    && str_contains($deployWorkflow, 'VPS_TEST_SSH_KNOWN_HOSTS')
    && !str_contains($deployWorkflow, 'ssh-keyscan')
    && str_contains($deployWorkflow, "vars.VPS_TEST_DEPLOY_ENABLED == 'true'")
    && str_contains($deployWorkflow, 'environment: vps-test'),
    'VPS-testdeploy vereist gepinde hosttrust, expliciete enable-gate en aparte environment'
);
spvCheck(
    str_contains($deployEntry, 'SSH_ORIGINAL_COMMAND')
    && str_contains($deployEntry, '^deploy[[:space:]]+([0-9a-f]{40})$')
    && str_contains($deployEntry, '/usr/local/sbin/verenigingsplatform-github-deploy')
    && str_contains($e2eInstaller, "'e2e check'")
    && str_contains($e2eInstaller, "'e2e apply'")
    && str_contains($e2eInstaller, "'e2e cleanup'")
    && !str_contains($e2eInstaller, 'verenigingsplatform-github-e2e *'),
    'GitHub SSH-key blijft beperkt tot vaste deploy- en E2E-forced-command allowlist'
);
spvCheck(
    (substr_count($workflow, 'npm ci --ignore-scripts') + substr_count($deployWorkflow, 'npm ci --ignore-scripts')) >= 2,
    'alle browserjobs gebruiken uitsluitend gelockte Node dependencies'
);

$cp = spvBron($root . '/app/control-plane/control-plane-runtime.php');
spvCheck(
    str_contains($cp, "session.use_strict_mode")
    && str_contains($cp, 'operator_identity')
    && str_contains($cp, 'session_regenerate_id(true)'),
    'control-plane sessie is strict en aan REMOTE_USER gebonden'
);

$package = json_decode(spvBron($root . '/package-lock.json'), true);
spvCheck(
    is_array($package)
    && (($package['packages']['node_modules/@playwright/test']['version'] ?? '') === '1.62.1')
    && str_starts_with((string)($package['packages']['node_modules/@playwright/test']['integrity'] ?? ''), 'sha512-'),
    'Playwright is exact gelockt met integriteitshash'
);

$supply = spvBron($root . '/.github/workflows/security-supply-chain.yml');
spvCheck(
    str_contains($supply, 'fetch-depth: 0')
    && str_contains($supply, "versie='8.30.1'")
    && str_contains($supply, '551f6fc83ea457d62a0d98237cbad105af8d557003051f41f3e7ca7b3f2470eb')
    && str_contains($supply, 'sha256sum --check --strict')
    && str_contains($supply, 'git --redact --verbose .')
    && str_contains($supply, 'npm ci --ignore-scripts --no-audit')
    && !str_contains($supply, 'npm audit --audit-level=high')
    && str_contains($supply, "versie='2.5.1'")
    && str_contains($supply, 'f9f25499a2c8cc367b3af45df2ea7eeca7fbccceab9c35079968f4b3652194be')
    && str_contains($supply, 'google/osv-scanner/releases/download/v${versie}/osv-scanner_linux_amd64')
    && str_contains($supply, '"$OSV_SCANNER_BIN" scan -L package-lock.json --format=vertical'),
    'CI scant volledige historie en controleert gelockte dependencies met checksum-gepinde OSV-Scanner'
);

echo "Pre-VPS security hardening: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
