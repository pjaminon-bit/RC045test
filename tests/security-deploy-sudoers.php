<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;
function o137(bool $conditie, string $label): void
{
    global $ok, $fout;
    if ($conditie) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}

$sudoersPad = $root . '/ops/vps-test-deploy/verenigingsplatform-github-deploy.sudoers';
$entryPad = $root . '/ops/vps-test-deploy/verenigingsplatform-github-entry';
$deployPad = $root . '/ops/vps-test-deploy/verenigingsplatform-github-deploy';

$sudoers = (string)file_get_contents($sudoersPad);
$entry = (string)file_get_contents($entryPad);
$deploy = (string)file_get_contents($deployPad);
$regel = trim($sudoers);
$verwacht = 'vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-deploy ^[0-9a-f]{40}$';

o137($regel === $verwacht, 'sudoers gebruikt exact één geankerde POSIX-ERE voor de deploy-SHA');
o137(substr_count(trim($sudoers), "\n") === 0, 'deploy-sudoers bevat exact één autorisatieregel');
o137(!str_contains($sudoers, '*') && !str_contains($sudoers, '?'), 'deploy-sudoers bevat geen shell-style argumentwildcards');
o137(!str_contains($sudoers, 'ALL=(ALL)') && !str_contains($sudoers, '/bin/sh') && !str_contains($sudoers, '/bin/bash'), 'sudoers verruimt geen root-shell of runas-grens');

o137(str_contains($entry, 'if [[ "$command" =~ ^deploy[[:space:]]+([0-9a-f]{40})$ ]]; then'), 'forced-command gateway valideert deploycommando zelf fail-closed');
o137(str_contains($entry, 'exec /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-deploy "${BASH_REMATCH[1]}"'), 'gateway geeft uitsluitend de opgeschoonde SHA als één gequote sudo-argument door');
o137(str_contains($deploy, 'if [[ "$#" -ne 1 || ! "$1" =~ ^[0-9a-f]{40}$ ]]; then'), 'rootwrapper behoudt onafhankelijke exact-één/lowercase-40hex validatie');
o137(str_contains($deploy, '[[ "$commit" == "$main_commit" ]] || {'), 'rootwrapper behoudt actuele-main-tip binding');

$geldig = str_repeat('a', 40);
$ongeldig = [
    '',
    str_repeat('a', 39),
    str_repeat('a', 41),
    str_repeat('A', 40),
    str_repeat('g', 40),
    $geldig . ' extra',
    $geldig . "\t" . str_repeat('b', 40),
    ' ' . $geldig,
    $geldig . ' ',
    "\t" . $geldig,
    $geldig . "\n",
    '"' . $geldig . '"',
    "'" . $geldig . "'",
    '*',
    '?',
    '[0-9a-f]{40}',
    $geldig . ';id',
    $geldig . '/etc/shadow',
];
$argumentContract = static fn(string $waarde): bool => preg_match('/^[0-9a-f]{40}$/D', $waarde) === 1;
o137($argumentContract($geldig), 'contract accepteert één lowercase 40-hex SHA');
foreach ($ongeldig as $index => $waarde) {
    o137(!$argumentContract($waarde), 'contract weigert negatieve argumentcase #' . ($index + 1));
}

if (is_executable('/usr/sbin/visudo')) {
    $cmd = '/usr/sbin/visudo -cf ' . escapeshellarg($sudoersPad) . ' 2>&1';
    exec($cmd, $uitvoer, $code);
    o137($code === 0, 'canonieke deploy-sudoers is visudo-syntactisch geldig');
}

echo "Issue #137 deploy sudoers hardening: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
