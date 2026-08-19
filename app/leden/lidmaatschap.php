<?php
// ============================================================
// Lidmaatschapstypen
// ============================================================
// Publiek configureerbare categorieën zoals jeugd, senior, student, donateur
// of gezinslid. De RC045-defaults staan in data/lidmaatschapstypen.json.
// ============================================================

function lidmaatschapBestand(): string
{
    return dirname(__DIR__, 2) . '/data/lidmaatschapstypen.json';
}

function lidmaatschapLees(): array
{
    $pad = lidmaatschapBestand();
    if (!is_file($pad)) return ['types'=>[]];
    $ruw = @file_get_contents($pad);
    $data = $ruw === false ? null : json_decode($ruw, true);
    if (!is_array($data) || !isset($data['types']) || !is_array($data['types'])) return ['types'=>[]];
    $types = [];
    foreach ($data['types'] as $type) {
        if (!is_array($type)) continue;
        $id = strtolower(trim((string)($type['id'] ?? '')));
        $id = preg_replace('/[^a-z0-9_-]+/', '-', $id);
        $id = trim((string)$id, '-');
        if ($id === '') continue;
        $types[] = [
            'id'=>$id,
            'label'=>trim((string)($type['label'] ?? $id)),
            'actief'=>!array_key_exists('actief',$type) || !empty($type['actief']),
            'leeftijd_min'=>isset($type['leeftijd_min']) && $type['leeftijd_min'] !== '' ? max(0,(int)$type['leeftijd_min']) : null,
            'leeftijd_max'=>isset($type['leeftijd_max']) && $type['leeftijd_max'] !== '' ? max(0,(int)$type['leeftijd_max']) : null,
            'jaarbedrag'=>max(0,(float)($type['jaarbedrag'] ?? 0)),
            'inschrijfgeld'=>max(0,(float)($type['inschrijfgeld'] ?? 0)),
            'pro_rata'=>!array_key_exists('pro_rata',$type) || !empty($type['pro_rata']),
        ];
    }
    return ['types'=>$types,'updated'=>(string)($data['updated']??'')];
}

function lidmaatschapTypeOpId(string $id): ?array
{
    foreach (lidmaatschapLees()['types'] as $type) if (($type['id']??'') === $id) return $type;
    return null;
}

function lidmaatschapTypeVoorLeeftijd(?int $leeftijd): ?array
{
    if ($leeftijd === null) return null;
    foreach (lidmaatschapLees()['types'] as $type) {
        if (empty($type['actief'])) continue;
        $min = $type['leeftijd_min']; $max = $type['leeftijd_max'];
        if ($min !== null && $leeftijd < $min) continue;
        if ($max !== null && $leeftijd > $max) continue;
        return $type;
    }
    return null;
}

function lidmaatschapBedragVoorMaand(array $type, int $maand): float
{
    $jaar = max(0,(float)($type['jaarbedrag']??0));
    if (empty($type['pro_rata'])) return $jaar;
    $maand = max(1,min(12,$maand));
    if ($maand === 12) return 0.0;
    return round($jaar * (12 - $maand) / 12);
}

function lidmaatschapSchrijf(array $types): bool
{
    $opschonen = [];
    $ids = [];
    foreach ($types as $type) {
        if (!is_array($type)) continue;
        $id = strtolower(trim((string)($type['id']??'')));
        $id = trim((string)preg_replace('/[^a-z0-9_-]+/','-',$id),'-');
        if ($id==='' || isset($ids[$id])) continue;
        $ids[$id]=true;
        $min = ($type['leeftijd_min']??'') === '' ? null : max(0,(int)$type['leeftijd_min']);
        $max = ($type['leeftijd_max']??'') === '' ? null : max(0,(int)$type['leeftijd_max']);
        if ($min !== null && $max !== null && $min > $max) [$min,$max]=[$max,$min];
        $opschonen[]=[
            'id'=>$id,
            'label'=>trim((string)($type['label']??$id)) ?: $id,
            'actief'=>!empty($type['actief']),
            'leeftijd_min'=>$min,
            'leeftijd_max'=>$max,
            'jaarbedrag'=>round(max(0,(float)($type['jaarbedrag']??0)),2),
            'inschrijfgeld'=>round(max(0,(float)($type['inschrijfgeld']??0)),2),
            'pro_rata'=>!empty($type['pro_rata']),
        ];
    }
    if (!$opschonen) return false;
    $data=['types'=>$opschonen,'updated'=>date('c')];
    $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    if($json===false)return false;
    $pad=lidmaatschapBestand();
    if(function_exists('maakDataBackup')){
        global $dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand;
        maakDataBackup($pad,$dataBackupMap,$dataBackupBewaardagen,$dataBackupMaxPerBestand);
    }
    $tmp=$pad.'.tmp.'.bin2hex(random_bytes(4));
    if(file_put_contents($tmp,$json,LOCK_EX)===false)return false;
    if(!@rename($tmp,$pad)){@unlink($tmp);return false;}
    return true;
}
