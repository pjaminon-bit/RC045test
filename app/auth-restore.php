<?php
// Gebruikersrestore mag nooit een eerder uitgegeven sessieversie terugbrengen.
// Geef ieder hersteld account daarom een nieuwe versie die afwijkt van zowel
// het snapshot als de huidige accounttoestand. De tijdgebonden hoge basis
// voorkomt bovendien dat oude standalone-sessies van verwijderde accounts
// praktisch opnieuw geldig kunnen worden wanneer zo'n account wordt hersteld.

function authRestoreRoteerSessieversies(array $herstel, array $huidig, ?int $nu = null): array
{
    $nu ??= time();
    $nu = max(1, $nu);
    $huidigeVersies = [];
    foreach ($huidig as $account) {
        if (!is_array($account)) continue;
        $naam = trim((string)($account['gebruikersnaam'] ?? ''));
        if ($naam === '') continue;
        $sleutel = strtolower($naam);
        $huidigeVersies[$sleutel] = max(
            (int)($huidigeVersies[$sleutel] ?? 1),
            max(1, (int)($account['sessie_versie'] ?? 1))
        );
    }

    foreach ($herstel as $i => $account) {
        if (!is_array($account)) continue;
        $naam = trim((string)($account['gebruikersnaam'] ?? ''));
        $oudeVersie = max(1, (int)($account['sessie_versie'] ?? 1));
        $huidigeVersie = $naam !== '' ? max(1, (int)($huidigeVersies[strtolower($naam)] ?? 1)) : 1;
        do {
            $nieuweVersie = $nu + random_int(1, 1_000_000);
        } while ($nieuweVersie === $oudeVersie || $nieuweVersie === $huidigeVersie);
        $account['sessie_versie'] = $nieuweVersie;
        $herstel[$i] = $account;
    }
    return $herstel;
}
