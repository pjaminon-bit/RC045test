<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c522(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

$apply = (string) file_get_contents($root . '/bin/apply-first-vps-bootstrap.php');
$docs = (string) file_get_contents($root . '/docs/VPS-FIRST-BOOTSTRAP.md');
$executor = (string) file_get_contents($root . '/bin/control-plane-executor.php');

c522(
    str_contains($apply, 'function b52PlatformStateBase(array$p):void') &&
    str_contains($apply, "\$base!=='/var/lib/verenigingsplatform'") &&
    str_contains($apply, '@chmod($base,0711)') &&
    str_contains($apply, 'b52Meta($base,0711,true)'),
    'platformstate-basis is exact root:root 0711 zodat Apache alleen kan traverseren naar publieke ACME-paden'
);

$productionPreflight = strpos($apply, 'b52ProductionPreflight($p,$bins);');
$stateBaseCall = strpos($apply, 'b52PlatformStateBase($p);$trustedReleasePlan=');
c522(
    $productionPreflight !== false && $stateBaseCall !== false && $productionPreflight < $stateBaseCall,
    'platformstate-basis wordt pas na volledige production preflight gemuteerd'
);

$statusExit = strpos($apply, "if(\$mode==='status'){\$state=b52StateRead(\$p,\$ctx['sha256'],true);echo bootstrap52Json(\$state);exit(0);}");
$bootstrapLockOpen = strpos($apply, "fopen(\$p['paths']['lock_file'],'c')");
c522(
    $statusExit !== false && $bootstrapLockOpen !== false && $statusExit < $productionPreflight && $statusExit < $bootstrapLockOpen && $statusExit < $stateBaseCall,
    '--status leest bestaande state en stopt vóór production preflight, lockwrites en filesystemmutaties'
);

c522(
    str_contains($apply, 'function b52TenantBase(array$p):void') &&
    str_contains($apply, '@chmod($base,0750)') &&
    str_contains($apply, 'b52Meta($base,0750,true)'),
    'lege tenantbasis wordt fail-closed als root:root 0750 voorbereid'
);

$tenantBaseCall = strpos($apply, "b52TenantBase(\$p);b52Child(\$current,'apply-vps-control-plane.php'");
$controlPlaneApply = strpos($apply, "b52Child(\$current,'apply-vps-control-plane.php',['--plan='.\$cpBundle.'/control-plane-plan.json','--apply'])");
$tenantProvision = strpos($apply, "b52Child(\$current,'provision-tenant.php'");
c522(
    $tenantBaseCall !== false && $controlPlaneApply !== false && $tenantProvision !== false &&
    $tenantBaseCall < $controlPlaneApply && $controlPlaneApply < $tenantProvision,
    'tenantbasis bestaat vóór eerste control-plane snapshot terwijl tenantprovisioning daarna blijft plaatsvinden'
);

c522(
    str_contains($executor, "throw new RuntimeException('Tenantroot ontbreekt of is onveilig.')"),
    'generieke control-plane executor blijft fail-closed voor werkelijk ontbrekende of onveilige tenantroot'
);

c522(
    str_contains($docs, '**JSON Lines (JSONL)**') &&
    str_contains($docs, '{"operator_password":"..."}') &&
    str_contains($docs, '{"tenant_admin_password":"..."}') &&
    !str_contains($docs, "\"operator_password\": \"...\",\n  \"tenant_admin_password\""),
    'documentatie beschrijft stagegebonden JSONL in plaats van één gecombineerd secrets-object'
);

c522(
    str_contains($docs, 'vanaf `operator_ready` maar vóór `tenant_admin_ready`: alleen één regel met `tenant_admin_password`') &&
    str_contains($docs, 'vanaf `tenant_admin_ready`: geen `--secrets-stdin` meer nodig'),
    'resume-documentatie levert alleen credentials aan die na het huidige checkpoint nog ontbreken'
);

c522(
    str_contains($docs, '/run/first-vps-secrets.jsonl') &&
    str_contains($docs, 'mode `0600`') &&
    str_contains($docs, 'verborgen invoerprompts nooit vermengd raken met gelijktijdige bootstrap-output'),
    'operationele secretinvoer gebruikt vooraf opgebouwd root-only bestand zonder concurrerende prompts'
);

c522(
    str_contains($docs, '/var/lib/verenigingsplatform` is bewust `root:root 0711`') &&
    str_contains($docs, 'lege tenantbasis vóór de eerste control-plane `--refresh-only` snapshot'),
    'live VPS filesystembevindingen zijn expliciet als productiecontract gedocumenteerd'
);

echo "Phase 5.2.2 live first-VPS findings: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
