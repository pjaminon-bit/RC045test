<?php
// ============================================================
// Fase 4.3 — DNS-plan en readinesscontract
// ============================================================
// Pure helpers voor een tenantgebonden DNS-plan en live readinesscontrole.
// Geen DNS-providerwrites, secrets, TLS-acties of webserveractivatie.
// ============================================================

require_once __DIR__ . '/webserver-contract.php';

function dns43Naam(string $naam): string
{
    $naam = strtolower(rtrim(trim($naam), '.'));
    if (!web42CanoniekeHost($naam)) {
        throw new RuntimeException('DNS-naam is niet canoniek of veilig.');
    }
    return $naam;
}

function dns43Ip(string $ip, int $family): string
{
    $ip = trim($ip);
    $flag = $family === 4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, $flag) === false) {
        throw new RuntimeException('Ongeldig IPv' . $family . '-adres in DNS-plan.');
    }
    $bin = @inet_pton($ip);
    $norm = $bin === false ? false : @inet_ntop($bin);
    if (!is_string($norm) || $norm === '') throw new RuntimeException('IP-adres kon niet canoniek worden gemaakt.');
    return strtolower($norm);
}

function dns43IpLijst(string $csv, int $family): array
{
    if (trim($csv) === '') return [];
    $uit = [];
    foreach (explode(',', $csv) as $deel) {
        $ip = dns43Ip($deel, $family);
        $uit[$ip] = true;
    }
    $lijst = array_keys($uit);
    sort($lijst, SORT_STRING);
    return $lijst;
}

function dns43WebContext(string $webPlanPad): array
{
    $web = web42PlanLeesEnValideer($webPlanPad);
    $raw = @file_get_contents($web['path']);
    if ($raw === false) throw new RuntimeException('web-plan.json kon niet opnieuw worden gelezen.');
    $plan = $web['plan'];
    if (($plan['activation']['dns_readiness_phase'] ?? '') !== '4.3'
        || ($plan['activation']['artifacts_are_inactive'] ?? false) !== true) {
        throw new RuntimeException('Webserverplan is niet correct aan fase 4.3 gebonden.');
    }
    return [
        'web' => $web,
        'web_plan_path' => $web['path'],
        'web_plan_sha256' => hash('sha256', $raw),
        'tenant_key' => (string)$plan['tenant_key'],
        'tenant_root' => (string)$web['context']['tenant_root'],
        'canonical_host' => dns43Naam((string)$plan['canonical_host']),
    ];
}

function dns43OutputDir(string $pad, string $tenantRoot): string
{
    if (!runtime41IsAbsoluutPad($pad) || runtime41HeeftRelatieveSegmenten($pad)) {
        throw new RuntimeException('DNS outputmap moet een absoluut veilig POSIX-pad zijn.');
    }
    $pad = runtime41NormPad($pad);
    $tenantRoot = runtime41NormPad($tenantRoot);
    if (!runtime41Binnen($pad, $tenantRoot) || $pad === $tenantRoot) {
        throw new RuntimeException('DNS outputmap moet een eigen submap binnen de tenantroot zijn.');
    }
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException("DNS outputmap mag geen symlink bevatten: {$link}");
    return $pad;
}

function dns43Plan(array $context, string $outputDir, string $strategy, array $ipv4, array $ipv6, string $cname): array
{
    $strategy = strtolower(trim($strategy));
    if (!in_array($strategy, ['direct', 'cname'], true)) {
        throw new RuntimeException('DNS-strategie moet direct of cname zijn.');
    }
    $outputDir = dns43OutputDir($outputDir, $context['tenant_root']);
    $host = $context['canonical_host'];
    $ipv4 = array_values(array_unique(array_map(fn($v) => dns43Ip((string)$v, 4), $ipv4)));
    $ipv6 = array_values(array_unique(array_map(fn($v) => dns43Ip((string)$v, 6), $ipv6)));
    sort($ipv4, SORT_STRING); sort($ipv6, SORT_STRING);
    $cname = trim($cname) === '' ? '' : dns43Naam($cname);

    if ($strategy === 'direct') {
        if ($cname !== '') throw new RuntimeException('Direct DNS-profiel mag geen CNAME bevatten.');
        if ($ipv4 === [] && $ipv6 === []) throw new RuntimeException('Direct DNS-profiel vereist minimaal één A- of AAAA-doel.');
    } else {
        if ($cname === '' || hash_equals($host, $cname)) throw new RuntimeException('CNAME-profiel vereist een ander canoniek doel.');
        if ($ipv4 === [] && $ipv6 === []) throw new RuntimeException('CNAME-profiel vereist minimaal één verwacht eindadres.');
    }

    return [
        'schema' => 1,
        'phase' => '4.3',
        'tenant_key' => $context['tenant_key'],
        'canonical_host' => $host,
        'source' => [
            'web_plan_file' => $context['web_plan_path'],
            'web_plan_sha256' => $context['web_plan_sha256'],
        ],
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
        'rules' => [
            'exact_rrset_match' => true,
            'unexpected_ipv4_forbidden' => true,
            'unexpected_ipv6_forbidden' => true,
            'mixed_cname_and_address_forbidden' => true,
            'cname_chain_depth' => $strategy === 'cname' ? 1 : 0,
            'live_system_resolver_required_for_readiness' => true,
            'readiness_max_age_seconds' => 900,
        ],
        'bundle' => [
            'output_dir' => $outputDir,
            'plan_file' => $outputDir . '/dns-plan.json',
            'readiness_file' => $outputDir . '/dns-readiness.json',
        ],
        'next' => [
            'tls_phase' => '4.4',
            'fresh_ready_status_required_before_tls' => true,
        ],
    ];
}

function dns43Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('DNS-contract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function dns43PlanLeesEnValideer(string $planPad): array
{
    $planPad = runtime41BestaandPad($planPad, 'dns-plan.json');
    $raw = @file_get_contents($planPad);
    if ($raw === false) throw new RuntimeException('dns-plan.json kon niet worden gelezen.');
    try { $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('dns-plan.json bevat ongeldige JSON.'); }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.3') {
        throw new RuntimeException('dns-plan.json heeft een onbekend fase-4.3 schema.');
    }

    $context = dns43WebContext((string)($plan['source']['web_plan_file'] ?? ''));
    if (!hash_equals($context['web_plan_sha256'], (string)($plan['source']['web_plan_sha256'] ?? ''))) {
        throw new RuntimeException('web-plan.json is gewijzigd sinds dit DNS-plan is gemaakt.');
    }
    $outputDir = (string)($plan['bundle']['output_dir'] ?? '');
    if (runtime41NormPad(dirname($planPad)) !== runtime41NormPad($outputDir)) {
        throw new RuntimeException('dns-plan.json staat niet in zijn gebonden outputmap.');
    }
    $expected = $plan['expected'] ?? [];
    $strategy = (string)($plan['strategy'] ?? '');
    $ipv4 = (array)($expected['terminal']['a'] ?? []);
    $ipv6 = (array)($expected['terminal']['aaaa'] ?? []);
    $cname = $strategy === 'cname' ? (string)(($expected['owner']['cname'][0] ?? '')) : '';
    $verwacht = dns43Plan($context, $outputDir, $strategy, $ipv4, $ipv6, $cname);
    if (!hash_equals(hash('sha256', dns43Json($verwacht)), hash('sha256', dns43Json($plan)))) {
        throw new RuntimeException('dns-plan.json wijkt af van het deterministische fase-4.3 contract.');
    }
    return ['plan' => $plan, 'context' => $context, 'path' => $planPad, 'sha256' => hash('sha256', $raw)];
}

function dns43Observatie(array $records): array
{
    $a = []; $aaaa = []; $cname = []; $ttls = [];
    foreach ($records as $record) {
        if (!is_array($record)) continue;
        $type = strtoupper((string)($record['type'] ?? ''));
        if (isset($record['ttl']) && is_numeric($record['ttl'])) $ttls[] = max(0, (int)$record['ttl']);
        if ($type === 'A' && isset($record['ip'])) $a[dns43Ip((string)$record['ip'], 4)] = true;
        elseif ($type === 'AAAA' && isset($record['ipv6'])) $aaaa[dns43Ip((string)$record['ipv6'], 6)] = true;
        elseif ($type === 'CNAME' && isset($record['target'])) $cname[dns43Naam((string)$record['target'])] = true;
    }
    $a = array_keys($a); $aaaa = array_keys($aaaa); $cname = array_keys($cname);
    sort($a, SORT_STRING); sort($aaaa, SORT_STRING); sort($cname, SORT_STRING);
    return ['a' => $a, 'aaaa' => $aaaa, 'cname' => $cname, 'ttl_min' => $ttls === [] ? null : min($ttls)];
}

function dns43Resolve(string $naam): array
{
    $naam = dns43Naam($naam);
    $records = @dns_get_record($naam, DNS_A | DNS_AAAA | DNS_CNAME);
    if ($records === false) throw new RuntimeException("DNS-query mislukt voor {$naam}.");
    return dns43Observatie($records);
}

function dns43RrsetsGelijk(array $a, array $b): bool
{
    $a = array_values($a); $b = array_values($b);
    sort($a, SORT_STRING); sort($b, SORT_STRING);
    return $a === $b;
}

function dns43Beoordeel(array $plan, array $owner, ?array $terminal = null): array
{
    $fouten = [];
    $eOwner = $plan['expected']['owner'];
    foreach (['a', 'aaaa', 'cname'] as $type) {
        if (!dns43RrsetsGelijk((array)($owner[$type] ?? []), (array)($eOwner[$type] ?? []))) {
            $fouten[] = "Owner {$type}-RRset wijkt af van het DNS-plan.";
        }
    }
    if (($plan['strategy'] ?? '') === 'cname') {
        if ($terminal === null) $fouten[] = 'Terminale CNAME-resolutie ontbreekt.';
        else {
            $eTerm = $plan['expected']['terminal'];
            foreach (['a', 'aaaa', 'cname'] as $type) {
                if (!dns43RrsetsGelijk((array)($terminal[$type] ?? []), (array)($eTerm[$type] ?? []))) {
                    $fouten[] = "Terminal {$type}-RRset wijkt af van het DNS-plan.";
                }
            }
        }
    }
    return ['ready' => $fouten === [], 'errors' => $fouten];
}
