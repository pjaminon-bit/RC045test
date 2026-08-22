<?php
if (PHP_SAPI !== 'cli') { fwrite(STDERR, "Alleen CLI.\n"); exit(1); }

$inputDir = $argv[1] ?? '';
$outputDir = $argv[2] ?? '';
if ($inputDir === '' && $outputDir === '') {
    echo "Authenticated E2E fixture helper: alleen actief met expliciete input/output.\n";
    exit(0);
}

$member = getenv('E2E_MEMBER_USER') ?: '';
if ($inputDir === '' || $outputDir === '' || preg_match('/^[a-zA-Z0-9._-]{2,30}$/D', $member) !== 1) {
    fwrite(STDERR, "Ongeldige fixture-invoer.\n"); exit(1);
}
if (!is_dir($inputDir) || (!is_dir($outputDir) && !mkdir($outputDir, 0700, true))) {
    fwrite(STDERR, "Fixturemap ontbreekt.\n"); exit(1);
}

function e2eDoc(string $path, array $default): array {
    if (!is_file($path)) return $default;
    $raw = file_get_contents($path);
    if (!is_string($raw)) throw new RuntimeException('Fixturebron onleesbaar: ' . basename($path));
    $start = strpos($raw, '{');
    if ($start === false) return $default;
    $doc = json_decode(substr($raw, $start), true);
    if (!is_array($doc)) throw new RuntimeException('Fixturebron bevat ongeldige JSON: ' . basename($path));
    return $doc;
}
function e2eWrite(string $path, array $doc): void {
    $json = json_encode($doc, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if (!is_string($json) || file_put_contents($path, "<?php exit; ?>\n" . $json . "\n") === false) {
        throw new RuntimeException('Fixture kon niet worden geschreven: ' . basename($path));
    }
    chmod($path, 0600);
}

try {
    $suffix = substr(hash('sha256', $member), 0, 16);
    $userId = 'usr_e2e_member_' . $suffix;
    $lidId = 'lid_e2e_' . $suffix;
    $groepId = 'commissie_e2e_' . $suffix;
    $vergId = 'verg_e2e_' . $suffix;
    $taakId = 'taak_e2e_' . $suffix;
    $now = gmdate('c');
    $today = gmdate('Y-m-d');
    $year = (int)gmdate('Y');

    $leden = e2eDoc($inputDir . '/leden-data.php', ['updated'=>$now,'volgnummer'=>0,'leden'=>[],'commissies'=>[]]);
    $leden['leden'] = array_values(array_filter((array)($leden['leden'] ?? []), static fn($r) => !is_array($r) || ($r['id'] ?? '') !== $lidId));
    $leden['leden'][] = [
        'id'=>$lidId,'nummer'=>'E2E-' . substr($suffix,0,6),'voornaam'=>'E2E','tussenvoegsel'=>'','achternaam'=>'Testlid',
        'straat'=>'Teststraat','huisnummer'=>'42','postcode'=>'1234 AB','gemeente'=>'Testdorp',
        'email'=>'e2e-testlid@example.invalid','telefoon'=>'0612345678','geboortedatum'=>'1990-01-01',
        'status'=>'actief','bestuursfunctie'=>'','commissies'=>[],'user_id'=>$userId,'beheer_account'=>$member,
        'aangemaakt'=>$now,'gewijzigd'=>$now,'gearchiveerd_op'=>'','gearchiveerd_door'=>''
    ];
    $leden['updated'] = $now;
    e2eWrite($outputDir . '/leden-data.php', $leden);

    $contrib = e2eDoc($inputDir . '/contributies-data.php', ['updated'=>$now,'regels'=>[]]);
    $contrib['regels'] = array_values(array_filter((array)($contrib['regels'] ?? []), static fn($r) => !is_array($r) || (($r['lid_id'] ?? '') !== $lidId || (int)($r['jaar'] ?? 0) !== $year)));
    $contrib['regels'][] = [
        'id'=>'contrib_e2e_' . $suffix,'lid_id'=>$lidId,'jaar'=>$year,'lidmaatschap_type'=>'','status'=>'deels_betaald',
        'verschuldigd_bedrag'=>100.00,'inschrijfgeld'=>0.00,'betaald_bedrag'=>25.00,'betaald_op'=>$today,
        'vrijstelling_reden'=>'','opmerking'=>'Authenticated E2E fixture','aangemaakt'=>$now,'gewijzigd'=>$now
    ];
    $contrib['updated'] = $now;
    e2eWrite($outputDir . '/contributies-data.php', $contrib);

    $groepen = e2eDoc($inputDir . '/groepen-data.php', [
        'schema'=>2,
        'rollen'=>[
            ['id'=>'trekker','naam'=>'Trekker','actief'=>true],['id'=>'voorzitter','naam'=>'Voorzitter','actief'=>true],
            ['id'=>'secretaris','naam'=>'Secretaris','actief'=>true],['id'=>'bestuurslid','naam'=>'Verantwoordelijk bestuurslid','actief'=>true],
            ['id'=>'lid','naam'=>'Lid','actief'=>true]
        ],
        'groepen'=>[],'relaties'=>[],'updated'=>$now
    ]);
    $groepen['groepen'] = array_values(array_filter((array)($groepen['groepen'] ?? []), static fn($g) => !is_array($g) || ($g['id'] ?? '') !== $groepId));
    $groepen['groepen'][] = [
        'id'=>$groepId,'type'=>'commissie','naam'=>'E2E Testcommissie','omschrijving'=>'Tijdelijke authenticated E2E-fixture',
        'doel'=>'Bewijst gekoppelde groepsweergave','status'=>'actief','startdatum'=>$today,'einddatum'=>'',
        'leden'=>[['lid_id'=>$lidId,'rollen'=>['lid'],'sinds'=>$today,'tot'=>'']], 'aangemaakt'=>$now,'gewijzigd'=>$now
    ];
    $groepen['updated'] = $now;
    e2eWrite($outputDir . '/groepen-data.php', $groepen);

    $verg = e2eDoc($inputDir . '/vergaderingen-data.php', ['updated'=>$now,'volgnummer'=>0,'vergaderingen'=>[]]);
    $verg['vergaderingen'] = array_values(array_filter((array)($verg['vergaderingen'] ?? []), static fn($v) => !is_array($v) || ($v['id'] ?? '') !== $vergId));
    $verg['vergaderingen'][] = [
        'id'=>$vergId,'nummer'=>999,'titel'=>'E2E ledenvergadering','datum'=>$today,'tijd'=>'19:30','locatie'=>'Testlocatie',
        'status'=>'afgerond','soort'=>'leden','ledenvergadering_type'=>'regulier','agenda_status'=>'definitief','notulen_status'=>'definitief',
        'agenda'=>[['onderwerp'=>'E2E agendapunt','indiener'=>'E2E','toelichting'=>'Tijdelijke testagenda','besluit'=>'E2E besluit']],
        'notulen'=>'E2E definitieve notulen','aanwezigheid'=>[$lidId=>'aanwezig'],'aangemaakt'=>$now,'aangemaakt_door'=>'e2e','gewijzigd'=>$now
    ];
    $verg['updated'] = $now;
    e2eWrite($outputDir . '/vergaderingen-data.php', $verg);

    $taken = e2eDoc($inputDir . '/taken-data.php', ['updated'=>$now,'volgnummer'=>0,'taken'=>[]]);
    $taken['taken'] = array_values(array_filter((array)($taken['taken'] ?? []), static fn($t) => !is_array($t) || ($t['id'] ?? '') !== $taakId));
    $taken['taken'][] = [
        'id'=>$taakId,'nummer'=>999,'omschrijving'=>'E2E taak voor testlid','status'=>'open','toegewezen_aan'=>$lidId,
        'deadline'=>$today,'opmerking'=>'Authenticated E2E fixture','aangemaakt'=>$now,'gewijzigd'=>$now
    ];
    $taken['updated'] = $now;
    e2eWrite($outputDir . '/taken-data.php', $taken);

    echo "Authenticated E2E fixtures prepared for synthetic linked member.\n";
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
