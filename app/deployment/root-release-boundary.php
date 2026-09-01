<?php
// Security boundary voor privileged subprocesses.
// Root-PHP mag nooit applicatiecode uit /srv/verenigingsplatform/current of een
// nog te vertrouwen kandidaat-release uitvoeren. Privileged PHP draait vanuit
// een expliciet geïnstalleerde, root-owned host-engine buiten de releaseboom.

function process521ReleaseRootFromReal(string $real): ?string
{
    if (preg_match('#^(/srv/verenigingsplatform/releases/[0-9a-f]{40})(?:/|$)#D', $real, $m) !== 1) return null;
    return $m[1];
}

function process521ReleaseRoot(string $path): ?string
{
    $real = realpath($path);
    if ($real === false) return null;
    return process521ReleaseRootFromReal($real);
}

function process521TrustedRunnerReleaseRoot(): ?string
{
    return process521ReleaseRoot(__FILE__);
}

function process521HostEngineRoot(): ?string
{
    $configured = getenv('VERENIGINGSPLATFORM_HOST_ENGINE_ROOT');
    if ($configured === false || trim($configured) === '') return null;
    $configured = rtrim(trim($configured), '/');
    if (preg_match('#^/usr/local/libexec/verenigingsplatform/host-engine/([0-9a-f]{40})$#D', $configured, $m) !== 1) {
        throw new RuntimeException('Host-engine root heeft geen toegestane fysieke versiepadvorm.');
    }
    if (is_link($configured) || !is_dir($configured)) {
        throw new RuntimeException('Host-engine root ontbreekt of is een symlink.');
    }
    $real = realpath($configured);
    if ($real === false || !hash_equals($configured, rtrim($real, '/'))) {
        throw new RuntimeException('Host-engine root resolveert niet exact naar zichzelf.');
    }
    $stat = @lstat($configured);
    if (!is_array($stat) || (int)$stat['uid'] !== 0 || (int)$stat['gid'] !== 0 || (((int)$stat['mode'] & 0777) !== 0555)) {
        throw new RuntimeException('Host-engine root wijkt af van root:root/0555.');
    }
    $marker = $configured . '/.host-engine-commit';
    if (is_link($marker) || !is_file($marker)) {
        throw new RuntimeException('Host-engine marker ontbreekt of is onveilig.');
    }
    $markerStat = @lstat($marker);
    $markerRaw = @file_get_contents($marker);
    if (!is_array($markerStat) || (int)$markerStat['uid'] !== 0 || (int)$markerStat['gid'] !== 0
        || (((int)$markerStat['mode'] & 0777) !== 0444) || !is_string($markerRaw)
        || !hash_equals($m[1], trim($markerRaw))) {
        throw new RuntimeException('Host-engine marker of metadata is ongeldig.');
    }
    return $configured;
}

function process521PhpBinary(string $binary): bool
{
    return preg_match('#^/usr/bin/php(?:[0-9]{1,2}\.[0-9]{1,2})?$#D', $binary) === 1;
}

function process521RootPhpBoundary(array $cmd): array
{
    if (PHP_OS_FAMILY !== 'Linux' || !function_exists('posix_geteuid') || posix_geteuid() !== 0) return $cmd;
    if ($cmd === [] || !isset($cmd[0]) || !process521PhpBinary((string)$cmd[0])) return $cmd;

    $hostRoot = process521HostEngineRoot();
    $trustedRelease = $hostRoot === null ? process521TrustedRunnerReleaseRoot() : null;
    if ($hostRoot === null && $trustedRelease === null) return $cmd;

    // PHP lint compileert kandidaatcode maar mag dat nooit als root doen.
    if (($cmd[1] ?? null) === '-l' && isset($cmd[2]) && count($cmd) === 3) {
        $candidate = realpath((string)$cmd[2]);
        $candidateRoot = $candidate === false ? null : process521ReleaseRootFromReal($candidate);
        if ($candidateRoot !== null) {
            $runuser = '/usr/sbin/runuser';
            if (!is_file($runuser) || !is_executable($runuser)) {
                throw new RuntimeException('Kandidaat-PHP lint vereist beschikbare runuser voor privilege-drop.');
            }
            return [$runuser, '-u', 'nobody', '--', (string)$cmd[0], '-l', $candidate];
        }
        return $cmd;
    }

    if (!isset($cmd[1]) || !is_string($cmd[1]) || !str_ends_with(strtolower($cmd[1]), '.php')) return $cmd;
    $child = realpath($cmd[1]);
    $childRoot = $child === false ? null : process521ReleaseRootFromReal($child);
    if ($childRoot === null) return $cmd;

    // Host-engine: nooit PHP uit een applicatierelease als root. Alleen een
    // healthaanroep wordt vervangen door de root-owned host-engine checker.
    if ($hostRoot !== null) {
        if (basename($child) === 'check-vps-health.php') {
            $hostChecker = realpath($hostRoot . '/bin/check-vps-health.php');
            if ($hostChecker === false || !is_file($hostChecker) || is_link($hostChecker)
                || !str_starts_with($hostChecker, $hostRoot . '/')) {
                throw new RuntimeException('Host-engine healthchecker ontbreekt of valt buiten de host-engine.');
            }
            $cmd[1] = $hostChecker;
            return $cmd;
        }
        throw new RuntimeException('Root-PHP naar applicatiereleasecode is vanuit host-engine geblokkeerd.');
    }

    // Compatibiliteit voor een reeds vertrouwde immutable release. Deze route
    // blijft fail-closed en wordt door de nieuwe deploy/systemd paden niet meer
    // als rootentrypoint gebruikt; hij ondersteunt uitsluitend rollback/recovery
    // tijdens de gecontroleerde migratie.
    if ($trustedRelease !== null && hash_equals($trustedRelease, $childRoot)) {
        $cmd[1] = $child;
        return $cmd;
    }
    if ($trustedRelease !== null && basename($child) === 'check-vps-health.php') {
        $trustedChecker = realpath($trustedRelease . '/bin/check-vps-health.php');
        if ($trustedChecker === false || !is_file($trustedChecker) || is_link($trustedChecker)
            || !hash_equals($trustedRelease, process521ReleaseRootFromReal($trustedChecker) ?? '')) {
            throw new RuntimeException('Trusted healthchecker ontbreekt of valt buiten de callerrelease.');
        }
        $cmd[1] = $trustedChecker;
        return $cmd;
    }

    throw new RuntimeException('Root-PHP naar een andere of kandidaat-release is geblokkeerd.');
}
