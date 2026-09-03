<?php
$root = dirname(__DIR__);
$local = $root . '/site-config.local.php';
$db = sys_get_temp_dir() . '/rc045test-152-' . bin2hex(random_bytes(5)) . '.sqlite';
if (is_file($local)) { fwrite(STDERR, "FOUT: site-config.local.php bestaat al; test weigert die te overschrijven.\n"); exit(1); }
if (!extension_loaded('pdo_sqlite')) { fwrite(STDERR, "FOUT: pdo_sqlite ontbreekt.\n"); exit(1); }
$config = "<?php\nreturn " . var_export([
    'vereniging' => ['sleutel' => 'security-152-pdo'],
    'opslag' => ['private_driver' => 'pdo', 'pdo' => ['dsn' => 'sqlite:' . $db, 'user' => '', 'password' => '']],
], true) . ";\n";
file_put_contents($local, $config, LOCK_EX);

function s152pAssert(bool $ok, string $message): void{if(!$ok)throw new RuntimeException($message);}

try {
    require_once $root . '/app/data-integriteit.php';
    s152pAssert(privateStoreDriver()==='pdo','PDO-profiel niet actief');

    $taken=['volgnummer'=>2,'taken'=>[
        ['id'=>'taak_1','nummer'=>1,'omschrijving'=>'A','vergadering_id'=>'verg_1','vergadering_soort'=>'bestuur','commissie_id'=>'commissie_a','commissie_bron'=>'groep'],
        ['id'=>'taak_2','nummer'=>2,'omschrijving'=>'B','vergadering_id'=>'','vergadering_soort'=>'','commissie_id'=>'commissie_a','commissie_bron'=>'groep'],
    ]];
    $verg=['volgnummer'=>1,'vergaderingen'=>[['id'=>'verg_1','nummer'=>1,'soort'=>'bestuur','titel'=>'V']]];
    $events=['volgnummer'=>1,'evenementen'=>[['id'=>'event_1','nummer'=>1,'titel'=>'E','deelnemers'=>[]]]];
    $groepen=['schema'=>2,'groepen'=>[['id'=>'commissie_a','type'=>'commissie','naam'=>'A','status'=>'actief']],'relaties'=>['commissie_a'=>['taken'=>['taak_1','taak_2'],'vergaderingen'=>['verg_1'],'evenementen'=>['event_1']]]];
    s152pAssert(repoTakenSchrijf($taken,false),'taken seed');
    s152pAssert(repoVergaderingenSchrijf($verg,false),'verg seed');
    s152pAssert(repoEvenementenSchrijf($events,false),'events seed');
    s152pAssert(repoGroepenSchrijf($groepen,false),'groepen seed');

    $r=dataIntegriteitVerwijderVergadering('verg_1');
    s152pAssert($r['gevonden']===true && $r['taken_ontkoppeld']===1 && $r['groep_relaties_verwijderd']===1,'PDO vergaderingcascade incorrect');
    s152pAssert((repoTakenLees()['taken'][0]['vergadering_id']??'x')==='','PDO taak niet ontkoppeld');
    s152pAssert(repoGroepenLees()['relaties']['commissie_a']['vergaderingen']===[],'PDO groep->vergadering niet opgeschoond');

    $r=dataIntegriteitVerwijderEvenement('event_1');
    s152pAssert($r['gevonden']===true && repoGroepenLees()['relaties']['commissie_a']['evenementen']===[],'PDO eventcascade incorrect');
    $r=dataIntegriteitVerwijderTaak('taak_1');
    s152pAssert($r['gevonden']===true && repoGroepenLees()['relaties']['commissie_a']['taken']===['taak_2'],'PDO taakcascade incorrect');
    s152pAssert((repoTakenLees()['taken'][0]['commissie_bron']??'')==='groep','#151 provenance verloren in PDO');

    // Echte PDO rollback op een latere storewrite.
    $taken=['volgnummer'=>1,'taken'=>[['id'=>'taak_r','nummer'=>1,'omschrijving'=>'R','vergadering_id'=>'verg_r','vergadering_soort'=>'bestuur']]];
    $verg=['volgnummer'=>1,'vergaderingen'=>[['id'=>'verg_r','nummer'=>1,'soort'=>'bestuur','titel'=>'R']]];
    $groepen=['schema'=>2,'groepen'=>[['id'=>'commissie_a','type'=>'commissie','naam'=>'A','status'=>'actief']],'relaties'=>['commissie_a'=>['taken'=>[],'vergaderingen'=>['verg_r'],'evenementen'=>[]]]];
    repoTakenSchrijf($taken,false);repoVergaderingenSchrijf($verg,false);repoGroepenSchrijf($groepen,false);
    $fout=false;
    try { dataIntegriteitVerwijderVergadering('verg_r',['taken'=>static fn(array $doc): bool=>false]); }
    catch(RuntimeException $e){$fout=true;}
    s152pAssert($fout,'PDO latere writefailure gaf geen fout');
    s152pAssert(array_column(repoVergaderingenLees()['vergaderingen'],'id')===['verg_r'],'PDO primaire delete niet gerollbackt');
    s152pAssert((repoTakenLees()['taken'][0]['vergadering_id']??'')==='verg_r','PDO taakrelation niet gerollbackt');
    s152pAssert(repoGroepenLees()['relaties']['commissie_a']['vergaderingen']===['verg_r'],'PDO groeprelation niet gerollbackt');

    // Detector/repair op PDO en geldige relaties behouden.
    $taken=['volgnummer'=>2,'taken'=>[
        ['id'=>'taak_ok','nummer'=>1,'omschrijving'=>'OK','vergadering_id'=>'verg_ok','vergadering_soort'=>'bestuur'],
        ['id'=>'taak_bad','nummer'=>2,'omschrijving'=>'BAD','vergadering_id'=>'verg_weg','vergadering_soort'=>'bestuur'],
    ]];
    $verg=['volgnummer'=>1,'vergaderingen'=>[['id'=>'verg_ok','nummer'=>1,'soort'=>'bestuur','titel'=>'OK']]];
    $events=['volgnummer'=>1,'evenementen'=>[['id'=>'event_ok','nummer'=>1,'titel'=>'OK','deelnemers'=>[]]]];
    $groepen=['schema'=>2,'groepen'=>[['id'=>'commissie_a','type'=>'commissie','naam'=>'A','status'=>'actief']],'relaties'=>['commissie_a'=>['taken'=>['taak_ok','taak_weg'],'vergaderingen'=>['verg_ok','verg_weg'],'evenementen'=>['event_ok','event_weg']]]];
    repoTakenSchrijf($taken,false);repoVergaderingenSchrijf($verg,false);repoEvenementenSchrijf($events,false);repoGroepenSchrijf($groepen,false);
    s152pAssert(dataIntegriteitDetecteer()['totaal']===4,'PDO detector vond niet alle dangling refs');
    $repair=dataIntegriteitHerstelDangling();
    s152pAssert($repair['na']['totaal']===0,'PDO repair liet dangling refs achter');
    s152pAssert((repoTakenLees()['taken'][0]['vergadering_id']??'')==='verg_ok','PDO repair verwijderde geldige taakrelatie');
    s152pAssert(repoGroepenLees()['relaties']['commissie_a']['taken']===['taak_ok'],'PDO repair verwijderde/behouden groeprelatie incorrect');

    echo "security-152-delete-integrity-pdo: OK\n";
} finally {
    @unlink($local);
    @unlink($db);
}
