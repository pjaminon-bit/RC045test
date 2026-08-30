<?php
$root = dirname(__DIR__); $ok = 0; $fout = 0;
function c511(bool $c, string $label): void { global $ok, $fout; if ($c) { $ok++; echo "OK: {$label}\n"; } else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); } }
require_once $root . '/app/deployment/authenticated-e2e-fixture.php';
require_once $root . '/app/deployment/authenticated-e2e-ephemeral.php';
$tenant = 'test'; $admin = 'vps-e2e-admin'; $member = 'vps-e2e-member'; $ids = e2e510Ids($tenant);
$hash = password_hash('Fixture-test-password-2026-ephemeral-aaaaaaaa', PASSWORD_DEFAULT);
if (!is_string($hash)) { fwrite(STDERR, "Password hash testsetup faalde.\n"); exit(1); }
$users = e2e510MergeAuthUsers([], $tenant, $admin, $member, $hash);
$leden = e2e510MergeLeden(['leden'=>[['id'=>'lid_real','voornaam'=>'Bestaand']]], $tenant, $member);
$contrib = e2e510MergeContributies(['regels'=>[['id'=>'c_real','lid_id'=>'lid_real','jaar'=>(int)gmdate('Y')]]], $tenant);
$groepen = e2e510MergeGroepen(['groepen'=>[['id'=>'g_real','naam'=>'Bestaand']],'rollen'=>[],'relaties'=>[]], $tenant);
$verg = e2e510MergeVergaderingen(['vergaderingen'=>[['id'=>'v_real','titel'=>'Bestaand']]], $tenant);
$taken = e2e510MergeTaken(['taken'=>[['id'=>'t_real','omschrijving'=>'Bestaand']]], $tenant);
[$leden,$contrib,$groepen,$verg,$taken] = e2e511MarkDocuments($leden,$contrib,$groepen,$verg,$taken,$tenant);
c511(e2e511CountAll($users,$leden,$contrib,$groepen,$verg,$taken,$tenant)===7, 'ephemeral fixture markeert exact twee auth- en vijf domeinrecords');
$allMarked = true;
foreach ([[$leden,'leden'],[$contrib,'regels'],[$groepen,'groepen'],[$verg,'vergaderingen'],[$taken,'taken']] as [$doc,$key]) {
    foreach ($doc[$key] ?? [] as $r) {
        if (is_array($r) && str_contains((string)($r['id'] ?? ''), '_e2e_') && !e2e511MarkerRecord($r,$tenant)) $allMarked = false;
    }
}
c511($allMarked, 'ieder ephemeral domeinrecord draagt expliciete fixture- én tenantmarker');
$cleanUsers = e2e511CleanupAuth($users,$tenant);
[$cleanLeden,$cleanContrib,$cleanGroepen,$cleanVerg,$cleanTaken] = e2e511CleanupDocuments($leden,$contrib,$groepen,$verg,$taken,$tenant);
c511(e2e511CountAll($cleanUsers,$cleanLeden,$cleanContrib,$cleanGroepen,$cleanVerg,$cleanTaken,$tenant)===0, 'cleanup verwijdert alle en uitsluitend gemarkeerde tenantfixturedata');
c511(count($cleanLeden['leden'])===1 && ($cleanLeden['leden'][0]['id']??'')==='lid_real', 'cleanup behoudt bestaand lid');
c511(count($cleanContrib['regels'])===1 && ($cleanContrib['regels'][0]['id']??'')==='c_real', 'cleanup behoudt bestaande contributie');
c511(count($cleanGroepen['groepen'])===1 && ($cleanGroepen['groepen'][0]['id']??'')==='g_real', 'cleanup behoudt bestaande groep');
$collision=false;
try { e2e511AssertReservedSlots(['leden'=>[['id'=>$ids['member'],'voornaam'=>'Echt']]], ['regels'=>[]], ['groepen'=>[]], ['vergaderingen'=>[]], ['taken'=>[]], $tenant); } catch (RuntimeException $e) { $collision=true; }
c511($collision, 'gereserveerde domein-ID botst fail-closed met niet-fixture data');
$collision=false;
try { e2e511CleanupAuth([['id'=>$ids['admin_user'],'gebruikersnaam'=>'echt']],$tenant); } catch (RuntimeException $e) { $collision=true; }
c511($collision, 'cleanup weigert gereserveerd niet-fixture authrecord');
$cli = (string)file_get_contents($root . '/bin/vps-authenticated-e2e-ephemeral.php');
c511(str_contains($cli,"'cleanup'") && str_contains($cli,'stream_get_contents(STDIN)') && !str_contains($cli,"'password:'"), 'ephemeral CLI ondersteunt cleanup en accepteert wachtwoord alleen via stdin');
c511(str_contains($cli,'privateStoreTransactie(') && str_contains($cli,'e2e511AuthHerstel('), 'ephemeral CLI houdt databasewrites transactioneel en herstelt auth bij fout');
$installer = (string)file_get_contents($root . '/bin/install-vps-authenticated-e2e-gateway.sh');
c511(str_contains($installer,"'e2e apply'") && str_contains($installer,"'e2e cleanup'") && str_contains($installer,"'e2e check'"), 'forced-command gateway kent alleen de drie vaste E2E-acties');
c511(str_contains($installer,'--expected-tenant=test') && str_contains($installer,'--expected-site=https://test.vps.holox.nl') && str_contains($installer,'--admin-user=vps-e2e-admin') && str_contains($installer,'--member-user=vps-e2e-member'), 'root-wrapper hardcodeert tenant, site en synthetische gebruikersnamen');
c511(str_contains($installer,'vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-e2e apply') && !str_contains($installer,'verenigingsplatform-github-e2e *'), 'sudoers gebruikt exacte E2E-command allowlist zonder wildcard');
c511(str_contains($installer,'runuser -u "$runtime_user"') && str_contains($installer,"r'vst[0-9a-f]{16}'"), 'fixturecode draait als gevalideerde tenant-runtimegebruiker en niet als root');
c511(str_contains($installer,"SSH_ORIGINAL_COMMAND='uname -a'") && str_contains($installer,'negative_rc') && str_contains($installer,'-eq 64'), 'installer bewijst met negatieve test dat algemene shellcommando’s geweigerd blijven');
echo "Phase 5.11 ephemeral VPS authenticated E2E gateway: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
