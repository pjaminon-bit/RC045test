<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function c155(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++; fwrite(STDERR, "FOUT: {$label}\n");
}

function wis155(string $pad): void
{
    if (is_link($pad) || is_file($pad)) { @chmod($pad, 0640); @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        wis155($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}

require_once $root . '/app/core/tenant-settings.php';
require_once $root . '/app/core/tenant-branding-assets.php';

$tmp = sys_get_temp_dir() . '/issue155-settings-' . bin2hex(random_bytes(5));
$private = $tmp . '/private';
$settingsDir = $private . '/settings';
$settingsPad = $settingsDir . '/site.json';
@mkdir($settingsDir, 0750, true);

$config = [
    'vereniging' => ['sleutel'=>'issue155','naam'=>'Testvereniging','volledige_naam'=>'Testvereniging','site_url'=>'https://test.example'],
    'branding' => ['logo'=>'','favicon'=>'','kleuren'=>[],'afbeeldingen'=>[]],
    'betaling' => [],
    'opslag' => ['private_root'=>$private],
];
$input = [
    'vereniging' => ['naam'=>'Testvereniging','volledige_naam'=>'Testvereniging Nederland','slogan'=>'Veilig beheer'],
    'branding' => ['logo'=>'','favicon'=>'','theme_color'=>'#112233','kleuren'=>['primary'=>'#345678']],
    'betaling' => ['iban'=>'NL91ABNA0417164300','tenaamstelling'=>'Testvereniging Nederland','omschrijving'=>'Contributie {jaar} - {naam}'],
];

try {
    c155(tenantSettingsLees($config) === [], 'ontbrekend site.json blijft geldige eerste configuratie');
    c155(tenantSettingsSchrijf($config, $input), 'eerste geldige settingswrite slaagt');
    $geldig = tenantSettingsLees($config);
    c155(($geldig['vereniging']['naam'] ?? '') === 'Testvereniging', 'geldige settings worden normaal gelezen');

    $scenarios = [
        'corrupte JSON' => "{\"vereniging\":",
        'niet-array JSON' => '"geen-document"',
        'normalisatie-failure' => json_encode(['vereniging'=>['naam'=>'RC045 kopie']], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
    foreach ($scenarios as $naam => $bytes) {
        @file_put_contents($settingsPad, (string)$bytes);
        $voor = (string)file_get_contents($settingsPad);
        $hard = false;
        try { tenantSettingsLees($config); }
        catch (TenantSettingsStorageException $e) { $hard = true; }
        c155($hard, $naam . ' faalt hard bij lezen');
        c155((string)file_get_contents($settingsPad) === $voor, $naam . ' blijft byte-identiek na readfout');

        $writeHard = false;
        try { tenantSettingsSchrijf($config, $input); }
        catch (TenantSettingsStorageException $e) { $writeHard = true; }
        c155($writeHard, $naam . ' blokkeert normale settingswrite');
        c155((string)file_get_contents($settingsPad) === $voor, $naam . ' blijft byte-identiek na geweigerde write');
    }

    @file_put_contents($settingsPad, json_encode($geldig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    @chmod($settingsPad, 0000);
    clearstatcache(true, $settingsPad);
    c155(!is_readable($settingsPad), 'testfixture maakt bestaand settingsbestand aantoonbaar onleesbaar');
    $onleesbaarHard = false;
    try { tenantSettingsLees($config); }
    catch (TenantSettingsStorageException $e) { $onleesbaarHard = true; }
    c155($onleesbaarHard, 'bestaand onleesbaar settingsbestand faalt hard');
    @chmod($settingsPad, 0640);
    clearstatcache(true, $settingsPad);

    @file_put_contents($settingsPad, '{"vereniging":');
    $brandingRoot = tenantBrandingAssetRoot($config);
    if ($brandingRoot !== null) wis155($brandingRoot);
    $brandingHard = false;
    try { tenantBrandingAssetTransactieBegin($config); }
    catch (TenantSettingsStorageException $e) { $brandingHard = true; }
    c155($brandingHard, 'corrupte settings blokkeren brandingtransactie vóór mutatie');
    c155($brandingRoot !== null && !file_exists($brandingRoot), 'brandingroot wordt bij corrupte settings niet aangemaakt of gewijzigd');

    tenantSettingsRuntimeLeesfoutMarkeer();
    c155(tenantSettingsRuntimeHeeftLeesfout(), 'runtime kan corrupt-state expliciet aan beheer doorgeven');
    c155(str_contains(tenantSettingsHerstelMelding(), 'geblokkeerd') && str_contains(tenantSettingsHerstelMelding(), 'Herstel'), 'beheerherstelmelding is expliciet en actiegericht');

    $settingsBron = (string)file_get_contents($root . '/app/core/tenant-settings.php');
    $siteConfigBron = (string)file_get_contents($root . '/site-config.php');
    $beheerBron = (string)file_get_contents($root . '/beheer/instellingen.php');
    $brandingBron = (string)file_get_contents($root . '/app/core/tenant-branding-assets.php');

    c155(str_contains($settingsBron, 'if (!file_exists($pad)) return [];'), 'alleen werkelijk ontbrekend site.json gebruikt lege semantiek');
    c155(str_contains($settingsBron, "tenantSettingsStorageFout('settingsbestand is niet leesbaar')"), 'onleesbaar bestand heeft expliciet fail-closed pad');
    c155(str_contains($settingsBron, 'JSON_THROW_ON_ERROR') && str_contains($settingsBron, 'settingsdocument faalt normalisatie'), 'parse- en normalisatiefouten zijn expliciet fail-closed');
    $readPos = strpos($settingsBron, '$huidig = tenantSettingsLees($basisConfig);');
    $mapPos = strpos($settingsBron, '$map = dirname($pad);');
    c155($readPos !== false && $mapPos !== false && $readPos < $mapPos, 'settingswrite valideert bestaande state vóór filesystemmutaties');
    c155(str_contains($siteConfigBron, 'catch (TenantSettingsStorageException $e)') && str_contains($siteConfigBron, 'tenantSettingsRuntimeLeesfoutMarkeer();'), 'publieke runtime onderscheidt corrupt-state zonder die als normale lege settings te behandelen');
    c155(str_contains($beheerBron, '$settingsHerstelNodig = $extern && tenantSettingsRuntimeHeeftLeesfout();'), 'beheer herkent expliciete corrupt/recoverystate');
    $blokPos = strpos($beheerBron, 'elseif ($settingsHerstelNodig)');
    $uploadPos = strpos($beheerBron, 'tenantBrandingAssetUpload(');
    c155($blokPos !== false && $uploadPos !== false && $blokPos < $uploadPos, 'beheer blokkeert corrupte state vóór brandingupload');
    c155(str_contains($beheerBron, 'settings/site.json') && str_contains($beheerBron, 'read-only'), 'beheer toont duidelijke recoverymelding');
    $brandingReadPos = strpos($brandingBron, 'tenantSettingsLees($config);');
    $brandingTxPos = strpos($brandingBron, 'atomicFileTxBegin([$root])');
    c155($brandingReadPos !== false && $brandingTxPos !== false && $brandingReadPos < $brandingTxPos, 'brandingtransactie preflight settings vóór snapshot/mutatie');
} finally {
    @chmod($settingsPad, 0640);
    wis155($tmp);
}

echo "Issue #155 tenant settings fail-closed: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
