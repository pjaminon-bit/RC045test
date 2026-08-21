<?php
// Fase 5.1.2 — pure control-plane credentialvalidatie.
// Alle records in operators.htpasswd moeten uniek, bcrypt en exact cost 12 zijn.

function control512BcryptCost(): int { return 12; }

function control512HtpasswdRecords(string $raw): array
{
    if ($raw === '' || str_contains($raw, "\0")) throw new RuntimeException('Operatorbestand is leeg of bevat NUL-bytes.');
    $records = [];
    foreach (preg_split('/\r?\n/', rtrim($raw, "\r\n")) ?: [] as $line) {
        if ($line === '') throw new RuntimeException('Operatorbestand bevat lege records.');
        $parts = explode(':', $line, 2);
        if (count($parts) !== 2) throw new RuntimeException('Operatorbestand bevat ongeldig recordformaat.');
        [$user,$hash] = $parts;
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{2,63}$/D', $user) !== 1) throw new RuntimeException('Operatorbestand bevat ongeldige gebruikersnaam.');
        if (isset($records[$user])) throw new RuntimeException('Operatorbestand bevat dubbele gebruikersnaam.');
        $cost = str_pad((string)control512BcryptCost(), 2, '0', STR_PAD_LEFT);
        if (preg_match('/^\$2[aby]\$' . preg_quote($cost, '/') . '\$[.\/A-Za-z0-9]{53}$/D', $hash) !== 1) {
            throw new RuntimeException('Ieder operatorrecord moet bcrypt met exact cost ' . control512BcryptCost() . ' gebruiken.');
        }
        $records[$user] = $hash;
    }
    if ($records === []) throw new RuntimeException('Operatorbestand bevat geen operatorrecords.');
    return $records;
}

function control512HtpasswdValidate(string $raw, ?string $requiredUser = null): array
{
    $records = control512HtpasswdRecords($raw);
    if ($requiredUser !== null && !array_key_exists($requiredUser, $records)) {
        throw new RuntimeException('Verwachte operator ontbreekt in operatorbestand.');
    }
    return $records;
}
