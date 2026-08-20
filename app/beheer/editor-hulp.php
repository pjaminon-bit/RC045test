<?php
// Gedeelde, kleine hulpfuncties voor zelfstandige beheermodules.
require_once dirname(__DIR__) . '/content/public-content-store.php';

function beheerEditorEsc($waarde): string
{
    return htmlspecialchars((string) $waarde, ENT_QUOTES, 'UTF-8');
}

function beheerEditorKort($waarde, int $max): string
{
    $tekst = trim(is_scalar($waarde) ? (string) $waarde : '');
    return function_exists('mb_substr') ? mb_substr($tekst, 0, $max, 'UTF-8') : substr($tekst, 0, $max);
}

function beheerEditorLeesJson(string $pad, $standaard = [])
{
    $pad = publicContentMapLegacyPad($pad);
    if (!is_file($pad)) return $standaard;
    $raw = @file_get_contents($pad);
    if ($raw === false) return $standaard;
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $standaard;
}

function beheerEditorSchrijfJson(string $pad, array $data): bool
{
    global $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand;

    $pad = publicContentMapLegacyPad($pad);
    // De oude data-backupmap is nog gedeeld. Tenant-lokale publieke content
    // wordt daar bewust niet heen gekopieerd; optie 9 maakt backup/restore zelf
    // tenant-aware. Standalone RC045 behoudt het bestaande backupgedrag.
    if (!publicContentIsTenantPad($pad) && function_exists('maakDataBackup')) {
        maakDataBackup($pad, $dataBackupMap, $dataBackupBewaardagen, $dataBackupMaxPerBestand);
    }

    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    if ($json === false) return false;

    $map = dirname($pad);
    if (!is_dir($map)) return false;
    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $e) {
        $suffix = (string) mt_rand(100000, 999999);
    }
    $tmp = $pad . '.tmp.' . $suffix;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    if (publicContentIsTenantPad($pad)) @chmod($tmp, 0640);
    if (!@rename($tmp, $pad)) {
        @unlink($tmp);
        return false;
    }
    if (publicContentIsTenantPad($pad)) @chmod($pad, 0640);
    return true;
}

function beheerEditorDatumIso($waarde): string
{
    $waarde = trim(is_scalar($waarde) ? (string) $waarde : '');
    if ($waarde === '') return '';
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $waarde, $m)) {
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $waarde : '';
    }
    if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $waarde, $m)) {
        return checkdate((int)$m[2], (int)$m[1], (int)$m[3]) ? ($m[3] . '-' . $m[2] . '-' . $m[1]) : '';
    }
    return '';
}

function beheerEditorDatumNl($waarde): string
{
    $iso = beheerEditorDatumIso($waarde);
    if ($iso === '') return '';
    [$jaar, $maand, $dag] = explode('-', $iso);
    return $dag . '-' . $maand . '-' . $jaar;
}
