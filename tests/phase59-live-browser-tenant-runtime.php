<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c59(bool $conditie, string $label): void
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

require_once $root . '/app/content/content-pagina.php';

$naam = 'Testvereniging';
$ontstaan = contentPaginaTenantNeutraalOntstaan($naam);
$reglement = contentPaginaTenantNeutraalBaanreglement($naam);

$ontstaanNl = (string)($ontstaan['story_p1']['nl'] ?? '') . ' ' . (string)($ontstaan['story_p2']['nl'] ?? '');
$reglementNl = (string)($reglement['intro_text']['nl'] ?? '');

c59(strlen(trim($ontstaanNl)) > 120, 'nieuwe externe tenant krijgt betekenisvolle geschiedenis-startinhoud');
c59(str_contains($ontstaanNl, $naam), 'geschiedenis-startinhoud gebruikt de eigen tenantnaam');
c59(!str_contains($ontstaanNl, 'RC045') && !str_contains($ontstaanNl, 'Bashers of the South'), 'geschiedenis-startinhoud bevat geen voorbeeldvereniging');
c59(strlen(trim($reglementNl)) > 120, 'nieuwe externe tenant krijgt betekenisvolle reglement-startinhoud');
c59(str_contains($reglementNl, $naam), 'reglement-startinhoud gebruikt de eigen tenantnaam');
c59(!str_contains($reglementNl, 'RC045') && !str_contains($reglementNl, 'Bashers of the South'), 'reglement-startinhoud bevat geen voorbeeldvereniging');

$siteConfig = (string)file_get_contents($root . '/site-config.php');
$publicContent = (string)file_get_contents($root . '/public-content.php');
$htaccess = (string)file_get_contents($root . '/.htaccess');

c59(
    str_contains($siteConfig, '$legacyBranding')
    && str_contains($siteConfig, "'logo' => 'rc045-logo.png'")
    && str_contains($siteConfig, "\$config['branding'][\$sleutel] = ''"),
    'externe tenant neutraliseert exact geerfde standalone-branding vóór publieke runtime'
);
c59(
    str_contains($siteConfig, "if (\$externPad !== null)")
    && str_contains($siteConfig, 'tenantconfig zelf verzint geen asset-URL')
    && !str_contains($siteConfig, "\$config['branding']['logo'] = 'images/template-placeholder.svg'"),
    'nieuwe externe tenant houdt lege logo-config en verzint geen placeholder-URL'
);
c59(
    str_contains($publicContent, 'ontbrekende optionele publieke')
    && str_contains($publicContent, '$data = [];'),
    'ontbrekende optionele externe dataset levert lege JSON in plaats van kapotte publieke URL'
);
c59(
    str_contains($publicContent, 'geen fallback naar de')
    && str_contains($publicContent, 'private tenantopslag blijft de enige bron'),
    'lege dataset herintroduceert geen legacy /data-fallback'
);
c59(
    str_contains($htaccess, 'public-content.php?key=$1')
    && str_contains($htaccess, 'homepage|ontstaan|baanreglement'),
    'legacy data-URL blijft via de whitelisted tenant-aware gateway lopen'
);

// Borg ook de concrete live-browserfout: zonder eigen logo mag de code niet
// afhankelijk zijn van een fictief tenantnaam-logo dat niet als bestand bestaat.
c59(!str_contains($siteConfig, 'Testvereniging-logo.png'), 'geen hardcoded of gegenereerde testtenant-logo-URL in configuratie');

echo "Phase 5.9 live-browser tenant runtime: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
