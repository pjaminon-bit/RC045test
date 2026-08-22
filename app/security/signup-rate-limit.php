<?php
// ============================================================
// Tenant-/installatiegebonden rate limiter voor openbare aanmeldingen
// ============================================================
require_once dirname(__DIR__) . '/core/tenant-runtime.php';

function signupRateLimitPaden(array $siteConfig, string $projectRoot): array
{
    $privateRoot = tenantRuntimePrivateRoot($siteConfig);
    if ($privateRoot !== null) {
        $map = $privateRoot . '/security';
    } else {
        $realProject = realpath($projectRoot);
        $bron = (is_string($realProject) && $realProject !== '' ? $realProject : $projectRoot)
            . "\0" . tenantRuntimeVeiligeSleutel((string)($siteConfig['vereniging']['sleutel'] ?? 'default'))
            . "\0" . strtolower(rtrim(trim((string)($siteConfig['vereniging']['site_url'] ?? '')), '/'));
        $map = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'verenigingsplatform-security'
            . DIRECTORY_SEPARATOR . substr(hash('sha256', $bron), 0, 32);
    }

    if (is_link($map)) throw new RuntimeException('Security-opslagmap mag geen symlink zijn.');
    if (!is_dir($map) && !@mkdir($map, 0750, true) && !is_dir($map)) {
        throw new RuntimeException('Security-opslagmap kon niet worden aangemaakt.');
    }
    @chmod($map, 0750);
    if (!is_dir($map) || !is_writable($map) || is_link($map)) {
        throw new RuntimeException('Security-opslagmap is niet veilig schrijfbaar.');
    }

    return [
        'data' => $map . '/signup-attempts.json',
        'lock' => $map . '/.signup-attempts.lock',
    ];
}

function signupRateLimitLees(string $pad): array
{
    if (!file_exists($pad)) return [];
    if (!is_file($pad) || is_link($pad)) throw new RuntimeException('Rate-limitopslag is onveilig.');
    $ruw = @file_get_contents($pad);
    if (!is_string($ruw)) throw new RuntimeException('Rate-limitopslag kon niet worden gelezen.');
    try { $data = json_decode($ruw, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { throw new RuntimeException('Rate-limitopslag bevat ongeldige data.', 0, $e); }
    if (!is_array($data)) throw new RuntimeException('Rate-limitopslag bevat ongeldige data.');
    return $data;
}

function signupRateLimitSchrijf(string $pad, array $data): void
{
    $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) throw new RuntimeException('Rate-limitdata kon niet worden geserialiseerd.');
    try { $suffix = bin2hex(random_bytes(6)); }
    catch (Throwable $e) { $suffix = str_replace('.', '', (string)microtime(true)); }
    $tmp = $pad . '.tmp.' . $suffix;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('Rate-limitopslag kon niet worden geschreven.');
    }
    @chmod($tmp, 0640);
    if (!@rename($tmp, $pad)) {
        @unlink($tmp);
        throw new RuntimeException('Rate-limitopslag kon niet atomisch worden vervangen.');
    }
    @chmod($pad, 0640);
}

/**
 * Verbruikt één poging. Opslagfouten gooien bewust een exception: de caller
 * moet dan 503 geven in plaats van de limiter ongemerkt uit te schakelen.
 */
function signupRateLimitConsume(array $siteConfig, string $projectRoot, string $clientIp, int $max = 5, int $venster = 3600): array
{
    if ($max < 1 || $max > 100 || $venster < 60 || $venster > 86400) {
        throw new InvalidArgumentException('Ongeldige rate-limitconfiguratie.');
    }
    $paden = signupRateLimitPaden($siteConfig, $projectRoot);
    if (is_link($paden['lock'])) throw new RuntimeException('Rate-limitlock mag geen symlink zijn.');
    $slot = @fopen($paden['lock'], 'c');
    if (!is_resource($slot)) throw new RuntimeException('Rate-limitlock kon niet worden geopend.');
    @chmod($paden['lock'], 0640);
    if (!flock($slot, LOCK_EX)) {
        fclose($slot);
        throw new RuntimeException('Rate-limitlock kon niet worden verkregen.');
    }

    try {
        $nu = time();
        $grens = $nu - $venster;
        $data = signupRateLimitLees($paden['data']);
        $opgeschoond = [];
        foreach ($data as $sleutel => $tijden) {
            if (!is_string($sleutel) || preg_match('/^[0-9a-f]{64}$/D', $sleutel) !== 1 || !is_array($tijden)) continue;
            $recent = [];
            foreach ($tijden as $tijd) if (is_int($tijd) && $tijd > $grens && $tijd <= $nu + 60) $recent[] = $tijd;
            if ($recent) $opgeschoond[$sleutel] = array_slice($recent, -$max);
        }
        if (count($opgeschoond) > 10000) throw new RuntimeException('Rate-limitopslag overschrijdt veilige omvang.');

        $sleutel = hash('sha256', $clientIp !== '' ? $clientIp : 'onbekend');
        $recent = $opgeschoond[$sleutel] ?? [];
        if (count($recent) >= $max) {
            signupRateLimitSchrijf($paden['data'], $opgeschoond);
            $oudste = min($recent);
            return ['allowed' => false, 'retry_after' => max(1, $oudste + $venster - $nu)];
        }

        $recent[] = $nu;
        $opgeschoond[$sleutel] = $recent;
        signupRateLimitSchrijf($paden['data'], $opgeschoond);
        return ['allowed' => true, 'retry_after' => 0];
    } finally {
        flock($slot, LOCK_UN);
        fclose($slot);
    }
}
