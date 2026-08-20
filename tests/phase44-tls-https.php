<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c44(bool $c,string $l):void{global$ok,$fout;if($c){$ok++;echo"OK: $l\n";}else{$fout++;fwrite(STDERR,"FOUT: $l\n");}}
function rr44(string $p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$i){if($i==='.'||$i==='..')continue;rr44($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p);}
function run44(array $a,?string $in=null):array{$d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($a,$d,$x,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,''];if($in!==null)fwrite($x[0],$in);fclose($x[0]);$o=stream_get_contents($x[1]);fclose($x[1]);$e=stream_get_contents($x[2]);fclose($x[2]);return[proc_close($p),trim((string)$o."\n".(string)$e)];}
function setup44(string $root,string $base,string $key,string $url,string $secret):string{$t=$base.'/'.$key;$cmds=[[PHP_BINARY,$root.'/bin/provision-tenant.php','--key='.$key,'--name=TLS '.ucfirst($key),'--url='.$url,'--root='.$base,'--modules=website,ledenadministratie']];foreach($cmds as$c)if(run44($c)[0]!==0)return'';if(run44([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$t.'/config.php','--password-stdin'],$secret."\n")[0]!==0)return'';if(run44([PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$t.'/config.php','--app-root='.$root])[0]!==0)return'';if(run44([PHP_BINARY,$root.'/bin/prepare-vps-runtime.php','--deployment='.$t.'/deployment.json'])[0]!==0)return'';if(run44([PHP_BINARY,$root.'/bin/prepare-vps-webserver.php','--runtime-plan='.$t.'/runtime/runtime-plan.json'])[0]!==0)return'';return$t;}
function ready44(string $plan,string $tenant,string $host,array $owner,?array $terminal=null):string{$j=json_decode((string)file_get_contents($plan),true);$now=time();$path=dirname($plan).'/dns-readiness.json';$s=['schema'=>1,'phase'=>'4.3-readiness','tenant_key'=>$tenant,'canonical_host'=>$host,'strategy'=>$j['strategy'],'ready'=>true,'resolver_mode'=>'system','checked_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now),'expires_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now+900),'source'=>['dns_plan_file'=>$plan,'dns_plan_sha256'=>hash_file('sha256',$plan),'web_plan_sha256'=>$j['source']['web_plan_sha256']],'propagation'=>['sample_count'=>3,'interval_seconds'=>2,'scope'=>'configured-system-resolver'],'observed'=>['owner'=>$owner,'terminal'=>$terminal]];file_put_contents($path,dns43Json($s));@chmod($path,0640);return$path;}
require_once $root.'/app/deployment/tls-contract.php';
$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase44-'.bin2hex(random_bytes(5));$base=$tmp.'/tenants';@mkdir($base,0750,true);
try{
$a=setup44($root,$base,'noorderhaven','https://noorderhaven.example','Noorderhaven-TLS-2026!');$b=setup44($root,$base,'duinrand','https://duinrand.example','Duinrand-TLS-2026!');c44($a!==''&&$b!=='','twee fase-4.2 tenants vormen TLS-bron');
$webA=$a.'/webserver/web-plan.json';$webB=$b.'/webserver/web-plan.json';
c44(run44([PHP_BINARY,$root.'/bin/prepare-vps-dns.php','--web-plan='.$webA,'--strategy=direct','--ipv4=203.0.113.10','--ipv6=2001:db8::10'])[0]===0,'tenant A krijgt direct DNS-plan');
c44(run44([PHP_BINARY,$root.'/bin/prepare-vps-dns.php','--web-plan='.$webB,'--strategy=cname','--cname=edge.example.net','--ipv4=198.51.100.20'])[0]===0,'tenant B krijgt CNAME DNS-plan');
$planDA=$a.'/dns/dns-plan.json';$planDB=$b.'/dns/dns-plan.json';$rA=ready44($planDA,'noorderhaven','noorderhaven.example',['a'=>['203.0.113.10'],'aaaa'=>['2001:db8::10'],'cname'=>[],'ttl_min'=>60]);$rB=ready44($planDB,'duinrand','duinrand.example',['a'=>[],'aaaa'=>[],'cname'=>['edge.example.net'],'ttl_min'=>60],['a'=>['198.51.100.20'],'aaaa'=>[],'cname'=>[],'ttl_min'=>60]);
[$dc,$do]=run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rA,'--dry-run']);$dry=json_decode($do,true);c44($dc===0&&is_array($dry)&&($dry['phase']??'')==='4.4'&&!is_dir($a.'/tls'),'TLS dry-run schrijft niets');
c44(($dry['acme']['authenticator']??'')==='webroot'&&($dry['acme']['challenge']??'')==='http-01'&&($dry['acme']['installer_plugin_forbidden']??false)===true,'ACME contract gebruikt alleen Certbot webroot HTTP-01');
c44(($dry['activation']['fresh_dns_readiness_required_at_apply']??false)===true&&($dry['activation']['full_candidate_configtest_before_enable']??false)===true,'TLS-plan vereist verse DNS-readiness en volledige configtest');
c44(($dry['security']['host_and_sni_must_match_tenant']??false)===true&&($dry['security']['unknown_https_uses_neutral_reject_certificate']??false)===true,'TLS-plan verankert Host/SNI-isolatie en neutrale catch-all');
c44(($dry['security']['hsts_include_subdomains']??true)===false&&($dry['security']['hsts_seconds']??0)===31536000,'HSTS is één jaar zonder riskante includeSubDomains');
c44(str_starts_with((string)$dry['apache']['http_catchall_filename'],'000-000-')&&str_starts_with((string)$dry['apache']['https_catchall_filename'],'000-000-'),'beide catch-alls sorteren vóór standaard Ubuntu sites');

[$pa,$oa]=run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rA]);[$pb,$ob]=run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rB]);$tpA=$a.'/tls/tls-plan.json';$tpB=$b.'/tls/tls-plan.json';$jA=json_decode((string)file_get_contents($tpA),true);$jB=json_decode((string)file_get_contents($tpB),true);c44($pa===0&&$pb===0&&is_array($jA)&&is_array($jB),'TLS-bundles worden voor beide tenants geschreven');
c44(($jA['acme']['cert_name']??'')!==($jB['acme']['cert_name']??'')&&str_starts_with($jA['acme']['cert_name'],'vp-noorderhaven-'),'Certbot lineages zijn tenant-uniek en deterministisch');
c44(($jA['certificate']['fullchain']??'')==='/etc/letsencrypt/live/'.$jA['acme']['cert_name'].'/fullchain.pem','certificaatpad is deterministisch in Certbot live-lineage');
c44(($jA['source']['dns_readiness_sha256']??'')===hash_file('sha256',$rA)&&($jA['source']['web_plan_sha256']??'')===hash_file('sha256',$webA),'TLS-plan bindt byte-exact aan readiness en web-plan');
$perm=fileperms($tpA);c44($perm!==false&&(($perm&0777)===0640),'tls-plan.json krijgt server-only 0640');
$hcA=(string)file_get_contents($jA['bundle']['http_catchall']);$hcB=(string)file_get_contents($jB['bundle']['http_catchall']);$htA=(string)file_get_contents($jA['bundle']['tenant_http']);$hsA=(string)file_get_contents($jA['bundle']['https_catchall']);$hsB=(string)file_get_contents($jB['bundle']['https_catchall']);$stA=(string)file_get_contents($jA['bundle']['tenant_https']);$stB=(string)file_get_contents($jB['bundle']['tenant_https']);$hook=(string)file_get_contents($jA['bundle']['renewal_hook']);
c44(hash_equals($hcA,$hcB),'HTTP default catch-all is tenant-neutraal en byte-identiek');
c44(str_contains($hcA,'StrictHostCheck On')&&str_contains($hcA,'Require all denied')&&!str_contains($hcA,'noorderhaven'),'HTTP catch-all weigert onbekende hosts zonder tenantlek');
c44(str_contains($htA,'ServerName noorderhaven.example')&&str_contains($htA,'/.well-known/acme-challenge'),'tenant HTTP-vhost bindt exact host en ACME challengepad');
c44(str_contains($htA,'Require all granted')&&str_contains($htA,'Require all denied'),'alleen challenge-directory krijgt expliciete HTTP-toegang');
c44(str_contains($htA,'https://noorderhaven.example%{REQUEST_URI}')&&!str_contains($htA,'%{HTTP_HOST}'),'HTTP redirect gebruikt vaste host en reflecteert request-Host niet');
c44(str_contains($htA,'[R=308,L,NE]'),'niet-ACME HTTP gebruikt permanente 308 HTTPS-redirect');
c44(hash_equals($hsA,$hsB),'HTTPS default catch-all is tenant-neutraal en byte-identiek');
c44(str_contains($hsA,'invalid.verenigingsplatform.invalid')&&str_contains($hsA,'SSLStrictSNIVHostCheck On')&&str_contains($hsA,'Require all denied'),'HTTPS catch-all gebruikt neutraal reject-profiel en weigert content');
c44(str_contains($hsA,'default-reject.crt')&&str_contains($hsA,'default-reject.key'),'onbekende SNI gebruikt apart platform-rejectcertificaat');
c44(str_contains($stA,'ServerName noorderhaven.example')&&str_contains($stB,'ServerName duinrand.example'),'HTTPS-vhosts zijn exact hostgebonden');
c44(str_contains($stA,'%{SSL:SSL_TLS_SNI}')&&str_contains($stA,'%{HTTP_HOST}')&&str_contains($stA,'[F,L]'),'tenant HTTPS controleert zowel TLS-SNI als Host fail-closed');
c44(str_contains($stA,'SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1')&&str_contains($stA,'SSLCompression Off'),'TLS 1.0/1.1 en compressie zijn uitgeschakeld');
c44(str_contains($stA,'Strict-Transport-Security "max-age=31536000"')&&!str_contains($stA,'includeSubDomains'),'HSTS bevat bewust geen includeSubDomains');
c44(str_contains($stA,$jA['certificate']['fullchain'])&&str_contains($stA,$jA['certificate']['privkey']),'tenant HTTPS verwijst uitsluitend naar eigen Certbot lineage');
c44(!str_contains($stA,$jB['acme']['cert_name'])&&!str_contains($stB,$jA['acme']['cert_name']),'tenantcertificaatpaden kunnen niet kruisverwijzen');
c44(str_contains($stA,$jA['apache']['routing_fragment_installed']),'HTTPS-wrapper include exact het geïnstalleerde fase-4.2 routingfragment');
c44(strpos($hook,'apache2ctl configtest')<strpos($hook,'systemctl reload apache2'),'renewal hook doet altijd configtest vóór reload');

[$ck,$cko]=run44([PHP_BINARY,$root.'/bin/apply-vps-tls.php','--plan='.$tpA,'--check']);c44($ck===0&&str_contains($cko,'CHECK OK'),'root-vrije TLS --check valideert verse bundle');
c44(run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rA])[0]===0,'identieke TLS-generatie is idempotent');
$orig=(string)file_get_contents($jA['bundle']['tenant_https']);file_put_contents($jA['bundle']['tenant_https'],$orig."#tamper\n");[$tc,$to]=run44([PHP_BINARY,$root.'/bin/apply-vps-tls.php','--plan='.$tpA,'--check']);c44($tc!==0&&str_contains($to,'wijkt af'),'TLS --check weigert gemanipuleerd Apache-artifact');file_put_contents($jA['bundle']['tenant_https'],$orig);@chmod($jA['bundle']['tenant_https'],0640);
$planOrig=(string)file_get_contents($tpA);$jj=json_decode($planOrig,true);$jj['canonical_host']='evil.example';file_put_contents($tpA,tls44Json($jj));[$pc,$po]=run44([PHP_BINARY,$root.'/bin/apply-vps-tls.php','--plan='.$tpA,'--check']);c44($pc!==0&&str_contains($po,'deterministische'),'gemanipuleerd tls-plan.json wordt geweigerd');file_put_contents($tpA,$planOrig);@chmod($tpA,0640);
$out=$tmp.'/tls-buiten';[$oc,$oo]=run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rA,'--output-dir='.$out]);c44($oc!==0&&!is_dir($out)&&str_contains($oo,'binnen de tenantroot'),'TLS-output buiten tenantroot wordt geweigerd');
$can=$tmp.'/canary';file_put_contents($can,'SAFE');$link=$a.'/tls-link';if(function_exists('symlink')&&@symlink($can,$link)){[$sc,$so]=run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rA,'--output-dir='.$link]);c44($sc!==0&&file_get_contents($can)==='SAFE','TLS symlink-output wordt geweigerd zonder canary te wijzigen');@unlink($link);}else c44(true,'TLS symlinktest overgeslagen');
[$sec,$sout]=run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rA,'--email=verboden@example']);c44($sec!==0&&str_contains($sout,'contactdata'),'tenant TLS-CLI weigert account-email/contactdata');

// Expiry: historische planintegriteit blijft leesbaar, maar operationele --check/apply niet.
$status=json_decode((string)file_get_contents($rA),true);$old=time()-1000;$status['checked_at_utc']=gmdate('Y-m-d\\TH:i:s\\Z',$old);$status['expires_at_utc']=gmdate('Y-m-d\\TH:i:s\\Z',$old+900);file_put_contents($rA,dns43Json($status));@chmod($rA,0640);[$ec,$eo]=run44([PHP_BINARY,$root.'/bin/prepare-vps-tls.php','--dns-readiness='.$rA,'--dry-run']);c44($ec!==0&&str_contains($eo,'verlopen'),'nieuwe TLS-bundle kan niet uit verlopen DNS-readiness worden gemaakt');file_put_contents($rA,dns43Json(array_replace($status,['checked_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',time()),'expires_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',time()+900)])));@chmod($rA,0640);

$src=(string)file_get_contents($root.'/bin/apply-vps-tls.php');$contract=(string)file_get_contents($root.'/app/deployment/tls-contract.php');
c44(str_contains($src,"posix_geteuid()!==0")&&str_contains($src,"PHP_OS_FAMILY!=='Linux'"),'TLS root-apply vereist Linux EUID 0');
c44(str_contains($src,"'certbot','show_account'")&&str_contains($src,'vooraf geregistreerd'),'Certbot-account moet buiten tenantautomation vooraf bestaan');
c44(str_contains($src,"'certbot','certonly','--webroot'")&&!str_contains($src,"'certbot','--apache'")&&!str_contains($src,"'certbot','run','--apache'"),'Certbot mag Apache-config niet autonoom herschrijven');
c44(str_contains($src,"'--preferred-challenges','http'")&&str_contains($src,"'--cert-name'")&&str_contains($src,"'-d',$plan['canonical_host']"),'uitgifte vraagt alleen canonical host via HTTP challenge aan');
c44(str_contains($src,'apply44DnsNu($ctx)')&&strpos($src,'apply44DnsNu($ctx)')<strpos($src,"['certbot','certonly'"),'live DNS wordt direct vóór ACME opnieuw gecontroleerd');
c44(str_contains($src,"filetype((string)$ctx['context']['web']['php_fpm']['socket'])")&&str_contains($src,'routing_fragment_installed'),'HTTPS activatie vereist actieve tenant-FPM en exact 4.2 fragment');
c44(str_contains($src,'openssl_x509_check_private_key')&&str_contains($src,"extensions']['subjectAltName")&&str_contains($src,'minimum_remaining_seconds'),'certificaat wordt op key-match, SAN en geldigheid gecontroleerd');
c44(str_contains($src,"/etc/letsencrypt/archive/")&&str_contains($src,'&0077'),'Certbot live-links en private-keyrechten worden fail-closed gecontroleerd');
c44(str_contains($src,'authenticator\\s*=\\s*webroot'),'renewal-config moet webroot-authenticatie bewaren');
c44(str_contains($src,'apply44RollbackHttp')&&str_contains($src,'Certbot HTTP-01 uitgifte faalde'),'mislukte ACME-uitgifte heeft expliciete HTTP rollback');
c44(substr_count($src,'apply44Configtest($plan)')>=2&&substr_count($src,'apply44Reload()')>=2,'HTTP- en volledige HTTPS-kandidaten krijgen elk configtest vóór reload');
c44(str_contains($contract,"'ssl_module'")&&str_contains($contract,"'certificate_private_key_never_serialized' => true"),'TLS-contract vereist mod_ssl en serializeert geen private key');
c44(!str_contains((string)file_get_contents($tpA),'PRIVATE KEY')&&!str_contains((string)file_get_contents($tpA),'@'),'TLS-plan bevat geen private key of account-email');
}finally{rr44($tmp);}echo"Phase 4.4 TLS HTTPS: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
