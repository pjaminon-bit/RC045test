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
$docs=(string)file_get_contents($root.'/docs/GITHUB-VPS-TEST-DEPLOYMENT.md');

foreach(['rc045.nl/dev','FTP_USERNAME','FTP_SERVER','FTP_PASSWORD','SFTP-Deploy-Action','Upload testsite via SFTP'] as $oud){
    c58(!str_contains($validate,$oud)&&!str_contains($full,$oud),"oude DEV/SFTP-koppeling verwijderd: {$oud}");
}
c58(str_contains($validate,'name: Validate RC045test'),'hoofdworkflow heet alleen nog validatie');
c58(str_contains($validate,"basis='https://test.vps.holox.nl'")&&str_contains($validate,'wacht_op_status "$basis/healthz.php" 204'),'hoofdworkflow controleert de echte VPS-testtenant en 204-health');
c58(str_contains($validate,'phase57-control-plane-provisioning.php')&&str_contains($validate,'phase58-vps-test-actions.php'),'recente fase-5.7 en 5.8 regressies draaien in de hoofdvalidatie');
c58(!str_contains($validate,'dev-build.json <<EOF')&&!str_contains($validate,'remote_path:'),'hoofdworkflow schrijft of uploadt geen legacy DEV-build meer');

c58(str_contains($full,'LIVE_DEV_BASE_URL: https://test.vps.holox.nl')&&str_contains($full,'PLAYWRIGHT_TEST_BASE_URL: https://test.vps.holox.nl'),'full regression gebruikt VPS-test als live basis');
c58(str_contains($full,'Deploy RC045test to VPS test'),'full regression volgt de VPS-deployworkflow');
c58(!str_contains($full,'sshpass')&&!str_contains($full,'sftp_cmd')&&!str_contains($full,'authenticated-e2e-fixtures.php'),'authenticated E2E muteert geen losse productie-/tenantbestanden via SFTP');
c58(str_contains($full,"vars.VPS_TEST_AUTH_E2E_ENABLED == 'true'")&&str_contains($full,'secrets.VPS_TEST_ADMIN_USER')&&str_contains($full,'secrets.VPS_TEST_MEMBER_USER'),'authenticated VPS-E2E is expliciet gated en gebruikt dedicated secrets');

c58(str_contains($security,'https://test.vps.holox.nl')&&!str_contains($security,'https://rc045.nl/dev'),'live security default is VPS-test');
c58(str_contains($browser,'https://test.vps.holox.nl')&&!str_contains($browser,'https://rc045.nl/dev'),'browseracceptatie default is VPS-test');
c58(str_contains($auth,'https://test.vps.holox.nl')&&!str_contains($auth,"domain.includes('rc045.nl')")&&str_contains($auth,'cookieHoortBijHost'),'authenticated browsertest gebruikt dynamische VPS-hostbinding');

c58(str_contains($deploy,'name: Deploy RC045test to VPS test')&&str_contains($deploy,"vars.VPS_TEST_DEPLOY_ENABLED == 'true'"),'privileged VPS-deploy is apart en standaard expliciet gated');
c58(str_contains($deploy,'environment: vps-test')&&str_contains($deploy,'secrets.VPS_TEST_DEPLOY_KEY')&&str_contains($deploy,'secrets.VPS_TEST_SSH_KNOWN_HOSTS'),'deploy gebruikt aparte environment-key en gepinde hosttrust');
c58(str_contains($deploy,'StrictHostKeyChecking=yes')&&!str_contains($deploy,'ssh-keyscan'),'workflow gebruikt geen trust-on-first-use voor SSH');
c58(str_contains($deploy,'"deploy $DEPLOY_SHA"')===false ? str_contains($deploy,'"deploy $DEPLOY_SHA"') : true,'noop');
c58(str_contains($deploy,'"deploy $DEPLOY_SHA"') || str_contains($deploy,'"deploy $DEPLOY_SHA"'),'workflow vraagt alleen exacte commitdeploy');
c58(str_contains($deploy,'DEPLOYED $DEPLOY_SHA'),'workflow verifieert serverbevestiging');
c58(str_contains($deploy,'https://test.vps.holox.nl')&&str_contains($deploy,'healthz.php" 204'),'post-deploy smoke test bewijst VPS-testhealth');

c58(str_contains($entry,'SSH_ORIGINAL_COMMAND')&&str_contains($entry,'^deploy[[:space:]]+([0-9a-f]{40})$'),'forced SSH entrypoint accepteert uitsluitend deploy + 40-hex commit');
c58(str_contains($entry,'exec /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-deploy "$commit"') || str_contains($entry,'exec /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-deploy "$commit"'),'entrypoint kan uitsluitend vaste root-wrapper starten');
c58(str_contains($wrapper,"repo='https://github.com/pjaminon-bit/RC045test.git'")&&str_contains($wrapper,'rev-parse HEAD'),'root-wrapper bindt staging aan vaste repo en exacte Git-commit');
c58(str_contains($wrapper,'trusted_prepare="$platform_root/current/bin/prepare-vps-release.php"') || str_contains($wrapper,'trusted_prepare="$platform_root/current/bin/prepare-vps-release.php"'),'root-wrapper gebruikt actieve vertrouwde prepare-tooling');
c58(str_contains($wrapper,'"$php" "$trusted_apply" --plan="$plan" --check')&&str_contains($wrapper,'"$php" "$trusted_apply" --plan="$plan" --deploy'),'root-wrapper voert bestaande check en immutable deploy uit');
c58(str_contains($wrapper,'release-state.json')&&str_contains($wrapper,'echo "DEPLOYED $commit"'),'root-wrapper bewijst actieve release-state vóór succesmelding');
c58(str_contains($docs,'RC045test` blijft de bronrepository')&&str_contains($docs,'geen algemene SSH-shell'),'documentatie borgt repobehoud en restricted deployment');

echo"Phase 5.8 VPS test Actions: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
