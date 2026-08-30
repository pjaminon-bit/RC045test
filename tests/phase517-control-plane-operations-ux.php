<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c517(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function rm517(string$p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$n){if($n==='.'||$n==='..')continue;rm517($p.'/'.$n);}@rmdir($p);}
$tmp=sys_get_temp_dir().'/rc045-phase517-'.bin2hex(random_bytes(5));$state=$tmp.'/state';$tenants=$tmp.'/tenants';foreach([$state.'/requests/pending',$state.'/requests/processing',$state.'/results',$state.'/sessions',$tenants]as$d)@mkdir($d,0770,true);
$cfg=['schema'=>1,'phase'=>'5.1-runtime','host'=>'beheer.example.test','app_root'=>$root,'tenants_root'=>$tenants,'runtime_user'=>get_current_user()?:'runner','pending_dir'=>$state.'/requests/pending','processing_dir'=>$state.'/requests/processing','results_dir'=>$state.'/results','sessions_dir'=>$state.'/sessions','snapshot_file'=>$state.'/snapshot.json','executor_lock'=>$tmp.'/executor.lock','audit_file'=>$tmp.'/audit.jsonl','lifecycle_apply'=>$root.'/bin/apply-vps-lifecycle.php'];file_put_contents($tmp.'/runtime.json',json_encode($cfg));file_put_contents($cfg['snapshot_file'],json_encode(['schema'=>1,'phase'=>'5.1-snapshot','generated_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'tenants'=>[]]));putenv('VP_CONTROL_PLANE_CONFIG='.$tmp.'/runtime.json');$_SERVER['REMOTE_USER']='operator.test';
try{
 require_once $root.'/app/control-plane/control-plane-runtime.php';require_once $root.'/app/control-plane/control-plane-observability.php';require_once $root.'/app/control-plane/control-plane-operations.php';
 $id=str_repeat('a',32);$other=str_repeat('b',32);$stale=str_repeat('c',32);
 $base=['schema'=>1,'phase'=>'5.1-request','tenant_key'=>'alpha','action'=>'suspend','operator'=>'operator.test','requested_at_utc'=>gmdate('Y-m-d\TH:i:s\Z'),'confirm'=>[]];
 file_put_contents($cfg['pending_dir'].'/'.$id.'.json',json_encode(array_merge($base,['request_id'=>$id])));
 file_put_contents($cfg['pending_dir'].'/'.$other.'.json',json_encode(array_merge($base,['request_id'=>$other,'operator'=>'andere.operator'])));
 file_put_contents($cfg['pending_dir'].'/'.$stale.'.json',json_encode(array_merge($base,['request_id'=>$stale,'tenant_key'=>'bravo','action'=>'export','requested_at_utc'=>gmdate('Y-m-d\TH:i:s\Z',time()-1000)])));
 file_put_contents($cfg['pending_dir'].'/'.str_repeat('d',32).'.json','{"schema":99}');
 $pending=cpOpsPendingRequests('operator.test',12);$ids=array_column($pending,'request_id');
 c517(count($pending)===2&&in_array($id,$ids,true)&&in_array($stale,$ids,true),'pending overzicht toont uitsluitend valide aanvragen van huidige operator');
 c517(!in_array($other,$ids,true),'pending aanvragen van andere operator lekken niet naar beheerconsole');
 $staleRow=null;foreach($pending as$r)if(($r['request_id']??'')===$stale)$staleRow=$r;c517(is_array($staleRow)&&($staleRow['stale']??false)===true&&($staleRow['age_seconds']??0)>=900,'oude pending aanvraag wordt zichtbaar als queue-aandachtspunt');
 c517(cpOpsPendingRequests('andere.operator',12)[0]['request_id']??null===$other,'operatorfilter werkt ook zelfstandig voor andere geldige operator');

 $active=['tenant_key'=>'alpha','status'=>'active','healthy'=>false,'transition'=>null,'last_export'=>null,'purge_not_before_utc'=>null];
 $suspended=['tenant_key'=>'bravo','status'=>'suspended','healthy'=>false,'transition'=>null,'last_export'=>null,'purge_not_before_utc'=>null];
 $stable=['tenant_key'=>'charlie','status'=>'active','healthy'=>true,'transition'=>null,'last_export'=>null,'purge_not_before_utc'=>null];
 c517(str_contains(implode(' ',cpOpsTenantAttention($active)),'monitoringstatus'),'ongezonde actieve tenant krijgt operationeel aandachtspunt');
 c517(str_contains(implode(' ',cpOpsTenantAttention($suspended)),'export'),'suspended tenant zonder export krijgt verwijderveiligheidswaarschuwing');
 c517(cpOpsTenantAttention($stable)===[],'gezonde stabiele tenant krijgt geen vals aandachtspunt');
 c517((cpOpsExportStatus(['last_export'=>['sha256'=>str_repeat('e',64),'created_at_utc'=>gmdate('Y-m-d\TH:i:s\Z')]])['available']??false)===true,'exportdiagnose accepteert alleen SHA-gebonden exportmetadata');

 $ops=(string)file_get_contents($root.'/app/control-plane/control-plane-operations.php');$ui=(string)file_get_contents($root.'/app/control-plane-web/index.php');
 c517(str_contains($ui,'data-confirm-suspend="1"')&&str_contains($ui,'window.confirm')&&str_contains($ui,'Weet je het zeker?'),'uitschakelen vereist expliciete browserbevestiging met duidelijke vraag');
 c517(str_contains($ui,'tijdelijke placeholder')&&str_contains($ui,'tenant-runtime/database worden gestopt'),'deactivatiebevestiging benoemt concrete gevolgen');
 c517(str_contains($ui,'value="attention">Aandacht nodig')&&str_contains($ui,"card.dataset.attention==='1'"),'beheerconsole heeft operationeel aandachtfilter');
 c517(str_contains($ui,'Openstaande aanvragen')&&str_contains($ui,'cpOpsPendingRequests($operator, 12)'),'beheerconsole toont operatorgebonden pending queue');
 c517(str_contains($ui,'Veilige export')&&str_contains($ui,'Transition')&&str_contains($ui,'Laatste status'),'tenantkaart toont lifecycle- en exportdiagnostiek');
 c517(str_contains($ui,'Tenantbeheer ↗')&&str_contains($ui,'Kopieer key')&&str_contains($ui,'Kopieer domein'),'tenantkaart bevat veilige operationele snelkoppelingen');
 c517(str_contains($ui,'@media(max-width:1080px){.system-grid,.meta{'),'responsive tenantdiagnostiek gebruikt geldige zelfstandige mediaregel');
 c517(!str_contains($ops,'processing_dir')&&!str_contains($ops,'proc_open(')&&!str_contains($ops,'shell_exec(')&&!str_contains($ops,'system(')&&!str_contains($ops,'exec('),'operationele webhelper opent geen root-only processingmap en start geen processen');
}finally{rm517($tmp);}echo"Phase 5.1.7 control-plane operations UX: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);