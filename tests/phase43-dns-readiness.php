<?php
$root = dirname(__DIR__);
$ok = 0; $fout = 0;
function check43(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo"OK: $label\n";}else{$fout++;fwrite(STDERR,"FOUT: $label\n");} }
function rr43(string $pad): void { if(is_link($pad)||is_file($pad)){@unlink($pad);return;} if(!is_dir($pad))return; foreach(scandir($pad)?:[] as $i){if($i==='.'||$i==='..')continue;rr43($pad.DIRECTORY_SEPARATOR.$i);}@rmdir($pad); }
function run43(array $args, ?string $stdin=null): array { $d=[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];$p=proc_open($args,$d,$pipes,null,null,['bypass_shell'=>true]);if(!is_resource($p))return[255,'proc_open mislukt'];if($stdin!==null)fwrite($pipes[0],$stdin);fclose($pipes[0]);$out=stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);return[proc_close($p),trim((string)$out."\n".(string)$err)]; }
function setup43(string $root,string $base,string $key,string $url,string $secret): string {
    $tenant=$base.'/'.$key;
    $steps=[
        [PHP_BINARY,$root.'/bin/provision-tenant.php','--key='.$key,'--name=DNS '.ucfirst($key),'--url='.$url,'--root='.$base,'--modules=website,ledenadministratie'],
    ];
    foreach($steps as $cmd){if(run43($cmd)[0]!==0)return '';}
    if(run43([PHP_BINARY,$root.'/bin/bootstrap-tenant-admin.php','--config='.$tenant.'/config.php','--password-stdin'],$secret."\n")[0]!==0)return '';
    if(run43([PHP_BINARY,$root.'/bin/prepare-vps-deployment.php','--config='.$tenant.'/config.php','--app-root='.$root])[0]!==0)return '';
    if(run43([PHP_BINARY,$root.'/bin/prepare-vps-runtime.php','--deployment='.$tenant.'/deployment.json'])[0]!==0)return '';
    if(run43([PHP_BINARY,$root.'/bin/prepare-vps-webserver.php','--runtime-plan='.$tenant.'/runtime/runtime-plan.json'])[0]!==0)return '';
    return $tenant;
}
function prepare43test(string $root,string $web,array $extra): array { return run43(array_merge([PHP_BINARY,$root.'/bin/prepare-vps-dns.php','--web-plan='.$web],$extra)); }

require_once $root.'/app/deployment/dns-contract.php';
$tmp=sys_get_temp_dir().DIRECTORY_SEPARATOR.'rc045-phase43-'.bin2hex(random_bytes(5));$base=$tmp.'/tenants';@mkdir($base,0750,true);
try {
    $a=setup43($root,$base,'noorderhaven','https://noorderhaven.example','Noorderhaven-DNS-Admin-2026!');
    $b=setup43($root,$base,'duinrand','https://duinrand.example','Duinrand-DNS-Admin-2026!');
    check43($a!==''&&$b!=='','twee fase-4.2 tenants zijn gereed als DNS-bron');
    $webA=$a.'/webserver/web-plan.json';$webB=$b.'/webserver/web-plan.json';

    [$dryCode,$dryOut]=prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10','--ipv6=2001:db8::10','--dry-run']);
    $dry=json_decode($dryOut,true);
    check43($dryCode===0&&is_array($dry)&&($dry['phase']??'')==='4.3'&&!is_dir($a.'/dns'),'dry-run levert DNS-plan zonder filesystemwrite');
    check43(($dry['strategy']??'')==='direct'&&($dry['canonical_host']??'')==='noorderhaven.example','direct plan bindt aan exacte canonical host');
    check43(($dry['rules']['exact_rrset_match']??false)===true&&($dry['rules']['unexpected_ipv6_forbidden']??false)===true,'DNS-plan eist exacte RRsets en verbiedt stale IPv6');
    check43(($dry['rules']['minimum_readiness_samples']??0)===3&&($dry['rules']['readiness_max_age_seconds']??0)===900,'readiness vereist drie samples en vervalt na 15 minuten');

    [$pa,$oa]=prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10','--ipv6=2001:0db8:0:0:0:0:0:10']);
    [$pb,$ob]=prepare43test($root,$webB,['--strategy=cname','--cname=edge.example.net.','--ipv4=198.51.100.20','--ipv6=2001:db8::20']);
    $planA=$a.'/dns/dns-plan.json';$planB=$b.'/dns/dns-plan.json';
    $jA=is_file($planA)?json_decode((string)file_get_contents($planA),true):null;$jB=is_file($planB)?json_decode((string)file_get_contents($planB),true):null;
    check43($pa===0&&$pb===0&&is_array($jA)&&is_array($jB),'direct en CNAME DNS-plannen worden geschreven');
    check43(($jA['expected']['owner']['a']??[])===['203.0.113.10']&&($jA['expected']['owner']['aaaa']??[])===['2001:db8::10'],'IP-adressen worden canoniek en deterministisch opgeslagen');
    check43(($jB['expected']['owner']['cname']??[])===['edge.example.net']&&($jB['expected']['owner']['a']??[])===[],'CNAME-profiel staat geen gemengde owner-adressen toe');
    check43(($jB['expected']['terminal']['a']??[])===['198.51.100.20']&&($jB['expected']['terminal']['aaaa']??[])===['2001:db8::20'],'CNAME-doel is exact aan verwachte VPS-adressen gebonden');
    check43(($jA['source']['web_plan_sha256']??'')===hash_file('sha256',$webA)&&($jB['source']['web_plan_sha256']??'')===hash_file('sha256',$webB),'DNS-plan bindt byte-exact aan fase-4.2 web-plan');
    $perm=fileperms($planA);check43($perm!==false&&(($perm&0777)===0640),'dns-plan.json krijgt server-only mode 0640');

    $goodA=['a'=>['203.0.113.10'],'aaaa'=>['2001:db8::10'],'cname'=>[],'ttl_min'=>60];
    check43((dns43Beoordeel($jA,$goodA)['ready']??false)===true,'exact direct A/AAAA-profiel is ready');
    $extraA=$goodA;$extraA['a'][]='203.0.113.11';check43((dns43Beoordeel($jA,$extraA)['ready']??true)===false,'extra oud IPv4-adres maakt direct profiel niet ready');
    $extra6=$goodA;$extra6['aaaa'][]='2001:db8::99';check43((dns43Beoordeel($jA,$extra6)['ready']??true)===false,'extra oud IPv6-adres maakt direct profiel niet ready');
    $mixed=$goodA;$mixed['cname']=['edge.example.net'];check43((dns43Beoordeel($jA,$mixed)['ready']??true)===false,'CNAME naast direct adressen wordt fail-closed geweigerd');
    $missing=$goodA;$missing['aaaa']=[];check43((dns43Beoordeel($jA,$missing)['ready']??true)===false,'ontbrekend verwacht IPv6-adres maakt profiel niet ready');

    $ownerB=['a'=>[],'aaaa'=>[],'cname'=>['edge.example.net'],'ttl_min'=>60];$termB=['a'=>['198.51.100.20'],'aaaa'=>['2001:db8::20'],'cname'=>[],'ttl_min'=>60];
    check43((dns43Beoordeel($jB,$ownerB,$termB)['ready']??false)===true,'exact CNAME plus terminale VPS-adressen is ready');
    $badOwner=$ownerB;$badOwner['a']=['198.51.100.20'];check43((dns43Beoordeel($jB,$badOwner,$termB)['ready']??true)===false,'CNAME-owner met direct A-record wordt geweigerd');
    $badTarget=$ownerB;$badTarget['cname']=['ander.example.net'];check43((dns43Beoordeel($jB,$badTarget,$termB)['ready']??true)===false,'verkeerd CNAME-doel wordt geweigerd');
    $badTerminal=$termB;$badTerminal['a']=['198.51.100.21'];check43((dns43Beoordeel($jB,$ownerB,$badTerminal)['ready']??true)===false,'CNAME naar verkeerd eindadres wordt geweigerd');
    $chain=$termB;$chain['cname']=['nog-een-hop.example.net'];check43((dns43Beoordeel($jB,$ownerB,$chain)['ready']??true)===false,'extra CNAME-hop buiten contract wordt geweigerd');

    check43(prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10'])[0]!==0,'afwijkend bestaand plan vereist expliciet --force');
    check43(prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10','--ipv6=2001:db8::10'])[0]===0,'identiek DNS-plan is idempotent');
    check43(prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10','--cname=edge.example.net','--force'])[0]!==0,'direct strategie weigert CNAME-argument');
    check43(prepare43test($root,$webA,['--strategy=cname','--cname=noorderhaven.example','--ipv4=203.0.113.10','--force'])[0]!==0,'CNAME-loop naar canonical host wordt geweigerd');
    check43(prepare43test($root,$webA,['--strategy=direct','--ipv4=999.1.1.1','--force'])[0]!==0,'ongeldig IPv4-adres faalt gesloten');
    check43(prepare43test($root,$webA,['--strategy=direct','--force'])[0]!==0,'DNS-plan zonder enig routeerdoel wordt geweigerd');

    $outside=$tmp.'/dns-buiten';[$co,$oo]=prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10','--output-dir='.$outside]);
    check43($co!==0&&!is_dir($outside)&&str_contains($oo,'binnen de tenantroot'),'DNS-output kan niet buiten eigen tenantroot worden geschreven');
    $canary=$tmp.'/dns-canary';file_put_contents($canary,'NIET WIJZIGEN');$link=$a.'/dns-link';
    if(function_exists('symlink')&&@symlink($canary,$link)){[$cs,$os]=prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10','--output-dir='.$link]);check43($cs!==0&&file_get_contents($canary)==='NIET WIJZIGEN','symlink als DNS-output wordt geweigerd zonder extern doel te wijzigen');@unlink($link);}else check43(true,'DNS output symlinktest overgeslagen');
    [$sec,$sout]=run43([PHP_BINARY,$root.'/bin/prepare-vps-dns.php','--web-plan='.$webA,'--strategy=direct','--ipv4=203.0.113.10','--token=verboden']);
    check43($sec!==0&&str_contains($sout,'Secrets'),'DNS-generator weigert secretachtige CLI-argumenten');

    // Herstel exact direct plan voor readiness-verificatie.
    check43(prepare43test($root,$webA,['--strategy=direct','--ipv4=203.0.113.10','--ipv6=2001:db8::10','--force'])[0]===0,'direct plan wordt gecontroleerd hersteld voor readiness');
    $jA=json_decode((string)file_get_contents($planA),true);$now=time();$ready=$a.'/dns/dns-readiness.json';
    $status=['schema'=>1,'phase'=>'4.3-readiness','tenant_key'=>'noorderhaven','canonical_host'=>'noorderhaven.example','strategy'=>'direct','ready'=>true,'resolver_mode'=>'system','checked_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now),'expires_at_utc'=>gmdate('Y-m-d\\TH:i:s\\Z',$now+900),'source'=>['dns_plan_file'=>$planA,'dns_plan_sha256'=>hash_file('sha256',$planA),'web_plan_sha256'=>$jA['source']['web_plan_sha256']],'propagation'=>['sample_count'=>3,'interval_seconds'=>2,'scope'=>'configured-system-resolver'],'observed'=>['owner'=>$goodA,'terminal'=>null]];
    file_put_contents($ready,dns43Json($status));@chmod($ready,0640);
    try{$rc=dns43ReadinessLeesEnValideer($ready,$now);$valid=true;}catch(Throwable $e){$valid=false;}
    check43($valid&&($rc['status']['ready']??false)===true,'verse live-system readiness met bronhash en observatie valideert');
    try{dns43ReadinessLeesEnValideer($ready,$now+901);$expired=false;}catch(Throwable $e){$expired=str_contains($e->getMessage(),'verlopen');}
    check43($expired,'readiness wordt na 15 minuten fail-closed geweigerd');
    $fake=$status;$fake['resolver_mode']='fixture';file_put_contents($ready,dns43Json($fake));
    try{dns43ReadinessLeesEnValideer($ready,$now);$fakeOk=false;}catch(Throwable $e){$fakeOk=str_contains($e->getMessage(),'systeemresolver');}
    check43($fakeOk,'niet-live resolverstatus kan TLS-readiness niet faken');
    $few=$status;$few['propagation']['sample_count']=2;file_put_contents($ready,dns43Json($few));
    try{dns43ReadinessLeesEnValideer($ready,$now);$fewOk=false;}catch(Throwable $e){$fewOk=str_contains($e->getMessage(),'propagation');}
    check43($fewOk,'te weinig propagation-samples worden geweigerd');
    $tamper=$status;$tamper['source']['dns_plan_sha256']=str_repeat('0',64);file_put_contents($ready,dns43Json($tamper));
    try{dns43ReadinessLeesEnValideer($ready,$now);$hashOk=false;}catch(Throwable $e){$hashOk=str_contains($e->getMessage(),'bron');}
    check43($hashOk,'readiness met verkeerde DNS-planhash wordt geweigerd');
    @unlink($ready);

    $webOrig=(string)file_get_contents($webA);file_put_contents($webA,$webOrig."\n");
    try{dns43PlanLeesEnValideer($planA);$stale=false;}catch(Throwable $e){$stale=str_contains($e->getMessage(),'gewijzigd');}
    check43($stale,'DNS-plan vervalt zodra fase-4.2 web-plan byte-inhoudelijk wijzigt');
    file_put_contents($webA,$webOrig);@chmod($webA,0640);

    $checker=(string)file_get_contents($root.'/bin/check-vps-dns.php');$contract=(string)file_get_contents($root.'/app/deployment/dns-contract.php');
    check43(str_contains($contract,'dns_get_record')&&str_contains($checker,"'resolver_mode' => 'system'"),'productiereadiness gebruikt live systeemresolver en markeert dat expliciet');
    check43(str_contains($checker,'check43ReadinessVerwijder($readyPad)'),'mislukte live check trekt bestaande readiness fail-closed in');
    check43(str_contains($checker,'dns43PlanLeesEnValideer($planPad)')&&str_contains($checker,'wijzigde tijdens'),'DNS-check hercontroleert broncontract na queries tegen TOCTOU');
    check43(!str_contains($checker,'curl ')&&!str_contains($checker,'nsupdate')&&!str_contains($checker,'cloudflare'),'4.3 schrijft bewust niet naar DNS-providers');
    check43(($jA['next']['tls_phase']??'')==='4.4'&&($jA['next']['fresh_ready_status_required_before_tls']??false)===true,'4.4 is contractueel geblokkeerd zonder verse DNS-readiness');

} finally { rr43($tmp); }
echo "Phase 4.3 DNS readiness: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
