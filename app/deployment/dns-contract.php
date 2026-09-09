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
    if (!web42CanoniekeHost($naam)) throw new RuntimeException('DNS-naam is niet canoniek of veilig.');
    return $naam;
}

function dns43Ip(string $ip, int $family): string
{
    $ip = trim($ip);
    $flag = $family === 4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, $flag) === false) {
        throw new RuntimeException('Ongeldig IPv' . $family . '-adres in DNS-plan.');
    }
    $bin = @inet_pton($ip); $norm = $bin === false ? false : @inet_ntop($bin);
    if (!is_string($norm) || $norm === '') throw new RuntimeException('IP-adres kon niet canoniek worden gemaakt.');
    return strtolower($norm);
}

function dns43IpLijst(string $csv, int $family): array
{
    if (trim($csv) === '') return [];
    $uit = [];
    foreach (explode(',', $csv) as $deel) $uit[dns43Ip($deel, $family)] = true;
    $lijst = array_keys($uit); sort($lijst, SORT_STRING); return $lijst;
}

function dns43ResolverContext(string $mode = 'system', ?string $endpoint = null, int $port = 53): array
{
    $mode = strtolower(trim($mode));
    if ($mode === 'system') {
        if ($endpoint !== null && trim($endpoint) !== '') throw new RuntimeException('System resolver mag geen expliciet endpoint bevatten.');
        if ($port !== 53) throw new RuntimeException('System resolver gebruikt het platform-default DNS-poortcontract.');
        return ['mode' => 'system', 'endpoint' => null, 'port' => 53];
    }
    if ($mode === 'doh') {
        $endpoint = strtolower(rtrim(trim((string)$endpoint), '.'));
        if ($endpoint !== 'cloudflare-dns.com') {
            throw new RuntimeException('DoH-resolverendpoint is niet toegestaan.');
        }
        if ($port !== 443) throw new RuntimeException('DoH-resolver gebruikt uitsluitend HTTPS-poort 443.');
        return ['mode' => 'doh', 'endpoint' => $endpoint, 'port' => 443];
    }
    if ($mode !== 'explicit') throw new RuntimeException('DNS-resolvermodus moet system, explicit of doh zijn.');
    $endpoint = trim((string)$endpoint);
    if ($endpoint === '' || filter_var($endpoint, FILTER_VALIDATE_IP) === false) {
        throw new RuntimeException('Expliciete DNS-resolver vereist een geldig IP-adres.');
    }
    $bin = @inet_pton($endpoint); $norm = $bin === false ? false : @inet_ntop($bin);
    if (!is_string($norm) || $norm === '') throw new RuntimeException('DNS-resolver-IP kon niet canoniek worden gemaakt.');
    if ($port < 1 || $port > 65535) throw new RuntimeException('DNS-resolverpoort moet tussen 1 en 65535 liggen.');
    return ['mode' => 'explicit', 'endpoint' => strtolower($norm), 'port' => $port];
}

function dns43ResolverContextVanCli(string $resolver, ?int $port = null): array
{
    $resolver = trim($resolver); $lower = strtolower($resolver);
    if ($lower === 'system') return dns43ResolverContext('system', null, $port ?? 53);
    if ($lower === 'doh:cloudflare' || $lower === 'doh:cloudflare-dns.com') {
        return dns43ResolverContext('doh', 'cloudflare-dns.com', $port ?? 443);
    }
    return dns43ResolverContext('explicit', $resolver, $port ?? 53);
}

function dns43ResolverContextValideer(array $resolver): array
{
    return dns43ResolverContext(
        (string)($resolver['mode'] ?? ''),
        isset($resolver['endpoint']) && $resolver['endpoint'] !== null ? (string)$resolver['endpoint'] : null,
        (int)($resolver['port'] ?? 0)
    );
}

function dns43ResolverContextHash(array $resolver): string
{
    return hash('sha256', dns43Json(dns43ResolverContextValideer($resolver)));
}

function dns43ResolverScope(array $resolver): string
{
    $resolver = dns43ResolverContextValideer($resolver);
    return match ($resolver['mode']) {
        'system' => 'configured-system-resolver',
        'explicit' => 'explicit-public-resolver',
        'doh' => 'doh-public-resolver',
        default => throw new RuntimeException('Onbekende DNS-resolvercontext.'),
    };
}

function dns43ResolverContextBind(array $resolver): array
{
    $resolver = dns43ResolverContextValideer($resolver);
    $hash = dns43ResolverContextHash($resolver);
    $key = '__dns43_bound_resolver_context';
    if (isset($GLOBALS[$key]) && is_array($GLOBALS[$key])) {
        $bestaand = dns43ResolverContextValideer($GLOBALS[$key]);
        if (!hash_equals(dns43ResolverContextHash($bestaand), $hash)) {
            throw new RuntimeException('DNS-resolvercontext drift binnen dezelfde faseketen.');
        }
        return $bestaand;
    }
    $GLOBALS[$key] = $resolver;
    return $resolver;
}

function dns43ResolverContextActief(): array
{
    $key = '__dns43_bound_resolver_context';
    return isset($GLOBALS[$key]) && is_array($GLOBALS[$key])
        ? dns43ResolverContextValideer($GLOBALS[$key])
        : dns43ResolverContext();
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
    $pad = runtime41NormPad($pad); $tenantRoot = runtime41NormPad($tenantRoot);
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
    if (!in_array($strategy, ['direct', 'cname'], true)) throw new RuntimeException('DNS-strategie moet direct of cname zijn.');
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
            'resolver_context_bound_readiness' => true,
            'minimum_readiness_samples' => 3,
            'minimum_sample_interval_seconds' => 2,
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
    if (runtime41NormPad(dirname($planPad)) !== runtime41NormPad($outputDir)) throw new RuntimeException('dns-plan.json staat niet in zijn gebonden outputmap.');
    $expected = $plan['expected'] ?? []; $strategy = (string)($plan['strategy'] ?? '');
    $ipv4 = (array)($expected['terminal']['a'] ?? []); $ipv6 = (array)($expected['terminal']['aaaa'] ?? []);
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

function dns43DnsNaamEncodeer(string $naam): string
{
    $uit = '';
    foreach (explode('.', dns43Naam($naam)) as $label) {
        $len = strlen($label);
        if ($len < 1 || $len > 63) throw new RuntimeException('DNS-label heeft ongeldige lengte.');
        $uit .= chr($len) . $label;
    }
    return $uit . "\0";
}

function dns43DnsNaamLees(string $packet, int &$offset, int $depth = 0): string
{
    if ($depth > 20) throw new RuntimeException('DNS-compressieketen is te diep.');
    $labels = []; $len = strlen($packet);
    while (true) {
        if ($offset >= $len) throw new RuntimeException('DNS-response bevat een afgekapt naamveld.');
        $octet = ord($packet[$offset]);
        if ($octet === 0) { $offset++; break; }
        if (($octet & 0xC0) === 0xC0) {
            if ($offset + 1 >= $len) throw new RuntimeException('DNS-response bevat een afgekorte compressiepointer.');
            $pointer = (($octet & 0x3F) << 8) | ord($packet[$offset + 1]);
            $offset += 2; $p = $pointer;
            $suffix = dns43DnsNaamLees($packet, $p, $depth + 1);
            if ($suffix !== '') $labels[] = $suffix;
            break;
        }
        if (($octet & 0xC0) !== 0 || $octet > 63 || $offset + 1 + $octet > $len) {
            throw new RuntimeException('DNS-response bevat een ongeldig naamveld.');
        }
        $offset++; $labels[] = substr($packet, $offset, $octet); $offset += $octet;
    }
    return strtolower(implode('.', $labels));
}

function dns43ExplicieteQuery(string $naam, int $type, array $resolver): array
{
    $resolver = dns43ResolverContextValideer($resolver);
    if ($resolver['mode'] !== 'explicit') throw new RuntimeException('Expliciete DNS-query vereist explicit resolvercontext.');
    $qtype = match ($type) { DNS_A => 1, DNS_AAAA => 28, DNS_CNAME => 5, default => throw new RuntimeException('Niet-ondersteund DNS-querytype.') };
    $id = random_int(1, 65535);
    $query = pack('nnnnnn', $id, 0x0100, 1, 0, 0, 0) . dns43DnsNaamEncodeer($naam) . pack('nn', $qtype, 1);
    $ep = str_contains((string)$resolver['endpoint'], ':') ? '[' . $resolver['endpoint'] . ']' : $resolver['endpoint'];
    $errno = 0; $errstr = '';
    $socket = @stream_socket_client('udp://' . $ep . ':' . $resolver['port'], $errno, $errstr, 3, STREAM_CLIENT_CONNECT);
    if (!is_resource($socket)) throw new RuntimeException('Expliciete DNS-resolver is niet bereikbaar.');
    stream_set_timeout($socket, 3);
    try {
        if (@fwrite($socket, $query) !== strlen($query)) throw new RuntimeException('DNS-query kon niet volledig worden verzonden.');
        $packet = @fread($socket, 4096);
        $meta = stream_get_meta_data($socket);
    } finally { fclose($socket); }
    if (!is_string($packet) || strlen($packet) < 12 || ($meta['timed_out'] ?? false)) throw new RuntimeException('Expliciete DNS-query gaf geen geldige response.');
    $head = unpack('nid/nflags/nqd/nan/nns/nar', substr($packet, 0, 12));
    if (!is_array($head) || (int)$head['id'] !== $id || (((int)$head['flags'] & 0x8000) === 0)) throw new RuntimeException('DNS-response hoort niet bij de uitgevoerde query.');
    if (((int)$head['flags'] & 0x0200) !== 0) throw new RuntimeException('DNS-response is truncated; readiness faalt gesloten.');
    $rcode = (int)$head['flags'] & 0x000F;
    if ($rcode !== 0) throw new RuntimeException('DNS-resolver antwoordde met foutcode ' . $rcode . '.');
    $offset = 12;
    for ($i = 0; $i < (int)$head['qd']; $i++) {
        dns43DnsNaamLees($packet, $offset);
        if ($offset + 4 > strlen($packet)) throw new RuntimeException('DNS-response bevat een afgekapt questionveld.');
        $offset += 4;
    }
    $records = []; $queryNaam = dns43Naam($naam);
    for ($i = 0; $i < (int)$head['an']; $i++) {
        $owner = dns43DnsNaamLees($packet, $offset);
        if ($offset + 10 > strlen($packet)) throw new RuntimeException('DNS-response bevat een afgekapt answerheader.');
        $rr = unpack('ntype/nclass/Nttl/nrdlen', substr($packet, $offset, 10)); $offset += 10;
        $rdlen = (int)$rr['rdlen']; $rstart = $offset;
        if ($offset + $rdlen > strlen($packet)) throw new RuntimeException('DNS-response bevat afgekapt answerdata.');
        if ((int)$rr['class'] === 1 && hash_equals($queryNaam, $owner) && (int)$rr['type'] === $qtype) {
            if ($qtype === 1 && $rdlen === 4) {
                $ip = @inet_ntop(substr($packet, $offset, 4));
                if (is_string($ip)) $records[] = ['type'=>'A','ip'=>$ip,'ttl'=>(int)$rr['ttl']];
            } elseif ($qtype === 28 && $rdlen === 16) {
                $ip = @inet_ntop(substr($packet, $offset, 16));
                if (is_string($ip)) $records[] = ['type'=>'AAAA','ipv6'=>$ip,'ttl'=>(int)$rr['ttl']];
            } elseif ($qtype === 5) {
                $nameOffset = $offset; $target = dns43DnsNaamLees($packet, $nameOffset);
                if ($target !== '') $records[] = ['type'=>'CNAME','target'=>$target,'ttl'=>(int)$rr['ttl']];
            }
        }
        $offset = $rstart + $rdlen;
    }
    return $records;
}

function dns43DohQuery(string $naam, int $type, array $resolver, ?callable $fetch = null): array
{
    $resolver = dns43ResolverContextValideer($resolver);
    if ($resolver['mode'] !== 'doh') throw new RuntimeException('DoH-query vereist doh resolvercontext.');
    $qtype = match ($type) { DNS_A => 1, DNS_AAAA => 28, DNS_CNAME => 5, default => throw new RuntimeException('Niet-ondersteund DNS-querytype.') };
    $naam = dns43Naam($naam);
    $url = 'https://' . $resolver['endpoint'] . '/dns-query?name=' . rawurlencode($naam) . '&type=' . $qtype;

    if ($fetch !== null) {
        $response = $fetch($url, $resolver);
        if (!is_array($response) || (int)($response['status'] ?? 0) !== 200 || !is_string($response['body'] ?? null)) {
            throw new RuntimeException('DoH-testfetch gaf geen geldige HTTPS-response.');
        }
        $raw = $response['body'];
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/dns-json\r\nUser-Agent: RC045test-DNS43\r\nConnection: close\r\n",
                'timeout' => 5,
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
            ],
        ]);
        $handle = @fopen($url, 'rb', false, $context);
        if (!is_resource($handle)) throw new RuntimeException('DoH-resolver is niet via gevalideerde HTTPS bereikbaar.');
        try {
            $meta = stream_get_meta_data($handle);
            $raw = stream_get_contents($handle);
        } finally { fclose($handle); }
        $headers = (array)($meta['wrapper_data'] ?? []);
        $status = (string)($headers[0] ?? '');
        if (preg_match('#^HTTP/\\S+\\s+200(?:\\s|$)#', $status) !== 1 || !is_string($raw)) {
            throw new RuntimeException('DoH-resolver gaf geen HTTP 200-response.');
        }
    }

    try { $json = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('DoH-resolver gaf ongeldige JSON.'); }
    if (!is_array($json) || (int)($json['Status'] ?? -1) !== 0) {
        throw new RuntimeException('DoH-resolver antwoordde met een DNS-foutstatus.');
    }

    $records = [];
    foreach ((array)($json['Answer'] ?? []) as $rr) {
        if (!is_array($rr)) throw new RuntimeException('DoH-response bevat een ongeldig answerrecord.');
        $ownerRaw = (string)($rr['name'] ?? '');
        $data = rtrim(trim((string)($rr['data'] ?? '')), '.');
        $rrType = (int)($rr['type'] ?? -1);
        $ttl = (int)($rr['TTL'] ?? $rr['ttl'] ?? 0);
        if ($ownerRaw === '' || $ttl < 0) throw new RuntimeException('DoH-response bevat een ongeldig answerrecord.');
        $owner = dns43Naam($ownerRaw);
        if (!hash_equals($naam, $owner) || $rrType !== $qtype) continue;
        if ($qtype === 1) {
            if (filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) throw new RuntimeException('DoH-response bevat een ongeldig A-record.');
            $records[] = ['type'=>'A','ip'=>dns43Ip($data,4),'ttl'=>$ttl];
        } elseif ($qtype === 28) {
            if (filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) throw new RuntimeException('DoH-response bevat een ongeldig AAAA-record.');
            $records[] = ['type'=>'AAAA','ipv6'=>dns43Ip($data,6),'ttl'=>$ttl];
        } elseif ($qtype === 5) {
            if ($data === '') throw new RuntimeException('DoH-response bevat een leeg CNAME-record.');
            $records[] = ['type'=>'CNAME','target'=>dns43Naam($data),'ttl'=>$ttl];
        }
    }
    return $records;
}

function dns43Resolve(string $naam, ?array $resolver = null, ?callable $query = null): array
{
    $naam = dns43Naam($naam); $resolver = dns43ResolverContextValideer($resolver ?? dns43ResolverContextActief());
    if ($query !== null) {
        $records = $query($naam, $resolver);
        if (!is_array($records)) throw new RuntimeException('DNS-testquery gaf geen recordlijst terug.');
        return dns43Observatie($records);
    }
    if ($resolver['mode'] === 'system') {
        $records = @dns_get_record($naam, DNS_A | DNS_AAAA | DNS_CNAME);
        if ($records === false) throw new RuntimeException("DNS-query mislukt voor {$naam}.");
        return dns43Observatie($records);
    }
    $records = [];
    foreach ([DNS_A, DNS_AAAA, DNS_CNAME] as $type) {
        $records = array_merge($records, $resolver['mode'] === 'doh'
            ? dns43DohQuery($naam, $type, $resolver)
            : dns43ExplicieteQuery($naam, $type, $resolver));
    }
    return dns43Observatie($records);
}

function dns43RrsetsGelijk(array $a, array $b): bool
{
    $a = array_values($a); $b = array_values($b); sort($a, SORT_STRING); sort($b, SORT_STRING); return $a === $b;
}

function dns43Beoordeel(array $plan, array $owner, ?array $terminal = null): array
{
    $fouten = []; $eOwner = $plan['expected']['owner'];
    foreach (['a', 'aaaa', 'cname'] as $type) {
        if (!dns43RrsetsGelijk((array)($owner[$type] ?? []), (array)($eOwner[$type] ?? []))) $fouten[] = "Owner {$type}-RRset wijkt af van het DNS-plan.";
    }
    if (($plan['strategy'] ?? '') === 'cname') {
        if ($terminal === null) $fouten[] = 'Terminale CNAME-resolutie ontbreekt.';
        else {
            $eTerm = $plan['expected']['terminal'];
            foreach (['a', 'aaaa', 'cname'] as $type) {
                if (!dns43RrsetsGelijk((array)($terminal[$type] ?? []), (array)($eTerm[$type] ?? []))) $fouten[] = "Terminal {$type}-RRset wijkt af van het DNS-plan.";
            }
        }
    }
    return ['ready' => $fouten === [], 'errors' => $fouten];
}

function dns43Utc(string $waarde): int
{
    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}T[0-9]{2}:[0-9]{2}:[0-9]{2}Z$/D', $waarde) !== 1) throw new RuntimeException('Readiness bevat geen canonieke UTC-tijd.');
    $dt = DateTimeImmutable::createFromFormat('!Y-m-d\\TH:i:s\\Z', $waarde, new DateTimeZone('UTC'));
    if (!$dt || $dt->format('Y-m-d\\TH:i:s\\Z') !== $waarde) throw new RuntimeException('Readiness bevat een ongeldige UTC-tijd.');
    return $dt->getTimestamp();
}

function dns43ReadinessLeesEnValideer(string $statusPad, ?int $nu = null): array
{
    $statusPad = runtime41BestaandPad($statusPad, 'dns-readiness.json');
    $raw = @file_get_contents($statusPad);
    if ($raw === false) throw new RuntimeException('dns-readiness.json kon niet worden gelezen.');
    try { $status = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('dns-readiness.json bevat ongeldige JSON.'); }
    if (!is_array($status) || (int)($status['schema'] ?? 0) !== 1 || ($status['phase'] ?? '') !== '4.3-readiness' || ($status['ready'] ?? false) !== true) {
        throw new RuntimeException('DNS-readiness is niet geldig of niet ready.');
    }
    $legacyResolver = !isset($status['resolver']);
    if ($legacyResolver) {
        if (($status['resolver_mode'] ?? '') !== 'system') throw new RuntimeException('Legacy DNS-readiness komt niet van de live systeemresolver.');
        $resolver = dns43ResolverContext();
    } else {
        $resolver = dns43ResolverContextValideer((array)$status['resolver']);
    }
    $resolverHash = dns43ResolverContextHash($resolver);
    if (!hash_equals($resolver['mode'], (string)($status['resolver_mode'] ?? ''))
        || (!$legacyResolver && !hash_equals($resolverHash, (string)($status['resolver_sha256'] ?? '')))) {
        throw new RuntimeException('DNS-readiness resolvercontext is intern inconsistent.');
    }

    $planCtx = dns43PlanLeesEnValideer((string)($status['source']['dns_plan_file'] ?? ''));
    $plan = $planCtx['plan'];
    if (!hash_equals($planCtx['sha256'], (string)($status['source']['dns_plan_sha256'] ?? ''))
        || !hash_equals((string)$plan['source']['web_plan_sha256'], (string)($status['source']['web_plan_sha256'] ?? ''))
        || !hash_equals((string)$plan['tenant_key'], (string)($status['tenant_key'] ?? ''))
        || !hash_equals((string)$plan['canonical_host'], (string)($status['canonical_host'] ?? ''))
        || !hash_equals((string)$plan['strategy'], (string)($status['strategy'] ?? ''))) {
        throw new RuntimeException('DNS-readiness is niet meer aan de actuele tenant/DNS-bron gebonden.');
    }
    if (runtime41NormPad($statusPad) !== runtime41NormPad((string)$plan['bundle']['readiness_file'])) throw new RuntimeException('DNS-readiness staat niet op het gebonden tenantpad.');

    $samples = (int)($status['propagation']['sample_count'] ?? 0); $interval = (int)($status['propagation']['interval_seconds'] ?? -1);
    $scope = dns43ResolverScope($resolver);
    if (($status['propagation']['scope'] ?? '') !== $scope
        || $samples < (int)$plan['rules']['minimum_readiness_samples']
        || $interval < (int)$plan['rules']['minimum_sample_interval_seconds']) {
        throw new RuntimeException('DNS-readiness bewijst onvoldoende propagation-stabiliteit of resolvercontext.');
    }
    $checked = dns43Utc((string)($status['checked_at_utc'] ?? '')); $expires = dns43Utc((string)($status['expires_at_utc'] ?? ''));
    if ($expires !== $checked + (int)$plan['rules']['readiness_max_age_seconds']) throw new RuntimeException('DNS-readiness heeft een ongeldige geldigheidsduur.');
    $nu ??= time();
    if ($nu < $checked - 60 || $nu > $expires) throw new RuntimeException('DNS-readiness is verlopen of heeft een onmogelijke kloktijd.');

    $owner = (array)($status['observed']['owner'] ?? []); $terminal = isset($status['observed']['terminal']) && is_array($status['observed']['terminal']) ? $status['observed']['terminal'] : null;
    if ((dns43Beoordeel($plan, $owner, $terminal)['ready'] ?? false) !== true) throw new RuntimeException('Opgeslagen DNS-observatie voldoet niet meer aan het gebonden plan.');
    $resolver = dns43ResolverContextBind($resolver);
    return ['status' => $status, 'resolver' => $resolver, 'resolver_sha256' => $resolverHash, 'plan_context' => $planCtx, 'path' => $statusPad, 'sha256' => hash('sha256', $raw)];
}
