<?php
// ============================================================
// Restrictive filesystem contract for private runtime data
// ============================================================

function privateFilesystemMode(string $pad): ?int
{
    clearstatcache(true, $pad);
    $mode = @fileperms($pad);
    return is_int($mode) ? ($mode & 0777) : null;
}

/**
 * Maak zo nodig een private directory aan. Bestaande applicatieparents (zoals
 * /tmp in tests of een bestaande documentroot) worden alleen gevalideerd en
 * nooit door deze helper van mode veranderd. Voor echte private backupdirs kan
 * expliciet worden gevraagd de bestaande directory naar 0750 te harden.
 */
function privateFilesystemBeveiligMap(string $map, bool $hardBestaand = false): bool
{
    if (is_link($map)) {
        error_log('[platform] private opslagmap is een symlink: ' . basename($map));
        return false;
    }

    $bestond = is_dir($map);
    if (!$bestond && !@mkdir($map, 0750, true) && !is_dir($map)) {
        error_log('[platform] private opslagmap kon niet worden aangemaakt: ' . basename($map));
        return false;
    }
    if (!is_dir($map) || is_link($map)) return false;

    if ($bestond && !$hardBestaand) return true;

    if (!@chmod($map, 0750)) {
        error_log('[platform] private opslagmap kon niet naar 0750 worden gezet: ' . basename($map));
        return false;
    }
    if (privateFilesystemMode($map) !== 0750) {
        error_log('[platform] private opslagmap heeft na chmod niet mode 0750: ' . basename($map));
        return false;
    }
    return true;
}

function privateFilesystemBeveiligBestand(string $pad, int $mode = 0640): bool
{
    $mode &= 0777;
    if ($mode === 0 || !is_file($pad) || is_link($pad)) {
        error_log('[platform] private opslagbestand is geen veilig regulier bestand: ' . basename($pad));
        return false;
    }
    if (!@chmod($pad, $mode)) {
        error_log('[platform] private opslagbestand kon niet naar restrictieve mode worden gezet: ' . basename($pad));
        return false;
    }
    if (privateFilesystemMode($pad) !== $mode) {
        error_log('[platform] private opslagbestand heeft na chmod niet de vereiste mode: ' . basename($pad));
        return false;
    }
    return true;
}

/** Backup mag nooit ruimer zijn dan bron én nooit ruimer dan 0640. */
function privateFilesystemBackupMode(string $bron): int
{
    $bronMode = privateFilesystemMode($bron);
    if ($bronMode === null) return 0600;
    return ($bronMode & 0640) ?: 0600;
}

/**
 * Legacy backuphelpers maken zelf de snapshotnaam. Na zo'n helpercall hardt
 * de writer alle snapshots van exact dit bronbestand en de backupmap. Zo is
 * ook bestaande compatibilitycode onafhankelijk van de process-umask.
 */
function privateFilesystemBeveiligLegacyBackups(string $bron, string $backupMap): bool
{
    if (!is_dir($backupMap)) return true;
    if (!privateFilesystemBeveiligMap($backupMap, true)) return false;
    $basis = basename($bron);
    $matches = @glob(rtrim($backupMap, '/\\') . DIRECTORY_SEPARATOR . '*' . $basis);
    if ($matches === false) return false;
    $mode = privateFilesystemBackupMode($bron);
    foreach ($matches as $bestand) {
        if (!is_file($bestand) || is_link($bestand) || !privateFilesystemBeveiligBestand($bestand, $mode)) {
            error_log('[platform] private legacy backup kon niet veilig worden gehard: ' . basename((string)$bestand));
            return false;
        }
    }
    return true;
}

/**
 * Schrijf bytes via een nieuw tempbestand met restrictieve mode en rename.
 * De tempinode wordt vóór rename gecontroleerd; de uiteindelijke inode wordt
 * daarna opnieuw geverifieerd. Een chmod-/modefout mag nooit als succes gelden.
 *
 * Standalone backuphelpers schrijven direct vóór deze primitive naar de vaste
 * siblingmap data-backups/. Als die map bestaat, wordt de snapshot van exact
 * dit bronbestand eerst gecontroleerd/gehard; een modefout blokkeert de write.
 */
function privateFilesystemAtomischSchrijf(string $pad, string $inhoud, int $mode = 0640): bool
{
    $map = dirname($pad);
    if (!privateFilesystemBeveiligMap($map)) return false;
    if (is_link($pad)) {
        error_log('[platform] private opslagdoel is een symlink: ' . basename($pad));
        return false;
    }

    $legacyBackupMap = $map . DIRECTORY_SEPARATOR . 'data-backups';
    if (is_dir($legacyBackupMap) && !privateFilesystemBeveiligLegacyBackups($pad, $legacyBackupMap)) {
        error_log('[platform] private write geweigerd omdat legacy backupmodes niet veilig konden worden afgedwongen');
        return false;
    }

    try { $suffix = bin2hex(random_bytes(5)); }
    catch (Throwable $e) { $suffix = str_replace('.', '', (string)microtime(true)); }
    $tmp = $pad . '.tmp.' . $suffix;
    if (is_link($tmp) || @file_put_contents($tmp, $inhoud, LOCK_EX) === false) {
        @unlink($tmp);
        return false;
    }
    if (!privateFilesystemBeveiligBestand($tmp, $mode)) {
        @unlink($tmp);
        return false;
    }
    if (!@rename($tmp, $pad)) {
        @unlink($tmp);
        return false;
    }
    return privateFilesystemBeveiligBestand($pad, $mode);
}

function privateFilesystemKopieerBackup(string $bron, string $doel): bool
{
    if (!is_file($bron) || is_link($bron)) return false;
    if (!privateFilesystemBeveiligMap(dirname($doel), true)) return false;
    if (is_link($doel) || !@copy($bron, $doel)) return false;
    if (!privateFilesystemBeveiligBestand($doel, privateFilesystemBackupMode($bron))) {
        @unlink($doel);
        return false;
    }
    return true;
}
