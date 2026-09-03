<?php
// ============================================================
// Centrale server-side autorisatie voor /beheer/* endpoints
// ============================================================
// De beheer-shell en directe beheer-URL's gebruiken hiermee dezelfde
// platformDefinities()['beheer'] capabilitycontracten. Onbekende beheer-PHP
// routes falen gesloten. Legacy editors mogen authRechten() intern nog
// gebruiken, maar nooit meer als zelfstandig autorisatiegrens.
require_once __DIR__ . '/auth-capabilities.php';

function authBeheerRechtenprofielGeldig(array $record): bool
{
    return (array_key_exists('capabilities', $record) && is_array($record['capabilities']))
        || (array_key_exists('tabs', $record) && is_array($record['tabs']));
}

function authBeheerRouteContract(?string $scriptName = null, ?array $query = null): ?array
{
    $scriptName = $scriptName ?? (string)($_SERVER['SCRIPT_NAME'] ?? '');
    $query = $query ?? $_GET;
    $path = (string)(parse_url($scriptName, PHP_URL_PATH) ?? '');
    if (!preg_match('~(?:^|/)beheer/([^/]+\.php)$~', $path, $match)) {
        return null;
    }

    $bestand = (string)$match[1];
    if ($bestand === 'index.php') {
        return ['type' => 'dashboard', 'bestand' => $bestand];
    }

    if ($bestand === 'groep-relaties.php') {
        return [
            'type' => 'capability-groepen',
            'bestand' => $bestand,
            'groepen' => [
                ['committees.manage', 'workgroups.manage'],
                ['tasks.manage', 'meetings.manage', 'events.manage'],
            ],
        ];
    }

    foreach ((array)(authPlatformDefinities()['beheer'] ?? []) as $sleutel => $definitie) {
        if (!is_array($definitie)) {
            continue;
        }
        $route = (string)($definitie['route'] ?? '');
        $routePad = (string)(parse_url($route, PHP_URL_PATH) ?? '');
        if ($routePad === '' || basename($routePad) !== $bestand) {
            continue;
        }
        $vereist = [];
        parse_str((string)(parse_url($route, PHP_URL_QUERY) ?? ''), $vereist);
        $matcht = true;
        foreach ($vereist as $naam => $waarde) {
            if (!array_key_exists($naam, $query) || (string)$query[$naam] !== (string)$waarde) {
                $matcht = false;
                break;
            }
        }
        if (!$matcht) {
            continue;
        }

        return [
            'type' => 'capability',
            'bestand' => $bestand,
            'sleutel' => (string)$sleutel,
            'capability' => (string)($definitie['capability'] ?? ''),
            'expliciet' => !empty($definitie['gevoelig']),
            'feature' => (string)($definitie['feature'] ?? ''),
        ];
    }

    return ['type' => 'onbekend', 'bestand' => $bestand];
}

function authBeheerEndpointMagOpenen(array $contract, ?callable $checker = null): bool
{
    $type = (string)($contract['type'] ?? '');
    if ($type === 'dashboard') {
        return true;
    }
    if ($type === 'capability') {
        $capability = trim((string)($contract['capability'] ?? ''));
        if ($capability === '') {
            return false;
        }
        $expliciet = !empty($contract['expliciet']);
        return $checker !== null
            ? (bool)$checker($capability, $expliciet)
            : authHeeftCapability($capability, $expliciet);
    }
    if ($type === 'capability-groepen') {
        foreach ((array)($contract['groepen'] ?? []) as $groep) {
            $groepToegestaan = false;
            foreach ((array)$groep as $capability) {
                $capability = trim((string)$capability);
                if ($capability === '') {
                    continue;
                }
                $toegestaan = $checker !== null
                    ? (bool)$checker($capability, false)
                    : authHeeftCapability($capability);
                if ($toegestaan) {
                    $groepToegestaan = true;
                    break;
                }
            }
            if (!$groepToegestaan) {
                return false;
            }
        }
        return true;
    }
    return false;
}

function authBeheerEndpointHandhaaf(?array $contract = null): void
{
    $contract = $contract ?? authBeheerRouteContract();
    if ($contract === null || ($contract['type'] ?? '') === 'dashboard') {
        return;
    }
    if (!authBeheerEndpointMagOpenen($contract)) {
        http_response_code(403);
        echo 'Geen toegang.';
        exit;
    }
}
