<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function audit313Check(bool $conditie, string $melding): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$melding}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$melding}\n");
}

$voorbeeld = (string)file_get_contents($root . '/site-config.local.example.php');
$provisioning = (string)file_get_contents($root . '/docs/PROVISIONING.md');
$provisioner = (string)file_get_contents($root . '/bin/provision-tenant.php');

audit313Check(str_contains($voorbeeld, 'STANDALONE / LEGACY'), 'configvoorbeeld is expliciet standalone/legacy');
audit313Check(str_contains($voorbeeld, 'Gebruik voor een nieuwe multi-tenant VPS-vereniging NIET dit bestand'), 'voorbeeld weigert zichzelf als VPS-provisioningtemplate');
audit313Check(str_contains($voorbeeld, "'private_driver'=>'json', // uitsluitend standalone/legacy"), 'JSON is in voorbeeld expliciet standalone/legacy');
audit313Check(!str_contains($voorbeeld, '// Gedeelde multi-tenant codebase:'), 'oude multi-tenant instructie ontbreekt uit standalonevoorbeeld');
audit313Check(str_contains($voorbeeld, 'provision-tenant.php'), 'nieuwe VPS-route verwijst naar provisioner');
audit313Check(str_contains($voorbeeld, 'PDO/PostgreSQL de canonieke standaard'), 'voorbeeld documenteert PDO als VPS-standaard');
audit313Check(str_contains($provisioning, '--driver=pdo   # standaard/canoniek voor nieuwe tenants'), 'provisioningdocument bewaakt PDO-default');
audit313Check(str_contains($provisioning, '--driver=json  # alleen expliciete standalone/legacycompatibiliteit'), 'provisioningdocument begrenst JSON tot legacy/standalone');
audit313Check(str_contains($provisioner, "?? 'pdo'"), 'uitvoerbare provisioner default blijft PDO');

printf("Audit #313 standalone/VPS-configvoorbeeld: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
