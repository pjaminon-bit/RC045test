<?php
if(PHP_SAPI!=='cli'){http_response_code(403);exit('Alleen via CLI beschikbaar.');}
foreach($_SERVER['argv']??[] as $arg){if(preg_match('/^--(?:password|secret|token|key|dsn)(?:=|$)/i',(string)$arg)===1){fwrite(STDERR,"FOUT: secrets niet toegestaan.\n");exit(1);}}
$opt=getopt('',['expected-tenant:','repair','help']);
if(isset($opt['help'])){echo "Gebruik onder tenant-runtimeuser: php bin/data-integriteit.php --expected-tenant=<key> [--repair]\nStandaard is de controle read-only; --repair verwijdert uitsluitend ondubbelzinnig dangling IDs uit finding #152.\n";exit(0);}
$verwacht=trim((string)($opt['expected-tenant']??''));
if($verwacht===''||preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/D',$verwacht)!==1){fwrite(STDERR,"FOUT: geldige --expected-tenant is verplicht.\n");exit(1);}
try{
 $config=require dirname(__DIR__).'/site-config.php';
 if(!is_array($config))throw new RuntimeException('site-config levert geen array.');
 $tenant=(string)($config['vereniging']['sleutel']??'');
 if(!hash_equals($verwacht,$tenant))throw new RuntimeException('tenant-key wijkt af.');
 require_once dirname(__DIR__).'/app/data-integriteit.php';
 if(isset($opt['repair'])){
  $resultaat=dataIntegriteitHerstelDangling();
  $voor=(int)($resultaat['voor']['totaal']??0);$na=(int)($resultaat['na']['totaal']??0);
  $tv=(int)($resultaat['hersteld']['taak_vergaderingen']??0);$gr=(int)($resultaat['hersteld']['groep_relaties']??0);
  echo "REPAIR OK tenant={$tenant} voor={$voor} taak_vergadering={$tv} groep_relaties={$gr} na={$na}\n";
  exit($na===0?0:3);
 }
 $rapport=dataIntegriteitDetecteer();$a=(array)($rapport['aantallen']??[]);$totaal=(int)($rapport['totaal']??0);
 echo 'INTEGRITEIT tenant='.$tenant.' totaal='.$totaal
  .' taak_vergadering='.(int)($a['taak_vergaderingen']??0)
  .' groep_taak='.(int)($a['groep_taken']??0)
  .' groep_vergadering='.(int)($a['groep_vergaderingen']??0)
  .' groep_evenement='.(int)($a['groep_evenementen']??0)."\n";
 exit($totaal===0?0:2);
}catch(Throwable $e){fwrite(STDERR,'FOUT: data-integriteitsactie mislukt voor verwachte tenant: '.get_class($e)."\n");exit(4);}
