<?php
// ============================================================
// Fase 4.4 — TLS/HTTPS + ACME contract
// ============================================================
// Pure helpers. Geen root-acties, certificaatuitgifte of reload in deze laag.
// ============================================================

require_once __DIR__ . '/dns-contract.php';

function tls44CertNaam(string $tenantKey): string
{
    if (!runtime41CanoniekeTenantKey($tenantKey)) throw new RuntimeException('Ongeldige tenant-key voor certificaatnaam.');
    return 'vp-' . substr($tenantKey, 0, 24) . '-' . substr(hash('sha256', "tls\0" . $tenantKey), 0, 12);
}

function tls44ReadinessHistorisch(string $pad): array
{
    $pad = runtime41BestaandPad($pad, 'dns-readiness.json');
    $raw = @file_get_contents($pad);
    if ($raw === false) throw new RuntimeException('DNS-readiness kon niet worden gelezen.');
    try { $status = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('DNS-readiness bevat ongeldige JSON.'); }
    if (!is_array($status)) throw new RuntimeException('DNS-readiness is ongeldig.');
    $checked = dns43Utc((string)($status['checked_at_utc'] ?? ''));
    return dns43ReadinessLeesEnValideer($pad, $checked);
}

function tls44Context(string $readinessPad, bool $vers = true): array
{
    $ready = $vers ? dns43ReadinessLeesEnValideer($readinessPad) : tls44ReadinessHistorisch($readinessPad);
    $dns = $ready['plan_context'];
    $webCtx = $dns['context'];
    $web = $webCtx['web']['plan'];
    if (($web['activation']['tls_wrapper_and_certificate_phase'] ?? '') !== '4.4') {
        throw new RuntimeException('Webserverplan is niet correct aan fase 4.4 gebonden.');
    }
    $raw = @file_get_contents($ready['path']);
    if ($raw === false) throw new RuntimeException('DNS-readiness kon niet opnieuw worden gelezen.');
    $tenantKey = (string)$web['tenant_key'];
    $certNaam = tls44CertNaam($tenantKey);
    return [
        'ready' => $ready,
        'readiness_path' => $ready['path'],
        'readiness_sha256' => hash('sha256', $raw),
        'dns' => $dns,
        'web' => $web,
        'web_path' => $webCtx['web_plan_path'],
        'web_sha256' => $webCtx['web_plan_sha256'],
        'tenant_key' => $tenantKey,
        'tenant_root' => (string)$webCtx['tenant_root'],
        'host' => (string)$web['canonical_host'],
        'cert_name' => $certNaam,
        'routing_fragment_source' => (string)$web['bundle']['https_routing_fragment'],
        'routing_fragment_installed' => (string)$web['apache']['fragment_dir'] . '/' . (string)$web['apache']['https_routing_fragment_filename'],
    ];
}

function tls44OutputDir(string $pad, string $tenantRoot): string
{
    if (!runtime41IsAbsoluutPad($pad) || runtime41HeeftRelatieveSegmenten($pad)) throw new RuntimeException('TLS outputmap moet een absoluut veilig POSIX-pad zijn.');
    $pad = runtime41NormPad($pad); $tenantRoot = runtime41NormPad($tenantRoot);
    if (!runtime41Binnen($pad, $tenantRoot) || $pad === $tenantRoot) throw new RuntimeException('TLS outputmap moet een eigen submap binnen de tenantroot zijn.');
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException("TLS outputmap mag geen symlink bevatten: {$link}");
    return $pad;
}

function tls44Plan(array $context, string $outputDir): array
{
    $outputDir = tls44OutputDir($outputDir, $context['tenant_root']);
    $tenant = $context['tenant_key']; $host = $context['host']; $cert = $context['cert_name'];
    $acmeRoot = '/var/lib/verenigingsplatform/acme/' . $tenant;
    $live = '/etc/letsencrypt/live/' . $cert;
    $httpCatch = '000-000-verenigingsplatform-http-catchall.conf';
    $httpsCatch = '000-000-verenigingsplatform-https-catchall.conf';
    $httpTenant = '100-vp-' . $tenant . '-http.conf';
    $httpsTenant = '200-vp-' . $tenant . '-https.conf';
    return [
        'schema' => 1,
        'phase' => '4.4',
        'server' => 'apache2',
        'tenant_key' => $tenant,
        'canonical_host' => $host,
        'source' => [
            'dns_readiness_file' => $context['readiness_path'],
            'dns_readiness_sha256' => $context['readiness_sha256'],
            'dns_plan_sha256' => $context['dns']['sha256'],
            'web_plan_file' => $context['web_path'],
            'web_plan_sha256' => $context['web_sha256'],
        ],
        'acme' => [
            'client' => 'certbot',
            'minimum_version' => '2.0.0',
            'authenticator' => 'webroot',
            'challenge' => 'http-01',
            'cert_name' => $cert,
            'webroot' => $acmeRoot,
            'challenge_uri_prefix' => '/.well-known/acme-challenge/',
            'account_must_preexist' => true,
            'installer_plugin_forbidden' => true,
        ],
        'certificate' => [
            'live_dir' => $live,
            'fullchain' => $live . '/fullchain.pem',
            'privkey' => $live . '/privkey.pem',
            'renewal_conf' => '/etc/letsencrypt/renewal/' . $cert . '.conf',
            'exact_dns_san_only' => true,
            'minimum_remaining_seconds' => 604800,
        ],
        'apache' => [
            'control_binary' => '/usr/sbin/apache2ctl',
            'sites_available_dir' => '/etc/apache2/sites-available',
            'sites_enabled_dir' => '/etc/apache2/sites-enabled',
            'http_catchall_filename' => $httpCatch,
            'https_catchall_filename' => $httpsCatch,
            'tenant_http_filename' => $httpTenant,
            'tenant_https_filename' => $httpsTenant,
            'routing_fragment_installed' => $context['routing_fragment_installed'],
            'default_tls_dir' => '/etc/verenigingsplatform/tls',
            'default_cert' => '/etc/verenigingsplatform/tls/default-reject.crt',
            'default_key' => '/etc/verenigingsplatform/tls/default-reject.key',
            'renewal_hook' => '/etc/letsencrypt/renewal-hooks/deploy/50-verenigingsplatform-apache-reload',
            'required_modules' => array_values(array_unique(array_merge((array)$context['web']['apache']['required_modules'], ['ssl_module']))),
        ],
        'bundle' => [
            'output_dir' => $outputDir,
            'plan_file' => $outputDir . '/tls-plan.json',
            'http_catchall' => $outputDir . '/' . $httpCatch,
            'https_catchall' => $outputDir . '/' . $httpsCatch,
            'tenant_http' => $outputDir . '/' . $httpTenant,
            'tenant_https' => $outputDir . '/' . $httpsTenant,
            'renewal_hook' => $outputDir . '/50-verenigingsplatform-apache-reload',
        ],
        'security' => [
            'tls12_and_tls13_only' => true,
            'tls_compression_off' => true,
            'strict_sni_check' => true,
            'host_and_sni_must_match_tenant' => true,
            'unknown_https_uses_neutral_reject_certificate' => true,
            'hsts_seconds' => 31536000,
            'hsts_include_subdomains' => false,
            'http_only_serves_acme_else_https_redirect' => true,
            'certificate_private_key_never_serialized' => true,
        ],
        'activation' => [
            'fresh_dns_readiness_required_at_apply' => true,
            'fpm_socket_required_before_enable' => true,
            'http_candidate_configtest_before_acme' => true,
            'full_candidate_configtest_before_enable' => true,
            'reload_only_after_successful_configtest' => true,
            'renewal_reload_only_after_successful_configtest' => true,
            'catchalls_must_sort_first' => true,
        ],
    ];
}

function tls44Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('TLS-contract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function tls44ApacheQuote(string $v): string
{
    if ($v === '' || str_contains($v, "\0") || str_contains($v, "\r") || str_contains($v, "\n") || str_contains($v, '"')) throw new RuntimeException('Ongeldige Apache TLS-waarde.');
    return '"' . $v . '"';
}

function tls44HttpCatchall(array $plan): string
{
    return implode("\n", [
        '# Fase 4.4: eerste/default HTTP catch-all.',
        '<VirtualHost *:80>',
        '    ServerName invalid.verenigingsplatform.invalid',
        '    StrictHostCheck On',
        '    ProxyRequests Off',
        '    <Location "/">',
        '        Require all denied',
        '    </Location>',
        '</VirtualHost>', '',
    ]);
}

function tls44TenantHttp(array $plan): string
{
    $host = $plan['canonical_host']; $root = $plan['acme']['webroot'];
    return implode("\n", [
        '# Fase 4.4: alleen HTTP-01 challenge; al het overige naar vaste HTTPS-host.',
        '<VirtualHost *:80>',
        '    ServerName ' . $host,
        '    StrictHostCheck On',
        '    ProxyRequests Off',
        '    DocumentRoot ' . tls44ApacheQuote($root),
        '    <Directory ' . tls44ApacheQuote($root) . '>',
        '        Options None', '        AllowOverride None', '        Require all denied', '    </Directory>',
        '    <Directory ' . tls44ApacheQuote($root . '/.well-known/acme-challenge') . '>',
        '        Options None', '        AllowOverride None', '        Require all granted', '    </Directory>',
        '    RewriteEngine On',
        '    RewriteCond %{REQUEST_URI} !^/\\.well-known/acme-challenge/[A-Za-z0-9_-]+$ [NC]',
        '    RewriteRule ^ https://' . $host . '%{REQUEST_URI} [R=308,L,NE]',
        '</VirtualHost>', '',
    ]);
}

function tls44HttpsCatchall(array $plan): string
{
    return implode("\n", [
        '# Fase 4.4: eerste/default HTTPS catch-all met neutraal reject-certificaat.',
        '<VirtualHost *:443>',
        '    ServerName invalid.verenigingsplatform.invalid',
        '    StrictHostCheck On', '    SSLEngine on', '    SSLStrictSNIVHostCheck On',
        '    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1', '    SSLCompression Off',
        '    SSLCertificateFile ' . tls44ApacheQuote($plan['apache']['default_cert']),
        '    SSLCertificateKeyFile ' . tls44ApacheQuote($plan['apache']['default_key']),
        '    ProxyRequests Off',
        '    <Location "/">', '        Require all denied', '    </Location>',
        '</VirtualHost>', '',
    ]);
}

function tls44TenantHttps(array $plan): string
{
    $host = $plan['canonical_host']; $hostRe = preg_quote($host, '/');
    return implode("\n", [
        '# Fase 4.4: tenant HTTPS-vhost; Host én TLS-SNI moeten exact dezelfde tenant zijn.',
        '<VirtualHost *:443>',
        '    ServerName ' . $host,
        '    StrictHostCheck On', '    SSLEngine on', '    SSLStrictSNIVHostCheck On',
        '    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1', '    SSLCompression Off',
        '    SSLCertificateFile ' . tls44ApacheQuote($plan['certificate']['fullchain']),
        '    SSLCertificateKeyFile ' . tls44ApacheQuote($plan['certificate']['privkey']),
        '    Header always set Strict-Transport-Security "max-age=' . (int)$plan['security']['hsts_seconds'] . '"',
        '    RewriteEngine On',
        '    RewriteCond %{SSL:SSL_TLS_SNI} !^' . $hostRe . '$ [NC,OR]',
        '    RewriteCond %{HTTP_HOST} !^' . $hostRe . '(?::443)?$ [NC]',
        '    RewriteRule ^ - [F,L]',
        '    Include ' . tls44ApacheQuote($plan['apache']['routing_fragment_installed']),
        '</VirtualHost>', '',
    ]);
}

function tls44RenewalHook(array $plan): string
{
    return "#!/bin/sh\nset -eu\n/usr/sbin/apache2ctl configtest\n/usr/bin/systemctl reload apache2\n";
}

function tls44Artifacts(array $plan): array
{
    return [
        $plan['bundle']['http_catchall'] => tls44HttpCatchall($plan),
        $plan['bundle']['https_catchall'] => tls44HttpsCatchall($plan),
        $plan['bundle']['tenant_http'] => tls44TenantHttp($plan),
        $plan['bundle']['tenant_https'] => tls44TenantHttps($plan),
        $plan['bundle']['renewal_hook'] => tls44RenewalHook($plan),
    ];
}

function tls44PlanLeesEnValideer(string $planPad, bool $verseReadiness = false): array
{
    $planPad = runtime41BestaandPad($planPad, 'tls-plan.json');
    $raw = @file_get_contents($planPad);
    if ($raw === false) throw new RuntimeException('tls-plan.json kon niet worden gelezen.');
    try { $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('tls-plan.json bevat ongeldige JSON.'); }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.4' || ($plan['server'] ?? '') !== 'apache2') throw new RuntimeException('tls-plan.json heeft een onbekend fase-4.4 schema.');
    $context = tls44Context((string)($plan['source']['dns_readiness_file'] ?? ''), $verseReadiness);
    if (!hash_equals($context['readiness_sha256'], (string)($plan['source']['dns_readiness_sha256'] ?? ''))
        || !hash_equals($context['dns']['sha256'], (string)($plan['source']['dns_plan_sha256'] ?? ''))
        || !hash_equals($context['web_sha256'], (string)($plan['source']['web_plan_sha256'] ?? ''))
        || !hash_equals($context['web_path'], (string)($plan['source']['web_plan_file'] ?? ''))) {
        throw new RuntimeException('TLS-plan is niet meer aan de actuele DNS/webserverbronnen gebonden.');
    }
    $outputDir = (string)($plan['bundle']['output_dir'] ?? '');
    if (runtime41NormPad(dirname($planPad)) !== runtime41NormPad($outputDir)) throw new RuntimeException('tls-plan.json staat niet in zijn gebonden outputmap.');
    $verwacht = tls44Plan($context, $outputDir);
    if (!hash_equals(hash('sha256', tls44Json($verwacht)), hash('sha256', tls44Json($plan)))) throw new RuntimeException('tls-plan.json wijkt af van het deterministische fase-4.4 contract.');
    return ['plan' => $plan, 'context' => $context, 'path' => $planPad, 'sha256' => hash('sha256', $raw), 'artifacts' => tls44Artifacts($plan)];
}
