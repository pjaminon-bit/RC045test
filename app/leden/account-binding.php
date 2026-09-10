<?php
// ============================================================
// Centrale account <-> ledenkoppeling.
//
// Een vaste user_id is autoritatief zodra die aanwezig is. De historische
// beheer_account-koppeling mag alleen als fallback dienen zolang user_id leeg
// is. Zo kan hergebruik van een gebruikersnaam nooit een bestaande vaste
// koppeling overnemen.
// ============================================================

function ledenAccountKoppelingStatus(array $lid, string $userId, string $gebruikersnaam = ''): string
{
    $userId = trim($userId);
    $gebruikersnaam = trim($gebruikersnaam);
    $lidUserId = trim((string)($lid['user_id'] ?? ''));
    $lidGebruikersnaam = trim((string)($lid['beheer_account'] ?? ''));

    if ($lidUserId !== '') {
        if ($userId !== '' && hash_equals($lidUserId, $userId)) return 'id';
        if ($gebruikersnaam !== '' && $lidGebruikersnaam !== '' && strcasecmp($lidGebruikersnaam, $gebruikersnaam) === 0) {
            return 'conflict';
        }
        return 'geen';
    }

    if ($gebruikersnaam !== '' && $lidGebruikersnaam !== '' && strcasecmp($lidGebruikersnaam, $gebruikersnaam) === 0) {
        return 'legacy';
    }
    return 'geen';
}

function ledenAccountKoppelingMatcht(array $lid, string $userId, string $gebruikersnaam = ''): bool
{
    return in_array(ledenAccountKoppelingStatus($lid, $userId, $gebruikersnaam), ['id', 'legacy'], true);
}

/**
 * Vind alle records die het verwijderen van een account onveilig maken.
 * Ook gearchiveerde records en conflicterende legacy-namen tellen mee: een
 * delete mag geen verwijzing laten dangling of bestaand integriteitsbewijs
 * wissen. De aanroeper blokkeert vóór de eerste datastore-mutatie.
 */
function ledenAccountVerwijderBlokkades(array $leden, string $userId, string $gebruikersnaam): array
{
    $resultaat = [];
    foreach ($leden as $lid) {
        if (!is_array($lid)) continue;
        $status = ledenAccountKoppelingStatus($lid, $userId, $gebruikersnaam);
        if ($status === 'geen') continue;
        $resultaat[] = [
            'lid_id' => trim((string)($lid['id'] ?? '')),
            'status' => $status,
            'gearchiveerd' => trim((string)($lid['gearchiveerd_op'] ?? '')) !== '',
            'user_id' => trim((string)($lid['user_id'] ?? '')),
            'beheer_account' => trim((string)($lid['beheer_account'] ?? '')),
        ];
    }
    return $resultaat;
}

/**
 * Read-only diagnose van auth->lid relaties. Geldige legacy-koppelingen met
 * lege user_id blijven toegestaan; ontbrekende accounts en conflicten worden
 * expliciet gerapporteerd zodat ze niet door runtime-fallbacks gemaskeerd zijn.
 */
function ledenAccountIntegriteit(array $leden, array $gebruikers): array
{
    $opId = [];
    $opNaam = [];
    foreach ($gebruikers as $gebruiker) {
        if (!is_array($gebruiker)) continue;
        $id = function_exists('authGebruikerId') ? authGebruikerId($gebruiker) : trim((string)($gebruiker['id'] ?? ''));
        $naam = trim((string)($gebruiker['gebruikersnaam'] ?? ''));
        if ($id !== '') $opId[$id] = $gebruiker;
        if ($naam !== '') $opNaam[strtolower($naam)] = $gebruiker;
    }

    $rapport = [
        'ontbrekende_user_ids' => [],
        'ontbrekende_legacy_accounts' => [],
        'conflicten' => [],
    ];

    foreach ($leden as $lid) {
        if (!is_array($lid)) continue;
        $lidId = trim((string)($lid['id'] ?? ''));
        $userId = trim((string)($lid['user_id'] ?? ''));
        $naam = trim((string)($lid['beheer_account'] ?? ''));
        if ($userId === '' && $naam === '') continue;

        if ($userId !== '') {
            $vastAccount = $opId[$userId] ?? null;
            if (!is_array($vastAccount)) {
                $rapport['ontbrekende_user_ids'][] = ['lid_id' => $lidId, 'user_id' => $userId, 'beheer_account' => $naam];
            }

            if ($naam !== '') {
                $vastNaam = is_array($vastAccount) ? trim((string)($vastAccount['gebruikersnaam'] ?? '')) : '';
                $naamAccount = $opNaam[strtolower($naam)] ?? null;
                $naamAccountId = is_array($naamAccount)
                    ? (function_exists('authGebruikerId') ? authGebruikerId($naamAccount) : trim((string)($naamAccount['id'] ?? '')))
                    : '';
                if (($vastNaam !== '' && strcasecmp($vastNaam, $naam) !== 0)
                    || ($naamAccountId !== '' && !hash_equals($userId, $naamAccountId))) {
                    $rapport['conflicten'][] = [
                        'lid_id' => $lidId,
                        'user_id' => $userId,
                        'beheer_account' => $naam,
                        'beheer_account_user_id' => $naamAccountId,
                    ];
                }
            }
            continue;
        }

        if ($naam !== '' && !isset($opNaam[strtolower($naam)])) {
            $rapport['ontbrekende_legacy_accounts'][] = ['lid_id' => $lidId, 'beheer_account' => $naam];
        }
    }

    $rapport['aantallen'] = [
        'ontbrekende_user_ids' => count($rapport['ontbrekende_user_ids']),
        'ontbrekende_legacy_accounts' => count($rapport['ontbrekende_legacy_accounts']),
        'conflicten' => count($rapport['conflicten']),
    ];
    $rapport['totaal'] = array_sum($rapport['aantallen']);
    return $rapport;
}
