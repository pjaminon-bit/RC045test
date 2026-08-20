<?php
// ============================================================
// Lidmaatschapstypen
// ============================================================
require_once dirname(__DIR__) . '/content/public-content-store.php';

function lidmaatschapBestand(): string
{
    $pad = publicContentPad('lidmaatschapstypen');
    if ($pad === null) throw new RuntimeException('Lidmaatschapstypen zijn niet geregistreerd in de tenantcontentstore.');
    return $pad;
}

function lidmaatschapLabels($waarde, string $fallback): array
{
    if (is_array($waarde)) {
        $labels=[];
        foreach (['nl','en','de'] as $taal) {
            $v=trim((string)($waarde[$taal]??''));
            if($v!=='')$labels[$taal]=$v;
        }
        if(!$labels)$labels=['nl'=>$fallback];
        if(empty($labels['nl']))$labels['nl']=reset($labels)?:$fallback;
        return $labels;
    }
    $tekst=trim((string)$waarde);
    return ['nl'=>$tekst!==''?$tekst:$fallback];
}

function lidmaatschapLees(): array
{
    $pad=lidmaatschapBestand();if(!is_file($pad))return['types'=>[]];
    $ruw=@file_get_contents($pad);$data=$ruw===false?null:json_decode($ruw,true);
    if(!is_array($data)||!isset($data['types'])||!is_array($data['types']))return['types'=>[]];
    $types=[];$ids=[];
    foreach($data['types'] as $type){
        if(!is_array($type))continue;
        $id=strtolower(trim((string)($type['id']??'')));$id=trim((string)preg_replace('/[^a-z0-9_-]+/','-',$id),'-');
        if($id===''||isset($ids[$id]))continue;$ids[$id]=true;
        $min=isset($type['leeftijd_min'])&&$type['leeftijd_min']!==''?max(0,(int)$type['leeftijd_min']):null;
        $max=isset($type['leeftijd_max'])&&$type['leeftijd_max']!==''?max(0,(int)$type['leeftijd_max']):null;
        if($min!==null&&$max!==null&&$min>$max)[$min,$max]=[$max,$min];
        $labels=lidmaatschapLabels($type['label']??($type['labels']??null),$id);
        $types[]=[
            'id'=>$id,'label'=>$labels['nl'],'labels'=>$labels,
            'actief'=>!array_key_exists('actief',$type)||!empty($type['actief']),
            'leeftijd_min'=>$min,'leeftijd_max'=>$max,
            'jaarbedrag'=>round(max(0,(float)($type['jaarbedrag']??0)),2),
            'inschrijfgeld'=>round(max(0,(float)($type['inschrijfgeld']??0)),2),
            'pro_rata'=>!array_key_exists('pro_rata',$type)||!empty($type['pro_rata']),
        ];
    }
    return ['types'=>$types,'updated'=>(string)($data['updated']??'')];
}

function lidmaatschapLabel(array $type,string $taal='nl'): string
{
    $labels=is_array($type['labels']??null)?$type['labels']:lidmaatschapLabels($type['label']??'',(string)($type['id']??''));
    return (string)($labels[$taal]??$labels['nl']??$type['id']??'');
}
function lidmaatschapTypeOpId(string $id): ?array{foreach(lidmaatschapLees()['types'] as $type)if(($type['id']??'')===$id)return$type;return null;}
function lidmaatschapTypeToegestaanVoorLeeftijd(array $type,?int $leeftijd): bool
{
    if(empty($type['actief']))return false;
    $min=$type['leeftijd_min']??null;$max=$type['leeftijd_max']??null;
    if($min===null&&$max===null)return true;if($leeftijd===null)return false;
    if($min!==null&&$leeftijd<(int)$min)return false;if($max!==null&&$leeftijd>(int)$max)return false;return true;
}
function lidmaatschapTypesVoorLeeftijd(?int $leeftijd): array{return array_values(array_filter(lidmaatschapLees()['types'],static fn($t)=>is_array($t)&&lidmaatschapTypeToegestaanVoorLeeftijd($t,$leeftijd)));}
function lidmaatschapTypeVoorLeeftijd(?int $leeftijd): ?array{foreach(lidmaatschapTypesVoorLeeftijd($leeftijd) as $type)return$type;return null;}
function lidmaatschapBedragVoorMaand(array $type,int $maand): float{$jaar=max(0,(float)($type['jaarbedrag']??0));if(empty($type['pro_rata']))return round($jaar,2);$maand=max(1,min(12,$maand));if($maand===12)return 0.0;return round($jaar*(12-$maand)/12,2);}

function lidmaatschapSchrijf(array $types): bool
{
    $opschonen=[];$ids=[];
    foreach($types as $type){
        if(!is_array($type))continue;$id=strtolower(trim((string)($type['id']??'')));$id=trim((string)preg_replace('/[^a-z0-9_-]+/','-',$id),'-');
        if($id===''||isset($ids[$id]))continue;$ids[$id]=true;
        $min=($type['leeftijd_min']??'')===''?null:max(0,(int)$type['leeftijd_min']);$max=($type['leeftijd_max']??'')===''?null:max(0,(int)$type['leeftijd_max']);if($min!==null&&$max!==null&&$min>$max)[$min,$max]=[$max,$min];
        $labels=lidmaatschapLabels($type['labels']??($type['label']??''),$id);
        $opschonen[]=['id'=>$id,'label'=>$labels,'actief'=>!empty($type['actief']),'leeftijd_min'=>$min,'leeftijd_max'=>$max,'jaarbedrag'=>round(max(0,(float)($type['jaarbedrag']??0)),2),'inschrijfgeld'=>round(max(0,(float)($type['inschrijfgeld']??0)),2),'pro_rata'=>!empty($type['pro_rata'])];
    }
    if(!$opschonen)return false;$data=['types'=>$opschonen,'updated'=>date('c')];$json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json===false)return false;$pad=lidmaatschapBestand();
    if(!publicContentIsTenantPad($pad)&&function_exists('maakDataBackup')){global $dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand;maakDataBackup($pad,$dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand);}
    try{$suffix=bin2hex(random_bytes(5));}catch(Throwable $e){$suffix=str_replace('.','',(string)microtime(true));}$tmp=$pad.'.tmp.'.$suffix;
    if(@file_put_contents($tmp,$json,LOCK_EX)===false)return false;if(publicContentIsTenantPad($pad))@chmod($tmp,0640);if(!@rename($tmp,$pad)){@unlink($tmp);return false;}if(publicContentIsTenantPad($pad))@chmod($pad,0640);return true;
}
