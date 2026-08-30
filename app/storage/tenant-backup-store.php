<?php
// ============================================================
// Tenant-aware backupopslag
// ============================================================
// Externe tenants bewaren snapshots uitsluitend onder hun eigen private_root.
// Iedere data-backup bevat tenant- en backupidentiteit in een envelope. Publieke
// assets worden per scope als volledige directorysnapshot opgeslagen, eveneens
// met tenantgebonden manifest. Standalone RC045 gebruikt voorlopig de bestaande
// data-backups-compatibiliteitslaag.
// ============================================================

require_once dirname(__DIR__) . '/core/site.php';

function tenantBackupActief(): bool
{
    return tenantRuntimePrivateRoot(siteConfig()) !== null;
}

function tenantBackupTenantKey(): string
{
    return tenantRuntimeVeiligeSleutel((string) siteConfigGet('vereniging.sleutel', 'default'));
}

function tenantBackupRoot(): ?string
{
    $privateRoot = tenantRuntimePrivateRoot(siteConfig());
    return $privateRoot === null ? null : $privateRoot . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'tenant';
}

function tenantBackupDataRoot(): ?string
{
    $root = tenantBackupRoot();
    return $root === null ? null : $root . DIRECTORY_SEPARATOR . 'records';
}

function tenantBackupAssetRoot(): ?string
{
    $root = tenantBackupRoot();
    return $root === null ? null : $root . DIRECTORY_SEPARATOR . 'assets';
}

function tenantBackupBewaardagen(): int
{
    $dagen = (int) siteConfigGet('opslag.backups.bewaardagen', 90);
    return max(1, min(730, $dagen));
}

function tenantBackupMaxPerItem(): int
{
    $max = (int) siteConfigGet('opslag.backups.max_per_item', 200);
    return max(1, min(1000, $max));
}

function tenantBackupMaxAssetSnapshots(): int
{
    $max = (int) siteConfigGet('opslag.backups.max_asset_snapshots', 20);
    return max(1, min(100, $max));
}

function tenantBackupMaxAssetBytes(): int
{
    $mb = (int) siteConfigGet('opslag.backups.max_asset_mb', 2048);
    $mb = max(50, min(51200, $mb));
    return $mb * 1024 * 1024;
}

function tenantBackupSleutel(string $sleutel): ?string
{
    $sleutel = trim($sleutel);
    if ($sleutel === '' || strlen($sleutel) > 100) return null;
    return preg_match('/^[a-z0-9][a-z0-9_-]*$/D', $sleutel) === 1 ? $sleutel : null;
}

function tenantBackupPadVoorVergelijk(string $pad): string
{
    $pad = str_replace('\\', '/', $pad);
    $pad = (string) preg_replace('~/+~', '/', $pad);
    if (DIRECTORY_SEPARATOR === '\\') $pad = strtolower($pad);
    return rtrim($pad, '/');
}

function tenantBackupPadBinnen(string $pad, string $root): bool
{
    $pad = tenantBackupPadVoorVergelijk($pad);
    $root = tenantBackupPadVoorVergelijk($root);
    return $pad === $root || strncmp($pad, $root . '/', strlen($root) + 1) === 0;
}

/** Geen symlinkcomponenten en fysiek altijd onder private_root. */
function tenantBackupPadVeilig(string $pad): bool
{
    $privateRoot = tenantRuntimePrivateRoot(siteConfig());
    if ($privateRoot === null || !is_dir($privateRoot) || is_link($privateRoot)) return false;
    if (!tenantBackupPadBinnen($pad, $privateRoot)) return false;

    $privateReal = realpath($privateRoot);
    if ($privateReal === false) return false;

    $cursor = rtrim($pad, '/\\');
    while (true) {
        if (is_link($cursor)) return false;
        if (tenantBackupPadVoorVergelijk($cursor) === tenantBackupPadVoorVergelijk($privateRoot)) break;
        $parent = dirname($cursor);
        if ($parent === $cursor || !tenantBackupPadBinnen($parent, $privateRoot)) return false;
        $cursor = $parent;
    }

    $bestaand = rtrim($pad, '/\\');
    while (!file_exists($bestaand) && !is_link($bestaand)) {
        $parent = dirname($bestaand);
        if ($parent === $bestaand || !tenantBackupPadBinnen($parent, $privateRoot)) return false;
        $bestaand = $parent;
    }
    if (is_link($bestaand)) return false;
    $real = realpath($bestaand);
    return $real !== false && tenantBackupPadBinnen($real, $privateReal);
}

function tenantBackupMaakMap(string $map): bool
{
    if (!tenantBackupPadVeilig($map)) return false;
    if (!is_dir($map) && !@mkdir($map, 0750, true)) return false;
    clearstatcache(true, $map);
    if (!is_dir($map) || is_link($map) || !tenantBackupPadVeilig($map)) return false;
    @chmod($map, 0750);
    return true;
}

function tenantBackupMicroTijd(): string
{
    $nu = microtime(true);
    $seconde = (int) floor($nu);
    $micro = (int) floor(($nu - $seconde) * 1000000);
    $micro = max(0, min(999999, $micro));
    return date('Y-m-d_His', $seconde) . '_' . sprintf('%06d', $micro);
}

function tenantBackupDataMap(string $sleutel): ?string
{
    $sleutel = tenantBackupSleutel($sleutel);
    $root = tenantBackupDataRoot();
    if ($sleutel === null || $root === null) return null;
    return $root . DIRECTORY_SEPARATOR . $sleutel;
}

function tenantBackupPruneData(string $sleutel): void
{
    $map = tenantBackupDataMap($sleutel);
    if ($map === null || !is_dir($map) || !tenantBackupPadVeilig($map)) return;
    $files = array_values(array_filter(@glob($map . DIRECTORY_SEPARATOR . '*.json') ?: [], static fn($p) => is_file($p) && !is_link($p)));
    // Snapshotnamen beginnen met een microsecond-precisie tijdstempel en zijn
    // daardoor betrouwbaar chronologisch te sorteren, ook wanneer filemtime
    // voor meerdere snapshots dezelfde seconde teruggeeft.
    usort($files, static fn($a, $b) => strcmp(basename($a), basename($b)));
    $grens = time() - tenantBackupBewaardagen() * 86400;
    $over = [];
    foreach ($files as $file) {
        $tijd = @filemtime($file);
        if ($tijd !== false && $tijd < $grens) @unlink($file); else $over[] = $file;
    }
    $teveel = count($over) - tenantBackupMaxPerItem();
    for ($i = 0; $i < $teveel; $i++) @unlink($over[$i]);
}

/** Schrijft een tenantgebonden JSON-envelope en retourneert het pad. */
function tenantBackupMaakArray(string $sleutel, array $data): ?string
{
    if (!tenantBackupActief()) return null;
    $sleutel = tenantBackupSleutel($sleutel);
    $map = $sleutel === null ? null : tenantBackupDataMap($sleutel);
    if ($sleutel === null || $map === null || !tenantBackupMaakMap($map)) return null;

    $envelope = [
        'schema' => 1,
        'tenant_key' => tenantBackupTenantKey(),
        'backup_key' => $sleutel,
        'created_at' => date('c'),
        'data' => $data,
    ];
    $json = json_encode($envelope, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return null;

    try { $rand = bin2hex(random_bytes(4)); }
    catch (Throwable $e) { $rand = substr(hash('sha256', (string) microtime(true)), 0, 8); }
    $naam = tenantBackupMicroTijd() . '_' . $rand . '.json';
    $pad = $map . DIRECTORY_SEPARATOR . $naam;
    $tmp = $pad . '.tmp';
    if (!tenantBackupPadVeilig($tmp)) return null;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return null;
    @chmod($tmp, 0640);
    if (!tenantBackupPadVeilig($pad) || !@rename($tmp, $pad)) { @unlink($tmp); return null; }
    @chmod($pad, 0640);
    tenantBackupPruneData($sleutel);
    return $pad;
}

function tenantBackupDataLijst(string $sleutel): array
{
    $map = tenantBackupDataMap($sleutel);
    if ($map === null || !is_dir($map) || !tenantBackupPadVeilig($map)) return [];
    $files = array_values(array_filter(@glob($map . DIRECTORY_SEPARATOR . '*.json') ?: [], static fn($p) => is_file($p) && !is_link($p)));
    usort($files, static fn($a, $b) => strcmp(basename($b), basename($a)));
    return $files;
}

/**
 * Een users-snapshot mag nooit een oude sessie opnieuw geldig maken.
 * Daarom krijgt ieder hersteld account een sessie_versie die strikt hoger is
 * dan zowel de snapshotversie als de nu actieve versie van hetzelfde account.
 * Dit is bewust onderdeel van de restore-read: beheer/backups.php schrijft
 * exact deze geharde data terug en bestaande sessies falen daarna gesloten.
 */
function tenantBackupHardenGebruikersHerstel(array $hersteld): array
{
    $privateRoot = tenantRuntimePrivateRoot(siteConfig());
    if ($privateRoot === null) return $hersteld;

    $usersPad = $privateRoot . DIRECTORY_SEPARATOR . 'auth' . DIRECTORY_SEPARATOR . 'users.json';
    $huidig = [];
    if (file_exists($usersPad) || is_link($usersPad)) {
        if (!is_file($usersPad) || is_link($usersPad) || !tenantBackupPadVeilig($usersPad)) {
            throw new RuntimeException('Actuele gebruikersopslag is niet veilig beschikbaar voor restore.');
        }
        $raw = @file_get_contents($usersPad);
        $decoded = $raw === false ? null : json_decode($raw, true);
        if (!is_array($decoded)) throw new RuntimeException('Actuele gebruikersopslag is beschadigd; restore afgebroken.');
        $huidig = $decoded;
    }

    $versies = [];
    foreach ($huidig as $account) {
        if (!is_array($account)) continue;
        $naam = strtolower(trim((string)($account['gebruikersnaam'] ?? '')));
        if ($naam === '') continue;
        $versies[$naam] = max(1, (int)($account['sessie_versie'] ?? 1));
    }

    foreach ($hersteld as $i => $account) {
        if (!is_array($account)) continue;
        $naam = strtolower(trim((string)($account['gebruikersnaam'] ?? '')));
        $snapshotVersie = max(1, (int)($account['sessie_versie'] ?? 1));
        $actueel = $naam !== '' ? max(1, (int)($versies[$naam] ?? 1)) : 1;
        $basis = max($snapshotVersie, $actueel);
        if ($basis >= PHP_INT_MAX) throw new RuntimeException('Gebruikersrestore bevat een ongeldige sessieversie.');
        $hersteld[$i]['sessie_versie'] = $basis + 1;
    }
    return $hersteld;
}

/** Leest alleen een bestand uit exact de map van de gevraagde backup-key. */
function tenantBackupLeesArray(string $sleutel, string $bestandsnaam, ?string &$fout = null): ?array
{
    $fout = null;
    $sleutel = tenantBackupSleutel($sleutel);
    $map = $sleutel === null ? null : tenantBackupDataMap($sleutel);
    $naam = basename($bestandsnaam);
    if ($sleutel === null || $map === null || $naam !== $bestandsnaam || !preg_match('/^[A-Za-z0-9_.-]+\.json$/D', $naam)) {
        $fout = 'Ongeldige back-upselectie.'; return null;
    }
    $realMap = realpath($map);
    $realPad = realpath($map . DIRECTORY_SEPARATOR . $naam);
    if ($realMap === false || $realPad === false || dirname($realPad) !== $realMap || !is_file($realPad) || is_link($realPad) || !tenantBackupPadVeilig($realPad)) {
        $fout = 'Back-up bestaat niet meer of valt buiten de tenantgrens.'; return null;
    }
    $raw = @file_get_contents($realPad);
    if ($raw === false) { $fout = 'Back-up kon niet worden gelezen.'; return null; }
    try { $env = json_decode($raw, true, 512, JSON_THROW_ON_ERROR); }
    catch (JsonException $e) { $fout = 'Back-up bevat beschadigde JSON.'; return null; }
    if (!is_array($env) || (int)($env['schema'] ?? 0) !== 1 || !is_array($env['data'] ?? null)) {
        $fout = 'Back-up heeft een onbekend formaat.'; return null;
    }
    if (!hash_equals(tenantBackupTenantKey(), (string)($env['tenant_key'] ?? '')) || !hash_equals($sleutel, (string)($env['backup_key'] ?? ''))) {
        $fout = 'Back-up hoort niet bij deze tenant of dit onderdeel.'; return null;
    }

    try {
        return $sleutel === 'auth-gebruikers'
            ? tenantBackupHardenGebruikersHerstel($env['data'])
            : $env['data'];
    } catch (Throwable $e) {
        error_log('[platform] gebruikersrestore sessie-hardening mislukt: ' . $e->getMessage());
        $fout = 'Gebruikersback-up kon niet veilig voor herstel worden voorbereid.';
        return null;
    }
}

function tenantBackupVerwijderMap(string $map): void
{
    if (is_link($map)) { @unlink($map); return; }
    if (!is_dir($map)) return;
    foreach ((array) @scandir($map) as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $map . DIRECTORY_SEPARATOR . $item;
        if (is_link($pad)) { @unlink($pad); continue; }
        if (is_dir($pad)) tenantBackupVerwijderMap($pad); else @unlink($pad);
    }
    @rmdir($map);
}

function tenantBackupMapGrootte(string $map): int
{
    if (!is_dir($map) || is_link($map)) return 0;
    $totaal = 0;
    foreach ((array) @scandir($map) as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $map . DIRECTORY_SEPARATOR . $item;
        if (is_link($pad)) continue;
        if (is_dir($pad)) $totaal += tenantBackupMapGrootte($pad);
        elseif (is_file($pad)) $totaal += max(0, (int) (@filesize($pad) ?: 0));
    }
    return $totaal;
}

function tenantBackupKopieerMap(string $bron, string $doel): bool
{
    if (!is_dir($bron) || is_link($bron) || !tenantBackupMaakMap($doel)) return false;
    foreach ((array) @scandir($bron) as $item) {
        if ($item === '.' || $item === '..') continue;
        $src = $bron . DIRECTORY_SEPARATOR . $item;
        $dst = $doel . DIRECTORY_SEPARATOR . $item;
        if (is_link($src)) return false;
        if (is_dir($src)) {
            if (!tenantBackupKopieerMap($src, $dst)) return false;
        } elseif (is_file($src)) {
            if (!tenantBackupPadVeilig($dst) || !@copy($src, $dst)) return false;
            @chmod($dst, 0640);
        }
    }
    return true;
}

function tenantBackupAssetScope(string $scope): ?string
{
    return in_array($scope, ['fotoboek', 'sponsors'], true) ? $scope : null;
}

function tenantBackupAssetScopeRoot(string $scope): ?string
{
    $scope = tenantBackupAssetScope($scope);
    $root = tenantBackupAssetRoot();
    return $scope === null || $root === null ? null : $root . DIRECTORY_SEPARATOR . $scope;
}

function tenantBackupAssetLijst(string $scope): array
{
    $root = tenantBackupAssetScopeRoot($scope);
    if ($root === null || !is_dir($root) || !tenantBackupPadVeilig($root)) return [];
    $dirs = [];
    foreach ((array) @scandir($root) as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $root . DIRECTORY_SEPARATOR . $item;
        if (is_dir($pad) && !is_link($pad) && is_file($pad . DIRECTORY_SEPARATOR . 'manifest.json')) $dirs[] = $pad;
    }
    usort($dirs, static fn($a, $b) => strcmp(basename($b), basename($a)));
    return $dirs;
}

function tenantBackupPruneAssets(): void
{
    $alle = [];
    foreach (['fotoboek', 'sponsors'] as $scope) {
        $lijst = tenantBackupAssetLijst($scope);
        $grens = time() - tenantBackupBewaardagen() * 86400;
        foreach ($lijst as $i => $map) {
            $tijd = @filemtime($map . DIRECTORY_SEPARATOR . 'manifest.json') ?: 0;
            if ($tijd > 0 && $tijd < $grens) { tenantBackupVerwijderMap($map); continue; }
            if ($i >= tenantBackupMaxAssetSnapshots()) { tenantBackupVerwijderMap($map); continue; }
            $alle[] = $map;
        }
    }

    usort($alle, static fn($a, $b) => strcmp(basename($a), basename($b)));
    $totaal = 0;
    $groottes = [];
    foreach ($alle as $map) { $g = tenantBackupMapGrootte($map); $groottes[$map] = $g; $totaal += $g; }
    $limiet = tenantBackupMaxAssetBytes();
    foreach ($alle as $map) {
        if ($totaal <= $limiet) break;
        $totaal -= $groottes[$map] ?? 0;
        tenantBackupVerwijderMap($map);
    }
}

/** Volledige snapshot van één publieke assetnamespace. */
function tenantBackupMaakAssetSnapshot(string $scope): ?string
{
    if (!tenantBackupActief()) return null;
    $scope = tenantBackupAssetScope($scope);
    if ($scope === null) return null;
    require_once dirname(__DIR__) . '/content/public-asset-store.php';
    $bron = publicAssetNamespaceRoot($scope);
    if ($bron === null || !is_dir($bron) || is_link($bron) || !publicAssetTenantPadVeilig($bron)) return null;
    if (tenantBackupMapGrootte($bron) > tenantBackupMaxAssetBytes()) {
        error_log('[platform] asset-backup overgeslagen: huidige ' . $scope . '-scope is groter dan de tenantlimiet');
        return null;
    }

    $scopeRoot = tenantBackupAssetScopeRoot($scope);
    if ($scopeRoot === null || !tenantBackupMaakMap($scopeRoot)) return null;
    try { $rand = bin2hex(random_bytes(4)); }
    catch (Throwable $e) { $rand = substr(hash('sha256', (string) microtime(true)), 0, 8); }
    $naam = tenantBackupMicroTijd() . '_' . $rand;
    $snapshot = $scopeRoot . DIRECTORY_SEPARATOR . $naam;
    if (!tenantBackupMaakMap($snapshot)) return null;
    $payload = $snapshot . DIRECTORY_SEPARATOR . 'payload';
    if (!tenantBackupKopieerMap($bron, $payload)) { tenantBackupVerwijderMap($snapshot); return null; }

    $manifest = [
        'schema' => 1,
        'tenant_key' => tenantBackupTenantKey(),
        'asset_scope' => $scope,
        'created_at' => date('c'),
    ];
    $json = json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $manifestPad = $snapshot . DIRECTORY_SEPARATOR . 'manifest.json';
    if ($json === false || @file_put_contents($manifestPad, $json, LOCK_EX) === false) { tenantBackupVerwijderMap($snapshot); return null; }
    @chmod($manifestPad, 0640);
    tenantBackupPruneAssets();
    return is_dir($snapshot) ? $snapshot : null;
}

function tenantBackupLeesAssetSnapshot(string $scope, string $naam, ?string &$fout = null): ?string
{
    $fout = null;
    $scope = tenantBackupAssetScope($scope);
    $root = $scope === null ? null : tenantBackupAssetScopeRoot($scope);
    $naam = basename($naam);
    if ($scope === null || $root === null || $naam === '' || !preg_match('/^[A-Za-z0-9_.-]+$/D', $naam)) { $fout='Ongeldige assetsnapshot.'; return null; }
    $realRoot = realpath($root);
    $real = realpath($root . DIRECTORY_SEPARATOR . $naam);
    if ($realRoot === false || $real === false || dirname($real) !== $realRoot || !is_dir($real) || is_link($real) || !tenantBackupPadVeilig($real)) { $fout='Assetsnapshot bestaat niet meer of valt buiten de tenantgrens.'; return null; }
    $manifestPad = $real . DIRECTORY_SEPARATOR . 'manifest.json';
    $payload = $real . DIRECTORY_SEPARATOR . 'payload';
    $raw = @file_get_contents($manifestPad);
    if ($raw === false || !is_dir($payload) || is_link($payload)) { $fout='Assetsnapshot is onvolledig.'; return null; }
    $manifest = json_decode($raw, true);
    if (!is_array($manifest) || (int)($manifest['schema'] ?? 0)!==1 || !hash_equals(tenantBackupTenantKey(), (string)($manifest['tenant_key']??'')) || !hash_equals($scope, (string)($manifest['asset_scope']??''))) {
        $fout='Assetsnapshot hoort niet bij deze tenant of scope.'; return null;
    }
    return $payload;
}

/**
 * Herstelt één volledige assetnamespace via staging + rename. De gekozen
 * snapshot wordt eerst volledig naar staging gekopieerd. Pas daarna wordt de
 * huidige toestand als verplichte rollback-snapshot vastgelegd. Zo kan retentie
 * de gekozen oude snapshot niet meer onder de voeten van een lopende restore
 * verwijderen en start de mutatie nooit zonder duurzame herstelroute.
 */
function tenantBackupHerstelAssetSnapshot(string $scope, string $naam, ?string &$fout = null): bool
{
    $payload = tenantBackupLeesAssetSnapshot($scope, $naam, $fout);
    if ($payload === null) return false;
    require_once dirname(__DIR__) . '/content/public-asset-store.php';
    $doel = publicAssetNamespaceRoot($scope);
    $tenantRoot = publicAssetTenantRoot();
    if ($doel === null || $tenantRoot === null || !publicAssetTenantPadVeilig($doel)) { $fout='Assetdoel is niet veilig beschikbaar.'; return false; }

    $parent = dirname($doel);
    if (!is_dir($parent) && !@mkdir($parent, 0750, true)) { $fout='Assetroot kon niet worden voorbereid.'; return false; }
    try { $rand = bin2hex(random_bytes(4)); }
    catch (Throwable $e) { $rand = substr(hash('sha256', (string) microtime(true)), 0, 8); }
    $stage = $parent . DIRECTORY_SEPARATOR . basename($doel) . '.restore.' . $rand;
    $oud = $parent . DIRECTORY_SEPARATOR . basename($doel) . '.before-restore.' . $rand;

    // Eerst de gekozen restorebron veiligstellen; daarna mag pruning optreden.
    if (!tenantBackupKopieerMap($payload, $stage)) { tenantBackupVerwijderMap($stage); $fout='Assetsnapshot kon niet naar staging worden gekopieerd.'; return false; }

    $hadDoel = is_dir($doel);
    if ($hadDoel) {
        $rollbackSnapshot = tenantBackupMaakAssetSnapshot($scope);
        if ($rollbackSnapshot === null) {
            tenantBackupVerwijderMap($stage);
            $fout='Assetherstel is afgebroken omdat de huidige assetmap niet als rollback-snapshot kon worden bewaard.';
            return false;
        }
    }

    if ($hadDoel && !@rename($doel, $oud)) { tenantBackupVerwijderMap($stage); $fout='Huidige assetmap kon niet veilig worden geparkeerd.'; return false; }
    if (!@rename($stage, $doel)) {
        $rollbackOk = !$hadDoel || @rename($oud, $doel);
        tenantBackupVerwijderMap($stage);
        if (!$rollbackOk) {
            error_log('[platform] CRITICAL assetrestore rollback mislukt; geparkeerde map: ' . $oud);
            $fout='Assetherstel en automatische rollback zijn mislukt. De vorige assetmap is geparkeerd en de duurzame rollback-snapshot is bewaard.';
            return false;
        }
        $fout='Herstelde assetmap kon niet atomisch worden geplaatst; de vorige toestand is automatisch teruggezet.';
        return false;
    }
    @chmod($doel, 0750);
    if ($hadDoel) tenantBackupVerwijderMap($oud);
    return true;
}
