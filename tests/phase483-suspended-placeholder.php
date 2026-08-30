<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c483(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
$apply=(string)file_get_contents($root.'/bin/apply-vps-lifecycle.php');
function seg483(string$s,string$a,string$b):string{$p=strpos($s,$a);$q=$p===false?false:strpos($s,$b,$p+strlen($a));return($p===false||$q===false)?'':substr($s,$p,$q-$p);}
$vhost=seg483($apply,'function apply48SuspendedVhost','function apply48SuspendedBestanden');
$suspend=seg483($apply,'function apply48ApacheSuspend','function apply48ApachePurge');
$activate=seg483($apply,'function apply48ApacheAan','function apply48ApacheSuspend');
$purge=seg483($apply,'function apply48ApachePurge','function apply48DbBestaat');
$purgeInfra=seg483($apply,'function apply48PurgeInfra','function apply48Tombstone');
c483($vhost!==''&&str_contains($vhost,"'/etc/letsencrypt/live/'.(string)$p['tls']['cert_name']"),'placeholder gebruikt exact het bestaande tenantcertificaat');
c483(str_contains($vhost,'SSLStrictSNIVHostCheck On')&&str_contains($vhost,'%{SSL:SSL_TLS_SNI}')&&str_contains($vhost,'%{HTTP_HOST}'),'placeholder behoudt strikte SNI- en Host-binding');
c483(str_contains($vhost,'ErrorDocument 503 /index.html')&&str_contains($vhost,'[R=503,L]'),'uitgeschakelde tenant antwoordt semantisch met HTTP 503');
c483(str_contains($vhost,'X-Robots-Tag')&&str_contains($vhost,'noindex')&&str_contains($vhost,'Cache-Control'),'placeholder is noindex en niet cachebaar');
c483(!str_contains($vhost,'routing_fragment')&&!str_contains($vhost,'ProxyPass')&&!str_contains($vhost,'SetHandler')&&!str_contains($vhost,'fastcgi'),'placeholder heeft geen koppeling naar tenant-PHP/FPM');
c483(str_contains($apply,"'/var/www/verenigingsplatform-suspended'")&&str_contains($apply,'apply48Write($x[\'index\'],apply48SuspendedHtml(),0644)'),'centrale statische placeholder is root-owned en publiek alleen leesbaar');
c483(str_contains($suspend,'HTTP-01 tenantroute moet actief blijven tijdens suspend.')&&str_contains($suspend,'apply48SuspendedBestanden($p)'),'suspend houdt ACME HTTP-01 actief en bouwt HTTPS-placeholder');
c483(str_contains($suspend,'@unlink($l)')&&str_contains($suspend,"@symlink($s['available'],$s['enabled'])"),'suspend wisselt app-HTTPS naar placeholder-HTTPS');
c483(str_contains($activate,"is_link($s['enabled'])")&&str_contains($activate,"@unlink($s['enabled'])")&&str_contains($activate,"$p['apache']['tenant_https_available']"),'activate schakelt placeholder uit en herstelt normale HTTPS-vhost');
c483(str_contains($purge,"[$s['enabled'],$s['available']]")&&str_contains($purgeInfra,"$s['available']"),'purge verwijdert placeholder-vhostlink en tenantgebonden placeholderconfig');
c483(str_contains($apply,'Deze vereniging is tijdelijk uitgeschakeld')&&str_contains($apply,'Verenigingsplatform'),'placeholder bevat een generieke duidelijke melding');
echo"Phase 4.8.3 suspended placeholder: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
