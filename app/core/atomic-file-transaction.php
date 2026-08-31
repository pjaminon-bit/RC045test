<?php
// ============================================================
// Kleine rollback-transacties voor gekoppelde metadata + assets
// ============================================================
// Bedoeld voor beheeracties die meerdere bestanden/mappen als één logische
// wijziging behandelen. Alle paden komen uit serverconfiguratie, nooit direct
// uit requestdata. Vóór de eerste mutatie wordt de oude toestand gekopieerd;
// bij false/exception wordt die toestand volledig teruggezet.

function atomicFileTxVerwijder(string $pad): bool
{
    if (is_link($pad)) return @unlink($pad);
    if (is_file($pad)) return @unlink($pad);
    if (!file_exists($pad)) return true;
    if (!is_dir($pad)) return false;
    foreach ((array)@scandir($pad) as $item) {
        if ($item === '.' || $item === '..') continue;
        if (!atomicFileTxVerwijder($pad . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return @rmdir($pad);
}

function atomicFileTxKopieer(string $bron, string $doel): bool
{
    if (is_link($bron)) return false;
    if (is_file($bron)) {
        $map = dirname($doel);
        if (!is_dir($map) && !@mkdir($map, 0700, true)) return false;
        if (!@copy($bron, $doel)) return false;
        @chmod($doel, 0600);
        return true;
    }
    if (!is_dir($bron)) return false;
    if (!is_dir($doel) && !@mkdir($doel, 0700, true)) return false;
    @chmod($doel, 0700);
    foreach ((array)@scandir($bron) as $item) {
        if ($item === '.' || $item === '..') continue;
        if (!atomicFileTxKopieer($bron . DIRECTORY_SEPARATOR . $item, $doel . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return true;
}

function atomicFileTxStagingRoot(): string
{
    $basis = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'verenigingsplatform-transactions';
    if (is_link($basis)) throw new RuntimeException('Transactiestaging mag geen symlink zijn.');
    if (!is_dir($basis) && !@mkdir($basis, 0700, true)) throw new RuntimeException('Transactiestaging kon niet worden aangemaakt.');
    @chmod($basis, 0700);
    try { $suffix = bin2hex(random_bytes(12)); }
    catch (Throwable $e) { throw new RuntimeException('Veilige transactienaam kon niet worden gemaakt.', 0, $e); }
    $root = $basis . DIRECTORY_SEPARATOR . $suffix;
    if (!@mkdir($root, 0700)) throw new RuntimeException('Transactiemap kon niet worden aangemaakt.');
    return $root;
}

function atomicFileTxBegin(array $paden): array
{
    $root = atomicFileTxStagingRoot();
    $entries = [];
    $gezien = [];
    try {
        foreach ($paden as $pad) {
            $pad = rtrim((string)$pad, '/\\');
            if ($pad === '' || $pad === DIRECTORY_SEPARATOR || !str_starts_with($pad, DIRECTORY_SEPARATOR)) {
                throw new RuntimeException('Ongeldig transactiedoel.');
            }
            if (isset($gezien[$pad])) continue;
            $gezien[$pad] = true;
            if (is_link($pad)) throw new RuntimeException('Transactiedoel mag geen symlink zijn.');

            $bestaat = file_exists($pad);
            $type = $bestaat ? (is_dir($pad) ? 'dir' : (is_file($pad) ? 'file' : 'other')) : 'missing';
            if ($type === 'other') throw new RuntimeException('Transactiedoel heeft een niet-ondersteund bestandstype.');
            $snapshot = $root . DIRECTORY_SEPARATOR . sprintf('%03d', count($entries));
            if ($bestaat && !atomicFileTxKopieer($pad, $snapshot)) {
                throw new RuntimeException('Bestaande toestand kon niet veilig worden gesnapshot.');
            }
            $entries[] = ['pad'=>$pad, 'bestond'=>$bestaat, 'type'=>$type, 'snapshot'=>$snapshot];
        }
        return ['root'=>$root, 'entries'=>$entries, 'committed'=>false, 'rolled_back'=>false, 'closed'=>false];
    } catch (Throwable $e) {
        atomicFileTxVerwijder($root);
        throw $e;
    }
}

function atomicFileTxCleanup(array &$tx): bool
{
    if (!empty($tx['closed'])) return true;
    $root = (string)($tx['root'] ?? '');
    $ok = $root === '' || !file_exists($root) || atomicFileTxVerwijder($root);
    if ($ok) $tx['closed'] = true;
    return $ok;
}

function atomicFileTxRollback(array &$tx): bool
{
    if (!empty($tx['closed'])) return true;
    // Na een vastgelegde commit of al voltooide rollback mag uitsluitend de
    // stagingcleanup opnieuw worden geprobeerd. De snapshots kunnen op dat
    // moment al gedeeltelijk verwijderd zijn.
    if (!empty($tx['committed']) || !empty($tx['rolled_back'])) return atomicFileTxCleanup($tx);

    $ok = true;
    $entries = array_reverse((array)($tx['entries'] ?? []));
    foreach ($entries as $entry) {
        $pad = (string)($entry['pad'] ?? '');
        $snapshot = (string)($entry['snapshot'] ?? '');
        $bestond = !empty($entry['bestond']);
        if ($pad === '' || is_link($pad)) {
            if ($pad !== '' && is_link($pad)) $ok = @unlink($pad) && $ok;
            elseif ($pad === '') $ok = false;
            continue;
        }
        if (file_exists($pad) && !atomicFileTxVerwijder($pad)) { $ok = false; continue; }
        if ($bestond && !atomicFileTxKopieer($snapshot, $pad)) $ok = false;
    }
    if (!$ok) return false;

    $tx['rolled_back'] = true;
    return atomicFileTxCleanup($tx);
}

function atomicFileTxCommit(array &$tx): bool
{
    if (!empty($tx['closed'])) return true;
    // De businessmutatie is op dit punt al geslaagd. Markeer dat vóór cleanup,
    // zodat een cleanupfout nooit een onveilige rollback met deels verwijderde
    // snapshots kan veroorzaken. Cleanup mag daarna veilig opnieuw worden
    // geprobeerd zolang closed nog false is.
    $tx['committed'] = true;
    return atomicFileTxCleanup($tx);
}

function atomicFileTransactie(array $paden, callable $mutatie)
{
    $tx = atomicFileTxBegin($paden);
    try {
        $resultaat = $mutatie();
        if ($resultaat === false) {
            if (!atomicFileTxRollback($tx)) throw new RuntimeException('Mutatie faalde en rollback kon niet volledig worden uitgevoerd.');
            return false;
        }
        if (!atomicFileTxCommit($tx)) throw new RuntimeException('Mutatie slaagde maar transactiestaging kon niet worden opgeruimd.');
        return $resultaat;
    } catch (Throwable $e) {
        if (!empty($tx['committed']) || !empty($tx['rolled_back'])) {
            // Businessstatus staat al vast; probeer uitsluitend staging nogmaals
            // op te ruimen. Geen tweede commit of rollback.
            atomicFileTxCleanup($tx);
        } elseif (empty($tx['closed']) && !atomicFileTxRollback($tx)) {
            throw new RuntimeException('Mutatie faalde en rollback kon niet volledig worden uitgevoerd.', 0, $e);
        }
        throw $e;
    }
}
