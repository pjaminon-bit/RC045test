<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function s255(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}

require_once $root . '/app/leden/account-binding.php';

$mismatch = ['id'=>'lid_mismatch','user_id'=>'usr_oudevasteid','beheer_account'=>'hergebruik'];
s255(ledenAccountKoppelingStatus($mismatch, 'usr_nieuweidxx', 'hergebruik') === 'conflict', 'vaste afwijkende user_id maakt naamtreffer conflict');
s255(!ledenAccountKoppelingMatcht($mismatch, 'usr_nieuweidxx', 'hergebruik'), 'nieuw account met hergebruikte naam neemt oude vaste koppeling niet over');
s255(ledenAccountKoppelingMatcht($mismatch, 'usr_oudevasteid', 'andere-naam'), 'juiste vaste user_id blijft autoritatief ondanks stale legacy-naam');

$legacy = ['id'=>'lid_legacy','user_id'=>'','beheer_account'=>'legacy'];
s255(ledenAccountKoppelingStatus($legacy, 'usr_legacynew1', 'legacy') === 'legacy', 'legacy naamfallback blijft werken als user_id leeg is');
s255(ledenAccountKoppelingMatcht($legacy, 'usr_legacynew1', 'legacy'), 'geldige legacy naamkoppeling matcht');

$verdwenen = ['id'=>'lid_verdwenen','user_id'=>'usr_bestaatniet','beheer_account'=>'zelfde-naam'];
s255(!ledenAccountKoppelingMatcht($verdwenen, 'usr_nieuweid22', 'zelfde-naam'), 'verdwenen vaste user_id valt niet impliciet terug op dezelfde gebruikersnaam');

$leden = [
    ['id'=>'actief','user_id'=>'usr_account001','beheer_account'=>'account','gearchiveerd_op'=>''],
    ['id'=>'archief','user_id'=>'usr_account001','beheer_account'=>'account','gearchiveerd_op'=>'2026-01-01T00:00:00+01:00'],
    ['id'=>'legacy','user_id'=>'','beheer_account'=>'account','gearchiveerd_op'=>''],
    ['id'=>'conflict','user_id'=>'usr_andere000','beheer_account'=>'account','gearchiveerd_op'=>''],
    ['id'=>'ander','user_id'=>'usr_onverwant0','beheer_account'=>'ander','gearchiveerd_op'=>''],
];
$blokkades = ledenAccountVerwijderBlokkades($leden, 'usr_account001', 'account');
s255(count($blokkades) === 4, 'delete-guard omvat actieve, gearchiveerde, legacy en conflicterende verwijzingen');
s255(count(array_filter($blokkades, static fn($r)=>!empty($r['gearchiveerd']))) === 1, 'gearchiveerde koppeling wordt niet genegeerd bij accountdelete');
s255(ledenAccountVerwijderBlokkades($leden, 'usr_vrijaccount', 'vrij') === [], 'ongebonden account blijft verwijderbaar');

$gebruikers = [
    ['id'=>'usr_account001','gebruikersnaam'=>'account'],
    ['id'=>'usr_andere000','gebruikersnaam'=>'andere'],
];
$diagnose = ledenAccountIntegriteit([
    ['id'=>'ok','user_id'=>'usr_account001','beheer_account'=>'account'],
    ['id'=>'missing-id','user_id'=>'usr_missing000','beheer_account'=>''],
    ['id'=>'missing-legacy','user_id'=>'','beheer_account'=>'verdwenen'],
    ['id'=>'conflict','user_id'=>'usr_andere000','beheer_account'=>'account'],
], $gebruikers);
s255(($diagnose['aantallen']['ontbrekende_user_ids']??-1) === 1, 'integriteitscheck meldt ontbrekende vaste user_id');
s255(($diagnose['aantallen']['ontbrekende_legacy_accounts']??-1) === 1, 'integriteitscheck meldt dangling legacy-account');
s255(($diagnose['aantallen']['conflicten']??-1) === 1, 'integriteitscheck meldt conflicterende vaste ID en legacy-naam');
s255(($diagnose['totaal']??-1) === 3, 'integriteitscheck telt accountproblemen mee');

$service = (string)file_get_contents($root . '/app/leden/service.php');
$cap = (string)file_get_contents($root . '/app/auth-capabilities.php');
$auth = (string)file_get_contents($root . '/auth.php');
$users = (string)file_get_contents($root . '/beheer/gebruikers.php');
$integriteit = (string)file_get_contents($root . '/app/data-integriteit.php');
$integriteitUi = (string)file_get_contents($root . '/beheer/data-integriteit.php');

s255(str_contains($service, 'ledenAccountKoppelingMatcht($lid,$userId,$gebruikersnaam)'), 'ledenservice gebruikt centrale bindingsinvariant');
s255(str_contains($cap, 'function authRolVoorAccount') && str_contains($cap, 'ledenAccountKoppelingMatcht($l,$userId,$gebruikersnaam)'), 'capabilitylaag gebruikt dezelfde bindingsinvariant');
s255(str_contains($auth, 'authRolVoorAccount(authHuidigeGebruikerId(), $huidigeGebruiker)') && !str_contains($auth, '? ledenRolVanGebruiker($huidigeGebruiker)'), 'legacy authRechten routeert niet meer via naam-only ledenRolVanGebruiker');
$guardPos = strpos($users, 'ledenServiceAccountVerwijderBlokkades($userId,$naam)');
$splicePos = strpos($users, 'array_splice($gebruikers,$idx,1)', $guardPos === false ? 0 : $guardPos);
s255($guardPos !== false && $splicePos !== false && $guardPos < $splicePos, 'accountdelete controleert ledenrelaties vóór eerste gebruikersmutatie');
s255(str_contains($integriteit, 'ledenAccountIntegriteit') && str_contains($integriteitUi, 'dataIntegriteitDetecteer(laadGebruikers($usersBestand))'), 'bestaande read-only integriteitscontrole bevat accountdiagnose');

echo "Security #255/#256 account binding: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
