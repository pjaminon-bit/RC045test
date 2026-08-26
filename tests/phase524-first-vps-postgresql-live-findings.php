<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c524(bool $conditie, string $label): void
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

$database = (string) file_get_contents(
    $root . '/app/deployment/database-contract.php'
);

$bootstrap = (string) file_get_contents(
    $root . '/bin/apply-first-vps-bootstrap.php'
);

c524(
    str_contains(
        $database,
        "'hba_include_directive' => 'include_dir \"/etc/verenigingsplatform/postgresql/pg_hba.d\"'"
    )
    &&
    !str_contains(
        $database,
        "include_dir '/etc/verenigingsplatform/postgresql/pg_hba.d'"
    ),
    'fase 4.5 gebruikt PostgreSQL-geldige HBA include_dir quoting'
);

c524(
    str_contains($bootstrap, "'SHOW listen_addresses;'")
    &&
    str_contains($bootstrap, "trim(\$listen)!==''"),
    'first-VPS production preflight vereist lege listen_addresses'
);

c524(
    str_contains($bootstrap, "'SHOW unix_socket_directories;'")
    &&
    str_contains(
        $bootstrap,
        "in_array('/var/run/postgresql',\$socketDirs,true)"
    ),
    'first-VPS production preflight vereist /var/run/postgresql'
);

$productionPreflight = strpos(
    $bootstrap,
    'b52ProductionPreflight($p,$bins);'
);

$firstMutation = strpos(
    $bootstrap,
    'b52PlatformStateBase($p);$trustedReleasePlan='
);

c524(
    $productionPreflight !== false
    &&
    $firstMutation !== false
    &&
    $productionPreflight < $firstMutation,
    'PostgreSQL production preflight blijft vóór bootstrapmutaties'
);

echo "Phase 5.2.4 PostgreSQL live findings: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
