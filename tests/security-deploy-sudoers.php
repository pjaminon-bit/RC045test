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
$verwacht = 'vst-deploy ALL=(root) NOPASSWD: /usr/local/sbin/verenigingsplatform-github-deploy ""';

o137($regel === $verwacht, 'sudoers staat deploywrapper uitsluitend zonder command-line argumenten toe');
o137(substr_count(trim($sudoers), "\n") === 0, 'deploy-sudoers bevat exact één autorisatieregel');
o137(!str_contains($sudoers, '*') && !str_contains($sudoers, '?') && !str_contains($sudoers, '^[0-9'), 'deploy-sudoers bevat geen wildcard of regexargumentmatcher');
o137(!str_contains($sudoers, 'ALL=(ALL)') && !str_contains($sudoers, '/bin/sh') && !str_contains($sudoers, '/bin/bash'), 'sudoers verruimt geen root-shell of runas-grens');

o137(str_contains($entry, 'if [[ "$command" =~ ^deploy[[:space:]]+([0-9a-f]{40})$ ]]; then'), 'forced-command gateway valideert deploycommando zelf fail-closed');
o137(str_contains($entry, 'printf \'%s\\n\' "${BASH_REMATCH[1]}" | /usr/bin/sudo -n /usr/local/sbin/verenigingsplatform-github-deploy'), 'gateway transporteert uitsluitend de opgeschoonde SHA via stdin naar een argumentloze sudo-call');
o137(!str_contains($entry, '/usr/local/sbin/verenigingsplatform-github-deploy "${BASH_REMATCH[1]}"'), 'gateway geeft de dynamische SHA niet meer als sudo-argument door');
o137(str_contains($deploy, 'if [[ "$#" -ne 0 ]]; then'), 'rootwrapper weigert ieder command-line argument');
o137(str_contains($deploy, 'data=sys.stdin.buffer.read(42)') && str_contains($deploy, 'len(data) != 41') && str_contains($deploy, 're.fullmatch(rb"[0-9a-f]{40}\\n", data)'), 'rootwrapper valideert exact 40 lowercase hex plus één newline op stdin');
o137(str_contains($deploy, '[[ "$commit" == "$main_commit" ]] || {'), 'rootwrapper behoudt actuele-main-tip binding');

$geldig = str_repeat('a', 40) . "\n";
$ongeldig = [
    '',
    str_repeat('a', 40),
    str_repeat('a', 39) . "\n",
    str_repeat('a', 41) . "\n",
    str_repeat('A', 40) . "\n",
    str_repeat('g', 40) . "\n",
    str_repeat('a', 40) . " extra\n",
    str_repeat('a', 40) . "\nextra\n",
    ' ' . str_repeat('a', 40) . "\n",
    str_repeat('a', 40) . " \n",
    "\t" . str_repeat('a', 40) . "\n",
    str_repeat('a', 40) . "\t\n",
    '"' . str_repeat('a', 40) . '"' . "\n",
    "'" . str_repeat('a', 40) . "'\n",
    str_repeat('a', 40) . ";id\n",
    str_repeat('a', 40) . "/etc/shadow\n",
    str_repeat('a', 40) . "\0\n",
];
$stdinContract = static fn(string $waarde): bool => strlen($waarde) === 41 && preg_match('/^[0-9a-f]{40}\n$/D', $waarde) === 1;
o137($stdinContract($geldig), 'stdincontract accepteert exact één lowercase 40-hex regel');
foreach ($ongeldig as $index => $waarde) {
    o137(!$stdinContract($waarde), 'stdincontract weigert negatieve payloadcase #' . ($index + 1));
}

if (is_executable('/usr/sbin/visudo')) {
    $cmd = '/usr/sbin/visudo -cf ' . escapeshellarg($sudoersPad) . ' 2>&1';
    exec($cmd, $uitvoer, $code);
    o137($code === 0, 'canonieke deploy-sudoers is visudo-syntactisch geldig');
}

echo "Issue #137 deploy sudoers hardening: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
