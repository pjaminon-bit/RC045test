<?php
// Fase 4.8.1 — pure padbinding voor definitieve tenantverwijdering.
// Geen rootmutaties. Zowel normale purge als crash-recovery gebruiken exact
// deze binding voordat de tenantboom recursief mag worden verwijderd.

function lifecycle481DeleteBinding(array $plan, ?array $tombstone = null): array
{
    if ((int)($plan['schema'] ?? 0) !== 1 || ($plan['phase'] ?? '') !== '4.8') {
        throw new RuntimeException('Purge-plan heeft onbekend schema of fase.');
    }
    $tenant = (string)($plan['tenant_key'] ?? '');
    if (!runtime41CanoniekeTenantKey($tenant)) {
        throw new RuntimeException('Purge-plan bevat geen canonieke tenant-key.');
    }
    $fs = $plan['filesystem'] ?? null;
    if (!is_array($fs)) throw new RuntimeException('Purge-plan mist filesystembinding.');
    $root = (string)($fs['tenant_root'] ?? '');
    if (!runtime41IsAbsoluutPad($root) || runtime41HeeftRelatieveSegmenten($root)) {
        throw new RuntimeException('Tenantroot voor purge is geen veilig absoluut pad.');
    }
    $root = runtime41NormPad($root);
    $base = runtime41NormPad(dirname($root));
    if ($root === '/' || $base === '/' || !hash_equals($tenant, basename($root))) {
        throw new RuntimeException('Tenantroot voor purge is niet exact aan tenant-key gebonden.');
    }
    $expected = [
        'private_root' => $root . '/private',
        'bundle_dir' => $root . '/lifecycle',
        'plan_file' => $root . '/lifecycle/lifecycle-plan.json',
        'state_dir' => '/var/lib/verenigingsplatform/lifecycle',
        'state_file' => '/var/lib/verenigingsplatform/lifecycle/' . $tenant . '.json',
        'plan_snapshot_dir' => '/var/lib/verenigingsplatform/lifecycle/plans',
        'plan_snapshot_file' => '/var/lib/verenigingsplatform/lifecycle/plans/' . $tenant . '.json',
        'tombstone_dir' => '/var/lib/verenigingsplatform/lifecycle/tombstones',
        'tombstone_file' => '/var/lib/verenigingsplatform/lifecycle/tombstones/' . $tenant . '.json',
        'export_root' => '/var/backups/verenigingsplatform/tenants/' . $tenant,
        'lock_file' => '/run/lock/verenigingsplatform-lifecycle-' . $tenant . '.lock',
    ];
    foreach ($expected as $key => $value) {
        $actual = (string)($fs[$key] ?? '');
        if (!hash_equals(runtime41NormPad($value), runtime41NormPad($actual))) {
            throw new RuntimeException('Purge filesystembinding wijkt af: ' . $key);
        }
    }
    if ($tombstone !== null) {
        if ((int)($tombstone['schema'] ?? 0) !== 1
            || ($tombstone['phase'] ?? '') !== '4.8-tombstone'
            || !hash_equals($tenant, (string)($tombstone['tenant_key'] ?? ''))) {
            throw new RuntimeException('Purge-tombstone is niet exact tenantgebonden.');
        }
        $status = (string)($tombstone['status'] ?? '');
        if (!in_array($status, ['purging_infrastructure', 'data_delete', 'deleted'], true)) {
            throw new RuntimeException('Purge-tombstone heeft onbekende status.');
        }
        if (array_key_exists('tenant_root', $tombstone)
            && !hash_equals($root, runtime41NormPad((string)$tombstone['tenant_root']))) {
            throw new RuntimeException('Purge-tombstone wijst naar een andere tenantroot.');
        }
    }
    return ['tenant_key' => $tenant, 'tenant_base' => $base, 'tenant_root' => $root];
}
