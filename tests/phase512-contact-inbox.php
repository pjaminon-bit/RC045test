<?php
$root=dirname(__DIR__);$errors=[];$ok=[];
function c512($cond,string $message): void{global$errors,$ok;if($cond)$ok[]=$message;else$errors[]=$message;}
function c512txt(string $path): string{return is_file($path)?(string)file_get_contents($path):'';}

require_once $root.'/contactberichten-opslag.php';
require_once $root.'/app/core/contact-inbox-runtime.php';

$bericht=contactBerichtNormaliseer([
    'naam'=>' E2E Contact ',
    'email'=>'contact@example.invalid',
    'telefoon'=>'+31 6 12345678',
    'onderwerp'=>'Vraag',
    'bericht'=>str_repeat('x',5100),
]);
c512(str_starts_with((string)($bericht['id']??''),'msg_'),'contactbericht krijgt eigen willekeurig id');
c512(($bericht['status']??'')==='nieuw','nieuw contactbericht start open');
c512(($bericht['naam']??'')==='E2E Contact','contactnaam wordt genormaliseerd');
c512(strlen((string)($bericht['bericht']??''))===5000,'contactbericht heeft harde lengtegrens');

$now=strtotime('2026-08-30T12:00:00+02:00');$oud=date('c',$now-200*86400);$recent=date('c',$now-10*86400);
$doc=['berichten'=>[
    ['id'=>'open-oud','status'=>'nieuw','aangemaakt'=>$oud],
    ['id'=>'klaar-oud','status'=>'afgehandeld','aangemaakt'=>$oud,'afgehandeld_op'=>$oud],
    ['id'=>'open-recent','status'=>'nieuw','aangemaakt'=>$recent],
    ['id'=>'klaar-recent','status'=>'afgehandeld','aangemaakt'=>$oud,'afgehandeld_op'=>$recent],
]];
$removed=contactBerichtenPasRetentieToe($doc,$now);$ids=array_map(static fn($b)=>(string)($b['id']??''),$doc['berichten']);
c512($removed===2,'retentie verwijdert oude open en afgehandelde contactberichten');
c512(!in_array('open-oud',$ids,true),'open contactbericht valt onder maximale bewaartermijn');
c512(in_array('open-recent',$ids,true)&&in_array('klaar-recent',$ids,true),'recente contactberichten blijven bewaard');

$sample='<!doctype html><html><body><form id="contact-form" action="https://formspree.io/f/legacy" data-tenant-disabled="1"><button type="submit" disabled="disabled">Stuur</button></form></body></html>';
$uit=contactInboxRuntimeTransform($sample);
c512(str_contains($uit,'action="contact-ontvangst.php"'),'runtime forceert same-origin contactendpoint');
c512(!str_contains(strtolower($uit),'formspree.io'),'runtime laat geen externe contact-action door');
c512(!str_contains($uit,'data-tenant-disabled'),'runtime activeert eerder uitgeschakelde tenantvorm');
c512(!preg_match('~<button[^>]+disabled~i',$uit),'runtime activeert submitknop');
$ander='<form id="ander" action="https://example.invalid/"></form>';
c512(contactInboxRuntimeTransform($ander)===$ander,'runtime raakt andere formulieren niet');

$endpoint=c512txt($root.'/contact-ontvangst.php');
c512(strpos($endpoint,"\$_POST['website']")!==false,'publiek endpoint bevat honeypot');
c512(strpos($endpoint,'aanmeldenPogingRegistreer')!==false,'publiek endpoint gebruikt geharde rate limiter');
c512(strpos($endpoint,"hash('sha256','contact|'")!==false,'contact-rate-limit is logisch gescheiden van aanmeldingen');
c512(strpos($endpoint,'contactBerichtenSchrijf')!==false,'publiek endpoint schrijft alleen naar private contactinbox');
c512(stripos($endpoint,'formspree')===false,'same-origin endpoint kent geen externe formprovider');

$platform=require $root.'/app/core/platform-definities.php';
c512(($platform['beheer']['contactberichten']['capability']??'')==='contact.messages.manage','contactinbox heeft aparte capability');
c512(!empty($platform['beheer']['contactberichten']['gevoelig']),'contactinbox is als gevoelige beheerfunctie gemarkeerd');
c512(!empty($platform['capabilities']['contact.messages.manage']['gevoelig']),'contactbericht-capability is gevoelig');

$beheer=c512txt($root.'/beheer/contactberichten.php');
c512(strpos($beheer,"authHeeftCapability('contact.messages.manage')")!==false,'beheerpagina dwingt contactcapability af');
c512(strpos($beheer,'csrfOk()')!==false,'beheeracties zijn CSRF-beveiligd');
c512(strpos($beheer,'contactBerichtenOpschonenBewaartermijn')!==false,'beheer materialiseert retentie');

$site=c512txt($root.'/site-config.php');
c512(strpos($site,'$formAction = "\'self\'"')!==false,'CSP staat formulieractie alleen same-origin toe');
c512(stripos($site,'formspree.io')===false,'CSP staat Formspree niet meer toe');
c512(strpos($site,'contactberichten_bewaardagen')!==false,'contactretentie heeft expliciete standaardconfig');
$ht=c512txt($root.'/.htaccess');$gi=c512txt($root.'/.gitignore');$backups=c512txt($root.'/beheer/backup-registry.php');
c512(strpos($ht,'contactberichten-data\\.php')!==false,'Apache blokkeert standalone contactinboxdata');
c512(strpos($gi,'contactberichten-data.php')!==false,'contactinboxdata kan niet naar Git worden gecommit');
c512(strpos($backups,"'contactberichten'=>'Contactberichten-inbox'")!==false,'tenantbackups bevatten contactinbox');
c512(strpos($backups,"'contactberichten_inbox'")!==false,'standalone backups bevatten contactinbox');

echo 'Phase 5.12 tenant contact inbox: '.count($ok).' OK, '.count($errors)." fout(en)\n";
if($errors){foreach($errors as $e)fwrite(STDERR,"FOUT: $e\n");exit(1);}foreach($ok as $m)echo "OK: $m\n";
