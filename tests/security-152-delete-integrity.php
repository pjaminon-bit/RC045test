<?php
$root = sys_get_temp_dir() . '/vp-sec152-json-' . bin2hex(random_bytes(6));
if (!mkdir($root, 0700, true)) throw new RuntimeException('Tijdelijke JSON-root kon niet worden gemaakt.');
putenv('VERENIGING_PRIVATE_ROOT=' . $root);
require_once dirname(__DIR__) . '/app/data-integriteit.php';

function s152Assert(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FOUT: {$message}\n");
        exit(1);
    }
}
function s152Taken(array $records): array{return ['volgnummer'=>count($records),'taken'=>$records,'updated'=>''];}
function s152Verg(array $records): array{return ['volgnummer'=>count($records),'vergaderingen'=>$records,'updated'=>''];}
function s152Events(array $records): array{return ['volgnummer'=>count($records),'evenementen'=>$records,'updated'=>''];}
function s152Groepen(array $relaties): array{return ['schema'=>2,'rollen'=>[],'groepen'=>[['id'=>'commissie_a','type'=>'commissie','naam'=>'A']], 'relaties'=>$relaties,'updated'=>''];}
function s152Seed(array $taken, array $verg, array $events, array $groepen): void
{
    s152Assert(repoTakenSchrijf($taken, false), 'taken seed write');
    s152Assert(repoVergaderingenSchrijf($verg, false), 'vergaderingen seed write');
    s152Assert(repoEvenementenSchrijf($events, false), 'evenementen seed write');
    s152Assert(repoGroepenSchrijf($groepen, false), 'groepen seed write');
}
function s152Taak(string $id, string $vergadering=''): array
{
    return ['id'=>$id,'nummer'=>1,'omschrijving'=>$id,'status'=>'open','vergadering_id'=>$vergadering,'vergadering_soort'=>$vergadering===''?'':'bestuur','commissie_id'=>'commissie_a','commissie_bron'=>'groep','gewijzigd'=>'oud'];
}
function s152Vergadering(string $id): array{return ['id'=>$id,'nummer'=>1,'soort'=>'bestuur','titel'=>$id,'datum'=>'2026-09-03'];}
function s152Event(string $id): array{return ['id'=>$id,'nummer'=>1,'titel'=>$id,'deelnemers'=>['lid_1']];}
function s152Rmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $item) $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    @rmdir($dir);
}

s152Assert(privateStoreDriver()==='json' && privateStoreJsonRoot()===$root, 'test moet geïsoleerde JSON private_root gebruiken');

// Vergadering verwijderen: taak blijft bestaan maar wordt ontkoppeld; alle groepsrefs verdwijnen.
s152Seed(
    s152Taken([s152Taak('taak_1','verg_1'),s152Taak('taak_2','verg_1'),s152Taak('taak_3','verg_2')]),
    s152Verg([s152Vergadering('verg_1'),s152Vergadering('verg_2')]),
    s152Events([s152Event('event_1')]),
    s152Groepen(['commissie_a'=>['taken'=>['taak_1'],'vergaderingen'=>['verg_1','verg_1','verg_2'],'evenementen'=>['event_1']]])
);
$r=dataIntegriteitVerwijderVergadering('verg_1');
s152Assert($r['gevonden']===true && $r['taken_ontkoppeld']===2 && $r['groep_relaties_verwijderd']===2,'vergaderingdelete moet alle afhankelijke refs opruimen');
$t=repoTakenLees();
s152Assert(count($t['taken'])===3,'vergaderingdelete mag taken niet verwijderen');
s152Assert(($t['taken'][0]['vergadering_id']??'x')==='' && ($t['taken'][0]['vergadering_soort']??'x')==='','eerste taak moet volledig ontkoppeld zijn');
s152Assert(($t['taken'][1]['vergadering_id']??'x')==='','tweede taak naar dezelfde vergadering moet ontkoppeld zijn');
s152Assert(($t['taken'][2]['vergadering_id']??'')==='verg_2','geldige andere vergaderingrelatie moet blijven');
s152Assert(dataIntegriteitDetecteer()['totaal']===0,'delete mag geen nieuwe dangling relaties nalaten');

// Taak verwijderen: hard delete + alle groep->taak refs; commissiecontract van andere taak blijft onaangeraakt.
s152Seed(
    s152Taken([s152Taak('taak_1'),s152Taak('taak_2')]),
    s152Verg([]),s152Events([]),
    s152Groepen(['commissie_a'=>['taken'=>['taak_1','taak_1','taak_2'],'vergaderingen'=>[],'evenementen'=>[]]])
);
$r=dataIntegriteitVerwijderTaak('taak_1');
s152Assert($r['gevonden']===true && $r['groep_relaties_verwijderd']===2,'taakdelete moet meervoudige groepsrefs verwijderen');
$t=repoTakenLees();$g=repoGroepenLees();
s152Assert(array_column($t['taken'],'id')===['taak_2'],'alleen doeltaak mag verdwijnen');
s152Assert(($t['taken'][0]['commissie_id']??'')==='commissie_a' && ($t['taken'][0]['commissie_bron']??'')==='groep','#151 commissieprovenance moet behouden blijven');
s152Assert($g['relaties']['commissie_a']['taken']===['taak_2'],'geldige groep->taak relatie moet behouden blijven');

// Evenement verwijderen: ingebedde deelnemers verdwijnen alleen met het primaire event; groepsref wordt gecascadet.
s152Seed(s152Taken([]),s152Verg([]),s152Events([s152Event('event_1'),s152Event('event_2')]),s152Groepen(['commissie_a'=>['taken'=>[],'vergaderingen'=>[],'evenementen'=>['event_1','event_2']]]));
$r=dataIntegriteitVerwijderEvenement('event_1');
s152Assert($r['gevonden']===true && $r['groep_relaties_verwijderd']===1,'eventdelete moet groepsref verwijderen');
s152Assert(array_column(repoEvenementenLees()['evenementen'],'id')===['event_2'],'ander evenement moet blijven');
s152Assert(repoGroepenLees()['relaties']['commissie_a']['evenementen']===['event_2'],'geldige groep->event relatie moet blijven');

// Object zonder relaties: delete schrijft alleen primaire store en blijft geldig.
s152Seed(s152Taken([s152Taak('taak_los')]),s152Verg([]),s152Events([]),s152Groepen(['commissie_a'=>['taken'=>[],'vergaderingen'=>[],'evenementen'=>[]]]));
$r=dataIntegriteitVerwijderTaak('taak_los');
s152Assert($r['gevonden']===true && $r['groep_relaties_verwijderd']===0 && repoTakenLees()['taken']===[],'relationeel los object moet normaal verwijderd worden');

// Latere storewrite faalt: eerdere primaire delete moet volledig rollbacken.
s152Seed(s152Taken([s152Taak('taak_1','verg_1')]),s152Verg([s152Vergadering('verg_1')]),s152Events([]),s152Groepen(['commissie_a'=>['taken'=>[],'vergaderingen'=>['verg_1'],'evenementen'=>[]]]));
$fout=false;
try {
    dataIntegriteitVerwijderVergadering('verg_1', ['taken'=>static fn(array $doc): bool=>false]);
} catch (RuntimeException $e) { $fout=true; }
s152Assert($fout,'gesimuleerde tweede storewrite moet fout geven');
s152Assert(array_column(repoVergaderingenLees()['vergaderingen'],'id')===['verg_1'],'primaire vergaderingdelete moet rollbacken');
s152Assert((repoTakenLees()['taken'][0]['vergadering_id']??'')==='verg_1','taakkoppeling moet na rollback intact zijn');
s152Assert(repoGroepenLees()['relaties']['commissie_a']['vergaderingen']===['verg_1'],'groepsrelatie moet na rollback intact zijn');

// Detector + conservatieve repair: alleen ondubbelzinnige dangling IDs verwijderen.
s152Seed(
    s152Taken([s152Taak('taak_geldig','verg_geldig'),s152Taak('taak_dangling','verg_weg')]),
    s152Verg([s152Vergadering('verg_geldig')]),
    s152Events([s152Event('event_geldig')]),
    s152Groepen(['commissie_a'=>[
        'taken'=>['taak_geldig','taak_weg'],
        'vergaderingen'=>['verg_geldig','verg_weg'],
        'evenementen'=>['event_geldig','event_weg'],
    ]])
);
$d=dataIntegriteitDetecteer();
s152Assert($d['aantallen']===['taak_vergaderingen'=>1,'groep_taken'=>1,'groep_vergaderingen'=>1,'groep_evenementen'=>1] && $d['totaal']===4,'detector moet alle vier dangling categorieën vinden');
$repair=dataIntegriteitHerstelDangling();
s152Assert($repair['voor']['totaal']===4 && $repair['na']['totaal']===0,'repair moet uitsluitend gedetecteerde dangling refs oplossen');
$t=repoTakenLees();$g=repoGroepenLees();
s152Assert(($t['taken'][0]['vergadering_id']??'')==='verg_geldig','geldige taak->vergadering relatie mag niet worden verwijderd');
s152Assert(($t['taken'][1]['vergadering_id']??'x')==='','dangling taak->vergadering relatie moet worden ontkoppeld');
s152Assert(($t['taken'][0]['commissie_id']??'')==='commissie_a' && ($t['taken'][1]['commissie_id']??'')==='commissie_a','repair mag commissiehistorie/provenance niet wijzigen');
s152Assert($g['relaties']['commissie_a']['taken']===['taak_geldig'],'geldige groep->taak relatie moet blijven');
s152Assert($g['relaties']['commissie_a']['vergaderingen']===['verg_geldig'],'geldige groep->vergadering relatie moet blijven');
s152Assert($g['relaties']['commissie_a']['evenementen']===['event_geldig'],'geldige groep->evenement relatie moet blijven');

// Repair rollback: task-cleanup schrijft eerst, falende latere groepenwrite moet beide terugzetten.
s152Seed(s152Taken([s152Taak('taak_dangling','verg_weg')]),s152Verg([]),s152Events([]),s152Groepen(['commissie_a'=>['taken'=>['taak_weg'],'vergaderingen'=>[],'evenementen'=>[]]]));
$fout=false;
try { dataIntegriteitHerstelDangling(['groepen'=>static fn(array $doc): bool=>false]); }
catch (RuntimeException $e) { $fout=true; }
s152Assert($fout,'falende latere repairwrite moet fout geven');
s152Assert((repoTakenLees()['taken'][0]['vergadering_id']??'')==='verg_weg','repair taskwrite moet rollbacken');
s152Assert(repoGroepenLees()['relaties']['commissie_a']['taken']===['taak_weg'],'falende groepenrepair mag originele relatie niet wijzigen');

s152Rmdir($root);
echo "security-152-delete-integrity: OK\n";
