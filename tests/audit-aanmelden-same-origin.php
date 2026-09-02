<?php
$root = dirname(__DIR__);
require_once $root . '/app/content/seo-head.php';

$ok = 0;
$fout = 0;
function c159(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

$legacy = <<<'HTML'
<form id="aanmeld-form" action="https://formspree.io/f/mgobjlkl" method="POST">
<script>
const form = document.getElementById('aanmeld-form');
async function verstuur() {
  const res = await fetch(form.action, {
    method: 'POST',
    body: new FormData(form),
    headers: { 'Accept': 'application/json' }
  });
  if (res.ok) {
        openBedankt();
        // Daarnaast naar onze eigen server, zodat de aanmelding meteen in het
        // ledenbestand komt met de status "in verificatie". Bewust pas hier,
        // na Formspree: gaat dit mis, dan staat de aanmelding nog steeds in de
        // mail aan het bestuur en merkt de bezoeker er niets van.
        fetch('aanmelden-ontvangst.php', {
          method: 'POST',
          body: new FormData(form)
        }).catch(function() { /* stil falen, de mail is al onderweg */ });
  }
}
</script>
HTML;

$hard = siteAanmeldenSameOriginOutput($legacy);
c159(!str_contains($hard, 'formspree.io'), 'rendered aanmeldoutput bevat geen Formspree-endpoint');
c159(str_contains($hard, 'action="aanmelden-ontvangst.php"'), 'formulieractie wordt same-origin lokale intake');
c159(substr_count($hard, 'fetch(form.action') === 1, 'primaire fetch blijft exact één keer bestaan');
c159(!str_contains($hard, "fetch('aanmelden-ontvangst.php'"), 'legacy tweede lokale best-effort fetch is verwijderd');
c159(substr_count($hard, 'aanmelden-ontvangst.php') === 1, 'rendered flow bevat exact één lokale intakebestemming');

$tenant = str_replace('https://formspree.io/f/mgobjlkl', 'aanmelden-ontvangst.php', $legacy);
$tenantHard = siteAanmeldenSameOriginOutput($tenant);
c159(!str_contains($tenantHard, 'formspree.io'), 'tenantoutput blijft vrij van Formspree');
c159(substr_count($tenantHard, 'aanmelden-ontvangst.php') === 1, 'tenantoutput houdt eveneens exact één intakebestemming');
c159(!str_contains($tenantHard, "fetch('aanmelden-ontvangst.php'"), 'tenantoutput verwijdert legacy dubbele POST');

$seo = (string) file_get_contents($root . '/app/content/seo-head.php');
c159(str_contains($seo, "if (\$pagina === 'aanmelden') siteAanmeldenSameOriginOutputStart();"), 'aanmeldpagina activeert centrale outputhardening');

$siteConfig = (string) file_get_contents($root . '/site-config.php');
c159(str_contains($siteConfig, "form-action 'self'"), 'CSP form-action blijft same-origin');
c159(!preg_match("/connect-src[^;]*formspree/i", $siteConfig), 'CSP krijgt geen Formspree-workaround');

echo "Issue #159 same-origin aanmeldflow: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
