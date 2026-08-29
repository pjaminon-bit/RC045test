<?php
// ============================================================
// Generieke contentpagina-hulpfuncties
// ============================================================

require_once __DIR__ . '/public-content-store.php';
require_once __DIR__ . '/tenant-content-policy.php';

function contentPaginaDefinities(): array
{
    static $definities = null;
    if ($definities === null) {
        $geladen = require __DIR__ . '/pagina-definities.php';
        $definities = is_array($geladen) ? $geladen : [];
    }
    return $definities;
}

function contentPaginaDefinitie(string $sleutel): array
{
    $alles = contentPaginaDefinities();
    return isset($alles[$sleutel]) && is_array($alles[$sleutel]) ? $alles[$sleutel] : [];
}

function contentPaginaBestaat(string $sleutel): bool
{
    return contentPaginaDefinitie($sleutel) !== [];
}

function contentPaginaRuntimeDefinitie(string $sleutel): array
{
    return tenantContentDefinitieVoorRuntime($sleutel, contentPaginaDefinitie($sleutel), siteNaam());
}

function contentPaginaDataPad(string $sleutel): ?string
{
    $def = contentPaginaDefinitie($sleutel);
    $relatief = trim((string) ($def['data_bestand'] ?? ''));
    if ($relatief === '') return null;

    $tenantPad = publicContentPad($sleutel);
    if ($tenantPad !== null) return $tenantPad;

    // Een nieuwe generieke contentpagina die niet in de publieke contentstore
    // is geregistreerd mag bij een externe tenant nooit ongemerkt naar /data
    // terugvallen. Standalone RC045 houdt de oude compatibiliteit.
    if (publicContentTenantRoot() !== null) {
        tenantRuntimeConfiguratieFout('Contentdataset is niet geregistreerd voor tenantopslag: ' . $sleutel);
    }
    return dirname(__DIR__, 2) . '/' . ltrim($relatief, '/');
}

function contentPaginaJsObjectBlok(string $bron, string $taal): string
{
    $naald = $taal . ': {';
    $pos = strpos($bron, $naald);
    if ($pos === false) return '';
    $start = strpos($bron, '{', $pos);
    if ($start === false) return '';

    $diepte = 0;
    $inString = false;
    $escape = false;
    $lengte = strlen($bron);
    for ($i = $start; $i < $lengte; $i++) {
        $ch = $bron[$i];
        if ($inString) {
            if ($escape) { $escape = false; continue; }
            if ($ch === '\\') { $escape = true; continue; }
            if ($ch === "'") $inString = false;
            continue;
        }
        if ($ch === "'") { $inString = true; continue; }
        if ($ch === '{') { $diepte++; continue; }
        if ($ch === '}') {
            $diepte--;
            if ($diepte === 0) return substr($bron, $start + 1, $i - $start - 1);
        }
    }
    return '';
}

function contentPaginaJsVertalingen(string $blok): array
{
    if ($blok === '') return [];
    $resultaat = [];
    if (!preg_match_all("/'([^']+)'\\s*:\\s*'((?:\\\\.|[^'])*)'/s", $blok, $matches, PREG_SET_ORDER)) return [];
    foreach ($matches as $m) {
        $waarde = stripcslashes($m[2]);
        $resultaat[(string) $m[1]] = $waarde;
    }
    return $resultaat;
}

function contentPaginaHomepageStandaard(): array
{
    static $standaard = null;
    if ($standaard !== null) return $standaard;

    $standaard = [];
    $pad = dirname(__DIR__, 2) . '/homepage.js';
    $bron = is_file($pad) ? @file_get_contents($pad) : false;
    if (!is_string($bron) || $bron === '') return $standaard;

    $perTaal = [];
    foreach (['nl', 'en', 'de'] as $taal) {
        $perTaal[$taal] = contentPaginaJsVertalingen(contentPaginaJsObjectBlok($bron, $taal));
    }

    $def = contentPaginaDefinitie('homepage');
    foreach (array_keys((array) ($def['velden'] ?? [])) as $veld) {
        $i18nSleutel = str_replace('_', '.', (string) $veld);
        $uitzonderingen = [
            'about_photos_title' => 'about.photos.title',
            'guest_note' => 'guest.notes',
            'member_note' => 'member.notes',
            'footer_sponsors_title' => 'footer.sponsors.title',
        ];
        if (isset($uitzonderingen[$veld])) $i18nSleutel = $uitzonderingen[$veld];

        $standaard[$veld] = [];
        foreach (['nl', 'en', 'de'] as $taal) {
            $standaard[$veld][$taal] = (string) ($perTaal[$taal][$i18nSleutel] ?? '');
        }
    }
    return $standaard;
}

function contentPaginaTenantNeutraalOntstaan(string $naam): array
{
    return [
        'hero_sub' => [
            'nl' => 'De geschiedenis van ' . $naam . ' krijgt hier een eigen plek.',
            'en' => 'The history of ' . $naam . ' will have its own place here.',
            'de' => 'Die Geschichte von ' . $naam . ' erhält hier ihren eigenen Platz.',
        ],
        'story_p1' => [
            'nl' => 'Onze vereniging bouwt aan een eigen verhaal. Deze pagina wordt door het bestuur aangevuld met de oorsprong, belangrijke mijlpalen en herinneringen die bij ' . $naam . ' horen.',
            'en' => 'Our association is building its own story. The board will add the origins, important milestones and memories that belong to ' . $naam . ' to this page.',
            'de' => 'Unser Verein schreibt seine eigene Geschichte. Der Vorstand ergänzt diese Seite mit Ursprung, wichtigen Meilensteinen und Erinnerungen rund um ' . $naam . '.',
        ],
        'story_p2' => [
            'nl' => 'Tot die tijd is dit een neutrale startpagina zonder overgenomen geschiedenis van een andere vereniging. Heb je materiaal of foto’s voor het archief, neem dan contact op met het bestuur.',
            'en' => 'Until then, this is a neutral starting page without history copied from another association. If you have material or photos for the archive, please contact the board.',
            'de' => 'Bis dahin ist dies eine neutrale Startseite ohne übernommene Geschichte eines anderen Vereins. Wer Material oder Fotos für das Archiv hat, kann sich an den Vorstand wenden.',
        ],
    ];
}

function contentPaginaTenantNeutraalBaanreglement(string $naam): array
{
    return [
        'hero_sub' => [
            'nl' => 'Regels en afspraken voor een veilige en prettige verenigingsomgeving.',
            'en' => 'Rules and agreements for a safe and pleasant association environment.',
            'de' => 'Regeln und Vereinbarungen für ein sicheres und angenehmes Vereinsumfeld.',
        ],
        'intro_bold' => [
            'nl' => 'Reglement wordt ingericht.',
            'en' => 'Rules are being prepared.',
            'de' => 'Die Regeln werden vorbereitet.',
        ],
        'intro_text' => [
            'nl' => 'Het definitieve reglement van ' . $naam . ' is nog niet gepubliceerd. Het bestuur kan de eigen regels via Beheer vastleggen. Neem bij twijfel over gebruik, veiligheid of toegang altijd contact op met de vereniging.',
            'en' => 'The final rules for ' . $naam . ' have not yet been published. The board can maintain its own rules through the administration area. If in doubt about use, safety or access, always contact the association.',
            'de' => 'Die endgültige Ordnung von ' . $naam . ' ist noch nicht veröffentlicht. Der Vorstand kann die eigenen Regeln im Verwaltungsbereich pflegen. Bei Fragen zu Nutzung, Sicherheit oder Zugang bitte immer den Verein kontaktieren.',
        ],
    ];
}

function contentPaginaStandaard(string $sleutel): array
{
    if (tenantContentIsExtern()) {
        if ($sleutel === 'homepage') return tenantContentNeutraleHomepage(siteNaam());
        if ($sleutel === 'ontstaan') return contentPaginaTenantNeutraalOntstaan(siteNaam());
        if ($sleutel === 'baanreglement') return contentPaginaTenantNeutraalBaanreglement(siteNaam());
    }
    if ($sleutel === 'homepage') return contentPaginaHomepageStandaard();
    $def = contentPaginaDefinitie($sleutel);
    $standaard = $def['standaard'] ?? [];
    $standaard = is_array($standaard) ? $standaard : [];
    return tenantContentStandaardVoorRuntime($sleutel, $standaard, siteNaam());
}

function contentPaginaMengStandaard(array $standaard, array $opgeslagen): array
{
    $resultaat = $standaard;
    foreach ($opgeslagen as $veld => $waarde) {
        if (!array_key_exists($veld, $resultaat)) {
            $resultaat[$veld] = $waarde;
            continue;
        }
        if (!is_array($waarde) || !is_array($resultaat[$veld])) continue;
        foreach (['nl', 'en', 'de'] as $taal) {
            if (isset($waarde[$taal]) && is_scalar($waarde[$taal]) && trim((string) $waarde[$taal]) !== '') {
                $resultaat[$veld][$taal] = (string) $waarde[$taal];
            }
        }
    }
    return $resultaat;
}

function contentPaginaLees(string $sleutel): array
{
    $standaard = contentPaginaStandaard($sleutel);
    $pad = contentPaginaDataPad($sleutel);
    if ($pad === null || !is_file($pad)) return $standaard;

    $json = @file_get_contents($pad);
    if ($json === false) return $standaard;
    $data = json_decode($json, true);
    if (!is_array($data)) return $standaard;
    if (tenantContentIsExtern() && tenantContentBevatLegacy($data)) {
        error_log('[platform] legacy contentpagina-data geweigerd voor externe tenant: ' . $sleutel);
        return $standaard;
    }

    return $standaard ? contentPaginaMengStandaard($standaard, $data) : $data;
}

function contentPaginaWaarde(array $data, string $veld, string $taal = 'nl', string $standaard = ''): string
{
    if (!array_key_exists($veld, $data)) return $standaard;
    $waarde = $data[$veld];
    if (is_array($waarde)) {
        if (isset($waarde[$taal]) && is_scalar($waarde[$taal])) return (string) $waarde[$taal];
        if (isset($waarde['nl']) && is_scalar($waarde['nl'])) return (string) $waarde['nl'];
        return $standaard;
    }
    return is_scalar($waarde) ? (string) $waarde : $standaard;
}

function contentPaginaHero(string $sleutel): array
{
    $def = contentPaginaRuntimeDefinitie($sleutel);
    $hero = $def['hero'] ?? [];
    return is_array($hero) ? $hero : [];
}

function contentPaginaHeroCss(string $sleutel): string
{
    $hero = contentPaginaHero($sleutel);
    $achtergrond = trim((string) ($hero['achtergrond'] ?? ''));
    if ($achtergrond === '') return '';
    $positie = trim((string) ($hero['positie'] ?? 'center')) ?: 'center';
    $opacity = $hero['opacity'] ?? 0.35;
    $opacity = is_numeric($opacity) ? max(0, min(1, (float) $opacity) : 0.35;
    if (preg_match('~^(?:https?:)?//~i', $achtergrond) || str_contains($achtergrond, '..')) return '';
    $url = htmlspecialchars($achtergrond, ENT_QUOTES, 'UTF-8');
    $pos = htmlspecialchars($positie, ENT_QUOTES, 'UTF-8');
    return ".page-hero-bg{background-image:url('{$url}')!important;background-position:{$pos}!important;opacity:{$opacity}!important;}";
}

function contentPaginaSeoSleutel(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    return trim((string) ($def['seo_sleutel'] ?? $sleutel));
}

function contentPaginaBeheerTab(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    return trim((string) ($def['beheer_tab'] ?? $sleutel));
}

function contentPaginaType(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    return trim((string) ($def['type'] ?? 'standaard'));
}

function contentPaginaSleutelVoorRequest(?string $scriptNaam = null): ?string
{
    $scriptNaam = $scriptNaam ?? (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    $bestand = pathinfo(basename($scriptNaam), PATHINFO_FILENAME);
    if ($bestand === '') return null;
    foreach (contentPaginaDefinities() as $sleutel => $def) {
        $slug = trim((string) ($def['slug'] ?? $sleutel));
        if ($bestand === $slug || $bestand === $sleutel) return (string) $sleutel;
    }
    return null;
}

function contentPaginaBootstrap(?string $sleutel = null): array
{
    $sleutel = $sleutel ?? contentPaginaSleutelVoorRequest();
    if ($sleutel === null || !contentPaginaBestaat($sleutel)) return [];
    return [
        'sleutel' => $sleutel,
        'definitie' => contentPaginaRuntimeDefinitie($sleutel),
        'data' => contentPaginaLees($sleutel),
        'hero' => contentPaginaHero($sleutel),
        'seo_sleutel' => contentPaginaSeoSleutel($sleutel),
        'beheer_tab' => contentPaginaBeheerTab($sleutel),
        'type' => contentPaginaType($sleutel),
    ];
}
