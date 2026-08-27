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

$mkdir = strpos($apply, "@mkdir('/etc/verenigingsplatform', 0711, true)");
$chgrpRoot = strpos($apply, "@chgrp('/etc/verenigingsplatform', 0)");
$chmodRoot = strpos($apply, "@chmod('/etc/verenigingsplatform', 0711)");
$postgresDirs = strpos($apply, "foreach (['/etc/verenigingsplatform/postgresql', \$includeDir] as \$dir)");
$postgresGroup = strpos($apply, '!@chgrp($dir, $pgGid)');
$postgresMode = strpos($apply, '!@chmod($dir, 0750)');
$tenantWrite = strpos($apply, 'apply45VeiligSchrijf($tenantHba');

c526($mkdir !== false, 'database apply maakt de gedeelde platformconfig-parent direct als traverse-only 0711 aan');
c526($chgrpRoot !== false && $chmodRoot !== false, 'database apply normaliseert de gedeelde parent naar root:root 0711 zodat andere platformdiensten bekende subpaden kunnen traverseren zonder directorylisting');
c526($postgresDirs !== false && $postgresGroup !== false && $postgresMode !== false, 'PostgreSQL-submappen blijven afzonderlijk root:postgres 0750 beschermd');
c526($chmodRoot !== false && $tenantWrite !== false && $chmodRoot < $tenantWrite, 'gedeelde parent-traversal is geregeld vóór tenant-HBA en pg_hba_file_rules validatie');
c526(str_contains($apply, 'root:root 0711 worden gemaakt als gedeelde traverse-only platformconfig-parent'), 'mislukte parent-normalisatie stopt fail-closed met een gerichte foutmelding');
c526(!str_contains($apply, "@chgrp('/etc/verenigingsplatform', \$pgGid)"), 'database apply eigent de gedeelde platformconfig-parent niet meer exclusief toe aan postgres');

if ($fout > 0) {
    fwrite(STDERR, "{$fout} fase-5.2.6 regressietest(s) mislukt.\n");
    exit(1);
}
echo "ALLE FASE-5.2.6 POSTGRESQL HBA PARENT-TRAVERSAL TESTS GESLAAGD ({$ok})\n";
