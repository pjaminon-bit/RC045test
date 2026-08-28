<?php
$root=dirname(__DIR__);$ok=0;$fout=0;
function c56(bool $c,string $l):void{global$ok,$fout;if($c){$ok++;echo"OK: $l\n";}else{$fout++;fwrite(STDERR,"FOUT: $l\n");}}
function wis56(string $p):void{if(is_link($p)||is_file($p)){@unlink($p);return;}if(!is_dir($p))return;foreach(scandir($p)?:[]as$i){if($i==='.'||$i==='..')continue;wis56($p.DIRECTORY_SEPARATOR.$i);}@rmdir($p);}

require_once $root.'/app/core/tenant-settings.php';
require_once $root.'/app/core/tenant-public-runtime.php';
require_once $root.'/app/core/tenant-public-media.php';

$tmp=sys_get_temp_dir().'/vereniging-product-'.bin2hex(random_bytes(5));$private=$tmp.'/private';@mkdir($private,0750,true);@mkdir($private.'/public-content',0750,true);
try{
 $basis=['vereniging'=>['sleutel'=>'morgen-club','naam'=>'Morgen Club','volledige_naam'=>'Morgen Club Nederland','slogan'=>'Samen actief','site_url'=>'https://morgen.example'],'branding'=>['logo'=>'','favicon'=>'','kleuren'=>[],'afbeeldingen'=>[]],'betaling'=>[],'opslag'=>['private_root'=>$private]];
 $input=['vereniging'=>['naam'=>'Morgen Club','volledige_naam'=>'Morgen Club Nederland','slogan'=>'Samen actief'],'branding'=>['logo'=>'','favicon'=>'','theme_color'=>'#112233','kleuren'=>['primary'=>'#345678','primary_dark'=>'#234567','primary_light'=>'#EAF0F4','accent'=>'#CC8800','accent_light'=>'#FFF4DD','dark'=>'#112233','text'=>'#223344','muted'=>'#667788','background'=>'#F5F7F9','nav_background'=>'#101820','nav_text'=>'#FFFFFF'],'afbeeldingen'=>['hero'=>'https://morgen.example/branding-asset.php?name=hero.jpg','about'=>'https://morgen.example/branding-asset.php?name=about.jpg','activity'=>'https://morgen.example/branding-asset.php?name=activity.jpg','gallery'=>'https://morgen.example/branding-asset.php?name=gallery.jpg']],'betaling'=>['iban'=>'NL91ABNA0417164300','tenaamstelling'=>'Morgen Club Nederland','omschrijving'=>'Contributie {jaar} - {naam}']];
 c56(tenantSettingsSchrijf($basis,$input),'tenantinstellingen worden atomisch onder private_root opgeslagen');
 $gelezen=tenantSettingsLees($basis);
 c56(($gelezen['vereniging']['naam']??'')==='Morgen Club','verenigingsnaam wordt teruggelezen');
 c56(($gelezen['branding']['kleuren']['nav_background']??'')==='#101820','menu-achtergrond is configureerbaar');
 c56(($gelezen['branding']['afbeeldingen']['hero']??'')==='https://morgen.example/branding-asset.php?name=hero.jpg','websitebeeldslots worden tenant-eigen opgeslagen');
 c56(($gelezen['betaling']['iban']??'')==='NL91 ABNA 0417 1643 00','IBAN wordt genormaliseerd');
 $legacyGeblokkeerd=false;try{tenantSettingsSchrijf($basis,array_replace_recursive($input,['vereniging'=>['naam'=>'RC045 kopie']]));}catch(InvalidArgumentException$e){$legacyGeblokkeerd=true;}
 c56($legacyGeblokkeerd,'beheerinstellingen weigeren voorbeeldvereniging-identiteit');

 @file_put_contents($private.'/public-content/contact.json',json_encode(['email'=>'bestuur@morgen.example','facebook'=>'https://facebook.com/morgenclub','adres_straat'=>'Parklaan 1','adres_postcode_plaats'=>'1234 AB Morgenstad'],JSON_UNESCAPED_SLASHES));
 $config=array_replace_recursive($basis,$gelezen);
 $html='<!doctype html><html><head><link rel="icon" href="favicon.ico"><script data-goatcounter="https://rc045.goatcounter.com/count" src="//gc.zgo.at/count.js"></script></head><body><nav class="nav"><span class="nav-logo-text">RC045</span></nav><img src="rc045-logo.png" alt="RC045"><img src="images/crawlergroen.jpg"><p>RC045 – Bashers of the South · Eygelshoven · Wijngaardsberg 26 · bestuur@rc045.nl · NL51 RABO 0367 6153 63</p><form id="aanmeld-form" action="https://formspree.io/f/voorbeeld"><button>stuur</button></form><script>fetch(\'aanmelden-ontvangst.php\', {method:\'POST\'}).catch(function(){});</script></body></html>';
 $uit=tenantPublicRuntimeTransform($html,$config);
 foreach(['rc045','bashers of the south','eygelshoven','wijngaardsberg','bestuur@rc045.nl','nl51 rabo 0367 6153 63','goatcounter.com','formspree.io']as$verboden)c56(stripos($uit,$verboden)===false,'uitvoer bevat geen '.$verboden);
 c56(str_contains($uit,'Morgen Club'),'tenantnaam wordt in historische markup ingevuld');
 c56(str_contains($uit,'Parklaan 1')&&str_contains($uit,'1234 AB Morgenstad'),'tenantadres vervangt voorbeeldlocatie');
 c56(str_contains($uit,'NL91 ABNA 0417 1643 00'),'tenant-IBAN vervangt voorbeeldrekening');
 c56(str_contains($uit,'action="aanmelden-ontvangst.php"'),'aanmeldformulier post naar eigen tenantinbox');
 c56(str_contains($uit,'--nav-bg:#101820!important')&&str_contains($uit,'--teal:#345678!important'),'tenantkleuren worden in uitgaande HTML geïnjecteerd');
 c56(str_contains($uit,'images/template-placeholder.svg'),'overgebleven voorbeeldmedia wordt door neutrale placeholder vervangen');

 $mediaHtml='<!doctype html><html><body><div id="hero-bg" class="hero-bg"></div><img class="about-img-main" src="images/oud.jpg"><img class="track-photo" src="images/oud2.jpg"><div class="carousel-slide-bg" data-bg="images/oud3.jpg"></div><img class="carousel-img" src="data:image/gif;base64,AA" data-src="images/oud3.jpg"></body></html>';
 $mediaUit=tenantPublicMediaTransform($mediaHtml,$config);
 c56(str_contains($mediaUit,'hero.jpg')&&str_contains($mediaUit,'about.jpg')&&str_contains($mediaUit,'activity.jpg')&&str_contains($mediaUit,'gallery.jpg'),'hero, over-ons, activiteit en fotostrook krijgen eigen tenantbeelden');

 $platform=require $root.'/app/core/platform-definities.php';
 c56(($platform['beheer']['instellingen']['route']??'')==='instellingen.php','beheer heeft Instellingen & huisstijl menu-item');
 c56(($platform['beheer']['websitebeelden']['route']??'')==='websitebeelden.php','beheer heeft Websitebeelden menu-item');
 c56(isset($platform['capabilities']['system.settings.manage']),'huisstijlbeheer heeft eigen capability');
 $pagina=(string)file_get_contents($root.'/beheer/instellingen.php');
 c56(str_contains($pagina,'type="color"')&&str_contains($pagina,'name="logo"')&&str_contains($pagina,'name="favicon"'),'beheer bevat colorpickers en brandinguploads');
 c56(str_contains($pagina,'csrf')&&str_contains($pagina,"authHeeftCapability('system.settings.manage', true)"),'instellingenbeheer is CSRF- en capability-afgeschermd');
 $beeldenPagina=(string)file_get_contents($root.'/beheer/websitebeelden.php');
 c56(str_contains($beeldenPagina,"'hero'")&&str_contains($beeldenPagina,"'about'")&&str_contains($beeldenPagina,"'activity'")&&str_contains($beeldenPagina,"'gallery'"),'websitebeeldenbeheer bevat vier praktische beeldslots');
 $contact=(string)file_get_contents($root.'/beheer/contact.php');
 c56(str_contains($contact,"tenantContentIsExtern()")&&str_contains($contact,"'instagram'=>'','updated'=>'']"),'contactbeheer heeft neutrale tenantfallback en Instagram');
 $ontvangst=(string)file_get_contents($root.'/aanmelden-ontvangst.php');
 c56(str_contains($ontvangst,'lidmaatschapTypeVoorLeeftijd($leeftijd)'),'aanmeldinbox kan lidmaatschapstype veilig uit leeftijd afleiden');
 $bootstrap=(string)file_get_contents($root.'/bin/prepare-first-vps-bootstrap.php');
 c56(str_contains($bootstrap,"php-runtime-requirements.php")&&str_contains($bootstrap,'platformPhpRequiredExtensions()'),'first-VPS bootstrap gebruikt dezelfde actuele PHP-runtime-eisen als releases');
}finally{wis56($tmp);}

echo"Productbranding/tenantneutraliteit: $ok OK, $fout fout(en)\n";exit($fout===0?0:1);
