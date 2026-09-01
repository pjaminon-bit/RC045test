<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c157(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
require_once $root.'/app/deployment/root-release-boundary.php';
require_once $root.'/app/deployment/monitoring-contract.php';

$sha=str_repeat('a',40);$release='/srv/verenigingsplatform/releases/'.$sha;
c157(process521ReleaseRootFromReal($release.'/bin/check-vps-health.php')===$release,'releaseparser herkent uitsluitend fysieke immutable commitrelease');
c157(process521ReleaseRootFromReal('/srv/verenigingsplatform/current/bin/check-vps-health.php')===null,'logische current-pointer is geen trusted fysieke release');
c157(process521ReleaseRootFromReal('/srv/verenigingsplatform/releases/not-a-commit/bin/x.php')===null,'niet-canonieke release wordt geweigerd');

$boundary=(string)file_get_contents($root.'/app/deployment/root-release-boundary.php');
$runner=(string)file_get_contents($root.'/app/deployment/process-runner.php');
$monitor=(string)file_get_contents($root.'/app/deployment/monitoring-contract.php');
$prepare=(string)file_get_contents($root.'/bin/prepare-vps-control-plane.php');
$deploy=(string)file_get_contents($root.'/ops/vps-test-deploy/verenigingsplatform-github-deploy');
$releaseApply=(string)file_get_contents($root.'/bin/apply-vps-release.php');

c157(str_contains($runner,"require_once __DIR__ . '/root-release-boundary.php'")&&str_contains($runner,'process521RootPhpBoundary($cmd)'),'iedere gedeelde privileged subprocess passeert root-release boundary');
c157(str_contains($boundary,"'-u', 'nobody', '--'")&&str_contains($boundary,"($cmd[1] ?? null) === '-l'"),'kandidaat PHP-lint dropt root naar nobody');
c157(str_contains($boundary,"basename($child) === 'check-vps-health.php'")&&str_contains($boundary,"$trustedRoot . '/bin/check-vps-health.php'"),'cross-release health wordt teruggebonden aan trusted callerrelease');
c157(str_contains($boundary,'Root-PHP naar een andere of kandidaat-release is geblokkeerd.'),'overige cross-release PHP faalt gesloten');

c157(str_contains($monitor,'function monitoring46TrustedRoot():string')&&str_contains($monitor,'function monitoring46SystemdService(array $p):string{$root=monitoring46TrustedRoot();'),'permanente healthservice gebruikt fysieke trusted bronroot');
c157(!str_contains($monitor,"function monitoring46SystemdService(array $p):string{$root=runtime41PlanLeesEnValideer"),'healthservice volgt niet langer runtime shared_code/current');
c157(monitoring46TrustedRoot()===realpath($root),'monitoring trusted root resolveert in bronregressie fysiek naar huidige checkout');

c157(str_contains($prepare,'$appReal = realpath($appArg);')&&str_contains($prepare,'control51Plan(trim((string)$o[\'host\']), $appRoot'),'control-plane plan pint app_root fysiek vóór systemd/runtimeconfig generatie');
$trustedPos=strpos($deploy,'trusted_control_executor=');$switchPos=strpos($deploy,'"$php" "$trusted_apply" --plan="$plan" --deploy');$refreshPos=strpos($deploy,'"$php" "$trusted_control_executor" --config="$control_plane_config" --refresh-only');
c157($trustedPos!==false&&$switchPos!==false&&$refreshPos!==false&&$trustedPos<$switchPos&&$switchPos<$refreshPos,'deploywrapper bevriest trusted executor vóór current-switch en gebruikt die na switch');
c157(!str_contains($deploy,'$platform_root/current/bin/control-plane-executor.php'),'deploywrapper root-start nooit executor uit nieuw current');

c157(str_contains($releaseApply,"'/usr/sbin/runuser','-u',$t['user'],'--'")&&str_contains($releaseApply,'apply47CandidateProbe'),'candidate runtimeprobe blijft onder tenantidentity');
c157(str_contains($releaseApply,'apply47PhpSyntax')&&str_contains($boundary,"($cmd[1] ?? null) === '-l'"),'candidate syntaxcheck valt onder expliciete unprivileged lintboundary');

echo"Issue #157 root-release boundary: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
