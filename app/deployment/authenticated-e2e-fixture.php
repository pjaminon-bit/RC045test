<?php
// Pure fixture builders for the dedicated authenticated VPS-test identity.
require_once dirname(__DIR__, 2) . '/app/auth-capabilities.php';

function e2e510Marker(): string { return 'vps-authenticated-e2e-v1'; }

function e2e510Username(string $value): string
{
    $value = trim($value);
    if (preg_match('/^[A-Za-z0-9._-]{2,30}$/D', $value) !== 1) {
        throw new InvalidArgumentException('E2E-gebruikersnaam moet 2-30 veilige tekens bevatten.');
    }
    return $value;
}

function e2e510Ids(string $tenant): array
{
    $tenant = tenantRuntimeVeiligeSleutel($tenant);
    if ($tenant === '' || $tenant === 'default') throw new InvalidArgumentException('Concrete tenant-key is verplicht.');
    $suffix = substr(hash('sha256', 'authenticated-e2e|' . $tenant), 0, 16);
    return [
        'admin_user' => 'usr_e2e_admin_' . $suffix,
        'member_user' => 'usr_e2e_member_' . $suffix,
        'member' => 'lid_e2e_' . $suffix,
        'group' => 'commissie_e2e_' . $suffix,
        'meeting' => 'verg_e2e_' . $suffix,
        'task' => 'taak_e2e_' . $suffix,
    ];
}

function e2e510AllCapabilities(): array
{
    $caps = array_keys(authCapabilityDefinities());
    sort($caps, SORT_STRING);
    return $caps;
}

function e2e510FixtureRecord(array $record, string $tenant, string $role): bool
{
    return ($record['e2e_fixture'] ?? '') === e2e510Marker()
        && ($record['e2e_tenant'] ?? '') === $tenant
        && ($record['e2e_role'] ?? '') === $role;
}

function e2e510MergeAuthUsers(array $users, string $tenant, string $adminUser, string $memberUser, string $passwordHash): array
{
    $tenant = tenantRuntimeVeiligeSleutel($tenant);
    $adminUser = e2e510Username($adminUser);
    $memberUser = e2e510Username($memberUser);
    if (strcasecmp($adminUser, $memberUser) === 0) throw new InvalidArgumentException('E2E-admin en E2E-lid moeten verschillende accounts zijn.');
    if ((password_get_info($passwordHash)['algoName'] ?? 'unknown') === 'unknown') throw new InvalidArgumentException('E2E password_hash is ongeldig.');

    $ids = e2e510Ids($tenant);
    $roles = [
        'admin' => ['id' => $ids['admin_user'], 'name' => $adminUser],
        'member' => ['id' => $ids['member_user'], 'name' => $memberUser],
    ];
    $existing = [];
    $kept = [];
    foreach ($users as $record) {
        if (!is_array($record)) throw new RuntimeException('Authstore bevat een ongeldig record.');
        $id = trim((string)($record['id'] ?? ''));
        $name = trim((string)($record['gebruikersnaam'] ?? ''));
        $matchedRole = null;
        foreach ($roles as $role => $target) {
            if (($id !== '' && hash_equals($target['id'], $id)) || ($name !== '' && strcasecmp($target['name'], $name) === 0)) {
                $matchedRole = $role;
                break;
            }
        }
        if ($matchedRole === null) {
            $kept[] = $record;
            continue;
        }
        if (!e2e510FixtureRecord($record, $tenant, $matchedRole)) {
            throw new RuntimeException('E2E-account botst met een bestaand niet-fixture account.');
        }
        $existing[$matchedRole] = $record;
    }

    $now = gmdate('c');
    foreach ($roles as $role => $target) {
        $old = $existing[$role] ?? [];
        $caps = $role === 'admin' ? e2e510AllCapabilities() : [];
        $created = trim((string)($old['aangemaakt'] ?? '')) ?: $now;
        $version = max(0, (int)($old['sessie_versie'] ?? 0)) + 1;
        $kept[] = [
            'id' => $target['id'],
            'gebruikersnaam' => $target['name'],
            'hash' => $passwordHash,
            'aangemaakt' => $created,
            'wachtwoord_gewijzigd' => $now,
            'sessie_versie' => $version,
            'actief' => true,
            'capabilities' => $caps,
            'tabs' => authLegacyTabsVoorCapabilities($caps),
            'e2e_fixture' => e2e510Marker(),
            'e2e_tenant' => $tenant,
            'e2e_role' => $role,
        ];
    }
    return array_values($kept);
}

function e2e510MergeLeden(array $doc, string $tenant, string $memberUser): array
{
    $ids = e2e510Ids($tenant);
    $memberUser = e2e510Username($memberUser);
    $now = gmdate('c');
    $leden = [];
    foreach ((array)($doc['leden'] ?? []) as $lid) {
        if (is_array($lid) && ($lid['id'] ?? '') === $ids['member']) continue;
        $leden[] = $lid;
    }
    $leden[] = [
        'id' => $ids['member'], 'nummer' => 'E2E-' . strtoupper(substr($ids['member'], -6)),
        'voornaam' => 'E2E', 'tussenvoegsel' => '', 'achternaam' => 'Testlid',
        'straat' => 'Teststraat', 'huisnummer' => '42', 'postcode' => '1234 AB', 'gemeente' => 'Testdorp', 'land' => 'Nederland',
        'email' => 'e2e-testlid@example.invalid', 'telefoon' => '0600000000', 'geboortedatum' => '1990-01-01',
        'status' => 'actief', 'bestuursfunctie' => '', 'commissies' => [],
        'user_id' => $ids['member_user'], 'beheer_account' => $memberUser,
        'aangemaakt' => $now, 'gewijzigd' => $now, 'gearchiveerd_op' => '', 'gearchiveerd_door' => '',
        'e2e_fixture' => e2e510Marker(),
    ];
    $doc['leden'] = array_values($leden);
    $doc['updated'] = $now;
    if (!isset($doc['volgnummer'])) $doc['volgnummer'] = 0;
    return $doc;
}

function e2e510MergeContributies(array $doc, string $tenant): array
{
    $ids = e2e510Ids($tenant);
    $year = (int)gmdate('Y'); $now = gmdate('c'); $today = gmdate('Y-m-d');
    $regels = [];
    foreach ((array)($doc['regels'] ?? []) as $regel) {
        if (is_array($regel) && ($regel['lid_id'] ?? '') === $ids['member'] && (int)($regel['jaar'] ?? 0) === $year) continue;
        $regels[] = $regel;
    }
    $regels[] = [
        'id' => 'contrib_e2e_' . substr(hash('sha256', $ids['member'] . '|' . $year), 0, 16),
        'lid_id' => $ids['member'], 'jaar' => $year, 'lidmaatschap_type' => '', 'status' => 'deels_betaald',
        'verschuldigd_bedrag' => 100.00, 'inschrijfgeld' => 0.00, 'betaald_bedrag' => 25.00, 'betaald_op' => $today,
        'vrijstelling_reden' => '', 'opmerking' => 'Authenticated VPS E2E fixture', 'aangemaakt' => $now, 'gewijzigd' => $now,
    ];
    $doc['regels'] = array_values($regels); $doc['updated'] = $now;
    return $doc;
}

function e2e510MergeGroepen(array $doc, string $tenant): array
{
    $ids = e2e510Ids($tenant); $now = gmdate('c'); $today = gmdate('Y-m-d');
    if (empty($doc['rollen']) || !is_array($doc['rollen'])) {
        $doc['rollen'] = [
            ['id'=>'trekker','naam'=>'Trekker','actief'=>true], ['id'=>'voorzitter','naam'=>'Voorzitter','actief'=>true],
            ['id'=>'secretaris','naam'=>'Secretaris','actief'=>true], ['id'=>'bestuurslid','naam'=>'Verantwoordelijk bestuurslid','actief'=>true],
            ['id'=>'lid','naam'=>'Lid','actief'=>true],
        ];
    }
    $groepen = [];
    foreach ((array)($doc['groepen'] ?? []) as $groep) {
        if (is_array($groep) && ($groep['id'] ?? '') === $ids['group']) continue;
        $groepen[] = $groep;
    }
    $groepen[] = [
        'id'=>$ids['group'],'type'=>'commissie','naam'=>'E2E Testcommissie','omschrijving'=>'Dedicated synthetische VPS-testfixture',
        'doel'=>'Authenticated ledenportaal end-to-end bewijzen','status'=>'actief','startdatum'=>$today,'einddatum'=>'',
        'leden'=>[['lid_id'=>$ids['member'],'rollen'=>['lid'],'sinds'=>$today,'tot'=>'']], 'aangemaakt'=>$now,'gewijzigd'=>$now,
    ];
    $doc['schema']=2; $doc['groepen']=array_values($groepen);
    if (!isset($doc['relaties']) || !is_array($doc['relaties'])) $doc['relaties']=[];
    $doc['updated']=$now;
    return $doc;
}

function e2e510MergeVergaderingen(array $doc, string $tenant): array
{
    $ids=e2e510Ids($tenant); $now=gmdate('c'); $today=gmdate('Y-m-d'); $lijst=[];
    foreach ((array)($doc['vergaderingen'] ?? []) as $vergadering) {
        if (is_array($vergadering) && ($vergadering['id'] ?? '') === $ids['meeting']) continue;
        $lijst[]=$vergadering;
    }
    $lijst[]=[
        'id'=>$ids['meeting'],'nummer'=>999,'titel'=>'E2E ledenvergadering','datum'=>$today,'tijd'=>'19:30','locatie'=>'Testlocatie',
        'status'=>'afgerond','soort'=>'leden','ledenvergadering_type'=>'regulier','agenda_status'=>'definitief','notulen_status'=>'definitief',
        'agenda'=>[['onderwerp'=>'E2E agendapunt','indiener'=>'E2E','toelichting'=>'Synthetische testagenda','besluit'=>'E2E besluit']],
        'notulen'=>'E2E definitieve notulen','aanwezigheid'=>[$ids['member']=>'aanwezig'],'aangemaakt'=>$now,'aangemaakt_door'=>'e2e-fixture','gewijzigd'=>$now,
        'e2e_fixture'=>e2e510Marker(),
    ];
    $doc['vergaderingen']=array_values($lijst); if(!isset($doc['volgnummer']))$doc['volgnummer']=0; $doc['updated']=$now;
    return $doc;
}

function e2e510MergeTaken(array $doc, string $tenant): array
{
    $ids=e2e510Ids($tenant); $now=gmdate('c'); $today=gmdate('Y-m-d'); $lijst=[];
    foreach ((array)($doc['taken'] ?? []) as $taak) {
        if (is_array($taak) && ($taak['id'] ?? '') === $ids['task']) continue;
        $lijst[]=$taak;
    }
    $lijst[]=[
        'id'=>$ids['task'],'nummer'=>999,'omschrijving'=>'E2E taak voor testlid','status'=>'open','toegewezen_aan'=>$ids['member'],
        'deadline'=>$today,'opmerking'=>'Authenticated VPS E2E fixture','aangemaakt'=>$now,'gewijzigd'=>$now,'e2e_fixture'=>e2e510Marker(),
    ];
    $doc['taken']=array_values($lijst); if(!isset($doc['volgnummer']))$doc['volgnummer']=0; $doc['updated']=$now;
    return $doc;
}
