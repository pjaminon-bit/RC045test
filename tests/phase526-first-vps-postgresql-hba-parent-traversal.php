<?php
$root = dirname(__DIR__);
$apply = (string)file_get_contents($root . '/bin/apply-vps-database.php');
$ok = 0;
$fout = 0;
function c526(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$mkdir = strpos($apply, "@mkdir('/etc/verenigingsplatform', 0710, true)");
$chgrp = strpos($apply, "@chgrp('/etc/verenigingsplatform', \$pgGid)");
$chmod = strpos($apply, "@chmod('/etc/verenigingsplatform', 0710)");
$tenantWrite = strpos($apply, 'apply45VeiligSchrijf($tenantHba');

c526($mkdir !== false, 'HBA apply maakt de platformconfig-parent direct met least-privilege 0710 aan');
c526($chgrp !== false && $chmod !== false, 'HBA apply normaliseert de parent naar root:postgres 0710 zodat postgres alleen kan traversen');
c526($chgrp !== false && $chmod !== false && $tenantWrite !== false && $chgrp < $tenantWrite && $chmod < $tenantWrite, 'PostgreSQL traverse-rechten zijn geregeld vóór tenant-HBA en pg_hba_file_rules validatie');
c526(str_contains($apply, 'root:postgres 0710 worden gemaakt voor PostgreSQL traverse'), 'mislukte parent-normalisatie stopt fail-closed met een gerichte foutmelding');

if ($fout > 0) {
    fwrite(STDERR, "{$fout} fase-5.2.6 regressietest(s) mislukt.\n");
    exit(1);
}
echo "ALLE FASE-5.2.6 POSTGRESQL HBA PARENT-TRAVERSAL TESTS GESLAAGD ({$ok})\n";
