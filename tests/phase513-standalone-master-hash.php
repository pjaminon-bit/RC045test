<?php
$root = dirname(__DIR__);
$ok = 0;
$fout = 0;

function s513Check(bool $cond, string $label): void {
    global $ok, $fout;
    if ($cond) { $ok++; echo "OK: {$label}\n"; }
    else { $fout++; fwrite(STDERR, "FOUT: {$label}\n"); }
}
function s513Run(array $argv): array {
    $spec = [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']];
    $p = proc_open($argv, $spec, $pipes, null, null, ['bypass_shell' => true]);
    if (!is_resource($p)) return [255,'','proc_open faalde'];
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $err = stream_get_contents($pipes[2]); fclose($pipes[2]);
    return [proc_close($p), (string)$out, (string)$err];
}
function s513Wis(string $pad): void {
    if (is_link($pad) || is_file($pad)) { @unlink($pad); return; }
    if (!is_dir($pad)) return;
    foreach (scandir($pad) ?: [] as $item) {
        if ($item === '.' || $item === '..') continue;
        s513Wis($pad . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($pad);
}
function s513Config(string $plain = '', string $hash = '', string $extra = ''): string {
    $src = "<?php\n";
    if ($plain !== '') $src .= '$BEHEER_WACHTWOORD = ' . var_export($plain, true) . ";\n";
    if ($hash !== '') $src .= '$BEHEER_WACHTWOORD_HASH = ' . var_export($hash, true) . ";\n";
    $src .= $extra;
    return $src;
}
function s513LeesMaster(string $pad): array {
    return (static function (string $bestand): array {
        $BEHEER_WACHTWOORD = null;
        $BEHEER_WACHTWOORD_HASH = null;
        require $bestand;
        return ['plain'=>$BEHEER_WACHTWOORD, 'hash'=>$BEHEER_WACHTWOORD_HASH];
    })($pad);
}

$migrator = $root . '/bin/migrate-standalone-master-hash.php';
$authSrc = (string)file_get_contents($root . '/auth.php');
$tmp = sys_get_temp_dir() . '/rc045-phase513-' . bin2hex(random_bytes(5));
@mkdir($tmp, 0700, true);

try {
    s513Check(is_file($migrator), 'standalone mastermigrator bestaat');

    $start = strpos($authSrc, 'function authMasterWachtwoordKlopt');
    $eind = strpos($authSrc, '// ===== Uitloggen =====', $start === false ? 0 : $start);
    $masterFunctie = ($start !== false && $eind !== false) ? substr($authSrc, $start, $eind - $start) : '';
    s513Check(
        $masterFunctie !== ''
        && str_contains($masterFunctie, 'password_verify')
        && str_contains($masterFunctie, '$BEHEER_WACHTWOORD_HASH')
        && !str_contains($masterFunctie, '$BEHEER_WACHTWOORD,')
        && !str_contains($masterFunctie, 'hash_equals'),
        'masterlogin verifieert uitsluitend een password_hash'
    );
    s513Check(
        str_contains($authSrc, '$configOk = $beheerHashOk && !$beheerHeeftPlaintext')
        && str_contains($authSrc, 'unset($BEHEER_WACHTWOORD)')
        && !str_contains($authSrc, '$configOk = $beheerHashOk || $beheerLegacyOk'),
        'runtime weigert iedere niet-lege plaintext mastervariabele fail-closed'
    );

    $wachtwoord = 'Synthetisch-Migratie-Wachtwoord-2026!';
    $cfg = $tmp . '/plaintext.php';
    file_put_contents($cfg, s513Config($wachtwoord));
    chmod($cfg, 0640);
    $voor = file_get_contents($cfg);
    [$code,$out,$err] = s513Run([PHP_BINARY,$migrator,'--config='.$cfg,'--check']);
    s513Check($code === 2 && str_contains($out, 'MIGRATION_REQUIRED'), '--check meldt plaintextconfig als migratieplichtig');
    s513Check(file_get_contents($cfg) === $voor, '--check muteert de serverconfig niet');
    s513Check(!str_contains($out.$err, $wachtwoord), 'checkoutput lekt het masterwachtwoord niet');

    [$code,$out,$err] = s513Run([PHP_BINARY,$migrator,'--config='.$cfg,'--apply']);
    $na = s513LeesMaster($cfg);
    s513Check($code === 0 && str_contains($out, 'STANDALONE MASTER MIGRATION OK'), '--apply migreert plaintextconfig succesvol');
    s513Check(($na['plain'] ?? null) === null && is_string($na['hash'] ?? null) && password_verify($wachtwoord, $na['hash']), 'gemigreerde config bevat alleen een verifieerbare hash');
    s513Check(!str_contains((string)file_get_contents($cfg), $wachtwoord), 'actieve config bevat het oude plaintext secret niet meer');
    s513Check((fileperms($cfg) & 0777) === 0640, 'gemigreerde masterconfig krijgt server-only 0640');
    s513Check((glob($cfg . '.pre-hash-*.bak') ?: []) === [], 'succesvolle migratie laat geen plaintext rollbackbestand achter');
    s513Check(!str_contains($out.$err, $wachtwoord) && !str_contains($out.$err, (string)($na['hash'] ?? '')), 'applyoutput lekt wachtwoord noch hash');

    [$code,$out] = s513Run([PHP_BINARY,$migrator,'--config='.$cfg,'--check']);
    s513Check($code === 0 && str_contains($out, 'status=hash-only'), 'gemigreerde config doorstaat hash-only nacontrole');

    $bestaandeHash = password_hash('Ander-Synthetisch-Wachtwoord-2026!', PASSWORD_DEFAULT);
    $cfgDubbel = $tmp . '/hash-plus-plaintext.php';
    file_put_contents($cfgDubbel, s513Config('legacy-secret-alleen-test', $bestaandeHash));
    [$code] = s513Run([PHP_BINARY,$migrator,'--config='.$cfgDubbel,'--apply']);
    $dubbel = s513LeesMaster($cfgDubbel);
    s513Check($code === 0 && ($dubbel['hash'] ?? '') === $bestaandeHash && ($dubbel['plain'] ?? null) === null, 'geldige bestaande hash blijft bytegelijk terwijl plaintext wordt verwijderd');

    $cfgOngeldig = $tmp . '/invalid-hash.php';
    $ongeldigVoor = s513Config('legacy-secret-alleen-test', 'geen-password-hash');
    file_put_contents($cfgOngeldig, $ongeldigVoor);
    [$code] = s513Run([PHP_BINARY,$migrator,'--config='.$cfgOngeldig,'--apply']);
    s513Check($code !== 0 && file_get_contents($cfgOngeldig) === $ongeldigVoor, 'ongeldige bestaande hash faalt zonder configmutatie');

    $cfgMeervoudig = $tmp . '/multiple.php';
    $multiVoor = "<?php\n\$BEHEER_WACHTWOORD='een';\n\$BEHEER_WACHTWOORD='twee';\n";
    file_put_contents($cfgMeervoudig, $multiVoor);
    [$code] = s513Run([PHP_BINARY,$migrator,'--config='.$cfgMeervoudig,'--apply']);
    s513Check($code !== 0 && file_get_contents($cfgMeervoudig) === $multiVoor, 'meerdere plaintext assignments worden fail-closed geweigerd');

    $cfgExpressie = $tmp . '/expression.php';
    $exprVoor = "<?php\n\$BEHEER_WACHTWOORD = getenv('LEGACY_MASTER');\n";
    file_put_contents($cfgExpressie, $exprVoor);
    [$code] = s513Run([PHP_BINARY,$migrator,'--config='.$cfgExpressie,'--check']);
    s513Check($code !== 0 && file_get_contents($cfgExpressie) === $exprVoor, 'berekende masterexpressie wordt niet automatisch herschreven');

    $doel = $tmp . '/real.php';
    file_put_contents($doel, s513Config('symlink-test-secret'));
    $link = $tmp . '/link.php';
    $symlinkOk = @symlink($doel, $link);
    if ($symlinkOk) {
        [$code] = s513Run([PHP_BINARY,$migrator,'--config='.$link,'--apply']);
        s513Check($code !== 0 && str_contains((string)file_get_contents($doel), 'symlink-test-secret'), 'symlinkconfig wordt vóór iedere mutatie geweigerd');
    } else {
        s513Check(true, 'symlinkscenario niet beschikbaar op dit testplatform');
    }

    $cfgVoorwaardelijk = $tmp . '/conditional.php';
    $condVoor = "<?php\nif (true) \$BEHEER_WACHTWOORD = 'conditional-test-secret';\n";
    file_put_contents($cfgVoorwaardelijk, $condVoor);
    [$code] = s513Run([PHP_BINARY,$migrator,'--config='.$cfgVoorwaardelijk,'--apply']);
    s513Check($code !== 0 && file_get_contents($cfgVoorwaardelijk) === $condVoor, 'voorwaardelijke top-level assignment vereist handmatige migratie');

    [$code] = s513Run([PHP_BINARY,$migrator,'--config='.$cfg,'--password=verboden','--apply']);
    s513Check($code !== 0, 'migrator accepteert geen passwordoptie op de commandline');

} finally {
    s513Wis($tmp);
}

echo "Phase 5.13 standalone master hash-only: {$ok} OK, {$fout} fout(en)\n";
exit($fout === 0 ? 0 : 1);
