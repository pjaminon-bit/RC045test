<?php
require_once dirname(__DIR__) . '/app/deployment/dns-contract.php';

$host = 'test.vps.holox.nl';

function diagDoh(string $url): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/dns-json\r\nUser-Agent: RC045test-226-diagnostic\r\n",
            'timeout' => 10,
            'ignore_errors' => true,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    if (!is_string($raw)) return ['error' => 'fetch_failed'];
    $json = json_decode($raw, true);
    if (!is_array($json)) return ['error' => 'invalid_json', 'raw' => substr($raw, 0, 200)];
    $out = ['status' => $json['Status'] ?? $json['status'] ?? null, 'a' => [], 'aaaa' => [], 'cname' => []];
    foreach ((array)($json['Answer'] ?? $json['answer'] ?? []) as $rr) {
        if (!is_array($rr)) continue;
        $type = (int)($rr['type'] ?? -1);
        $data = rtrim((string)($rr['data'] ?? ''), '.');
        if ($type === 1 && filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) $out['a'][] = $data;
        elseif ($type === 28 && filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) $out['aaaa'][] = strtolower($data);
        elseif ($type === 5 && $data !== '') $out['cname'][] = strtolower($data);
    }
    foreach (['a', 'aaaa', 'cname'] as $key) {
        $out[$key] = array_values(array_unique($out[$key]));
        sort($out[$key], SORT_STRING);
    }
    return $out;
}

$result = [];
try {
    $result['system'] = dns43Observatie(dns43Resolve($host, dns43ResolverContext('system')));
} catch (Throwable $e) {
    $result['system'] = ['error' => $e->getMessage()];
}
try {
    $result['udp_1_1_1_1'] = dns43Observatie(dns43Resolve($host, dns43ResolverContext('explicit', '1.1.1.1', 53)));
} catch (Throwable $e) {
    $result['udp_1_1_1_1'] = ['error' => $e->getMessage()];
}
$result['google_doh_a'] = diagDoh('https://dns.google/resolve?name=' . rawurlencode($host) . '&type=A');
$result['google_doh_aaaa'] = diagDoh('https://dns.google/resolve?name=' . rawurlencode($host) . '&type=AAAA');
$result['cloudflare_doh_a'] = diagDoh('https://cloudflare-dns.com/dns-query?name=' . rawurlencode($host) . '&type=A');
$result['cloudflare_doh_aaaa'] = diagDoh('https://cloudflare-dns.com/dns-query?name=' . rawurlencode($host) . '&type=AAAA');

echo "#226 PUBLIC DNS DIAGNOSTIC\n";
echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), "\n";
exit(0);
