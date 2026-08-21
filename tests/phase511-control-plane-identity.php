<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c511(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function reject511(callable$fn,string$needle=''):bool{try{$fn();return false;}catch(Throwable$e){return$needle===''||str_contains($e->getMessage(),$needle);}}
require_once $root.'/app/deployment/control-plane-identity.php';
$passwd=[['root','x','0','0','root','/root','/bin/bash'],['vst-control','x','998','998','','/nonexistent','/usr/sbin/nologin']];
$groups=[['root','x','0',''],['vst-control','x','998','']];
$id=control511UserIdentity('vst-control','vst-control',$passwd,$groups);
c511($id===['uid'=>998,'gid'=>998],'exacte unieke control-plane UID/GID wordt geaccepteerd');
$p=$passwd;$p[]=['collision','x','998','997','','/nonexistent','/usr/sbin/nologin'];c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$p,$groups),'UID'),'dubbele UID onder andere account wordt fail-closed geweigerd');
$g=$groups;$g[]=['collision-group','x','998',''];c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$passwd,$g),'GID'),'dubbele GID onder andere groepsnaam wordt fail-closed geweigerd');
$p=$passwd;$p[]=['shared-primary','x','997','998','','/nonexistent','/usr/sbin/nologin'];c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$p,$groups),'primary group'),'control-plane GID mag niet primary group van andere account zijn');
$g=[['root','x','0',''],['vst-control','x','998','ander']];c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$passwd,$g),'groepsleden'),'control-plane groep mag geen expliciete leden bevatten');
$p=$passwd;$p[1][5]='/tmp';c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$p,$groups),'home'),'afwijkende home wordt geweigerd');
$p=$passwd;$p[1][6]='/bin/bash';c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$p,$groups),'shell'),'afwijkende login shell wordt geweigerd');
$p=$passwd;$p[]=$passwd[1];c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$p,$groups),'exact één NSS-record'),'dubbel userrecord met dezelfde naam wordt geweigerd');
$g=$groups;$g[]=$groups[1];c511(reject511(fn()=>control511UserIdentity('vst-control','vst-control',$passwd,$g),'exact één NSS-record'),'dubbel grouprecord met dezelfde naam wordt geweigerd');
$apply=(string)file_get_contents($root.'/bin/apply-vps-control-plane.php');
preg_match('/function cpaAccount\(.*?\n}\nfunction cpaCert/s',$apply,$m);$account=$m[0]??'';
c511(str_contains($apply,"'/usr/bin/getent'")&&str_contains($apply,'function cpaGetentAlle'),'root-installer vereist absolute getent en leest volledige NSS databases');
c511(str_contains($account,'control511GroupIdentity')&&str_contains($account,'control511UserIdentity'),'root-installer gebruikt exact de pure collisionchecks vóór en na creatie');
c511(strpos($account,'control511GroupIdentity')<strpos($account,"'/usr/sbin/useradd'"),'GID-exclusiviteit wordt vóór eventuele usercreatie bewezen');
c511(str_contains($account,"'/usr/bin/id','-G'")&&str_contains($account,'supplementary groups'),'numeric group membership moet exact alleen de primary GID bevatten');
c511(str_contains($apply,"[$dc,,$de]=cpaRun(['/usr/bin/systemctl','daemon-reload'])")&&str_contains($apply,'systemd daemon-reload faalt'),'daemon-reload resultaat wordt niet langer genegeerd');
$workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');c511(str_contains($workflow,'phase511-control-plane-identity.php'),'fase 5.1.1 identity test draait in CI');
echo"Phase 5.1.1 control-plane identity: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
