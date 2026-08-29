<?php
// ============================================================
// Fase 5.2 — first-VPS productiebootstrapcontract
// ============================================================
// Pure, secretvrije bron van waarheid voor de eerste productiebootstrap.
// Rootmutaties en live DNS/ACME-acties zitten uitsluitend in bin/.
// ============================================================
require_once __DIR__ . '/control-plane-contract.php';
require_once __DIR__ . '/release-contract.php';

function bootstrap52Host(string $host): string
{
    $host = strtolower(rtrim(trim($host), '.'));
    if (!web42CanoniekeHost($host)) throw new RuntimeException('Ongeldige publieke hostnaam in fase 5.2.');
    return $host;
}

function bootstrap52OutputDir(string $pad, string $sourceRoot): string
{
    $pad = release47VeiligAbsoluut($pad, 'Fase-5.2 outputmap');
    if (runtime41Binnen($pad, $sourceRoot)) throw new RuntimeException('Fase-5.2 outputmap mag niet binnen de releasebron staan.');
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException('Fase-5.2 outputmap bevat een symlink: ' . $link);
    return $pad;
}

function bootstrap52Operator(string $user): string
{
    $user = trim($user);
    if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{2,63}$/D', $user) !== 1) throw new RuntimeException('Ongeldige platformoperatornaam.');
    return $user;
}

function bootstrap52TenantKey(string $key): string
{
    if (!runtime41CanoniekeTenantKey($key)) throw new RuntimeException('Ongeldige eerste tenant-key.');
    return $key;
}

function bootstrap52Modules(string|array $invoer): array
{
    $defs = require dirname(__DIR__) . '/core/platform-definities.php';
    $beschikbaar = array_keys((array)($defs['features'] ?? []));
    if ($beschikbaar === [] || !in_array('website', $beschikbaar, true)) throw new RuntimeException('Platformmoduledefinities zijn incompleet.');
    $gevraagd = is_array($invoer) ? $invoer : explode(',', $invoer);
    $uit = [];
    foreach ($gevraagd as $module) {
        $module = trim((string)$module);
        if ($module === '') continue;
        if (!in_array($module, $beschikbaar, true)) throw new RuntimeException('Onbekende module in fase-5.2 tenantprofiel: ' . $module);
        if (!in_array($module, $uit, true)) $uit[] = $module;
    }
    if (!in_array('website', $uit, true)) throw new RuntimeException("Eerste tenant moet kernmodule 'website' bevatten.");
    return $uit;
}

function bootstrap52Dns(string $strategy, string $ipv4Csv, string $ipv6Csv, string $cname, string $host): array
{
    $strategy = strtolower(trim($strategy));
    if (!in_array($strategy, ['direct','cname'], true)) throw new RuntimeException('DNS-strategie moet direct of cname zijn.');
    $host = bootstrap52Host($host);
    $ipv4 = dns43IpLijst($ipv4Csv, 4);
    $ipv6 = dns43IpLijst($ipv6Csv, 6);
    $cname = trim($cname) === '' ? '' : dns43Naam($cname);
    if ($strategy === 'direct') {
        if ($cname !== '') throw new RuntimeException('Direct DNS-profiel mag geen CNAME bevatten.');
        if ($ipv4 === [] && $ipv6 === []) throw new RuntimeException('Direct DNS-profiel vereist minimaal één adres.');
    } else {
        if ($cname === '' || hash_equals($host, $cname)) throw new RuntimeException('CNAME-profiel vereist een ander canoniek doel.');
        if ($ipv4 === [] && $ipv6 === []) throw new RuntimeException('CNAME-profiel vereist minimaal één verwacht eindadres.');
    }
    return [
        'strategy' => $strategy,
        'expected' => [
            'owner' => [
                'a' => $strategy === 'direct' ? $ipv4 : [],
                'aaaa' => $strategy === 'direct' ? $ipv6 : [],
                'cname' => $strategy === 'cname' ? [$cname] : [],
            ],
            'terminal' => [
                'name' => $strategy === 'cname' ? $cname : $host,
                'a' => $ipv4,
                'aaaa' => $ipv6,
                'cname' => [],
            ],
        ],
    ];
}

function bootstrap52DnsBeoordeel(array $profile, string $host, array $owner, ?array $terminal): array
{
    $plan = ['strategy'=>$profile['strategy'],'canonical_host'=>$host,'expected'=>$profile['expected']];
    return dns43Beoordeel($plan, $owner, $terminal);
}

function bootstrap52Plan(array $in): array
{
    $source = runtime41BestaandPad((string)($in['source'] ?? ''), 'Fase-5.2 releasebron', true);
    $commit = release47Commit((string)($in['commit'] ?? ''));
    $platformRoot = release47VeiligAbsoluut((string)($in['platform_root'] ?? '/srv/verenigingsplatform'), 'Platformroot');
    $tenantBase = release47VeiligAbsoluut((string)($in['tenant_base'] ?? '/srv/verenigingen'), 'Tenantbasis');
    $releasePlan = release47Plan($source, $commit, $platformRoot, $tenantBase);
    $out = bootstrap52OutputDir((string)($in['output_dir'] ?? ''), $source);
    $php = (string)($in['php_version'] ?? '8.5');
    if (!runtime41PhpVersie($php)) throw new RuntimeException('Ongeldige PHP-versie voor fase 5.2.');

    $platformHost = bootstrap52Host((string)($in['platform_host'] ?? ''));
    $tenantHost = bootstrap52Host((string)($in['tenant_host'] ?? ''));
    if (hash_equals($platformHost, $tenantHost)) throw new RuntimeException('Platformbeheer- en tenanthost moeten verschillend zijn.');
    $tenantKey = bootstrap52TenantKey((string)($in['tenant_key'] ?? ''));
    $tenantName = trim((string)($in['tenant_name'] ?? ''));
    if ($tenantName === '' || mb_strlen($tenantName) > 160) throw new RuntimeException('Eerste tenantnaam ontbreekt of is te lang.');
    $operator = bootstrap52Operator((string)($in['operator_user'] ?? ''));
    $modules = bootstrap52Modules($in['modules'] ?? []);
    $certName = trim((string)($in['cert_name'] ?? 'verenigingsplatform-beheer'));
    if (!control51Naam($certName)) throw new RuntimeException('Ongeldige platform Certbot lineage-naam.');

    $platformDns = bootstrap52Dns(
        (string)($in['platform_dns_strategy'] ?? ''),
        (string)($in['platform_ipv4'] ?? ''),
        (string)($in['platform_ipv6'] ?? ''),
        (string)($in['platform_cname'] ?? ''),
        $platformHost
    );
    $tenantDns = bootstrap52Dns(
        (string)($in['tenant_dns_strategy'] ?? ''),
        (string)($in['tenant_ipv4'] ?? ''),
        (string)($in['tenant_ipv6'] ?? ''),
        (string)($in['tenant_cname'] ?? ''),
        $tenantHost
    );

    $stateRoot = '/var/lib/verenigingsplatform/bootstrap';
    $acmeRoot = '/var/lib/verenigingsplatform/acme/control-plane';
    $tenantRoot = $tenantBase . '/' . $tenantKey;
    $httpCatch = '000-000-verenigingsplatform-http-catchall.conf';
    $httpsCatch = '000-000-verenigingsplatform-https-catchall.conf';
    $bootstrapHttp = '050-verenigingsplatform-control-plane-bootstrap-http.conf';

    return [
        'schema' => 1,
        'phase' => '5.2',
        'commit' => $commit,
        'source' => [
            'root' => $source,
            'manifest_sha256' => $releasePlan['source']['manifest_sha256'],
            'file_count' => $releasePlan['source']['file_count'],
            'bytes' => $releasePlan['source']['bytes'],
        ],
        'paths' => [
            'platform_root' => $platformRoot,
            'current' => $platformRoot . '/current',
            'tenant_base' => $tenantBase,
            'tenant_root' => $tenantRoot,
            'output_dir' => $out,
            'state_root' => $stateRoot,
            'state_file' => $stateRoot . '/first-vps-state.json',
            'lock_file' => '/run/lock/verenigingsplatform-first-bootstrap.lock',
            'release_plan' => $out . '/release-plan.json',
            'control_plane_bundle' => $stateRoot . '/control-plane-bundle',
        ],
        'platform' => [
            'host' => $platformHost,
            'php_version' => $php,
            'operator_user' => $operator,
            'cert_name' => $certName,
            'dns' => $platformDns,
            'acme' => [
                'webroot' => $acmeRoot,
                'challenge_dir' => $acmeRoot . '/.well-known/acme-challenge',
                'minimum_samples' => 3,
                'minimum_interval_seconds' => 2,
                'readiness_max_age_seconds' => 900,
                'certbot_minimum_version' => '2.0.0',
                'account_registration_without_email_allowed_for_first_bootstrap' => true,
                'certificate_key_type' => 'ecdsa',
                'elliptic_curve' => 'secp256r1',
            ],
        ],
        'tenant' => [
            'key' => $tenantKey,
            'name' => $tenantName,
            'host' => $tenantHost,
            'url' => 'https://' . $tenantHost,
            'timezone' => 'Europe/Amsterdam',
            'private_driver' => 'pdo',
            'php_version' => $php,
            'modules' => $modules,
            'dns' => $tenantDns,
            'config_file' => $tenantRoot . '/config.php',
            'deployment_file' => $tenantRoot . '/deployment.json',
            'runtime_plan' => $tenantRoot . '/runtime/runtime-plan.json',
            'web_plan' => $tenantRoot . '/webserver/web-plan.json',
            'dns_plan' => $tenantRoot . '/dns/dns-plan.json',
            'dns_readiness' => $tenantRoot . '/dns/dns-readiness.json',
            'tls_plan' => $tenantRoot . '/tls/tls-plan.json',
            'database_plan' => $tenantRoot . '/database/database-plan.json',
            'monitoring_plan' => $tenantRoot . '/monitoring/monitoring-plan.json',
            'lifecycle_plan' => $tenantRoot . '/lifecycle/lifecycle-plan.json',
        ],
        'apache' => [
            'sites_available' => '/etc/apache2/sites-available',
            'sites_enabled' => '/etc/apache2/sites-enabled',
            'http_catchall_filename' => $httpCatch,
            'https_catchall_filename' => $httpsCatch,
            'bootstrap_http_filename' => $bootstrapHttp,
            'bootstrap_http_available' => '/etc/apache2/sites-available/' . $bootstrapHttp,
            'bootstrap_http_enabled' => '/etc/apache2/sites-enabled/' . $bootstrapHttp,
            'default_tls_dir' => '/etc/verenigingsplatform/tls',
            'default_cert' => '/etc/verenigingsplatform/tls/default-reject.crt',
            'default_key' => '/etc/verenigingsplatform/tls/default-reject.key',
            'renewal_hook' => '/etc/letsencrypt/renewal-hooks/deploy/50-verenigingsplatform-apache-reload',
        ],
        'bundle' => [
            'plan_file' => $out . '/first-vps-bootstrap-plan.json',
            'release_plan_file' => $out . '/release-plan.json',
            'http_catchall' => $out . '/' . $httpCatch,
            'https_catchall' => $out . '/' . $httpsCatch,
            'bootstrap_http' => $out . '/' . $bootstrapHttp,
            'renewal_hook' => $out . '/50-verenigingsplatform-apache-reload',
        ],
        'preflight' => [
            'os_family' => 'debian-ubuntu',
            'apache_minimum_version' => '2.4.49',
            'postgresql_minimum_major' => 16,
            'required_php_modules' => ['openssl','pdo_pgsql','mbstring','curl','dom'],
            'packages_are_not_auto_installed' => true,
        ],
        'workflow' => [
            'release_bootstrap_first' => true,
            'platform_dns_before_acme' => true,
            'neutral_tls_catchall_before_platform_certificate' => true,
            'control_plane_before_first_tenant' => true,
            'tenant_database_before_fpm_activation' => true,
            'tenant_tls_before_monitoring' => true,
            'tenant_health_before_lifecycle_adoption' => true,
            'final_platform_smoke_expected_http' => 401,
            'final_tenant_health_expected_http' => 204,
        ],
        'security' => [
            'root_apply_only' => true,
            'no_secrets_in_plan' => true,
            'passwords_stdin_only' => true,
            'provider_credentials_forbidden' => true,
            'dns_provider_writes_forbidden' => true,
            'fixed_argv_no_shell' => true,
            'resume_bound_to_plan_sha256' => true,
            'neutral_unknown_sni_required' => true,
        ],
    ];
}

function bootstrap52Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Fase-5.2 data kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function bootstrap52HttpCatchall(array $plan): string
{
    return tls44HttpCatchall(['apache'=>[]]);
}

function bootstrap52HttpsCatchall(array $plan): string
{
    return tls44HttpsCatchall(['apache'=>[
        'default_cert'=>$plan['apache']['default_cert'],
        'default_key'=>$plan['apache']['default_key'],
    ]]);
}

function bootstrap52BootstrapHttp(array $plan): string
{
    $host = $plan['platform']['host'];
    $root = $plan['platform']['acme']['webroot'];
    $challenge = $plan['platform']['acme']['challenge_dir'];
    return implode("\n", [
        '# Fase 5.2 — tijdelijke platformbeheer HTTP-01 vhost.',
        '<VirtualHost *:80>',
        '    ServerName ' . $host,
        '    StrictHostCheck On',
        '    ProxyRequests Off',
        '    DocumentRoot "' . $root . '"',
        '    <Directory "' . $root . '">',
        '        Options None',
        '        AllowOverride None',
        '        Require all denied',
        '    </Directory>',
        '    <Directory "' . $challenge . '">',
        '        Options None',
        '        AllowOverride None',
        '        Require all granted',
        '    </Directory>',
        '    RewriteEngine On',
        '    RewriteCond %{REQUEST_URI} !^/\\.well-known/acme-challenge/[A-Za-z0-9_-]+$ [NC]',
        '    RewriteRule ^ - [R=404,L]',
        '</VirtualHost>',
        '',
    ]);
}

function bootstrap52Artifacts(array $plan): array
{
    return [
        $plan['bundle']['release_plan_file'] => release47Json(release47Plan(
            $plan['source']['root'], $plan['commit'], $plan['paths']['platform_root'], $plan['paths']['tenant_base']
        )),
        $plan['bundle']['http_catchall'] => bootstrap52HttpCatchall($plan),
        $plan['bundle']['https_catchall'] => bootstrap52HttpsCatchall($plan),
        $plan['bundle']['bootstrap_http'] => bootstrap52BootstrapHttp($plan),
        $plan['bundle']['renewal_hook'] => tls44RenewalHook(['apache'=>[]]),
    ];
}

function bootstrap52PlanLeesEnValideer(string $pad): array
{
    $pad = runtime41BestaandPad($pad, 'first-vps-bootstrap-plan.json');
    $raw = @file_get_contents($pad);
    if (!is_string($raw)) throw new RuntimeException('Fase-5.2 plan kon niet worden gelezen.');
    try { $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('Fase-5.2 plan bevat ongeldige JSON.'); }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '5.2') throw new RuntimeException('Onbekend fase-5.2 planschema.');
    $in = [
        'source'=>$plan['source']['root'], 'commit'=>$plan['commit'], 'platform_root'=>$plan['paths']['platform_root'],
        'tenant_base'=>$plan['paths']['tenant_base'], 'output_dir'=>$plan['paths']['output_dir'],
        'platform_host'=>$plan['platform']['host'], 'php_version'=>$plan['platform']['php_version'],
        'operator_user'=>$plan['platform']['operator_user'], 'cert_name'=>$plan['platform']['cert_name'],
        'platform_dns_strategy'=>$plan['platform']['dns']['strategy'],
        'platform_ipv4'=>implode(',', (array)$plan['platform']['dns']['expected']['terminal']['a']),
        'platform_ipv6'=>implode(',', (array)$plan['platform']['dns']['expected']['terminal']['aaaa']),
        'platform_cname'=>(string)($plan['platform']['dns']['expected']['owner']['cname'][0] ?? ''),
        'tenant_key'=>$plan['tenant']['key'], 'tenant_name'=>$plan['tenant']['name'], 'tenant_host'=>$plan['tenant']['host'],
        'modules'=>$plan['tenant']['modules'], 'tenant_dns_strategy'=>$plan['tenant']['dns']['strategy'],
        'tenant_ipv4'=>implode(',', (array)$plan['tenant']['dns']['expected']['terminal']['a']),
        'tenant_ipv6'=>implode(',', (array)$plan['tenant']['dns']['expected']['terminal']['aaaa']),
        'tenant_cname'=>(string)($plan['tenant']['dns']['expected']['owner']['cname'][0] ?? ''),
    ];
    $expected = bootstrap52Plan($in);
    if (!hash_equals(bootstrap52Json($expected), bootstrap52Json($plan))) throw new RuntimeException('Fase-5.2 plan wijkt af van het deterministische contract of de releasebron.');
    if (!hash_equals(runtime41NormPad(dirname($pad)), runtime41NormPad((string)$plan['paths']['output_dir']))) throw new RuntimeException('Fase-5.2 plan staat niet in zijn gebonden outputmap.');
    foreach (bootstrap52Artifacts($plan) as $file=>$inhoud) {
        if (runtime41SymlinkInPad($file) !== null || !is_file($file)) throw new RuntimeException('Fase-5.2 artifact ontbreekt of is onveilig: ' . $file);
        $actual = @file_get_contents($file);
        if (!is_string($actual) || !hash_equals(hash('sha256',$inhoud), hash('sha256',$actual))) throw new RuntimeException('Fase-5.2 artifact wijkt af: ' . basename($file));
    }
    return ['path'=>$pad,'sha256'=>hash('sha256',$raw),'plan'=>$plan,'artifacts'=>bootstrap52Artifacts($plan)];
}
