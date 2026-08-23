<?php
$root = dirname(__DIR__);
$htaccess = @file_get_contents($root . '/.htaccess');
if (!is_string($htaccess)) {
    fwrite(STDERR, "FOUT: .htaccess ontbreekt\n");
    exit(1);
}

$errors = [];
$required = [
    'RewriteCond %{REQUEST_URI} ^/dev/images/sponsors/ [NC]',
    'RewriteRule ^images/sponsors/[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp)$ /dev/images/template-placeholder.svg [R=302,L,NC,NE,QSD]',
    'RewriteRule ^images/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9][A-Za-z0-9._-]{0,180}\\.(?:jpe?g|png|webp|gif|svg)$ images/template-placeholder.svg [L,NC]',
];
foreach ($required as $needle) {
    if (!str_contains($htaccess, $needle)) {
        $errors[] = 'ontbrekend contract: ' . $needle;
    }
}

$sponsorPos = strpos($htaccess, 'RewriteCond %{REQUEST_URI} ^/dev/images/sponsors/ [NC]');
$generalPos = strpos($htaccess, 'RewriteCond %{REQUEST_URI} ^/dev/images/ [NC]', ($sponsorPos === false ? 0 : $sponsorPos + 1));
if ($sponsorPos === false || $generalPos === false || $sponsorPos >= $generalPos) {
    $errors[] = 'DEV sponsorredirect moet vóór de algemene DEV imagefallback staan';
}

if (str_contains($htaccess, '$ images/template-placeholder.svg [R=302')) {
    $errors[] = 'DEV sponsorredirect mag geen relatief target gebruiken op Strato';
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "FOUT: {$error}\n");
    exit(1);
}

echo "OK: DEV sponsorplaceholder gebruikt een absoluut /dev URL-pad vóór de algemene imagefallback\n";
