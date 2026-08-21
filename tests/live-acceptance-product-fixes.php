<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function liveFixCheck(bool $c,string $l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}

$ht=(string)file_get_contents($root.'/.htaccess');
liveFixCheck(str_contains($ht,'Header always unset X-Powered-By')&&str_contains($ht,'Header unset X-Powered-By'),'Apache verwijdert X-Powered-By in normale en always-headertabel');

$endpoint=(string)file_get_contents($root.'/public-content.php');
liveFixCheck(str_contains($endpoint,'tenantRuntimeExternConfigPad()')&&str_contains($endpoint,'tenantRuntimeConfigVerplicht()')&&str_contains($endpoint,'publicContentLegacyRoot() . DIRECTORY_SEPARATOR . $bestand')&&str_contains($endpoint,'http_response_code(204)'),'ontbrekende standalone override wordt vóór tenant-store resolutie als 204 behandeld');
liveFixCheck(str_contains($endpoint,'http_response_code(404)'),'tenant/ongeldige content blijft fail-closed met 404');

$seo=(string)file_get_contents($root.'/app/content/seo-head.php');
liveFixCheck(str_contains($seo,'acceptance-hardening.css')&&str_contains($seo,'acceptance-hardening.js'),'alle publieke SEO-heads laden centrale browserhardening');

$css=(string)file_get_contents($root.'/acceptance-hardening.css');
liveFixCheck(str_contains($css,'@media (max-width: 900px)')&&str_contains($css,'.nav-links { display: none !important; }')&&str_contains($css,'.nav-hamburger { display: flex !important; }'),'tablet gebruikt hamburger in plaats van overlopende desktopnav');
liveFixCheck(str_contains($css,'.about-grid > *')&&str_contains($css,'min-width: 0 !important'),'gridkinderen kunnen viewport niet via min-content verbreden');
liveFixCheck(str_contains($css,'.carousel-dot')&&str_contains($css,'min-width: 28px !important')&&str_contains($css,'min-height: 28px !important'),'carousel-dots hebben minimaal 28x28 klikgebied');

$js=(string)file_get_contents($root.'/acceptance-hardening.js');
liveFixCheck(str_contains($js,"select#landcode")&&str_contains($js,"setAttribute('aria-label', 'Landcode')"),'landcode-select krijgt toegankelijke naam');
foreach(['voornaam','achternaam','geboortedatum','straat','huisnummer','postcode','stad','land','akkoord-reglement','akkoord-betaling'] as $id){liveFixCheck(str_contains($js,"'{$id}'"),'aanmeldformulier borgt required-semantiek voor '.$id);}

$isolatie=(string)file_get_contents($root.'/tests/phase321-public-content-isolation.php');
liveFixCheck(str_contains($isolatie,"trim(\$outMissing)==='STATUS=404'"),'bestaande tenantisolatietest blijft ontbrekende tenantdataset als 404 eisen');

echo"Live acceptance product fixes: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
