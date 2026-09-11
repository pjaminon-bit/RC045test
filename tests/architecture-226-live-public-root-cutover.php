<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function check226live(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$apply = (string)file_get_contents($root . '/bin/apply-vps-webserver.php');
$start = strpos($apply, 'function apply42LiveFragment');
$end = $start === false ? false : strpos($apply, "foreach (\$_SERVER['argv']", $start);
$live = ($start !== false && $end !== false) ? substr($apply, $start, $end - $start) : '';
$metaStart = strpos($apply, 'function apply42RootMeta');
$metaEnd = $metaStart === false ? false : strpos($apply, 'function apply42ApachePreflight', $metaStart);
$meta = ($metaStart !== false && $metaEnd !== false) ? substr($apply, $metaStart, $metaEnd - $metaStart) : '';

check226live(str_contains($apply, "'live-fragment'") && str_contains($apply, '--live-fragment'), 'webserver-applier heeft een expliciete live-fragmentmodus');
check226live(str_contains($apply, 'Kies exact één van --check, --apply of --live-fragment.'), 'live cutover is wederzijds exclusief met check en inactieve apply');
check226live(str_contains($apply, '--force is niet toegestaan bij --live-fragment'), 'live cutover kan niet met generieke force-semantiek worden afgezwakt');
check226live(str_contains($apply, "'200-vp-' . \$tenant . '-https.conf'") && str_contains($apply, 'is_link($enabled)'), 'live cutover vereist een reeds actieve tenant-HTTPS-vhost');
check226live(str_contains($apply, "substr_count(\$raw, 'Include \"' . \$fragment . '\"') !== 1"), 'actieve HTTPS-vhost moet exact één include naar het gebonden routingfragment hebben');
check226live(str_contains($live, "\$doel = \$doelen['fragment'];") && !str_contains($live, "\$doelen['http']") && !str_contains($live, "\$doelen['catchall']"), 'live modus schrijft uitsluitend het HTTPS-routingfragment');
check226live(str_contains($live, "@filetype(\$socket) !== 'socket'"), 'live cutover vereist dat de tenant-FPM-socket daadwerkelijk actief is');
check226live(
    str_contains($live, 'apply42RootMeta($doel, 0644)')
    && str_contains($meta, '@lstat($pad)')
    && str_contains($meta, "(int)\$meta['uid'] !== 0")
    && str_contains($meta, "(int)\$meta['gid'] !== 0")
    && str_contains($meta, "((int)\$meta['mode'] & 0777) !== (\$mode & 0777)"),
    'bestaand live fragment moet root:root 0644 zijn'
);

$voor = strpos($live, 'apply42Configtest($plan)');
$rename = strpos($live, '@rename($tmp, $doel)');
$na = $rename === false ? false : strpos($live, 'apply42Configtest($plan)', $rename);
$reload = $na === false ? false : strpos($live, 'apply42Reload()', $na);
check226live($voor !== false && $rename !== false && $na !== false && $reload !== false && $voor < $rename && $rename < $na && $na < $reload, 'volledige Apache-configtest loopt vóór mutatie én na atomische vervanging vóór reload');
check226live(str_contains($live, 'apply42SchrijfRootAtomisch($doel, $huidig, true, null)') && substr_count($live, 'apply42SchrijfRootAtomisch($doel, $huidig, true, null)') >= 2, 'zowel configtest- als reloadfailure herstellen de vorige fragmentbytes atomisch');
check226live(str_contains($apply, "'/usr/bin/systemctl'") && str_contains($apply, "'reload', 'apache2'"), 'reload gebruikt uitsluitend het vaste systemctl-pad en alleen Apache reload');
check226live(str_contains($live, 'rollback kon niet volledig worden bewezen; handmatige interventie vereist'), 'onbewezen rollback faalt expliciet gesloten met operatorinterventie');
check226live(!str_contains($live, 'symlink(') && !str_contains($live, 'a2ensite'), 'live cutover wijzigt geen sites-enabled symlinks en activeert geen nieuwe vhost');
check226live(str_contains($live, "' document_root=' . \$plan['shared_code']['document_root']"), 'succesoutput bewijst de geactiveerde DocumentRoot uit het gevalideerde webplan');

echo "Architecture #226 live public-root cutover: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
