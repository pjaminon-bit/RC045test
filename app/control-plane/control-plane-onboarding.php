<?php
// Non-root onboarding request helper. DNS topology is public configuration and
// may enter the queue; passwords, hashes and provider credentials never do.

require_once __DIR__ . '/control-plane-admin-suite.php';

function cpOnboardIpCsv(string $raw,int $version): string
{
    $out=[];
    foreach(explode(',',$raw)as$item){
        $ip=trim($item);if($ip==='')continue;
        $flag=$version===4?FILTER_FLAG_IPV4:FILTER_FLAG_IPV6;
        if(filter_var($ip,FILTER_VALIDATE_IP,$flag)===false)throw new RuntimeException('Ongeldig IPv'.$version.'-adres in DNS-profiel.');
        if(!in_array($ip,$out,true))$out[]=$ip;
    }
    sort($out,SORT_STRING);return implode(',',$out);
}

function cpOnboardResumeRequest(array $input): string
{
    cpSuiteRequire('mutate');
    $tenant=trim((string)($input['tenant']??''));$row=cp51TenantUitSnapshot($tenant);$status=(string)($row['status']??'');
    if(!in_array($status,['setup_required','unmanaged'],true))throw new RuntimeException('Deze vereniging staat niet meer in een hervatbare onboardingstatus.');
    $strategy=strtolower(trim((string)($input['dns_strategy']??'')));
    if(!in_array($strategy,['direct','cname'],true))throw new RuntimeException('Kies direct of CNAME als DNS-strategie.');
    $ipv4=cpOnboardIpCsv((string)($input['ipv4']??''),4);$ipv6=cpOnboardIpCsv((string)($input['ipv6']??''),6);
    if($ipv4===''&&$ipv6==='')throw new RuntimeException('Vul minimaal één verwacht IPv4- of IPv6-adres in.');
    $cname=trim((string)($input['cname']??''));
    if($strategy==='direct'){
        if($cname!=='')throw new RuntimeException('Bij directe DNS hoort geen CNAME-doel.');
        $cname='';
    }else{
        $cname=cp57Host($cname);$host=(string)($row['canonical_host']??'');
        if($host!==''&&hash_equals(strtolower($host),strtolower($cname)))throw new RuntimeException('CNAME-doel moet verschillen van het tenantdomein.');
    }
    $profile=['strategy'=>$strategy,'ipv4'=>$ipv4,'ipv6'=>$ipv6,'cname'=>$cname];
    $existing=is_array($row['dns_profile']??null)?$row['dns_profile']:null;
    if($existing!==null){
        $canon=['strategy'=>(string)($existing['strategy']??''),'ipv4'=>implode(',',array_values((array)($existing['ipv4']??[]))),'ipv6'=>implode(',',array_values((array)($existing['ipv6']??[]))),'cname'=>(string)($existing['cname']??'')];
        if($canon!==$profile)throw new RuntimeException('Dit wijkt af van het al vastgelegde DNS-profiel. Wijzig een bestaand DNS-plan alleen na server-side controle.');
    }
    return cpSuiteQueue($tenant,'onboarding-resume',$profile);
}

function cpOnboardRecentResult(string $requestId,?string $operator=null): ?array
{
    if(preg_match('/^[0-9a-f]{32}$/D',$requestId)!==1)return null;$operator??=cp51Operator();
    if(!control58OperatorValid($operator))return null;$file=cp51Config()['results_dir'].'/'.$requestId.'.json';
    if(is_link($file)||!is_file($file))return null;$raw=@file_get_contents($file);$r=is_string($raw)?json_decode($raw,true):null;
    if(!is_array($r)||(int)($r['schema']??0)!==1||($r['phase']??'')!=='5.1-result'||!hash_equals($requestId,(string)($r['request_id']??''))||!hash_equals($operator,(string)($r['operator']??''))||($r['action']??'')!=='onboarding-resume'||!runtime41CanoniekeTenantKey((string)($r['tenant_key']??''))||!in_array((string)($r['result']??''),['ok','failed'],true)||!is_string($r['message']??null)||mb_strlen((string)$r['message'])>500||strtotime((string)($r['completed_at_utc']??''))===false)return null;
    return['request_id'=>$requestId,'tenant_key'=>(string)$r['tenant_key'],'result'=>(string)$r['result'],'message'=>(string)$r['message'],'completed_at_utc'=>(string)$r['completed_at_utc']];
}
