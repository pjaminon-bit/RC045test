<?php
// ============================================================
// Ledenlabels / segmenten
// ============================================================
require_once dirname(__DIR__) . '/storage/domein-repositories.php';
function labelsKort($v,int $max=120): string{$s=trim((string)$v);return function_exists('mb_substr')?mb_substr($s,0,$max,'UTF-8'):substr($s,0,$max);}
function labelsId($v): string{$s=strtolower(labelsKort($v,80));if(function_exists('iconv')){$x=@iconv('UTF-8','ASCII//TRANSLIT',$s);if($x!==false)$s=$x;}$s=preg_replace('/[^a-z0-9]+/','_',$s);return trim((string)$s,'_');}
function labelsNormaliseerDocument(array $doc): array{
 $labels=[];$ids=[];foreach((array)($doc['labels']??[]) as $l){if(!is_array($l))continue;$naam=labelsKort($l['naam']??'',60);$id=labelsId($l['id']??$naam);if($naam===''||$id===''||isset($ids[$id]))continue;$ids[$id]=true;$labels[]=['id'=>$id,'naam'=>$naam,'beschrijving'=>labelsKort($l['beschrijving']??'',300),'actief'=>($l['actief']??true)!==false];}
 $toew=[];foreach((array)($doc['toewijzingen']??[]) as $lidId=>$waarden){$lidId=labelsKort($lidId,80);if($lidId==='')continue;$set=[];foreach((array)$waarden as $id){$id=labelsId($id);if(isset($ids[$id]))$set[$id]=true;}if($set)$toew[$lidId]=array_keys($set);}
 return ['schema'=>1,'labels'=>$labels,'toewijzingen'=>$toew,'updated'=>(string)($doc['updated']??'')];
}
function labelsLeesDocument(): array{return labelsNormaliseerDocument(repoLedenlabelsLees());}
function labelsSchrijfDocument(array $doc,bool $backup=true): bool{return repoLedenlabelsSchrijf(labelsNormaliseerDocument($doc),$backup);}
function labelsMap(array $doc,bool $alle=true): array{$uit=[];foreach((array)($doc['labels']??[]) as $l)if(is_array($l)&&($alle||!empty($l['actief'])))$uit[(string)$l['id']]=$l;return$uit;}
function labelsVoorLid(array $doc,string $lidId): array{$map=labelsMap($doc,true);$uit=[];foreach((array)($doc['toewijzingen'][$lidId]??[]) as $id)if(isset($map[$id]))$uit[]=$map[$id];return$uit;}
function labelsZetVoorLid(array &$doc,string $lidId,array $ids): void{$map=labelsMap($doc,true);$set=[];foreach($ids as $id){$id=labelsId($id);if(isset($map[$id]))$set[$id]=true;}if($set)$doc['toewijzingen'][$lidId]=array_keys($set);else unset($doc['toewijzingen'][$lidId]);}
function labelsPurgeLid(array &$doc,string $lidId): void{unset($doc['toewijzingen'][$lidId]);}
