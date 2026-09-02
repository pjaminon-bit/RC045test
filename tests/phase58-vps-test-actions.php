<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c58(bool $c,string $label):void{global$ok,$fout;if($c){$ok++;echo"OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");}}
$validate=(string)file_get_contents($root.'/.github/workflows/deploy-dev.yml');
$full=(string)file_get_contents($root.'/.github/workflows/full-regression.yml');
$deploy=(string)file_get_contents($root.'/.github/workflows/deploy-vps-test.yml');
$security=(string)file_get_contents($root.'/tests/live-dev-security.sh');
$browser=(string)file_get_contents($root.'/tests/live-dev-browser.spec.js');
$auth=(string)file_get_contents($root.'/tests/live-dev-authenticated.spec.js');
$entry=(string)file_get_contents($root.'/ops/vps-test-deploy/verenigingsplatform-github-entry');
$wrapper=(string)file_get_contents($root.'/ops/vps-test-deploy/verenigingsplatform-github-deploy');
$host=(string)file_get_contents($root.'/ops/vps-test-deploy/verenigingsplatform-host-php');
$installer=(string)file_get_contents($root.'/ops/vps-test-deploy/install-verenigingsplatform-host-engine');
$migration=(string)file_get_contents($root.'/ops/vps-test-deploy/migrate-verenigingsplatform-root-boundary');
$docs=(string)file_get_contents($root.'/docs/GITHUB-VPS-TEST-DEPLOYMENT.md');

foreach(['rc045.nl/dev','FTP_USERNAME','FTP_SERVER','FTP_PASSWORD','SFTP-Deploy-Action','Upload testsite via SFTP'] as $oud){
    c58(!str_contains($validate,$oud)&&!str_contains($full,$oud),"oude DEV/SFTP-koppeling verwijderd: {$oud}");
}
c58(str_contains($validate,'name: Validate RC045test'),'hoofdworkflow heet alleen nog validatie');
c58(str_contains($validate,'bash tests/run-all.sh'),'hoofdvalidatie gebruikt de complete dynamische bronregressiesuite');
c58(!str_contains($validate,'phase57-control-plane-provisioning.php')&&!str_contains($validate,'phase58-vps-test-actions.php'),'hoofdvalidatie onderhoudt geen handmatige lijst van individuele regressietests');
c58(!str_contains($validate,'dev-build.json <<EOF')&&!str_contains($validate,'remote_path:'),'hoofdworkflow schrijft of uploadt geen legacy DEV-build meer');

c58(str_contains($full,"  push:\n    branches:\n      - main"),'full regression draait op iedere push naar main vóór deployment');
c58(str_contains($full,'bash tests/run-all.sh'),'full regression gebruikt de volledige dynamische testset');
c58(str_contains($full,'LIVE_DEV_BASE_URL: https://test.vps.holox.nl')&&str_contains($full,'PLAYWRIGHT_TEST_BASE_URL: https://test.vps.holox.nl'),'full regression gebruikt VPS-test als live basis');
c58(str_contains($full,'Deploy RC045test to VPS test'),'post-deploy full regression volgt de VPS-deployworkflow voor live checks');
c58(!str_contains($full,'sshpass')&&!str_contains($full,'sftp_cmd')&&!str_contains($full,'authenticated-e2e-fixtures.php'),'authenticated E2E muteert geen losse productie-/tenantbestanden via SFTP');

c58(str_contains($deploy,"      - Full regression acceptance"),'privileged deploy wacht op Full regression acceptance');
c58(!str_contains($deploy,"      - Validate RC045test"),'privileged deploy wacht niet langer alleen op de beperkte Validate-workflow');
c58(str_contains($deploy,"github.event.workflow_run.event == 'push'")&&str_contains($deploy,"github.event.workflow_run.head_branch == 'main'")&&str_contains($deploy,"github.event.workflow_run.conclusion == 'success'"),'automatische deploy accepteert uitsluitend succesvolle main-push full regression');
c58(
    str_contains($deploy,"  e2e-fixture-setup:\n")
    && str_contains($deploy,"  live-authenticated:\n")
    && str_contains($deploy,"  e2e-fixture-cleanup:\n")
    && str_contains($deploy,'needs: e2e-fixture-setup')
    && str_contains($deploy,'E2E_ADMIN_USER: vps-e2e-admin')
    && str_contains($deploy,'E2E_MEMBER_USER: vps-e2e-member')
    && str_contains($deploy,'secrets.token_urlsafe(48)')
    && str_contains($deploy,"'e2e check'")
    && str_contains($deploy,"'e2e apply'")
    && str_contains($deploy,"'e2e cleanup'")
    && !str_contains($deploy,'VPS_TEST_AUTH_E2E_ENABLED')
    && !str_contains($full,'VPS_TEST_AUTH_E2E_ENABLED')
    && !str_contains($deploy,'VPS_TEST_E2E_PASSWORD')
    && !str_contains($full,'VPS_TEST_E2E_PASSWORD')
    && !str_contains($deploy,'VPS_TEST_ADMIN_USER')
    && !str_contains($deploy,'VPS_TEST_MEMBER_USER'),
    'authenticated VPS-E2E volgt deploy verplicht via gescheiden fixture-, browser- en cleanupjobs met per-run credentials'
);

c58(str_contains($security,'https://test.vps.holox.nl')&&!str_contains($security,'https://rc045.nl/dev'),'live security default is VPS-test');
c58(str_contains($browser,'https://test.vps.holox.nl')&&!str_contains($browser,'https://rc045.nl/dev'),'browseracceptatie default is VPS-test');
c58(str_contains($auth,'https://test.vps.holox.nl')&&!str_contains($auth,"domain.includes('rc045.nl')")&&str_contains($auth,'cookieHoortBijHost'),'authenticated browsertest gebruikt dynamische VPS-hostbinding');

c58(str_contains($deploy,'name: Deploy RC045test to VPS test')&&str_contains($deploy,"vars.VPS_TEST_DEPLOY_ENABLED == 'true'"),'privileged VPS-deploy is apart en standaard expliciet gated');
c58(str_contains($deploy,'environment: vps-test')&&str_contains($deploy,'secrets.VPS_TEST_DEPLOY_KEY')&&str_contains($deploy,'secrets.VPS_TEST_SSH_KNOWN_HOSTS'),'deploy gebruikt aparte environment-key en gepinde hosttrust');
c58(str_contains($deploy,'StrictHostKeyChecking=yes')&&!str_contains($deploy,'ssh-keyscan'),'workflow gebruikt geen trust-on-first-use voor SSH');
c58(str_contains($deploy,'deploy $DEPLOY_SHA')&&str_contains($deploy,'DEPLOYED $DEPLOY_SHA'),'workflow vraagt alleen exacte commitdeploy en verifieert serverbevestiging');
c58(str_contains($deploy,'https://test.vps.holox.nl')&&str_contains($deploy,'healthz.php')&&str_contains($deploy,'204 nee'),'post-deploy smoke test bewijst VPS-testhealth');

c58(str_contains($deploy,'id-token: write'),'deployworkflow kan een GitHub OIDC-token voor workload identity aanvragen');
c58(str_contains($deploy,'tailscale/github-action@306e68a486fd2350f2bfc3b19fcd143891a4a2d8')&&str_contains($deploy,'version: 1.94.2'),'Tailscale GitHub Action en clientversie zijn deterministisch gepind');
c58(str_contains($deploy,'secrets.TS_OAUTH_CLIENT_ID')&&str_contains($deploy,'secrets.TS_AUDIENCE'),'Tailscale workload identity gebruikt client-ID en audience');
c58(!str_contains($deploy,'oauth-secret:')&&!str_contains($deploy,'authkey:'),'deployworkflow bewaart geen langdurig Tailscale OAuth-secret of authkey');
c58(str_contains($deploy,'tags: tag:github-rc045test'),'ephemeral GitHub-runner gebruikt een afzonderlijke least-privilege tag');
c58(str_contains($deploy,"VPS_TEST_TAILSCALE_HOST || '100.104.242.66'")&&str_contains($deploy,'VPS_TAILSCALE_HOST'),'SSH netwerkdoel is het private Tailscale-adres van platform');
c58(!str_contains($deploy,'VPS_SSH_HOST:'),'publieke VPS-hostnaam is niet langer het SSH-netwerkdoel');
c58(str_contains($deploy,'HostKeyAlias="$VPS_SSH_HOST_ALIAS"')&&str_contains($deploy,'VPS_SSH_HOST_ALIAS: vps.holox.nl'),'private Tailscale-route behoudt de out-of-band geverifieerde SSH-hostkey via HostKeyAlias');
c58(str_contains($deploy,"github.event_name == 'workflow_dispatch' && github.ref == 'refs/heads/main'"),'handmatige privileged deploy kan uitsluitend vanaf main');
c58(str_contains($deploy,'ping: ${{ vars.VPS_TEST_TAILSCALE_HOST')&&str_contains($deploy,'100.104.242.66'),'Tailscale peerconnectiviteit wordt vóór SSH geverifieerd');

c58(str_contains($entry,'SSH_ORIGINAL_COMMAND')&&str_contains($entry,'^deploy[[:space:]]+([0-9a-f]{40})$'),'forced SSH entrypoint accepteert uitsluitend deploy + 40-hex commit');
c58(str_contains($entry,'/usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-deploy')&&str_contains($entry,'$commit'),'entrypoint kan uitsluitend vaste root-wrapper met gevalideerde commit starten');
c58(str_contains($wrapper,"repo='https://github.com/pjaminon-bit/RC045test.git'")&&str_contains($wrapper,'rev-parse HEAD'),'root-wrapper bindt staging aan vaste repo en exacte Git-commit');
c58(str_contains($wrapper,"host_launcher='/usr/local/sbin/verenigingsplatform-host-php'")&&str_contains($wrapper,'"$host_launcher" release-prepare')&&str_contains($wrapper,'"$host_launcher" release-apply --plan="$plan" --check')&&str_contains($wrapper,'"$host_launcher" release-apply --plan="$plan" --deploy'),'root-wrapper gebruikt uitsluitend de root-owned host-engine voor privileged releaseprepare/apply');
c58(str_contains($wrapper,'Healthservice is nog niet naar host-tooling gemigreerd')&&str_contains($wrapper,'Effectieve control-plane service is nog niet naar host-tooling gemigreerd'),'root-wrapper weigert deploy zolang permanente rootentrypoints nog releasecode volgen');
c58(str_contains($wrapper,'"$host_launcher" control-plane --config="$control_plane_config" --refresh-only')&&!str_contains($wrapper,'trusted_control_executor=')&&!str_contains($wrapper,'trusted_apply=')&&!str_contains($wrapper,'trusted_prepare='),'post-deploy refresh en deploy bevatten geen directe root-PHP uit actieve/kandidaatrelease');
c58(str_contains($wrapper,'release-state.json')&&str_contains($wrapper,'DEPLOYED $commit'),'root-wrapper bewijst actieve release-state vóór succesmelding');

c58(str_contains($host,'/etc/verenigingsplatform/host-engine.path')&&str_contains($host,'--check --quiet .host-engine-manifest.sha256'),'host-launcher bindt iedere privileged aanroep aan een byte-gecontroleerde versioned engine');
c58(str_contains($host,"health) script='bin/check-vps-health.php'")&&str_contains($host,"release-apply) script='bin/apply-vps-release.php'")&&str_contains($host,"control-plane) script='bin/control-plane-executor.php'"),'host-launcher heeft een vaste expliciete commandallowlist');
c58(str_contains($installer,'status --porcelain=v1 --untracked-files=all')&&str_contains($installer,'ls-files -z -- app bin')&&str_contains($installer,'.host-engine-manifest.sha256'),'host-engine installer kopieert uitsluitend een schone exacte checkout naar een read-only manifestgebonden engine');
c58(str_contains($migration,'ROOT BOUNDARY MIGRATION OK')&&str_contains($migration,'monitoring-prepare')&&str_contains($migration,'control-plane --config='),'first-hop migratie zet monitoring en control-plane gecontroleerd op host-tooling');

c58(str_contains($docs,'RC045test` blijft de bronrepository')&&str_contains($docs,'geen algemene SSH-shell'),'documentatie borgt repobehoud en restricted deployment');
c58(str_contains($docs,'Tailscale')&&str_contains($docs,'tag:github-rc045test')&&str_contains($docs,'TS_OAUTH_CLIENT_ID')&&str_contains($docs,'TS_AUDIENCE'),'documentatie borgt private Tailscale/OIDC deployment');
c58(str_contains($docs,'geen publieke SSH-poort')||str_contains($docs,'publieke SSH-poort hoeft niet open'),'documentatie borgt dat SSH op internet gesloten kan blijven');

echo"Phase 5.8 VPS test Actions: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
