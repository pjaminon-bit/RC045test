from pathlib import Path
import re, hashlib, subprocess
root=Path.cwd()

asset_map={
'aanmelden.php':'csp205-aanmelden-887c147a5647.css','bedankt.php':'csp205-bedankt-661944f142d1.css',
'beheer/aanmeldingen.php':'beheer/csp205-aanmeldingen-8f5cbc55f7ee.css','beheer/contributies.php':'beheer/csp205-contributies-3f54f40605ae.css',
'beheer/evenementen.php':'beheer/csp205-evenementen-39cf7deb7428.css','beheer/fotoboek.php':'beheer/csp205-fotoboek-2773df84ef0a.css',
'beheer/gebruikers.php':'beheer/csp205-gebruikers-77f6516e9115.css','beheer/leden.php':'beheer/csp205-leden-c0d477a73efa.css',
'beheer/vergaderingen.php':'beheer/csp205-vergaderingen-6fb4039a76cc.css','index.php':'csp205-index-6c81cdcc5da6.css',
'leden/index.php':'leden/csp205-index-2-371ac002dea1.css','media.php':'csp205-media-299a8782f402.css',
'app/control-plane-web/index.php':'app/control-plane-web/csp205-index-d89da7f1f8bf.css'}

def cls_for(s): return 'csp-i-'+hashlib.sha256(s.encode()).hexdigest()[:10]
def rewrite_attrs(rel,cssrel,skip=None):
 p=root/rel; cp=root/cssrel; t=p.read_text(); rules=[]
 pat=re.compile(r'<([A-Za-z][^<>]*?)\sstyle\s*=\s*(["\'])(.*?)\2([^<>]*?)>',re.I|re.S)
 def repl(m):
  s=m.group(3).strip()
  if (skip and skip(s)) or '<?' in s:return m.group(0)
  c=cls_for(s); whole=m.group(1)+m.group(4); cm=re.search(r'\bclass\s*=\s*(["\'])(.*?)\1',whole,re.I|re.S)
  if cm: whole=whole.replace(cm.group(0),f'class={cm.group(1)}{cm.group(2)} {c}{cm.group(1)}',1)
  else: whole+=f' class="{c}"'
  rules.append((c,s.rstrip(';'))); return '<'+whole+'>'
 t=pat.sub(repl,t); p.write_text(t)
 if rules:
  css=cp.read_text().rstrip()+"\n"; seen=set()
  for c,s in rules:
   if c not in seen: css+=f'.{c}{{{s}}}\n'; seen.add(c)
  cp.write_text(css)

for rel,css in asset_map.items(): rewrite_attrs(rel,css,(lambda s:'<?=$ob' in s) if rel=='app/control-plane-web/index.php' else None)
rewrite_attrs('app/beheer/lid-groepen-inline.php','beheer/ui-2026.css')

for rel in ['app/control-plane-web/index.php','app/control-plane-web/onboarding.php']:
 p=root/rel;t=p.read_text().replace('<span style="width:<?=$ob[\'percent\']?>%"></span>','<span class="csp-progress-<?=$ob[\'percent\']?>"></span>');p.write_text(t)
progress=''.join(f'.csp-progress-{i}{{width:{i}%}}\n' for i in range(101))
for rel in ['app/control-plane-web/csp205-index-d89da7f1f8bf.css','app/control-plane-web/csp205-onboarding-3573109525fd.css']:
 p=root/rel;p.write_text(p.read_text().rstrip()+'\n'+progress)

p=root/'beheer/instellingen.php';t=p.read_text();attr=' style="--pv-primary:<?=insEsc($kleuren[\'primary\'])?>;--pv-accent:<?=insEsc($kleuren[\'accent\'])?>;--pv-dark:<?=insEsc($kleuren[\'dark\'])?>;--pv-text:<?=insEsc($kleuren[\'text\'])?>;--pv-bg:<?=insEsc($kleuren[\'background\'])?>;--pv-nav:<?=insEsc($kleuren[\'nav_background\'])?>;--pv-navtext:<?=insEsc($kleuren[\'nav_text\'])?>"'
if attr not in t: raise SystemExit('settings preview style attr missing')
t=t.replace(attr,''); needle='</head><body>';style='''<style nonce="<?=siteCspHtmlWaarde(siteCspNonce())?>" id="beheer-preview-theme">#preview{--pv-primary:<?=insEsc($kleuren['primary'])?>;--pv-accent:<?=insEsc($kleuren['accent'])?>;--pv-dark:<?=insEsc($kleuren['dark'])?>;--pv-text:<?=insEsc($kleuren['text'])?>;--pv-bg:<?=insEsc($kleuren['background'])?>;--pv-nav:<?=insEsc($kleuren['nav_background'])?>;--pv-navtext:<?=insEsc($kleuren['nav_text'])?>}</style>'''
if needle not in t: raise SystemExit('settings head needle missing')
p.write_text(t.replace(needle,style+needle,1))

p=root/'app/content/tenant-homepage.php';t=p.read_text();old='<!doctype html><html lang="nl"><body style="margin:0;display:grid;place-items:center;height:100%;font-family:sans-serif;background:#eef3ef;color:#526258">Locatiekaart nog niet ingesteld</body></html>';new='<!doctype html><html lang="nl"><head><link rel="stylesheet" href="/iframe-placeholder.css"></head><body>Locatiekaart nog niet ingesteld</body></html>'
if old not in t: raise SystemExit('iframe style source missing')
p.write_text(t.replace(old,new));(root/'iframe-placeholder.css').write_text('html,body{margin:0;width:100%;height:100%}body{display:grid;place-items:center;font-family:sans-serif;background:#eef3ef;color:#526258}\n')
p=root/'app/core/tenant-public-media.php';t=p.read_text();t,n=re.subn(r'\s*\$node->setAttribute\(\'style\',\s*"background-image:url\(\'"\s*\.\s*htmlspecialchars\(\$hero, ENT_QUOTES, \'UTF-8\'\)\s*\.\s*"\'\)"\);','',t);p.write_text(t)
if n!=1: raise SystemExit('tenant media style sink count != 1')

p=root/'app/content/content-renderer.php';t=p.read_text().replace('<style id="content-page-hero-1">','<style nonce="<?=siteCspHtmlWaarde(siteCspNonce())?>" id="content-page-hero-1">').replace('<style id="content-page-hero-2">','<style nonce="<?=siteCspHtmlWaarde(siteCspNonce())?>" id="content-page-hero-2">');p.write_text(t)
p=root/'app/content/content-pagina-runtime.php';t=p.read_text().replace("$style = '<style id=\"content-page-hero\">' . $heroCss . '</style>';", "$style = '<style nonce=\"' . siteCspHtmlWaarde(siteCspNonce()) . '\" id=\"content-page-hero\">' . $heroCss . '</style>';" );p.write_text(t)
p=root/'app/core/tenant-public-runtime.php';t=p.read_text().replace("return '<style id=\"tenant-product-theme\">:root{'", "return '<style nonce=\"' . siteCspHtmlWaarde(siteCspNonce()) . '\" id=\"tenant-product-theme\">:root{'",1);p.write_text(t)
p=root/'app/core/site.php';t=p.read_text().replace("echo '<style>body{", "echo '<style nonce=\"' . siteCspHtmlWaarde(siteCspNonce()) . '\">body{").replace("return '<style id=\"site-module-visibility\">'", "return '<style nonce=\"' . siteCspHtmlWaarde(siteCspNonce()) . '\" id=\"site-module-visibility\">'").replace("return '<style id=\"site-beheer-module-visibility\">'", "return '<style nonce=\"' . siteCspHtmlWaarde(siteCspNonce()) . '\" id=\"site-beheer-module-visibility\">'").replace('return "<style id=\\"site-theme\\">\\n  :root {\\n"','return "<style nonce=\\\"" . siteCspHtmlWaarde(siteCspNonce()) . "\\\" id=\\"site-theme\\">\\n  :root {\\n"');p.write_text(t)

p=root/'bin/apply-vps-lifecycle.php';t=p.read_text();m=re.search(r"function apply48SuspendedHtml\(\):string\n\{\n    return '.*?';\n\}",t,re.S)
if not m: raise SystemExit('suspended html function missing')
rep='''function apply48SuspendedCss():string\n{\n    return <<<'CSS'\nhtml{color-scheme:light}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f4f6f4;color:#17211b;font:16px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.card{width:min(680px,100%);background:#fff;border:1px solid #dce3de;border-radius:18px;padding:clamp(28px,6vw,54px);box-shadow:0 14px 44px rgba(18,38,25,.08);text-align:center}.mark{width:52px;height:52px;margin:0 auto 20px;border-radius:50%;display:grid;place-items:center;background:#eef3ef;font-size:24px}h1{margin:0 0 12px;font-size:clamp(1.55rem,4vw,2.25rem);line-height:1.18}p{margin:0 auto;max-width:520px;color:#647169}.foot{margin-top:28px;padding-top:20px;border-top:1px solid #edf0ed;color:#8a948d;font-size:.82rem}\nCSS;\n}\nfunction apply48SuspendedHtml():string\n{\n    return '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow,noarchive"><title>Website tijdelijk uitgeschakeld</title><link rel="stylesheet" href="/style.css"></head><body><main class="card"><div class="mark" aria-hidden="true">⏸</div><h1>Deze vereniging is tijdelijk uitgeschakeld</h1><p>De website is op dit moment niet beschikbaar. Probeer het later opnieuw of neem contact op met de vereniging als je vragen hebt.</p><div class="foot">Verenigingsplatform</div></main></body></html>\\n';\n}'''
t=t[:m.start()]+rep+t[m.end():]
t=t.replace("return ['root'=>'/var/www/verenigingsplatform-suspended','index'=>'/var/www/verenigingsplatform-suspended/index.html','available'=>","return ['root'=>'/var/www/verenigingsplatform-suspended','index'=>'/var/www/verenigingsplatform-suspended/index.html','style'=>'/var/www/verenigingsplatform-suspended/style.css','available'=>",1).replace("style-src \\\'unsafe-inline\\\'","style-src \\\'self\\\'",1).replace("apply48Write($x['index'],apply48SuspendedHtml(),0644);apply48Write($x['available']","apply48Write($x['index'],apply48SuspendedHtml(),0644);apply48Write($x['style'],apply48SuspendedCss(),0644);apply48Write($x['available']",1);p.write_text(t)

p=root/'site-config.php';t=p.read_text();old=". \"style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; \"";new=". \"style-src 'self' 'nonce-{$cspNonce}' https://fonts.googleapis.com; style-src-attr 'none'; \""
if old not in t: raise SystemExit('central old style-src missing')
p.write_text(t.replace(old,new,1))
p=root/'app/deployment/control-plane-contract.php';t=p.read_text();old="style-src \\\'self\\\' \\\'unsafe-inline\\\'";
if old not in t: raise SystemExit('control plane old style-src missing')
p.write_text(t.replace(old,"style-src \\\'self\\\'",1))

ren={}
for p in list(root.rglob('csp205-*.css')):
 rel=p.relative_to(root).as_posix();m=re.match(r'^(.*csp205-[a-z0-9-]+-)[0-9a-f]{12}(\.css)$',rel)
 if not m:continue
 nr=m.group(1)+hashlib.sha256(p.read_bytes()).hexdigest()[:12]+m.group(2)
 if nr!=rel:ren[rel]=nr
for old,new in ren.items():
 ob=Path(old).name;nb=Path(new).name
 for p in root.rglob('*'):
  if p.is_file() and p.suffix.lower() in {'.php','.html','.js'}:
   x=p.read_text(errors='ignore');y=x.replace(ob,nb).replace(old,new)
   if x!=y:p.write_text(y)
for old,new in ren.items():
 dst=root/new;dst.parent.mkdir(parents=True,exist_ok=True);(root/old).rename(dst)

p=root/'tests/csp-script-source-regression.php';t=p.read_text();old='cspOk(str_contains($siteConfig, "style-src \'self\' \'unsafe-inline\' https://fonts.googleapis.com"), "style-src blijft bewust ongewijzigd voor apart hardeningtraject");';new='cspOk(str_contains($siteConfig, "style-src \'self\' \'nonce-" . \'{$cspNonce}\' . "\' https://fonts.googleapis.com"), \'style-src gebruikt self plus response-nonce en bestaande Google Fonts origin\');\n    cspOk(str_contains($siteConfig, "style-src-attr \'none\'"), \'style-attributen zijn fail-closed geblokkeerd\');'
if old not in t: raise SystemExit('script regression old style assertion missing')
p.write_text(t.replace(old,new,1))

p=root/'tests/security-205-static-style-assets.php';t=p.read_text().replace("'bin/apply-vps-lifecycle.php'=>1","'beheer/instellingen.php'=>1").replace("str_contains($renderer,'<style id=\"content-page-hero-1\"><?= $heroCss ?></style>')&&str_contains($renderer,'<style id=\"content-page-hero-2\"><?= $heroCss ?></style>')","str_contains($renderer,'nonce=\"<?=siteCspHtmlWaarde(siteCspNonce())?>\" id=\"content-page-hero-1\"')&&str_contains($renderer,'nonce=\"<?=siteCspHtmlWaarde(siteCspNonce())?>\" id=\"content-page-hero-2\"')").replace("content-renderer inline styles zijn beperkt tot benoemde hero-contracten","content-renderer inline styles zijn benoemd en expliciet nonced");p.write_text(t)

p=root/'tests/phase483-suspended-placeholder.php';t=p.read_text();needle="c483(str_contains($vhost,'X-Robots-Tag')&&str_contains($vhost,'noindex')&&str_contains($vhost,'Cache-Control'),'placeholder is noindex en niet cachebaar');";t=t.replace(needle,needle+"\n"+"c483(str_contains($vhost,\"style-src 'self'\")&&!str_contains($vhost,\"'unsafe-inline'\"),'placeholder-CSP staat uitsluitend eigen stylesheet toe zonder unsafe-inline');",1);needle2="c483(str_contains($apply,\"'/var/www/verenigingsplatform-suspended'\")&&str_contains($apply,'apply48Write($x[\\'index\\'],apply48SuspendedHtml(),0644)'),'centrale statische placeholder is root-owned en publiek alleen leesbaar');";t=t.replace(needle2,needle2+"\n"+"c483(str_contains($apply,'href=\"/style.css\"')&&str_contains($apply,\"'style'=>'/var/www/verenigingsplatform-suspended/style.css'\")&&str_contains($apply,\"apply48Write(\\$x['style'],apply48SuspendedCss(),0644)\"),'placeholder-CSS staat in apart root-owned same-origin bestand');",1);p.write_text(t)
p=root/'tests/phase51-control-plane.php';t=p.read_text();needle="c51(str_contains($apache,'Content-Security-Policy')&&str_contains($apache,'X-Frame-Options \"DENY\"'),'platformbeheer krijgt strikte browserbeveiligingsheaders');";t=t.replace(needle,needle+"\n"+"c51(str_contains($apache,\"style-src 'self'\")&&!str_contains($apache,\"'unsafe-inline'\"),'platformbeheer-CSP bevat geen unsafe-inline styles');",1);p.write_text(t)

p=root/'tests/live-dev-security.sh';t=p.read_text();t=t.replace('"script-src \'self\'" "frame-src https://www.openstreetmap.org"','"script-src \'self\'" "script-src-attr \'none\'" "style-src-attr \'none\'" "frame-src https://www.openstreetmap.org"',1);marker="if ! grep -Eqi 'script-src[^;]*(gc\\.zgo\\.at|goatcounter|openstreetmap\\.org)' <<<\"$csp\"; then";extra="if grep -Eqi \"style-src[^;]*'unsafe-inline'\" <<<\"$csp\"; then bad 'CSP style-src bevat nog unsafe-inline'; else ok 'CSP style-src bevat geen unsafe-inline'; fi\nif grep -Eqi \"style-src[[:space:]]+'self'[[:space:]]+'nonce-[A-Za-z0-9+/_=-]+'[[:space:]]+https://fonts\\.googleapis\\.com\" <<<\"$csp\"; then ok 'CSP style-src bindt dynamische styles aan response-nonce'; else bad 'CSP style-src mist self + nonce + Google Fonts contract'; fi\n";t=t.replace(marker,extra+marker,1);route='''for route in / /beheer/ /leden/; do\n  safe="$(tr '/ ' '__' <<<"$route")"\n  curl --silent --show-error --dump-header "$TMP/csp-$safe" --output /dev/null --connect-timeout 10 --max-time 30 "$BASE$route"\n  route_csp="$(tr -d '\\r' < "$TMP/csp-$safe" | grep -Ei '^content-security-policy:' | tail -n1 || true)"\n  if [[ -n "$route_csp" ]] && grep -Fqi "style-src-attr 'none'" <<<"$route_csp" && ! grep -Eqi "style-src[^;]*'unsafe-inline'" <<<"$route_csp"; then ok "$route heeft hardened style-CSP"; else bad "$route mist hardened style-CSP"; fi\ndone\n\n''';marker2='trace="$(curl --silent --show-error --request TRACE';pos=t.find(marker2);t=t[:pos]+route+t[pos:] if pos>=0 else (_ for _ in ()).throw(SystemExit('live marker missing'));p.write_text(t)

strict=r'''<?php
declare(strict_types=1);$root=dirname(__DIR__);$ok=0;$fout=0;function s192(bool$c,string$l):void{global$ok,$fout;if($c){$ok++;echo"OK: {$l}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$l}\n");}}function g192(string$r,string$p):string{$x=shell_exec('git -C '.escapeshellarg($r).' show '.escapeshellarg('HEAD:'.$p));if(!is_string($x))throw new RuntimeException('git show faalt: '.$p);return$x;}$tracked=[];exec('git -C '.escapeshellarg($root).' ls-files',$tracked,$rc);if($rc!==0||$tracked===[])exit(1);$attrs=[];$sinks=[];$blocks=[];$unsafe=[];foreach($tracked as$p){$p=str_replace('\\','/',trim($p));if($p===''||str_starts_with($p,'tests/')||str_starts_with($p,'vendor/')||str_starts_with($p,'node_modules/'))continue;$e=strtolower(pathinfo($p,PATHINFO_EXTENSION));if(!in_array($e,['php','html','js','conf','sh','yml','yaml'],true))continue;$x=g192($root,$p);if(preg_match_all('~<[A-Za-z][^<>]*\\sstyle\\s*=\\s*(["\\']).*?\\1[^<>]*>~is',$x,$m,PREG_OFFSET_CAPTURE))foreach($m[0]as$q)$attrs[]=$p;if(preg_match('~\\.cssText\\s*=|setAttribute(?:NS)?\\s*\\([^)]*["\\']style["\\']|createElement\\s*\\(\\s*["\\']style["\\']~i',$x))$sinks[]=$p;if(preg_match_all('~<style\\b([^>]*)>.*?</style>~is',$x,$m,PREG_SET_ORDER))foreach($m as$q)$blocks[]=[$p,(string)$q[1]];if(str_contains($x,"'unsafe-inline'"))$unsafe[]=$p;}s192($attrs===[],'geen style-attributen');s192($sinks===[],'geen CSP-geblokkeerde style-sinks');s192($unsafe===[],"geen unsafe-inline in runtime/config");$exp=['app/content/content-pagina-runtime.php'=>1,'app/content/content-renderer.php'=>2,'app/core/site.php'=>4,'app/core/tenant-public-runtime.php'=>1,'beheer/instellingen.php'=>1];$cnt=[];$bad=[];foreach($blocks as[$p,$a]){$cnt[$p]=($cnt[$p]??0)+1;if(stripos($a,'nonce=')===false)$bad[]=$p;}ksort($cnt);s192($cnt===$exp&&count($blocks)===9,'exact negen benoemde dynamische styleblokken');s192($bad===[],'alle dynamische styles zijn nonced');$c=g192($root,'site-config.php');s192(str_contains($c,"style-src 'self' 'nonce-".'{$cspNonce}'."' https://fonts.googleapis.com")&&str_contains($c,"style-src-attr 'none'"),'centrale style-CSP hardened');$cp=g192($root,'app/deployment/control-plane-contract.php');s192(str_contains($cp,"style-src \\'self\\'")&&!str_contains($cp,"unsafe-inline"),'control-plane style-CSP hardened');$sus=g192($root,'bin/apply-vps-lifecycle.php');s192(str_contains($sus,'href="/style.css"')&&!str_contains($sus,"style-src \\'unsafe-inline\\'"),'suspended placeholder external CSS');$tr=g192($root,'app/core/tenant-public-runtime.php');s192(str_contains($tr,"preg_match('/^#[0-9A-F]{6}$/D'")&&str_contains($tr,'siteCspHtmlWaarde(siteCspNonce())'),'tenantkleurcontract strikt + nonce');$assets=[];foreach($tracked as$p)if(preg_match('#(?:^|/)csp205-[a-z0-9-]+-[0-9a-f]{12}\\.css$#D',$p))$assets[]=$p;$bh=[];foreach($assets as$a){$x=g192($root,$a);preg_match('/-([0-9a-f]{12})\\.css$/D',$a,$m);if(($m[1]??'')!==substr(hash('sha256',$x),0,12))$bh[]=$a;}s192(count($assets)===43&&$bh===[],'43 immutable content-gehashte CSS-assets');echo"CSP style regressie: {$ok} OK, {$fout} fout(en)\n";exit($fout===0?0:1);'''
(root/'tests/csp-style-source-regression.php').write_text(strict)
if (root/'tests/csp-style-source-snapshot.php').exists():(root/'tests/csp-style-source-snapshot.php').unlink()

for p in root.rglob('*.php'):
 r=subprocess.run(['php','-l',str(p)],stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,text=True)
 if r.returncode:raise SystemExit(f'lint {p}: {r.stderr}')
print('FINAL192_OK',len(ren),'rehashes')
