<?php
$root = dirname(__DIR__);

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTPS'] = 'on';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/public/index.php';
$_GET = [];
$_POST = [];

ob_start();
require $root . '/public/index.php';
$html = (string)ob_get_clean();

$ok = 0;
$fout = 0;
$check = static function (bool $conditie, string $melding) use (&$ok, &$fout): void {
    if ($conditie) {
        $ok++;
        echo "OK: {$melding}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$melding}\n");
};

$check(http_response_code() < 400, 'homepage via public/index.php blijft een succesvolle response');
$check(preg_match('~<title>[^<]+</title>~i', $html) === 1, 'SEO-head behoudt een niet-lege documenttitel achter de frontcontroller');
$check(str_contains($html, 'acceptance-hardening.css'), 'SEO-head laadt acceptance-hardening.css achter de frontcontroller');
$check(str_contains($html, 'acceptance-hardening.js'), 'SEO-head laadt acceptance-hardening.js achter de frontcontroller');
$check(str_contains($html, '<h1'), 'homepage-inhoud blijft aanwezig achter de frontcontroller');

$seo = (string)file_get_contents($root . '/app/content/seo-head.php');
$check(str_contains($seo, 'global $RC045_SITE, $RC045_TALEN, $RC045_PAGINAS;'), 'regressie dekt expliciet de legacy global-scope afhankelijkheid die #226 brak');

printf("Architecture #226 public render scope: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);