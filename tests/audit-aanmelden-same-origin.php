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

$external = <<<'HTML'
<form id="aanmeld-form" action="https://example.invalid/external" method="POST">
<script>
const form = document.getElementById('aanmeld-form');
async function verstuur() {
  await fetch(form.action, {
    method: 'POST',
    body: new FormData(form),
    headers: { 'Accept': 'application/json' }
  });
}
</script>
HTML;

$hard = siteAanmeldenSameOriginOutput($external);
c159(!str_contains($hard, 'https://example.invalid/external'), 'gerenderde aanmeldoutput bevat geen externe formulieractie');
c159(str_contains($hard, 'action="aanmelden-ontvangst.php"'), 'formulieractie wordt same-origin lokale intake');
c159(substr_count($hard, 'fetch(form.action') === 1, 'primaire fetch blijft exact één keer bestaan');
c159(substr_count($hard, 'aanmelden-ontvangst.php') === 1, 'gerenderde flow bevat exact één lokale intakebestemming');

$local = str_replace('https://example.invalid/external', 'aanmelden-ontvangst.php', $external);
$localHard = siteAanmeldenSameOriginOutput($local);
c159(substr_count($localHard, 'aanmelden-ontvangst.php') === 1, 'reeds lokale output houdt exact één intakebestemming');

$source = (string) file_get_contents($root . '/aanmelden.php');
c159(str_contains($source, '<form id="aanmeld-form" action="aanmelden-ontvangst.php"'), 'aanmeldbron post rechtstreeks naar lokale intake');
c159(substr_count($source, 'fetch(form.action') === 1, 'aanmeldbron bevat exact één primaire browser-POST');
c159(!str_contains($source, "fetch('aanmelden-ontvangst.php'"), 'aanmeldbron bevat geen tweede best-effort lokale POST');

$seo = (string) file_get_contents($root . '/app/content/seo-head.php');
c159(str_contains($seo, "if (\$pagina === 'aanmelden') siteAanmeldenSameOriginOutputStart();"), 'aanmeldpagina activeert centrale outputhardening');

$siteConfig = (string) file_get_contents($root . '/site-config.php');
c159(
    str_contains($siteConfig, '$formAction = "\'self\'";')
    && str_contains($siteConfig, 'form-action {$formAction}'),
    'CSP form-action blijft same-origin'
);

echo "Issue #159 same-origin aanmeldflow: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
