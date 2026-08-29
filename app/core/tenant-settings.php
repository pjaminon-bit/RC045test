<?php
// ============================================================
// Bewerkbare tenantinstellingen
// ============================================================
// Dagelijkse huisstijl- en verenigingsinstellingen staan voor externe tenants
// onder private_root/settings/site.json. Alleen deze expliciete whitelist kan
// de server-only basisconfig overrulen; infrastructuur, database, tenant-key en
// modulegrenzen blijven daardoor buiten bereik van het webbeheer.
// ============================================================
require_once __DIR__ . '/tenant-runtime.php';

function tenantSettingsPad(array $config): ?string
{
    $privateRoot = tenantRuntimePrivateRoot($config);
    if ($privateRoot === null) return null;
    return rtrim($privateRoot, '/\\') . DIRECTORY_SEPARATOR . 'settings' . DIRECTORY_SEPARATOR . 'site.json';
}

function tenantSettingsBevatLegacy($waarde): bool
{
    if (!is_scalar($waarde) && !is_array($waarde)) return false;
    $tekst = is_array($waarde) ? (json_encode($waarde, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '') : (string)$waarde;
    $tekst = strtolower($tekst);
    foreach ([
        'rc045', 'bashers of the south', 'eygelshoven', 'wijngaardsberg',
        'bestuur@rc045.nl', 'facebook.com/rc045', 'kok lexmond',
        'nl51 rabo 0367 6153 63', 'pjaminon@me.com',
    ] as $vingerafdruk) {
        if (str_contains($tekst, $vingerafdruk)) return true;
    }
    return false;
}

function tenantSettingsTekst($waarde, int $max = 160): string
{
    $tekst = trim(is_scalar($waarde) ? (string)$waarde : '');
    if (str_contains($tekst, "\0")) return '';
    return function_exists('mb_substr') ? mb_substr($tekst, 0, $max, 'UTF-8') : substr($tekst, 0, $max);
}

function tenantSettingsKleur($waarde, string $fallback): string
{
    $kleur = strtoupper(tenantSettingsTekst($waarde, 7));
    return preg_match('/^#[0-9A-F]{6}$/D', $kleur) === 1 ? $kleur : $fallback;
}

function tenantSettingsAsset($waarde): string
{
    $asset = tenantSettingsTekst($waarde, 300);
    if ($asset === '') return '';
    if (preg_match('~^(?:public-asset\.php\?scope=branding&path=|branding-asset\.php\?name=)[A-Za-z0-9._-]+$~D', $asset) === 1) return $asset;
    if (filter_var($asset, FILTER_VALIDATE_URL) !== false && in_array(strtolower((string)parse_url($asset, PHP_URL_SCHEME)), ['http','https'], true)) return $asset;
    return '';
}

function tenantSettingsIban($waarde): string
{
    $iban = strtoupper((string)preg_replace('/\s+/', '', tenantSettingsTekst($waarde, 40)));
    if ($iban === '') return '';
    if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{10,30}$/D', $iban) !== 1) return '';
    return trim(chunk_split($iban, 4, ' '));
}

function tenantSettingsNormaliseer(array $input, array $huidig = []): array
{
    $standaardKleuren = [
        'primary'=>'#3A7A77','primary_dark'=>'#2D6260','primary_light'=>'#EAF4F3',
        'accent'=>'#C89A1A','accent_light'=>'#FBF4DF','dark'=>'#1E2C13',
        'text'=>'#2A3818','muted'=>'#6A7560','background'=>'#FAF6EC',
        'nav_background'=>'#FFFFFF','nav_text'=>'#2A3818',
    ];
    $beeldSleutels = ['hero','about','activity','gallery'];

    $verenigingIn = is_array($input['vereniging'] ?? null) ? $input['vereniging'] : [];
    $brandingIn = is_array($input['branding'] ?? null) ? $input['branding'] : [];
    $kleurenIn = is_array($brandingIn['kleuren'] ?? null) ? $brandingIn['kleuren'] : [];
    $beeldenIn = is_array($brandingIn['afbeeldingen'] ?? null) ? $brandingIn['afbeeldingen'] : [];
    $betalingIn = is_array($input['betaling'] ?? null) ? $input['betaling'] : [];

    $huidigBranding = is_array($huidig['branding'] ?? null) ? $huidig['branding'] : [];
    $huidigBeelden = is_array($huidigBranding['afbeeldingen'] ?? null) ? $huidigBranding['afbeeldingen'] : [];
    $logo = array_key_exists('logo', $brandingIn) ? tenantSettingsAsset($brandingIn['logo']) : tenantSettingsAsset($huidigBranding['logo'] ?? '');
    $favicon = array_key_exists('favicon', $brandingIn) ? tenantSettingsAsset($brandingIn['favicon']) : tenantSettingsAsset($huidigBranding['favicon'] ?? '');

    $resultaat = [
        'vereniging' => [
            'naam' => tenantSettingsTekst($verenigingIn['naam'] ?? '', 80),
            'volledige_naam' => tenantSettingsTekst($verenigingIn['volledige_naam'] ?? '', 140),
            'slogan' => tenantSettingsTekst($verenigingIn['slogan'] ?? '', 140),
        ],
        'branding' => [
            'logo' => $logo,
            'social_image' => $logo,
            'favicon' => $favicon,
            'theme_color' => tenantSettingsKleur($brandingIn['theme_color'] ?? ($kleurenIn['dark'] ?? ''), $standaardKleuren['dark']),
            'kleuren' => [],
            'afbeeldingen' => [],
        ],
        'betaling' => [
            'iban' => tenantSettingsIban($betalingIn['iban'] ?? ''),
            'tenaamstelling' => tenantSettingsTekst($betalingIn['tenaamstelling'] ?? '', 120),
            'omschrijving' => tenantSettingsTekst($betalingIn['omschrijving'] ?? '', 160),
        ],
        'updated' => date('c'),
    ];

    foreach ($standaardKleuren as $sleutel => $fallback) {
        $resultaat['branding']['kleuren'][$sleutel] = tenantSettingsKleur($kleurenIn[$sleutel] ?? '', $fallback);
    }
    foreach ($beeldSleutels as $sleutel) {
        $resultaat['branding']['afbeeldingen'][$sleutel] = array_key_exists($sleutel, $beeldenIn)
            ? tenantSettingsAsset($beeldenIn[$sleutel])
            : tenantSettingsAsset($huidigBeelden[$sleutel] ?? '');
    }

    if ($resultaat['vereniging']['naam'] === '') $resultaat['vereniging']['naam'] = 'Vereniging';
    if ($resultaat['vereniging']['volledige_naam'] === '') $resultaat['vereniging']['volledige_naam'] = $resultaat['vereniging']['naam'];
    if ($resultaat['betaling']['tenaamstelling'] === '') $resultaat['betaling']['tenaamstelling'] = $resultaat['vereniging']['volledige_naam'];
    if ($resultaat['betaling']['omschrijving'] === '') $resultaat['betaling']['omschrijving'] = 'Contributie {jaar} - {naam}';

    if (tenantSettingsBevatLegacy($resultaat)) {
        throw new InvalidArgumentException('Instellingen bevatten gegevens van de oorspronkelijke voorbeeldvereniging.');
    }
    return $resultaat;
}

function tenantSettingsLees(array $config): array
{
    $pad = tenantSettingsPad($config);
    if ($pad === null || !is_file($pad) || !is_readable($pad) || is_link($pad)) return [];
    $raw = @file_get_contents($pad);
    if ($raw === false) return [];
    try { $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { error_log('[platform] tenant settings onleesbaar'); return []; }
    if (!is_array($data)) return [];
    try { return tenantSettingsNormaliseer($data, $data); }
    catch (Throwable $e) { error_log('[platform] tenant settings geweigerd'); return []; }
}

function tenantSettingsSchrijf(array $basisConfig, array $input): bool
{
    $pad = tenantSettingsPad($basisConfig);
    if ($pad === null) return false;
    $privateRoot = tenantRuntimePrivateRoot($basisConfig);
    if ($privateRoot === null || !is_dir($privateRoot) || is_link($privateRoot)) return false;

    $map = dirname($pad);
    if (is_link($map)) return false;
    if (!is_dir($map) && !@mkdir($map, 0750, true)) return false;
    clearstatcache(true, $map);
    if (!is_dir($map) || is_link($map)) return false;
    @chmod($map, 0750);

    $huidig = tenantSettingsLees($basisConfig);
    $data = tenantSettingsNormaliseer($input, $huidig);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) return false;

    try { $suffix = bin2hex(random_bytes(6)); }
    catch (Throwable $e) { $suffix = substr(hash('sha256', (string)microtime(true)), 0, 12); }
    $tmp = $pad . '.tmp.' . $suffix;
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    @chmod($tmp, 0640);
    if (!@rename($tmp, $pad)) { @unlink($tmp); return false; }
    @chmod($pad, 0640);
    return true;
}
