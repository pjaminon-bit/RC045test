<?php
$root=dirname(__DIR__);$ok=0;$fail=0;
function r146(bool $condition,string $label):void{global$ok,$fail;if($condition){$ok++;echo"OK: {$label}\n";}else{$fail++;fwrite(STDERR,"FOUT: {$label}\n");}}
function r146rm(string $path):void{if(is_link($path)||is_file($path)){@unlink($path);return;}if(!is_dir($path))return;foreach(scandir($path)?:[]as$name){if($name==='.'||$name==='..')continue;r146rm($path.'/'.$name);}@rmdir($path);}
function cpeWrite(string $path,array $data,int $mode=0640,string|int $group='runner'):void{file_put_contents($path,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n");}
require_once $root.'/app/deployment/control-plane-admin-executor.php';

$tmp=sys_get_temp_dir().'/rc045-roles146-'.bin2hex(random_bytes(5));$state=$tmp.'/state';@mkdir($state,0770,true);
try{
    $cfg=['snapshot_file'=>$state.'/snapshot.json','runtime_user'=>'runner','_config_file'=>$tmp.'/runtime.json'];
    file_put_contents($cfg['_config_file'],'{}');
    $hash=password_hash('regression-password-only',PASSWORD_BCRYPT,['cost'=>control512BcryptCost()]);
    if(!is_string($hash))throw new RuntimeException('bcrypt fixture kon niet worden gemaakt.');
    $auth=static function(array $users)use($tmp,$hash):void{$rows=[];foreach($users as$user)$rows[]=$user.':'.$hash;file_put_contents($tmp.'/operators.htpasswd',implode("\n",$rows)."\n");};
    $rolesFile=$state.'/operators.json';

    $auth(['alice','bob']);
    r146(control58ExecutorRole($cfg,'alice')==='viewer','root-executor behandelt ontbrekende rollenstate als viewer');
    r146(control58SyncRoles($cfg)===null&&!file_exists($rolesFile),'sync initialiseert ontbrekende rollenstate niet en kent geen owner toe');
    r146(str_contains((string)control58RolesWarning($cfg),'ontbreekt'),'ontbrekende rollenstate levert expliciete security-observabilitymelding');

    file_put_contents($rolesFile,'{"schema":');$corruptBefore=file_get_contents($rolesFile);
    r146(control58ExecutorRole($cfg,'alice')==='viewer','root-executor behandelt corrupte rollenstate als viewer');
    r146(control58SyncRoles($cfg)===null&&file_get_contents($rolesFile)===$corruptBefore,'sync overschrijft corrupte rollenstate niet stilzwijgend');
    r146(str_contains((string)control58RolesWarning($cfg),'ongeldig'),'corrupte rollenstate levert expliciete security-observabilitymelding');

    file_put_contents($rolesFile,json_encode(['schema'=>1,'phase'=>'5.8-operators','updated_at_utc'=>gmdate('c'),'roles'=>['alice'=>'owner','bob'=>'viewer']]));
    $auth(['alice','bob','charlie']);$synced=control58SyncRoles($cfg);
    r146(is_array($synced)&&($synced['roles']['alice']??'')==='owner'&&($synced['roles']['charlie']??'')==='viewer','nieuwe Basic-Auth operator wordt uitsluitend viewer');
    r146(count(array_filter($synced['roles']??[],static fn($role)=>$role==='owner'))===1,'sync behoudt exact bestaande owner en promoveert niemand extra');
    r146(control58ExecutorRole($cfg,'alice')==='owner'&&control58ExecutorRole($cfg,'charlie')==='viewer','geldige rollenstate wordt identiek door executor afgedwongen');

    $auth(['bob','charlie']);$ownerGone=control58SyncRoles($cfg);$stored=control58RolesDocument(json_decode((string)file_get_contents($rolesFile),true));
    r146(is_array($ownerGone)&&($ownerGone['roles']??null)===[]&&is_array($stored)&&$stored['roles']===[],'verdwenen enige owner wordt niet vervangen; stale privileges worden verwijderd');
    r146(control58ExecutorRole($cfg,'bob')==='viewer'&&control58ExecutorRole($cfg,'charlie')==='viewer','ownerloze recovery-state houdt alle geauthenticeerde operators read-only');
    r146(str_contains((string)control58RolesWarning($cfg),'geen actieve owner'),'ownerloze recovery-state is server-side zichtbaar');

    $initial=control58InitialRolesDocument(['alice','bob','charlie'],'bob',gmdate('c'));
    r146(($initial['roles']['bob']??'')==='owner'&&($initial['roles']['alice']??'')==='viewer'&&($initial['roles']['charlie']??'')==='viewer','root-bootstrapcontract kiest expliciet één owner en maakt alle anderen viewer');
    r146(count(array_filter($initial['roles'],static fn($role)=>$role==='owner'))===1,'bootstrapcontract kan nooit alle htpasswd-users owner maken');
    $threw=false;try{control58InitialRolesDocument(['alice','bob'],'mallory',gmdate('c'));}catch(Throwable$e){$threw=true;}
    r146($threw,'bootstrap weigert owner die niet in het Basic-Auth operatorbestand staat');

    $bootstrap=(string)file_get_contents($root.'/bin/bootstrap-control-plane-roles.php');$markerPos=strpos($bootstrap,"roles146Write(\$paths['roles_bootstrap_file']");$rolesPos=strpos($bootstrap,"roles146Write(\$paths['roles_file']");
    r146(str_contains($bootstrap,"posix_geteuid() !== 0")&&str_contains($bootstrap,"array_key_exists('recover', \$opt)"),'hersteltool is root-only en vereist expliciete recovermodus voor bestaande recovery-state');
    r146($markerPos!==false&&$rolesPos!==false&&$markerPos<$rolesPos,'bootstrap schrijft marker vóór privilegehoudende rollenstate en blijft bij onderbreking fail-closed');
    r146(!str_contains((string)file_get_contents($root.'/app/deployment/control-plane-admin-executor.php'),"return'owner'; // pre-5.8"),'oude executor ownerfallback is aantoonbaar verwijderd');
}finally{r146rm($tmp);}
echo"Security #146 control-plane roles: {$ok} OK, {$fail} fout(en)\n";exit($fail===0?0:1);