<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function msmFout(string $melding, int $code = 1): never
{
    fwrite(STDERR, $melding . PHP_EOL);
    exit($code);
}

function msmGebruik(): never
{
    $script = basename(__FILE__);
    fwrite(STDOUT, "Gebruik: php {$script} [--config=/absoluut/pad/beheer-config.php] (--check|--apply)\n");
    exit(0);
}

function msmAbsoluutPad(string $pad): bool
{
    if ($pad === '') return false;
    if (DIRECTORY_SEPARATOR === '\\') return preg_match('/^[A-Za-z]:[\\\\\/]/', $pad) === 1;
    return str_starts_with($pad, '/');
}

/**
 * Vind een eenvoudige zelfstandige top-level assignment van één bekende
 * variabele. Toegestaan: $VAR = 'string'; direct na PHP-open-tag of een
 * eerdere puntkomma, met alleen whitespace/comments tussen tokens.
 * Conditionele, berekende, geneste of meervoudige assignments falen gesloten.
 *
 * @return array{start:int,end:int,value:string}|null
 */
function msmVindAssignment(string $bron, string $variabele): ?array
{
    $tokens = token_get_all($bron);
    $offset = 0;
    $matches = [];
    $depth = 0;
    $aantal = count($tokens);

    $tekst = static function ($token): string {
        return is_array($token) ? $token[1] : $token;
    };
    $overslaan = static function ($token): bool {
        return is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
    };

    $offsets = [];
    foreach ($tokens as $i => $token) {
        $offsets[$i] = $offset;
        $offset += strlen($tekst($token));
    }

    for ($i = 0; $i < $aantal; $i++) {
        $token = $tokens[$i];
        $t = $tekst($token);
        if ($t === '{' || $t === '(' || $t === '[') $depth++;
        if ($t === '}' || $t === ')' || $t === ']') $depth = max(0, $depth - 1);
        if ($depth !== 0 || !is_array($token) || $token[0] !== T_VARIABLE || $token[1] !== $variabele) continue;

        $vorige = $i - 1;
        while ($vorige >= 0 && $overslaan($tokens[$vorige])) $vorige--;
        $vorigeToken = $vorige >= 0 ? $tokens[$vorige] : null;
        $zelfstandig = ($vorigeToken === ';')
            || (is_array($vorigeToken) && $vorigeToken[0] === T_OPEN_TAG);
        if (!$zelfstandig) {
            msmFout("Onveilige context voor {$variabele}; alleen een zelfstandige top-level stringtoewijzing wordt ondersteund.");
        }

        $start = $offsets[$i];
        $j = $i + 1;
        while ($j < $aantal && $overslaan($tokens[$j])) $j++;
        if ($j >= $aantal || $tekst($tokens[$j]) !== '=') msmFout("Onveilige assignment voor {$variabele}; alleen eenvoudige stringtoewijzing wordt ondersteund.");
        $j++;
        while ($j < $aantal && $overslaan($tokens[$j])) $j++;
        if ($j >= $aantal || !is_array($tokens[$j]) || $tokens[$j][0] !== T_CONSTANT_ENCAPSED_STRING) {
            msmFout("Onveilige waarde voor {$variabele}; alleen een letterlijke string wordt ondersteund.");
        }
        $literal = $tokens[$j][1];
        $quote = $literal[0] ?? '';
        if (($quote !== "'" && $quote !== '"') || substr($literal, -1) !== $quote) msmFout("Ongeldige stringliteral voor {$variabele}.");
        $waarde = $quote === "'"
            ? str_replace(["\\\\", "\\'"], ["\\", "'"], substr($literal, 1, -1))
            : stripcslashes(substr($literal, 1, -1));
        $j++;
        while ($j < $aantal && $overslaan($tokens[$j])) $j++;
        if ($j >= $aantal || $tekst($tokens[$j]) !== ';') msmFout("Assignment voor {$variabele} mist een veilige afsluitende puntkomma.");
        $end = $offsets[$j] + 1;
        $matches[] = ['start' => $start, 'end' => $end, 'value' => $waarde];
        $i = $j;
    }

    if (count($matches) > 1) msmFout("Meerdere assignments voor {$variabele}; handmatige migratie vereist.");
    return $matches[0] ?? null;
}

function msmPhpString(string $waarde): string
{
    return "'" . str_replace(['\\', "'"], ['\\\\', "\\'"], $waarde) . "'";
}

foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--check' || $arg === '--apply' || $arg === '--help') continue;
    if (str_starts_with($arg, '--config=') && strlen($arg) > strlen('--config=')) continue;
    msmFout('Onbekende of onveilige CLI-optie: ' . $arg, 64);
}

$opties = getopt('', ['config:', 'check', 'apply', 'help']);
if (isset($opties['help'])) msmGebruik();
$check = array_key_exists('check', $opties);
$apply = array_key_exists('apply', $opties);
if ($check === $apply) msmFout('Kies exact één actie: --check of --apply.', 64);

$config = isset($opties['config']) ? trim((string)$opties['config']) : dirname(__DIR__) . '/beheer-config.php';
if (!msmAbsoluutPad($config)) msmFout('--config moet een absoluut pad zijn.', 64);
if (is_link($config) || !is_file($config) || !is_readable($config)) msmFout('Standalone beheer-config.php is niet veilig leesbaar.');
if ($apply && !is_writable($config)) msmFout('Standalone beheer-config.php is niet veilig schrijfbaar.');

$bron = file_get_contents($config);
if (!is_string($bron) || $bron === '') msmFout('Standalone beheer-config.php kon niet worden gelezen.');

$plain = msmVindAssignment($bron, '$BEHEER_WACHTWOORD');
$hashAssignment = msmVindAssignment($bron, '$BEHEER_WACHTWOORD_HASH');
$hashGeldig = $hashAssignment !== null
    && $hashAssignment['value'] !== ''
    && ((password_get_info($hashAssignment['value'])['algoName'] ?? 'unknown') !== 'unknown');
$plainWaarde = $plain['value'] ?? '';
$plainAanwezig = $plain !== null && trim($plainWaarde) !== '';

if (!$plainAanwezig && $hashGeldig) {
    fwrite(STDOUT, "STANDALONE MASTER CHECK OK  status=hash-only\n");
    exit(0);
}
if (!$plainAanwezig) msmFout('Geen bruikbare plaintext mastercredential en geen geldige hash gevonden; handmatige herstelactie vereist.');
if ($plainWaarde === 'VeranderDitWachtwoord') msmFout('Placeholdermasterwachtwoord wordt niet gemigreerd; stel eerst een echt sterk wachtwoord in.');
if ($hashAssignment !== null && !$hashGeldig) msmFout('Bestaande BEHEER_WACHTWOORD_HASH is ongeldig; handmatige beoordeling vereist.');

if ($check) {
    fwrite(STDOUT, "STANDALONE MASTER CHECK MIGRATION_REQUIRED  plaintext=present hash=" . ($hashGeldig ? 'valid' : 'missing') . "\n");
    exit(2);
}

$nieuweHash = $hashGeldig ? $hashAssignment['value'] : password_hash($plainWaarde, PASSWORD_DEFAULT);
if (!is_string($nieuweHash) || $nieuweHash === '' || ((password_get_info($nieuweHash)['algoName'] ?? 'unknown') === 'unknown')) {
    msmFout('Password hash kon niet veilig worden aangemaakt.');
}

$vervangingen = [];
if ($hashAssignment !== null) {
    $vervangingen[] = ['start' => $hashAssignment['start'], 'end' => $hashAssignment['end'], 'text' => '$BEHEER_WACHTWOORD_HASH = ' . msmPhpString($nieuweHash) . ';'];
    $vervangingen[] = ['start' => $plain['start'], 'end' => $plain['end'], 'text' => ''];
} else {
    $vervangingen[] = ['start' => $plain['start'], 'end' => $plain['end'], 'text' => '$BEHEER_WACHTWOORD_HASH = ' . msmPhpString($nieuweHash) . ';'];
}
usort($vervangingen, static fn(array $a, array $b): int => $b['start'] <=> $a['start']);
$nieuw = $bron;
foreach ($vervangingen as $v) {
    $nieuw = substr($nieuw, 0, $v['start']) . $v['text'] . substr($nieuw, $v['end']);
}
if (msmVindAssignment($nieuw, '$BEHEER_WACHTWOORD') !== null) {
    msmFout('Migratie zou een plaintext masterassignment laten staan; write geweigerd.');
}
$candidateHash = msmVindAssignment($nieuw, '$BEHEER_WACHTWOORD_HASH');
$candidateOk = $candidateHash !== null
    && ($hashGeldig
        ? hash_equals($hashAssignment['value'], $candidateHash['value'])
        : password_verify($plainWaarde, $candidateHash['value']));
if (!$candidateOk) {
    msmFout('Nieuwe hash-only kandidaatconfig doorstaat de credentialcontrole niet.');
}

$tmp = $config . '.tmp.' . bin2hex(random_bytes(6));
if (file_put_contents($tmp, $nieuw, LOCK_EX) !== strlen($nieuw)) {
    @unlink($tmp);
    msmFout('Tijdelijke hash-only config kon niet volledig worden geschreven.');
}
@chmod($tmp, 0640);
if (!is_file($tmp) || (fileperms($tmp) & 0777) !== 0640) {
    @unlink($tmp);
    msmFout('Tijdelijke hash-only config kreeg niet de vereiste server-only rechten.');
}
if (!rename($tmp, $config)) {
    @unlink($tmp);
    msmFout('Hash-only config kon niet atomisch worden geplaatst; oorspronkelijke config is niet gewijzigd.');
}
@chmod($config, 0640);

$controle = file_get_contents($config);
$controlePlain = is_string($controle) ? msmVindAssignment($controle, '$BEHEER_WACHTWOORD') : null;
$controleHash = is_string($controle) ? msmVindAssignment($controle, '$BEHEER_WACHTWOORD_HASH') : null;
$eindMode = fileperms($config) & 0777;
$eindCredentialOk = $controleHash !== null
    && ($hashGeldig
        ? hash_equals($hashAssignment['value'], $controleHash['value'])
        : password_verify($plainWaarde, $controleHash['value']));
if (!is_string($controle)
    || $controlePlain !== null
    || !$eindCredentialOk
    || $eindMode !== 0640) {
    // De kandidaat was vóór rename al inhoudelijk bewezen. Een fout hier wijst
    // op filesystemdrift/race; schrijf het oude secret niet opnieuw naar disk.
    msmFout('Nacontrole van de geplaatste hash-only config faalde; handmatige beoordeling vereist.');
}

fwrite(STDOUT, "STANDALONE MASTER MIGRATION OK  status=hash-only\n");
