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

$databaseContract = (string) file_get_contents($root . '/app/deployment/database-contract.php');
$bootstrap = (string) file_get_contents($root . '/bin/apply-first-vps-bootstrap.php');

c524(
    str_contains($databaseContract, 'include_dir "/etc/verenigingsplatform/postgresql/pg_hba.d"')
        && !str_contains($databaseContract, "include_dir '/etc/verenigingsplatform/postgresql/pg_hba.d'"),
    'HBA include_dir gebruikt PostgreSQL-geldige double-quote syntax'
);

c524(
    str_contains($bootstrap, 'SHOW listen_addresses;')
        && str_contains($bootstrap, "if(trim(\$listen)!=='')")
        && str_contains($bootstrap, "listen_addresses=''"),
    'production preflight weigert een PostgreSQL TCP-listener vóór bootstrap'
);

c524(
    str_contains($bootstrap, 'SHOW unix_socket_directories;')
        && str_contains($bootstrap, "in_array('/var/run/postgresql',\$socketDirs,true)"),
    'production preflight vereist de vaste PostgreSQL Unix-socketdirectory'
);

$preflightCall = strpos($bootstrap, 'b52ProductionPreflight($p,$bins);');
$stateBaseCall = strpos($bootstrap, 'b52PlatformStateBase($p);');
c524(
    $preflightCall !== false
        && $stateBaseCall !== false
        && $preflightCall < $stateBaseCall,
    'production preflight draait vóór de eerste platformstate-mutatie'
);

echo "Phase 5.2.4 PostgreSQL live findings: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
