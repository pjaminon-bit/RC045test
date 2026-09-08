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
    c226dns($system===['mode'=>'system','endpoint'=>null,'port'=>53],'normale system-resolvermodus blijft de backward-compatible default');
    c226dns(($public['mode']??'')==='explicit'&&($public['endpoint']??'')==='1.1.1.1'&&($public['port']??0)===53,'split-DNS kan een expliciete publieke resolver vastleggen');
    try{dns43ResolverContextVanCli('geen-ip',53);$bad=false;}catch(Throwable $e){$bad=str_contains($e->getMessage(),'geldig IP');}
    c226dns($bad,'ongeldige expliciete resolver faalt gesloten');

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
    c226dns(($obsPublic['a']??[])===['149.143.36.59']&&(dns43Beoordeel($plan,$obsPublic)['ready']??false)===true,'expliciete publieke resolver kan de publieke DNS-view ready verklaren');
    $obsInternal=dns43Resolve('test.vps.holox.nl',$public,$internalQuery);
    c226dns((dns43Beoordeel($plan,$obsInternal)['ready']??true)===false,'publieke DNS-mismatch 10.0.0.11 versus 149.143.36.59 wordt geweigerd');

    $now=time();$ready=$tenant.'/dns/dns-readiness.json';
    $status=[
        'schema'=>1,'phase'=>'4.3-readiness','tenant_key'=>'splitdns','canonical_host'=>'test.vps.holox.nl','strategy'=>'direct','ready'=>true,
        'resolver_mode'=>'explicit','resolver'=>$public,'resolver_sha256'=>dns43ResolverContextHash($public),
        'checked_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now),'expires_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now+900),
        'source'=>['dns_plan_file'=>$planPad,'dns_plan_sha256'=>hash_file('sha256',$planPad),'web_plan_sha256'=>$plan['source']['web_plan_sha256']],
        'propagation'=>['sample_count'=>3,'interval_seconds'=>2,'scope'=>'explicit-public-resolver'],
        'observed'=>['owner'=>$obsPublic,'terminal'=>null],
    ];
    file_put_contents($ready,dns43Json($status));@chmod($ready,0640);
    $validated=dns43ReadinessLeesEnValideer($ready,$now);
    c226dns(($validated['resolver']['endpoint']??'')==='1.1.1.1'&&hash_equals($validated['resolver_sha256'],$status['resolver_sha256']),'readiness bindt resolverkeuze en resolverhash aantoonbaar');

    $drift=$status;$drift['resolver']['endpoint']='8.8.8.8';
    file_put_contents($ready,dns43Json($drift));
    try{dns43ReadinessLeesEnValideer($ready,$now);$driftRejected=false;}catch(Throwable $e){$driftRejected=str_contains($e->getMessage(),'inconsistent');}
    c226dns($driftRejected,'resolver/context drift in readiness wordt fail-closed geweigerd');
    file_put_contents($ready,dns43Json($status));@chmod($ready,0640);

    $tlsCtx=tls44Context($ready,true);
    $used=null;
    $tlsQuery=function(string $naam,array $resolver)use(&$used):array{$used=$resolver;return[['type'=>'A','ip'=>'149.143.36.59','ttl'=>60]];};
    $fresh=dns43Resolve('test.vps.holox.nl',null,$tlsQuery);
    c226dns(($tlsCtx['ready']['resolver']['endpoint']??'')==='1.1.1.1'&&($used['endpoint']??'')==='1.1.1.1'&&(dns43Beoordeel($plan,$fresh)['ready']??false)===true,'TLS-context en verse pre-ACME DNS-hercontrole gebruiken dezelfde publieke resolverview');
    try{dns43ResolverContextBind($system);$chainDrift=false;}catch(Throwable $e){$chainDrift=str_contains($e->getMessage(),'drift');}
    c226dns($chainDrift,'resolverwissel binnen dezelfde readiness/TLS-faseketen wordt geweigerd');

    $checker=(string)file_get_contents($root.'/bin/check-vps-dns.php');
    $contract=(string)file_get_contents($root.'/app/deployment/dns-contract.php');
    $tlsApply=(string)file_get_contents($root.'/bin/apply-vps-tls.php');
    c226dns(str_contains($checker,"'resolver::'")&&str_contains($checker,"'resolver-port::'")&&str_contains($checker,'dns43ResolverContextVanCli'),'readiness-CLI exposeert expliciete publieke resolver zonder system-default te breken');
    c226dns(str_contains($contract,'stream_socket_client')&&str_contains($contract,'dns43ResolverContextBind')&&str_contains($contract,'dns43ResolverContextActief'),'gedeeld DNS-contract implementeert expliciete resolver en ketenbinding zonder dig-afhankelijkheid');
    c226dns(str_contains($tlsApply,'apply44DnsNu($ctx)')&&str_contains($tlsApply,'dns43Resolve('),'TLS/ACME-keten hercontroleert DNS via het gedeelde, gebonden resolvercontract');
} finally { rr226dns($tmp); }
echo "Issue #226 split-DNS resolvercontext: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
