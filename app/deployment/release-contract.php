<?php
// ============================================================
// Fase 4.7 — immutable releases + atomische current/rollback
// ============================================================
// Pure helpers. Geen root-mutaties of service reloads in deze laag.
// ============================================================

require_once __DIR__ . '/monitoring-contract.php';

function release47Commit(string $commit): string
{
    $commit = strtolower(trim($commit));
    if (preg_match('/^[0-9a-f]{40}$/D', $commit) !== 1) {
        throw new RuntimeException('Releasecommit moet exact 40 hextekens bevatten.');
    }
    return $commit;
}

function release47VeiligAbsoluut(string $pad, string $label): string
{
    $pad = runtime41NormPad($pad);
    if (!runtime41IsAbsoluutPad($pad) || runtime41HeeftRelatieveSegmenten($pad) || str_contains($pad, "\0")) {
        throw new RuntimeException("{$label} moet een absoluut POSIX-pad zonder . of .. segmenten zijn.");
    }
    return $pad;
}

function release47GenegeerdPad(string $rel): bool
{
    $rel = ltrim(str_replace('\\', '/', $rel), '/');
    foreach (['.git/', '.github/', 'data/', 'data-backups/', 'images/fotoboek/', 'images/sponsors/'] as $prefix) {
        if (str_starts_with($rel, $prefix)) return true;
    }
    if (in_array($rel, [
        '.git', '.github', 'data', 'data-backups', 'images/fotoboek', 'images/sponsors',
        'beheer-config.php', 'beheer-users.json', 'beheer-log.json', 'beheer-login-pogingen.json',
        'site-config.local.php', 'vertaal-config.php', 'leden-data.php', 'aanmeldingen-data.php',
        'contributies-data.php', 'vergaderingen-data.php', 'taken-data.php', 'operationele-taken-data.php',
        'evenementen-data.php', 'groepen-data.php', 'ledenlabels-data.php', 'dev-build.json', '.DS_Store',
        '.verenigingsplatform-release.json',
    ], true)) return true;
    return preg_match('/\.sqlite3?$/Di', $rel) === 1;
}

function release47Manifest(string $root): array
{
    $root = runtime41BestaandPad($root, 'Releasebron', true);
    $bestanden = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $info) {
        $pad = $info->getPathname();
        $rel = ltrim(substr(str_replace('\\', '/', $pad), strlen(str_replace('\\', '/', $root))), '/');
        if ($rel === '' || release47GenegeerdPad($rel)) continue;
        if (is_link($pad)) throw new RuntimeException("Releasebron bevat symlink: {$rel}");
        if ($info->isDir()) continue;
        if (!$info->isFile()) throw new RuntimeException("Releasebron bevat onverwacht object: {$rel}");
        $sha = @hash_file('sha256', $pad);
        $size = @filesize($pad);
        if (!is_string($sha) || $sha === '' || !is_int($size)) throw new RuntimeException("Releasebestand onleesbaar: {$rel}");
        $bestanden[$rel] = ['sha256' => $sha, 'size' => $size];
    }
    ksort($bestanden, SORT_STRING);
    foreach (['site-config.php', 'auth.php', 'healthz.php', 'bin/check-vps-health.php', 'bin/check-release-tenant.php', 'app/deployment/release-contract.php'] as $vereist) {
        if (!isset($bestanden[$vereist])) throw new RuntimeException("Releasebron mist vereist bestand: {$vereist}");
    }
    $ctx = hash_init('sha256');
    $bytes = 0;
    foreach ($bestanden as $rel => $meta) {
        hash_update($ctx, $rel . "\0" . (string)$meta['size'] . "\0" . $meta['sha256'] . "\0");
        $bytes += (int)$meta['size'];
    }
    return [
        'root' => $root,
        'sha256' => hash_final($ctx),
        'file_count' => count($bestanden),
        'bytes' => $bytes,
        'files' => $bestanden,
    ];
}

function release47Plan(string $sourceRoot, string $commit, string $platformRoot, string $tenantBase): array
{
    $commit = release47Commit($commit);
    $source = release47Manifest($sourceRoot);
    $platformRoot = release47VeiligAbsoluut($platformRoot, 'Platformroot');
    $tenantBase = release47VeiligAbsoluut($tenantBase, 'Tenantbasis');
    if (runtime41Binnen($platformRoot, $source['root']) || runtime41Binnen($source['root'], $platformRoot)) {
        throw new RuntimeException('Releasebron en platformroot mogen niet overlappen.');
    }
    if (runtime41Binnen($tenantBase, $platformRoot) || runtime41Binnen($platformRoot, $tenantBase)) {
        throw new RuntimeException('Platformroot en tenantbasis moeten fysiek gescheiden zijn.');
    }
    $releases = $platformRoot . '/releases';
    return [
        'schema' => 1,
        'phase' => '4.7',
        'commit' => $commit,
        'source' => [
            'root' => $source['root'],
            'manifest_sha256' => $source['sha256'],
            'file_count' => $source['file_count'],
            'bytes' => $source['bytes'],
        ],
        'paths' => [
            'platform_root' => $platformRoot,
            'releases_root' => $releases,
            'release_dir' => $releases . '/' . $commit,
            'current' => $platformRoot . '/current',
            'state' => $platformRoot . '/release-state.json',
            'events' => $platformRoot . '/release-events.jsonl',
            'lock' => '/var/lock/verenigingsplatform-release.lock',
            'tenant_base' => $tenantBase,
        ],
        'release' => [
            'directories_mode' => '0555',
            'files_mode' => '0444',
            'owner' => 'root',
            'group' => 'root',
            'overwrite_existing_forbidden' => true,
            'automatic_prune_forbidden' => true,
        ],
        'activation' => [
            'current_switch_atomic_symlink_rename' => true,
            'preflight_current_health_required_for_deploy' => true,
            'candidate_tenant_readonly_probe_required' => true,
            'apache_configtest_required' => true,
            'php_fpm_reload_after_switch' => true,
            'post_switch_health_required' => true,
            'failed_deploy_rolls_back_current' => true,
            'rollback_uses_previous_validated_only' => true,
        ],
        'security' => [
            'no_secrets_in_plan' => true,
            'source_symlinks_forbidden' => true,
            'mutable_tenant_data_excluded' => true,
            'release_content_hash_required' => true,
            'root_apply_only' => true,
        ],
    ];
}

function release47Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Releasecontract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function release47PlanLeesEnValideer(string $pad): array
{
    $pad = runtime41BestaandPad($pad, 'release-plan.json');
    $raw = @file_get_contents($pad);
    if (!is_string($raw)) throw new RuntimeException('release-plan.json kon niet worden gelezen.');
    try { $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { throw new RuntimeException('release-plan.json bevat ongeldige JSON.'); }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.7') {
        throw new RuntimeException('release-plan.json heeft een onbekend schema/fase.');
    }
    $verwacht = release47Plan(
        (string)($plan['source']['root'] ?? ''),
        (string)($plan['commit'] ?? ''),
        (string)($plan['paths']['platform_root'] ?? ''),
        (string)($plan['paths']['tenant_base'] ?? '')
    );
    if (!hash_equals(release47Json($verwacht), release47Json($plan))) {
        throw new RuntimeException('release-plan.json wijkt af van de actuele releasebron.');
    }
    if (runtime41Binnen($pad, (string)$plan['source']['root'])) {
        throw new RuntimeException('release-plan.json moet buiten de releasebron staan zodat het manifest stabiel blijft.');
    }
    return ['path' => $pad, 'sha256' => hash('sha256', $raw), 'plan' => $plan, 'manifest' => release47Manifest((string)$plan['source']['root'])];
}

function release47Marker(array $plan): array
{
    return [
        'schema' => 1,
        'phase' => '4.7-release',
        'commit' => $plan['commit'],
        'manifest_sha256' => $plan['source']['manifest_sha256'],
        'file_count' => (int)$plan['source']['file_count'],
        'bytes' => (int)$plan['source']['bytes'],
        'immutable' => true,
    ];
}

function release47MarkerLees(string $releaseDir, bool $controleerInhoud = true): array
{
    $releaseDir = runtime41BestaandPad($releaseDir, 'Release directory', true);
    $commit = basename($releaseDir);
    release47Commit($commit);
    $markerPad = $releaseDir . '/.verenigingsplatform-release.json';
    if (is_link($markerPad) || !is_file($markerPad)) throw new RuntimeException('Release mist een regulier markerbestand.');
    $raw = @file_get_contents($markerPad);
    $marker = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($marker) || (int)($marker['schema'] ?? 0) !== 1 || ($marker['phase'] ?? '') !== '4.7-release'
        || !hash_equals($commit, (string)($marker['commit'] ?? '')) || ($marker['immutable'] ?? false) !== true
        || preg_match('/^[0-9a-f]{64}$/D', (string)($marker['manifest_sha256'] ?? '')) !== 1) {
        throw new RuntimeException('Release marker is ongeldig of hoort bij een andere release.');
    }
    if ($controleerInhoud) {
        $manifest = release47Manifest($releaseDir);
        if (!hash_equals((string)$marker['manifest_sha256'], $manifest['sha256'])
            || (int)$marker['file_count'] !== $manifest['file_count'] || (int)$marker['bytes'] !== $manifest['bytes']) {
            throw new RuntimeException('Immutable release-inhoud wijkt af van de marker.');
        }
    }
    return ['path' => $releaseDir, 'marker' => $marker];
}

function release47StateEntry(array $markerCtx): array
{
    return [
        'commit' => (string)$markerCtx['marker']['commit'],
        'path' => (string)$markerCtx['path'],
        'manifest_sha256' => (string)$markerCtx['marker']['manifest_sha256'],
    ];
}
