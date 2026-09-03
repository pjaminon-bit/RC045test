<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c116(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}

function wis116(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        wis116($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

function event116(string $id, string $datum, string $begin, string $eind, array $deelnemers = [], int $capaciteit = 0, string $zichtbaarheid = 'leden'): array
{
    return [
        'id'=>$id,'nummer'=>1,'titel'=>$id,'datum'=>$datum,'tijd'=>'10:00','eindtijd'=>'',
        'locatie'=>'','omschrijving'=>'','betaalverzoek'=>'','inschrijving_begin'=>$begin,
        'inschrijving_eind'=>$eind,'capaciteit'=>$capaciteit,'zichtbaarheid'=>$zichtbaarheid,
        'deelnemers'=>$deelnemers,'aangemaakt'=>date('c'),'aangemaakt_door'=>'test','gewijzigd'=>date('c'),
    ];
}

function evenementUitStore116(string $id): ?array
{
    foreach ((array)(repoEvenementenLees()['evenementen'] ?? []) as $e) {
        if (is_array($e) && ($e['id'] ?? '') === $id) return $e;
    }
    return null;
}

$tmp = sys_get_temp_dir() . '/issue116-events-' . bin2hex(random_bytes(5));
$private = $tmp . '/private';
$configPad = $tmp . '/tenant.php';
@mkdir($private, 0750, true);

$config = [
    'vereniging'=>['sleutel'=>'issue116','naam'=>'Issue 116','volledige_naam'=>'Issue 116','site_url'=>'https://example.invalid','timezone'=>'Europe/Amsterdam'],
    'modules'=>['ledenadministratie'=>true,'evenementen'=>true,'vergaderingen'=>false,'taken'=>false,'operationele_taken'=>false,'werkgroepen'=>false],
    'opslag'=>['private_driver'=>'json','private_root'=>$private],
];
file_put_contents($configPad, "<?php\nreturn " . var_export($config, true) . ";\n");
putenv('VERENIGING_CONFIG_FILE=' . $configPad);

try {
    require_once $root . '/app/leden/portaal-service.php';

    $lid = ['id'=>'lid_normaal','gearchiveerd_op'=>'','bestuursfunctie'=>''];
    $bestuur = ['id'=>'lid_bestuur','gearchiveerd_op'=>'','bestuursfunctie'=>'bestuurslid'];
    $gearchiveerd = ['id'=>'lid_archief','gearchiveerd_op'=>date('c'),'bestuursfunctie'=>''];
    c116(repoLedenSchrijf(['updated'=>date('c'),'volgnummer'=>3,'leden'=>[$lid,$bestuur,$gearchiveerd]], false), 'testtenant bevat actieve en gearchiveerde lidcontexten');

    $morgen = date('Y-m-d', strtotime('+10 days'));
    $gisteren = date('Y-m-d', strtotime('-1 day'));
    $voorBegin = date('Y-m-d', strtotime('+2 days'));
    $openBegin = date('Y-m-d', strtotime('-2 days'));
    $openEind = date('Y-m-d', strtotime('+2 days'));
    $geslotenEind = date('Y-m-d', strtotime('-1 day'));

    $events = [
        event116('open', $morgen, $openBegin, $openEind),
        event116('gesloten_leeg', $morgen, $openBegin, $geslotenEind),
        event116('gesloten_deelnemer', $morgen, $openBegin, $geslotenEind, ['lid_normaal']),
        event116('afgelopen_deelnemer', $gisteren, $openBegin, $openEind, ['lid_normaal']),
        event116('vol', $morgen, $openBegin, $openEind, ['ander_lid'], 1),
        event116('bestuur_only', $morgen, $openBegin, $openEind, [], 0, 'bestuur'),
        event116('nog_niet_open', $morgen, $voorBegin, $openEind),
    ];
    c116(repoEvenementenSchrijf(['updated'=>date('c'),'volgnummer'=>7,'evenementen'=>$events], false), 'testtenant bevat alle deelnamegrenzen');

    $open = evenementUitStore116('open');
    $geslotenLeeg = evenementUitStore116('gesloten_leeg');
    $geslotenDeelnemer = evenementUitStore116('gesloten_deelnemer');
    $afgelopen = evenementUitStore116('afgelopen_deelnemer');
    $vol = evenementUitStore116('vol');
    $bestuurOnly = evenementUitStore116('bestuur_only');
    $nogNietOpen = evenementUitStore116('nog_niet_open');

    c116(is_array($open) && portaalEvenementDeelnameMogelijkheden($open, $lid)['actie'] === 'inschrijven', 'open + niet ingeschreven toont inschrijven');
    c116(is_array($geslotenLeeg) && portaalEvenementDeelnameMogelijkheden($geslotenLeeg, $lid)['actie'] === '', 'gesloten + niet ingeschreven toont geen actie');
    c116(is_array($geslotenDeelnemer) && portaalEvenementDeelnameMogelijkheden($geslotenDeelnemer, $lid)['actie'] === 'uitschrijven', 'gesloten + wel ingeschreven toont uitschrijven');
    c116(is_array($afgelopen) && portaalEvenementDeelnameMogelijkheden($afgelopen, $lid)['actie'] === '', 'afgelopen evenement toont geen mutatie');
    c116(is_array($vol) && portaalEvenementDeelnameMogelijkheden($vol, $lid)['actie'] === '', 'capaciteit bereikt toont geen nieuwe inschrijving');
    c116(is_array($bestuurOnly) && !portaalEvenementDeelnameMogelijkheden($bestuurOnly, $lid)['toegankelijk'], 'verborgen bestuursevenement is niet toegankelijk voor gewoon lid');
    c116(is_array($bestuurOnly) && portaalEvenementDeelnameMogelijkheden($bestuurOnly, $bestuur)['actie'] === 'inschrijven', 'bestuursevenement is toegankelijk voor geldig bestuurslid');
    c116(is_array($nogNietOpen) && portaalEvenementDeelnameMogelijkheden($nogNietOpen, $bestuur)['actie'] === '', 'bestuurslid kan preview zien maar inschrijfstart niet omzeilen');

    $f = '';
    c116(portaalEvenementDeelnameWijzigen('open', 'lid_normaal', true, $f), 'service accepteert normale open inschrijving');
    $openNa = evenementUitStore116('open');
    c116(is_array($openNa) && evenementHeeftDeelnemer($openNa, 'lid_normaal'), 'open inschrijving wordt daadwerkelijk opgeslagen');
    c116(portaalEvenementDeelnameWijzigen('open', 'lid_normaal', false, $f), 'service accepteert normale uitschrijving');
    $openNa = evenementUitStore116('open');
    c116(is_array($openNa) && !evenementHeeftDeelnemer($openNa, 'lid_normaal'), 'normale uitschrijving wordt daadwerkelijk opgeslagen');

    $f = '';
    c116(!portaalEvenementDeelnameWijzigen('gesloten_leeg', 'lid_normaal', true, $f) && str_contains($f, 'gesloten'), 'service weigert nieuwe inschrijving na deadline');
    $f = '';
    c116(portaalEvenementDeelnameWijzigen('gesloten_deelnemer', 'lid_normaal', false, $f), 'service staat bestaande uitschrijving na deadline toe');
    $geslotenNa = evenementUitStore116('gesloten_deelnemer');
    c116(is_array($geslotenNa) && !evenementHeeftDeelnemer($geslotenNa, 'lid_normaal'), 'uitschrijving na deadline wordt opgeslagen');

    $f = '';
    c116(!portaalEvenementDeelnameWijzigen('afgelopen_deelnemer', 'lid_normaal', false, $f) && str_contains($f, 'afgelopen'), 'service weigert mutatie van afgelopen evenement');
    $afgelopenNa = evenementUitStore116('afgelopen_deelnemer');
    c116(is_array($afgelopenNa) && evenementHeeftDeelnemer($afgelopenNa, 'lid_normaal'), 'historische deelname blijft bij geweigerde mutatie intact');

    $f = '';
    c116(!portaalEvenementDeelnameWijzigen('vol', 'lid_normaal', true, $f) && str_contains($f, 'vol'), 'service weigert nieuwe inschrijving wanneer capaciteit bereikt is');
    $f = '';
    c116(!portaalEvenementDeelnameWijzigen('bestuur_only', 'lid_normaal', true, $f) && str_contains($f, 'niet voor jou'), 'service weigert verborgen evenement voor gewoon lid');
    $f = '';
    c116(portaalEvenementDeelnameWijzigen('bestuur_only', 'lid_bestuur', true, $f), 'service accepteert bestuursevenement voor geldig bestuurslid');
    $f = '';
    c116(!portaalEvenementDeelnameWijzigen('nog_niet_open', 'lid_bestuur', true, $f) && str_contains($f, 'gesloten'), 'service laat bestuurslid inschrijfstart niet omzeilen');
    $f = '';
    c116(!portaalEvenementDeelnameWijzigen('open', 'bestaat_niet', true, $f) && str_contains($f, 'lid'), 'service weigert onbekende lidbinding');
    $f = '';
    c116(!portaalEvenementDeelnameWijzigen('open', 'lid_archief', true, $f) && str_contains($f, 'lid'), 'service weigert gearchiveerde lidbinding');

    $normaleIds = array_map(static fn($e)=>(string)($e['id']??''), portaalEvenementenVoorLid(false));
    $bestuursIds = array_map(static fn($e)=>(string)($e['id']??''), portaalEvenementenVoorLid(true));
    c116(!in_array('bestuur_only', $normaleIds, true) && !in_array('nog_niet_open', $normaleIds, true), 'gewone UI-lijst lekt verborgen of nog niet geopende evenementen niet');
    c116(in_array('bestuur_only', $bestuursIds, true) && in_array('nog_niet_open', $bestuursIds, true), 'bestuurs-UI behoudt bestaande previewzichtbaarheid');

    $uiBron = (string)file_get_contents($root . '/leden/index.php');
    $serviceBron = (string)file_get_contents($root . '/app/leden/portaal-service.php');
    $legacyBron = (string)file_get_contents($root . '/evenementen-opslag.php');
    c116(str_contains($uiBron, 'portaalEvenementDeelnameMogelijkheden($e,$lid)') && !str_contains($uiBron, 'evenementInschrijvingOpen($e)||$ingeschreven'), 'UI gebruikt centrale deelnamepolicy in plaats van eigen deadlinevoorwaarde');
    c116(str_contains($serviceBron, 'portaalEvenementDeelnameMogelijkheden($e,$lid)') && str_contains($serviceBron, 'portaalActiefLidVoorDeelname'), 'service gebruikt dezelfde policy en valideert actuele lidbinding');
    c116(str_contains($legacyBron, 'evenementDeelnameMogelijkheden($e, $lidId, evenementZichtbaarVoorLeden($e))') && str_contains($legacyBron, "if (!\$mogelijk['inschrijfperiode_open'])"), 'legacy helper onderscheidt uitschrijven van nieuwe inschrijving');
} finally {
    putenv('VERENIGING_CONFIG_FILE');
    wis116($tmp);
}

echo "Issue #116 event participation contract: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
