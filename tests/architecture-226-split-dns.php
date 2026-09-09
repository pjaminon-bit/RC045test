<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function c226dns(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo "OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");} }
function rr226dns(string $p): void { if(is_link($p)||is_file($p)){@unlink($p);return;} if(!is_dir($p))return; foreach(scandir($p)?:[] as $x){if($x==='.'||$x==='..')continue;rr226dns($p.DIRECTORY_SEPARATOR.$x);}@rmdir($p); }
function run226dns(array $args, ?string $stdin=null): array { $d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($args,$d,$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,''];if($stdin!==null)fwrite($pipes[0],$stdin);fclose($pipes[0]);$o=stream_get_contents($pipes[1]);fclose($pipes[1]);$e=stream_get_contents($pipes[2]);fclose($pipes[2]);return[proc_close($p),trim((string)$o."\n".(string)$e)]; }
function setup226dns(string $root,string $base): string {
    $t=$base.'/splitdns';
    if(run226dns([PHP_BINARY,$root.'/bin/provision-tenant.php','--key=splitdns','--name=Split DNS','--url=https://test.vps.holox.nl','--root='.$base,'--modules=website,ledenadministratie'])[0]!==0)return'';
    if(run226dns([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$t.'/config.php','--password-stdin'],"Split-DNS-Test-2026!\n")[0]!==0)return'';
    if(run226dns([PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$t.'/config.php','--app-root='.$root])[0]!==0)return'';
    if(run226dns([PHP_BINARY,$root.'/bin/prepare-vps-runtime.php','--deployment='.$t.'/deployment.json'])[0]!==0)return'';
    if(run226dns([PHP_BINARY,$root.'/bin/prepare-vps-webserver.php','--runtime-plan='.$t.'/runtime/runtime-plan.json'])[0]!==0)return'';
    if(run226dns([PHP_BINARY,$root.'/bin/prepare-vps-dns.php','--web-plan='.$t.'/webserver/web-plan.json','--strategy=direct','--ipv4=149.143.36.59'])[0]!==0)return'';
    return $t;
}

require_once $root.'/app/deployment/tls-contract.php';
$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-226-splitdns-'.bin2hex(random_bytes(5));$base=$tmp.'/tenants';@mkdir($base,0750,true);
try {
    $system=dns43ResolverContextVanCli('system');
    $public=dns43ResolverContextVanCli('1.1.1.1',53);
    $doh=dns43ResolverContextVanCli('doh:cloudflare');
    c226dns($system===['mode'=>'system','endpoint'=>null,'port'=>53],'normale system-resolvermodus blijft de backward-compatible default');
    c226dns(($public['mode']??'')==='explicit'&&($public['endpoint']??'')==='1.1.1.1'&&($public['port']??0)===53,'split-DNS kan een expliciete publieke UDP-resolver vastleggen');
    c226dns($doh===['mode'=>'doh','endpoint'=>'cloudflare-dns.com','port'=>443],'split-DNS kan een vaste HTTPS-gevalideerde DoH-resolvercontext vastleggen');
    try{dns43ResolverContextVanCli('geen-ip',53);$bad=false;}catch(Throwable $e){$bad=str_contains($e->getMessage(),'geldig IP');}
    c226dns($bad,'ongeldige expliciete resolver faalt gesloten');
    try{dns43ResolverContext('doh','evil.example',443);$badDoh=false;}catch(Throwable $e){$badDoh=str_contains($e->getMessage(),'niet toegestaan');}
    c226dns($badDoh,'DoH staat geen arbitraire endpoint/SSRF-context toe');
    try{dns43ResolverContext('doh','cloudflare-dns.com',8443);$badDohPort=false;}catch(Throwable $e){$badDohPort=str_contains($e->getMessage(),'443');}
    c226dns($badDohPort,'DoH faalt gesloten buiten HTTPS-poort 443');

    $tenant=setup226dns($root,$base);
    c226dns($tenant!=='','split-DNS regressietenant en fase-4.3 plan worden opgebouwd');
    $planPad=$tenant.'/dns/dns-plan.json';$plan=json_decode((string)file_get_contents($planPad),true);
    $publicQuery=function(string $naam,array $resolver):array{
        if(($resolver['mode']??'')!=='explicit'||($resolver['endpoint']??'')!=='1.1.1.1')throw new RuntimeException('verkeerde resolvercontext');
        return [['type'=>'A','ip'=>'149.143.36.59','ttl'=>60]];
    };
    $internalQuery=function(string $naam,array $resolver):array{return [['type'=>'A','ip'=>'10.0.0.11','ttl'=>60]];};
    $systemQuery=function(string $naam,array $resolver):array{
        if(($resolver['mode']??'')!=='system')throw new RuntimeException('geen system resolver');
        return [['type'=>'A','ip'=>'149.143.36.59','ttl'=>60]];
    };
    $obsSystem=dns43Resolve('test.vps.holox.nl',$system,$systemQuery);
    c226dns(($obsSystem['a']??[])===['149.143.36.59'],'system-resolverpad blijft functioneel zonder expliciete endpointoverride');
    $obsPublic=dns43Resolve('test.vps.holox.nl',$public,$publicQuery);
    c226dns(($obsPublic['a']??[])===['149.143.36.59']&&(dns43Beoordeel($plan,$obsPublic)['ready']??false)===true,'expliciete publieke UDP-resolver blijft backward-compatible functioneel');
    $obsInternal=dns43Resolve('test.vps.holox.nl',$public,$internalQuery);
    c226dns((dns43Beoordeel($plan,$obsInternal)['ready']??true)===false,'publieke DNS-mismatch 10.0.0.11 versus 149.143.36.59 wordt geweigerd');

    $dohFetch=function(string $url,array $resolver):array{
        if(($resolver['mode']??'')!=='doh'||($resolver['endpoint']??'')!=='cloudflare-dns.com'||($resolver['port']??0)!==443)throw new RuntimeException('verkeerde DoH-context');
        if(!str_starts_with($url,'https://cloudflare-dns.com/dns-query?'))throw new RuntimeException('onveilig DoH-URL');
        return ['status'=>200,'body'=>json_encode(['Status'=>0,'Answer'=>[['name'=>'test.vps.holox.nl.','type'=>1,'TTL'=>300,'data'=>'149.143.36.59']]],JSON_THROW_ON_ERROR)];
    };
    $dohRecords=dns43DohQuery('test.vps.holox.nl',DNS_A,$doh,$dohFetch);
    $obsDoh=dns43Observatie($dohRecords);
    c226dns(($obsDoh['a']??[])===['149.143.36.59']&&($obsDoh['ttl_min']??0)===300,'DoH JSON-response wordt strict naar dezelfde DNS-observatie genormaliseerd');
    c226dns((dns43Beoordeel($plan,$obsDoh)['ready']??false)===true,'DoH publieke view kan het fase-4.3 plan ready verklaren');
    c226dns(dns43ResolverScope($doh)==='doh-public-resolver','DoH readiness heeft een eigen cryptografisch gebonden propagation-scope');

    $now=time();$ready=$tenant.'/dns/dns-readiness.json';
    $status=[
        'schema'=>1,'phase'=>'4.3-readiness','tenant_key'=>'splitdns','canonical_host'=>'test.vps.holox.nl','strategy'=>'direct','ready'=>true,
        'resolver_mode'=>'doh','resolver'=>$doh,'resolver_sha256'=>dns43ResolverContextHash($doh),
        'checked_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now),'expires_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now+900),
        'source'=>['dns_plan_file'=>$planPad,'dns_plan_sha256'=>hash_file('sha256',$planPad),'web_plan_sha256'=>$plan['source']['web_plan_sha256']],
        'propagation'=>['sample_count'=>3,'interval_seconds'=>2,'scope'=>'doh-public-resolver'],
        'observed'=>['owner'=>$obsDoh,'terminal'=>null],
    ];
    file_put_contents($ready,dns43Json($status));@chmod($ready,0640);
    $validated=dns43ReadinessLeesEnValideer($ready,$now);
    c226dns(($validated['resolver']['mode']??'')==='doh'&&($validated['resolver']['endpoint']??'')==='cloudflare-dns.com'&&hash_equals($validated['resolver_sha256'],$status['resolver_sha256']),'readiness bindt DoH-keuze en resolverhash aantoonbaar');

    $drift=$status;$drift['resolver']['endpoint']='evil.example';
    file_put_contents($ready,dns43Json($drift));
    try{dns43ReadinessLeesEnValideer($ready,$now);$driftRejected=false;}catch(Throwable $e){$driftRejected=true;}
    c226dns($driftRejected,'DoH resolver/context drift in readiness wordt fail-closed geweigerd');
    file_put_contents($ready,dns43Json($status));@chmod($ready,0640);

    $tlsCtx=tls44Context($ready,true);
    $used=null;
    $tlsQuery=function(string $naam,array $resolver)use(&$used):array{$used=$resolver;return[['type'=>'A','ip'=>'149.143.36.59','ttl'=>60]];};
    $fresh=dns43Resolve('test.vps.holox.nl',null,$tlsQuery);
    c226dns(($tlsCtx['ready']['resolver']['mode']??'')==='doh'&&($used['mode']??'')==='doh'&&($used['endpoint']??'')==='cloudflare-dns.com'&&(dns43Beoordeel($plan,$fresh)['ready']??false)===true,'TLS-context en verse pre-ACME DNS-hercontrole gebruiken exact dezelfde DoH-resolverview');
    try{dns43ResolverContextBind($system);$chainDrift=false;}catch(Throwable $e){$chainDrift=str_contains($e->getMessage(),'drift');}
    c226dns($chainDrift,'resolverwissel binnen dezelfde readiness/TLS-faseketen wordt geweigerd');

    $checker=(string)file_get_contents($root.'/bin/check-vps-dns.php');
    $contract=(string)file_get_contents($root.'/app/deployment/dns-contract.php');
    $tlsApply=(string)file_get_contents($root.'/bin/apply-vps-tls.php');
    c226dns(str_contains($checker,"'resolver::'")&&str_contains($checker,'doh:cloudflare')&&str_contains($checker,'dns43ResolverContextVanCli')&&str_contains($checker,'dns43ResolverScope'),'readiness-CLI exposeert DoH naast system/UDP zonder de default te breken');
    c226dns(str_contains($contract,'stream_socket_client')&&str_contains($contract,'dns43DohQuery')&&str_contains($contract,"'verify_peer_name' => true")&&str_contains($contract,'cloudflare-dns.com')&&str_contains($contract,'dns43ResolverContextBind'),'gedeeld DNS-contract implementeert UDP én allowlisted HTTPS-gevalideerde DoH-ketenbinding');
    c226dns(str_contains($tlsApply,'apply44DnsNu($ctx)')&&str_contains($tlsApply,'dns43Resolve('),'TLS/ACME-keten hercontroleert DNS via het gedeelde, gebonden resolvercontract');
} finally { rr226dns($tmp); }
echo "Issue #226 split-DNS resolvercontext: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
