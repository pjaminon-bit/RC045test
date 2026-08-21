<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c514(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
require_once $root.'/app/deployment/process-runner.php';
$exec=(string)file_get_contents($root.'/bin/control-plane-executor.php');$runner=(string)file_get_contents($root.'/app/deployment/process-runner.php');
c514(str_contains($exec,'function cpeUid')&&str_contains($exec,'function cpeGid')&&str_contains($exec,'function cpeMeta'),'executor heeft centrale owner/group/mode-nacontrole');
c514(str_contains($exec,"if(!@chown(\$p,\$owner)||!@chgrp(\$p,\$group)||!@chmod(\$p,\$mode))throw")&&str_contains($exec,'cpeMeta($p,$mode,true,$owner,$group)'),'directorymutaties mogen niet stil falen en worden nagecontroleerd');
c514(str_contains($exec,"if(!@chown(\$tmp,0)||!@chgrp(\$tmp,\$group)||!@chmod(\$tmp,\$mode))")&&str_contains($exec,'cpeMeta($tmp,$mode,false,0,$group)'),'tijdelijke JSON-write wordt vóór rename exact gemetadateerd en bewezen');
c514(str_contains($exec,"if(!@chown(\$p,0)||!@chgrp(\$p,\$group)||!@chmod(\$p,\$mode))throw")&&str_contains($exec,'cpeMeta($p,$mode,false,0,$group)'),'geplaatste JSON-write wordt opnieuw exact gemetadateerd en bewezen');
c514(str_contains($exec,"if(!@chown(\$c['audit_file'],0)||!@chgrp(\$c['audit_file'],'adm')||!@chmod(\$c['audit_file'],0640))throw")&&str_contains($exec,"cpeMeta(\$c['audit_file'],0640,false,0,'adm')"),'auditlog kan niet succesvol doorgaan met foutieve metadata');
c514(str_contains($exec,'function cpeUnlink')&&str_contains($exec,'bleef bestaan na verwijdering'),'kritieke unlink controleert zowel returnwaarde als daadwerkelijk verdwijnen');
$lockOpen=strpos($exec,"\$lh=@fopen(\$c['executor_lock'],'c')");$lockMeta=$lockOpen===false?false:strpos($exec,"cpeMeta(\$c['executor_lock'],0600,false,0,0)",$lockOpen);$flock=$lockMeta===false?false:strpos($exec,'flock($lh,LOCK_EX|LOCK_NB)',$lockMeta);c514($lockOpen!==false&&$lockMeta!==false&&$flock!==false&&$lockOpen<$lockMeta&&$lockMeta<$flock,'executor-lock wordt root:root 0600 bewezen vóór exclusieve lock als geldige serverstate wordt gebruikt');
c514(str_contains($exec,"finally{cpeUnlink(\$dst,'Verwerkt control-plane queue-item');}"),'processing-item moet ook na actie/foutafhandeling aantoonbaar verwijderd worden');
c514(!str_contains($exec,"@chown(\$c['executor_lock'],0);@chgrp")&&!str_contains($exec,"finally{@unlink(\$dst);}"),'oude genegeerde lockmetadata en queue-unlink zijn verwijderd');
c514(str_contains($exec,'try{cpeResult($c,$r,\'failed\'')&&str_contains($exec,'catch(Throwable$ignored)'),'secundaire foutregistratie blijft best-effort zodat de oorspronkelijke lifecyclefout niet wordt gemaskeerd');
c514(str_contains($exec,"require_once dirname(__DIR__) . '/app/deployment/process-runner.php'")&&str_contains($exec,'process521Run($cmd, null, null, null, 3600)')&&!str_contains($exec,'proc_open('),'executor gebruikt uitsluitend de gedeelde deadlock-veilige subprocess-runner');
c514(str_contains($runner,'stream_select(')&&str_contains($runner,"['bypass_shell' => true]")&&str_contains($runner,'Privileged subprocess vereist een absoluut executablepad.'),'gedeelde runner multiplexed pipes, omzeilt de shell en weigert PATH-lookups');
$payloadCode='for($i=0;$i<128;$i++){fwrite(STDOUT,str_repeat("O",8192));fwrite(STDERR,str_repeat("E",8192));}';
[$pc,$po,$pe]=process521Run([PHP_BINARY,'-r',$payloadCode],null,null,null,10,4194304);c514($pc===0&&strlen($po)===1048576&&strlen($pe)===1048576,'runner draint >1 MiB stdout en stderr gelijktijdig zonder pipe-deadlock');
$stdin=str_repeat('xY7-',65536);[$ic,$io,$ie]=process521Run([PHP_BINARY,'-r','$s=stream_get_contents(STDIN);fwrite(STDOUT,hash("sha256",$s));fwrite(STDERR,(string)strlen($s));'],$stdin,null,null,10);c514($ic===0&&$io===hash('sha256',$stdin)&&$ie===(string)strlen($stdin),'runner schrijft grote stdin terwijl stdout/stderr gelijktijdig worden gedraind');
$absolute=false;try{process521Run(['php','-v']);}catch(Throwable$e){$absolute=str_contains($e->getMessage(),'absoluut executablepad');}c514($absolute,'runner weigert niet-absolute executablepaden fail-closed');

$production=[
 'runtime'=>$root.'/bin/apply-vps-runtime.php',
 'webserver'=>$root.'/bin/apply-vps-webserver.php',
 'tls'=>$root.'/bin/apply-vps-tls.php',
 'database'=>$root.'/bin/apply-vps-database.php',
 'monitoring'=>$root.'/bin/apply-vps-monitoring.php',
 'release'=>$root.'/bin/apply-vps-release.php',
 'lifecycle'=>$root.'/bin/apply-vps-lifecycle.php',
 'control-plane'=>$root.'/bin/apply-vps-control-plane.php',
 'first-vps'=>$root.'/bin/apply-first-vps-bootstrap.php',
 'executor'=>$root.'/bin/control-plane-executor.php',
 'operator'=>$root.'/bin/bootstrap-control-plane-operator.php',
];
foreach($production as$name=>$file){$src=(string)file_get_contents($file);c514(!str_contains($src,'proc_open('),$name.' bevat geen eigen directe proc_open meer');c514(str_contains($src,'process-runner.php')&&str_contains($src,'process521Run('),$name.' gebruikt de gedeelde subprocess-runner');}
$runtime=(string)file_get_contents($production['runtime']);c514(str_contains($runtime,"'getent' => '/usr/bin/getent'")&&str_contains($runtime,"'groupadd' => '/usr/sbin/groupadd'")&&str_contains($runtime,"'pgrep' => '/usr/bin/pgrep'")&&str_contains($runtime,'apply41Deps();'),'runtime zet PATH-tools vast op absolute binaries vóór mutaties');
$database=(string)file_get_contents($production['database']);c514(str_contains($database,"'runuser' => ['/usr/sbin/runuser','/usr/bin/runuser']")&&str_contains($database,"'psql' => ['/usr/bin/psql']")&&str_contains($database,"\$command[\$sep + 1] = apply45Binary")&&str_contains($database,'apply45Deps();'),'database pint runuser én child-psql en preflight dependencies');
$release=(string)file_get_contents($production['release']);$releaseLock=strpos($release,"\$lock=@fopen((string)\$plan['paths']['lock'],'c+')");$releaseDeps=strpos($release,'apply47Deps();');c514($releaseDeps!==false&&$releaseLock!==false&&$releaseDeps<$releaseLock&&str_contains($release,"'/usr/sbin/runuser','/usr/bin/env','/usr/bin/systemctl','/usr/sbin/apache2ctl'"),'release valideert vaste absolute executables vóór het release-lockbestand');
$tls=(string)file_get_contents($production['tls']);c514(str_contains($tls,"'certbot'=>['/usr/bin/certbot','/usr/local/bin/certbot']")&&str_contains($tls,"'openssl'=>['/usr/bin/openssl']")&&str_contains($tls,'apply44Deps($plan)'),'TLS lost certbot/openssl/systemctl fail-closed naar absolute binaries op');
$monitor=(string)file_get_contents($production['monitoring']);$monitorContract=(string)file_get_contents($root.'/app/deployment/monitoring-contract.php');c514(str_contains($monitor,"\$php='/usr/bin/php'.(string)\$p['runtime']['php_version']")&&str_contains($monitorContract,"'php_binary'=>'/usr/bin/php'.\$php")&&str_contains($monitorContract,"'ExecStart='.\$php.' "),'monitoring apply en systemd-service zijn aan exacte tenant-PHP gekoppeld');
$web=(string)file_get_contents($production['webserver']);c514(str_contains($web,'process521Run($cmd, null, null, null, 300)')&&str_contains($web,'Apache control-binary ontbreekt of is niet absoluut.'),'webserver gebruikt gedeelde runner en eist absoluut Apache executablepad');

$workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');c514(str_contains($workflow,'phase514-executor-mutation-results.php'),'fase 5.1.4 executor mutation-resulttest draait in CI');
echo"Phase 5.1.4 executor mutation results: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
