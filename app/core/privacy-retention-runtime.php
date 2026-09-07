<?php
// ============================================================
// Periodieke tenant-retentie voor formulier-PII
// ============================================================
// De root-owned VPS healthtimer raakt healthz.php iedere minuut, maar deze
// helper voert de relatief zwaardere inboxcleanup maximaal eenmaal per lokale
// kalenderdag uit. Een aparte tenant-private flock serialiseert concurrerende
// healthrequests. De dagmarker wordt pas na beide succesvolle cleanups
// atomair geschreven; bij een crash/fout kan een volgende probe dus retryen.
// ============================================================
require_once dirname(__DIR__, 2) . '/contactberichten-opslag.php';
require_once dirname(__DIR__, 2) . '/aanmeldingen-opslag.php';

function privacyRetentionTenantDag(array $config, ?int $nu = null): string
{
    $nu = $nu ?? time();
    $timezone = trim((string)($config['vereniging']['timezone'] ?? 'Europe/Amsterdam'));
    try {
        $tz = new DateTimeZone($timezone !== '' ? $timezone : 'Europe/Amsterdam');
    } catch (Throwable $e) {
        throw new RuntimeException('Tenant-retentie heeft een ongeldige timezone.', 0, $e);
    }
    return (new DateTimeImmutable('@' . $nu))->setTimezone($tz)->format('Y-m-d');
}

function privacyRetentionPrivateRoot(array $config): string
{
    $root = tenantRuntimePrivateRoot($config);
    if ($root === null || !is_dir($root) || is_link($root) || !is_readable($root) || !is_writable($root)) {
        throw new RuntimeException('Tenant-retentie mist een bruikbare private root.');
    }
    return $root;
}

function privacyRetentionLockPad(array $config): string
{
    return privacyRetentionPrivateRoot($config) . DIRECTORY_SEPARATOR . '.privacy-retention.lock';
}

function privacyRetentionMarkerPad(array $config): string
{
    return privacyRetentionPrivateRoot($config) . DIRECTORY_SEPARATOR . '.privacy-retention-last-run.json';
}

function privacyRetentionMarkerLees(string $pad): ?array
{
    if (!file_exists($pad)) return null;
    if (!is_file($pad) || is_link($pad)) {
        throw new RuntimeException('Tenant-retentiemarker is geen veilig regulier bestand.');
    }
    $raw = @file_get_contents($pad);
    if ($raw === false) throw new RuntimeException('Tenant-retentiemarker kon niet worden gelezen.');
    $data = json_decode($raw, true);
    if (!is_array($data) || (int)($data['schema'] ?? 0) !== 1) {
        throw new RuntimeException('Tenant-retentiemarker bevat ongeldige data.');
    }
    return $data;
}

function privacyRetentionMarkerSchrijf(string $pad, array $data): void
{
    if (is_link($pad)) throw new RuntimeException('Tenant-retentiemarker mag geen symlink zijn.');
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    try {
        $suffix = bin2hex(random_bytes(6));
    } catch (Throwable $e) {
        throw new RuntimeException('Tenant-retentiemarker kon geen veilige tijdelijke naam maken.', 0, $e);
    }
    $tmp = $pad . '.tmp.' . $suffix;
    try {
        if (is_link($tmp) || @file_put_contents($tmp, $json, LOCK_EX) === false) {
            throw new RuntimeException('Tenant-retentiemarker kon niet worden voorbereid.');
        }
        @chmod($tmp, 0640);
        if (is_link($pad) || !@rename($tmp, $pad)) {
            throw new RuntimeException('Tenant-retentiemarker kon niet atomair worden opgeslagen.');
        }
        @chmod($pad, 0640);
    } finally {
        if (is_file($tmp) || is_link($tmp)) @unlink($tmp);
    }
}

/**
 * @return array{ran:bool,day:string,contact_removed:int,membership_removed:int}
 */
function privacyRetentionMaintenanceRun(array $config, ?int $nu = null): array
{
    $nu = $nu ?? time();
    $dag = privacyRetentionTenantDag($config, $nu);
    $tenant = tenantRuntimeVeiligeSleutel((string)($config['vereniging']['sleutel'] ?? 'default'));
    $lockPad = privacyRetentionLockPad($config);
    $markerPad = privacyRetentionMarkerPad($config);

    if (is_link($lockPad)) throw new RuntimeException('Tenant-retentielock mag geen symlink zijn.');
    $lock = @fopen($lockPad, 'c');
    if ($lock === false) throw new RuntimeException('Tenant-retentielock kon niet worden geopend.');
    @chmod($lockPad, 0640);

    try {
        if (!flock($lock, LOCK_EX)) throw new RuntimeException('Tenant-retentielock kon niet exclusief worden verkregen.');

        $marker = privacyRetentionMarkerLees($markerPad);
        if ($marker !== null) {
            $markerTenant = (string)($marker['tenant_key'] ?? '');
            if ($markerTenant === '' || !hash_equals($tenant, $markerTenant)) {
                throw new RuntimeException('Tenant-retentiemarker hoort bij een andere tenant.');
            }
            if (hash_equals($dag, (string)($marker['completed_day'] ?? ''))) {
                return ['ran'=>false, 'day'=>$dag, 'contact_removed'=>0, 'membership_removed'=>0];
            }
        }

        $contact = contactBerichtenOpschonenBewaartermijn();
        $membership = aanmeldingenOpschonenBewaartermijn();

        privacyRetentionMarkerSchrijf($markerPad, [
            'schema' => 1,
            'tenant_key' => $tenant,
            'completed_day' => $dag,
            'completed_at' => date('c', $nu),
        ]);

        return [
            'ran'=>true,
            'day'=>$dag,
            'contact_removed'=>$contact,
            'membership_removed'=>$membership,
        ];
    } finally {
        @flock($lock, LOCK_UN);
        fclose($lock);
    }
}
