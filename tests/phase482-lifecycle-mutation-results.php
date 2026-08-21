<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c482(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
$apply=(string)file_get_contents($root.'/bin/apply-vps-lifecycle.php');
c482(str_contains($apply,'function apply48Uid')&&str_contains($apply,'function apply48Gid')&&str_contains($apply,'function apply48Meta'),'lifecycle heeft centrale owner/group/mode-nacontrole');
c482(str_contains($apply,'Tijdelijke lifecyclewrite kon niet veilig worden gemetadateerd.')&&str_contains($apply,'Lifecyclewrite-rechten konden niet worden genormaliseerd: ')&&substr_count($apply,'apply48Meta($')>=6,'state/tombstonewrites controleren metadata vóór en na atomische plaatsing');
c482(str_contains($apply,'Lifecycle-lock kon niet root-only worden gemaakt.')&&str_contains($apply,'apply48Meta($lock,0600,false,0,0)'),'lifecycle-lock moet aantoonbaar root:root 0600 zijn');
c482(str_contains($apply,'Lifecycle auditmetadata kon niet worden genormaliseerd.')&&str_contains($apply,"apply48Meta(\$f,0640,false,0,'adm')"),'lifecycle auditlog kan niet met verkeerde metadata succesvol eindigen');
c482(str_contains($apply,'Tijdelijke FPM poolconfig kon niet veilig worden gemetadateerd.')&&str_contains($apply,'FPM poolconfig-rechten konden niet worden genormaliseerd.'),'FPM poolactivatie controleert tijdelijke en definitieve metadata');
c482(substr_count($apply,'apply48FileMeta(')>=4&&str_contains($apply,'apply48FileMeta($dbDump,0600)')&&str_contains($apply,'apply48FileMeta($fs,0600)')&&str_contains($apply,'apply48FileMeta($pkg,0600)'),'database-, filesystem- en pakketexport worden aantoonbaar root-only 0600 gemaakt');
c482(str_contains($apply,'function apply48RmStrict')&&str_contains($apply,'function apply48RmBestEffort'),'kritieke verwijdering en tijdelijke cleanup hebben expliciet verschillende foutsemantiek');
c482(str_contains($apply,'finally{apply48RmBestEffort($stage);}')&&str_contains($apply,'apply48RmStrict($deleteRoot)'),'alleen tijdelijke exportstage gebruikt best-effort; tenantdata gebruikt strict delete');
c482(str_contains($apply,"apply48Unlink(\$f,'Tenant monitoringartifact')")&&str_contains($apply,"apply48Unlink(\$f,'Apache tenantartifact')"),'purge controleert verwijdering van monitoring- en Apache-artifacts');
c482(str_contains($apply,"[\$dc,,\$de]=apply48Run(['systemctl','daemon-reload'])")&&str_contains($apply,'systemd daemon-reload faalde tijdens tenantpurge'),'purge controleert systemd daemon-reload exitcode');
c482(str_contains($apply,"apply48Unlink((string)\$pr['filesystem']['state_file'],'Lifecycle-state')")&&str_contains($apply,"apply48Unlink(\$snap,'Lifecycle plansnapshot')"),'recover-purge mag state/snapshot cleanup niet stil negeren');
c482(str_contains($apply,"apply48Unlink((string)\$p['filesystem']['state_file'],'Lifecycle-state')")&&str_contains($apply,"apply48Unlink((string)\$p['filesystem']['plan_snapshot_file'],'Lifecycle plansnapshot')"),'normale purge bewijst state- en plansnapshotverwijdering');
c482(str_contains($apply,"(int)\$s['gid']!==0")&&str_contains($apply,'Recovery plansnapshot'),'recovery metadata vereist naast root owner ook root group');
c482(!str_contains($apply,'function apply48Rm(string')&&!str_contains($apply,'finally{apply48Rm($stage);}')&&!str_contains($apply,")]as\$f){if(is_link(\$f))throw new RuntimeException('Onverwachte symlink in tenant servicebestand: '.\$f);if(is_file(\$f))@unlink(\$f);}"),'oude generieke silent recursive delete en silent purge-artifactcleanup zijn verwijderd');
$workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');c482(str_contains($workflow,'phase482-lifecycle-mutation-results.php'),'fase 4.8.2 lifecycle mutation-resulttest draait in CI');
echo"Phase 4.8.2 lifecycle mutation results: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
