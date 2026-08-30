<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c510(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
require_once $root.'/app/deployment/authenticated-e2e-fixture.php';
$tenant='test';$admin='vps-e2e-admin';$member='vps-e2e-member';$ids=e2e510Ids($tenant);
$hash1=password_hash('Fixture-test-password-2026-aaaaaaaa',PASSWORD_DEFAULT);$hash2=password_hash('Fixture-test-password-2026-bbbbbbbb',PASSWORD_DEFAULT);
if(!is_string($hash1)||!is_string($hash2)){fwrite(STDERR,"Password hash testsetup faalde.\n");exit(1);}
$seed=[['id'=>'usr_existing_12345678','gebruikersnaam'=>'bestaand','hash'=>$hash1,'aangemaakt'=>'2026-01-01T00:00:00Z','wachtwoord_gewijzigd'=>'2026-01-01T00:00:00Z','sessie_versie'=>3,'actief'=>true,'capabilities'=>['members.view'],'tabs'=>[]]];
$users=e2e510MergeAuthUsers($seed,$tenant,$admin,$member,$hash1);c510(count($users)===3,'fixture voegt exact twee accounts toe zonder bestaand account te verwijderen');
$byRole=[];foreach($users as$u)if(is_array($u)&&($u['e2e_fixture']??'')===e2e510Marker())$byRole[(string)($u['e2e_role']??'')]=$u;
c510(isset($byRole['admin'],$byRole['member']),'fixture markeert admin en member expliciet');
c510(($byRole['admin']['id']??'')===$ids['admin_user']&&($byRole['member']['id']??'')===$ids['member_user'],'auth-ID’s zijn deterministisch tenantgebonden');
c510(count((array)($byRole['admin']['capabilities']??[]))===count(e2e510AllCapabilities()),'E2E-admin krijgt alle actuele capabilities');
c510(($byRole['member']['capabilities']??null)===[],'E2E-lid krijgt geen beheer-capabilities');
$users2=e2e510MergeAuthUsers($users,$tenant,$admin,$member,$hash2);$roles2=[];foreach($users2 as$u)if(is_array($u)&&($u['e2e_fixture']??'')===e2e510Marker())$roles2[(string)$u['e2e_role']]=$u;
c510(count($users2)===3&&($roles2['admin']['sessie_versie']??0)===2&&($roles2['member']['sessie_versie']??0)===2,'opnieuw provisionen roteert fixture-sessiegeneratie zonder duplicaten');
$collision=false;try{e2e510MergeAuthUsers([['id'=>$ids['admin_user'],'gebruikersnaam'=>'echt-account']],$tenant,$admin,$member,$hash1);}catch(RuntimeException$e){$collision=true;}c510($collision,'gereserveerde fixture-ID kan geen bestaand niet-fixture account overschrijven');
$leden=e2e510MergeLeden(['updated'=>'','volgnummer'=>7,'leden'=>[['id'=>'lid_real','voornaam'=>'Bestaand']]],$tenant,$member);c510(count($leden['leden'])===2,'synthetisch lid wordt naast bestaande leden geplaatst');
$fixtureLid=null;foreach($leden['leden']as$l)if(is_array($l)&&($l['id']??'')===$ids['member'])$fixtureLid=$l;
c510(is_array($fixtureLid)&&($fixtureLid['user_id']??'')===$ids['member_user']&&($fixtureLid['beheer_account']??'')===$member,'synthetisch lid is aan het member-account gekoppeld');
c510(($fixtureLid['email']??'')==='e2e-testlid@example.invalid','fixture gebruikt uitsluitend synthetisch .invalid e-mailadres');
$contrib=e2e510MergeContributies(['regels'=>[['lid_id'=>'lid_real','jaar'=>(int)gmdate('Y'),'status'=>'betaald']]],$tenant);c510(count($contrib['regels'])===2,'contributiefixture behoudt niet-E2E regels');
$part=false;foreach($contrib['regels']as$r)if(is_array($r)&&($r['lid_id']??'')===$ids['member']&&($r['status']??'')==='deels_betaald')$part=true;c510($part,'synthetisch lid krijgt deels betaalde contributieregel voor portalacceptatie');
$groepen=e2e510MergeGroepen(['groepen'=>[['id'=>'commissie_real','type'=>'commissie','naam'=>'Bestaand']],'rollen'=>[],'relaties'=>[]],$tenant);$groupOk=false;foreach($groepen['groepen']as$g)if(is_array($g)&&($g['id']??'')===$ids['group']&&($g['naam']??'')==='E2E Testcommissie')$groupOk=true;c510($groupOk,'gekoppelde E2E Testcommissie wordt deterministisch toegevoegd');
$verg=e2e510MergeVergaderingen(['vergaderingen'=>[]],$tenant);$v=$verg['vergaderingen'][0]??[];c510(($v['soort']??'')==='leden'&&($v['notulen_status']??'')==='definitief'&&str_contains((string)($v['notulen']??''),'E2E definitieve notulen'),'ledenvergadering bevat zichtbare definitieve E2E-notulen');
$taken=e2e510MergeTaken(['taken'=>[]],$tenant);$t=$taken['taken'][0]??[];c510(($t['toegewezen_aan']??'')===$ids['member']&&($t['omschrijving']??'')==='E2E taak voor testlid','E2E-taak is aan het synthetische lid toegewezen');
$cli=(string)file_get_contents($root.'/bin/provision-vps-authenticated-e2e.php');
c510(str_contains($cli,"'password-stdin'")&&str_contains($cli,'stream_get_contents(STDIN)')&&!str_contains($cli,"'password:'"),'provisioner accepteert wachtwoord uitsluitend via stdin');
c510(str_contains($cli,"private_driver']??'')))!=='pdo'")&&str_contains($cli,'privateStoreTransactie('),'provisioner vereist PDO en schrijft domeindata transactioneel');
c510(str_contains($cli,'e2e510AuthHerstel(')&&str_contains($cli,'backups/auth'),'authstore heeft backup en herstelpad bij mislukte databasedeploy');
$workflow=(string)file_get_contents($root.'/.github/workflows/full-regression.yml');
c510(str_contains($workflow,'VPS_TEST_AUTH_E2E_ENABLED')&&str_contains($workflow,'VPS_TEST_ADMIN_USER')&&str_contains($workflow,'VPS_TEST_MEMBER_USER')&&str_contains($workflow,'VPS_TEST_E2E_PASSWORD'),'CI blijft expliciet gated en gebruikt dedicated VPS-testsecrets');
echo"Phase 5.10 VPS authenticated E2E fixture: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
