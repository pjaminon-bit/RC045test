<?php
$root = dirname(__DIR__);
require_once $root . '/app/core/tenant-public-runtime.php';

$ok = 0;
$fout = 0;

function check221(bool $cond, string $label): void
{
    global $ok, $fout;
    if ($cond) {
        $ok++;
        echo "OK: {$label}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$label}\n");
}

function rr221(string $dir): void
{
    if (is_link($dir)) { @unlink($dir); return; }
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        $pad = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($pad) && !is_link($pad)) rr221($pad); else @unlink($pad);
    }
    @rmdir($dir);
}

$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'security-221-' . bin2hex(random_bytes(4));
@mkdir($tmp . '/public-content', 0700, true);

try {
    file_put_contents(
        $tmp . '/public-content/contact.json',
        json_encode([
            'email' => 'veilig@example.test',
            'facebook' => 'javascript:alert(9)',
            'adres_straat' => 'Straat </span><img src=x onerror=alert(4)>',
            'adres_postcode_plaats' => '1234 AB <svg onload=alert(5)>',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );

    $config = [
        'vereniging' => [
            'sleutel' => 'security-221',
            'naam' => 'Club </title><script id="tenant-pwn">alert(1)</script>',
            'volledige_naam' => 'Volledige <svg onload=alert(2)> Club',
            'slogan' => 'Slogan <img src=x onerror=alert(3)>',
            'site_url' => 'https://tenant.example',
        ],
        'branding' => [
            'kleuren' => [],
        ],
        'betaling' => [
            'iban' => 'NL00 TEST <script id="iban-pwn">alert(6)</script>',
            'tenaamstelling' => 'Naam <img src=x onerror=alert(7)>',
            'omschrijving' => 'Contributie {jaar} - {naam} <svg onload=alert(8)>',
        ],
        'opslag' => [
            'private_root' => $tmp,
        ],
    ];

    $bron = <<<'HTML'
<!doctype html>
<html>
<head><title>RC045 – Bashers of the South</title></head>
<body>
<h1>RC045</h1>
<p>Bashers of the South</p>
<p>NL51 RABO 0367 6153 63</p>
<p>T.n.v. RC045</p>
<p>contributie RC045 {jaar}</p>
<a id="facebook" href="https://www.facebook.com/rc045/">facebook.com/rc045</a>
<span>Wijngaardsberg 26</span>
<span>6464 EZ Eygelshoven</span>
</body>
</html>
HTML;

    $html = tenantPublicRuntimeTransform($bron, $config);

    check221(str_contains($html, 'Club &lt;/title&gt;&lt;script id="tenant-pwn"&gt;alert(1)&lt;/script&gt;'), 'tenantnaam wordt als veilige HTML-tekst gerenderd');
    check221(str_contains($html, 'Volledige &lt;svg onload=alert(2)&gt; Club'), 'volledige naam kan geen SVG-markup injecteren');
    check221(str_contains($html, 'Slogan &lt;img src=x onerror=alert(3)&gt;'), 'slogan kan geen image/eventhandler injecteren');
    check221(str_contains($html, 'NL00 TEST &lt;script id="iban-pwn"&gt;alert(6)&lt;/script&gt;'), 'betalingswaarde wordt als veilige HTML-tekst gerenderd');
    check221(str_contains($html, 'Straat &lt;/span&gt;&lt;img src=x onerror=alert(4)&gt;'), 'contactadres kan geen elementinjectie veroorzaken');
    check221(str_contains($html, '1234 AB &lt;svg onload=alert(5)&gt;'), 'contactplaats kan geen SVG-eventhandler injecteren');

    check221(preg_match('~<script\b[^>]*id=["\'](?:tenant-pwn|iban-pwn)["\']~i', $html) !== 1, 'kwaadaardige tenantwaarden maken geen extra scripttag');
    check221(preg_match('~<(?:img|svg)\b[^>]*(?:onerror|onload)\s*=~i', $html) !== 1, 'kwaadaardige tenantwaarden maken geen eventhandler-element');
    check221(str_contains($html, 'id="facebook" href="#contact"'), 'onveilig facebook-schema valt fail-closed terug naar lokale contactlink');

    $normaal = $config;
    $normaal['vereniging']['naam'] = "O'Brien & Co";
    $normaal['vereniging']['volledige_naam'] = "Vereniging O'Brien & Co";
    $normaal['vereniging']['slogan'] = 'Samen & actief';
    file_put_contents(
        $tmp . '/public-content/contact.json',
        json_encode([
            'email' => 'info@example.test',
            'facebook' => 'https://social.example/club?a=1&b=2',
            'adres_straat' => 'Dorpsstraat 1 & 3',
            'adres_postcode_plaats' => "1234 AB 't Dorp",
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n"
    );
    $normaalHtml = tenantPublicRuntimeTransform($bron, $normaal);
    check221(str_contains($normaalHtml, "O'Brien &amp; Co"), 'legitieme apostrof en ampersand blijven veilig renderbaar in tekstcontext');
    check221(str_contains($normaalHtml, 'https://social.example/club?a=1&amp;b=2'), 'geldige HTTPS-link blijft behouden en attribuutveilig');

    check221(tenantPublicRuntimeHttpUrl('https://example.test/pad?q=1', '') === 'https://example.test/pad?q=1', 'HTTPS-URL wordt geaccepteerd');
    check221(tenantPublicRuntimeHttpUrl('http://example.test/pad', '') === 'http://example.test/pad', 'HTTP-URL wordt geaccepteerd voor compatibiliteit');
    check221(tenantPublicRuntimeHttpUrl('javascript:alert(1)', '#contact') === '#contact', 'javascript-schema wordt geweigerd');
    check221(tenantPublicRuntimeHttpUrl('data:text/html,<script>alert(1)</script>', '#contact') === '#contact', 'data-schema wordt geweigerd');
} finally {
    rr221($tmp);
}

echo "Security #221 tenant output XSS: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
