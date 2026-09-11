<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function c269(bool $conditie, string $label): void { global $ok,$fout; if($conditie){$ok++;echo "OK: {$label}\n";return;} $fout++;fwrite(STDERR,"FOUT: {$label}\n"); }
function wis269(string $pad): void { if(is_link($pad)||is_file($pad)){@unlink($pad);return;} if(!is_dir($pad))return; foreach(scandir($pad)?:[] as $item){if($item==='.'||$item==='..')continue;wis269($pad.DIRECTORY_SEPARATOR.$item);}@rmdir($pad); }

$tmp = sys_get_temp_dir().'/security269-'.bin2hex(random_bytes(5));
$private = $tmp.'/private';
$notifications = $private.'/notifications';
$configPad = $tmp.'/config.php';
@mkdir($private.'/collections',0750,true);
@mkdir($notifications,0750,true);
$credentials = $notifications.'/smtp-credentials.json';
file_put_contents($credentials,json_encode(['username'=>'cadence-user','password'=>'dummy-secret'],JSON_THROW_ON_ERROR));
@chmod($credentials,0600);
$config = [
    'vereniging'=>[
        'sleutel'=>'security269',
        'naam'=>'Security 269',
        'volledige_naam'=>'Security 269',
        'site_url'=>'https://security269.example.invalid',
        'timezone'=>'Europe/Amsterdam',
    ],
    'modules'=>['website'=>true,'aanmelden'=>true],
    'opslag'=>[
        'private_driver'=>'json',
        'private_root'=>$private,
        'pdo'=>['dsn'=>'','user'=>'','password'=>''],
    ],
    'notificaties'=>[
        'enabled'=>true,
        'required'=>true,
        'provider'=>'smtp',
        'from'=>'no-reply@security269.example.invalid',
        'recipients'=>[
            'contact.received'=>['board@security269.example.invalid'],
            'membership.received'=>['members@security269.example.invalid'],
        ],
        'stale_after_seconds'=>900,
        'smtp'=>[
            'host'=>'smtp.security269.example.invalid',
            'port'=>587,
            'security'=>'starttls',
            'credentials_file'=>$credentials,
        ],
    ],
];
file_put_contents($configPad,"<?php\nreturn ".var_export($config,true).";\n");
putenv('VERENIGING_REQUIRE_TENANT_CONFIG=1');
putenv('VERENIGING_CONFIG_FILE='.$configPad);
putenv('VERENIGING_PRIVATE_ROOT='.$private);

try {
    require_once $root.'/app/notifications/tenant-notifications.php';
    $now = 2200000000;
    c269(tenantNotificationDispatchIntervalSeconds() === 60,'tenantbrede dispatchcadans is exact één minuut');
    c269(tenantNotificationEnqueue('contact.received','cadence-contact',$now-10),'eerste due notification-intent gequeued');
    c269(tenantNotificationEnqueue('membership.received','cadence-membership',$now-10),'tweede due notification-intent gequeued');

    $calls = 0;
    $transport = static function () use (&$calls): array { $calls++; return ['ok'=>true]; };
    $eerste = tenantNotificationDispatchOne($config,$transport,$now);
    c269(($eerste['attempted']??false)===true&&($eerste['ok']??false)===true&&$calls===1,'eerste health/scheduler-trigger claimt exact één due item');

    $outbox = tenantNotificationOutboxLees();
    c269(tenantNotificationTimestamp((string)($outbox['dispatch_not_before']??'')) === $now+60,'claim legt tenantbrede volgende dispatchgrens atomisch vast');
    c269(count(array_filter($outbox['items'],static fn($item)=>is_array($item)&&($item['status']??'')==='pending'))===1,'na eerste delivery blijft tweede intent due/pending');

    foreach ([$now,$now+1,$now+30,$now+59] as $probeNow) {
        $result = tenantNotificationDispatchOne($config,$transport,$probeNow);
        c269(($result['attempted']??true)===false&&($result['state']??'')==='idle','herhaalde publieke trigger vóór cadencegrens voert geen extra SMTP-poging uit op t='.$probeNow);
    }
    c269($calls===1,'meerdere publieke triggers binnen schedulerinterval blijven samen op één transportpoging');

    $tweede = tenantNotificationDispatchOne($config,$transport,$now+60);
    c269(($tweede['attempted']??false)===true&&($tweede['ok']??false)===true&&$calls===2,'eerstvolgende interval mag exact één volgend due item dispatchen');
    $na = tenantNotificationOutboxLees();
    c269(count(array_filter($na['items'],static fn($item)=>is_array($item)&&($item['status']??'')==='pending'))===0,'beide intents zijn na twee afzonderlijke intervallen delivered');
    c269(tenantNotificationTimestamp((string)($na['dispatch_not_before']??'')) === $now+120,'tweede claim schuift globale cadencegrens opnieuw één minuut op');

    $health = (string)file_get_contents($root.'/healthz.php');
    $monitoring = (string)file_get_contents($root.'/app/deployment/monitoring-contract.php');
    c269(str_contains($health,'tenantNotificationDispatchOne($config)'),'bestaande root-owned healthtimer blijft delivery onder tenant-FPM-identiteit triggeren');
    c269(str_contains($health,'tenantNotificationBacklogStatus($config)'),'health/readiness blijft stale notification backlog bewaken');
    c269(str_contains($monitoring,"'interval_seconds'=>60"),'dispatchcadans blijft gelijk aan expliciete health-schedulerfrequentie');

    $legacy = $na;
    unset($legacy['dispatch_not_before']);
    c269(tenantNotificationOutboxSchrijf($legacy),'legacy schema-1 outbox zonder cadenceveld blijft schrijfbaar');
    $legacyRead = tenantNotificationOutboxLees();
    c269(array_key_exists('dispatch_not_before',$legacyRead)&&$legacyRead['dispatch_not_before']===null,'legacy outbox wordt backward-compatible naar lege cadence gemigreerd');
} finally {
    putenv('VERENIGING_REQUIRE_TENANT_CONFIG');
    putenv('VERENIGING_CONFIG_FILE');
    putenv('VERENIGING_PRIVATE_ROOT');
    wis269($tmp);
}

echo "Security #269 notification dispatch cadence: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);
