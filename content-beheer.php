<?php
// ============================================================
// Generieke beheerlaag voor configureerbare contentpagina's
// ============================================================
// Bouwt beheervelden en opslag rechtstreeks uit pagina-definities.php.
// Hiermee verdwijnen pagina-specifieke opslagblokken uit beheer.php.
// ============================================================

require_once __DIR__ . '/content-pagina.php';

function contentBeheerVelden(string $sleutel): array
{
    $def = contentPaginaDefinitie($sleutel);
    if (!$def) return [];

    $velden = [];
    foreach (($def['velden'] ?? []) as $veld => $info) {
        if (!is_array($info)) continue;
        $velden[(string) $veld] = [
            (string) ($info['label'] ?? $veld),
            (string) ($info['type'] ?? 'tekst'),
        ];
    }

    foreach (($def['artikelen'] ?? []) as $nummer => $artikel) {
        if (!is_array($artikel)) continue;
        $titelVeld = trim((string) ($artikel['titel'] ?? ''));
        $inhoudVeld = trim((string) ($artikel['inhoud'] ?? ''));
        if ($titelVeld !== '') {
            $velden[$titelVeld] = ['Artikel ' . $nummer . ': titel', 'tekst'];
        }
        if ($inhoudVeld !== '') {
            $velden[$inhoudVeld] = ['Artikel ' . $nummer . ': inhoud', 'blok'];
        }
    }

    return $velden;
}

function contentBeheerGroepen(string $sleutel): array
{
    $def = contentPaginaDefinitie($sleutel);
    if (!$def) return [];

    if (($def['type'] ?? '') !== 'artikelen') {
        return ['Inhoud' => array_keys(contentBeheerVelden($sleutel))];
    }

    $groepen = [];
    $intro = array_keys((array) ($def['velden'] ?? []));
    if ($intro) $groepen['Intro'] = $intro;

    foreach (($def['artikelen'] ?? []) as $nummer => $artikel) {
        if (!is_array($artikel)) continue;
        $velden = [];
        foreach (['titel', 'inhoud'] as $soort) {
            $veld = trim((string) ($artikel[$soort] ?? ''));
            if ($veld !== '') $velden[] = $veld;
        }
        if ($velden) $groepen['Artikel ' . $nummer] = $velden;
    }

    return $groepen;
}

function contentBeheerPostPrefix(string $sleutel): string
{
    $def = contentPaginaDefinitie($sleutel);
    $prefix = trim((string) ($def['beheer_prefix'] ?? ''));
    return $prefix !== '' ? $prefix : 'content_' . preg_replace('/[^a-z0-9_\-]/i', '_', $sleutel);
}

function contentBeheerMaxLengte(string $type): int
{
    return $type === 'blok' ? 3000 : 200;
}

function contentBeheerLeesPostWaarde(array $post, string $prefix, string $veld, string $taal): string
{
    $waarde = $post[$prefix][$veld][$taal] ?? '';
    return is_scalar($waarde) ? trim((string) $waarde) : '';
}

function contentBeheerOpslaan(string $sleutel, array $post, callable $kort, callable $schrijfJson): array
{
    $velden = contentBeheerVelden($sleutel);
    $pad = contentPaginaDataPad($sleutel);
    if (!$velden || $pad === null) {
        return ['ok' => false, 'melding' => 'Deze contentpagina is niet correct geconfigureerd.'];
    }

    $prefix = contentBeheerPostPrefix($sleutel);
    $nieuw = [];
    foreach ($velden as $veld => $info) {
        $type = (string) ($info[1] ?? 'tekst');
        $max = contentBeheerMaxLengte($type);
        $nieuw[$veld] = [];
        foreach (['nl', 'en', 'de'] as $taal) {
            $nieuw[$veld][$taal] = $kort(contentBeheerLeesPostWaarde($post, $prefix, $veld, $taal), $max);
        }
    }

    if (!$schrijfJson($pad, $nieuw)) {
        return ['ok' => false, 'melding' => 'Opslaan mislukt. Controleer de schrijfrechten van de map data op de server.'];
    }

    return ['ok' => true, 'melding' => 'Opgeslagen. De contentpagina is bijgewerkt.', 'data' => $nieuw];
}
