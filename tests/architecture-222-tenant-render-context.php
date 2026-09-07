<?php
$root = dirname(__DIR__);
require_once $root . '/app/core/tenant-public-runtime.php';

$ok = 0;
$fout = 0;

function check222(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; return; }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function rr222(string $dir): void
{
    if (is_link($dir)) { @unlink($dir); return; }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($pad) && !is_link($pad)) rr222($pad); else @unlink($pad);
    }
    @rmdir($dir);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'architecture-222-' . bin2hex(random_bytes(4));
@mkdir($tmp . '/public-content', 0700, true);

try {
    file_put_contents($tmp . '/public-content/contact.json', json_encode([
        'email' => 'info@noorderhaven.example',
        'facebook' => 'https://social.example/noorderhaven?a=1&b=2',
        'adres_straat' => 'Havenstraat 7',
        'adres_postcode_plaats' => '1234 AB Noorderhaven',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

    $config = [
        'vereniging' => [
            'sleutel' => 'noorderhaven',
            'naam' => 'Noorderhaven',
            'volledige_naam' => 'Roeivereniging Noorderhaven',
            'slogan' => 'Samen op het water',
            'site_url' => 'https://noorderhaven.example',
        ],
        'branding' => ['kleuren' => []],
        'betaling' => [
            'iban' => 'NL00 TEST 0123 4567 89',
            'tenaamstelling' => 'Roeivereniging Noorderhaven',
            'omschrijving' => 'Contributie {jaar} - {naam}',
        ],
        'opslag' => ['private_root' => $tmp],
    ];

    $bron = <<<'HTML'
<!doctype html>
<html>
<head>
<title>RC045 – Bashers of the South</title>
<meta name="description" content="Welkom bij RC045 in Eygelshoven">
<style>.rc045-card{background:url(images/crawler.jpg)}</style>
<script type="application/ld+json" id="structured-data">{"@type":"Organization","name":"RC045 – Bashers of the South","url":"https://rc045.nl"}</script>
<script id="legacy-js">const storageKey='rc045_lang'; const visibleClub='RC045'; const clubUrl='https://rc045.nl';</script>
</head>
<body data-technical-key="rc045_lang">
<h1>RC045</h1>
<p>Bashers of the South</p>
<p>NL51 RABO 0367 6153 63</p>
<p>T.n.v. RC045</p>
<p>Wijngaardsberg 26, 6464 EZ Eygelshoven</p>
<a id="facebook" href="https://www.facebook.com/rc045/">facebook.com/rc045</a>
<a id="mail" href="mailto:bestuur@rc045.nl">bestuur@rc045.nl</a>
<a id="site" href="https://rc045.nl/aanmelden.php">Website RC045</a>
</body>
</html>
HTML;

    $html = tenantPublicRuntimeTransform($bron, $config);

    check222(str_contains($html, '<h1>Noorderhaven</h1>'), 'zichtbare tenantnaam wordt in tekstcontext gerenderd');
    check222(str_contains($html, 'Samen op het water'), 'slogan wordt in tekstcontext gerenderd');
    check222(str_contains($html, 'NL00 TEST 0123 4567 89'), 'betalingsdata wordt in tekstcontext gerenderd');
    check222(str_contains($html, 'Havenstraat 7') && str_contains($html, '1234 AB Noorderhaven'), 'adresdata wordt in tekstcontext gerenderd');
    check222(str_contains($html, 'href="https://social.example/noorderhaven?a=1&amp;b=2"'), 'social URL wordt als URL-attribuut gerenderd');
    check222(str_contains($html, 'href="mailto:info@noorderhaven.example"'), 'mailadres wordt als mailto-attribuut gerenderd');
    check222(str_contains($html, 'href="https://noorderhaven.example/aanmelden.php"'), 'site-URL wordt contextueel met pad gerenderd');

    check222(str_contains($html, "storageKey='rc045_lang'"), 'technische localStorage-key wordt niet door displaynaam gemuteerd');
    check222(str_contains($html, 'data-technical-key="rc045_lang"'), 'willekeurig technisch data-attribuut wordt niet door identity-rendering gemuteerd');
    check222(str_contains($html, '.rc045-card'), 'CSS-selector blijft technische broncode en wordt niet als merkcontent herschreven');
    check222(str_contains($html, "visibleClub='Noorderhaven'"), 'bekende zichtbare legacy-identiteit in JavaScript krijgt JS-veilige tenantwaarde');
    check222(!str_contains($html, "visibleClub='RC045'"), 'zichtbare JavaScript-identiteit lekt niet als voorbeeldvereniging');

    preg_match('~<script[^>]+id="structured-data"[^>]*>(.*?)</script>~s', $html, $jsonMatch);
    $structured = json_decode(html_entity_decode($jsonMatch[1] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
    check222(is_array($structured) && ($structured['name'] ?? '') === 'Roeivereniging Noorderhaven', 'structured data krijgt contextuele volledige naam');
    check222(is_array($structured) && ($structured['url'] ?? '') === 'https://noorderhaven.example', 'structured data krijgt tenant-URL');

    $runtimeBron = (string) file_get_contents($root . '/app/core/tenant-public-runtime.php');
    $homepageBron = (string) file_get_contents($root . '/app/content/tenant-homepage.php');
    check222(!str_contains($runtimeBron, 'str_ireplace('), 'runtime bevat geen whole-response identity str_ireplace meer');
    check222(!str_contains($homepageBron, 'str_ireplace('), 'homepage bevat geen whole-response identity str_ireplace meer');
    check222(str_contains($runtimeBron, 'tenantPublicRuntimeRenderContext'), 'runtime gebruikt een expliciete tenant render-context');
} finally {
    rr222($tmp);
}

echo "Architecture #222 tenant render context: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
