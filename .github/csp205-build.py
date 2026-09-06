from pathlib import Path
import re, hashlib

root = Path('.').resolve()
source_paths = [
'index.php','fotoboek.php','aanmelden.php','bedankt.php','media.php',
'beheer/actueel.php','beheer/nieuws.php','beheer/vergaderingen.php','beheer/groep-relaties.php','beheer/leden.php','beheer/contact.php','beheer/agenda.php','beheer/data-integriteit.php','beheer/index.php','beheer/contributies.php','beheer/lidmaatschap.php','beheer/backups.php','beheer/evenementen.php','beheer/fotoboek.php','beheer/gebruikers.php','beheer/taken.php','beheer/contactberichten.php','beheer/faq.php','beheer/sponsors.php','beheer/ledenlabels.php','beheer/changelog.php','beheer/instellingen.php','beheer/bedankt.php','beheer/websitebeelden.php','beheer/leden-import.php','beheer/groepsrollen.php','beheer/media.php','beheer/operationele-taken.php','beheer/aanmeldingen.php','beheer/logboek.php',
'leden/index.php','app/control-plane-web/index.php','app/control-plane-web/onboarding.php','app/content/content-beheer.php','app/content/content-renderer.php','app/beheer/groepen-beheer.php'
]

def normalized_css(body: str) -> str:
    return body.lstrip('\n').rstrip() + '\n'

def asset_location(source_path: str, block_idx: int, final_css: str):
    sp = Path(source_path)
    digest = hashlib.sha256(final_css.encode()).hexdigest()[:12]
    stem = sp.stem.replace('_','-')
    suffix = f'-{block_idx}' if block_idx > 1 else ''
    name = f'csp205-{stem}{suffix}-{digest}.css'
    if source_path.startswith('app/control-plane-web/'):
        target = Path('app/control-plane-web') / name; href = name
    elif source_path == 'app/content/content-beheer.php':
        target = Path('beheer') / name; href = name
    elif source_path == 'app/beheer/groepen-beheer.php':
        target = Path('beheer') / name; href = name
    else:
        target = sp.parent / name; href = name
    return target, href

created_assets=[]
for path in source_paths:
    p=root/path
    if not p.is_file(): raise SystemExit(f'missing source: {path}')
    txt=p.read_text()
    matches=list(re.finditer(r'<style\b[^>]*>(.*?)</style>', txt, re.S|re.I))
    if not matches: raise SystemExit(f'expected static style block missing: {path}')
    reps=[]
    if path == 'app/content/content-renderer.php':
        for idx,m in enumerate(matches,1):
            mm=re.match(r'\s*<\?=\s*\$heroCss\s*\?>\s*', m.group(1))
            if not mm: raise SystemExit(f'unexpected renderer style contract: block {idx}')
            final_css=normalized_css(m.group(1)[mm.end():])
            digest=hashlib.sha256(final_css.encode()).hexdigest()[:12]
            name=f'csp205-content-renderer-{idx}-{digest}.css'
            target=Path(name); (root/target).write_text(final_css)
            reps.append((m.start(),m.end(),f'<style id="content-page-hero-{idx}"><?= $heroCss ?></style>\n<link rel="stylesheet" href="{name}">'))
            created_assets.append(str(target))
    else:
        for idx,m in enumerate(matches,1):
            final_css=normalized_css(m.group(1))
            target,href=asset_location(path,idx,final_css)
            (root/target).parent.mkdir(parents=True,exist_ok=True); (root/target).write_text(final_css)
            reps.append((m.start(),m.end(),f'<link rel="stylesheet" href="{href}">'))
            created_assets.append(str(target))
    for st,en,rep in reversed(reps): txt=txt[:st]+rep+txt[en:]
    p.write_text(txt)

test = r'''<?php
$root = dirname(__DIR__); $ok = 0; $fout = 0;
function s205(bool $c,string $l):void{global $ok,$fout;if($c){$ok++;echo "OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}
function s205GitShow(string $r,string $p):string{$raw=shell_exec('git -C '.escapeshellarg($r).' show '.escapeshellarg('HEAD:'.$p));if(!is_string($raw))throw new RuntimeException('Git-bron kon niet worden gelezen: '.$p);return $raw;}
$tracked=[];exec('git -C '.escapeshellarg($root).' ls-files',$tracked,$rc);if($rc!==0||$tracked===[]){fwrite(STDERR,"FOUT: tracked bronlijst kon niet worden gelezen.\n");exit(1);}
$allow=['app/content/content-pagina-runtime.php'=>1,'app/content/content-renderer.php'=>2,'app/core/site.php'=>4,'app/core/tenant-public-runtime.php'=>1,'bin/apply-vps-lifecycle.php'=>1];$counts=[];$bron=[];
foreach($tracked as $p){$p=str_replace('\\','/',trim($p));if($p===''||str_starts_with($p,'tests/')||str_starts_with($p,'vendor/'))continue;$e=strtolower(pathinfo($p,PATHINFO_EXTENSION));if(!in_array($e,['php','html'],true))continue;$raw=s205GitShow($root,$p);$bron[$p]=$raw;preg_match_all('/<style\b[^>]*>.*?<\/style>/is',$raw,$m);if(($m[0]??[])!==[])$counts[$p]=count($m[0]);}
ksort($counts);s205($counts===$allow,'alleen expliciet dynamische/deferred styleblokken blijven in tracked bron');s205(array_sum($counts)===9,'tracked bron bevat exact 9 resterende dynamische/deferred styleblokken');$renderer=$bron['app/content/content-renderer.php']??'';s205(substr_count($renderer,'<?= $heroCss ?>')>=2,'content-renderer behoudt uitsluitend dynamische hero-CSS inline');s205(str_contains($renderer,'<style id="content-page-hero-1"><?= $heroCss ?></style>')&&str_contains($renderer,'<style id="content-page-hero-2"><?= $heroCss ?></style>'),'content-renderer inline styles zijn beperkt tot benoemde hero-contracten');
$assets=[];foreach($tracked as $p){$p=str_replace('\\','/',trim($p));if(preg_match('#(?:^|/)csp205-[a-z0-9-]+-[0-9a-f]{12}\.css$#D',$p)===1)$assets[]=$p;}sort($assets);s205(count($assets)===43,'43 immutable content-gehashte #205 CSS-assets zijn tracked');$all=implode("\n",$bron);$missing=[];$bad=[];foreach($assets as $a){$n=basename($a);if(!str_contains($all,'href="'.$n.'"'))$missing[]=$a;$css=s205GitShow($root,$a);if(preg_match('/-([0-9a-f]{12})\.css$/D',$n,$hm)!==1||!hash_equals($hm[1],substr(hash('sha256',$css),0,12)))$bad[]=$a;}s205($missing===[],'iedere #205 CSS-asset wordt door tracked HTML/PHP-bron gerefereerd');s205($bad===[],'iedere #205 CSS-bestandsnaam is exact aan de assetinhoud gebonden');$uv=[];foreach($bron as $p=>$raw){if(preg_match_all('/href="([^"]*csp205-[^"]+\.css)"/i',$raw,$m)!==false)foreach($m[1]??[] as $h)if(preg_match('/csp205-[a-z0-9-]+-[0-9a-f]{12}\.css$/D',$h)!==1)$uv[]=$p.' -> '.$h;}s205($uv===[],'alle #205 stylesheetreferenties zijn content-gehasht/versioned');echo "Security #205 static style assets: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);
'''
(root/'tests').mkdir(parents=True,exist_ok=True);(root/'tests/security-205-static-style-assets.php').write_text(test)
print(f'#205 generated {len(created_assets)} CSS assets from {len(source_paths)} source files')
