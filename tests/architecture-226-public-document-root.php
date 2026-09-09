<?php
$root = dirname(__DIR__);
require_once $root . '/app/web/public-route-contract.php';

$ok = 0; $fout = 0;
function check226(bool $cond, string $label): void { global $ok,$fout; if($cond){$ok++;echo "OK: {$label}\n";}else{$fout++;fwrite(STDERR,"FOUT: {$label}\n");} }
function binnen226(string $pad, string $root): bool {
    $p=realpath($pad);$r=realpath($root);if(!is_string($p)||!is_string($r))return false;
    $p=rtrim(str_replace('\\','/',$p),'/');$r=rtrim(str_replace('\\','/',$r),'/');
    return $p===$r||str_starts_with($p,$r.'/');
}
function route226(string $uri): ?array { return public226Resolve($uri, dirname(__DIR__)); }
function mirrored226(string $source,string $public): bool {
    return is_file($source)&&is_file($public)&&hash_equals((string)hash_file('sha256',$source),(string)hash_file('sha256',$public));
}

$public=$root.'/public';
check226(is_dir($public)&&!is_link($public),'public/ bestaat als fysieke niet-gesymlinkte directory');
check226(binnen226($public,$root)&&realpath($public)!==realpath($root),'public/ is een eigen subdirectory van de applicatierelease');

foreach (['app','bin','tests','docs','ops'] as $intern) {
    check226(is_dir($root.'/'.$intern)&&!binnen226($root.'/'.$intern,$public),"{$intern}/ ligt fysiek buiten de documentroot");
}
foreach (['auth.php','site-config.php','app/core/site.php','app/core/site-seo.php','app/beheer/module-registry.php','beheer/fotoboek-lib.php'] as $intern) {
    check226(is_file($root.'/'.$intern)&&!binnen226($root.'/'.$intern,$public),"{$intern} ligt fysiek buiten de documentroot");
}

$php=[];$symlinks=[];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($public,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
foreach($it as$info){
    $pad=$info->getPathname();
    if(is_link($pad)){$symlinks[]=$pad;continue;}
    if($info->isFile()&&strtolower($info->getExtension())==='php')$php[]=str_replace('\\','/',substr($pad,strlen($root)+1));
}
sort($php,SORT_STRING);
check226($symlinks===[],'public/ bevat geen symlinks die de filesystemgrens kunnen omzeilen');
check226($php===['public/index.php'],'alleen public/index.php is fysiek PHP-bereikbaar');

$frontController=(string)file_get_contents($public.'/index.php');
$routeContract=(string)file_get_contents($root.'/app/web/public-route-contract.php');
check226(str_contains($frontController, '$public226Target = public226Dispatch();')
    && str_contains($frontController, 'require $public226Target;'), 'frontcontroller voert het gerouteerde PHP-target zelf op top-level uit');
check226(!str_contains($routeContract, 'require $target;')
    && str_contains($routeContract, 'return $target;'), 'routecontract retourneert het PHP-target zonder include in lokale functiescope');
check226(str_contains($routeContract, 'legacy routebestanden in lokale functiescope uitvoeren')
    && str_contains($routeContract, 'globale PHP-scope'), 'global-scope compatibiliteitsgrens is expliciet gedocumenteerd');

$rootAssets=['acceptance-hardening.css','acceptance-hardening.js','android-chrome-192x192.png','android-chrome-512x512.png','apple-touch-icon.png','csp205-aanmelden-b31d17f20682.css','csp205-bedankt-d8aa6cfe465f.css','csp205-content-renderer-1-3035699392f1.css','csp205-content-renderer-2-935f1f35b2f9.css','csp205-fotoboek-0315ba62e094.css','csp205-index-f6f06d63c77b.css','csp205-media-abe69927c5b8.css','favicon-16x16.png','favicon-32x32.png','favicon-48x48.png','favicon.ico','homepage.js','iframe-placeholder.css','lidmaatschap-aanmelden.js','paneel-thema.js','paneel.css','paneel.js','rc045-logo.png','robots.txt','site-i18n.js','site.webmanifest','sitemap.xml','styles.css'];
$allMirrored=true;foreach($rootAssets as$a){if(!mirrored226($root.'/'.$a,$public.'/'.$a)){$allMirrored=false;fwrite(STDERR,"FOUT DETAIL: asset mirror wijkt af: {$a}\n");}}
check226($allMirrored,'alle expliciete root-browserassets zijn byte-identiek onder public/ gematerialiseerd');

$treeMirrors=[
    ['source'=>$root.'/beheer','dest'=>$public.'/beheer','regex'=>'/\.css$/D'],
    ['source'=>$root.'/leden','dest'=>$public.'/leden','regex'=>'/\.css$/D'],
    ['source'=>$root.'/vendor/photoswipe','dest'=>$public.'/vendor/photoswipe','regex'=>'/\.(?:css|js)$/D'],
];
foreach($treeMirrors as$m){
    $good=true;$count=0;
    foreach(scandir($m['source'])?:[] as$n){if($n==='.'||$n==='..'||preg_match($m['regex'],$n)!==1)continue;$count++;if(!mirrored226($m['source'].'/'.$n,$m['dest'].'/'.$n))$good=false;}
    check226($count>0&&$good,'publieke assets uit '.basename($m['source']).'/ zijn volledig en byte-identiek gespiegeld');
}
check226(mirrored226($root.'/images/template-placeholder.svg',$public.'/images/template-placeholder.svg')&&mirrored226($root.'/images/template-placeholder.png',$public.'/images/template-placeholder.png'),'template-placeholders staan byte-identiek in public/images');

$known=[
    '/'=>['php','index.php'],
    '/index.html'=>['php','index.php'],
    '/aanmelden.html'=>['php','aanmelden.php'],
    '/healthz.php'=>['php','healthz.php'],
    '/beheer/'=>['php','beheer/index.php'],
    '/beheer/leden.php'=>['php','beheer/leden.php'],
    '/beheer/data-integriteit.php'=>['php','beheer/data-integriteit.php'],
    '/beheer/rekentabel.html'=>['php','beheer/rekentabel.php'],
    '/leden/'=>['php','leden/index.php'],
    '/data/homepage.json'=>['php','public-content.php'],
    '/images/sponsors/logo.png'=>['php','public-asset.php'],
    '/images/fotoboek/zomer/thumbs/foto.jpg'=>['php','public-asset.php'],
];
foreach($known as$uri=>$expect){$r=route226($uri);check226(is_array($r)&&($r['type']??'')===$expect[0]&&($r['target']??'')===$expect[1],"publieke route blijft expliciet toegestaan: {$uri}");}

$internals=['/app/core/platform-definities.php','/bin/apply-vps-release.php','/tests/run-all.sh','/docs/VPS-DEPLOYMENT.md','/ops/vps-test-deploy/verenigingsplatform-github-deploy','/auth.php','/site-config.php','/.git/HEAD','/.github/workflows/full-regression.yml','/beheer/fotoboek-lib.php','/beheer/backup-registry.php','/beheer/gebruikers-rechten.php','/beheer/lid-groepen.php'];
foreach($internals as$uri)check226(route226($uri)===null,"intern pad is niet routable: {$uri}");

$traversal=['/../auth.php','/%2e%2e/auth.php','/beheer/%2e%2e/auth.php','/%252e%252e/auth.php','/beheer/%5c..%5cauth.php','/%ZZ/auth.php'];
foreach($traversal as$uri)check226(route226($uri)===null,"traversal/encodingvariant faalt gesloten: {$uri}");

$ht=(string)file_get_contents($public.'/.htaccess');
check226(str_contains($ht,'RewriteCond %{REQUEST_FILENAME} -f')&&str_contains($ht,'RewriteRule ^ index.php [L,QSA]'),'public/.htaccess serveert alleen echte assets direct en routeert de rest naar de frontcontroller');
check226(str_contains($ht,'RewriteCond %{REQUEST_FILENAME} !/index\\.php$')&&str_contains($ht,'RewriteRule \\.php$ - [F,L,NC]'),'public/.htaccess blokkeert fysieke extra PHP-bestanden vóór directe fileserving');
check226(!str_contains($ht,'<FilesMatch')&&!str_contains($ht,'Require all'),'public/.htaccess heeft geen AuthConfig-afhankelijke authorizationregels meer nodig');
check226(!str_contains($ht,'app|bin|tests|docs')&&!str_contains($ht,'site-config'),'public/.htaccess onderhoudt geen gevoelige repository-denylist');

$web=(string)file_get_contents($root.'/app/deployment/webserver-contract.php');
check226(str_contains($web,"'/public'")&&str_contains($web,'application_release_root_denied'),'Apache-contract bindt expliciet aan public/ en weigert de applicatierelease als geheel');
check226(str_contains($web,'$releaseParent = dirname($releaseRoot)')&&str_contains($web,'Options +FollowSymLinks'),'Apache-contract staat de atomische current-symlink alleen vanaf zijn geweigerde parentdirectory toe');
check226(str_contains($web,'AuthMerging Off')&&str_contains($web,'<FilesMatch "(?i)\\\\.php$">'),'Apache-contract maakt de frontcontroller-authorization expliciet en blokkeert PHP case-insensitive');
check226(!str_contains($web,'LocationMatch "^/(?:app|bin|tests|docs')&&!str_contains($web,'site-config(?:\\.local)?'),'Apache-generator bevat geen dubbele gevoelige-pad/filename denylist meer');

echo "Architecture #226 public document root: {$ok} OK, {$fout} fout(en)\n";
exit($fout===0?0:1);