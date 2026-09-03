<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c157(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
require_once $root.'/app/deployment/root-release-boundary.php';
require_once $root.'/app/deployment/monitoring-contract.php';
require_once $root.'/app/deployment/privileged-ops-contract.php';

$sha=str_repeat('a',40);$release='/srv/verenigingsplatform/releases/'.$sha;
c157(process521ReleaseRootFromReal($release.'/bin/check-vps-health.php')===$release,'releaseparser herkent fysieke immutable commitrelease');
c157(process521ReleaseRootFromReal('/srv/verenigingsplatform/current/bin/check-vps-health.php')===null,'current-pointer is nooit een trusted fysieke release');
c157(process521ReleaseRootFromReal('/srv/verenigingsplatform/releases/not-a-commit/bin/x.php')===null,'niet-canonieke release wordt geweigerd');

$boundary=(string)file_get_contents($root.'/app/deployment/root-release-boundary.php');
$runner=(string)file_get_contents($root.'/app/deployment/process-runner.php');
$monitor=(string)file_get_contents($root.'/app/deployment/monitoring-contract.php');
$monitorApply=(string)file_get_contents($root.'/bin/apply-vps-monitoring.php');
$prepareControl=(string)file_get_contents($root.'/bin/prepare-vps-control-plane.php');
$admin=(string)file_get_contents($root.'/app/deployment/control-plane-admin-executor.php');
$launcher=(string)file_get_contents($root.'/ops/vps-test-deploy/verenigingsplatform-host-php');
$integrityWrapper=(string)file_get_contents($root.'/bin/control-plane-integrity-wrapper.php');
$installer=(string)file_get_contents($root.'/ops/vps-test-deploy/install-verenigingsplatform-host-engine');
$migration=(string)file_get_contents($root.'/ops/vps-test-deploy/migrate-verenigingsplatform-root-boundary');
$dropin=(string)file_get_contents($root.'/ops/vps-test-deploy/verenigingsplatform-control-plane-host-engine.conf');
$deploy=(string)file_get_contents($root.'/ops/vps-test-deploy/verenigingsplatform-github-deploy');
$releaseApply=(string)file_get_contents($root.'/bin/apply-vps-release.php');

c157(str_contains($runner,"require_once __DIR__ . '/root-release-boundary.php'")&&str_contains($runner,'process521RootPhpBoundary($cmd)'),'iedere gedeelde privileged subprocess passeert root-release boundary');
c157(str_contains($boundary,"'-u', 'nobody', '--'")&&str_contains($boundary,"=== '-l'"),'kandidaat-PHP lint dropt root naar nobody');
c157(str_contains($boundary,'function process521HostEngineRoot(): ?string')&&str_contains($boundary,'/usr/local/libexec/verenigingsplatform/host-engine/'),'boundary kent uitsluitend versioned host-engine buiten releaseboom');
c157(str_contains($boundary,'process521HostEngineReleaseScript')&&str_contains($boundary,"'apply-vps-lifecycle.php'")&&str_contains($boundary,"'provision-tenant.php'"),'legacy control-plane root-PHP wordt alleen via expliciete host-engine allowlist herschreven');
c157(str_contains($boundary,'process521HostEngineSystemdRun')&&str_contains($boundary,"basename(\$child) !== 'control-plane-scheduled-run.php'")&&str_contains($boundary,'systemd-run naar applicatierelease-PHP is geblokkeerd.'),'root systemd-run accepteert uitsluitend host-engine scheduled helper');
c157(str_contains($boundary,'Root-PHP naar applicatiereleasecode is vanuit host-engine geblokkeerd.'),'niet-allowlisted root→release PHP faalt gesloten');

$unit=monitoring46SystemdService([
    'tenant_key'=>'test',
    'runtime'=>['fpm_service'=>'php8.5-fpm.service'],
    'systemd'=>['host_launcher'=>monitoring46HostLauncher()],
    'bundle'=>['plan_file'=>'/srv/verenigingen/test/monitoring/monitoring-plan.json'],
    'logging'=>['app_dir'=>'/srv/verenigingen/test/private/monitoring'],
]);
c157(str_contains($unit,'ExecStart=/usr/local/sbin/verenigingsplatform-host-php health --monitoring-plan=/srv/verenigingen/test/monitoring/monitoring-plan.json --probe --write-status --alert'),'permanente healthservice start volledige root-owned host-health invocation');
c157(!str_contains($unit,'/srv/verenigingsplatform/current/')&&!str_contains($unit,'/srv/verenigingsplatform/releases/'),'gegenereerde healthservice bevat geen releasecodepad');
c157(str_contains($monitorApply,'process521HostEngineRoot()')&&str_contains($monitorApply,'--apply mag alleen vanuit de root-owned host-engine'),'monitoring apply weigert root-uitvoering buiten host-engine');

c157(str_starts_with($launcher,"#!/usr/bin/bash\n")&&str_contains($launcher,"export PATH='/usr/sbin:/usr/bin:/sbin:/bin'"),'permanente host-launcher pint Bash en systeem-PATH');
c157(str_contains($launcher,'sha256sum_bin')&&str_contains($launcher,'--check --quiet .host-engine-manifest.sha256'),'host-launcher verifieert volledige host-engine manifest vóór iedere uitvoer');
c157(str_contains($launcher,'exec /usr/bin/env -i')&&str_contains($launcher,'VERENIGINGSPLATFORM_HOST_ENGINE_ROOT='),'host-launcher start met minimale schone environment en expliciete enginebinding');
c157(str_contains($launcher,"health) script='bin/check-vps-health.php'")&&str_contains($launcher,"control-plane) script='bin/control-plane-integrity-wrapper.php'")&&str_contains($launcher,"release-apply) script='bin/apply-vps-release.php'"),'host-launcher heeft vaste privileged commandallowlist inclusief root-owned integriteitswrapper');
c157(str_contains($integrityWrapper,"$engineRoot . '/bin/control-plane-executor.php'")&&str_contains($integrityWrapper,'process521Run($cmd')&&!str_contains($integrityWrapper,'/srv/verenigingsplatform/current')&&!str_contains($integrityWrapper,'/srv/verenigingsplatform/releases/'),'integriteitswrapper start uitsluitend de executor uit dezelfde root-owned host-engine');
c157(str_contains($installer,'status --porcelain=v1 --untracked-files=all')&&str_contains($installer,'rev-parse HEAD'),'host-engine installer vereist schone exact gebonden checkout');
c157(str_starts_with($installer,"#!/usr/bin/bash\n")&&str_contains($installer,"export PATH='/usr/sbin:/usr/bin:/sbin:/bin'"),'host-engine installer pint Bash en systeem-PATH');
c157(str_contains($installer,'-m 0444')&&str_contains($installer,'"$chmod_bin" 0555')&&str_contains($installer,'.host-engine-manifest.sha256'),'host-engine installer maakt files read-only, dirs immutable-intent en schrijft manifest');
c157(!str_contains($installer,'php ')&&!str_contains($installer,'./bin/'),'host-engine installer voert geen PHP of bin-code uit de repositorycheckout uit');

c157(str_starts_with($migration,"#!/usr/bin/bash\n")&&str_contains($migration,"export PATH='/usr/sbin:/usr/bin:/sbin:/bin'"),'live migratie pint Bash en systeem-PATH');
c157(!str_contains($migration,'(?:')&&str_contains($migration,'=~ ^[a-z0-9]([a-z0-9-]*[a-z0-9])?$')&&str_contains($migration,'${#tenant}" -lt 3')&&str_contains($migration,'"$tenant" == *--*'),'live migratie gebruikt Bash-geldige canonieke tenantvalidatie');
c157(str_contains($migration,"'vp-control-schedule-*'")&&str_contains($migration,'Migratie geweigerd: er bestaan nog vp-control-schedule-* systemd-units.'),'live migratie weigert achterblijvende pre-migratie scheduled rootjobs');
c157(str_contains($migration,'monitoring-prepare')&&str_contains($migration,'monitoring-apply')&&str_contains($migration,'lifecycle-prepare'),'live migratie regenereert monitoring en afhankelijke lifecyclecontracten');
c157(str_contains($migration,'--probe --write-status --alert')&&str_contains($migration,'probe_started_epoch')&&str_contains($migration,'$checked<$min'),'live migratie vereist volledige host-health en een vers statusbewijs');
c157(str_contains($migration,'ROOT BOUNDARY MIGRATION OK')&&str_contains($migration,'root-boundary-migration'),'live migratie bewaart root-only herstelbewijs en geeft expliciet succes');
c157(str_contains($dropin,'ExecStart=/usr/local/sbin/verenigingsplatform-host-php control-plane --config=/etc/verenigingsplatform/control-plane/runtime.json'),'control-plane override start alleen host-launcher');
c157(str_contains($prepareControl,'process521HostEngineRoot()')&&str_contains($prepareControl,'Control-plane app-root moet exact de actief geïnstalleerde root-owned host-engine zijn.'),'nieuwe control-plane bundles kunnen geen release-app-root meer accepteren');
c157(str_contains($admin,'$script=$c[\'app_root\'].\'/bin/control-plane-scheduled-run.php\'')&&str_contains($boundary,"'control-plane-scheduled-run.php'"),'legacy schedulepad wordt door host-boundary expliciet afgevangen');

c157(str_starts_with($deploy,"#!/usr/bin/bash\n")&&str_contains($deploy,"export PATH='/usr/sbin:/usr/bin:/sbin:/bin'"),'permanente deploywrapper pint Bash en systeem-PATH');
c157(str_contains($deploy,"host_launcher='/usr/local/sbin/verenigingsplatform-host-php'")&&str_contains($deploy,'"$host_launcher" release-prepare')&&str_contains($deploy,'"$host_launcher" release-apply --plan="$plan" --deploy'),'deploy gebruikt host-engine voor prepare en release-activatie');
c157(str_contains($deploy,'"$host_launcher" control-plane --config="$control_plane_config" --refresh-only'),'post-deploy control-plane refresh loopt via host-engine');
c157(!str_contains($deploy,'trusted_prepare=')&&!str_contains($deploy,'trusted_apply=')&&!str_contains($deploy,'trusted_control_executor='),'deploywrapper voert geen trusted/current release-PHP meer rechtstreeks als root uit');
c157(str_contains($deploy,'Healthservice is nog niet naar host-tooling gemigreerd')&&str_contains($deploy,'Effectieve control-plane service is nog niet naar host-tooling gemigreerd'),'deploy faalt gesloten zolang permanente rootentrypoints niet zijn gemigreerd');

c157(str_contains($releaseApply,"'/usr/sbin/runuser'")&&str_contains($releaseApply,"'-u'")&&str_contains($releaseApply,'$t[\'user\']')&&str_contains($releaseApply,'apply47CandidateProbe'),'candidate runtimeprobe blijft onder tenantidentity');
c157(str_contains($releaseApply,'apply47PhpSyntax')&&str_contains($boundary,"=== '-l'"),'candidate syntaxcheck valt onder unprivileged lintboundary');

$contract=privilegedOpsContract();$defs=[];foreach($contract['tools']as$d)$defs[$d['id']]=$d;
foreach([
    'github-deploy'=>'ops/vps-test-deploy/verenigingsplatform-github-deploy',
    'host-php'=>'ops/vps-test-deploy/verenigingsplatform-host-php',
]as$id=>$path){
    $expected=(string)($defs[$id]['expected_sha256']??'');$actual=hash_file('sha256',$root.'/'.$path);
    c157(is_string($actual)&&hash_equals($expected,$actual),"privileged driftcontract hash is byte-exact voor {$id}");
    c157(($defs[$id]['expected_uid']??null)===0&&($defs[$id]['expected_gid']??null)===0&&($defs[$id]['expected_mode']??null)===0755,"privileged driftcontract eist root:root/0755 voor {$id}");
}

foreach([
    'ops/vps-test-deploy/verenigingsplatform-host-php',
    'ops/vps-test-deploy/install-verenigingsplatform-host-engine',
    'ops/vps-test-deploy/migrate-verenigingsplatform-root-boundary',
    'ops/vps-test-deploy/verenigingsplatform-github-deploy',
]as$script){
    $cmd='/usr/bin/bash -n '.escapeshellarg($root.'/'.$script).' 2>&1';exec($cmd,$out,$code);c157($code===0,'bash syntax geldig: '.$script);
}

echo"Issue #157 host-engine root boundary: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
