<?php
$root = dirname(__DIR__);
$path = $root . '/bin/accept-phase211-json-pdo.php';
$src = is_file($path) ? (string)file_get_contents($path) : '';
$ok = 0; $fout = 0;
function c211v(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo "OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");} }

c211v($src !== '', 'VPS migration acceptance-runner bestaat');
c211v(str_contains($src, "const A211_TENANT = 'phase211acceptance';"), 'runner gebruikt één vaste acceptance-tenant');
c211v(str_contains($src, "count(\$argv) !== 2 || \$argv[1] !== '--run'"), 'runner accepteert uitsluitend exact --run');
c211v(str_contains($src, '/usr/local/libexec/verenigingsplatform/host-engine/'), 'runner vereist geïnstalleerde immutable host-engine');
c211v(str_contains($src, '.host-engine-manifest.sha256') && str_contains($src, 'Host-engine manifestdrift'), 'runner herverifieert host-engine manifestinhoud');
c211v(str_contains($src, "--driver=json"), 'fixture wordt expliciet als legacy JSON geprovisioneerd');
c211v(str_contains($src, "--migration-target"), 'PostgreSQL wordt expliciet als migration-target voorbereid');
c211v(str_contains($src, 'phase211_poison') && str_contains($src, 'niet leeg en wijkt af'), 'negatieve liveproef vereist afwijkende PDO-target failure');
c211v(substr_count($src, "'--mode=apply'") >= 3, 'runner bevat negatieve, eerste en finale apply');
c211v(str_contains($src, "'--mode=rollback'"), 'runner bewijst rollback vóór finale cutover');
c211v(substr_count($src, 'check-release-tenant.php') >= 2, 'beide PDO-cutovers krijgen read-only applicatieprobe');
c211v(str_contains($src, 'source_unchanged') && str_contains($src, 'source_final'), 'JSON-bronintegriteit wordt vóór/na failure en rollback bewaakt');
c211v(str_contains($src, 'database45Marker($tenant)') && str_contains($src, 'cleanup geweigerd'), 'cleanup verwijdert PostgreSQL-objecten alleen na markerbinding');
c211v(str_contains($src, 'database45HbaConfig($plan)') && str_contains($src, 'Tenant-HBA wijkt af'), 'cleanup verwijdert HBA alleen na inhoudsbinding');
c211v(str_contains($src, 'runtime41VerwachteOsUser') && str_contains($src, '/usr/sbin/userdel') && str_contains($src, '/usr/sbin/groupdel'), 'cleanup is aan deterministische runtime-identiteit gebonden');
c211v(str_contains($src, "'cleanup'=>'not-started'") && str_contains($src, "\$evidence['cleanup'] = 'ok'"), 'evidence legt cleanupresultaat vast');
c211v(str_contains($src, 'proof_sha256') && str_contains($src, 'target_aggregate_sha256'), 'finale cryptografische migratiebewijzen worden gerapporteerd');
c211v(str_contains($src, 'a211RetainProof') && str_contains($src, 'retained_proof_path'), 'finale proof wordt vóór fixturecleanup root-only retained');
c211v(!str_contains($src, 'shell_exec(') && !str_contains($src, 'system(') && !str_contains($src, 'passthru(') && !str_contains($src, 'popen('), 'runner introduceert geen shelluitvoering');

echo "Platform #211 VPS migration acceptance: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
