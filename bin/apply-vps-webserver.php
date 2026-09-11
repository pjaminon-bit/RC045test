<?php
// ============================================================
// Fase 4.2 — valideer/installeer Apache artifacts
// ============================================================
// --check is root-vrij en controleert de complete bundle opnieuw.
// --apply installeert uitsluitend INACTIEVE fase-4.2 artifacts.
// --live-fragment vervangt uitsluitend het reeds actieve HTTPS-routingfragment
// van een bestaande fase-4.4 tenant, met volledige configtest/reload/rollback.
// ============================================================
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Alleen via CLI beschikbaar.');
}

require_once dirname(__DIR__) . '/app/deployment/webserver-contract.php';
require_once dirname(__DIR__) . '/app/deployment/process-runner.php';

function apply42Stop(string $melding, int $code = 1): void
{
    fwrite(STDERR, "FOUT: {$melding}\n");
    exit($code);
}

function apply42Help(): void
{
    echo "Gebruik:\n";
    echo "  php bin/apply-vps-webserver.php --plan=/srv/verenigingen/club/webserver/web-plan.json --check\n";
    echo "  sudo php bin/apply-vps-webserver.php --plan=... --apply [--force]\n";
    echo "  sudo php bin/apply-vps-webserver.php --plan=... --live-fragment\n\n";
    echo "Opties:\n";
    echo "  --check           valideer plan en alle gegenereerde Apache-artifacts; wijzig niets\n";
    echo "  --apply           installeer artifacts INACTIEF in de vaste Ubuntu/Debian Apache-paden\n";
    echo "  --live-fragment   vervang uitsluitend het routingfragment van een reeds actieve HTTPS-tenant\n";
    echo "                    atomisch, met configtest vóór reload en rollback bij fout\n";
    echo "  --force           vervang afwijkend INACTIEF bestand na alle controles; nooit een actief sitebestand\n";
    echo "  --help            toon deze hulp\n\n";
    echo "--apply gebruikt nooit a2ensite/sites-enabled en reloadt of herstart Apache niet.\n";
    echo "Alleen --live-fragment mag een bestaande live tenantconfig wijzigen en doet dat uitsluitend voor het gebonden HTTPS-routingfragment.\n";
}

function apply42Bundle(string $planPad): array
{
    try {
        $context = web42PlanLeesEnValideer($planPad);
        foreach ($context['artifacts'] as $pad => $verwacht) {
            $real = runtime41BestaandPad((string)$pad, 'Gegenereerd Apache-artifact');
            if (runtime41NormPad(dirname($real)) !== runtime41NormPad($context['plan']['bundle']['output_dir'])) {
                throw new RuntimeException('Apache-artifact staat niet in de gebonden webserver outputmap.');
            }
            $huidig = @file_get_contents($real);
            if (!is_string($huidig) || !hash_equals(hash('sha256', $verwacht), hash('sha256', $huidig))) {
                throw new RuntimeException('Gegenereerd Apache-artifact wijkt af van web-plan.json: ' . basename($real));
            }
        }
        return $context;
    } catch (Throwable $e) {
        apply42Stop($e->getMessage());
    }
}

function apply42Run(array $cmd): array
{
    return process521Run($cmd, null, null, null, 300);
}

function apply42RootMeta(string $pad, int $mode, bool $map = false): void
{
    clearstatcache(true, $pad);
    $meta = @lstat($pad);
    if (!is_array($meta) || is_link($pad) || ($map ? !is_dir($pad) : !is_file($pad))
        || (int)$meta['uid'] !== 0 || (int)$meta['gid'] !== 0 || (((int)$meta['mode'] & 0777) !== ($mode & 0777))) {
        apply42Stop('Apache root:root metadata wijkt af: ' . $pad);
    }
}

function apply42ApachePreflight(array $plan): void
{
    $binary=(string)$plan['apache']['control_binary'];
    if(!str_starts_with($binary,'/')||!is_file($binary)||!is_executable($binary))apply42Stop('Apache control-binary ontbreekt of is niet absoluut.');
    [$vCode, $vOut, $vErr] = apply42Run([$binary, '-v']);
    $versieTekst = trim($vOut . "\n" . $vErr);
    if ($vCode !== 0 || preg_match('~Apache/([0-9]+\.[0-9]+\.[0-9]+)~', $versieTekst, $m) !== 1) {
        apply42Stop('Apache-versie kon niet betrouwbaar worden vastgesteld.');
    }
    if (version_compare($m[1], (string)$plan['apache']['minimum_version'], '<')) {
        apply42Stop('Apache ' . $m[1] . ' is te oud; minimaal ' . $plan['apache']['minimum_version'] . ' is vereist voor StrictHostCheck.');
    }

    [$mCode, $mOut, $mErr] = apply42Run([$binary, '-M']);
    if ($mCode !== 0) apply42Stop('Apache modulelijst kon niet worden gecontroleerd: ' . ($mErr !== '' ? $mErr : 'onbekende fout'));
    $geladen = [];
    foreach (preg_split('/\r?\n/', $mOut . "\n" . $mErr) ?: [] as $regel) {
        if (preg_match('/\b([a-z0-9_]+_module)\b/i', $regel, $mm) === 1) $geladen[strtolower($mm[1])] = true;
    }
    foreach ($plan['apache']['required_modules'] as $module) {
        if (!isset($geladen[strtolower((string)$module)])) apply42Stop("Vereiste Apache-module ontbreekt: {$module}");
    }
}

function apply42VastePaden(array $plan): array
{
    $sitesAvailable = (string)$plan['apache']['sites_available_dir'];
    $sitesEnabled = (string)$plan['apache']['sites_enabled_dir'];
    $fragmentDir = (string)$plan['apache']['fragment_dir'];
    if (!hash_equals('/etc/apache2/sites-available', $sitesAvailable)
        || !hash_equals('/etc/apache2/sites-enabled', $sitesEnabled)
        || !hash_equals('/etc/verenigingsplatform/apache/fragments', $fragmentDir)) {
        apply42Stop('Fase 4.2 accepteert uitsluitend de vaste Ubuntu/Debian Apache installatiedoelen.');
    }

    try {
        $sitesAvailable = runtime41BestaandPad($sitesAvailable, 'Apache sites-available', true);
        $sitesEnabled = runtime41BestaandPad($sitesEnabled, 'Apache sites-enabled', true);
    } catch (Throwable $e) {
        apply42Stop($e->getMessage());
    }

    if (runtime41SymlinkInPad($fragmentDir) !== null) apply42Stop('Apache fragmentmap mag niet via een symlink lopen.');
    if (!is_dir($fragmentDir)) {
        if (!@mkdir($fragmentDir, 0755, true) && !is_dir($fragmentDir)) apply42Stop('Apache fragmentmap kon niet veilig worden aangemaakt.');
    }
    try { $fragmentDir = runtime41BestaandPad($fragmentDir, 'Apache fragmentmap', true); }
    catch (Throwable $e) { apply42Stop($e->getMessage()); }
    foreach ([dirname(dirname($fragmentDir)), dirname($fragmentDir)] as $parent) {
        if (!@chown($parent, 'root') || !@chgrp($parent, 'root')) apply42Stop('Apache fragmentparent kon niet root-owned worden gemaakt: ' . $parent);
    }
    if (!@chown($fragmentDir, 'root') || !@chgrp($fragmentDir, 'root') || !@chmod($fragmentDir, 0755)) {
        apply42Stop('Apache fragmentmap kon niet exact root:root 0755 worden gemaakt.');
    }
    apply42RootMeta($fragmentDir, 0755, true);

    return compact('sitesAvailable', 'sitesEnabled', 'fragmentDir');
}

function apply42Doelen(array $plan, array $dirs): array
{
    return [
        'catchall' => $dirs['sitesAvailable'] . '/' . $plan['apache']['http_catchall_filename'],
        'http' => $dirs['sitesAvailable'] . '/' . $plan['apache']['tenant_http_filename'],
        'fragment' => $dirs['fragmentDir'] . '/' . $plan['apache']['https_routing_fragment_filename'],
    ];
}

function apply42ActiefPad(array $plan, array $dirs, string $type): ?string
{
    $naam = match ($type) {
        'catchall' => (string)$plan['apache']['http_catchall_filename'],
        'http' => (string)$plan['apache']['tenant_http_filename'],
        default => '',
    };
    if ($naam === '') return null;
    return $dirs['sitesEnabled'] . '/' . $naam;
}

function apply42SchrijfRootAtomisch(string $doel, string $inhoud, bool $force, ?string $actiefPad = null): string
{
    $map = dirname($doel);
    if (!is_dir($map) || is_link($map)) apply42Stop("Onveilige Apache doelmap: {$map}");
    if (is_link($doel)) apply42Stop("Apache installatiedoel mag geen symlink zijn: {$doel}");

    $actief = $actiefPad !== null && (is_link($actiefPad) || file_exists($actiefPad));
    if (is_file($doel)) {
        $huidig = @file_get_contents($doel);
        if (!is_string($huidig)) apply42Stop("Bestaand Apache-bestand is niet leesbaar: {$doel}");
        if (hash_equals(hash('sha256', $huidig), hash('sha256', $inhoud))) {
            if (!@chown($doel, 'root') || !@chgrp($doel, 'root') || !@chmod($doel, 0644)) apply42Stop('Ongewijzigde Apache-config kon niet naar root:root 0644 worden genormaliseerd.');
            apply42RootMeta($doel, 0644);
            return 'ongewijzigd';
        }
        if ($actief) apply42Stop('Afwijkend Apache-sitebestand is al actief; fase 4.2 wijzigt nooit live sites-enabled configuratie.');
        if (!$force) apply42Stop('Afwijkend INACTIEF Apache-bestand bestaat al; gebruik --force na controle.');
    } elseif (file_exists($doel)) {
        apply42Stop("Apache installatiedoel bestaat maar is geen regulier bestand: {$doel}");
    } elseif ($actief) {
        apply42Stop('sites-enabled bevat een actief/dangling doel zonder veilig sites-available bronbestand.');
    }

    $tmp = $map . '/.' . basename($doel) . '.tmp.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null) apply42Stop('Onveilig tijdelijk Apache installatiedoel.');
    if (@file_put_contents($tmp, $inhoud, LOCK_EX) === false) apply42Stop('Apache-config kon niet tijdelijk worden geschreven.');
    if (!@chown($tmp, 'root') || !@chgrp($tmp, 'root') || !@chmod($tmp, 0644)) {
        @unlink($tmp); apply42Stop('Tijdelijke Apache-config kreeg niet de verplichte root:root 0644 rechten.');
    }
    apply42RootMeta($tmp, 0644);
    clearstatcache(true, $doel);
    if (is_link($doel)) { @unlink($tmp); apply42Stop('Apache installatiedoel werd tijdens write een symlink.'); }
    if (!@rename($tmp, $doel)) { @unlink($tmp); apply42Stop('Apache-config kon niet atomisch worden geplaatst.'); }
    if (!@chown($doel, 'root') || !@chgrp($doel, 'root') || !@chmod($doel, 0644)) {
        apply42Stop('Geïnstalleerde Apache-config kreeg niet de verplichte root:root 0644 rechten.');
    }
    apply42RootMeta($doel, 0644);
    return 'geschreven';
}

function apply42SyntaxTest(array $plan, array $artifactPaden): void
{
    $cmd = [(string)$plan['apache']['control_binary'], '-t'];
    foreach ($artifactPaden as $pad) {
        $cmd[] = '-c';
        $cmd[] = 'Include "' . addcslashes((string)$pad, "\\\"") . '"';
    }
    [$code, $out, $err] = apply42Run($cmd);
    if ($code !== 0) {
        $melding = trim($out . "\n" . $err);
        apply42Stop('Apache syntaxtest inclusief de fase-4.2 artifacts faalde: ' . ($melding !== '' ? $melding : 'onbekende fout'));
    }
}

function apply42Configtest(array $plan): array
{
    [$code, $out, $err] = apply42Run([(string)$plan['apache']['control_binary'], 'configtest']);
    return [$code === 0, trim($out . "\n" . $err)];
}

function apply42Reload(): array
{
    $systemctl = '/usr/bin/systemctl';
    if (!is_file($systemctl) || !is_executable($systemctl)) return [false, 'systemctl ontbreekt of is niet uitvoerbaar.'];
    [$code, $out, $err] = apply42Run([$systemctl, 'reload', 'apache2']);
    return [$code === 0, trim($out . "\n" . $err)];
}

function apply42LiveHttpsVhost(array $plan, array $dirs, string $fragment): void
{
    $tenant = (string)$plan['tenant_key'];
    $host = (string)$plan['canonical_host'];
    $naam = '200-vp-' . $tenant . '-https.conf';
    $available = $dirs['sitesAvailable'] . '/' . $naam;
    $enabled = $dirs['sitesEnabled'] . '/' . $naam;
    if (!is_file($available) || is_link($available) || !is_link($enabled)) {
        apply42Stop('Live-fragment vereist een reeds actieve fase-4.4 tenant HTTPS-vhost.');
    }
    $availableReal = realpath($available);
    $enabledReal = realpath($enabled);
    if ($availableReal === false || $enabledReal === false || !hash_equals($availableReal, $enabledReal)) {
        apply42Stop('Actieve tenant HTTPS-sites-enabled link wijst niet exact naar sites-available.');
    }
    $raw = @file_get_contents($available);
    if (!is_string($raw)
        || !str_contains($raw, '<VirtualHost *:443>')
        || preg_match('/^\s*ServerName\s+' . preg_quote($host, '/') . '\s*$/m', $raw) !== 1
        || substr_count($raw, 'Include "' . $fragment . '"') !== 1) {
        apply42Stop('Actieve tenant HTTPS-vhost is niet exact aan host en routingfragment gebonden.');
    }
}

function apply42LiveFragment(array $context, array $dirs, array $doelen): void
{
    $plan = $context['plan'];
    $doel = $doelen['fragment'];
    $bron = (string)$plan['bundle']['https_routing_fragment'];
    $socket = (string)$plan['php_fpm']['socket'];

    if (@filetype($socket) !== 'socket') apply42Stop('Live-fragment vereist een actieve tenant PHP-FPM socket.');
    if (!is_file($doel) || is_link($doel)) apply42Stop('Geïnstalleerd live routingfragment ontbreekt of is geen regulier bestand.');
    apply42RootMeta($doel, 0644);
    if (!is_file($bron) || is_link($bron)) apply42Stop('Gebonden tenant-routingfragmentbron ontbreekt of is onveilig.');

    apply42LiveHttpsVhost($plan, $dirs, $doel);
    [$voorOk, $voorMelding] = apply42Configtest($plan);
    if (!$voorOk) apply42Stop('Bestaande actieve Apache-config is al ongeldig; live-fragment geweigerd: ' . $voorMelding);

    $nieuw = web42HttpsRoutingFragment($plan);
    $huidig = @file_get_contents($doel);
    if (!is_string($huidig)) apply42Stop('Bestaand routingfragment kon niet worden gelezen.');
    if (hash_equals(hash('sha256', $huidig), hash('sha256', $nieuw))) {
        echo 'LIVE FRAGMENT ONGEWIJZIGD  tenant=' . $plan['tenant_key'] . ' host=' . $plan['canonical_host'] . "\n";
        return;
    }

    // De tenant-lokale kandidaat is reeds byte-exact tegen web-plan.json gevalideerd.
    // Test hem aanvullend tegen de daadwerkelijk geladen Apache-modules vóór de live write.
    apply42SyntaxTest($plan, [$bron]);

    $map = dirname($doel);
    $tmp = $map . '/.' . basename($doel) . '.live.' . bin2hex(random_bytes(8));
    if (runtime41SymlinkInPad($tmp) !== null) apply42Stop('Onveilig tijdelijk live-fragmentpad.');
    if (@file_put_contents($tmp, $nieuw, LOCK_EX) === false) apply42Stop('Live routingfragment kon niet tijdelijk worden geschreven.');
    if (!@chown($tmp, 'root') || !@chgrp($tmp, 'root') || !@chmod($tmp, 0644)) {
        @unlink($tmp); apply42Stop('Tijdelijk live routingfragment kreeg niet root:root 0644.');
    }
    apply42RootMeta($tmp, 0644);

    clearstatcache(true, $doel);
    if (!is_file($doel) || is_link($doel)) {
        @unlink($tmp); apply42Stop('Live routingfragmentdoel wijzigde tijdens staging; cutover geweigerd.');
    }
    if (!@rename($tmp, $doel)) {
        @unlink($tmp); apply42Stop('Live routingfragment kon niet atomisch worden vervangen.');
    }
    apply42RootMeta($doel, 0644);

    [$naOk, $naMelding] = apply42Configtest($plan);
    if (!$naOk) {
        apply42SchrijfRootAtomisch($doel, $huidig, true, null);
        [$herstelOk, $herstelMelding] = apply42Configtest($plan);
        if (!$herstelOk) {
            apply42Stop('Nieuwe live config faalde én rollbackconfig is ongeldig; handmatige interventie vereist: ' . $naMelding . ' / rollback: ' . $herstelMelding, 3);
        }
        apply42Stop('Nieuwe live routingconfig faalde configtest en is atomisch teruggedraaid: ' . $naMelding, 2);
    }

    [$reloadOk, $reloadMelding] = apply42Reload();
    if (!$reloadOk) {
        apply42SchrijfRootAtomisch($doel, $huidig, true, null);
        [$herstelOk, $herstelMelding] = apply42Configtest($plan);
        $herstelReloadOk = false;
        $herstelReloadMelding = '';
        if ($herstelOk) [$herstelReloadOk, $herstelReloadMelding] = apply42Reload();
        if (!$herstelOk || !$herstelReloadOk) {
            apply42Stop('Apache reload faalde en rollback kon niet volledig worden bewezen; handmatige interventie vereist: ' . $reloadMelding . ' / rollback-config: ' . $herstelMelding . ' / rollback-reload: ' . $herstelReloadMelding, 3);
        }
        apply42Stop('Apache reload faalde; vorige routingconfig is hersteld en opnieuw geladen: ' . $reloadMelding, 2);
    }

    echo 'LIVE FRAGMENT APPLY OK  tenant=' . $plan['tenant_key'] . ' host=' . $plan['canonical_host'] . ' document_root=' . $plan['shared_code']['document_root'] . "\n";
}

foreach ($_SERVER['argv'] ?? [] as $arg) {
    if (preg_match('/^--(?:password|hash|secret|dsn|db-password|token|key|certificate|private-key)(?:=|$)/i', (string)$arg) === 1) {
        apply42Stop('Secrets horen niet in fase-4.2 CLI-argumenten.');
    }
}
$opt = getopt('', ['plan:', 'check', 'apply', 'live-fragment', 'force', 'help']);
if (isset($opt['help'])) { apply42Help(); exit(0); }
$planPad = trim((string)($opt['plan'] ?? ''));
if ($planPad === '') apply42Stop('--plan=/absoluut/pad/web-plan.json is verplicht.');
$check = isset($opt['check']);
$apply = isset($opt['apply']);
$liveFragment = isset($opt['live-fragment']);
if (((int)$check + (int)$apply + (int)$liveFragment) !== 1) apply42Stop('Kies exact één van --check, --apply of --live-fragment.');
if ($liveFragment && isset($opt['force'])) apply42Stop('--force is niet toegestaan bij --live-fragment; de live write heeft een eigen strikt rollbackcontract.');

$context = apply42Bundle($planPad);
$plan = $context['plan'];

if ($check) {
    echo 'CHECK OK  tenant=' . $plan['tenant_key'] . ' host=' . $plan['canonical_host'] . ' socket=' . $plan['php_fpm']['socket'] . "\n";
    exit(0);
}

if (PHP_OS_FAMILY !== 'Linux') apply42Stop('Root-apply is uitsluitend voor Linux bedoeld.');
if (!function_exists('posix_geteuid') || posix_geteuid() !== 0) apply42Stop('Root-apply vereist root (EUID 0).');

apply42ApachePreflight($plan);
$dirs = apply42VastePaden($plan);
$doelen = apply42Doelen($plan, $dirs);

if ($liveFragment) {
    apply42LiveFragment($context, $dirs, $doelen);
    exit(0);
}

// Test de gegenereerde, nog tenant-lokale artifacts tegen de daadwerkelijk
// geladen Apache modules/config vóór er iets onder /etc wordt geplaatst.
apply42SyntaxTest($plan, array_keys($context['artifacts']));

$inhoudPerType = [
    'catchall' => web42CatchallConfig($plan),
    'http' => web42TenantHttpConfig($plan),
    'fragment' => web42HttpsRoutingFragment($plan),
];
$force = isset($opt['force']);
$status = [];
foreach ($inhoudPerType as $type => $inhoud) {
    $status[$type] = apply42SchrijfRootAtomisch(
        $doelen[$type],
        $inhoud,
        $force,
        apply42ActiefPad($plan, $dirs, $type)
    );
}

// Controleer ook de exact geïnstalleerde kopieën. Dit activeert ze nog steeds niet.
apply42SyntaxTest($plan, array_values($doelen));

echo 'APPLY OK  tenant=' . $plan['tenant_key'] . ' host=' . $plan['canonical_host'] . "\n";
foreach ($doelen as $type => $pad) echo strtoupper($status[$type]) . '  ' . $pad . "\n";
echo 'INACTIEF: niets is aan sites-enabled toegevoegd en Apache is niet herladen/herstart.' . "\n";
echo 'Volgende stappen: DNS-readiness (4.3), daarna TLS-wrapper/certificaat + volledige configtest/activatie (4.4).' . "\n";