<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function audit308Check(bool $conditie, string $melding): void
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

$web = (string)file_get_contents($root . '/docs/VPS-WEBSERVER.md');
$deploy = (string)file_get_contents($root . '/docs/VPS-DEPLOYMENT.md');

foreach ([$web, $deploy] as $inhoud) {
    audit308Check(str_contains($inhoud, 'current/public') || str_contains($inhoud, 'app_root/public'), 'documentatie benoemt minimale public-root als DocumentRoot');
}

audit308Check(str_contains($web, 'release-root zelf') && str_contains($web, 'Require all denied'), 'webserverdoc documenteert release-root als denied');
audit308Check(str_contains($web, 'public/index.php') && str_contains($web, 'fysiek') && str_contains($web, 'PHP'), 'webserverdoc benoemt public/index.php als fysieke PHP-entrypoint');
audit308Check(str_contains($web, 'AllowOverride FileInfo Indexes Options'), 'webserverdoc beschrijft beperkte AllowOverride voor public-root');
audit308Check(!str_contains($web, 'DocumentRoot "/srv/verenigingsplatform/current"'), 'oude full-release DocumentRoot ontbreekt');
audit308Check(!str_contains($web, 'AllowOverride All'), 'oude brede AllowOverride All-instructie ontbreekt');
audit308Check(!str_contains($web, '<LocationMatch "^/(?:app|bin|tests|docs|\\.github|\\.git)'), 'oude interne-paden denylist is niet meer actuele configuratie');
audit308Check(!str_contains($deploy, 'de documentroot is de gedeelde applicatierelease'), 'deploymentcontract bevat geen oude full-release DocumentRoot-claim');
audit308Check(str_contains($deploy, 'alleen `/srv/verenigingsplatform/current/public` is de Apache DocumentRoot'), 'deploymentcontract legt public-rootgrens expliciet vast');

printf("Audit #308 public-rootdocumentatie: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
