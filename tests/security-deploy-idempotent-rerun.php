<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$wrapper = $root . '/ops/vps-test-deploy/verenigingsplatform-github-deploy';
$src = @file_get_contents($wrapper);

function test199(bool $ok, string $message): void
{
    if (!$ok) {
        fwrite(STDERR, "FOUT: {$message}\n");
        exit(1);
    }
}

function test199Contains(string $src, string $needle, string $message): void
{
    test199(str_contains($src, $needle), $message);
}

test199(is_string($src) && $src !== '', 'deploywrapper kon niet worden gelezen');

// De bestaande main-tip-, host-launcher- en release-enginegrenzen blijven de
// primaire trust boundary. De wrapper mag alleen het specifieke already-active
// resultaat gecontroleerd omzetten naar een heracceptatiepad.
test199Contains($src, '[[ "$commit" == "$main_commit" ]]', 'deploy blijft aan actuele main-tip gebonden');
test199Contains($src, '"$host_launcher" release-prepare', 'root-owned host-engine prepare blijft verplicht');
test199Contains($src, '"$host_launcher" release-apply --plan="$plan" --check', 'release-engine check blijft verplicht');
test199Contains($src, 'deploy_output="$("$host_launcher" release-apply --plan="$plan" --deploy 2>&1)"', 'deployresultaat wordt gecontroleerd opgevangen');
test199Contains($src, 'deploy_rc=$?', 'deploy exitcode wordt bewaard');

test199Contains(
    $src,
    'elif [[ "$deploy_rc" -eq 1 && "$deploy_output" == \'FOUT: Kandidaatrelease is al actief.\' ]]; then',
    'no-op mag alleen op de specifieke already-active fout worden geactiveerd'
);
test199(substr_count($src, 'FOUT: Kandidaatrelease is al actief.') === 1, 'already-active fout mag niet via meerdere losse matches worden afgehandeld');
test199Contains($src, 'exit "$deploy_rc"', 'alle overige release-enginefouten behouden hun foutstatus');

$checkPos = strpos($src, '"$host_launcher" release-apply --plan="$plan" --check');
$deployPos = strpos($src, 'deploy_output="$("$host_launcher" release-apply --plan="$plan" --deploy 2>&1)"');
$noopCallPos = strpos($src, "\n  validate_already_active\n");
test199(
    is_int($checkPos) && is_int($deployPos) && is_int($noopCallPos)
    && $checkPos < $deployPos && $deployPos < $noopCallPos,
    'host-engine check en deploy moeten vóór de beperkte same-active revalidatie lopen'
);

$fnStart = strpos($src, "validate_already_active() {");
$fnEnd = strpos($src, "\n# Een deploy mag pas plaatsvinden", $fnStart === false ? 0 : $fnStart);
test199(is_int($fnStart) && is_int($fnEnd) && $fnEnd > $fnStart, 'same-active helper kon niet worden afgebakend');
$helper = substr($src, $fnStart, $fnEnd - $fnStart);

// De heracceptatie is read/validate-only voor release-state. Geen current-swap,
// FPM-reload, release-statewrite of brede filesystemmutatie is toegestaan.
test199Contains($helper, '"$active" == "$platform_root/releases/$commit"', 'current moet fysiek aan dezelfde commit gebonden zijn');
test199Contains($helper, '$active/bin/check-release-tenant.php', 'tenantprobe komt uit de reeds geïmmutabiliseerde actieve release');
test199Contains($helper, 'if ! tenant_inventory=', 'tenantinventaris moet zijn eigen Python-exitstatus fail-closed bewaken');
test199Contains($helper, 'mapfile -t tenant_rows <<<"$tenant_inventory"', 'alleen een geslaagde tenantinventaris mag worden geparsed');
test199(!str_contains($helper, 'mapfile -t tenant_rows < <('), 'process-substitution mag Python-fouten in tenantinventaris niet verbergen');
test199Contains($helper, "re.fullmatch(r'vst[0-9a-f]{16}'", 'runtime-user krijgt canonieke tenantvormcontrole');
test199Contains($helper, 'doc.get(\'tenant_key\') != tenant', 'runtimeplan blijft aan tenantdirectory gebonden');
test199Contains($helper, '"$runuser_bin" -u "$runtime_user" -- "$env_bin"', 'tenantprobe draait als tenant-runtimeuser');
test199Contains($helper, '"$runuser_bin" -u "$syntax_user" -- "$php_bin" -l "$php_file"', 'release-PHP syntax draait niet als root maar als tenant-user');
test199Contains($helper, '"$apachectl" configtest', 'Apacheconfiguratie wordt opnieuw gevalideerd');
test199Contains($helper, 'DEPLOY NOOP VALIDATED commit=$commit tenants=$tenant_count already_active=1', 'no-op geeft expliciete niet-geheime acceptance-evidence');

foreach (['release-state.json', 'current.tmp', 'systemctl reload', 'ln -s', 'mv ', 'chown ', 'chmod '] as $forbidden) {
    test199(!str_contains($helper, $forbidden), "same-active helper mag geen muterende releasehandeling bevatten: {$forbidden}");
}

// Geen nieuwe sudo-hop of app-PHP als root in de privileged wrapper.
test199(!str_contains($src, '/usr/bin/sudo'), 'deploywrapper mag geen extra sudo-hop introduceren');
foreach (preg_split('/\R/', $helper) ?: [] as $line) {
    if (str_contains($line, '"$php_bin" -l "$php_file"')) {
        test199(str_contains($line, '"$runuser_bin" -u "$syntax_user" --'), 'PHP syntax mag niet rechtstreeks als root worden uitgevoerd');
    }
}

// De bestaande postcondities blijven ook na een gevalideerde no-op vereist.
test199Contains($src, '[[ "$active" == "$platform_root/releases/$commit" ]]', 'finale current-postconditie blijft verplicht');
test199Contains($src, '[[ "$state_commit" == "$commit" ]]', 'release-state moet dezelfde actieve commit bevestigen');
test199Contains($src, 'echo "DEPLOYED $commit"', 'workflowcontract blijft na volledige validatie behouden');

echo "OK: veilige idempotente same-active deployrerun blijft fail-closed en respecteert de root/release trust boundary.\n";
