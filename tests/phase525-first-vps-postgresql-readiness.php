<?php
$root = dirname(__DIR__);
$readiness = (string) file_get_contents($root . "/docs/VPS-READINESS.md");
$bootstrap = (string) file_get_contents($root . "/docs/VPS-FIRST-BOOTSTRAP.md");
$ok = str_contains($readiness, "99-verenigingsplatform.conf")
    && str_contains($readiness, "listen_addresses = ''")
    && str_contains($readiness, "SHOW unix_socket_directories")
    && str_contains($bootstrap, "socket-only PostgreSQL");
if (!$ok) { fwrite(STDERR, "FOUT: PostgreSQL readiness-documentatie ontbreekt of is onvolledig.\n"); exit(1); }
echo "OK: PostgreSQL socket-only readiness is gedocumenteerd en geborgd.\n";
