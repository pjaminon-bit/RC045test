<?php
// ============================================================
// Generiek groepenmodel voor commissies en werkgroepen
// ============================================================
require_once dirname(__DIR__) . '/storage/domein-repositories.php';

function groepenKort($v,int $max=120): string{$s=trim((string)$v);return function_exists('mb_substr')?mb_substr($s,0,$max,'UTF-8'):substr($s,0,$max);}
function groepenId($v): string{$s=strtolower(groepenKort($v,80));if(function_exists('iconv')){$x=@iconv('UTF-8','ASCII//TRANSLIT',$s);if($x!==false)$s=$x;}$s=preg_replace('/[^a-z0-9]+/','_',$s);return trim((string)$s,'_');}
function groepenNieuwId(string $type,string $naam,array $bestaand=[]): string{$basis=groepenId($type.'_'.$naam);if($basis==='')$basis=$type.'_groep';$id=$basis;$n=2;while(isset($bestaand[$id]))$id=$basis.'_'.$n++;return$id;}
function groepenStatussen(): array{return ['actief'=>'Actief','afgerond'=>'Afgerond','gearchiveerd'=>'Gearchiveerd'];}
function groepenTypes(): array{return ['commissie'=>'Commissie','werkgroep'=>'Werkgroep'];}

function groepenNormaliseerRollen(array $rollen): array{
 $uit=[];$gezien=[];foreach($rollen as $r){if(!is_array($r))continue;$id=groepenId($r['id']??'');$naam=groepenKort($r['naam']??'',60);if($id===''||$naam===''||isset($gezien[$id]))continue;$gezien[$id]=true;$uit[]=['id'=>$id,'naam'=>$naam,'actief'=>($r['actief']??true)!==false];}
 if(!$uit)$uit=groepenLeeg()['rollen'];return$uit;
}
function groepenNormaliseerLeden(array $leden,array $rolIds): array{
 $uit=[];$gezien=[];foreach($leden as $m){if(!is_array($m))continue;$lid=groepenKort($m['lid_id']??'',80);if($lid==='')continue;$rollen=[];foreach((array)($m['rollen']??[]) as $rol){$rol=groepenId($rol);if($rol!==''&&isset($rolIds[$rol]))$rollen[$rol]=true;}if(!$rollen&&isset($rolIds['lid']))$rollen['lid']=true;$key=$lid;if(isset($gezien[$key])){foreach(array_keys($rollen) as $rol)$uit[$gezien[$key]]['rollen'][]=$rol;$uit[$gezien[$key]]['rollen']=array_values(array_unique($uit[$gezien[$key]]['rollen']));continue;}$gezien[$key]=count($uit);$uit[]=['lid_id'=>$lid,'rollen'=>array_keys($rollen),'sinds'=>groepenKort($m['sinds']??'',10),'tot'=>groepenKort($m['tot']??'',10)];}
 return$uit;
}
function groepenNormaliseerDocument(array $doc): array{
 $rollen=groepenNormaliseerRollen((array)($doc['rollen']??[]));$rolIds=[];foreach($rollen as $r)$rolIds[$r['id']]=true;$groepen=[];$ids=[];foreach((array)($doc['groepen']??[]) as $g){if(!is_array($g))continue;$type=(string)($g['type']??'');if(!isset(groepenTypes()[$type]))continue;$naam=groepenKort($g['naam']??'',80);if($naam==='')continue;$id=groepenId($g['id']??'');if($id===''||isset($ids[$id]))$id=groepenNieuwId($type,$naam,$ids);$ids[$id]=true;$status=(string)($g['status']??'actief');if(!isset(groepenStatussen()[$status]))$status='actief';$groepen[]=['id'=>$id,'type'=>$type,'naam'=>$naam,'omschrijving'=>groepenKort($g['omschrijving']??'',1000),'doel'=>groepenKort($g['doel']??'',1000),'status'=>$status,'startdatum'=>groepenKort($g['startdatum']??'',10),'einddatum'=>groepenKort($g['einddatum']??'',10),'leden'=>groepenNormaliseerLeden((array)($g['leden']??[]),$rolIds),'aangemaakt'=>groepenKort($g['aangemaakt']??'',40),'gewijzigd'=>groepenKort($g['gewijzigd']??'',40)];}
 return ['schema'=>1,'rollen'=>$rollen,'groepen'=>$groepen,'updated'=>(string)($doc['updated']??'')];
}

function groepenLegacyDocument(): array{
 $doc=groepenLeeg();$leden=repoLedenLees();$legacy=(array)($leden['commissies']??[]);if(!$legacy)return groepenNormaliseerDocument($doc);$rolIds=[];foreach($doc['rollen'] as $r)$rolIds[$r['id']]=true;
 foreach($legacy as $sleutel=>$c){$naam=is_array($c)?groepenKort($c['naam']??'',80):groepenKort($c,80);if($naam==='')continue;$id='commissie_'.groepenId($sleutel);if($id==='commissie_')$id=groepenNieuwId('commissie',$naam,array_column($doc['groepen'],null,'id'));$assign=[];foreach((array)($leden['leden']??[]) as $lid){if(!is_array($lid))continue;$lidId=(string)($lid['id']??'');if($lidId===''||!in_array((string)$sleutel,(array)($lid['commissies']??[]),true))continue;$assign[$lidId]=['lid_id'=>$lidId,'rollen'=>['lid'],'sinds'=>'','tot'=>''];}
 if(is_array($c)){foreach([['hoofd_lid_id','trekker'],['bestuurslid_id','bestuurslid']] as [$veld,$rol]){$lidId=groepenKort($c[$veld]??'',80);if($lidId==='')continue;if(!isset($assign[$lidId]))$assign[$lidId]=['lid_id'=>$lidId,'rollen'=>[],'sinds'=>'','tot'=>''];$assign[$lidId]['rollen'][]=$rol;$assign[$lidId]['rollen']=array_values(array_unique($assign[$lidId]['rollen']));}}
 $doc['groepen'][]=['id'=>$id,'type'=>'commissie','naam'=>$naam,'omschrijving'=>'','doel'=>'','status'=>'actief','startdatum'=>'','einddatum'=>'','leden'=>array_values($assign),'aangemaakt'=>'','gewijzigd'=>''];}
 return groepenNormaliseerDocument($doc);
}
function groepenLeesDocument(): array{$doc=groepenNormaliseerDocument(repoGroepenLees());if(!$doc['groepen']){$legacy=groepenLegacyDocument();if($legacy['groepen'])return$legacy;}return$doc;}
function groepenSchrijfDocument(array $doc,bool $backup=true): bool{return repoGroepenSchrijf(groepenNormaliseerDocument($doc),$backup);}
function groepenVoorType(array $doc,string $type,bool $archiefMee=true): array{$uit=[];foreach((array)($doc['groepen']??[]) as $g){if(!is_array($g)||($g['type']??'')!==$type)continue;if(!$archiefMee&&($g['status']??'actief')==='gearchiveerd')continue;$uit[]=$g;}usort($uit,static fn($a,$b)=>strnatcasecmp((string)$a['naam'],(string)$b['naam']));return$uit;}
function groepenVoorLid(array $doc,string $lidId): array{$uit=[];foreach((array)($doc['groepen']??[]) as $g){if(!is_array($g)||($g['status']??'actief')==='gearchiveerd')continue;foreach((array)($g['leden']??[]) as $m)if(is_array($m)&&($m['lid_id']??'')===$lidId){$uit[]=$g;break;}}return$uit;}
function groepenRolMap(array $doc,bool $alle=true): array{$uit=[];foreach((array)($doc['rollen']??[]) as $r)if(is_array($r)&&($alle||!empty($r['actief'])))$uit[(string)$r['id']]=(string)$r['naam'];return$uit;}
function groepenPurgeLid(array &$doc,string $lidId): void{foreach((array)($doc['groepen']??[]) as $i=>$g){if(!is_array($g))continue;$doc['groepen'][$i]['leden']=array_values(array_filter((array)($g['leden']??[]),static fn($m)=>!is_array($m)||($m['lid_id']??'')!==$lidId));}}
