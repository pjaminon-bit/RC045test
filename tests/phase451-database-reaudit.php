<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function c451(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$apply = (string)file_get_contents($root . '/bin/apply-vps-database.php');

c451(str_contains($apply, 'function apply45RuntimeMoetInactiefZijn'), 'database-apply heeft expliciete tenant-runtime stilstandguard');
c451(str_contains($apply, "['pgrep', '-u', \$tenantUser]") && str_contains($apply, 'if ($code === 1) return;'), 'procesguard accepteert alleen aantoonbaar geen tenantprocessen');
c451(str_contains($apply, 'Stop eerst de tenant-PHP-FPM pool'), 'actieve tenantprocessen worden fail-closed geweigerd');

$posRuntime = strpos($apply, 'apply45RuntimeMoetInactiefZijn($appUser);');
$posNoLogin = strpos($apply, "apply45PgQuery('ALTER ROLE ' . \$appUser . ' NOLOGIN INHERIT");
$posHba = strpos($apply, 'apply45HbaInstalleer($plan);');
$posDatabase = strpos($apply, "apply45PgQuery('CREATE DATABASE '");
$posPriv = strpos($apply, "apply45PgQuery('REVOKE ALL ON DATABASE '");
$posLogin = strpos($apply, "apply45PgQuery('ALTER ROLE ' . \$appUser . ' LOGIN INHERIT");
$posPeer = strpos($apply, 'apply45PeerCheck($plan);');

c451($posRuntime !== false && $posNoLogin !== false && $posRuntime < $posNoLogin, 'runtime is stil vóór app-role beveiligingsmutaties');
c451($posNoLogin !== false && $posHba !== false && $posNoLogin < $posHba, 'app-role staat NOLOGIN vóór tenant-HBA installatie');
c451($posHba !== false && $posDatabase !== false && $posHba < $posDatabase, 'tenant-HBA allow/reject wordt geladen vóór databasecreatie');
c451($posDatabase !== false && $posPriv !== false && $posDatabase < $posPriv, 'database bestaat vóór privilege-normalisatie');
c451($posPriv !== false && $posLogin !== false && $posPriv < $posLogin, 'least privilege is bewezen vóór LOGIN wordt toegestaan');
c451($posLogin !== false && $posPeer !== false && $posLogin < $posPeer, 'peer/cross-database proef volgt op uiteindelijke LOGIN');

c451(str_contains($apply, "CREATE ROLE ' . \$appUser . ' NOLOGIN INHERIT"), 'nieuwe app-role wordt initieel als NOLOGIN aangemaakt');
c451(str_contains($apply, "'false|true', \$noLoginProps"), 'NOLOGIN/no-password toestand wordt vóór HBA aantoonbaar gevalideerd');
$posGebonden = strpos($apply, '$appRoleTenantGebonden = true;', strpos($apply, 'if ($appBestond)'));
$posPreProps = strpos($apply, '$preProps =', strpos($apply, 'if ($appBestond)'));
c451($posGebonden !== false && $posPreProps !== false && $posGebonden < $posPreProps, 'reeds gemarkeerde tenantrole wordt vóór driftcheck als fail-closed sluitbaar gemarkeerd');
c451(str_contains($apply, 'gevaarlijke privilege/password-drift; role wordt fail-closed gesloten'), 'gevaarlijke drift op bestaande tenantrole leidt tot expliciete sluiting');
c451(str_contains($apply, 'appRoleTenantGebonden') && str_contains($apply, "ALTER ROLE ' . \$appUser . ' NOLOGIN PASSWORD NULL"), 'foutpad sluit tenantgebonden app-role opnieuw fail-closed');
c451(str_contains($apply, 'Beschermende tenant-HBA blijft actief'), 'foutpad documenteert dat geladen HBA-reject niet wordt teruggedraaid');
c451(!str_contains($apply, 'apply45HbaRollback('), 'na rolecreatie bestaat geen rollbackpad dat tenant-HBA weer kan verwijderen');
$posDeps = strpos($apply, 'apply45Deps();');
$posFirstMutation = strpos($apply, 'apply45RuntimeMoetInactiefZijn($appUser);');
c451(str_contains($apply, "'pgrep' => ['/usr/bin/pgrep']") && $posDeps !== false && $posFirstMutation !== false && $posDeps < $posFirstMutation, 'root-apply behandelt pgrep via vaste absolute dependency vóór beveiligingsmutaties');

c451(str_contains($apply, "foreach (['/etc/verenigingsplatform', '/etc/verenigingsplatform/postgresql', \$includeDir] as \$veiligPad)"), 'HBA-installatie controleert ook bovenliggende platformconfigpaden');
c451(substr_count($apply, 'runtime41SymlinkInPad(') >= 5, 'root-HBA writes en bundle ownership gebruiken ancestor-symlinkcontrole');
c451(str_contains($apply, "runtime41BestaandPad(apply45PgQuery('SHOW hba_file')"), 'actief pg_hba.conf-pad wordt volledig symlink-vrij opgelost');

$workflow = (string)file_get_contents($root . '/.github/workflows/deploy-dev.yml');
$runAll = (string)file_get_contents($root . '/tests/run-all.sh');
$htaccess = (string)file_get_contents($root . '/.htaccess');
c451(
    str_contains($workflow, 'bash tests/run-all.sh')
    && str_contains($runAll, "find tests -maxdepth 1 -type f -name '*.php'")
    && str_contains($runAll, 'php "$test_file"'),
    'fase 4.5.1 heraudit valt automatisch onder de volledige CI-regressiesuite'
);
c451(
    str_contains($htaccess, 'RewriteRule ^(?:app|bin|tests|docs|\\.github|\\.git)(?:/|$) - [F,L,NC]'),
    'fase 4.5.1 test blijft via HTTP fail-closed afgeschermd'
);

echo "Phase 4.5.1 database reaudit: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
