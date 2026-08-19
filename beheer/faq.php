<?php
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/app/data-slot.php';
require_once dirname(__DIR__) . '/app/beheer/editor-hulp.php';

if (!$ingelogd) { header('Location: ../beheer.php'); exit; }
$rechten = authRechten(['faq' => 'Vragen'], []);
if (!$isMaster && !in_array('faq', $rechten['toegestaneTabs'] ?? [], true)) { http_response_code(403); echo 'Geen toegang tot Vragen.'; exit; }

$bestand = dirname(__DIR__) . '/data/faq.json';
$standaard = [
    ['q'=>['nl'=>'Wanneer ben ik officieel lid?','en'=>'When am I officially a member?','de'=>'Wann bin ich offiziell Mitglied?'],'a'=>['nl'=>'Je bent officieel lid zodra je aanmelding is bevestigd door het bestuur én de contributie is ontvangen op onze bankrekening. Je ontvangt dan een bevestiging per e-mail of via de WhatsApp groep.','en'=>'You are officially a member once your registration has been confirmed by the board and the membership fee has been received in our bank account. You will then receive a confirmation by email or via the WhatsApp group.','de'=>'Du bist offiziell Mitglied, sobald deine Anmeldung vom Vorstand bestätigt wurde und der Mitgliedsbeitrag auf unserem Konto eingegangen ist. Du erhältst dann eine Bestätigung per E-Mail oder über die WhatsApp-Gruppe.']],
    ['q'=>['nl'=>'Hoe bereken ik mijn contributie?','en'=>'How is my membership fee calculated?','de'=>'Wie wird mein Mitgliedsbeitrag berechnet?'],'a'=>['nl'=>'De contributie wordt berekend op basis van de maand waarin je je aanmeldt. Je betaalt voor de resterende maanden van het jaar. De exacte berekening zie je automatisch zodra je je geboortedatum invult.','en'=>'The fee is calculated based on the month you register. You pay for the remaining months of the year. The exact amount is shown automatically once you enter your date of birth.','de'=>'Der Beitrag wird anhand des Monats berechnet, in dem du dich anmeldest. Du zahlst für die verbleibenden Monate des Jahres. Den genauen Betrag siehst du automatisch, sobald du dein Geburtsdatum eingibst.']],
    ['q'=>['nl'=>'Wat als ik later in het jaar lid word?','en'=>'What if I join later in the year?','de'=>'Was ist, wenn ich erst später im Jahr beitrete?'],'a'=>['nl'=>'Dan betaal je een pro-rata bedrag voor de resterende maanden. Schrijf je in december in? Dan betaal je alleen de eenmalige inschrijfkosten van €10; de volledige contributie voor het volgende jaar hoeft dan nog niet te worden overgemaakt.','en'=>'You pay a pro-rata amount for the remaining months. Joining in December? Then you only pay the one-time registration fee of €10; the full membership fee for the following year does not need to be transferred yet.','de'=>'Du zahlst dann einen anteiligen Betrag für die verbleibenden Monate. Wenn du im Dezember beitrittst, zahlst du nur die einmalige Anmeldegebühr von €10; der volle Mitgliedsbeitrag für das nächste Jahr muss dann noch nicht überwiesen werden.']],
    ['q'=>['nl'=>'Moet ik elk jaar opnieuw betalen?','en'=>'Do I need to pay every year?','de'=>'Muss ich jedes Jahr erneut zahlen?'],'a'=>['nl'=>'Ja, de contributie wordt jaarlijks geïnd. Je ontvangt hierover tijdig bericht via de WhatsApp groep of nieuwsbrief.','en'=>'Yes, membership fees are collected annually. You will be notified in time via the WhatsApp group or newsletter.','de'=>'Ja, der Mitgliedsbeitrag wird jährlich erhoben. Du wirst rechtzeitig über die WhatsApp-Gruppe oder den Newsletter informiert.']],
    ['q'=>['nl'=>'Kan ik eerst komen kijken voor ik lid word?','en'=>'Can I come and have a look before joining?','de'=>'Kann ich erst vorbeischauen, bevor ich Mitglied werde?'],'a'=>['nl'=>'Ja, je kunt altijd eerst als gastrijder langskomen. Volwassenen betalen €10, jeugd t/m 15 jaar betaalt €5 per dag. Meld je bij aankomst bij een bestuurslid.','en'=>'Yes, you can always come as a guest rider first. Adults pay €10, youth up to 15 years pay €5 per day. Check in with a board member on arrival.','de'=>'Ja, du kannst jederzeit als Gastfahrer vorbeikommen. Erwachsene zahlen €10, Jugendliche bis 15 Jahre zahlen €5 pro Tag. Melde dich bei einem Vorstandsmitglied.']],
];

$data = beheerEditorLeesJson($bestand, $standaard);
$melding=''; $type='';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!csrfOk()) { $melding='Sessie verlopen. Ververs de pagina en probeer opnieuw.'; $type='fout'; }
    else {
        $items=[];
        foreach ((array)($_POST['faq'] ?? []) as $rij) {
            if (!is_array($rij)) continue;
            $qNl=beheerEditorKort($rij['q_nl'] ?? '',150);
            if ($qNl==='') continue;
            $items[]=[
                'q'=>['nl'=>$qNl,'en'=>beheerEditorKort($rij['q_en'] ?? '',150),'de'=>beheerEditorKort($rij['q_de'] ?? '',150)],
                'a'=>['nl'=>beheerEditorKort($rij['a_nl'] ?? '',600),'en'=>beheerEditorKort($rij['a_en'] ?? '',600),'de'=>beheerEditorKort($rij['a_de'] ?? '',600)],
            ];
        }
        $slot=dataSlotOpen();
        try { $ok=beheerEditorSchrijfJson($bestand,$items); } finally { dataSlotDicht($slot); }
        if ($ok) { $data=$items; $melding='Vragen opgeslagen.'; $type='ok'; schrijfLog($logBestand,$huidigeGebruiker,'faq',count($items).' vraag/vragen opgeslagen'); }
        else { $melding='Opslaan mislukt.'; $type='fout'; }
    }
}
$data[]=['q'=>['nl'=>'','en'=>'','de'=>''],'a'=>['nl'=>'','en'=>'','de'=>'']];
?><!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Vragen beheren</title>
<style>body{margin:0;background:#f6f2e8;color:#26351d;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}.top{background:#fff;border-bottom:1px solid #ddd8c0;padding:16px 24px}.topin,.wrap{max-width:1180px;margin:auto}.topin{display:flex;justify-content:space-between}.top a{color:#2d6260;text-decoration:none;font-weight:700}.wrap{padding:30px 24px 70px}.kaart{background:#fff;border:1px solid #ddd8c0;border-radius:14px;padding:22px;margin-bottom:18px}.talen{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.taal label{display:block;font-weight:700;font-size:12px;margin:8px 0 5px}.taal input,.taal textarea{width:100%;box-sizing:border-box;border:1px solid #cfcab7;border-radius:8px;padding:10px;font:inherit}.taal textarea{min-height:95px}.melding{padding:12px 14px;border-radius:9px;margin-bottom:18px}.ok{background:#e8f5ee;color:#205b38}.fout{background:#fdeceb;color:#8b2e27}.btn{background:#3a7a77;color:#fff;border:0;border-radius:9px;padding:11px 18px;font-weight:700;cursor:pointer}.hint{color:#68705f}@media(max-width:800px){.talen{grid-template-columns:1fr}}</style></head><body>
<div class="top"><div class="topin"><a href="./">← Terug naar beheer</a><a href="../aanmelden.html#faq" target="_blank" rel="noopener">Bekijk FAQ ↗</a></div></div>
<main class="wrap"><h1>Vragen</h1><p class="hint">Leeg de Nederlandse vraag en sla op om een item te verwijderen. Onderaan staat altijd één leeg blok voor een nieuwe vraag.</p>
<?php if($melding!==''):?><div class="melding <?=beheerEditorEsc($type)?>"><?=beheerEditorEsc($melding)?></div><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=beheerEditorEsc($csrfToken)?>">
<?php foreach($data as $i=>$item):?><section class="kaart"><h2>Vraag <?=($i+1)?></h2><div class="talen"><?php foreach(['nl'=>'Nederlands','en'=>'Engels','de'=>'Duits'] as $t=>$label):?><div class="taal"><label><?=beheerEditorEsc($label)?><?=$t==='nl'?'':' (optioneel)'?></label><input name="faq[<?=$i?>][q_<?=$t?>]" maxlength="150" value="<?=beheerEditorEsc($item['q'][$t]??'')?>" placeholder="Vraag"><label>Antwoord</label><textarea name="faq[<?=$i?>][a_<?=$t?>]" maxlength="600"><?=beheerEditorEsc($item['a'][$t]??'')?></textarea></div><?php endforeach;?></div></section><?php endforeach;?>
<button class="btn" type="submit">Vragen opslaan</button></form></main></body></html>
