<?php
$root = dirname(__DIR__);
$script = (string) file_get_contents($root . '/tests/live-dev-security.sh');

$ok = 0;
$fout = 0;
function check226LiveSecurity(bool $conditie, string $melding): void
{
    global $ok, $fout;
    if ($conditie) {
        $ok++;
        echo "OK: {$melding}\n";
        return;
    }
    $fout++;
    fwrite(STDERR, "FOUT: {$melding}\n");
}

$intern = [
    'app/deployment/first-vps-bootstrap-contract.php',
    'bin/apply-first-vps-bootstrap.php',
    'tests/phase52-first-vps-bootstrap.php',
    'dev-build.json',
];

foreach ($intern as $pad) {
    check226LiveSecurity(
        str_contains($script, '["$BASE/' . $pad . '"]="404"'),
        "live-security verwacht 404 voor intern pad {$pad}"
    );
    check226LiveSecurity(
        !str_contains($script, '["$BASE/' . $pad . '"]="403"'),
        "live-security bevat geen oude 403-verwachting voor {$pad}"
    );
}

check226LiveSecurity(
    str_contains($script, 'minimale public/ documentroot')
        && str_contains($script, 'geen existence disclosure'),
    'live-security documenteert waarom interne releasepaden onder public/ als 404 moeten eindigen'
);

check226LiveSecurity(
    str_contains($script, 'case "$trace" in 403|405|501)'),
    'TRACE-blokkade blijft onafhankelijk 403/405/501 accepteren'
);

printf("Architecture #226 live security boundary: %d OK, %d fout(en)\n", $ok, $fout);
exit($fout === 0 ? 0 : 1);
