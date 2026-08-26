<?php
$root = dirname(__DIR__);
$apply = (string)file_get_contents($root . '/bin/apply-first-vps-bootstrap.php');
$ok = 0;
$fout = 0;
function c527(bool $conditie, string $label): void {
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$helper = strpos($apply, 'function b52PlatformAcmeDirs(array$p):void');
$base = strpos($apply, "b52SafeDir(\$base,0711)");
$webroot = strpos($apply, "b52SafeDir(\$webroot,0755)");
$wellKnown = strpos($apply, "b52SafeDir(\$webroot.'/.well-known',0755)");
$challenge = strpos($apply, 'b52SafeDir($challenge,0755)');
$httpCall = strpos($apply, 'b52PlatformAcmeDirs($p);b52DefaultCert($p);');

c527($helper !== false, 'first-VPS bootstrap heeft een gerichte ACME-directorynormalisatie');
c527($base !== false, 'gedeelde ACME-parent wordt root:root 0711 zodat Apache alleen kan traversen en niet kan listen');
c527($webroot !== false && $wellKnown !== false && $challenge !== false, 'platform webroot, .well-known en challenge worden expliciet 0755 genormaliseerd en zijn niet afhankelijk van shell-umask');
c527($base !== false && $webroot !== false && $wellKnown !== false && $challenge !== false && $base < $webroot && $webroot < $wellKnown && $wellKnown < $challenge, 'ACME-padcomponenten worden top-down genormaliseerd vóór HTTP-01 gebruik');
c527($httpCall !== false && $helper !== false && $helper < $httpCall, 'platform HTTP-stage normaliseert ACME traverse-rechten vóór Certbot/certificaatuitgifte');
c527(str_contains($apply, 'Onverwachte platform-ACME paden voor fase 5.2.'), 'afwijkende ACME-paden stoppen fail-closed');

if ($fout > 0) {
    fwrite(STDERR, "{$fout} fase-5.2.7 regressietest(s) mislukt.\n");
    exit(1);
}
echo "ALLE FASE-5.2.7 ACME PARENT-TRAVERSAL TESTS GESLAAGD ({$ok})\n";
