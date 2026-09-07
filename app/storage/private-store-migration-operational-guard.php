<?php
// ============================================================
// Private-store migration downstream operational-plan guard (#211)
// ============================================================
// Een JSON->PDO migration-target wijzigt het fase-4.5 databaseplan. Bestaande
// monitoring- en lifecycleplannen zijn byte-exact aan dat plan gebonden en
// moeten daarom opnieuw zijn voorbereid voordat check/apply/rollback doorgaat.
// Deze guard muteert niets; hij valideert alleen bestaande downstream-plannen.
// ============================================================

function privateMigrationOperationalPlanBestand(string $pad, string $label): string
{
    if (is_link($pad) || !is_file($pad)) {
        throw new RuntimeException($label . ' ontbreekt of is onveilig.');
    }
    $real = realpath($pad);
    if ($real === false || !hash_equals($pad, $real)) {
        throw new RuntimeException($label . ' moet een fysiek canoniek pad zijn.');
    }
    return $real;
}

function privateMigrationOperationalPlansValidate(
    string $tenantRoot,
    string $tenant,
    ?callable $monitoringValidator = null,
    ?callable $lifecycleValidator = null
): array {
    $tenantRoot = rtrim($tenantRoot, '/');
    $realRoot = realpath($tenantRoot);
    if ($tenantRoot === '' || $tenant === '' || $realRoot === false || !hash_equals($tenantRoot, $realRoot)) {
        throw new RuntimeException('Operationele migratieplancontrole vereist een canonieke tenantroot en tenant-key.');
    }

    $databasePlan = $tenantRoot . '/database/database-plan.json';
    $monitoringPlan = $tenantRoot . '/monitoring/monitoring-plan.json';
    $lifecyclePlan = $tenantRoot . '/lifecycle/lifecycle-plan.json';
    $heeftMonitoring = file_exists($monitoringPlan) || is_link($monitoringPlan);
    $heeftLifecycle = file_exists($lifecyclePlan) || is_link($lifecyclePlan);

    if (!$heeftMonitoring && !$heeftLifecycle) {
        return ['monitoring' => 'absent', 'lifecycle' => 'absent'];
    }
    if ($heeftLifecycle && !$heeftMonitoring) {
        throw new RuntimeException('Lifecycleplan bestaat zonder actueel monitoringplan; storage-migratie wordt fail-closed geweigerd.');
    }

    if ($monitoringValidator === null || ($heeftLifecycle && $lifecycleValidator === null)) {
        require_once dirname(__DIR__) . '/deployment/lifecycle-contract.php';
    }
    $monitoringValidator ??= 'monitoring46PlanLeesEnValideer';
    $lifecycleValidator ??= 'lifecycle48PlanLeesEnValideer';

    $monitoringPad = privateMigrationOperationalPlanBestand($monitoringPlan, 'Monitoringplan');
    $monitoringCtx = $monitoringValidator($monitoringPad);
    $monitoring = is_array($monitoringCtx) ? ($monitoringCtx['plan'] ?? null) : null;
    if (!is_array($monitoring)
        || !hash_equals($tenant, (string)($monitoring['tenant_key'] ?? ''))
        || !hash_equals($databasePlan, (string)($monitoring['source']['database_plan_file'] ?? ''))) {
        throw new RuntimeException('Monitoringplan is niet aan het actuele migration-target databaseplan gebonden. Bereid monitoring opnieuw voor met --force.');
    }

    $status = ['monitoring' => 'valid', 'lifecycle' => 'absent'];
    if (!$heeftLifecycle) return $status;

    $lifecyclePad = privateMigrationOperationalPlanBestand($lifecyclePlan, 'Lifecycleplan');
    $lifecycleCtx = $lifecycleValidator($lifecyclePad);
    $lifecycle = is_array($lifecycleCtx) ? ($lifecycleCtx['plan'] ?? null) : null;
    if (!is_array($lifecycle)
        || !hash_equals($tenant, (string)($lifecycle['tenant_key'] ?? ''))
        || !hash_equals($databasePlan, (string)($lifecycle['source']['database_plan_file'] ?? ''))
        || !hash_equals($monitoringPlan, (string)($lifecycle['source']['monitoring_plan_file'] ?? ''))) {
        throw new RuntimeException('Lifecycleplan is niet aan de actuele monitoring/databaseplannen gebonden. Bereid lifecycle opnieuw voor met --force.');
    }

    $status['lifecycle'] = 'valid';
    return $status;
}
