<?php
// ============================================================
// Fase 4.2 — Apache 2.4 webserver- en vhostcontract
// ============================================================
// Pure helpers. Deze laag genereert uitsluitend deterministische, secretvrije
// Apache-artifacts uit het gevalideerde fase-4.1 runtimeplan. Geen root-acties,
// site-enable, TLS-key of reload vindt hier plaats.
// ============================================================

require_once __DIR__ . '/runtime-contract.php';

function web42CanoniekeHost(string $host): bool
{
    if ($host === '' || strlen($host) > 253 || $host !== strtolower($host)) return false;
    return preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/D', $host) === 1;
}

function web42VeiligeApacheNaam(string $naam): bool
{
    return strlen($naam) >= 1
        && strlen($naam) <= 80
        && preg_match('/^[a-z0-9][a-z0-9.-]*$/D', $naam) === 1;
}

function web42RuntimeContext(string $runtimePlanPad): array
{
    $context = runtime41PlanLeesEnValideer($runtimePlanPad);
    $runtime = $context['plan'];
    $deployment = $context['deployment'];
    $raw = $deployment['raw'] ?? null;
    if (!is_array($raw)) throw new RuntimeException('Deploymentcontext ontbreekt in fase 4.2.');

    $host = (string)($raw['canonical_host'] ?? '');
    if (!web42CanoniekeHost($host)) throw new RuntimeException('deployment.json bevat geen veilige canonieke host.');

    $web = $raw['web'] ?? null;
    if (!is_array($web)
        || !hash_equals($host, (string)($web['canonical_host'] ?? ''))
        || !hash_equals('https://' . $host, (string)($web['http_redirect_target'] ?? ''))
        || ($web['https_required'] ?? false) !== true
        || ($web['redirect_must_not_use_request_host'] ?? false) !== true
        || ($web['reject_unknown_hosts'] ?? false) !== true
        || ($web['default_vhost_must_reject'] ?? false) !== true
        || ($web['serve_only_shared_app_root'] ?? false) !== true
        || ($web['private_root_must_never_be_document_root'] ?? false) !== true
        || ($web['tenant_runtime_selected_by_php_pool'] ?? false) !== true
        || ($web['vcs_metadata_must_not_be_served'] ?? false) !== true) {
        throw new RuntimeException('deployment.json voldoet niet aan het aangescherpte webservercontract.');
    }

    $documentRoot = (string)($raw['shared_code']['document_root'] ?? '');
    if (!hash_equals((string)$deployment['app_root'], $documentRoot)
        || !hash_equals((string)$runtime['filesystem']['shared_code']['path'], $documentRoot)
        || !hash_equals((string)$runtime['filesystem']['shared_code']['real_path'], (string)$deployment['app_root_real'])) {
        throw new RuntimeException('DocumentRoot, runtimeplan en gedeelde release zijn niet exact aan elkaar gebonden.');
    }

    $pool = (string)($runtime['php_fpm']['pool'] ?? '');
    $socket = (string)($runtime['php_fpm']['socket'] ?? '');
    if (!web42VeiligeApacheNaam($pool)
        || !hash_equals((string)$deployment['pool'], $pool)
        || !hash_equals((string)$deployment['socket'], $socket)
        || !str_starts_with($socket, '/run/php/')
        || !str_ends_with($socket, '.sock')) {
        throw new RuntimeException('Fase 4.2 kan de tenant niet veilig aan één PHP-FPM pool/socket binden.');
    }

    $runtimeRaw = @file_get_contents($context['path']);
    if ($runtimeRaw === false) throw new RuntimeException('runtime-plan.json kon niet opnieuw worden gelezen.');

    return [
        'runtime_context' => $context,
        'runtime_plan' => $runtime,
        'runtime_plan_path' => $context['path'],
        'runtime_plan_sha256' => hash('sha256', $runtimeRaw),
        'deployment' => $deployment,
        'tenant_key' => (string)$runtime['tenant_key'],
        'tenant_root' => (string)$runtime['filesystem']['tenant_root']['path'],
        'private_root' => (string)$runtime['filesystem']['private_root']['path'],
        'host' => $host,
        'document_root' => $documentRoot,
        'document_root_real' => (string)$deployment['app_root_real'],
        'pool' => $pool,
        'socket' => $socket,
    ];
}

function web42OutputDir(string $pad, string $tenantRoot): string
{
    if (!runtime41IsAbsoluutPad($pad) || runtime41HeeftRelatieveSegmenten($pad)) {
        throw new RuntimeException('Webserver outputmap moet een absoluut veilig POSIX-pad zijn.');
    }
    $pad = runtime41NormPad($pad);
    $tenantRoot = runtime41NormPad($tenantRoot);
    if (!runtime41Binnen($pad, $tenantRoot) || $pad === $tenantRoot) {
        throw new RuntimeException('Webserver outputmap moet een eigen submap binnen de tenantroot zijn.');
    }
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException("Webserver outputmap mag geen symlink bevatten: {$link}");
    return $pad;
}

function web42Plan(array $context, string $outputDir): array
{
    $outputDir = web42OutputDir($outputDir, $context['tenant_root']);
    $tenantKey = $context['tenant_key'];
    $pool = $context['pool'];

    $catchallNaam = '000-verenigingsplatform-http-catchall.conf';
    $httpNaam = '100-vp-' . $tenantKey . '-http.conf';
    $fragmentNaam = $pool . '.https-routing.inc.conf';

    return [
        'schema' => 1,
        'phase' => '4.2',
        'server' => 'apache2',
        'tenant_key' => $tenantKey,
        'canonical_host' => $context['host'],
        'source' => [
            'runtime_plan_file' => $context['runtime_plan_path'],
            'runtime_plan_sha256' => $context['runtime_plan_sha256'],
            'deployment_file' => $context['deployment']['path'],
            'deployment_sha256' => $context['deployment']['sha256'],
        ],
        'shared_code' => [
            'document_root' => $context['document_root'],
            'real_path' => $context['document_root_real'],
        ],
        'php_fpm' => [
            'pool' => $pool,
            'socket' => $context['socket'],
            'backend' => 'fcgi://' . $pool . '/',
        ],
        'bundle' => [
            'output_dir' => $outputDir,
            'plan_file' => $outputDir . '/web-plan.json',
            'http_catchall_file' => $outputDir . '/' . $catchallNaam,
            'tenant_http_file' => $outputDir . '/' . $httpNaam,
            'https_routing_fragment' => $outputDir . '/' . $fragmentNaam,
        ],
        'apache' => [
            'platform' => 'ubuntu-debian-apache-2.4',
            'minimum_version' => '2.4.49',
            'control_binary' => '/usr/sbin/apache2ctl',
            'sites_available_dir' => '/etc/apache2/sites-available',
            'sites_enabled_dir' => '/etc/apache2/sites-enabled',
            'fragment_dir' => '/etc/verenigingsplatform/apache/fragments',
            'http_catchall_filename' => $catchallNaam,
            'tenant_http_filename' => $httpNaam,
            'https_routing_fragment_filename' => $fragmentNaam,
            'required_modules' => [
                'alias_module',
                'authz_core_module',
                'dir_module',
                'headers_module',
                'proxy_module',
                'proxy_fcgi_module',
                'rewrite_module',
            ],
        ],
        'security' => [
            'exact_server_name_only' => true,
            'server_alias_forbidden' => true,
            'literal_http_redirect' => 'https://' . $context['host'] . '/',
            'request_host_reflection_forbidden' => true,
            'default_http_vhost_must_be_first' => true,
            'strict_host_check_on_default' => true,
            'http_vhost_must_not_route_php' => true,
            'document_root_is_shared_release_only' => true,
            'tenant_private_root_never_served' => true,
            'php_routes_to_own_socket_only' => true,
            'generic_proxy_pass_forbidden' => true,
            'tooling_and_vcs_denied_server_side' => true,
        ],
        'activation' => [
            'artifacts_are_inactive' => true,
            'a2ensite_forbidden_in_phase_4_2' => true,
            'sites_enabled_write_forbidden_in_phase_4_2' => true,
            'reload_or_restart_forbidden_in_phase_4_2' => true,
            'dns_readiness_phase' => '4.3',
            'tls_wrapper_and_certificate_phase' => '4.4',
            'full_configtest_required_before_enable_or_reload' => true,
        ],
    ];
}

function web42Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Webservercontract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function web42ApacheQuote(string $waarde): string
{
    // Apache-regexvoorbeelden gebruiken backslashes rechtstreeks in quoted
    // arguments (zoals "\\.php$"). Verdubbel die dus niet generiek: dat zou
    // de regexbetekenis wijzigen. Quotes/newlines zijn in onze gecontroleerde
    // literals niet nodig en worden fail-closed geweigerd.
    if ($waarde === ''
        || str_contains($waarde, "\0")
        || str_contains($waarde, "\r")
        || str_contains($waarde, "\n")
        || str_contains($waarde, '"')) {
        throw new RuntimeException('Ongeldige Apache-configuratiewaarde.');
    }
    return '"' . $waarde . '"';
}

function web42CatchallConfig(array $plan): string
{
    return implode("\n", [
        '# Gegenereerd door fase 4.2. Globale eerste/default HTTP-vhost.',
        '# Onbekende Host-headers worden nooit naar een tenant of PHP gestuurd.',
        '<VirtualHost *:80>',
        '    ServerName invalid.verenigingsplatform.invalid',
        '    StrictHostCheck On',
        '    ProxyRequests Off',
        '    <Location "/">',
        '        Require all denied',
        '    </Location>',
        '</VirtualHost>',
        '',
    ]);
}

function web42TenantHttpConfig(array $plan): string
{
    $host = $plan['canonical_host'];
    return implode("\n", [
        '# Gegenereerd door fase 4.2. Tenant HTTP-vhost; bevat geen PHP-routing.',
        '<VirtualHost *:80>',
        '    ServerName ' . $host,
        '    ProxyRequests Off',
        '    Redirect permanent "/" ' . web42ApacheQuote('https://' . $host . '/'),
        '</VirtualHost>',
        '',
    ]);
}

function web42HttpsRoutingFragment(array $plan): string
{
    $docroot = $plan['shared_code']['document_root'];
    $docrootParent = dirname($docroot);
    $socket = $plan['php_fpm']['socket'];
    $backend = $plan['php_fpm']['backend'];

    $gevoelig = '^(?:beheer-(?:config\\.php|users\\.json|log\\.json|login-pogingen\\.json)|'
        . 'leden-app\\.php|leden-data\\.php|aanmeldingen-data\\.php|contributies-data\\.php|groepen-data\\.php|ledenlabels-data\\.php|'
        . 'leden-opslag\\.php|aanmeldingen-opslag\\.php|groepen-opslag\\.php|ledenlabels-opslag\\.php|aanmelden-pogingen\\.php|'
        . 'vergaderingen-data\\.php|vergaderingen-opslag\\.php|taken-data\\.php|taken-opslag\\.php|'
        . 'operationele-taken-data\\.php|operationele-taken-opslag\\.php|evenementen-data\\.php|evenementen-opslag\\.php|'
        . 'auth\\.php|data-slot\\.php|vertaal-config\\.php|site-config(?:\\.local)?\\.php|site\\.php|site-seo\\.php|'
        . 'paneel-modules\\.php|module-definities\\.php|changelog-historie\\.php|dev-build\\.json)$';

    return implode("\n", [
        '# Gegenereerd door fase 4.2. Include dit uitsluitend BINNEN de tenant HTTPS-vhost uit fase 4.4.',
        '# TLS/certificaat en de exacte hostbinding worden door de fase-4.4 wrapper geleverd.',
        'UseCanonicalName On',
        'ProxyRequests Off',
        'DocumentRoot ' . web42ApacheQuote($docroot),
        'DirectoryIndex index.php index.html',
        '',
        '<Directory "/">',
        '    Options None',
        '    AllowOverride None',
        '    Require all denied',
        '</Directory>',
        '',
        '<Directory ' . web42ApacheQuote($docrootParent) . '>',
        '    Options +FollowSymLinks',
        '    AllowOverride None',
        '    Require all denied',
        '</Directory>',
        '',
        '<Directory ' . web42ApacheQuote($docroot) . '>',
        '    Options -Indexes -ExecCGI +FollowSymLinks',
        '    AllowOverride All',
        '    Require all granted',
        '</Directory>',
        '',
        '<LocationMatch "^/(?:app|bin|tests|docs|\\.github|\\.git)(?:/|$)">',
        '    Require all denied',
        '</LocationMatch>',
        '',
        '<FilesMatch ' . web42ApacheQuote($gevoelig) . '>',
        '    Require all denied',
        '</FilesMatch>',
        '',
        '<FilesMatch "\\.php$">',
        '    SetHandler ' . web42ApacheQuote('proxy:unix:' . $socket . '|' . $backend),
        '</FilesMatch>',
        '',
    ]);
}

function web42Artifacts(array $plan): array
{
    return [
        $plan['bundle']['http_catchall_file'] => web42CatchallConfig($plan),
        $plan['bundle']['tenant_http_file'] => web42TenantHttpConfig($plan),
        $plan['bundle']['https_routing_fragment'] => web42HttpsRoutingFragment($plan),
    ];
}

function web42PlanLeesEnValideer(string $planPad): array
{
    $planPad = runtime41BestaandPad($planPad, 'web-plan.json');
    $raw = @file_get_contents($planPad);
    if ($raw === false) throw new RuntimeException('web-plan.json kon niet worden gelezen.');
    try {
        $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('web-plan.json bevat ongeldige JSON.');
    }
    if (!is_array($plan)
        || (int)($plan['schema'] ?? 0) !== 1
        || ($plan['phase'] ?? '') !== '4.2'
        || ($plan['server'] ?? '') !== 'apache2') {
        throw new RuntimeException('web-plan.json heeft een onbekend fase-4.2 schema.');
    }

    $runtimePlan = (string)($plan['source']['runtime_plan_file'] ?? '');
    $context = web42RuntimeContext($runtimePlan);
    if (!hash_equals($context['runtime_plan_sha256'], (string)($plan['source']['runtime_plan_sha256'] ?? ''))
        || !hash_equals($context['deployment']['sha256'], (string)($plan['source']['deployment_sha256'] ?? ''))
        || !hash_equals($context['deployment']['path'], (string)($plan['source']['deployment_file'] ?? ''))) {
        throw new RuntimeException('Bron-runtime/deployment is gewijzigd sinds deze webserverbundle is gemaakt.');
    }

    $outputDir = (string)($plan['bundle']['output_dir'] ?? '');
    if (runtime41NormPad(dirname($planPad)) !== runtime41NormPad($outputDir)) {
        throw new RuntimeException('web-plan.json staat niet in zijn gebonden outputmap.');
    }
    $verwacht = web42Plan($context, $outputDir);
    if (!hash_equals(hash('sha256', web42Json($verwacht)), hash('sha256', web42Json($plan)))) {
        throw new RuntimeException('web-plan.json wijkt af van het deterministische fase-4.2 contract.');
    }

    return [
        'plan' => $plan,
        'context' => $context,
        'path' => $planPad,
        'artifacts' => web42Artifacts($plan),
    ];
}
