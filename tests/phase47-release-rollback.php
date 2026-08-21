<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c47(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function r47(array$a):array{$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($a,$d,$x,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,''];fclose($x[0]);$o=stream_get_contents($x[1]);fclose($x[1]);$e=stream_get_contents($x[2]);fclose($x[2]);return[proc_close($p),trim($o."\n".$e)];}
function rm47(string$d):void{if(!is_dir($d)||is_link($d))return;foreach(scandir($d)?:[]as$n){if($n==='.'||$n==='..')continue;$p=$d.'/'.$n;if(is_link($p)||is_file($p))@unlink($p);elseif(is_dir($p))rm47($p);}@rmdir($d);}
function cp47(string$s,string$d):void{@mkdir(dirname($d),0755,true);copy($s,$d);}
require_once $root.'/app/deployment/release-contract.php';
$tmp=sys_get_temp_dir().'/rc045-phase47-'.bin2hex(random_bytes(5));$src=$tmp.'/source';$plans=$tmp.'/plans';@mkdir($src,0755,true);@mkdir($plans,0755,true);
$required=['site-config.php','auth.php','healthz.php','bin/check-vps-health.php','bin/check-release-tenant.php','app/deployment/release-contract.php'];
try{
 foreach($required as$f)cp47($root.'/'.$f,$src.'/'.$f);file_put_contents($src.'/index.php','<?php echo "ok";');file_put_contents($src.'/beheer-users.json','ZEER-GEHEIM');file_put_contents($src.'/dev-build.json','{"secret":"niet meenemen"}');
 $commit=str_repeat('a',40);$platform=$tmp.'/platform';$tenants=$tmp.'/tenants';$out=$plans.'/release-plan.json';
 [$pc,$po]=r47([PHP_BINARY,$root.'/bin/prepare-vps-release.php','--source='.$src,'--commit='.$commit,'--platform-root='.$platform,'--tenant-base='.$tenants,'--output='.$out]);
 $plan=is_file($out)?json_decode((string)file_get_contents($out),true):null;c47($pc===0&&is_array($plan),'releaseplan wordt root-vrij geschreven');
 c47(($plan['phase']??'')==='4.7'&&($plan['commit']??'')===$commit,'plan bindt exact aan 40-hex commit');
 c47(($plan['paths']['release_dir']??'')===$platform.'/releases/'.$commit&&($plan['paths']['current']??'')===$platform.'/current','vaste releases/current paden zijn deterministisch');
 c47(($plan['release']['overwrite_existing_forbidden']??false)===true&&($plan['release']['automatic_prune_forbidden']??false)===true,'immutable release mag niet worden overschreven of automatisch gepruned');
 c47(($plan['activation']['failed_deploy_rolls_back_current']??false)===true&&($plan['activation']['rollback_uses_previous_validated_only']??false)===true,'activatiecontract vereist rollback en alleen vorige gevalideerde release');
 c47(($plan['security']['unexpected_secretlike_files_rejected']??false)===true,'releasecontract weigert onverwachte secretachtige bestanden');
 $raw=(string)file_get_contents($out);c47(!str_contains($raw,'ZEER-GEHEIM')&&!str_contains($raw,'niet meenemen'),'mutable/private brondata komt niet in releaseplan');
 $m=release47Manifest($src);c47(!isset($m['files']['beheer-users.json'])&&!isset($m['files']['dev-build.json']),'private en DEV-bestanden zijn uit release-inhoud uitgesloten');
 c47(isset($m['files']['healthz.php'])&&isset($m['files']['bin/check-release-tenant.php']),'productie-health en kandidaattenantprobe zitten in release');
 [$ic,$io]=r47([PHP_BINARY,$root.'/bin/prepare-vps-release.php','--source='.$src,'--commit='.$commit,'--platform-root='.$platform,'--tenant-base='.$tenants,'--output='.$out]);c47($ic===0&&str_contains($io,'ONGEWIJZIGD'),'identiek releaseplan is idempotent');
 [$cc,$co]=r47([PHP_BINARY,$root.'/bin/apply-vps-release.php','--plan='.$out,'--check']);c47($cc===0&&str_contains($co,'CHECK OK'),'root-vrije apply --check valideert bronmanifest opnieuw');
 file_put_contents($src.'/index.php','<?php echo "gewijzigd";');[$mc,$mo]=r47([PHP_BINARY,$root.'/bin/apply-vps-release.php','--plan='.$out,'--check']);c47($mc!==0&&str_contains($mo,'wijkt af'),'releaseplan vervalt na bronwijziging');file_put_contents($src.'/index.php','<?php echo "ok";');
 [$fc,$fo]=r47([PHP_BINARY,$root.'/bin/prepare-vps-release.php','--source='.$src,'--commit'=>'abc','--platform-root='.$platform,'--tenant-base='.$tenants,'--output='.$plans.'/bad.json']);c47($fc!==0&&!file_exists($plans.'/bad.json'),'ongeldige commit-id wordt fail-closed geweigerd');
 [$sc,$so]=r47([PHP_BINARY,$root.'/bin/prepare-vps-release.php','--source='.$src,'--commit='.$commit,'--platform-root='.$platform,'--tenant-base='.$tenants,'--output='.$src.'/release-plan.json']);c47($sc!==0&&!file_exists($src.'/release-plan.json'),'releaseplan binnen source tree wordt geweigerd');
 [$sec,$seco]=r47([PHP_BINARY,$root.'/bin/prepare-vps-release.php','--source='.$src,'--commit='.$commit,'--output='.$plans.'/x.json','--secret=nee']);c47($sec!==0&&str_contains($seco,'Secrets'),'release CLI weigert secretachtige argumenten');
 file_put_contents($src.'/.env','DB_PASSWORD=mag-nooit-mee');try{release47Manifest($src);c47(false,'onverwacht .env bestand blokkeert releasebron');}catch(Throwable$e){c47(str_contains($e->getMessage(),'secretachtig'),'onverwacht .env bestand blokkeert releasebron');}@unlink($src.'/.env');file_put_contents($src.'/.env.example','DB_PASSWORD=voorbeeld');try{$voorbeeld=release47Manifest($src);c47(isset($voorbeeld['files']['.env.example']),'.env.example blijft als niet-secret voorbeeld toegestaan');}catch(Throwable$e){c47(false,'.env.example blijft als niet-secret voorbeeld toegestaan');}@unlink($src.'/.env.example');
 if(function_exists('symlink')){$outside=$tmp.'/outside';file_put_contents($outside,'x');@symlink($outside,$src.'/evil-link');try{release47Manifest($src);c47(false,'symlink in releasebron wordt geweigerd');}catch(Throwable$e){c47(str_contains($e->getMessage(),'symlink'),'symlink in releasebron wordt geweigerd');}@unlink($src.'/evil-link');}else c47(true,'symlinktest overgeslagen');

 $relRoot=$tmp.'/bridge/releases';@mkdir($relRoot,0755,true);$a=str_repeat('1',40);$b=str_repeat('2',40);foreach([$a,$b]as$c){$d=$relRoot.'/'.$c;@mkdir($d,0755,true);file_put_contents($d.'/.verenigingsplatform-release.json',json_encode(['schema'=>1,'phase'=>'4.7-release','commit'=>$c,'manifest_sha256'=>str_repeat($c[0],64),'immutable'=>true]));}
 $current=$tmp.'/bridge/current';@symlink('releases/'.$b,$current);$entryB=['commit'=>$b,'path'=>$relRoot.'/'.$b,'manifest_sha256'=>str_repeat('2',64)];$entryA=['commit'=>$a,'path'=>$relRoot.'/'.$a,'manifest_sha256'=>str_repeat('1',64)];file_put_contents($tmp.'/bridge/release-state.json',json_encode(['schema'=>1,'phase'=>'4.7-state','active'=>$entryB,'previous'=>$entryA,'transition'=>null]));
 c47(runtime41BeheerdeReleaseCurrent($current,(string)realpath($current)),'runtime accepteert current-wissel met exact gebonden actieve release-state');
 file_put_contents($tmp.'/bridge/release-state.json',json_encode(['schema'=>1,'phase'=>'4.7-state','active'=>$entryA,'previous'=>null,'transition'=>['mode'=>'deploy','from'=>$entryA,'to'=>$entryB]]));c47(runtime41BeheerdeReleaseCurrent($current,(string)realpath($current)),'runtime accepteert exact transition-doel tijdens atomische deploy');
 $bad=$entryB;$bad['manifest_sha256']=str_repeat('f',64);file_put_contents($tmp.'/bridge/release-state.json',json_encode(['schema'=>1,'phase'=>'4.7-state','active'=>$bad,'previous'=>null,'transition'=>null]));c47(!runtime41BeheerdeReleaseCurrent($current,(string)realpath($current)),'verkeerde manifestbinding in release-state wordt geweigerd');

 $apply=(string)file_get_contents($root.'/bin/apply-vps-release.php');
 c47(str_contains($apply,'flock($lock,LOCK_EX|LOCK_NB)'),'releasehandelingen worden globaal geserialiseerd');
 c47(str_contains($apply,"'/.current.tmp.'")&&str_contains($apply,'rename($tmp,$current)')&&str_contains($apply,'symlink($rel,$tmp)'),'current-wissel gebruikt tijdelijke symlink plus atomische rename');
 c47(str_contains($apply,'apply47Health((string)$state[\'active\'][\'path\']')&&str_contains($apply,'apply47CandidateProbe'),'deploy vereist gezonde huidige release en read-only kandidaatprobe');
 c47(strpos($apply,'apply47CandidateProbe')<strpos($apply,'apply47ApacheTest')&&strpos($apply,'apply47ApacheTest')<strrpos($apply,'apply47Switch($plan,$candidate)'),'tenantprobe en Apache configtest gebeuren vóór kandidaatswitch');
 c47(str_contains($apply,"['/usr/bin/systemctl','reload'"),'PHP-FPM wordt na codewissel gecontroleerd herladen via absolute systemctl');
 c47(substr_count($apply,"'/usr/bin/php'.(string)\$t['php_version']")>=2,'syntax- en tenantprobe gebruiken exact de tenant PHP-versie');
 $boot=strpos($apply,"if(\$mode==='bootstrap')");$bootLint=strpos($apply,"apply47PhpSyntax((string)\$entry['path'],\$manifest)",$boot);$bootSwitch=strpos($apply,'apply47Switch($plan,$entry)',$boot);c47($boot!==false&&$bootLint!==false&&$bootSwitch!==false&&$bootLint<$bootSwitch,'eerste VPS-bootstrap lint volledige release vóór current-wissel');
 c47(str_contains($apply,'apply47Herstel')&&str_contains($apply,'deploy_failed_rolled_back'),'mislukte post-switch deploy heeft expliciet rollbackpad');
 c47(str_contains($apply,"\$state['previous']")&&str_contains($apply,'Geen vorige gevalideerde release beschikbaar'),'handmatige rollback gebruikt alleen previous uit state');
 c47(str_contains($apply,"apply47FpmReload(\$tenants)&&apply47Health((string)\$state['active']['path'],\$tenants,false)"),'mislukte handmatige rollback bewijst herstelde oorspronkelijke health');
 c47(str_contains($apply,"'recover'=>isset(\$opt['recover'])")&&str_contains($apply,'apply47Recover($plan)'),'onderbroken transition heeft expliciete recover-modus');
 c47(str_contains($apply,'apply47GeenTransition($state)')&&str_contains($apply,'Voer eerst --recover uit'),'nieuwe deploy of rollback overschrijft nooit een onafgeronde transition');
 c47(str_contains($apply,'candidate-reverted')&&str_contains($apply,"apply47FpmReload(\$tenants)&&apply47Health((string)\$from['path'],\$tenants,false)"),'recovery rolt half geschakelde kandidaat terug en bewijst oorspronkelijke health');
 c47(str_contains($apply,'Release transition is niet exact aan active/from/to gebonden'),'transition from/to wordt marker- en active-gebonden gevalideerd');
 c47(!str_contains($apply,'rm -rf')&&!str_contains($apply,'unlink($final)'),'bestaande immutable releases worden nooit automatisch verwijderd');
 c47(str_contains($apply,"\$mode==='bootstrap'")&&str_contains($apply,'tenantbasis is niet leeg'),'bootstrap is expliciet en alleen vóór tenantactivatie');
 $cand=(string)file_get_contents($root.'/bin/check-release-tenant.php');c47(str_contains($cand,"SELECT 1")&&!str_contains($cand,'INSERT ')&&!str_contains($cand,'UPDATE ')&&!str_contains($cand,'DELETE '),'kandidaattenantprobe is database read-only');
 $workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');c47(str_contains($workflow,'phase47-release-rollback.php'),'fase 4.7 test draait in CI');c47(str_contains($workflow,'/bin/apply-vps-release.php')&&str_contains($workflow,'/tests/phase47-release-rollback.php'),'4.7 tooling en test blijven via DEV HTTP-smoke afgeschermd');
}finally{rm47($tmp);}
echo"Phase 4.7 release rollback: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
