<?php
// ============================================================
// Beheer-dashboard / module-shell
// ============================================================
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/core/site.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';

function beheerShellEsc($waarde): string { return htmlspecialchars((string)$waarde,ENT_QUOTES,'UTF-8'); }
function beheerShellPlatform(): array { return authPlatformDefinities(); }
function beheerShellComponentActief(array $def): bool { $feature=trim((string)($def['feature']??''));return$feature===''||siteModuleActief($feature); }
function beheerShellMagOpenen(array $def): bool { if(!beheerShellComponentActief($def))return false;$cap=trim((string)($def['capability']??''));return$cap===''||authHeeftCapability($cap,!empty($def['gevoelig'])); }
function beheerShellRouteBestaat(string $route): bool { $pad=(string)parse_url($route,PHP_URL_PATH);return$pad!==''&&is_file(__DIR__.'/'.ltrim($pad,'/')); }
function beheerShellHoofdmenuZichtbaar(string $sleutel): bool { return !in_array($sleutel,['aanmeldingen','leden_import','ledenlabels','groepsrollen','operationele_taken'],true); }
function beheerShellMagRelaties(): bool { return authHeeftCapability('tasks.manage')||authHeeftCapability('meetings.manage')||authHeeftCapability('events.manage'); }
function beheerShellSubmenu(string $sleutel,array $componenten): array {
    $sets=[
        'leden'=>['aanmeldingen','leden_import','ledenlabels'],
        'commissies'=>['groepsrollen'],
        'werkgroepen'=>['groepsrollen'],
        'taken'=>['operationele_taken'],
    ];
    $uit=[];
    foreach($sets[$sleutel]??[] as $sub){$def=$componenten[$sub]??null;if(!is_array($def)||!beheerShellMagOpenen($def))continue;$route=trim((string)($def['route']??''));if($route===''||!beheerShellRouteBestaat($route))continue;$uit[]=['label'=>(string)($def['label']??$sub),'route'=>$route];}
    if(in_array($sleutel,['commissies','werkgroepen'],true)&&beheerShellMagRelaties()&&is_file(__DIR__.'/groep-relaties.php'))$uit[]=['label'=>'Relaties','route'=>'groep-relaties.php'];
    return$uit;
}

$platform=beheerShellPlatform();$componenten=is_array($platform['beheer']??null)?$platform['beheer']:[];$groepen=[];
if($ingelogd)foreach($componenten as $sleutel=>$def){if(!is_array($def)||!beheerShellHoofdmenuZichtbaar((string)$sleutel)||!beheerShellMagOpenen($def))continue;$route=trim((string)($def['route']??''));if($route===''||!beheerShellRouteBestaat($route))continue;$categorie=(string)($def['categorie']??'Overig');$def['_sleutel']=(string)$sleutel;$groepen[$categorie][(string)$sleutel]=$def;}
$build=null;$buildPad=dirname(__DIR__).'/dev-build.json';if(is_file($buildPad)){$ruw=@file_get_contents($buildPad);$gelezen=$ruw===false?null:json_decode($ruw,true);if(is_array($gelezen))$build=$gelezen;}
?><!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=beheerShellEsc(siteNaam())?> beheer</title><link rel="stylesheet" href="csp205-index-8e244dd87d1c.css"><link rel="stylesheet" href="ui-2026.css"></head><body class="beheer-dashboard"><?php if(!$ingelogd):?><div class="login-wrap"><?php authInlogFormulier(siteNaam().' beheer');?></div><?php else:?><header class="top"><div class="topin"><div><span class="brand"><?=beheerShellEsc(siteNaam())?> beheer</span> · <a href="../index.html">website</a> · <a href="../leden/">mijn vereniging</a></div><div class="user"><span>Ingelogd als <strong><?=beheerShellEsc($huidigeGebruiker)?></strong></span><form method="post"><input type="hidden" name="formulier" value="uitloggen"><input type="hidden" name="csrf" value="<?=beheerShellEsc($csrfToken)?>"><button class="logout" type="submit">Uitloggen</button></form></div></div></header><main class="wrap"><div class="hero"><h1>Beheer</h1><p>Website, vereniging en systeembeheer vanuit één modulaire omgeving.</p></div><?php if(!$groepen):?><div class="empty">Voor dit account zijn geen beheeronderdelen beschikbaar.</div><?php else:?><div class="grid"><?php foreach($groepen as $categorie=>$items):?><section class="groep"><h2><?=beheerShellEsc($categorie)?></h2><div class="links"><?php foreach($items as $def):$subs=beheerShellSubmenu((string)($def['_sleutel']??''),$componenten);?><div class="moduleblok"><a class="module" href="<?=beheerShellEsc($def['route']??'')?>"><span><?=beheerShellEsc($def['label']??'')?></span><span class="meta">open →</span></a><?php if($subs):?><div class="subnav"><?php foreach($subs as $sub):?><a href="<?=beheerShellEsc($sub['route'])?>"><?=beheerShellEsc($sub['label'])?></a><?php endforeach;?></div><?php endif;?></div><?php endforeach;?></div></section><?php endforeach;?></div><?php endif;?><?php if($build):?><div class="build">DEV build: <strong><?=beheerShellEsc($build['commit_short']??'')?></strong> · branch <?=beheerShellEsc($build['branch']??'')?> · run <?=beheerShellEsc($build['run_number']??'')?> · <?=beheerShellEsc($build['deployed_at_utc']??'')?></div><?php endif;?></main><?php endif;?></body></html>
