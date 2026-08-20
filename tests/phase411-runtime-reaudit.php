<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function check411(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");} }

$apply = (string)file_get_contents($root . '/bin/apply-vps-runtime.php');

check411(str_contains($apply, 'function apply41GetentAlle'), 'apply-laag kan volledige NSS passwd/group database fail-closed controleren');
check411(str_contains($apply, 'Tenant system group mag geen expliciete groepsleden bevatten'), 'tenantgroep weigert expliciete extra groepsleden');
check411(str_contains($apply, 'Tenant-GID wordt ook door een andere groepsnaam gebruikt'), 'dubbele GID onder andere groepsnaam wordt geweigerd');
check411(str_contains($apply, 'Tenant-GID is primary group van een andere account'), 'andere account met tenant-GID als primary group wordt geweigerd');
check411(str_contains($apply, 'Tenant-UID wordt ook door een andere account gebruikt'), 'dubbele UID onder andere accountnaam wordt geweigerd');
check411(str_contains($apply, "['pgrep', '-u', \$tenantUser]"), 'actieve processen worden per tenant-runtimeuser gecontroleerd');
check411(str_contains($apply, 'Stop eerst de tenant-PHP-FPM pool'), 'reapply faalt gesloten zolang tenantprocessen actief zijn');

$posGroep = strpos($apply, 'apply41ControleerGroepExclusief($tenantGroup, $gid, $tenantUser);');
$posUser = strpos($apply, '$uid = apply41EnsureUser($plan[\'os\'], $gid);');
$posUid = strpos($apply, 'apply41ControleerUidExclusief($tenantUser, $uid);');
$posIdle = strpos($apply, 'apply41RuntimeMoetInactiefZijn($tenantUser);');
$posMetadata = strpos($apply, 'apply41MetadataRechten($plan);');
check411($posGroep !== false && $posUser !== false && $posGroep < $posUser, 'GID-exclusiviteit wordt vóór usercreatie gecontroleerd');
check411($posUid !== false && $posIdle !== false && $posMetadata !== false && $posUid < $posIdle && $posIdle < $posMetadata, 'UID-exclusiviteit en processtilstand worden vóór filesystemmutaties afgedwongen');
check411(str_contains($apply, "if (\$code === 1) return;") && str_contains($apply, 'pgrep ontbreekt of gaf een onverwachte status'), 'procescontrole accepteert alleen expliciet geen-processen en faalt anders gesloten');

$workflow = (string)file_get_contents($root . '/.github/workflows/deploy-dev.yml');
check411(str_contains($workflow, 'php tests/phase411-runtime-reaudit.php'), '4.1.1 reaudittest zit in CI');
check411(str_contains($workflow, 'tests/phase411-runtime-reaudit.php'), '4.1.1 test blijft via DEV HTTP-smoke afgeschermd');

echo "Phase 4.1.1 runtime reaudit: $ok OK, $fout fout(en)\n";
exit($fout === 0 ? 0 : 1);
