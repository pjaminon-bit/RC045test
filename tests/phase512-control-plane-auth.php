<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c512(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function reject512(callable$fn,string$needle=''):bool{try{$fn();return false;}catch(Throwable$e){return$needle===''||str_contains($e->getMessage(),$needle);}}
require_once $root.'/app/deployment/control-plane-auth-hardening.php';
$h12='$2y$12$'.str_repeat('A',53);$h12b='$2b$12$'.str_repeat('B',53);$h10='$2y$10$'.str_repeat('C',53);
$r=control512HtpasswdValidate("operator.one:{$h12}\noperator-two:{$h12b}\n",'operator.one');c512(count($r)===2&&control512BcryptCost()===12,'meerdere unieke bcrypt cost-12 operatorrecords worden geaccepteerd');
c512(reject512(fn()=>control512HtpasswdValidate("operator.one:{$h10}\n"),'cost 12'),'lager bcrypt cost wordt fail-closed geweigerd');
c512(reject512(fn()=>control512HtpasswdValidate("operator.one:{SHA}abc\n"),'bcrypt'),'legacy SHA/MD5/plaintext records worden geweigerd');
c512(reject512(fn()=>control512HtpasswdValidate("operator.one:{$h12}\noperator.one:{$h12b}\n"),'dubbele'),'dubbele operatornaam wordt geweigerd');
c512(reject512(fn()=>control512HtpasswdValidate("operator.one:{$h12}\n\noperator-two:{$h12b}\n"),'lege records'),'interne lege htpasswd-records worden geweigerd');
c512(reject512(fn()=>control512HtpasswdValidate("operator.one:{$h12}\n",'ontbreekt'),'ontbreekt'),'vereiste operator moet na write werkelijk aanwezig zijn');
c512(reject512(fn()=>control512HtpasswdValidate("xx:{$h12}\n"),'gebruikersnaam'),'te korte/ongeldige operatornaam wordt geweigerd');
$boot=(string)file_get_contents($root.'/bin/bootstrap-control-plane-operator.php');
c512(str_contains($boot,"require_once dirname(__DIR__) . '/app/deployment/control-plane-auth-hardening.php'")&&str_contains($boot,"\$cmd[]='-C'")&&str_contains($boot,'control512BcryptCost()'),'operatorbootstrap gebruikt expliciet bcrypt en vaste cost uit één helper');
$pre=strpos($boot,'control512HtpasswdValidate($voor)');$run=strpos($boot,'cboRun($cmd');c512($pre!==false&&$run!==false&&$pre<$run,'bestaand htpasswd-bestand wordt volledig gevalideerd vóór wijziging');
c512(str_contains($boot,'control512HtpasswdValidate($raw,$u)'),'na write wordt het volledige operatorbestand plus verwachte user opnieuw bewezen');
$apply=(string)file_get_contents($root.'/bin/apply-vps-control-plane.php');c512(str_contains($apply,'control512HtpasswdValidate($raw)')&&str_contains($apply,'runtime41SymlinkInPad($f)'),'control-plane root-apply valideert alle credentials en volledige authpad-ancestor');
$web=(string)file_get_contents($root.'/app/control-plane-web/index.php');$cache=strpos($web,"header('Cache-Control: no-store, max-age=0')");$post=strpos($web,"if (\$_SERVER['REQUEST_METHOD'] === 'POST')");c512($cache!==false&&$post!==false&&$cache<$post&&str_contains($web,"header('Pragma: no-cache')")&&str_contains($web,"header('X-Robots-Tag: noindex, nofollow, noarchive')"),'control-plane zet no-store/no-cache/noindex vóór requestverwerking');
$workflow=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');c512(str_contains($workflow,'phase512-control-plane-auth.php'),'fase 5.1.2 auth-hardeningtest draait in CI');
echo"Phase 5.1.2 control-plane auth: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
