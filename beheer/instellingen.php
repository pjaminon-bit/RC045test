<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/auth-capabilities.php';
require_once dirname(__DIR__) . '/app/core/tenant-settings.php';
require_once dirname(__DIR__) . '/app/core/tenant-branding-assets.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
if (!authHeeftCapability('system.settings.manage', true)) { http_response_code(403); echo 'Geen toegang tot Instellingen & huisstijl.'; exit; }

function insEsc($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function insKleur(array $config, string $sleutel, string $fallback): string {
    $v = strtoupper(trim((string)($config['branding']['kleuren'][$sleutel] ?? '')));
    return preg_match('/^#[0-9A-F]{6}$/D', $v) === 1 ? $v : $fallback;
}
function insVeiligeIdentiteit(string $v, string $fallback=''): string {
    return tenantSettingsBevatLegacy($v) ? $fallback : $v;
}

$config = $authSiteConfig;
$extern = tenantRuntimeExternConfigPad() !== null;
$melding = '';
$type = '';

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) {
        $melding = 'Sessie verlopen. Ververs de pagina en probeer opnieuw.';
        $type = 'fout';
    } elseif (!$extern) {
        $melding = 'Webinstellingen zijn alleen actief voor een geïsoleerde tenantinstallatie.';
        $type = 'fout';
    } else {
        try {
            $logo = trim((string)($config['branding']['logo'] ?? ''));
            $favicon = trim((string)($config['branding']['favicon'] ?? ''));
            if (!empty($_FILES['logo']) && (int)($_FILES['logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $logo = tenantBrandingAssetUpload($config, $_FILES['logo'], 'logo');
            }
            if (!empty($_FILES['favicon']) && (int)($_FILES['favicon']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $favicon = tenantBrandingAssetUpload($config, $_FILES['favicon'], 'favicon');
            }
            if (!empty($_POST['logo_verwijderen'])) $logo = '';
            if (!empty($_POST['favicon_verwijderen'])) $favicon = '';

            $input = [
                'vereniging' => [
                    'naam' => $_POST['naam'] ?? '',
                    'volledige_naam' => $_POST['volledige_naam'] ?? '',
                    'slogan' => $_POST['slogan'] ?? '',
                ],
                'branding' => [
                    'logo' => $logo,
                    'favicon' => $favicon,
                    'theme_color' => $_POST['kleur_dark'] ?? '',
                    'kleuren' => [
                        'primary'=>$_POST['kleur_primary']??'',
                        'primary_dark'=>$_POST['kleur_primary_dark']??'',
                        'primary_light'=>$_POST['kleur_primary_light']??'',
                        'accent'=>$_POST['kleur_accent']??'',
                        'accent_light'=>$_POST['kleur_accent_light']??'',
                        'dark'=>$_POST['kleur_dark']??'',
                        'text'=>$_POST['kleur_text']??'',
                        'muted'=>$_POST['kleur_muted']??'',
                        'background'=>$_POST['kleur_background']??'',
                        'nav_background'=>$_POST['kleur_nav_background']??'',
                        'nav_text'=>$_POST['kleur_nav_text']??'',
                    ],
                ],
                'betaling' => [
                    'iban' => $_POST['iban'] ?? '',
                    'tenaamstelling' => $_POST['tenaamstelling'] ?? '',
                    'omschrijving' => $_POST['omschrijving'] ?? '',
                ],
            ];
            if (!tenantSettingsSchrijf($config, $input)) throw new RuntimeException('Instellingen konden niet veilig worden opgeslagen.');
            schrijfLog($logBestand, $huidigeGebruiker, 'instellingen', 'identiteit, huisstijl en betaalgegevens bijgewerkt');
            header('Location: instellingen.php?opgeslagen=1');
            exit;
        } catch (Throwable $e) {
            error_log('[platform] instellingen opslaan mislukt: ' . $e->getMessage());
            $melding = $e instanceof InvalidArgumentException ? $e->getMessage() : 'Opslaan is mislukt. Controleer de invoer en probeer opnieuw.';
            $type = 'fout';
        }
    }
}

if (isset($_GET['opgeslagen'])) { $melding = 'Instellingen opgeslagen en direct actief op de website.'; $type = 'ok'; }
// Na een redirect wordt site-config opnieuw geladen en zien we de nieuwe waarden.
$config = require dirname(__DIR__) . '/site-config.php';
$naam = insVeiligeIdentiteit(trim((string)($config['vereniging']['naam'] ?? '')), 'Vereniging');
$volledig = insVeiligeIdentiteit(trim((string)($config['vereniging']['volledige_naam'] ?? '')), $naam);
$slogan = insVeiligeIdentiteit(trim((string)($config['vereniging']['slogan'] ?? '')), '');
$logo = insVeiligeIdentiteit(trim((string)($config['branding']['logo'] ?? '')), '');
$favicon = insVeiligeIdentiteit(trim((string)($config['branding']['favicon'] ?? '')), '');
$betaling = is_array($config['betaling'] ?? null) ? $config['betaling'] : [];
$kleuren = [
    'primary'=>insKleur($config,'primary','#3A7A77'),
    'primary_dark'=>insKleur($config,'primary_dark','#2D6260'),
    'primary_light'=>insKleur($config,'primary_light','#EAF4F3'),
    'accent'=>insKleur($config,'accent','#C89A1A'),
    'accent_light'=>insKleur($config,'accent_light','#FBF4DF'),
    'dark'=>insKleur($config,'dark','#1E2C13'),
    'text'=>insKleur($config,'text','#2A3818'),
    'muted'=>insKleur($config,'muted','#6A7560'),
    'background'=>insKleur($config,'background','#FAF6EC'),
    'nav_background'=>insKleur($config,'nav_background','#FFFFFF'),
    'nav_text'=>insKleur($config,'nav_text','#2A3818'),
];
$previewLogo = $logo;
if ($previewLogo !== '' && !preg_match('~^https?://~i', $previewLogo)) $previewLogo = '../' . ltrim($previewLogo, '/');
?><!doctype html>
<html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Instellingen & huisstijl</title>
<style>
:root{--bg:#f6f2e8;--card:#fff;--ink:#26351d;--muted:#68705f;--line:#ddd8c0;--primary:#3a7a77;--primary2:#2d6260;--danger:#8b2e27}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;line-height:1.5}.top{background:#fff;border-bottom:1px solid var(--line);padding:15px 22px;position:sticky;top:0;z-index:20}.topin,.wrap{max-width:1180px;margin:auto}.topin{display:flex;justify-content:space-between;gap:16px;align-items:center}.top a{color:var(--primary2);font-weight:750;text-decoration:none}.wrap{padding:30px 22px 70px}.intro{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-bottom:24px}.intro h1{margin:0 0 7px}.intro p{margin:0;color:var(--muted)}.layout{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:22px;align-items:start}.kaart{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 4px 18px rgba(0,0,0,.035)}.kaart h2{margin:0 0 5px;font-size:19px}.kaart>p{margin:0 0 18px;color:var(--muted);font-size:14px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.veld.full{grid-column:1/-1}.veld label{display:block;font-weight:750;font-size:13px;margin-bottom:6px}.veld input[type=text],.veld input[type=url]{width:100%;border:1px solid #cfcab7;border-radius:9px;padding:11px;font:inherit;background:#fff}.veld input:focus{outline:3px solid rgba(58,122,119,.14);border-color:var(--primary)}.hint{font-size:12px;color:var(--muted);margin-top:5px}.colors{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:11px}.color{display:grid;grid-template-columns:44px 1fr;gap:10px;align-items:center;border:1px solid #e5e0cf;border-radius:10px;padding:9px}.color input{width:44px;height:38px;border:0;padding:0;background:none;cursor:pointer}.color strong{display:block;font-size:13px}.color small{color:var(--muted)}.upload{border:1px dashed #c8c1a9;border-radius:11px;padding:14px}.upload input[type=file]{width:100%}.check{display:flex;gap:8px;align-items:center;margin-top:9px;font-size:13px}.btn{border:0;background:var(--primary);color:#fff;border-radius:10px;padding:13px 19px;font:inherit;font-weight:800;cursor:pointer}.btn:hover{background:var(--primary2)}.melding{padding:12px 14px;border-radius:10px;margin-bottom:18px}.ok{background:#e8f5ee;color:#205b38}.fout{background:#fdeceb;color:var(--danger)}.preview{position:sticky;top:88px;overflow:hidden;padding:0}.preview-nav{height:64px;padding:0 18px;display:flex;align-items:center;justify-content:space-between;background:var(--pv-nav);color:var(--pv-navtext);border-bottom:1px solid rgba(0,0,0,.08)}.preview-brand{display:flex;align-items:center;gap:9px;font-weight:850}.preview-brand img{width:38px;height:38px;object-fit:contain;border-radius:7px}.preview-links{display:flex;gap:7px;font-size:11px}.preview-links span:last-child{background:var(--pv-primary);color:#fff;border-radius:7px;padding:6px 8px}.preview-hero{padding:40px 24px;background:linear-gradient(135deg,var(--pv-dark),var(--pv-primary));color:#fff}.preview-hero h3{font-size:29px;margin:0 0 7px}.preview-hero p{opacity:.8;margin:0 0 18px}.preview-button{display:inline-block;background:var(--pv-primary);color:#fff;padding:9px 12px;border-radius:8px;font-weight:750}.preview-body{padding:22px 24px;background:var(--pv-bg);color:var(--pv-text)}.preview-card{background:#fff;border-radius:10px;padding:15px;border-left:4px solid var(--pv-accent)}.linkskaart a{display:block;color:var(--primary2);font-weight:750;text-decoration:none;padding:8px 0;border-bottom:1px solid #eee9db}.linkskaart a:last-child{border-bottom:0}.waarschuwing{background:#fff8e7;border:1px solid #ecd49b;border-radius:10px;padding:12px 14px;font-size:13px;color:#72551a}@media(max-width:900px){.layout{grid-template-columns:1fr}.preview{position:static}.grid,.colors{grid-template-columns:1fr}}@media(max-width:560px){.wrap{padding:22px 14px 50px}.top{position:static}.topin,.intro{flex-direction:column}.kaart{padding:17px}.preview-links{display:none}}
</style></head><body>
<header class="top"><div class="topin"><a href="./">← Terug naar beheer</a><a href="../index.html" target="_blank" rel="noopener">Bekijk website ↗</a></div></header>
<main class="wrap"><div class="intro"><div><h1>Instellingen & huisstijl</h1><p>De identiteit van de vereniging en de uitstraling van de publieke website.</p></div></div>
<?php if($melding!==''):?><div class="melding <?=insEsc($type)?>"><?=insEsc($melding)?></div><?php endif;?>
<?php if(!$extern):?><div class="waarschuwing">Deze installatie draait in standalone-compatibiliteitsmodus. Bewerkbare tenantinstellingen zijn hier bewust uitgeschakeld.</div><?php endif;?>
<form method="post" enctype="multipart/form-data" id="settings-form"><input type="hidden" name="csrf" value="<?=insEsc($csrfToken)?>"><div class="layout"><div>
<section class="kaart"><h2>Verenigingsidentiteit</h2><p>Deze gegevens worden gebruikt in logo-teksten, paginatitels, footer en publieke formulieren.</p><div class="grid">
<div class="veld"><label for="naam">Korte naam</label><input id="naam" name="naam" maxlength="80" required value="<?=insEsc($naam)?>"><div class="hint">Bijvoorbeeld: Tennisclub Parkzicht</div></div>
<div class="veld"><label for="volledige_naam">Volledige naam</label><input id="volledige_naam" name="volledige_naam" maxlength="140" required value="<?=insEsc($volledig)?>"></div>
<div class="veld full"><label for="slogan">Slogan / ondertitel</label><input id="slogan" name="slogan" maxlength="140" value="<?=insEsc($slogan)?>"></div>
</div></section>
<section class="kaart"><h2>Logo & favicon</h2><p>PNG, JPG of WebP. Uploads staan geïsoleerd in de private tenantopslag en worden via een read-only gateway gepubliceerd.</p><div class="grid">
<div class="upload"><div class="veld"><label for="logo">Logo</label><input id="logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp"><div class="hint">Maximaal 5 MB. Transparante PNG werkt meestal het mooist.</div></div><?php if($logo!==''):?><label class="check"><input type="checkbox" name="logo_verwijderen" value="1"> Huidig logo verwijderen</label><?php endif;?></div>
<div class="upload"><div class="veld"><label for="favicon">Favicon</label><input id="favicon" type="file" name="favicon" accept="image/png,image/jpeg,image/webp"><div class="hint">Maximaal 1 MB. Vierkant bestand aanbevolen.</div></div><?php if($favicon!==''):?><label class="check"><input type="checkbox" name="favicon_verwijderen" value="1"> Huidige favicon verwijderen</label><?php endif;?></div>
</div></section>
<section class="kaart"><h2>Kleuren</h2><p>De kleuren worden direct als CSS-variabelen toegepast. Menu, knoppen, accenten en pagina-achtergronden bewegen mee.</p><div class="colors">
<?php $labels=['primary'=>['Hoofdkleur','Knoppen en actieve menu-items'],'primary_dark'=>['Hoofdkleur donker','Hover en sterke accenten'],'primary_light'=>['Hoofdkleur licht','Zachte vlakken en hover'],'accent'=>['Accentkleur','Labels en highlights'],'accent_light'=>['Accentkleur licht','Meldingen en subtiele vlakken'],'dark'=>['Donkere kleur','Hero en sterke tekst'],'text'=>['Tekstkleur','Normale tekst'],'muted'=>['Gedempte tekst','Subtekst en labels'],'background'=>['Pagina-achtergrond','Algemene achtergrond'],'nav_background'=>['Menu-achtergrond','Navigatiebalk'],'nav_text'=>['Menu-tekst','Links en verenigingsnaam']];foreach($labels as $k=>$lab):?><label class="color"><input type="color" name="kleur_<?=$k?>" value="<?=insEsc($kleuren[$k])?>" data-color="<?=$k?>"><span><strong><?=insEsc($lab[0])?></strong><small><?=insEsc($lab[1])?></small></span></label><?php endforeach;?>
</div></section>
<section class="kaart"><h2>Betaalgegevens lidmaatschap</h2><p>Deze gegevens worden gebruikt op de aanmeld- en bedankt-pagina. Laat IBAN leeg als contributie niet via bankoverschrijving loopt.</p><div class="grid">
<div class="veld"><label for="iban">IBAN</label><input id="iban" name="iban" maxlength="40" autocomplete="off" value="<?=insEsc($betaling['iban']??'')?>"></div>
<div class="veld"><label for="tenaamstelling">Tenaamstelling</label><input id="tenaamstelling" name="tenaamstelling" maxlength="120" value="<?=insEsc($betaling['tenaamstelling']??$volledig)?>"></div>
<div class="veld full"><label for="omschrijving">Betalingsomschrijving</label><input id="omschrijving" name="omschrijving" maxlength="160" value="<?=insEsc($betaling['omschrijving']??'Contributie {jaar} - {naam}')?>"><div class="hint">Gebruik <code>{jaar}</code> en <code>{naam}</code> als dynamische waarden.</div></div>
</div></section>
<button class="btn" type="submit"<?=$extern?'':' disabled'?>>Instellingen opslaan</button>
</div><aside>
<section class="kaart preview" id="preview" style="--pv-primary:<?=insEsc($kleuren['primary'])?>;--pv-accent:<?=insEsc($kleuren['accent'])?>;--pv-dark:<?=insEsc($kleuren['dark'])?>;--pv-text:<?=insEsc($kleuren['text'])?>;--pv-bg:<?=insEsc($kleuren['background'])?>;--pv-nav:<?=insEsc($kleuren['nav_background'])?>;--pv-navtext:<?=insEsc($kleuren['nav_text'])?>"><div class="preview-nav"><div class="preview-brand"><?php if($previewLogo!==''):?><img id="preview-logo" src="<?=insEsc($previewLogo)?>" alt=""><?php else:?><span id="preview-logo-placeholder">◆</span><?php endif;?><span id="preview-name"><?=insEsc($naam)?></span></div><div class="preview-links"><span>Over ons</span><span>Contact</span><span>Lid worden</span></div></div><div class="preview-hero"><h3 id="preview-title"><?=insEsc($naam)?></h3><p id="preview-slogan"><?=insEsc($slogan!==''?$slogan:'Jouw vereniging, jouw uitstraling.')?></p><span class="preview-button">Bekijk activiteiten</span></div><div class="preview-body"><div class="preview-card"><strong>Live voorbeeld</strong><br><small>Kleuren en identiteit worden hier direct weergegeven.</small></div></div></section>
<section class="kaart linkskaart"><h2>Meer website-instellingen</h2><p>Inhoud blijft in de daarvoor bedoelde modules, zodat huisstijl en content niet door elkaar lopen.</p><a href="contact.php">Contact, adres & openingstijden →</a><a href="content.php?pagina=homepage">Homepage-teksten →</a><a href="lidmaatschap.php">Lidmaatschapstypen & tarieven →</a><a href="sponsors.php">Sponsors →</a></section>
</aside></div></form></main>
<script>
(function(){var form=document.getElementById('settings-form'),p=document.getElementById('preview');if(!form||!p)return;function v(n){var e=form.elements[n];return e?e.value:'';}function upd(){document.getElementById('preview-name').textContent=v('naam')||'Vereniging';document.getElementById('preview-title').textContent=v('naam')||'Vereniging';document.getElementById('preview-slogan').textContent=v('slogan')||'Jouw vereniging, jouw uitstraling.';var map={primary:'--pv-primary',accent:'--pv-accent',dark:'--pv-dark',text:'--pv-text',background:'--pv-bg',nav_background:'--pv-nav',nav_text:'--pv-navtext'};Object.keys(map).forEach(function(k){var e=form.elements['kleur_'+k];if(e)p.style.setProperty(map[k],e.value);});}form.addEventListener('input',upd);var logo=document.getElementById('logo');if(logo)logo.addEventListener('change',function(){var f=logo.files&&logo.files[0];if(!f)return;var img=document.getElementById('preview-logo');if(!img){img=document.createElement('img');img.id='preview-logo';var ph=document.getElementById('preview-logo-placeholder');if(ph)ph.replaceWith(img);}img.src=URL.createObjectURL(f);});upd();})();
</script></body></html>
