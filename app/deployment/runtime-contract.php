<?php
// ============================================================
// Fase 4.1 — Linux runtime- en PHP-FPM-contract
// ============================================================
// Pure helpers voor het genereren en opnieuw valideren van een tenant-runtime-
// bundle. Deze laag voert zelf geen root-acties uit en bevat geen secrets.
// ============================================================

function runtime41NormPad(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string)preg_replace('~/+~', '/', $pad);
    if ($pad !== '/') $pad = rtrim($pad, '/');
    return $pad;
}

function runtime41IsAbsoluutPad(string $pad): bool
{
    if ($pad === '' || str_contains($pad, "\0")) return false;
    return $pad[0] === '/';
}

function runtime41HeeftRelatieveSegmenten(string $pad): bool
{
    foreach (explode('/', str_replace('\\', '/', $pad)) as $segment) {
        if ($segment === '.' || $segment === '..') return true;
    }
    return false;
}

function runtime41Binnen(string $pad, string $root): bool
{
    $pad = runtime41NormPad($pad);
    $root = runtime41NormPad($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

function runtime41SymlinkInPad(string $pad): ?string
{
    $cursor = rtrim($pad, '/');
    if ($cursor === '') $cursor = '/';
    while (true) {
        if (is_link($cursor)) return $cursor;
        $parent = dirname($cursor);
        if ($parent === $cursor) break;
        $cursor = $parent;
    }
    return null;
}

function runtime41BestaandPad(string $pad, string $label, bool $map = false): string
{
    if (!runtime41IsAbsoluutPad($pad) || runtime41HeeftRelatieveSegmenten($pad)) {
        throw new RuntimeException("{$label} moet een absoluut POSIX-pad zonder . of .. segmenten zijn.");
    }
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException("{$label} mag geen symlink bevatten: {$link}");
    $real = realpath($pad);
    if ($real === false) throw new RuntimeException("{$label} bestaat niet of kan niet fysiek worden opgelost.");
    if ($map ? !is_dir($real) : !is_file($real)) throw new RuntimeException("{$label} heeft niet het verwachte bestandstype.");
    return rtrim($real, '/');
}

function runtime41CanoniekeTenantKey(string $key): bool
{
    return strlen($key) >= 3
        && strlen($key) <= 63
        && $key !== 'default'
        && !str_contains($key, '--')
        && preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D', $key) === 1;
}

function runtime41LinuxNaam(string $naam): bool
{
    return strlen($naam) >= 1
        && strlen($naam) <= 31
        && preg_match('/^[a-z_][a-z0-9_-]*$/D', $naam) === 1;
}

function runtime41PhpVersie(string $versie): bool
{
    if (preg_match('/^([0-9]{1,2})\.([0-9]{1,2})$/D', $versie, $m) !== 1) return false;
    $major = (int)$m[1];
    $minor = (int)$m[2];
    return $major >= 8 && $major <= 99 && $minor >= 0 && $minor <= 99;
}

function runtime41VerwachteOsUser(string $tenantKey): string
{
    return 'vst' . substr(hash('sha256', "user\0" . $tenantKey), 0, 16);
}

function runtime41VerwachtePool(string $tenantKey): string
{
    $hash = substr(hash('sha256', $tenantKey), 0, 12);
    return 'vst-' . substr($tenantKey, 0, 24) . '-' . $hash;
}

/**
 * Fase 4.7 mag de logische `current` symlink naar een andere immutable release
 * laten wijzen zonder alle bestaande 4.1-4.6 infrastructuurplannen te herschrijven.
 * Dat is alleen toegestaan wanneer een geldige 4.7 release-state de actuele
 * fysieke target expliciet als active of als exact transition from/to bindt.
 */
function runtime41BeheerdeReleaseCurrent(string $appLogical, string $logicalReal): bool
{
    $appLogical = runtime41NormPad($appLogical);
    $logicalReal = runtime41NormPad($logicalReal);
    if (basename($appLogical) !== 'current' || !is_link($appLogical)) return false;
    $platformRoot = runtime41NormPad(dirname($appLogical));
    $releases = $platformRoot . '/releases';
    $statePad = $platformRoot . '/release-state.json';
    if (runtime41SymlinkInPad($platformRoot) !== null || runtime41SymlinkInPad($releases) !== null || !is_dir($releases)) return false;
    $releasesReal = realpath($releases);
    if ($releasesReal === false || runtime41NormPad(dirname($logicalReal)) !== runtime41NormPad($releasesReal)) return false;
    $commit = basename($logicalReal);
    if (preg_match('/^[0-9a-f]{40}$/D', $commit) !== 1) return false;

    $markerPad = $logicalReal . '/.verenigingsplatform-release.json';
    if (is_link($markerPad) || !is_file($markerPad)) return false;
    $markerRaw = @file_get_contents($markerPad);
    $marker = is_string($markerRaw) ? json_decode($markerRaw, true) : null;
    if (!is_array($marker)
        || (int)($marker['schema'] ?? 0) !== 1
        || ($marker['phase'] ?? '') !== '4.7-release'
        || ($marker['immutable'] ?? false) !== true
        || !hash_equals($commit, (string)($marker['commit'] ?? ''))
        || preg_match('/^[0-9a-f]{64}$/D', (string)($marker['manifest_sha256'] ?? '')) !== 1) return false;

    if (is_link($statePad) || !is_file($statePad) || runtime41SymlinkInPad(dirname($statePad)) !== null) return false;
    $stateRaw = @file_get_contents($statePad);
    $state = is_string($stateRaw) ? json_decode($stateRaw, true) : null;
    if (!is_array($state) || (int)($state['schema'] ?? 0) !== 1 || ($state['phase'] ?? '') !== '4.7-state') return false;

    $entries = [];
    if (is_array($state['active'] ?? null)) $entries[] = $state['active'];
    if (is_array($state['transition'] ?? null)) {
        if (is_array($state['transition']['from'] ?? null)) $entries[] = $state['transition']['from'];
        if (is_array($state['transition']['to'] ?? null)) $entries[] = $state['transition']['to'];
    }
    foreach ($entries as $entry) {
        if (hash_equals($commit, (string)($entry['commit'] ?? ''))
            && hash_equals($logicalReal, runtime41NormPad((string)($entry['path'] ?? '')))
            && hash_equals((string)$marker['manifest_sha256'], (string)($entry['manifest_sha256'] ?? ''))) return true;
    }
    return false;
}

function runtime41DeploymentLees(string $pad): array
{
    $pad = runtime41BestaandPad($pad, 'deployment.json');
    $raw = @file_get_contents($pad);
    if ($raw === false) throw new RuntimeException('deployment.json kon niet worden gelezen.');
    try {
        $deployment = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('deployment.json bevat ongeldige JSON.');
    }
    if (!is_array($deployment) || (int)($deployment['schema'] ?? 0) !== 1) {
        throw new RuntimeException('deployment.json heeft een onbekend schema.');
    }

    $tenantKey = (string)($deployment['tenant_key'] ?? '');
    if (!runtime41CanoniekeTenantKey($tenantKey)) throw new RuntimeException('deployment.json bevat geen geldige tenant-key.');

    $tenantRoot = runtime41BestaandPad((string)($deployment['tenant']['tenant_root'] ?? ''), 'Tenantroot', true);
    $config = runtime41BestaandPad((string)($deployment['tenant']['config_file'] ?? ''), 'Tenantconfig');
    $privateRoot = runtime41BestaandPad((string)($deployment['tenant']['private_root'] ?? ''), 'Private root', true);
    if (runtime41NormPad(dirname($pad)) !== runtime41NormPad($tenantRoot)) {
        throw new RuntimeException('deployment.json moet direct in zijn eigen tenantroot staan.');
    }
    if (runtime41NormPad(dirname($config)) !== runtime41NormPad($tenantRoot)
        || runtime41NormPad($privateRoot) !== runtime41NormPad($tenantRoot . '/private')) {
        throw new RuntimeException('deployment.json bindt niet aan de provisioned tenantstructuur.');
    }

    $appLogical = (string)($deployment['shared_code']['app_root'] ?? '');
    $appReal = runtime41BestaandPad((string)($deployment['shared_code']['app_root_real'] ?? ''), 'Fysieke app-root', true);
    if (!runtime41IsAbsoluutPad($appLogical) || runtime41HeeftRelatieveSegmenten($appLogical)) {
        throw new RuntimeException('Logische app-root is geen veilig absoluut POSIX-pad.');
    }
    $logicalReal = realpath($appLogical);
    if ($logicalReal === false) throw new RuntimeException('Logische app-root kan niet fysiek worden opgelost.');
    $currentReal = runtime41NormPad($logicalReal);
    if ($currentReal !== runtime41NormPad($appReal) && !runtime41BeheerdeReleaseCurrent($appLogical, $currentReal)) {
        throw new RuntimeException('Logische en fysieke app-root wijzen niet naar dezelfde of een geldig beheerde fase-4.7 release.');
    }
    if (runtime41Binnen($tenantRoot, $appReal) || runtime41Binnen($appReal, $tenantRoot)
        || runtime41Binnen($tenantRoot, $currentReal) || runtime41Binnen($currentReal, $tenantRoot)) {
        throw new RuntimeException('Gedeelde applicatiecode en tenantroot mogen fysiek niet overlappen.');
    }

    $verwachteUser = runtime41VerwachteOsUser($tenantKey);
    $verwachtePool = runtime41VerwachtePool($tenantKey);
    $verwachteSocket = '/run/php/' . $verwachtePool . '.sock';
    if (!hash_equals($verwachteUser, (string)($deployment['php_fpm']['recommended_os_user'] ?? ''))
        || !hash_equals($verwachtePool, (string)($deployment['php_fpm']['pool'] ?? ''))
        || !hash_equals($verwachteSocket, (string)($deployment['php_fpm']['socket'] ?? ''))) {
        throw new RuntimeException('PHP-FPM/OS-identiteit in deployment.json is gemanipuleerd of niet deterministisch.');
    }
    if (($deployment['php_fpm']['clear_env'] ?? false) !== true
        || ($deployment['php_fpm']['one_pool_per_tenant'] ?? false) !== true) {
        throw new RuntimeException('deployment.json mist verplichte PHP-FPM isolatievlaggen.');
    }

    $runtimeEnv = $deployment['runtime_env'] ?? null;
    $verwachtEnv = [
        'VERENIGING_REQUIRE_TENANT_CONFIG' => '1',
        'VERENIGING_CONFIG_FILE' => $config,
        'VERENIGING_PRIVATE_ROOT' => $privateRoot,
    ];
    if (!is_array($runtimeEnv) || array_keys($runtimeEnv) !== array_keys($verwachtEnv)) {
        throw new RuntimeException('deployment.json bevat niet exact het verwachte runtime-environment.');
    }
    foreach ($verwachtEnv as $key => $waarde) {
        if (!hash_equals($waarde, (string)($runtimeEnv[$key] ?? ''))) {
            throw new RuntimeException("Runtime-environment klopt niet voor {$key}.");
        }
    }

    return [
        'path' => $pad,
        'sha256' => hash('sha256', $raw),
        'raw' => $deployment,
        'tenant_key' => $tenantKey,
        'tenant_root' => $tenantRoot,
        'config_file' => $config,
        'private_root' => $privateRoot,
        'app_root' => rtrim($appLogical, '/'),
        'app_root_real' => $appReal,
        'app_root_current_real' => $currentReal,
        'os_user' => $verwachteUser,
        'pool' => $verwachtePool,
        'socket' => $verwachteSocket,
        'runtime_env' => $verwachtEnv,
    ];
}

function runtime41OutputDir(string $pad, string $tenantRoot): string
{
    if (!runtime41IsAbsoluutPad($pad) || runtime41HeeftRelatieveSegmenten($pad)) {
        throw new RuntimeException('Runtime outputmap moet een absoluut veilig POSIX-pad zijn.');
    }
    if (!runtime41Binnen($pad, $tenantRoot) || runtime41NormPad($pad) === runtime41NormPad($tenantRoot)) {
        throw new RuntimeException('Runtime outputmap moet een eigen submap binnen de tenantroot zijn.');
    }
    $link = runtime41SymlinkInPad($pad);
    if ($link !== null) throw new RuntimeException("Runtime outputmap mag geen symlink bevatten: {$link}");
    return runtime41NormPad($pad);
}

function runtime41Plan(array $deployment, string $outputDir, string $phpVersion, string $webUser, string $webGroup): array
{
    if (!runtime41PhpVersie($phpVersion)) throw new RuntimeException('PHP-versie moet bijvoorbeeld 8.3 zijn.');
    if (!runtime41LinuxNaam($webUser) || !runtime41LinuxNaam($webGroup)) {
        throw new RuntimeException('Webserver user/group hebben geen veilige Linux-naam.');
    }
    $outputDir = runtime41OutputDir($outputDir, $deployment['tenant_root']);
    $osGroup = $deployment['os_user']; // unieke primary group met dezelfde naam
    $privateRoot = $deployment['private_root'];
    $sessions = $privateRoot . '/sessions';
    $tmp = $privateRoot . '/tmp';
    $fpmFile = $outputDir . '/' . $deployment['pool'] . '.conf';
    $planFile = $outputDir . '/runtime-plan.json';

    return [
        'schema' => 1,
        'phase' => '4.1',
        'tenant_key' => $deployment['tenant_key'],
        'source' => [
            'deployment_file' => $deployment['path'],
            'deployment_sha256' => $deployment['sha256'],
        ],
        'settings' => [
            'php_version' => $phpVersion,
            'web_user' => $webUser,
            'web_group' => $webGroup,
        ],
        'bundle' => [
            'output_dir' => $outputDir,
            'plan_file' => $planFile,
            'php_fpm_file' => $fpmFile,
        ],
        'os' => [
            'user' => $deployment['os_user'],
            'group' => $osGroup,
            'system_account' => true,
            'home' => '/nonexistent',
            'shell' => '/usr/sbin/nologin',
            'supplementary_groups' => [],
        ],
        'php_fpm' => [
            'pool' => $deployment['pool'],
            'socket' => $deployment['socket'],
            'pool_config_filename' => $deployment['pool'] . '.conf',
            'clear_env' => true,
            'one_pool_per_tenant' => true,
            'listen_owner' => $webUser,
            'listen_group' => $webGroup,
            'listen_mode' => '0660',
            'pm' => 'ondemand',
            'pm_max_children' => 5,
            'pm_process_idle_timeout' => '10s',
            'pm_max_requests' => 500,
            'session_save_path' => $sessions,
            'upload_tmp_dir' => $tmp,
            'runtime_env' => $deployment['runtime_env'],
        ],
        'filesystem' => [
            'tenant_root' => [
                'path' => $deployment['tenant_root'],
                'owner' => 'root',
                'group' => $osGroup,
                'mode' => '0750',
            ],
            'metadata_files' => [
                $deployment['config_file'],
                $deployment['tenant_root'] . '/runtime.env',
                $deployment['tenant_root'] . '/tenant.json',
                $deployment['path'],
            ],
            'metadata_owner' => 'root',
            'metadata_group' => $osGroup,
            'metadata_mode' => '0640',
            'private_root' => [
                'path' => $privateRoot,
                'owner' => $deployment['os_user'],
                'group' => $osGroup,
                'directory_mode' => '0750',
                'file_mode' => '0640',
            ],
            'sessions' => [
                'path' => $sessions,
                'directory_mode' => '0700',
                'file_mode' => '0600',
            ],
            'tmp' => [
                'path' => $tmp,
                'directory_mode' => '0700',
                'file_mode' => '0600',
            ],
            'shared_code' => [
                'path' => $deployment['app_root'],
                'real_path' => $deployment['app_root_real'],
                'must_not_be_owned_by_tenant_user' => true,
                'must_not_be_writable_by_tenant_identity' => true,
                'world_writable_forbidden' => true,
            ],
        ],
        'apply_contract' => [
            'linux_only' => true,
            'root_required' => true,
            'tenant_tree_symlinks_forbidden' => true,
            'shared_code_is_never_chowned' => true,
            'fpm_reload_is_explicit_after_config_test' => true,
        ],
    ];
}

function runtime41Json(array $data): string
{
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (!is_string($json)) throw new RuntimeException('Runtimecontract kon niet als JSON worden opgebouwd.');
    return $json . "\n";
}

function runtime41FpmQuote(string $waarde): string
{
    if (str_contains($waarde, "\0") || str_contains($waarde, "\r") || str_contains($waarde, "\n")) {
        throw new RuntimeException('Ongeldige control character in PHP-FPM waarde.');
    }
    return '"' . addcslashes($waarde, "\\\"") . '"';
}

function runtime41FpmConfig(array $plan): string
{
    $fpm = $plan['php_fpm'];
    $os = $plan['os'];
    $regels = [
        '; Gegenereerd door fase 4.1 — bevat geen secrets.',
        '; Wijzig niet handmatig; genereer opnieuw uit deployment.json.',
        '[' . $fpm['pool'] . ']',
        'user = ' . $os['user'],
        'group = ' . $os['group'],
        'listen = ' . runtime41FpmQuote($fpm['socket']),
        'listen.owner = ' . $fpm['listen_owner'],
        'listen.group = ' . $fpm['listen_group'],
        'listen.mode = ' . $fpm['listen_mode'],
        'clear_env = yes',
        'catch_workers_output = yes',
        'decorate_workers_output = no',
        'pm = ' . $fpm['pm'],
        'pm.max_children = ' . (int)$fpm['pm_max_children'],
        'pm.process_idle_timeout = ' . $fpm['pm_process_idle_timeout'],
        'pm.max_requests = ' . (int)$fpm['pm_max_requests'],
        'php_admin_value[session.save_path] = ' . runtime41FpmQuote($fpm['session_save_path']),
        'php_admin_value[upload_tmp_dir] = ' . runtime41FpmQuote($fpm['upload_tmp_dir']),
    ];
    foreach ($fpm['runtime_env'] as $key => $waarde) {
        $regels[] = 'env[' . $key . '] = ' . runtime41FpmQuote((string)$waarde);
    }
    return implode("\n", $regels) . "\n";
}

function runtime41PlanLeesEnValideer(string $planPad): array
{
    $planPad = runtime41BestaandPad($planPad, 'runtime-plan.json');
    $raw = @file_get_contents($planPad);
    if ($raw === false) throw new RuntimeException('runtime-plan.json kon niet worden gelezen.');
    try {
        $plan = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('runtime-plan.json bevat ongeldige JSON.');
    }
    if (!is_array($plan) || (int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.1') {
        throw new RuntimeException('runtime-plan.json heeft een onbekend schema.');
    }

    $deployment = runtime41DeploymentLees((string)($plan['source']['deployment_file'] ?? ''));
    if (!hash_equals($deployment['sha256'], (string)($plan['source']['deployment_sha256'] ?? ''))) {
        throw new RuntimeException('deployment.json is gewijzigd sinds deze runtimebundle is gemaakt.');
    }
    $outputDir = (string)($plan['bundle']['output_dir'] ?? '');
    if (runtime41NormPad(dirname($planPad)) !== runtime41NormPad($outputDir)) {
        throw new RuntimeException('runtime-plan.json staat niet in zijn gebonden outputmap.');
    }
    $verwacht = runtime41Plan(
        $deployment,
        $outputDir,
        (string)($plan['settings']['php_version'] ?? ''),
        (string)($plan['settings']['web_user'] ?? ''),
        (string)($plan['settings']['web_group'] ?? '')
    );
    if (!hash_equals(hash('sha256', runtime41Json($verwacht)), hash('sha256', runtime41Json($plan)))) {
        throw new RuntimeException('runtime-plan.json wijkt af van het deterministische fase-4.1 contract.');
    }
    return ['plan' => $plan, 'deployment' => $deployment, 'path' => $planPad];
}
